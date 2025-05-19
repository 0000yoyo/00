<?php
// 創建 admin_reports.php

session_start();
require_once 'db_connection.php';

// 確保只有管理員可以訪問
if(!isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true){
    header("location: login.php");
    exit;
}

// 獲取報告列表
$reports_dir = __DIR__ . '/reports';
$reports = [];

if (file_exists($reports_dir)) {
    $files = scandir($reports_dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && is_file($reports_dir . '/' . $file)) {
            if (preg_match('/grammar_stats_(\d{8})\.html/', $file, $matches)) {
                $date = $matches[1];
                $year = substr($date, 0, 4);
                $month = substr($date, 4, 2);
                $day = substr($date, 6, 2);
                $formatted_date = "{$year}-{$month}-{$day}";
                
                $reports[] = [
                    'file' => $file,
                    'date' => $formatted_date,
                    'timestamp' => strtotime($formatted_date),
                    'size' => filesize($reports_dir . '/' . $file)
                ];
            }
        }
    }
}

// 按日期排序，最新的在前
usort($reports, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

// 處理報告生成請求
$generation_message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'generate') {
    // 執行報告生成腳本
    $cmd = "php " . escapeshellarg(__DIR__ . '/generate_grammar_stats.php');
    exec($cmd, $output, $return_var);
    
    if ($return_var === 0) {
        $generation_message = "報告已成功生成！";
        
        // 重新載入頁面以顯示新報告
        header("Location: admin_reports.php?success=1");
        exit;
    } else {
        $generation_message = "報告生成失敗: " . implode("\n", $output);
    }
}

// 處理成功消息
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $generation_message = "報告已成功生成！";
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>系統報告 - 作文批改平台</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-blue-700">系統報告</h1>
                <a href="admin_ai_monitor.php" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-arrow-left mr-1"></i>返回監控面板
                </a>
            </div>
            
            <?php if (!empty($generation_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($generation_message); ?>
            </div>
            <?php endif; ?>
            
            <!-- 生成報告按鈕 -->
            <div class="mb-6">
                <form method="post">
                    <input type="hidden" name="action" value="generate">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        <i class="fas fa-sync-alt mr-1"></i> 生成最新報告
                    </button>
                </form>
            </div>
            
            <!-- 報告列表 -->
            <h2 class="text-xl font-semibold mb-4">可用報告</h2>
            
            <?php if (empty($reports)): ?>
            <div class="bg-gray-50 p-4 rounded text-center">
                尚未生成任何報告
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">報告日期</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">檔案名稱</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">檔案大小</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($reports as $report): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php echo htmlspecialchars($report['date']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php echo htmlspecialchars($report['file']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php echo round($report['size'] / 1024, 2); ?> KB
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="reports/<?php echo urlencode($report['file']); ?>" 
                                   class="text-blue-600 hover:text-blue-900 mr-3" target="_blank">
                                   <i class="fas fa-eye mr-1"></i> 查看
                                </a>
                                <a href="reports/<?php echo urlencode($report['file']); ?>" 
                                   class="text-green-600 hover:text-green-900" download>
                                   <i class="fas fa-download mr-1"></i> 下載
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>