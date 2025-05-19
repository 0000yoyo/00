<?php
// targeted_clean_grammar_rules.php - 針對特定類型的單字規則清理
require_once 'db_connection.php';

// 設定執行環境
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 記錄開始時間和操作日誌
$start_time = microtime(true);
$log_messages = ['開始針對性清理單字文法規則 - ' . date('Y-m-d H:i:s')];

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

// 定義保留的類型 - 拼寫錯誤不過濾單字規則
$preserve_single_word_types = ['spelling'];

// 定義要過濾的常見單字列表（這些單字的規則將被移除）
$common_single_words = [
    'is', 'am', 'are', 'was', 'were', 'be', 'been', 'being',  // be動詞
    'and', 'or', 'but', 'so', 'yet', 'for', 'nor', // 連接詞
    'to', 'from', 'in', 'on', 'at', 'with', 'by', 'for', 'of', 'about', // 介詞
    'the', 'a', 'an', // 冠詞
    'it', 'they', 'we', 'he', 'she', 'you', 'i', // 代名詞
    'this', 'that', 'these', 'those', // 指示代名詞
    'do', 'does', 'did', 'done', // do動詞
    'can', 'could', 'will', 'would', 'shall', 'should', 'may', 'might', 'must', // 情態動詞
    'up', 'down', 'out', 'off', 'on', 'in', // 副詞/介词
    'has', 'have', 'had', 'having' // have動詞
];

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
    
    // 統計資訊
    $total_rules_before = 0;
    $total_removed = 0;
    $total_preserved = 0;
    $removed_by_type = [];
    
    // 處理每種錯誤類型的規則
    foreach ($grammar_rules['rules'] as $error_type => &$rules) {
        $total_rules_before += count($rules);
        $original_count = count($rules);
        $removed_count = 0;
        
        // 是否要保留此類型的單字規則
        $preserve_type = in_array($error_type, $preserve_single_word_types);
        
        // 過濾規則
        $filtered_rules = [];
        foreach ($rules as $rule) {
            if (empty($rule['original'])) {
                continue;
            }
            
            // 檢查是否是單字規則
            $original_text = trim($rule['original']);
            $words = array_filter(explode(' ', $original_text));
            $is_single_word = (count($words) === 1);
            
            // 過濾邏輯：
            // 1. 多字規則始終保留
            // 2. 對於單字規則：
            //    a. 如果是保留類型(spelling)，保留
            //    b. 如果不是保留類型，檢查是否是常見單字
            $should_remove = false;
            
            if ($is_single_word) {
                $single_word = strtolower($original_text);
                
                if (!$preserve_type && in_array($single_word, $common_single_words)) {
                    $should_remove = true;
                    $removed_count++;
                }
            }
            
            if (!$should_remove) {
                $filtered_rules[] = $rule;
            }
        }
        
        // 更新規則
        $rules = $filtered_rules;
        $preserved_count = count($rules);
        
        $removed_by_type[$error_type] = $removed_count;
        $total_removed += $removed_count;
        $total_preserved += $preserved_count;
        
        $log_messages[] = "錯誤類型 '{$error_type}': 原有 {$original_count} 條規則，移除了 {$removed_count} 條常見單字規則，保留 {$preserved_count} 條規則";
    }
    
    // 計算統計信息
    $percent_removed = ($total_rules_before > 0) 
        ? round(($total_removed / $total_rules_before) * 100, 2) 
        : 0;
    
    $log_messages[] = "總計: 原有 {$total_rules_before} 條規則";
    $log_messages[] = "移除了 {$total_removed} 條常見單字規則 ({$percent_removed}%)";
    $log_messages[] = "保留了 {$total_preserved} 條規則";
    
    // 按類型輸出詳細移除情況
    $log_messages[] = "\n各類型移除詳情:";
    foreach ($removed_by_type as $type => $count) {
        if ($count > 0) {
            $type_desc = isset($grammar_rules['descriptions'][$type]) ? $grammar_rules['descriptions'][$type] : $type;
            $log_messages[] = "- {$type_desc}: 移除了 {$count} 條常見單字規則";
        }
    }
    
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