<?php
// execute_model_training.php
require_once 'db_connection.php';

// 設定執行環境
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(600); // 允許腳本執行10分鐘

// 解析命令行參數
$options = getopt('', ['task_id:', 'version:']);
$task_id = isset($options['task_id']) ? (int)$options['task_id'] : 0;
$version = isset($options['version']) ? $options['version'] : '';

if (!$task_id || !$version) {
    die("錯誤: 缺少必要參數 task_id 或 version\n");
}

// 設置日誌文件
$log_file = __DIR__ . '/logs/model_training_' . $task_id . '_' . date('Ymd_His') . '.log';

// 更新任務狀態為處理中
try {
    $stmt = $conn->prepare("
        UPDATE ai_training_logs
        SET status = 'processing', processed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$task_id]);
    
    logMessage("開始執行模型訓練任務 ID: {$task_id}, 版本: {$version}");
    
    // 準備訓練數據
    // 1. 收集教師反饋數據
    $stmt = $conn->prepare("
        SELECT 
            em.essay_id,
            e.content,
            e.category,
            em.ai_score,
            em.teacher_score,
            em.score_difference
        FROM essay_metrics em
        JOIN essays e ON em.essay_id = e.id
        WHERE em.used_for_training = 1 AND em.used_in_model = 0
        LIMIT 1000
    ");
    $stmt->execute();
    $feedback_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("收集了 " . count($feedback_data) . " 條教師反饋數據");
    
    // 2. 將反饋數據保存為臨時文件
    $temp_dir = __DIR__ . '/data/temp';
    if (!file_exists($temp_dir)) {
        mkdir($temp_dir, 0755, true);
    }
    
    $feedback_file = $temp_dir . '/feedback_data_' . $task_id . '.json';
    file_put_contents($feedback_file, json_encode($feedback_data, JSON_UNESCAPED_UNICODE));
    
    logMessage("反饋數據已保存到: {$feedback_file}");
    
    // 3. 執行Python訓練腳本
    $dataset_file = __DIR__ . '/data/essays/training_set_rel3.tsv';  // 基本訓練集
    
    // 設定默認的 essay_set 值為 1
    $essay_set = 1;
    
    // 檢查並驗證訓練腳本和數據集文件
    $script_path = __DIR__ . '/scripts/train_model.py';
    if (!file_exists($script_path)) {
        throw new Exception("找不到訓練腳本：{$script_path}");
    }
    
    if (!file_exists($dataset_file)) {
        throw new Exception("找不到數據集文件：{$dataset_file}");
    }
    
    // 使用批處理文件來激活 Conda 環境並執行 Python 腳本
    $bat_file = $temp_dir . '/run_model_' . $task_id . '.bat';
    $bat_content = "@echo off\r\n";
    $bat_content .= "call C:\\Users\\Henry\\anaconda3\\Scripts\\activate.bat C:\\Users\\Henry\\anaconda3\r\n";
    $bat_content .= "call conda activate essay_env\r\n";  // 使用新創建的環境
    $bat_content .= "python \"" . $script_path . "\" ";
    $bat_content .= "--data \"" . $dataset_file . "\" ";
    $bat_content .= "--feedback \"" . $feedback_file . "\" ";
    $bat_content .= "--essay_set " . $essay_set . " ";
    $bat_content .= "--version \"" . $version . "\"\r\n";
    $bat_content .= "echo 退出代碼: %errorlevel%\r\n";
    
    file_put_contents($bat_file, $bat_content);
    logMessage("創建批處理文件: " . $bat_file);
    
    // 執行批處理文件
    $command = "cmd /c \"" . $bat_file . "\"";
    logMessage("執行命令: " . $command);
    
    $output = [];
    $return_var = 0;
    exec($command, $output, $return_var);
    
    logMessage("命令輸出: " . implode("\n", $output));
    
    // 4. 檢查訓練結果
    if ($return_var === 0) {
        // 訓練成功
        logMessage("模型訓練成功!");
        
        // 標記已使用的反饋數據
        $essay_ids = array_column($feedback_data, 'essay_id');
        
        if (!empty($essay_ids)) {
            $placeholders = implode(',', array_fill(0, count($essay_ids), '?'));
            
            $stmt = $conn->prepare("
                UPDATE essay_metrics
                SET used_in_model = 1
                WHERE essay_id IN ({$placeholders})
            ");
            $stmt->execute($essay_ids);
            
            logMessage("已更新 " . count($essay_ids) . " 條數據為已用於模型");
        } else {
            logMessage("沒有資料需要更新");
        }
        
        // 更新任務狀態為成功
        $stmt = $conn->prepare("
            UPDATE ai_training_logs
            SET status = 'success', notes = CONCAT(IFNULL(notes, ''), '\n模型訓練成功')
            WHERE id = ?
        ");
        $stmt->execute([$task_id]);
        
    } else {
        // 訓練失敗
        logMessage("模型訓練失敗! 返回代碼: {$return_var}");
        
        // 檢查數據集和反饋文件是否存在
        logMessage("檢查數據集文件...");
        if (file_exists($dataset_file)) {
            $file_size = filesize($dataset_file);
            logMessage("數據集文件存在: {$dataset_file}，大小: " . round($file_size / 1024, 2) . " KB");
        } else {
            logMessage("數據集文件不存在: {$dataset_file}");
        }
        
        // 檢查反饋文件
        if (file_exists($feedback_file)) {
            $feedback_size = filesize($feedback_file);
            logMessage("反饋文件存在: {$feedback_file}，大小: " . round($feedback_size / 1024, 2) . " KB");
        } else {
            logMessage("反饋文件不存在: {$feedback_file}");
        }
        
        // 創建一個簡單測試腳本
        $test_script = $temp_dir . '/test_script.py';
        $test_content = "# -*- coding: utf-8 -*-\n";
        $test_content .= "print('測試腳本執行')\n";
        $test_content .= "try:\n";
        $test_content .= "    import numpy\n";
        $test_content .= "    import pandas\n";
        $test_content .= "    import sklearn\n";
        $test_content .= "    print('所有必要模組導入成功')\n";
        $test_content .= "except Exception as e:\n";
        $test_content .= "    print(f'導入錯誤: {e}')\n";
        
        file_put_contents($test_script, $test_content);
        
        // 創建測試批處理文件
        $test_bat = $temp_dir . '/test_' . $task_id . '.bat';
        $test_bat_content = "@echo off\r\n";
        $test_bat_content .= "call C:\\Users\\Henry\\anaconda3\\Scripts\\activate.bat C:\\Users\\Henry\\anaconda3\r\n";
        $test_bat_content .= "call conda activate essay_env\r\n";  // 使用新創建的環境
        $test_bat_content .= "python \"" . $test_script . "\"\r\n";
        
        file_put_contents($test_bat, $test_bat_content);
        
        // 執行測試批處理文件
        $test_cmd = "cmd /c \"" . $test_bat . "\"";
        exec($test_cmd, $test_output, $test_return);
        
        logMessage("測試腳本返回: " . $test_return);
        if (!empty($test_output)) {
            logMessage("測試腳本輸出: " . implode("\n", $test_output));
        }
        
        // 更新任務狀態為失敗
        $stmt = $conn->prepare("
            UPDATE ai_training_logs
            SET status = 'failed', notes = CONCAT(IFNULL(notes, ''), '\n模型訓練失敗: 返回代碼 {$return_var}')
            WHERE id = ?
        ");
        $stmt->execute([$task_id]);
    }
    
} catch (Exception $e) {
    logMessage("執行過程中發生錯誤: " . $e->getMessage());
    
    // 更新任務狀態為失敗
    try {
        $stmt = $conn->prepare("
            UPDATE ai_training_logs
            SET status = 'failed', notes = CONCAT(IFNULL(notes, ''), '\n錯誤: " . $e->getMessage() . "')
            WHERE id = ?
        ");
        $stmt->execute([$task_id]);
    } catch (Exception $e2) {
        logMessage("更新任務狀態時出錯: " . $e2->getMessage());
    }
}

/**
 * 寫入日誌文件
 */
function logMessage($message) {
    global $log_file;
    
    $log_dir = dirname($log_file);
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] {$message}\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    // 同時輸出到控制台
    echo $log_entry;
}
?>