
專題 /
請讀取我的專案檔案，幫我修正問題:開始處理AI文法反饋 - 2025-05-18 12:09:38 找到 0 條未處理的反饋 處理完成，耗時: 0 秒Warning: Undefined variable $essay_content in D:\xampp\htdocs\Project\process_grammar_feedback.php on line 478Warning: Undefined variable $wrong_expression in D:\xampp\htdocs\Project\process_grammar_feedback.php on line 478Deprecated: stripos(): Passing null to parameter #1 ($haystack) of type string is deprecated in D:\xampp\htdocs\Project\process_grammar_feedback.php on line 466Deprecated: stripos(): Passing null to parameter #2 ($needle) of type string is deprecated in D:\xampp\htdocs\Project\process_grammar_feedback.php on line 466Deprecated: strlen(): Passing null to parameter #1 ($string) of type string is deprecated in D:\xampp\htdocs\Project\process_grammar_feedback.php on line 471Deprecated: substr(): Passing null to parameter #1 ($string) of type string is deprecated in D:\xampp\htdocs\Project\process_grammar_feedback.php on line 472Warning: Undefined variable $essay_id in D:\xampp\htdocs\Project\process_grammar_feedback.php on line 484Warning: Undefined variable $teacher_id in D:\xampp\htdocs\Project\process_grammar_feedback.php on line 484Warning: Undefined variable $wrong_expression in D:\xampp\htdocs\Project\process_grammar_feedback.php on line 484Warning: Undefined variable $correct_expression in D:\xampp\htdocs\Project\process_grammar_feedback.php on line 484Fatal error: Uncaught PDOException: SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'essay_id' cannot be null in D:\xampp\htdocs\Project\process_grammar_feedback.php:484 Stack trace: #0 D:\xampp\htdocs\Project\process_grammar_feedback.php(484): PDOStatement->execute(Array) #1 {main} thrown in D:\xampp\htdocs\Project\process_grammar_feedback.php on line 484

Edit
看起來這個問題出現在 process_grammar_feedback.php 檔案中，主要是關於未定義變數和在函數外部呼叫了函數內部的程式碼。我會修正這些問題。

主要的錯誤是:

未定義變數 ($essay_content, $wrong_expression, $essay_id, $teacher_id, $correct_expression)
在函數外部直接執行了 extractContext 和 SQL 語句
我將修復檔案中的這些問題:


修正後的 process_grammar_feedback.php
Code 
我已經修正了 process_grammar_feedback.php 檔案中的問題。主要做了以下修改：

主要修改內容：
修復未定義變數問題：
移除了檔案尾部直接執行的 extractContext 程式碼，這段程式碼之前位於第 478 行
移除了檔案尾部直接執行的 SQL 插入操作
增強 extractContext 函數：
增加了檢查確保輸入參數 $text 和 $wrong_expression 不為空
php
if (empty($text) || empty($wrong_expression)) {
    return "";
}
移除錯誤的程式碼重複：
移除了檔案尾部的重複函數和程式碼執行
錯誤詳細分析：
錯誤發生的原因是檔案中存在一個函數 extractContext，但在函數外部直接執行了一些使用這個函數的程式碼，這導致變數 $essay_content、$wrong_expression 等在全局範圍內未定義。同時也有一個 SQL 準備和執行語句位於全局範圍內，使用了這些未定義的變數。

修改後的檔案現在已經正確地將所有功能包含在函數內部，不再有全局範圍內直接執行的程式碼，這樣就可以避免 PHP 的變數未定義警告和錯誤。

測試步驟：
將修正後的程式碼覆蓋到您的 process_grammar_feedback.php 檔案
再次執行處理程序查看是否還有錯誤
檢查日誌確認所有功能正常運作
希望這個修正能解決您的問題！







<?php
// process_grammar_feedback.php - 定期處理教師對AI文法分析的反饋
// 建議通過 cron job 每天執行一次

require_once 'db_connection.php';
require_once 'grammar_analysis_service.php';

// 設定執行環境
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300); // 允許腳本執行5分鐘

// 記錄開始時間
$start_time = microtime(true);
$log_messages = ['開始處理AI文法反饋 - ' . date('Y-m-d H:i:s')];

// 初始化文法分析服務
$grammar_service = new GrammarAnalysisService();

// 加載現有的文法規則
$grammar_rules = loadGrammarRules();
if (!$grammar_rules) {
    $grammar_rules = [
        'rules' => [],
        'descriptions' => getDefaultErrorDescriptions()
    ];
    $log_messages[] = '無法載入現有文法規則，將創建新規則';
}

// 獲取未處理的反饋
$feedback_records = fetchUnprocessedFeedback($conn);
$log_messages[] = '找到 ' . count($feedback_records) . ' 條未處理的反饋';

