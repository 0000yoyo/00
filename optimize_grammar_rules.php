<?php
// optimize_grammar_rules.php - 根據使用情況自動優化文法規則
require_once 'db_connection.php';

// 設定執行環境
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300); // 允許腳本執行5分鐘

// 記錄開始時間
$start_time = microtime(true);
$log_messages = ['開始優化文法規則 - ' . date('Y-m-d H:i:s')];

// 載入現有規則
$rules_path = __DIR__ . '/models/grammar_rules.pkl';
$backup_path = __DIR__ . '/models/grammar_rules_backup_' . date('Ymd_His') . '.pkl';

// 創建備份
if (file_exists($rules_path)) {
    if (copy($rules_path, $backup_path)) {
        $log_messages[] = "已創建備份文件: {$backup_path}";
    } else {
        $log_messages[] = "警告: 無法創建備份文件: {$backup_path}";
    }
}

try {
    // 讀取現有規則
    $data = file_get_contents($rules_path);
    $grammar_rules = unserialize($data);
    
    if (!$grammar_rules || !isset($grammar_rules['rules'])) {
        $log_messages[] = "錯誤: 無法解析文法規則文件或格式不正確";
        outputLog($log_messages);
        exit(1);
    }
    
    $log_messages[] = "成功載入文法規則";
    
    // 1. 清理因多次反饋而標記為刪除的規則
    $total_rules_before = 0;
    $removed_rules = 0;
    
    foreach ($grammar_rules['rules'] as $error_type => &$rules) {
        $total_rules_before += count($rules);
        $original_count = count($rules);
        
        // 移除標記為已刪除的規則
        $rules = array_filter($rules, function($rule) {
            return !(isset($rule['is_removed']) && $rule['is_removed']);
        });
        
        // 如果有移除規則，重新索引數組
        if (count($rules) < $original_count) {
            $rules = array_values($rules);
            $removed_count = $original_count - count($rules);
            $removed_rules += $removed_count;
            $log_messages[] = "從 '{$error_type}' 類型中移除了 {$removed_count} 條標記為刪除的規則";
        }
    }
    
    $log_messages[] = "總計從 {$total_rules_before} 條規則中移除了 {$removed_rules} 條標記為刪除的規則";
    
    // 2. 合併相似的規則
    $merged_rules = 0;
    
    foreach ($grammar_rules['rules'] as $error_type => &$rules) {
        $original_count = count($rules);
        
        // 按原始表達式分組
        $grouped_rules = [];
        foreach ($rules as $rule) {
            if (!isset($rule['original'])) {
                // 跳過沒有 original 字段的規則
                continue;
            }
            
            $original_expr = $rule['original'];
            if (!isset($grouped_rules[$original_expr])) {
                $grouped_rules[$original_expr] = [];
            }
            $grouped_rules[$original_expr][] = $rule;
        }
        
        // 合併相同原始表達式的規則
        $merged_type_rules = [];
        foreach ($grouped_rules as $original_expr => $similar_rules) {
            if (count($similar_rules) > 1) {
                // 合併多個相同原始表達式的規則
                $merged_rule = array_reduce($similar_rules, function($carry, $item) {
                    // 初始化合併規則
                    if (!$carry) {
                        return $item;
                    }
                    
                    // 合併可能的正確表達式
                    $corrected = array_unique(array_merge($carry['corrected'], $item['corrected']));
                    
                    // 合併示例
                    $examples = isset($carry['examples']) ? $carry['examples'] : [];
                    if (isset($item['examples'])) {
                        $examples = array_unique(array_merge($examples, $item['examples']));
                    }
                    
                    // 合併上下文
                    $contexts = isset($carry['contexts']) ? $carry['contexts'] : [];
                    if (isset($item['contexts'])) {
                        $contexts = array_unique(array_merge($contexts, $item['contexts']));
                    }
                    
                    // 合併計數和權重，使用最大值
                    $count = max(
                        isset($carry['count']) ? $carry['count'] : 0,
                        isset($item['count']) ? $item['count'] : 0
                    );
                    
                    $weight = max(
                        isset($carry['weight']) ? $carry['weight'] : 0,
                        isset($item['weight']) ? $item['weight'] : 0
                    );
                    
                    // 保留反饋記錄
                    $feedback = isset($carry['feedback']) ? $carry['feedback'] : [];
                    if (isset($item['feedback'])) {
                        $feedback = array_merge($feedback, $item['feedback']);
                    }
                    
                    return [
                        'original' => $carry['original'],
                        'corrected' => $corrected,
                        'count' => $count,
                        'weight' => $weight,
                        'examples' => $examples,
                        'contexts' => $contexts,
                        'feedback' => $feedback,
                        'last_updated' => date('Y-m-d H:i:s')
                    ];
                });
                
                $merged_type_rules[] = $merged_rule;
                $merged_rules += (count($similar_rules) - 1);
            } else {
                // 只有一個規則，直接添加
                $merged_type_rules[] = $similar_rules[0];
            }
        }
        
        // 更新規則
        $rules = $merged_type_rules;
        
        if (count($rules) < $original_count) {
            $log_messages[] = "在 '{$error_type}' 類型中合併了 " . ($original_count - count($rules)) . " 條相似規則";
        }
    }
    
    $log_messages[] = "總計合併了 {$merged_rules} 條相似規則";
    
    // 3. 保存更新後的規則
    if (file_put_contents($rules_path, serialize($grammar_rules)) !== false) {
        $log_messages[] = "成功保存優化後的文法規則";
    } else {
        $log_messages[] = "錯誤: 無法保存優化後的文法規則";
    }
    
} catch (Exception $e) {
    $log_messages[] = "錯誤: " . $e->getMessage();
}

// 記錄執行時間
$execution_time = microtime(true) - $start_time;
$log_messages[] = "優化完成，耗時: " . round($execution_time, 2) . " 秒";

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
    $log_file = __DIR__ . '/logs/grammar_rules_optimize_' . date('Ymd') . '.log';
    $log_dir = dirname($log_file);
    
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, implode("\n", $messages) . "\n\n", FILE_APPEND);
}
?>