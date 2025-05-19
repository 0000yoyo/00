<?php
// clean_grammar_rules.php - 清理單字文法規則

require_once 'db_connection.php';

// 設定執行環境
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 記錄開始時間和操作日誌
$start_time = microtime(true);
$log_messages = ['開始清理單字文法規則 - ' . date('Y-m-d H:i:s')];

// 載入現有的文法規則
$rules_path = __DIR__ . '/models/grammar_rules.pkl';
$backup_path = __DIR__ . '/models/grammar_rules_backup_' . date('Ymd_His') . '.pkl';

// 確保文法規則文件存在
if (!file_exists($rules_path)) {
    $log_messages[] = "錯誤: 找不到文法規則文件: {$rules_path}";
    outputLog($log_messages);
    exit(1);
}

// 創建備份
if (!copy($rules_path, $backup_path)) {
    $log_messages[] = "警告: 無法創建備份文件: {$backup_path}";
} else {
    $log_messages[] = "已創建備份文件: {$backup_path}";
}

// 載入文法規則
try {
    $data = file_get_contents($rules_path);
    $grammar_rules = unserialize($data);
    
    if (!$grammar_rules || !isset($grammar_rules['rules'])) {
        $log_messages[] = "錯誤: 無法解析文法規則文件或格式不正確";
        outputLog($log_messages);
        exit(1);
    }
    
    $log_messages[] = "成功載入文法規則";
    
    // 收集統計信息
    $total_rules_before = 0;
    $single_word_rules = 0;
    $multi_word_rules = 0;
    
    // 處理每種錯誤類型的規則
    foreach ($grammar_rules['rules'] as $error_type => &$rules) {
        $total_rules_before += count($rules);
        $original_count = count($rules);
        
        // 過濾掉單字規則
        $filtered_rules = [];
        foreach ($rules as $rule) {
            if (empty($rule['original'])) {
                continue;
            }
            
            // 計算單詞數量 - 使用空白分割並計算有效單詞數量
            $word_count = count(array_filter(explode(' ', trim($rule['original']))));
            
            if ($word_count > 1) {
                $filtered_rules[] = $rule;
                $multi_word_rules++;
            } else {
                $single_word_rules++;
            }
        }
        
        // 更新規則
        $rules = $filtered_rules;
        $removed_count = $original_count - count($rules);
        
        $log_messages[] = "錯誤類型 '{$error_type}': 移除了 {$removed_count} 條單字規則，保留 " . count($rules) . " 條多字規則";
    }
    
    // 計算移除百分比
    $total_rules_after = $total_rules_before - $single_word_rules;
    $percent_removed = ($total_rules_before > 0) 
        ? round(($single_word_rules / $total_rules_before) * 100, 2) 
        : 0;
    
    $log_messages[] = "總計: 原有 {$total_rules_before} 條規則";
    $log_messages[] = "移除了 {$single_word_rules} 條單字規則 ({$percent_removed}%)";
    $log_messages[] = "保留了 {$total_rules_after} 條多字規則";
    
    // 保存更新後的規則
    if (file_put_contents($rules_path, serialize($grammar_rules)) !== false) {
        $log_messages[] = "成功保存更新後的文法規則";
    } else {
        $log_messages[] = "錯誤: 無法保存更新後的文法規則";
    }
    
} catch (Exception $e) {
    $log_messages[] = "錯誤: " . $e->getMessage();
}

// 記錄執行時間
$execution_time = microtime(true) - $start_time;
$log_messages[] = "清理完成，耗時: " . round($execution_time, 2) . " 秒";

// 輸出日誌
outputLog($log_messages);

/**
 * 輸出日誌到控制台和文件
 */
function outputLog($messages) {
    // 輸出到控制台
    foreach ($messages as $message) {
        echo $message . "\n";
    }
    
    // 保存到日誌文件
    $log_file = __DIR__ . '/logs/grammar_rules_cleanup_' . date('Ymd') . '.log';
    $log_dir = dirname($log_file);
    
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, implode("\n", $messages) . "\n\n", FILE_APPEND);
}
?>