// 處理反饋並更新規則
if (!empty($feedback_records)) {
    $stats = [
        'missed_issues' => 0,
        'false_positives' => 0,
        'general_comments' => 0
    ];
    
    // 準備提交到 NLP 服務的反饋數據
    $nlp_feedback_data = [];
    
    // 處理每條反饋
    foreach ($feedback_records as $record) {
        try {
            $feedback_type = $record['feedback_type'];
            $essay_id = $record['essay_id'];
            
            // 獲取作文內容
            $essay_content = $record['essay_content'];
            
            if ($feedback_type == 'missed_issue') {
                // 處理AI未識別的錯誤
                $wrong = $record['wrong_expression'];
                $correct = $record['correct_expression'];
                $error_type = !empty($record['error_type']) && $record['error_type'] != 'unknown' 
                            ? $record['error_type'] 
                            : determineErrorType($wrong, $correct);
                
                if (!empty($wrong)) {
                    // 準備提交到 NLP 服務的反饋
                    $nlp_feedback_data[] = [
                        'essay_id' => $essay_id,
                        'essay_text' => $essay_content,
                        'feedback' => [
                            'type' => 'missed_issue',
                            'wrong' => $wrong,
                            'correct' => $correct,
                            'error_type' => $error_type
                        ]
                    ];
                    
                    // 在本地更新規則
                    updateLocalRule($grammar_rules, $error_type, $wrong, $correct, $essay_content);
                    
                    $stats['missed_issues']++;
                    $log_messages[] = "處理未識別問題: '{$wrong}' -> '{$correct}' (類型: {$error_type})";
                }
            } 
            elseif ($feedback_type == 'false_positive') {
                // 處理誤報問題
                $wrong = $record['wrong_expression'];
                
                if (!empty($wrong)) {
                    // 準備提交到 NLP 服務的反饋
                    $nlp_feedback_data[] = [
                        'essay_id' => $essay_id,
                        'essay_text' => $essay_content,
                        'feedback' => [
                            'type' => 'false_positive',
                            'expression' => $wrong
                        ]
                    ];
                    
                    // 在本地降低規則權重
                    updateLocalRuleWeight($grammar_rules, $wrong);
                    
                    $stats['false_positives']++;
                    $log_messages[] = "處理誤報問題: '{$wrong}'";
                }
            }
            else if ($feedback_type == 'general') {
                // 處理一般反饋
                $comment = $record['comment'];
                
                if (!empty($comment)) {
                    // 準備提交到 NLP 服務的反饋
                    $nlp_feedback_data[] = [
                        'essay_id' => $essay_id,
                        'essay_text' => $essay_content,
                        'feedback' => [
                            'type' => 'general',
                            'comment' => $comment
                        ]
                    ];
                    
                    $stats['general_comments']++;
                    $log_messages[] = "處理一般反饋 ID: {$record['id']}";
                }
            }
            
            // 標記該反饋為已處理
            markFeedbackProcessed($conn, $record['id']);
            
        } catch (Exception $e) {
            $log_messages[] = '處理反饋 ID:' . $record['id'] . ' 時出錯: ' . $e->getMessage();
        }
    }
    
    // 對各錯誤類型的規則按頻率排序
    foreach ($grammar_rules['rules'] as &$rules) {
        usort($rules, function($a, $b) {
            return ($b['count'] ?? 0) - ($a['count'] ?? 0);
        });
    }
    
    // 保存更新後的文法規則
    if (saveGrammarRules($grammar_rules)) {
        $log_messages[] = '文法規則已成功更新';
        $log_messages[] = '- 添加/更新了 ' . $stats['missed_issues'] . ' 條未識別的錯誤';
        $log_messages[] = '- 處理了 ' . $stats['false_positives'] . ' 條誤報的問題';
        $log_messages[] = '- 記錄了 ' . $stats['general_comments'] . ' 條一般反饋';
    } else {
        $log_messages[] = '保存文法規則時發生錯誤';
    }
    
    // 發送反饋給 NLP 服務
    if (!empty($nlp_feedback_data)) {
        foreach ($nlp_feedback_data as $feedback) {
            if ($grammar_service->submitFeedback($feedback)) {
                $log_messages[] = "成功提交反饋到 NLP 服務: Essay ID {$feedback['essay_id']}";
            } else {
                $log_messages[] = "提交反饋到 NLP 服務失敗: Essay ID {$feedback['essay_id']}";
            }
        }
        
        // 檢查是否需要觸發模型訓練
        if (count($nlp_feedback_data) >= 10) {  // 至少有10條反饋才觸發訓練
            $training_result = $grammar_service->trainModel();
            if ($training_result && isset($training_result['success']) && $training_result['success']) {
                $log_messages[] = "成功觸發 NLP 模型訓練: " . ($training_result['message'] ?? '');
            } else {
                $log_messages[] = "觸發 NLP 模型訓練失敗";
            }
        } else {
            $log_messages[] = "反饋數量不足，暫不觸發 NLP 模型訓練";
        }
    }
}

