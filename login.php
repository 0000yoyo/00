<?php
// 初始化 session
session_start();
require_once 'db_connection.php';

// 檢查是否已登入
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    // 根據使用者類型重新導向
    switch($_SESSION["user_type"]) {
        case 'student':
            header("location: student_dashboard.php");
            exit;
        case 'teacher':
            header("location: teacher_dashboard.php");
            exit;
        case 'teacher_pending':
            header("location: teacher_pending_dashboard.php");
            exit;
        default:
            // 如果使用者類型未知，銷毀 session 並重新導向至登入頁
            session_destroy();
            header("location: login.php");
            exit;
    }
}

// 初始化錯誤訊息
$login_err = "";

// 處理登入邏輯
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // 接收並過濾輸入
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // 驗證輸入
    if(empty($email)){
        $login_err = "請輸入電子郵件";
    } elseif(empty($password)){
        $login_err = "請輸入密碼";
    } else {
        try {
            // 準備 SQL 查詢
            $stmt = $conn->prepare("SELECT id, email, password, username,user_type, is_verified, admin_verified FROM users WHERE email = ?");
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
					$_SESSION["username"] = $user['username']; 
                    
                    // 特殊處理教師帳號
                    if ($user['user_type'] == 'teacher_pending') {
                        header("location: teacher_pending_dashboard.php");
                        exit;
                    }

                    if ($user['user_type'] == 'teacher' && 
                        (!isset($user['admin_verified']) || $user['admin_verified'] == 0)) {
                        $login_err = "您的教師帳號尚未通過管理員審核，請耐心等候";
                    } else {
                        // 根據使用者類型重新導向
                        switch($user['user_type']) {
                            case 'student':
                                header("location: student_dashboard.php");
                                exit;
                            case 'teacher':
                                header("location: teacher_dashboard.php");
                                exit;
                            default:
                                $login_err = "未知的使用者類型";
                        }
                    }
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
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>作文批改平台 - 登入</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-100 to-blue-300 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white shadow-2xl rounded-2xl overflow-hidden">
        <div class="relative">
            <!-- 管理員登入按鈕 -->
            <div class="absolute top-4 right-4">
                <a href="admin_login.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-3 py-1 rounded-md text-sm transition">
                    管理員登入
                </a>
            </div>

            <div class="p-8">
                <h2 class="text-3xl font-bold text-center text-blue-800 mb-6">
                    作文批改平台
                </h2>

                <?php 
                // 顯示錯誤訊息
                if(!empty($login_err)){
                    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">' . 
                         htmlspecialchars($login_err) . 
                         '</div>';
                }
                ?>

                <!-- 其餘登入表單程式碼保持不變 -->
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-4">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input 
                            type="email" 
                            name="email" 
                            required 
                            placeholder="電子郵件" 
                            class="w-full pl-10 pr-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                    </div>

                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input 
                            type="password" 
                            name="password" 
                            required 
                            placeholder="密碼" 
                            class="w-full pl-10 pr-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                    </div>

                    <button 
                        type="submit" 
                        class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition-colors duration-300 flex items-center justify-center"
                    >
                        <i class="fas fa-sign-in-alt mr-2"></i>登入
                    </button>
                </form>

                <div class="text-center mt-4">
                    <p class="text-gray-600">
                        還沒有帳號？
                        <a href="register.php" class="text-blue-600 hover:underline">
                            立即註冊
                        </a>
                    </p>
                </div>

                <div class="text-center mt-4">
                    <a href="forgot_password.php" class="text-blue-600 hover:underline">
                        忘記密碼？
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>