<?php
// 創建/修改 update_grammar_rules.php 文件

require_once 'db_connection.php';

// 設定執行環境
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300); // 允許腳本執行5分鐘

// 記錄開始時間
$start_time = microtime(true);
$log_messages = ['開始更新文法規則 - ' . date('Y-m-d H:i:s')];

// 1. 從資料庫獲取教師文法反饋
try {
    // 獲取未處理的反饋
    $stmt = $conn->prepare("
        SELECT af.*, e.content as essay_content 
        FROM ai_grammar_feedback af
        JOIN essays e ON af.essay_id = e.id
        WHERE af.processed = 0
        ORDER BY af.created_at ASC
        LIMIT 100
    ");
    $stmt->execute();
    $feedback_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $log_messages[] = '找到 ' . count($feedback_records) . ' 條未處理的反饋';
    
    if (!empty($feedback_records)) {
        // 2. 讀取現有的文法規則
        $grammar_rules_path = __DIR__ . '/models/grammar_rules.pkl';
        $grammar_rules = [];
        
        if (file_exists($grammar_rules_path)) {
            // 調用 Python 腳本來讀取 pickle 文件
            $python_script = __DIR__ . '/scripts/read_grammar_rules.py';
            $json_output_path = __DIR__ . '/temp/grammar_rules_json.tmp';
            
            // 創建臨時目錄（如果不存在）
            if (!file_exists(__DIR__ . '/temp')) {
                mkdir(__DIR__ . '/temp', 0755, true);
            }
            
            // 執行 Python 腳本將 pickle 轉換為 JSON
            $cmd = "python " . escapeshellarg($python_script) . 
                   " --input " . escapeshellarg($grammar_rules_path) . 
                   " --output " . escapeshellarg($json_output_path);
            
            exec($cmd, $output, $return_var);
            
            if ($return_var === 0 && file_exists($json_output_path)) {
                $json_content = file_get_contents($json_output_path);
                $grammar_rules = json_decode($json_content, true);
                unlink($json_output_path); // 刪除臨時文件
                
                $log_messages[] = '成功讀取文法規則';
            } else {
                $log_messages[] = '讀取文法規則失敗，將創建新的規則文件';
                // 創建空的規則結構
                $grammar_rules = [
                    'rules' => [
                        'tense' => [],
                        'subject_verb_agreement' => [],
                        'article' => [],
                        'plurals' => [],
                        'preposition' => [],
                        'word_choice' => [],
                        'spelling' => []
                    ],
                    'descriptions' => [
                        'tense' => '時態使用錯誤',
                        'subject_verb_agreement' => '主謂一致性問題',
                        'article' => '冠詞使用錯誤',
                        'plurals' => '複數形式錯誤',
                        'preposition' => '介詞使用錯誤',
                        'word_choice' => '詞語選擇不當',
                        'spelling' => '拼寫錯誤'
                    ]
                ];
            }
        }
        
        // 3. 處理每條反饋，更新規則
        foreach ($feedback_records as $record) {
            try {
                $feedback_type = $record['feedback_type'];
                $wrong_expression = $record['wrong_expression'];
                $correct_expression = $record['correct_expression'];
                $essay_content = $record['essay_content'];
                
                if ($feedback_type == 'missed_issue' && !empty($wrong_expression) && !empty($correct_expression)) {
                    // 確定錯誤類型
                    $error_type = determineErrorType($wrong_expression, $correct_expression);
                    
                    // 確保錯誤類型存在於規則中
                    if (!isset($grammar_rules['rules'][$error_type])) {
                        $grammar_rules['rules'][$error_type] = [];
                    }
                    
                    // 檢查是否已存在相同規則
                    $rule_exists = false;
                    foreach ($grammar_rules['rules'][$error_type] as &$rule) {
                        if ($rule['original'] === $wrong_expression) {
                            // 更新現有規則
                            if (!in_array($correct_expression, $rule['corrected'])) {
                                $rule['corrected'][] = $correct_expression;
                            }
                            $rule['count'] = ($rule['count'] ?? 0) + 1;
                            
                            // 添加例句（如果不存在）
                            if (!in_array($essay_content, $rule['examples'])) {
                                $rule['examples'][] = $essay_content;
                            }
                            
                            $rule_exists = true;
                            break;
                        }
                    }
                    
                    // 如果規則不存在，添加新規則
					if (!$rule_exists) {
						// 計算初始權重 - 基於錯誤類型和單詞數量
						$word_count = count(array_filter(explode(' ', trim($wrong_expression))));
						$initial_weight = 1;
						
						// 多字規則獲得更高的初始權重
						if ($word_count > 1) {
							$initial_weight = 1 + min(5, $word_count);
						}
						
						// 某些特定類型的錯誤獲得更高權重
						$priority_types = ['subject_verb_agreement', 'tense', 'preposition'];
						if (in_array($error_type, $priority_types)) {
							$initial_weight += 2;
						}
						
						$grammar_rules['rules'][$error_type][] = [
							'original' => $wrong_expression,
							'corrected' => [$correct_expression],
							'count' => $initial_weight,
							'examples' => [$essay_content],
							'weight' => $initial_weight,
							'last_updated' => date('Y-m-d H:i:s')
						];
					}
                    
                    $log_messages[] = "處理文法反饋: '{$wrong_expression}' -> '{$correct_expression}' (類型: {$error_type})";
                } 
                elseif ($feedback_type == 'false_positive' && !empty($wrong_expression)) {
                    // 處理誤報問題 - 降低規則權重或標記
					foreach ($grammar_rules['rules'] as $type => &$rules) {
						foreach ($rules as $key => &$rule) {
							if ($rule['original'] === $wrong_expression) {
								// 降低權重更多
								$current_weight = isset($rule['weight']) ? $rule['weight'] : ($rule['count'] ?? 1);
								$new_weight = max(0, $current_weight - 2);  // 每次反饋減少2點權重
								
								$rule['count'] = $new_weight;
								$rule['weight'] = $new_weight;
								$rule['last_updated'] = date('Y-m-d H:i:s');
								
								// 記錄反饋，但不立即刪除規則
								if (!isset($rule['feedback'])) {
									$rule['feedback'] = [];
								}
								$rule['feedback'][] = [
									'type' => 'false_positive',
									'date' => date('Y-m-d H:i:s'),
									'context' => $essay_content
								];
								
								// 如果權重很低，標記為可能的誤報
								if ($new_weight <= 1) {
									$rule['potential_false_positive'] = true;
								}
								
								// 如果有多次反饋或權重為0，標記為已移除
								if ($new_weight == 0 || (isset($rule['feedback']) && count($rule['feedback']) >= 3)) {
									$rule['is_removed'] = true;
								}
								
								$log_messages[] = "處理誤報問題: '{$wrong_expression}' (類型: {$type}, 新權重: {$new_weight})";
								break;
							}
						}
					}
                }
                
                // 標記此反饋為已處理
                $stmt = $conn->prepare("
                    UPDATE ai_grammar_feedback
                    SET processed = 1, processed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$record['id']]);
                
            } catch (Exception $e) {
                $log_messages[] = '處理反饋 ID:' . $record['id'] . ' 時出錯: ' . $e->getMessage();
            }
        }
        
        // 4. 保存更新後的規則
        // 創建 Python 腳本將 JSON 轉換回 pickle
        $updated_json_path = __DIR__ . '/temp/updated_grammar_rules.json';
        file_put_contents($updated_json_path, json_encode($grammar_rules, JSON_UNESCAPED_UNICODE));
        
        $python_save_script = __DIR__ . '/scripts/save_grammar_rules.py';
        $cmd = "python " . escapeshellarg($python_save_script) . 
               " --input " . escapeshellarg($updated_json_path) . 
               " --output " . escapeshellarg($grammar_rules_path);
        
        exec($cmd, $output, $return_var);
        
        if ($return_var === 0) {
            $log_messages[] = '成功保存更新後的文法規則';
            @unlink($updated_json_path); // 刪除臨時文件
        } else {
            $log_messages[] = '保存文法規則失敗: ' . implode("\n", $output);
        }
    }
    
} catch (Exception $e) {
    $log_messages[] = '執行過程中發生錯誤: ' . $e->getMessage();
}

