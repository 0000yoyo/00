<?php
// advanced_ai_review_service.php
require_once 'grammar_analysis_service.php';

class AdvancedAIReviewService {
    private $model_version = '1.0.0';
    private $python_script_path;
    private $model_path;
    private $grammar_service;
    
    /**
     * 構造函數
     */
    public function __construct() {
        $this->python_script_path = __DIR__ . '/scripts/score_essay.py';
        $this->model_path = __DIR__ . '/models/essay_scoring_model_v' . $this->model_version . '.pkl';
        $this->grammar_service = new GrammarAnalysisService();
        
        // 確保 Python 腳本和模型文件存在
        $this->checkDependencies();
    }
    
    /**
     * 檢查依賴
     */
    private function checkDependencies() {
        if (!file_exists($this->python_script_path)) {
            error_log("評分腳本不存在: " . $this->python_script_path);
        }
        
        if (!file_exists($this->model_path)) {
            error_log("模型文件不存在: " . $this->model_path . " (初次使用時為正常情況，將使用基於特徵的初步評分)");
        }
    }
    
    /**
     * 使用 AI 批改作文
     * @param string $content 作文內容
     * @param string $category 作文類型
     * @return array 批改結果，包含 'feedback' 和 'score'
     */
    public function reviewEssay($content, $category) {
        // 使用新的文法分析服務
        try {
            $result = $this->grammar_service->analyzeGrammar($content, $category);
            
            // 添加模型版本信息
            $result['model_version'] = isset($result['model_version']) ? $result['model_version'] : $this->model_version;
            
            // 確保回饋欄位存在
            if (!isset($result['feedback'])) {
                // 計算基本文本特徵
                $word_count = str_word_count($content);
                $sentence_count = preg_match_all('/[.!?]+/', $content, $matches);
                
                // 生成評語，基於文法分析的結果
                $feedback = "系統評分：{$result['score']}分。這篇{$this->categoryToChinese($category)}包含約{$word_count}個字";
                if ($sentence_count > 0) {
                    $feedback .= "，分為約{$sentence_count}個句子";
                }
                
                // 添加文法分析結果
                if (isset($result['grammar_issues']) && !empty($result['grammar_issues'])) {
                    $feedback .= "\n\n【文法分析】\n";
                    
                    // 計算總問題數
                    $total_issues = 0;
                    $type_count = 0;
                    foreach ($result['grammar_issues'] as $type => $issues) {
                        $total_issues += count($issues);
                        $type_count++;
                    }
                    
                    // 添加總結統計
                    $feedback .= "共發現 {$total_issues} 個文法問題，分屬 {$type_count} 種不同類型。\n\n";
                    
                    foreach ($result['grammar_issues'] as $type => $issues) {
                        $error_type = $this->getErrorTypeDescription($type);
                        $issue_count = count($issues);
                        $feedback .= "發現可能的{$error_type} ({$issue_count}個):\n";
                        
                        // 顯示所有問題，不限制數量
                        foreach ($issues as $issue) {
                            $feedback .= "- {$issue}\n";
                        }
                        
                        $feedback .= "\n";
                    }
                } else {
                    $feedback .= "\n\n【文法分析】\n未發現明顯的文法問題，文法使用良好。";
                }
                
                // 添加詞彙多樣性分析
                $unique_words = count(array_unique(explode(' ', strtolower(preg_replace('/[^\w\s]/', '', $content)))));
                $total_words = max(1, str_word_count($content));
                $lexical_diversity = $unique_words / $total_words;
                
                $feedback .= "\n\n【詞彙豐富度】";
                if ($lexical_diversity > 0.7) {
                    $feedback .= "文章使用了豐富多樣的詞彙，詞彙選擇精準。";
                } elseif ($lexical_diversity > 0.5) {
                    $feedback .= "文章的詞彙多樣性良好，但仍可以進一步豐富。";
                } elseif ($lexical_diversity > 0.3) {
                    $feedback .= "文章中有較多重複用詞，建議擴充詞彙，使用更多同義詞。";
                } else {
                    $feedback .= "文章詞彙重複率較高，建議提升詞彙量。";
                }
                
                // 句子流暢度分析
                $avg_sentence_length = $total_words / max(1, $sentence_count);
                $feedback .= "\n\n【句子流暢度】";
                if ($avg_sentence_length > 25) {
                    $feedback .= "文章使用了較長的句子，部分句子可能影響閱讀流暢度。建議適當拆分長句，提高可讀性。";
                } elseif ($avg_sentence_length > 15) {
                    $feedback .= "文章句子長度適中，閱讀流暢。";
                } else {
                    $feedback .= "文章句子較短，可以嘗試增加一些複雜句型，提升文章層次感。";
                }
                
                // 結構分析
                $feedback .= "\n\n【結構分析】";
                if ($sentence_count < 3) {
                    $feedback .= "文章篇幅較短，建議增加內容，豐富論述。";
                } else {
                    $paragraph_count = substr_count($content, "\n\n") + 1;
                    if ($paragraph_count < 2) {
                        $feedback .= "文章段落劃分不明顯，建議適當分段，增加結構清晰度。";
                    } else {
                        $feedback .= "文章結構相對完整。";
                    }
                }
                
                // 添加提醒
                $feedback .= "\n\n這是系統基於文本特徵的初步評估，最終評分將由教師進行審核。";
                
                $result['feedback'] = $feedback;
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("AI 評分異常: " . $e->getMessage());
            return $this->simulateReview($content, $category);
        }
    }
    
    /**
     * 獲取錯誤類型的中文描述
     * @param string $error_type 錯誤類型
     * @return string 中文描述
     */
    private function getErrorTypeDescription($error_type) {
        $descriptions = [
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
        
        return isset($descriptions[$error_type]) ? $descriptions[$error_type] : $error_type;
    }
    
    /**
     * 模擬評分（作為備份使用）- 基於文本特徵的簡單評分
     * @param string $content 作文內容
     * @param string $category 作文類型
     * @return array 批改結果，包含 'feedback' 和 'score'
     */
    private function simulateReview($content, $category) {
        // 計算基本文本特徵
        $word_count = str_word_count($content);
        $sentence_count = preg_match_all('/[.!?]+/', $content, $matches);
        
        // 基於字數的評分
        $base_score = 75;  // 基礎分數
        $length_bonus = min(10, $word_count / 100);  // 每100字最多加10分
        
        // 最終分數計算
        $score = min(98, max(60, $base_score + $length_bonus));
        
        // 生成評語
        $feedback = "系統評分：{$score}分。這篇{$this->categoryToChinese($category)}包含約{$word_count}個字";
        if ($sentence_count > 0) {
            $feedback .= "，分為約{$sentence_count}個句子";
        }
        $feedback .= "。這是系統基於基本文本特徵的初步評估，最終評分將由教師進行審核。";
        
        return [
            'feedback' => $feedback,
            'score' => (int)$score,
            'model_version' => 'simulated',
            'is_simulated' => true
        ];
    }
    
    /**
     * 將作文類型英文轉換為中文
     * @param string $category 作文類型
     * @return string 中文名稱
     */
    private function categoryToChinese($category) {
        $mapping = [
            'narrative' => '敘事文',
            'descriptive' => '描述文',
            'argumentative' => '論說文',
            'expository' => '說明文',
            'compare_contrast' => '比較對比文',
            'persuasive' => '議論文',
            'reflective' => '反思文',
            'critical_analysis' => '批評性分析文'
        ];
        
        return isset($mapping[$category]) ? $mapping[$category] : '作文';
    }
}
?>