<?php
session_start();
require_once 'db_connection.php';
require_once 'send_verification_email.php';

// 確保是從註冊流程來的
if (!isset($_SESSION['registration_email'])) {
    header("Location: register.php");
    exit();
}

$email = $_SESSION['registration_email'];
$registration_success = $_SESSION['registration_success'] ?? '';
$resend_message = '';

// 處理重新發送驗證碼
if (isset($_POST['resend_code'])) {
    try {
        // 刪除舊的驗證碼
        $stmt = $conn->prepare("UPDATE users SET verification_code = NULL, verification_expires = NULL WHERE email = ?");
        $stmt->execute([$email]);

        // 生成新的驗證碼
        $new_verification_code = generateVerificationCode();
        $new_verification_expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // 更新資料庫中的驗證碼
        $stmt = $conn->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE email = ?");
        $stmt->execute([$new_verification_code, $new_verification_expires, $email]);

        // 發送新的驗證碼
        $sent_code = sendVerificationEmail($email);
        
        if ($sent_code) {
            // 重置過期時間為5分鐘
            $_SESSION['verification_expire_time'] = time() + (5 * 60);
            $resend_message = "已重新發送驗證碼至 " . htmlspecialchars($email);
        } else {
            $verification_error = "驗證碼重新發送失敗";
        }
    } catch(PDOException $e) {
        $verification_error = "系統錯誤：" . $e->getMessage();
    }
}

// 如果是第一次進入頁面，設定過期時間
if (!isset($_SESSION['verification_expire_time'])) {
    $_SESSION['verification_expire_time'] = time() + (5 * 60); // 5分鐘後過期
}

$verification_error = '';
$time_left = max(0, $_SESSION['verification_expire_time'] - time());

if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['resend_code'])) {
    $input_code = $_POST['verification_code'] ?? '';

    // 檢查是否已過期
    if ($time_left <= 0) {
        $verification_error = "驗證碼已過期，請重新發送";
    } else {
        try {
            // 查詢資料庫中的驗證碼
            $stmt = $conn->prepare("
                SELECT * FROM users 
                WHERE email = :email 
                AND verification_code = :code
                AND verification_expires > CURRENT_TIMESTAMP
            ");
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':code', $input_code);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // 驗證成功，更新使用者狀態
                $update_stmt = $conn->prepare("
                    UPDATE users 
                    SET is_verified = 1, 
                        verification_code = NULL, 
                        verification_expires = NULL 
                    WHERE email = :email
                ");
                $update_stmt->bindParam(':email', $email);
                $update_stmt->execute();

                // 清除 session
                unset($_SESSION['registration_email']);
                unset($_SESSION['registration_success']);
                unset($_SESSION['verification_expire_time']);

                // 設定成功訊息
                $_SESSION['verification_success'] = "電子郵件驗證成功！";
                
                // 使用 JavaScript 顯示彈出視窗
                echo "<script>
                    alert('電子郵件驗證成功！');
                    window.location.href = 'login.php';
                </script>";
                exit();
            } else {
                $verification_error = "驗證碼錯誤或已過期";
            }
        } catch(PDOException $e) {
            $verification_error = "系統錯誤：" . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>電子郵件驗證</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        let countdownTimer;

        function startCountdown(duration) {
            const countdownEl = document.getElementById('countdown');
            let timer = duration;

            // 清除之前的計時器
            if (countdownTimer) {
                clearInterval(countdownTimer);
            }

            countdownTimer = setInterval(function () {
                let minutes = parseInt(timer / 60, 10);
                let seconds = parseInt(timer % 60, 10);

                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;

                countdownEl.textContent = `驗證碼將在 ${minutes}:${seconds} 後過期`;

                if (--timer < 0) {
                    clearInterval(countdownTimer);
                    countdownEl.textContent = "驗證碼已過期";
                }
            }, 1000);
        }

        function resendCode() {
            document.getElementById('resend-form').submit();
        }

        // 頁面載入時開始倒數
        window.onload = function() {
            startCountdown(<?php echo $time_left; ?>);
        };
    </script>
</head>
<body class="bg-gradient-to-br from-blue-100 to-blue-300 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md bg-white shadow-2xl rounded-2xl p-8">
        <h2 class="text-2xl font-bold text-center mb-6">電子郵件驗證</h2>
        
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
            已發送驗證碼至 <?php echo htmlspecialchars($email); ?>
        </div>

        <?php if (!empty($resend_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php echo $resend_message; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($verification_error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($verification_error); ?>
            </div>
        <?php endif; ?>

        <div id="countdown" class="text-center text-blue-600 mb-4"></div>

        <form method="post" class="space-y-4">
            <div>
                <label class="block mb-2">請輸入6位數驗證碼</label>
                <input 
                    type="text" 
                    name="verification_code" 
                    required 
                    maxlength="6"
                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="6位數驗證碼"
                >
            </div>
            <button 
                type="submit" 
                class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition-colors"
            >
                驗證
            </button>
        </form>

        <div class="text-center mt-4">
            <form id="resend-form" method="post" class="inline">
                <input type="hidden" name="resend_code" value="1">
                <span 
                    id="resend-text" 
                    onclick="resendCode()" 
                    class="text-blue-600 hover:underline cursor-pointer"
                >
                    重新發送驗證碼
                </span>
            </form>
        </div>
    </div>
</body>
</html>