// 記錄執行時間
$execution_time = microtime(true) - $start_time;
$log_messages[] = '處理完成，耗時: ' . round($execution_time, 2) . ' 秒';

// 輸出日誌
writeToLog($log_messages);

// 函數定義

/**
 * 載入現有的文法規則
 */
function loadGrammarRules() {
    $rules_path = __DIR__ . '/models/grammar_rules.pkl';
    
    if (!file_exists($rules_path)) {
        // 嘗試其他可能路徑
        $alternative_paths = [
            __DIR__ . '/../models/grammar_rules.pkl',
            __DIR__ . '/../../models/grammar_rules.pkl'
        ];
        
        foreach ($alternative_paths as $path) {
            if (file_exists($path)) {
                $rules_path = $path;
                break;
            }
        }
    }
    
    if (file_exists($rules_path)) {
        try {
            $data = file_get_contents($rules_path);
            return unserialize($data);
        } catch (Exception $e) {
            error_log('載入文法規則失敗: ' . $e->getMessage());
        }
    }
    
    return null;
}

/**
 * 保存更新後的文法規則
 */
function saveGrammarRules($grammar_rules) {
    $rules_path = __DIR__ . '/models/grammar_rules.pkl';
    
    // 確保目錄存在
    $dir = dirname($rules_path);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // 創建備份
    if (file_exists($rules_path)) {
        $backup_path = $rules_path . '.bak.' . date('YmdHis');
        copy($rules_path, $backup_path);
    }
    
    try {
        return file_put_contents($rules_path, serialize($grammar_rules)) !== false;
    } catch (Exception $e) {
        error_log('保存文法規則失敗: ' . $e->getMessage());
        return false;
    }
}

/**
 * 獲取未處理的反饋
 */
function fetchUnprocessedFeedback($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT id, essay_id, teacher_id, feedback_type, wrong_expression, 
                   correct_expression, error_type, comment, created_at, 
                   (SELECT content FROM essays WHERE id = essay_id) as essay_content
            FROM ai_grammar_feedback
            WHERE processed = 0
            ORDER BY created_at ASC
            LIMIT 100
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('獲取未處理反饋失敗: ' . $e->getMessage());
        return [];
    }
}

/**
 * 標記反饋為已處理
 */
