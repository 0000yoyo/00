<?php
session_start();
require_once 'db_connection.php';

// 確保只有教師待審核使用者可以訪問
if(!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] != 'teacher_pending'){
    header("location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>教師審核中</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-100 to-blue-300 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md bg-white shadow-2xl rounded-2xl p-8 text-center">
        <h2 class="text-2xl font-bold text-blue-800 mb-6">帳號審核中</h2>
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
            <p>您的教師帳號正在等待管理員審核</p>
            <p>審核通過後，您將可以正常使用系統</p>
        </div>
        <a href="logout.php" class="text-blue-600 hover:underline">登出</a>
    </div>
</body>
</html>