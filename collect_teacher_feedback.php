<?php
// collect_teacher_feedback.php
require_once 'db_connection.php';

// 設定執行環境
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300); // 允許腳本執行5分鐘

// 輸出HTML頭部
echo '<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>收集教師評分反饋</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-4">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-blue-700 mb-4">收集教師評分反饋</h1>
        <div class="bg-blue-50 p-4 rounded-lg mb-4">正在處理中，請稍候...</div>
        <div class="bg-gray-50 p-4 rounded-lg border overflow-auto h-96">
            <pre class="text-sm" id="log">';

// 記錄開始時間
$start_time = microtime(true);
$log_messages = ['開始收集教師評分反饋 - ' . date('Y-m-d H:i:s')];

// 檢查 essays 表是否有 processed_for_training 欄位
$fieldExists = false;
try {
    $check = $conn->query("SHOW COLUMNS FROM essays LIKE 'processed_for_training'");
    $fieldExists = ($check->rowCount() > 0);
    
    if (!$fieldExists) {
        // 添加 processed_for_training 欄位
        $conn->exec("ALTER TABLE essays ADD COLUMN processed_for_training TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已用於AI訓練'");
        $log_messages[] = '成功添加 processed_for_training 欄位到 essays 表格';
    }
} catch (Exception $e) {
    $log_messages[] = '檢查/添加欄位時出錯: ' . $e->getMessage();
}

// 檢查是否存在 ai_training_logs 表
$tableExists = false;
try {
    $check = $conn->query("SHOW TABLES LIKE 'ai_training_logs'");
    $tableExists = ($check->rowCount() > 0);
    
    if (!$tableExists) {
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
        $log_messages[] = '成功創建 ai_training_logs 表格';
        $tableExists = true;
    }
} catch (Exception $e) {
    $log_messages[] = '檢查/創建表格時出錯: ' . $e->getMessage();
}

// 原有的處理邏輯...
try {
    // 獲取最近評分但未處理的教師反饋數據
    $stmt = $conn->prepare("
        SELECT 
            e.id as essay_id,
            e.user_id,
            e.teacher_id,
            e.ai_score,
            e.score as teacher_score,
            e.score_difference,
            e.ai_feedback,
            e.teacher_feedback,
            e.feedback_difference,
            e.category,
            e.content,
            m.id as metrics_id
        FROM essays e
        LEFT JOIN essay_metrics m ON e.ai_metrics_id = m.id
        WHERE 
            e.teacher_review = 1 
            AND e.status = 'graded'
            AND e.ai_score IS NOT NULL
            AND e.score IS NOT NULL
            AND (e.score_difference IS NOT NULL OR e.score_difference != 0)
            " . ($fieldExists ? "AND e.processed_for_training = 0" : "") . "
        ORDER BY e.graded_at DESC
        LIMIT 100
    ");
    $stmt->execute();
    $feedback_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $log_messages[] = '找到 ' . count($feedback_data) . ' 條未處理的教師評分反饋';
    
    if (!empty($feedback_data)) {
        // 處理每條反饋
        foreach ($feedback_data as $data) {
            try {
                // 提取基本特徵數據
                $essay_id = $data['essay_id'];
                $content = $data['content'];
                $category = $data['category'];
                $ai_score = $data['ai_score'];
                $teacher_score = $data['teacher_score'];
                $score_diff = $data['score_difference'] ?? ($teacher_score - $ai_score);
                
                // 更新或創建評分指標記錄
                if ($data['metrics_id']) {
                    // 更新現有指標
                    $stmt = $conn->prepare("
                        UPDATE essay_metrics 
                        SET 
                            teacher_score = ?,
                            score_difference = ?,
                            used_for_training = 1
                        WHERE id = ?
                    ");
                    $stmt->execute([$teacher_score, $score_diff, $data['metrics_id']]);
                } else {
                    // 創建新指標記錄
                    require_once 'score_essay.php';
                    $features = extract_features(clean_text($content));
                    
                    $stmt = $conn->prepare("
                        INSERT INTO essay_metrics
                        (essay_id, user_id, teacher_id, ai_score, teacher_score, 
                        score_difference, word_count, sentence_count, 
                        lexical_diversity, avg_sentence_length, category, used_for_training, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                    ");
                    
                    $stmt->execute([
                        $essay_id,
                        $data['user_id'],
                        $data['teacher_id'],
                        $ai_score,
                        $teacher_score,
                        $score_diff,
                        $features['word_count'],
                        $features['sentence_count'],
                        $features['lexical_diversity'],
                        $features['avg_sentence_length'],
                        $category
                    ]);
                    
                    $metrics_id = $conn->lastInsertId();
                    
                    // 更新作文中的 metrics_id
                    $stmt = $conn->prepare("UPDATE essays SET ai_metrics_id = ? WHERE id = ?");
                    $stmt->execute([$metrics_id, $essay_id]);
                }
                
                // 標記為已處理
                if ($fieldExists) {
                    $stmt = $conn->prepare("UPDATE essays SET processed_for_training = 1 WHERE id = ?");
                    $stmt->execute([$essay_id]);
                }
                
                // 記錄到訓練日誌
                if ($tableExists) {
                    $stmt = $conn->prepare("
                        INSERT INTO ai_training_logs 
                        (feedback_id, process_type, status, processed_at, notes)
                        VALUES (?, 'score_feedback', 'processed', NOW(), ?)
                    ");
                    $notes = "處理作文ID {$essay_id} 的評分反饋：AI評分 {$ai_score}，教師評分 {$teacher_score}，差異 {$score_diff}";
                    $stmt->execute([$essay_id, $notes]);
                }
                
                $log_messages[] = "成功處理作文ID {$essay_id} 的評分反饋";
                
                // 輸出即時進度
                echo htmlspecialchars("處理作文ID {$essay_id}...") . "\n";
                flush();
                
            } catch (Exception $e) {
                $log_messages[] = '處理反饋 ID:' . $essay_id . ' 時出錯: ' . $e->getMessage();
            }
        }
    }
    
    // 記錄執行時間
    $execution_time = microtime(true) - $start_time;
    $log_messages[] = '處理完成，耗時: ' . round($execution_time, 2) . ' 秒';
    
} catch (Exception $e) {
    $log_messages[] = '執行過程中發生錯誤: ' . $e->getMessage();
}

// 輸出日誌
writeToLog($log_messages);

// 顯示日誌內容
foreach ($log_messages as $message) {
    echo htmlspecialchars($message) . "\n";
}

/**
 * 寫入日誌文件
 */
function writeToLog($messages) {
    $log_file = __DIR__ . '/logs/teacher_feedback_' . date('Ymd') . '.log';
    
    // 確保日誌目錄存在
    $log_dir = dirname($log_file);
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_content = implode("\n", $messages) . "\n\n";
    file_put_contents($log_file, $log_content, FILE_APPEND);
}

echo '</pre>
        </div>
        <div class="mt-4">
            <a href="admin_ai_monitor.php" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">返回監控面板</a>
        </div>
    </div>
</body>
</html>';
?>