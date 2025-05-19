<?php
/**
 * 文法分析服務類
 */
class GrammarAnalysisService {
    private $nlp_service_url = 'http://localhost:5000';  // NLP 服務的基本 URL
    private $use_nlp_service = true;  // 是否使用 NLP 服務
    private $grammar_rules_path;      // 文法規則文件路徑
    
    /**
     * 構造函數
     */
    public function __construct() {
        $this->grammar_rules_path = __DIR__ . '/models/grammar_rules.pkl';
    }
    
    /**
     * 分析文本中的文法問題
     * @param string $text 要分析的文本
     * @param string $category 作文類型 (可選)
     * @return array 分析結果，包含文法問題和評分
     */
    public function analyzeGrammar($text, $category = 'narrative') {
        // 優先使用 NLP 服務（如果啟用）
        if ($this->use_nlp_service) {
            $nlp_result = $this->callNLPService($text, $category);
            
            // 如果 NLP 服務成功返回結果
            if ($nlp_result !== null) {
                return $nlp_result;
            }
        }
        
        // 如果 NLP 服務未啟用或調用失敗，使用本地規則
        return $this->analyzeWithLocalRules($text, $category);
    }
    
    /**
     * 使用本地文法規則分析文法
     * @param string $text 要分析的文本
     * @param string $category 作文類型
     * @return array 分析結果
     */
    private function analyzeWithLocalRules($text, $category) {
        // 載入文法規則
        $grammar_rules = $this->loadGrammarRules();
        
        // 檢查文法問題
        $grammar_issues = $this->checkGrammarIssues($text, $grammar_rules);
        
        // 計算評分 (簡單實現)
        $score = 85; // 基礎分數
        
        // 根據找到的問題降低分數
        $issue_count = 0;
        foreach ($grammar_issues as $issues) {
            $issue_count += count($issues);
        }
        
        // 每個問題扣 2 分，最低 60 分
        $score = max(60, $score - ($issue_count * 2));
        
        return [
            'grammar_issues' => $grammar_issues,
            'score' => $score,
            'summary' => $this->generateSummary($grammar_issues),
            'all_issues_count' => $issue_count,
            'model_version' => 'local-rules'
        ];
    }
    
