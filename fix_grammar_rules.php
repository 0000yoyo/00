<?php
// fix_grammar_rules.php - 用於重新初始化文法規則檔案

// 定義基本的錯誤類型描述
$error_descriptions = [
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

// 初始化空規則
$grammar_rules = [
    "rules" => [
        "spelling" => [],
        "grammar" => [],
        "punctuation" => [],
        "word_choice" => [],
        "structure" => [],
        "article" => [],
        "tense" => [],
        "subject_verb_agreement" => [],
        "preposition" => [],
        "plurals" => [],
    ],
    "descriptions" => $error_descriptions
];

// 確保模型目錄存在
$models_dir = __DIR__ . '/models';
if (!file_exists($models_dir)) {
    mkdir($models_dir, 0755, true);
    echo "已創建模型目錄: $models_dir<br>";
}

// 序列化並保存文法規則
$rules_path = $models_dir . '/grammar_rules.pkl';
$serialized = serialize($grammar_rules);
if (file_put_contents($rules_path, $serialized) !== false) {
    echo "成功創建初始文法規則檔案: $rules_path<br>";
    echo "檔案大小: " . filesize($rules_path) . " bytes<br>";
} else {
    echo "創建文法規則檔案失敗<br>";
}

// 測試讀取剛保存的檔案
try {
    $data = file_get_contents($rules_path);
    $loaded_rules = unserialize($data);
    
    if ($loaded_rules) {
        echo "成功讀取文法規則檔案<br>";
        echo "規則類型數量: " . count($loaded_rules['rules']) . "<br>";
        echo "描述數量: " . count($loaded_rules['descriptions']) . "<br>";
    } else {
        echo "讀取文法規則檔案失敗，無法反序列化<br>";
    }
} catch (Exception $e) {
    echo "讀取檔案時發生錯誤: " . $e->getMessage() . "<br>";
}
?>