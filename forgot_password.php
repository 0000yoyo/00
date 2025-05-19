<?php
session_start();
require_once 'db_connection.php';
require_once 'send_verification_email.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// 初始化錯誤和成功訊息
$error = '';
$success = '';

// 產生重設密碼的隨機token
function generateResetToken() {
    return bin2hex(random_bytes(32)); // 64個字元的隨機字串
}

// 處理忘記密碼請求
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    // 驗證電子郵件
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "請輸入有效的電子郵件地址";
    } else {
        try {
            // 檢查電子郵件是否存在
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // 產生重設密碼的token
                $reset_token = generateResetToken();
                $token_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // 將token存入資料庫
                $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
                $stmt->execute([$reset_token, $token_expires, $email]);

                // 發送重設密碼的郵件
                $reset_link = "http://localhost/Project/reset_password.php?token=" . $reset_token;

                // 使用 PHPMailer 發送郵件
                $mail = new PHPMailer(true);
				$mail->CharSet = 'UTF-8';  // 設定字元編碼
				$mail->Encoding = 'base64'; // 設定編碼方式
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'mailforproject12345@gmail.com';
                $mail->Password   = 'bmnh forg xofu piez';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->setFrom('mailforproject12345@gmail.com', 'Essay Platform');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = '作文批改平台帳戶密碼重置';
                $mail->Body    = "
                    <p>您收到這封信是因為您申請重設密碼</p>
                    <p>請點擊以下連結重設密碼：</p>
                    <p><a href='{$reset_link}'>重設密碼</a></p>
                    <p>如果這不是您本人的操作，請忽略此信</p>
                    <p>此連結將在1小時後失效</p>
                ";

                try {
                    if($mail->send()) {
                        $success = "重設密碼的連結已發送到您的電子郵件。";
                    } else {
                        $error = "郵件發送失敗：" . $mail->ErrorInfo;
                    }
                } catch (Exception $e) {
                    $error = "郵件發送異常：" . $e->getMessage();
                }
            } else {
                $error = "找不到該電子郵件的帳號";
            }
        } catch(PDOException $e) {
            $error = "系統錯誤：" . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>忘記密碼</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-100 to-blue-300 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md bg-white shadow-2xl rounded-2xl p-8">
        <h2 class="text-2xl font-bold text-center text-blue-800 mb-6">忘記密碼</h2>

        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <div>
                <label class="block mb-2">請輸入註冊時的電子郵件</label>
                <input 
                    type="email" 
                    name="email" 
                    required 
                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="請輸入電子郵件"
                >
            </div>
            <button 
                type="submit" 
                class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition-colors"
            >
                發送重設密碼連結
            </button>
        </form>

        <div class="text-center mt-4">
            <a href="login.php" class="text-blue-600 hover:underline">返回登入</a>
        </div>
    </div>
</body>
</html>