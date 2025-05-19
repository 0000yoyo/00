<?php
// reset_grammar_rules.php - 重置文法規則到初始狀態

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

// 初始化基本規則 - 這些是我們確定需要的規則
$basic_rules = [
    "tense" => [
        [
            "original" => "buyed",
            "corrected" => ["bought"],
            "count" => 1
        ],
        [
            "original" => "I plan to ate",
            "corrected" => ["I plan to eat"],
            "count" => 1
        ]
    ],
    "subject_verb_agreement" => [
        [
            "original" => "I has",
            "corrected" => ["I have"],
            "count" => 1
        ],
        [
            "original" => "She write",
            "corrected" => ["She writes"],
            "count" => 1
        ],
        [
            "original" => "This essay express",
            "corrected" => ["This essay expresses"],
            "count" => 1
        ]
    ],
    "plurals" => [
        [
            "original" => "childrens",
            "corrected" => ["children"],
            "count" => 1
        ]
    ],
    "grammar" => [
        [
            "original" => "My brother and me went",
            "corrected" => ["My brother and I went"],
            "count" => 1
        ]
    ]
];

// 初始化空規則結構，包含基本規則
$grammar_rules = [
    "rules" => $basic_rules,
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
        
        // 輸出所有規則
        echo "<h3>當前規則:</h3>";
        foreach ($loaded_rules['rules'] as $type => $rules) {
            echo "<h4>" . htmlspecialchars($loaded_rules['descriptions'][$type]) . " (" . count($rules) . "):</h4>";
            echo "<ul>";
            foreach ($rules as $rule) {
                $original = htmlspecialchars($rule['original']);
                $corrected = htmlspecialchars($rule['corrected'][0]);
                echo "<li>'$original' → '$corrected'</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "讀取文法規則檔案失敗，無法反序列化<br>";
    }
} catch (Exception $e) {
    echo "讀取檔案時發生錯誤: " . $e->getMessage() . "<br>";
}
?>