// 記錄執行時間
$execution_time = microtime(true) - $start_time;
$log_messages[] = '處理完成，耗時: ' . round($execution_time, 2) . ' 秒';

// 輸出日誌
$log_file = __DIR__ . '/logs/grammar_rules_update_' . date('Ymd') . '.log';
$log_dir = dirname($log_file);
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}
file_put_contents($log_file, implode("\n", $log_messages) . "\n\n", FILE_APPEND);

/**
 * 確定錯誤類型
 * @param string $wrong 錯誤表達
 * @param string $correct 正確表達
 * @return string 錯誤類型
 */
function determineErrorType($wrong, $correct) {
    // 轉換為小寫進行比較
    $wrong_lower = strtolower($wrong);
    $correct_lower = strtolower($correct);
    
    // 1. 檢查時態問題
    $past_tense_pairs = [
        'go' => 'went',
        'shine' => 'shone',
        'is' => 'was',
        'are' => 'were',
        'eat' => 'ate',
        'run' => 'ran',
        'laying' => 'lay',
        'setting' => 'set',
        'forget' => 'forgot',
        'going' => 'go',
        'arrives' => 'arrived'
    ];
    
    foreach ($past_tense_pairs as $present, $past) {
        if (strpos($wrong_lower, $present) !== false && strpos($correct_lower, $past) !== false) {
            return 'tense';
        }
    }
    
    // 2. 檢查主謂一致性問題
    $sv_pairs = [
        'weather were' => 'weather was',
        'sun setting' => 'sun was setting',
        'it were' => 'it was',
        'i is' => 'i am',
        'they is' => 'they are',
        'we is' => 'we are',
        'he are' => 'he is',
        'she are' => 'she is'
    ];
    
    foreach ($sv_pairs as $wrong_sv, $correct_sv) {
        if (strpos($wrong_lower, $wrong_sv) !== false && strpos($correct_lower, $correct_sv) !== false) {
            return 'subject_verb_agreement';
        }
    }
    
    // 3. 檢查冠詞問題
    if ((strpos($wrong_lower, ' a ') !== false && strpos($correct_lower, ' an ') !== false) ||
        (strpos($wrong_lower, ' an ') !== false && strpos($correct_lower, ' a ') !== false) ||
        (strpos($wrong_lower, 'under umbrella') !== false && 
         (strpos($correct_lower, 'under an umbrella') !== false || 
          strpos($correct_lower, 'under the umbrella') !== false))) {
        return 'article';
    }
    
    // 4. 檢查複數形式
    $singular_plural_pairs = [
        'hour' => 'hours',
        'day' => 'days',
        'child' => 'children',
        'stuffs' => 'stuff',
        'man' => 'men',
        'woman' => 'women'
    ];
    
    foreach ($singular_plural_pairs as $singular, $plural) {
        if ((strpos($wrong_lower, $singular) !== false && strpos($correct_lower, $plural) !== false) ||
            (strpos($wrong_lower, $plural) !== false && strpos($correct_lower, $singular) !== false)) {
            return 'plurals';
        }
    }
    
    // 5. 檢查介詞用法
    $prepositions = ['in', 'on', 'at', 'for', 'with', 'by', 'to', 'from', 'of'];
    $wrong_words = explode(' ', $wrong_lower);
    $correct_words = explode(' ', $correct_lower);
    
    foreach ($prepositions as $prep) {
        if ((in_array($prep, $wrong_words) && !in_array($prep, $correct_words)) ||
            (!in_array($prep, $wrong_words) && in_array($prep, $correct_words))) {
            return 'preposition';
        }
    }
    
    // 6. 檢查拼寫錯誤 (編輯距離)
    $wrong_clean = preg_replace('/[^a-zA-Z0-9]/', '', $wrong_lower);
    $correct_clean = preg_replace('/[^a-zA-Z0-9]/', '', $correct_lower);
    
    if (strlen($wrong_clean) > 3 && strlen($correct_clean) > 3) {
        similar_text($wrong_clean, $correct_clean, $percent);
        if ($percent > 70 && $percent < 100) {
            return 'spelling';
        }
    }
    
    // 7. 默認為詞語選擇問題
    return 'word_choice';
}
?>