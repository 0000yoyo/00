<?php
// schedule_ai_training.php
require_once 'db_connection.php';

// 設定執行環境
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300);

$log_messages = ['開始檢查AI訓練調度 - ' . date('Y-m-d H:i:s')];

try {
    // 檢查是否有足夠的新反饋數據來訓練模型
    $stmt = $conn->prepare("
        SELECT COUNT(*) as feedback_count 
        FROM essay_metrics 
        WHERE used_for_training = 1 AND used_in_model = 0
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $feedback_count = $result['feedback_count'];
    
    $log_messages[] = "找到 {$feedback_count} 條未用於模型訓練的反饋數據";
    
    // 檢查是否有足夠的數據進行訓練
    $threshold = 10; // 至少需要10條反饋才啟動訓練
    
    if ($feedback_count >= $threshold) {
        // 檢查是否有訓練任務正在進行
        $stmt = $conn->prepare("
            SELECT COUNT(*) as running_count 
            FROM ai_training_logs 
            WHERE process_type = 'model_training' 
            AND (status = 'pending' OR status = 'processing')
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $running_count = $result['running_count'];
        
        if ($running_count == 0) {
            // 計算新版本號
            $stmt = $conn->prepare("
                SELECT MAX(CAST(SUBSTRING_INDEX(model_version, '.', -1) AS UNSIGNED)) as last_patch
                FROM model_training
                WHERE model_version LIKE '1.0.%'
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $last_patch = $result['last_patch'] ?? 0;
            $new_version = "1.0." . ($last_patch + 1);
            
            // 創建訓練任務
            $stmt = $conn->prepare("
                INSERT INTO ai_training_logs 
                (process_type, status, notes)
                VALUES ('model_training', 'pending', ?)
            ");
            $notes = "計劃訓練 {$new_version} 版模型，基於 {$feedback_count} 條反饋數據";
            $stmt->execute([$notes]);
            $task_id = $conn->lastInsertId();
            
            $log_messages[] = "創建訓練任務 (ID: {$task_id})，版本 {$new_version}";
            
            // 執行訓練腳本
            $command = "php execute_model_training.php --task_id={$task_id} --version={$new_version} > /dev/null 2>&1 &";
            exec($command);
            
            $log_messages[] = "已啟動訓練腳本: {$command}";
        } else {
            $log_messages[] = "已有訓練任務正在執行，跳過新訓練";
        }
    } else {
        $log_messages[] = "反饋數據數量不足 (需要至少 {$threshold} 條，當前有 {$feedback_count} 條)，跳過訓練";
    }
    
} catch (Exception $e) {
    $log_messages[] = '執行過程中發生錯誤: ' . $e->getMessage();
}

// 輸出日誌
writeToLog($log_messages);

function writeToLog($messages) {
    $log_file = __DIR__ . '/logs/ai_training_scheduler_' . date('Ymd') . '.log';
    
    // 確保日誌目錄存在
    $log_dir = dirname($log_file);
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_content = implode("\n", $messages) . "\n\n";
    file_put_contents($log_file, $log_content, FILE_APPEND);
    
    // 同時輸出到控制台
    echo $log_content;
}
?>