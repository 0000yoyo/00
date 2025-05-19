<?php
require_once 'db_connection.php';
// 初始化 session
session_start();

// 顯示驗證成功或失敗訊息
$verification_success = '';
$verification_error = '';
$registration_success = '';

// 檢查是否已經登入
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    // 根據使用者類型跳轉到不同頁面
    if($_SESSION["user_type"] == 'student') {
        header("location: student_dashboard.php");
    } else {
        header("location: teacher_dashboard.php");
    }
    exit;
}

// 處理登入邏輯
$login_err = "";

// 檢查是否有註冊成功的訊息
if (isset($_SESSION['registration_success'])) {
    $registration_success = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']); // 清除訊息
}

// 檢查是否有驗證成功的訊息
if (isset($_SESSION['verification_success'])) {
    $verification_success = $_SESSION['verification_success'];
    unset($_SESSION['verification_success']);
}

// 檢查是否有驗證失敗的訊息
if (isset($_SESSION['verification_error'])) {
    $verification_error = $_SESSION['verification_error'];
    unset($_SESSION['verification_error']);
}

// 處理登入邏輯
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // 登入邏輯
    if(isset($_POST['login'])){
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        
        try {
            // 準備 SQL 查詢
            $stmt = $conn->prepare("SELECT id, email, password, user_type, is_verified FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if($user) {
                // 檢查帳號是否已驗證
                if($user['is_verified'] == 0) {
                    $login_err = "請先完成電子郵件驗證";
                } elseif(password_verify($password, $user['password'])) {
                    // 密碼正確，開始 session
                    $_SESSION["loggedin"] = true;
                    $_SESSION["id"] = $user['id'];
                    $_SESSION["email"] = $user['email'];
                    $_SESSION["user_type"] = $user['user_type'];

                    // 根據使用者類型跳轉
                    if($user['user_type'] == 'student') {
                        header("location: student_dashboard.php");
                    } else {
                        header("location: teacher_dashboard.php");
                    }
                    exit;
                } else {
                    $login_err = "密碼錯誤";
                }
            } else {
                $login_err = "找不到該電子郵件的帳號";
            }
        } catch(PDOException $e) {
            $login_err = "登入系統錯誤：" . $e->getMessage();
        }
    }
    
    // 處理註冊邏輯
    if(isset($_POST['register'])){
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        $user_type = trim($_POST['user_type']);
        
        $errors = [];

        // 電子郵件驗證
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "電子郵件格式不正確";
        }

        // 密碼驗證
        function validatePassword($password) {
            $hasUppercase = preg_match('/[A-Z]/', $password);
            $hasLowercase = preg_match('/[a-z]/', $password);
            $hasNumber = preg_match('/\d/', $password);
            $isLengthValid = strlen($password) == 6;

            return $hasUppercase && $hasLowercase && $hasNumber && $isLengthValid;
        }

        if (!validatePassword($password)) {
            $errors[] = "密碼必須是6位數，包含大寫、小寫字母和數字";
        }

        // 確認密碼是否一致
        if ($password !== $confirm_password) {
            $errors[] = "兩次輸入的密碼不一致";
        }

        // 使用者名稱長度驗證
        if (strlen($username) < 2) {
            $errors[] = "使用者名稱至少需要2個字元";
        }

        // 檢查使用者類型
        if (!in_array($user_type, ['student', 'teacher'])) {
            $errors[] = "請選擇正確的使用者類型";
        }

        // 如果沒有錯誤
        if (empty($errors)) {
            try {
                // 檢查電子郵件是否已存在
                $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = "此電子郵件已被註冊";
                } else {
                    // 密碼雜湊
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // 產生驗證碼和過期時間
                    $verification_code = bin2hex(random_bytes(16));
                    $verification_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    // 插入使用者資料
                    $stmt = $conn->prepare("INSERT INTO users (email, username, password, user_type, verification_code, verification_expires, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
                    $stmt->execute([$email, $username, $hashed_password, $user_type, $verification_code, $verification_expires]);

                    // 嘗試發送驗證信
                    require_once 'send_verification_email.php';
                    if (sendVerificationEmail($email, $verification_code)) {
                        $_SESSION['registration_success'] = "註冊成功！請檢查您的電子郵件並完成驗證。";
                        header("Location: login.php");
                        exit();
                    } else {
                        $login_err = "註冊成功，但驗證信發送失敗。";
                    }
                }
            } catch(PDOException $e) {
                $login_err = "系統錯誤：" . $e->getMessage();
            }
        }
    }
}
?>