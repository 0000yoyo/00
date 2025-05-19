<?php
session_start();
require_once 'db_connection.php';

// 確保只有管理員可以訪問
if(!isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true){
    header("location: login.php");
    exit;
}

// 獲取要查看的日誌文件名
$file = isset($_GET['file']) ? $_GET['file'] : '';
$log_content = '';
$error = '';

if ($file) {
    // 確保文件名安全
    $file = basename($file);
    $log_path = __DIR__ . '/logs/' . $file;
    
    if (file_exists($log_path)) {
        $log_content = file_get_contents($log_path);
    } else {
        $error = "找不到日誌文件: " . htmlspecialchars($file);
    }
} else {
    $error = "未指定日誌文件";
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>查看日誌 - 作文批改平台</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-2xl font-bold text-blue-700">查看日誌: <?php echo htmlspecialchars($file); ?></h1>
                <a href="admin_ai_monitor.php" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-arrow-left mr-1"></i>返回監控面板
                </a>
            </div>
            
            <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-4 rounded mb-4">
                <?php echo $error; ?>
            </div>
            <?php else: ?>
            <div class="bg-gray-50 p-4 rounded border overflow-auto" style="max-height: 80vh;">
                <pre class="text-sm whitespace-pre-wrap"><?php echo htmlspecialchars($log_content); ?></pre>
            </div>
            
            <div class="mt-4 flex justify-between">
                <div>
                    <span class="text-sm text-gray-600">
                        文件大小: <?php echo number_format(filesize($log_path) / 1024, 2); ?> KB
                        | 修改時間: <?php echo date('Y-m-d H:i:s', filemtime($log_path)); ?>
                    </span>
                </div>
                <div>
                    <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        <i class="fas fa-print mr-1"></i>列印
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>