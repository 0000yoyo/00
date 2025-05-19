<?php
session_start();
require_once 'db_connection.php';

// 如果已經登入，重新導向
if(isset($_SESSION["is_admin"]) && $_SESSION["is_admin"] === true){
    header("location: admin_dashboard.php");
    exit;
}

$login_error = '';

if($_SERVER["REQUEST_METHOD"] == "POST"){
    // 接收並過濾輸入
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // 指定的管理員帳號
    $ADMIN_EMAIL = 'system_admin@platform.edu';
    $ADMIN_PASSWORD = 'Admin2025!Sys';

    // 基本驗證
    if(empty($email) || empty($password)){
        $login_error = "請輸入電子郵件和密碼";
    } else {
        // 直接比對帳號密碼
        if($email === $ADMIN_EMAIL && $password === $ADMIN_PASSWORD) {
            // 登入成功
            $_SESSION["loggedin"] = true;
            $_SESSION["is_admin"] = true;
            
            // 重新導向到管理員儀表板
            header("location: admin_dashboard.php");
            exit;
        } else {
            $login_error = "帳號或密碼錯誤";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>管理員登入</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-center mb-6 text-blue-800">管理員登入</h2>

        <?php if(!empty($login_error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
            <?php echo htmlspecialchars($login_error); ?>
        </div>
        <?php endif; ?>

        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">電子郵件</label>
                <input 
                    type="email" 
                    name="email" 
                    id="email" 
                    required 
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                >
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">密碼</label>
                <input 
                    type="password" 
                    name="password" 
                    id="password" 
                    required 
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                >
            </div>
            <div>
                <button type="submit" class="w-full bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    登入
                </button>
            </div>
        </form>
    </div>
</body>
</html>