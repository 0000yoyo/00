<?php
session_start();
require_once 'db_connection.php';

// 開啟所有錯誤報告
error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = '';
$success = '';

// 記錄日誌函數
function logError($message) {
    error_log($message, 3, "reset_password_debug.log");
}

// 密碼驗證函數
function validatePassword($password) {
    $hasUppercase = preg_match('/[A-Z]/', $password);
    $hasLowercase = preg_match('/[a-z]/', $password);
    $hasNumber = preg_match('/\d/', $password);
    $isLengthValid = strlen($password) == 6;

    // 記錄密碼驗證詳情
    logError("Password Validation: " . 
        "Uppercase: " . ($hasUppercase ? 'Yes' : 'No') . ", " .
        "Lowercase: " . ($hasLowercase ? 'Yes' : 'No') . ", " .
        "Number: " . ($hasNumber ? 'Yes' : 'No') . ", " .
        "Length: " . ($isLengthValid ? 'Valid' : 'Invalid')
    );

    return $hasUppercase && $hasLowercase && $hasNumber && $isLengthValid;
}

// 檢查是否有有效的重設token
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    logError("Received reset token: " . $token);

    try {
        // 檢查token是否有效且未過期
        $stmt = $conn->prepare("
            SELECT * FROM users 
            WHERE reset_token = ? 
            AND reset_expires > CURRENT_TIMESTAMP
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "無效或已過期的重設連結";
            logError("Invalid or expired token: " . $token);
        }
    } catch(PDOException $e) {
        $error = "系統錯誤：" . $e->getMessage();
        logError("Database error: " . $e->getMessage());
    }
} 

// 處理密碼重設提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    logError("POST request received");
    
    $token = $_POST['token'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    logError("Submitted data - Token: $token, New Password Length: " . strlen($new_password));

    // 驗證密碼
    if (!validatePassword($new_password)) {
        $error = "密碼必須是6位數，包含大寫、小寫字母和數字";
        logError("Password validation failed");
    } else if ($new_password !== $confirm_password) {
        $error = "兩次輸入的密碼不一致";
        logError("Passwords do not match");
    } else {
        try {
            // 檢查token是否有效
            $stmt = $conn->prepare("
                SELECT * FROM users 
                WHERE reset_token = ? 
                AND reset_expires > CURRENT_TIMESTAMP
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // 更新密碼
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET password = ?, 
                        reset_token = NULL, 
                        reset_expires = NULL 
                    WHERE email = ?
                ");
                $stmt->execute([$hashed_password, $user['email']]);

                logError("Password reset successful for email: " . $user['email']);

                // 使用 JavaScript 顯示成功訊息並重定向
                echo "<script>
                    alert('密碼重設成功！');
                    window.location.href = 'login.php';
                </script>";
                exit();
            } else {
                $error = "無效或已過期的重設連結";
                logError("Invalid or expired token during password reset");
            }
        } catch(PDOException $e) {
            $error = "系統錯誤：" . $e->getMessage();
            logError("Database error during password reset: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>重設密碼</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-100 to-blue-300 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md bg-white shadow-2xl rounded-2xl p-8">
        <h2 class="text-2xl font-bold text-center text-blue-800 mb-6">重設密碼</h2>

        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($token) && !$error): ?>
        <form method="post" class="space-y-4">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div>
                <label class="block mb-2">新密碼</label>
                <input 
                    type="password" 
                    name="new_password" 
                    required 
                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="6位數，包含大小寫和數字"
                >
            </div>

            <div>
                <label class="block mb-2">確認新密碼</label>
                <input 
                    type="password" 
                    name="confirm_password" 
                    required 
                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="再次輸入新密碼"
                >
            </div>

            <button 
                type="submit" 
                class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition-colors"
            >
                重設密碼
            </button>
        </form>
        <?php else: ?>
            <div class="text-center text-red-600">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>