    /**
     * 調用 NLP 服務進行文法分析
     * @param string $text 要分析的文本
     * @param string $category 作文類型
     * @return array|null 分析結果或 null（如果調用失敗）
     */
    private function callNLPService($text, $category) {
        try {
            // 準備請求資料
            $data = [
                'text' => $text,
                'category' => $category
            ];
            
            // 初始化 cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->nlp_service_url . '/api/analyze_grammar');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);  // 設定超時時間
            
            // 執行請求
            $response = curl_exec($ch);
            $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // 檢查是否成功
            if ($status_code == 200 && $response) {
                $result = json_decode($response, true);
                
                // 添加額外信息
                $result['summary'] = $this->generateSummary($result['grammar_issues']);
                $result['all_issues_count'] = $this->countTotalIssues($result['grammar_issues']);
                
                return $result;
            }
            
            // 如果調用失敗，記錄錯誤
            error_log("NLP 服務調用失敗: HTTP {$status_code}, Response: {$response}");
            return null;
            
        } catch (Exception $e) {
            error_log('調用 NLP 服務時發生錯誤: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 載入文法規則
     * @return array 文法規則
     */
    private function loadGrammarRules() {
        if (file_exists($this->grammar_rules_path)) {
            try {
                $data = file_get_contents($this->grammar_rules_path);
                $grammar_rules = unserialize($data);
                return $grammar_rules;
            } catch (Exception $e) {
                error_log('載入文法規則失敗: ' . $e->getMessage());
            }
        }
        
        // 如果載入失敗，返回空規則
        return [
            'rules' => [],
            'descriptions' => []
        ];
    }
    
    /**
     * 檢查文本中的文法問題
     * @param string $text 要檢查的文本
     * @param array $grammar_rules 文法規則
     * @return array 找到的文法問題
     */
    private function checkGrammarIssues($text, $grammar_rules) {
        $issues = [];
        
        // 如果沒有規則，則返回空結果
        if (empty($grammar_rules) || empty($grammar_rules['rules'])) {
            return $issues;
        }
        
        // 分割文本為句子以便進行上下文分析
        $sentences = preg_split('/(?<=[.!?])\s+/', $text);
        
        // 針對每種錯誤類型檢查
        foreach ($grammar_rules['rules'] as $error_type => $rules) {
            $found_issues = [];
            
            foreach ($rules as $rule) {
                // 跳過被標記為已移除的規則
                if (isset($rule['is_removed']) && $rule['is_removed']) {
                    continue;
                }
                
                if (empty($rule['original'])) continue;
                
                $original = $rule['original'];
                
                // 計算單詞數量
                $word_count = count(array_filter(explode(' ', trim($original))));
                
                // 單詞規則需要特殊處理，確保它是一個獨立的單詞，並基於上下文判斷
                if ($word_count <= 1) {
                    // 對每個句子進行上下文敏感的檢查
                    foreach ($sentences as $sentence) {
                        // 檢查句子中是否包含該單詞
                        if (preg_match('/\b' . preg_quote($original, '/') . '\b/i', $sentence)) {
                            // 進行上下文檢查，判斷該用法是否確實錯誤
                            if ($this->isActualErrorInContext($original, $sentence, $error_type)) {
                                $corrected = !empty($rule['corrected'][0]) ? $rule['corrected'][0] : "";
                                $suggestion = "'{$original}' 可能應為 '{$corrected}'";
                                
                                if (!in_array($suggestion, $found_issues)) {
                                    $found_issues[] = $suggestion;
                                }
                            }
                        }
                    }
                } else {
                    // 多詞表達規則 - 這些通常更準確，所以使用簡單匹配
                    if (preg_match('/\b' . preg_quote($original, '/') . '\b/i', $text)) {
                        $corrected = !empty($rule['corrected'][0]) ? $rule['corrected'][0] : "";
                        $suggestion = "'{$original}' 可能應為 '{$corrected}'";
                        
                        if (!in_array($suggestion, $found_issues)) {
                            $found_issues[] = $suggestion;
                        }
                    }
                }
            }
            
            if (!empty($found_issues)) {
                $issues[$error_type] = $found_issues;
            }
        }
        
        return $issues;
    }
    
    /**
     * 基於上下文判斷單詞用法是否確實錯誤
     * @param string $word 要檢查的單詞
     * @param string $sentence 包含該單詞的句子
     * @param string $error_type 錯誤類型
     * @return bool 是否確實錯誤
     */
    private function isActualErrorInContext($word, $sentence, $error_type) {
        // 基本邏輯: 避免誤判常見詞彙
        $common_words = [
            'when', 'if', 'where', 'who', 'what', 'why', 'how',
            'lot', 'first', 'life', 'one', 'after',
            'this', 'that', 'these', 'those',
            'do', 'does', 'did', 'done',
            'can', 'could', 'will', 'would', 'shall', 'should',
            'up', 'down', 'out', 'off', 'on', 'in'
        ];
        
        if (in_array(strtolower($word), $common_words)) {
            // 如果是常見詞，執行更嚴格的上下文檢查
            switch (strtolower($word)) {
                case 'when':
                    // 當'when'作為時間連接詞使用時，通常是正確的
                    if (preg_match('/\b(at|on|in|during|after|before|while)\s+when\b/i', $sentence)) {
                        return true; // 這可能是錯誤用法
                    }
                    return false; // 其他情況下，'when'通常是正確的
                    
                case 'if':
                    // 'if'通常用於條件句，很少有錯誤情況
                    return false;
                    
                case 'first':
                    // 檢查是否使用了"at first"或"in first"等不正確結構
                    if (preg_match('/\b(at|in)\s+first\b/i', $sentence)) {
                        return true;
                    }
                    return false;
                    
                case 'lot':
                    // 只有當'lot'不是作為'a lot of'的一部分時才可能有問題
                    if (!preg_match('/\ba\s+lot\s+of\b/i', $sentence)) {
                        return true;
                    }
                    return false;
                    
                case 'life':
                    // 在大多數情況下，'life'是正確的單數形式
                    // 只有特定上下文下才需要用複數'lives'
                    if (preg_match('/\btheir\s+life\b|\bour\s+life\b|\blives\s+of\b/i', $sentence)) {
                        return true;
                    }
                    return false;
                    
                case 'one':
                    // 'one'通常是正確的
                    return false;
                    
                case 'university':
                    // 检查是否应该使用复数形式
                    if (preg_match('/\bmany\s+university\b|\bseveral\s+university\b/i', $sentence)) {
                        return true;
                    }
                    return false;
            }
        }
        
        // 如果是拼寫錯誤，可以放寬限制
        if ($error_type == 'spelling') {
            return true;
        }
        
        // 對於冠詞、主謂一致等常見錯誤，使用基於語法的檢查
        if (in_array($error_type, ['article', 'subject_verb_agreement', 'tense'])) {
            // 執行更複雜的語法檢查
            return $this->checkGrammaticalError($word, $sentence, $error_type);
        }
        
        // 對於多字規則或其他情況，保持原有判斷邏輯
        return true;
    }
    
    /**
     * 檢查特定的語法錯誤
     * @param string $word 要檢查的單詞
     * @param string $sentence 包含該單詞的句子
     * @param string $error_type 錯誤類型
     * @return bool 是否存在語法錯誤
     */
    private function checkGrammaticalError($word, $sentence, $error_type) {
        switch ($error_type) {
            case 'article':
                // 冠詞錯誤檢查
                if ($word == 'a' && preg_match('/\ba\s+[aeiou]/i', $sentence)) {
                    return true; // 'a' 在元音前應該改為 'an'
                }
                if ($word == 'an' && preg_match('/\ban\s+[^aeiou]/i', $sentence)) {
                    return true; // 'an' 在非元音前應該改為 'a'
                }
                break;
                
            case 'subject_verb_agreement':
                // 主謂一致檢查
                if ($word == 'is' && preg_match('/\b(they|we|you)\s+is\b/i', $sentence)) {
                    return true;
                }
                if ($word == 'are' && preg_match('/\b(he|she|it)\s+are\b/i', $sentence)) {
                    return true;
                }
                break;
                
            case 'tense':
                // 時態一致檢查
                if ($word == 'go' && preg_match('/\bhave\s+go\b/i', $sentence)) {
                    return true; // should be 'have gone'
                }
                break;
        }
        
        return false;
    }
    
    /**
     * 生成錯誤摘要
     * @param array $grammar_issues 文法問題
     * @return array 錯誤摘要
     */
    private function generateSummary($grammar_issues) {
        // 直接返回所有問題，不限制數量
        return $grammar_issues;
    }
    
    /**
     * 計算所有問題的總數
     * @param array $grammar_issues 文法問題
     * @return int 問題總數
     */
    private function countTotalIssues($grammar_issues) {
        $total = 0;
        foreach ($grammar_issues as $issues) {
            $total += count($issues);
        }
        return $total;
    }
    
    /**
     * 提交教師反饋到 NLP 服務以改進模型
     * @param array $feedback 反饋資料
     * @return bool 是否成功提交
     */
    public function submitFeedback($feedback) {
        if (!$this->use_nlp_service) {
            return false;
        }
        
        try {
            // 初始化 cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->nlp_service_url . '/api/submit_feedback');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($feedback));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            // 執行請求
            $response = curl_exec($ch);
            $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // 檢查是否成功
            if ($status_code == 200 && $response) {
                $result = json_decode($response, true);
                return isset($result['success']) && $result['success'];
            }
            
            return false;
        } catch (Exception $e) {
            error_log('提交反饋時發生錯誤: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 觸發模型訓練
     * @return array|bool 訓練結果或 false（如果失敗）
     */
    public function trainModel() {
        if (!$this->use_nlp_service) {
            return false;
        }
        
        try {
            // 初始化 cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->nlp_service_url . '/api/train_model');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);  // 增加超時時間，訓練可能需要更長時間
            
            // 執行請求
            $response = curl_exec($ch);
            $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
            
            // 檢查是否成功
            if ($status_code == 200 && $response) {
                return json_decode($response, true);
            }
            
            return false;
        } catch (Exception $e) {
            error_log('觸發模型訓練時發生錯誤: ' . $e->getMessage());
            return false;
        }
    }
}
?>