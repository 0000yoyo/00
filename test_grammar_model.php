

<?php
require_once 'db_connection.php';
require_once 'grammar_analysis_service.php';

// 測試文本 - 包含您知道的特定錯誤類型
$test_cases = [
    // 原始文本 => [預期找到的錯誤類型]
    "I go to the beach yesterday" => ['tense'],
    "They is going to school tomorrow" => ['subject_verb_agreement'],
    "I have a apple" => ['article'],
    "We have five hour to finish the work" => ['plurals'],
    "I laying on the beach" => ['tense'], // 缺少助動詞
    "I going to bring my camera" => ['tense'], // 缺少助動詞
    "She reading a book" => ['tense'], // 缺少助動詞
    "The sun setting in the west" => ['tense'], // 缺少助動詞
    "under umbrella" => ['article'], // 缺少冠詞
    // 添加您先前發現AI未識別的其他例子
];

echo "開始測試文法識別能力...\n\n";

// 創建文法分析服務
$grammar_service = new GrammarAnalysisService();

// 測試每個案例
foreach ($test_cases as $text => $expected_types) {
    echo "測試文本: \"{$text}\"\n";
    echo "預期錯誤類型: " . implode(", ", $expected_types) . "\n";
    
    // 執行文法分析
    $result = $grammar_service->analyzeGrammar($text);
    
    // 檢查識別結果
    if (empty($result['grammar_issues'])) {
        echo "結果: 未識別出任何錯誤 ❌\n";
    } else {
        echo "識別出的錯誤:\n";
        $found_expected = false;
        foreach ($result['grammar_issues'] as $type => $issues) {
            echo "- {$type}: " . implode(", ", $issues) . "\n";
            if (in_array($type, $expected_types)) {
                $found_expected = true;
            }
        }
        
        echo "是否識別出預期類型: " . ($found_expected ? "是 ✓" : "否 ❌") . "\n";
    }
    echo "----------------------------\n";
}
?>