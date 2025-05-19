<?php
// add_grammar_rule.php
header('Content-Type: application/json');

// 檢查是否為 POST 請求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '只接受 POST 請求']);
    exit;
}

// 獲取提交的數據
$error_type = $_POST['error_type'] ?? '';
$wrong_expression = $_POST['wrong_expression'] ?? '';
$correct_expression = $_POST['correct_expression'] ?? '';

// 檢查必要參數
if (empty($error_type) || empty($wrong_expression) || empty($correct_expression)) {
    echo json_encode(['success' => false, 'message' => '缺少必要參數']);
    exit;
}

// 定義文法規則檔案路徑
$models_dir = __DIR__ . '/models';
$rules_path = $models_dir . '/grammar_rules.pkl';

// 確保目錄存在
if (!file_exists($models_dir)) {
    if (!mkdir($models_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => '無法創建模型目錄']);
        exit;
    }
}

// 嘗試載入現有規則
$grammar_rules = [];
if (file_exists($rules_path)) {
    try {
        $data = file_get_contents($rules_path);
        $grammar_rules = unserialize($data);
    } catch (Exception $e) {
        // 如果無法讀取，創建新的規則結構
        $grammar_rules = [
            'rules' => [],
            'descriptions' => [
                'spelling' => '拼寫錯誤',
                'grammar' => '文法錯誤',
                'punctuation' => '標點符號錯誤',
                'word_choice' => '詞語選擇不當',
                'structure' => '句子結構問題',
                'article' => '冠詞使用錯誤',
                'tense' => '時態使用錯誤',
                'subject_verb_agreement' => '主謂一致性問題',
                'preposition' => '介詞使用錯誤',
                'plurals' => '複數形式錯誤',
                'unknown' => '其他錯誤'
            ]
        ];
    }
}

// 確保規則結構完整
if (!isset($grammar_rules['rules'])) {
    $grammar_rules['rules'] = [];
}

// 確保錯誤類型存在
if (!isset($grammar_rules['rules'][$error_type])) {
    $grammar_rules['rules'][$error_type] = [];
}

// 檢查是否已存在相同規則
$rule_exists = false;
foreach ($grammar_rules['rules'][$error_type] as &$rule) {
    if ($rule['original'] === $wrong_expression) {
        // 更新現有規則
        $rule['corrected'] = [$correct_expression];
        $rule['count'] = isset($rule['count']) ? $rule['count'] + 1 : 1;
        $rule_exists = true;
        break;
    }
}

// 如果不存在，添加新規則
if (!$rule_exists) {
    $grammar_rules['rules'][$error_type][] = [
        'original' => $wrong_expression,
        'corrected' => [$correct_expression],
        'count' => 1,
        'examples' => []
    ];
}

// 保存更新後的規則
if (file_put_contents($rules_path, serialize($grammar_rules)) !== false) {
    echo json_encode(['success' => true, 'message' => '文法規則已成功添加']);
} else {
    echo json_encode(['success' => false, 'message' => '保存文法規則失敗']);
}
?>