function markFeedbackProcessed($conn, $feedback_id) {
    try {
        // 更新反饋處理狀態
        $stmt = $conn->prepare("
            UPDATE ai_grammar_feedback
            SET processed = 1, processed_at = NOW()
            WHERE id = ?
        ");
        $result = $stmt->execute([$feedback_id]);
        
        // 記錄處理結果到訓練日誌
        if ($result) {
            $stmt = $conn->prepare("
                INSERT INTO ai_training_logs 
                (feedback_id, process_type, status, processed_at, notes)
                VALUES (?, 'grammar_feedback', 'processed', NOW(), '自動處理文法反饋')
            ");
            $stmt->execute([$feedback_id]);
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log('標記反饋處理狀態失敗: ' . $e->getMessage());
        return false;
    }
}

/**
 * 確定錯誤類型
 */
function determineErrorType($wrong, $correct) {
    // 檢查主謂一致性問題
    $sv_patterns = [
        'they is', 'they was',
        'he are', 'she are', 'it are', 'he were', 'she were', 'it were',
        'you is', 'you has',
        'i is', 'i has',
        'we is', 'we was',
        'who is' => 'who are'
    ];
    
    foreach ($sv_patterns as $pattern) {
        if (stripos($wrong, $pattern) !== false) {
            return 'subject_verb_agreement';
        }
    }
    
    // 檢查冠詞問題
    if (preg_match('/\ba\s+[aeiou]/i', $wrong) || preg_match('/\ban\s+[^aeiou]/i', $wrong)) {
        return 'article';
    }
    
    // 檢查拼寫問題 - 如果長度相近但有差異
    if (abs(strlen($wrong) - strlen($correct)) <= 3) {
        return 'spelling';
    }
    
    // 檢查時態問題
    if ((stripos($wrong, 'is') !== false && stripos($correct, 'was') !== false) ||
        (stripos($wrong, 'go') !== false && stripos($correct, 'went') !== false)) {
        return 'tense';
    }
    
    // 檢查介詞問題
    $prepositions = ['in', 'on', 'at', 'for', 'with', 'by', 'to', 'from'];
    foreach ($prepositions as $prep) {
        if ((stripos($wrong, $prep) !== false && stripos($correct, $prep) === false) ||
            (stripos($wrong, $prep) === false && stripos($correct, $prep) !== false)) {
            return 'preposition';
        }
    }
    
    // 默認為詞語選擇問題
    return 'word_choice';
}

/**
 * 獲取默認的錯誤類型描述
 */
function getDefaultErrorDescriptions() {
    return [
        "spelling" => "拼寫錯誤",
        "grammar" => "文法錯誤",
        "punctuation" => "標點符號錯誤",
        "word_choice" => "詞語選擇不當",
        "structure" => "句子結構問題",
        "article" => "冠詞使用錯誤",
        "tense" => "時態使用錯誤",
        "subject_verb_agreement" => "主謂一致性問題",
        "preposition" => "介詞使用錯誤",
        "plurals" => "複數形式錯誤",
        "unknown" => "其他錯誤"
    ];
}

/**
 * 更新本地規則
 */
function updateLocalRule(&$grammar_rules, $error_type, $wrong, $correct, $context) {
    // 確保該類型存在
    if (!isset($grammar_rules['rules'][$error_type])) {
        $grammar_rules['rules'][$error_type] = [];
    }
    
    // 檢查是否已存在相同規則
    $rule_exists = false;
    foreach ($grammar_rules['rules'][$error_type] as &$rule) {
        if ($rule['original'] === $wrong) {
            // 更新現有規則
            if (!in_array($correct, $rule['corrected'])) {
                $rule['corrected'][] = $correct;
            }
            $rule['count'] = isset($rule['count']) ? $rule['count'] + 1 : 1;
            
            // 添加上下文示例
            if (!isset($rule['examples'])) {
                $rule['examples'] = [];
            }
            
            // 提取包含錯誤表達的上下文
            $context_excerpt = extractContext($context, $wrong);
            if (!empty($context_excerpt) && !in_array($context_excerpt, $rule['examples'])) {
                $rule['examples'][] = $context_excerpt;
            }
            
            $rule_exists = true;
            break;
        }
    }
    
    // 如果規則不存在，添加新規則
    if (!$rule_exists) {
        // 計算初始權重 - 基於錯誤類型和單詞數量
        $word_count = count(array_filter(explode(' ', trim($wrong))));
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
        
        // 提取上下文
        $context_excerpt = extractContext($context, $wrong);
        
        $grammar_rules['rules'][$error_type][] = [
            'original' => $wrong,
            'corrected' => [$correct],
            'count' => $initial_weight,
            'examples' => !empty($context_excerpt) ? [$context_excerpt] : [],
            'weight' => $initial_weight,
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * 更新規則權重（針對誤報）
 */
function updateLocalRuleWeight(&$grammar_rules, $expression) {
    // 在所有規則中搜索並降低權重
    foreach ($grammar_rules['rules'] as $type => &$rules) {
        foreach ($rules as &$rule) {
            if ($rule['original'] === $expression) {
                // 降低權重
                $rule['count'] = max(0, ($rule['count'] ?? 1) - 2);  // 更大幅度降低權重
                $rule['weight'] = max(0, ($rule['weight'] ?? 1) - 2);
                
                // 如果權重低於閾值，標記為已移除
                if ($rule['count'] <= 1) {
                    $rule['is_removed'] = true;
                }
                
                // 記錄反饋
                if (!isset($rule['feedback'])) {
                    $rule['feedback'] = [];
                }
                
                $rule['feedback'][] = [
                    'type' => 'false_positive',
                    'date' => date('Y-m-d H:i:s')
                ];
                
                break;
            }
        }
    }
}

/**
 * 從文章中提取包含錯誤表達的上下文
 */
function extractContext($text, $wrong_expression, $maxLength = 500) {
    if (empty($text) || empty($wrong_expression)) {
        return "";
    }
    
    // 找到包含錯誤表達的上下文
    $pos = stripos($text, $wrong_expression);
    if ($pos === false) return "";
    
    // 提取前後文
    $start = max(0, $pos - 200);
    $length = min(strlen($text) - $start, $maxLength);
    $context = substr($text, $start, $length);
    
    return $context;
}

/**
 * 寫入日誌文件
 */
function writeToLog($messages) {
    $log_file = __DIR__ . '/logs/ai_feedback_' . date('Ymd') . '.log';
    
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

/**
 * 模擬提交反饋到NLP服務
 * 實際應用中，這應該通過API調用來完成
 */
function submitFeedbackToNLP($feedback) {
    // 模擬提交反饋到NLP服務
    // 在實際應用中，這裡應該是一個API調用
    // 返回true表示提交成功
    return true;
}

/**
 * 模擬觸發NLP模型訓練
 * 實際應用中，這應該通過API調用來完成
 */
function triggerNLPTraining() {
    // 模擬觸發NLP模型訓練
    // 在實際應用中，這裡應該是一個API調用
    return [
        'success' => true,
        'message' => '模型訓練任務已提交'
    ];
}
?>
