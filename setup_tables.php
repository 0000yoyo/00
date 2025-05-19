<?php
// setup_tables.php - 設置必要的資料表和欄位
require_once 'db_connection.php';

// 設定執行環境
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 輸出HTML頭部
echo '<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>設置 AI 系統資料表</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-4">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-blue-700 mb-4">設置 AI 系統資料表</h1>
        <div class="bg-blue-50 p-4 rounded-lg mb-4">正在檢查和創建必要的資料表...</div>
        <div class="bg-gray-50 p-4 rounded-lg border">';

$setup_logs = [];
$success = true;

try {
    // 1. 檢查 ai_grammar_feedback 表是否存在
    $result = $conn->query("SHOW TABLES LIKE 'ai_grammar_feedback'");
    if ($result->rowCount() == 0) {
        // 創建表格
        $sql = "CREATE TABLE `ai_grammar_feedback` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `essay_id` int(11) NOT NULL,
            `teacher_id` int(11) NOT NULL,
            `feedback_type` enum('missed_issue','false_positive','general') NOT NULL,
            `wrong_expression` varchar(255) DEFAULT NULL,
            `correct_expression` varchar(255) DEFAULT NULL,
            `comment` text DEFAULT NULL,
            `processed` tinyint(1) NOT NULL DEFAULT 0,
            `processed_at` datetime DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `essay_id` (`essay_id`),
            KEY `teacher_id` (`teacher_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $conn->exec($sql);
        $setup_logs[] = "✅ 成功創建 ai_grammar_feedback 表格";
    } else {
        $setup_logs[] = "ℹ️ ai_grammar_feedback 表格已存在";
    }
    
    // 2. 檢查 ai_training_logs 表是否存在
    $result = $conn->query("SHOW TABLES LIKE 'ai_training_logs'");
    if ($result->rowCount() == 0) {
        // 創建表格
        $sql = "CREATE TABLE `ai_training_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `feedback_id` int(11) DEFAULT NULL,
            `process_type` enum('grammar_feedback','score_feedback','model_training') NOT NULL,
            `status` enum('pending','processing','failed','success','processed') NOT NULL DEFAULT 'pending',
            `processed_at` datetime DEFAULT NULL,
            `notes` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `feedback_id` (`feedback_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $conn->exec($sql);
        $setup_logs[] = "✅ 成功創建 ai_training_logs 表格";
    } else {
        $setup_logs[] = "ℹ️ ai_training_logs 表格已存在";
    }
    
    // 3. 檢查 essays 表中是否有 processed_for_training 欄位
    $result = $conn->query("SHOW COLUMNS FROM essays LIKE 'processed_for_training'");
    if ($result->rowCount() == 0) {
        // 添加 processed_for_training 欄位
        $sql = "ALTER TABLE essays ADD COLUMN processed_for_training TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已用於AI訓練'";
        $conn->exec($sql);
        $setup_logs[] = "✅ 成功添加 processed_for_training 欄位到 essays 表格";
    } else {
        $setup_logs[] = "ℹ️ processed_for_training 欄位已存在於 essays 表格";
    }
    
    // 4. 檢查日誌目錄是否存在
    $log_dir = __DIR__ . '/logs';
    if (!file_exists($log_dir)) {
        if (mkdir($log_dir, 0755, true)) {
            $setup_logs[] = "✅ 成功創建日誌目錄: {$log_dir}";
        } else {
            $setup_logs[] = "❌ 無法創建日誌目錄: {$log_dir}";
            $success = false;
        }
    } else {
        $setup_logs[] = "ℹ️ 日誌目錄已存在: {$log_dir}";
    }
    
    // 5. 檢查模型目錄是否存在
    $models_dir = __DIR__ . '/models';
    if (!file_exists($models_dir)) {
        if (mkdir($models_dir, 0755, true)) {
            $setup_logs[] = "✅ 成功創建模型目錄: {$models_dir}";
        } else {
            $setup_logs[] = "❌ 無法創建模型目錄: {$models_dir}";
            $success = false;
        }
    } else {
        $setup_logs[] = "ℹ️ 模型目錄已存在: {$models_dir}";
    }
    
} catch (PDOException $e) {
    $setup_logs[] = "❌ 設置資料表時出錯: " . $e->getMessage();
    $success = false;
}

// 輸出設置結果
foreach ($setup_logs as $log) {
    echo "<p class='mb-2'>{$log}</p>";
}

echo '  </div>
        <div class="mt-6 text-center">
            <div class="mb-4">';

if ($success) {
    echo '<div class="bg-green-100 text-green-800 px-4 py-3 rounded">✅ 全部設置完成！您可以開始使用 AI 系統了。</div>';
} else {
    echo '<div class="bg-red-100 text-red-800 px-4 py-3 rounded">⚠️ 設置過程中發生錯誤，請查看上方日誌。</div>';
}

echo '  </div>
            <a href="admin_ai_monitor.php" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                前往 AI 系統監控
            </a>
        </div>
    </div>
</body>
</html>';
?>