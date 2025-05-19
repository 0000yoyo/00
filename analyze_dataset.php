<?php
// analyze_dataset.php - 分析 ASAP 數據集並提取文法規則

// 設定文件路徑
$data_dir = __DIR__ . '/data/essays/';
$src_file = $data_dir . 'dev.src';
$ref_files = [
    $data_dir . 'dev.ref0',
    $data_dir . 'dev.ref1',
    $data_dir . 'dev.ref2',
    $data_dir . 'dev.ref3'
];

echo "<h1>ASAP 數據集文法規則提取</h1>";

// 檢查文件是否存在
echo "<h2>檢查文件</h2>";
$files_exist = true;

if (!file_exists($src_file)) {
    echo "<p style='color:red'>找不到源文件: " . htmlspecialchars($src_file) . "</p>";
    $files_exist = false;
}

foreach ($ref_files as $ref_file) {
    if (!file_exists($ref_file)) {
        echo "<p style='color:red'>找不到參考文件: " . htmlspecialchars($ref_file) . "</p>";
        $files_exist = false;
    }
}

if (!$files_exist) {
    echo "<p>請確保所有必要的數據集文件存在。</p>";
    exit;
}

echo "<p style='color:green'>所有數據集文件都存在</p>";

// 讀取源文件
$src_lines = file($src_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
echo "<p>源文件中共有 " . count($src_lines) . " 行文本</p>";

// 讀取參考文件
$ref_lines = [];
foreach ($ref_files as $i => $ref_file) {
    $ref_lines[$i] = file($ref_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    echo "<p>參考文件 " . ($i + 1) . " 中共有 " . count($ref_lines[$i]) . " 行文本</p>";
}

// 分析差異並提取規則
echo "<h2>分析差異並提取規則</h2>";

$grammar_rules = [
    'tense' => [],
    'subject_verb_agreement' => [],
    'plurals' => [],
    'article' => [],
    'grammar' => [],
    'spelling' => [],
    'punctuation' => [],
    'word_choice' => [],
    'structure' => [],
    'preposition' => []
];

// 存儲已發現的規則，避免重複
$found_rules = [];

// 對每一行源文本進行分析
for ($i = 0; $i < count($src_lines); $i++) {
    $src_line = $src_lines[$i];
    
    // 對每個參考文件
    foreach ($ref_lines as $ref_set) {
        if ($i < count($ref_set)) {
            $ref_line = $ref_set[$i];
            
            // 如果源文本和參考文本不同，分析差異
            if ($src_line !== $ref_line) {
                $diff_rules = analyze_differences($src_line, $ref_line);
                
                // 添加發現的規則
                foreach ($diff_rules as $rule) {
                    $rule_key = $rule['original'] . '|' . $rule['corrected'][0];
                    
                    if (!isset($found_rules[$rule_key])) {
                        $found_rules[$rule_key] = true;
                        $grammar_rules[$rule['type']][] = $rule;
                    }
                }
            }
        }
    }
}

// 分析源文本和參考文本之間的差異
function analyze_differences($src_text, $ref_text) {
    $rules = [];
    
    // 將文本拆分為單詞
    $src_words = preg_split('/\s+/', $src_text);
    $ref_words = preg_split('/\s+/', $ref_text);
    
    // 尋找簡單的單詞替換
    $max_length = max(count($src_words), count($ref_words));
    $i = 0; $j = 0;
    
    while ($i < count($src_words) && $j < count($ref_words)) {
        if (strtolower($src_words[$i]) !== strtolower($ref_words[$j])) {
            // 尋找下一個匹配點
            $next_match_i = $i;
            $next_match_j = $j;
            $found_match = false;
            
            // 向前尋找最近的匹配點
            for ($k = 1; $k <= 3; $k++) {
                if ($i + $k < count($src_words) && $j + $k < count($ref_words) && 
                    strtolower($src_words[$i + $k]) === strtolower($ref_words[$j + $k])) {
                    $next_match_i = $i + $k;
                    $next_match_j = $j + $k;
                    $found_match = true;
                    break;
                }
            }
            
            if ($found_match) {
                // 提取差異短語
                $src_phrase = implode(' ', array_slice($src_words, $i, $next_match_i - $i));
                $ref_phrase = implode(' ', array_slice($ref_words, $j, $next_match_j - $j));
                
                // 分類錯誤類型
                $error_type = classify_error($src_phrase, $ref_phrase);
                
                // 添加規則
                $rules[] = [
                    'type' => $error_type,
                    'original' => $src_phrase,
                    'corrected' => [$ref_phrase],
                    'count' => 1,
                    'examples' => [$src_text]
                ];
                
                // 更新索引
                $i = $next_match_i;
                $j = $next_match_j;
            } else {
                // 如果找不到匹配點，則跳過當前單詞
                $i++;
                $j++;
            }
        } else {
            // 如果單詞匹配，則前進
            $i++;
            $j++;
        }
    }
    
    return $rules;
}

// 分類錯誤類型
function classify_error($original, $corrected) {
    // 時態錯誤
    $tense_patterns = [
        '/ed\b/' => '/\b(bought|went|saw|had|done|made|said|come|known|given|shown)\b/',
        '/\b(buy|go|see|have|do|make|say|come|know|give|show)ed\b/' => '/\b(bought|went|saw|had|done|made|said|came|knew|gave|showed)\b/',
        '/\bto [a-z]+ed\b/' => '/\bto [a-z]+\b/'
    ];
    
    foreach ($tense_patterns as $pattern1 => $pattern2) {
        if (preg_match($pattern1, $original) && preg_match($pattern2, $corrected)) {
            return 'tense';
        }
    }
    
    // 主謂一致性問題
    $sv_patterns = [
        '/\b(I|we|you|they)\s+(is|was|has)\b/i' => 'subject_verb_agreement',
        '/\b(he|she|it)\s+(are|were|have)\b/i' => 'subject_verb_agreement',
        '/\b(is|are|was|were)\s+(\w+ing)\b/i' => 'subject_verb_agreement',
        '/\b(\w+)\s+(write|express|make|do)\b/i' => 'subject_verb_agreement'
    ];
    
    foreach ($sv_patterns as $pattern => $type) {
        if (preg_match($pattern, $original)) {
            return $type;
        }
    }
    
    // 複數形式錯誤
    if (preg_match('/\b(\w+)s\b/i', $original) && preg_match('/\b(\w+)\b/i', $corrected)) {
        return 'plurals';
    }
    
    // 冠詞使用錯誤
    if (preg_match('/\b(a|an|the)\b/i', $original) || preg_match('/\b(a|an|the)\b/i', $corrected)) {
        return 'article';
    }
    
    // 介詞使用錯誤
    $prepositions = ['in', 'on', 'at', 'for', 'with', 'by', 'to', 'from', 'of', 'about'];
    foreach ($prepositions as $prep) {
        if (preg_match('/\b' . $prep . '\b/i', $original) || preg_match('/\b' . $prep . '\b/i', $corrected)) {
            return 'preposition';
        }
    }
    
    // 拼寫錯誤 - 如果長度相近但有差異
    if (abs(strlen($original) - strlen($corrected)) <= 3) {
        return 'spelling';
    }
    
    // 詞語選擇不當
    if (str_word_count($original) === str_word_count($corrected)) {
        return 'word_choice';
    }
    
    // 預設為一般文法錯誤
    return 'grammar';
}

// 輸出已提取的規則
echo "<h2>提取的規則</h2>";
$total_rules = 0;

foreach ($grammar_rules as $type => $rules) {
    $total_rules += count($rules);
    echo "<h3>" . htmlspecialchars($type) . " (" . count($rules) . ")</h3>";
    
    if (!empty($rules)) {
        echo "<ul>";
        // 只顯示前10條規則
        $display_count = min(count($rules), 10);
        for ($i = 0; $i < $display_count; $i++) {
            $rule = $rules[$i];
            echo "<li>";
            echo htmlspecialchars($rule['original']) . " → " . htmlspecialchars($rule['corrected'][0]);
            echo "</li>";
        }
        echo "</ul>";
        
        if (count($rules) > 10) {
            echo "<p>... 以及更多 " . (count($rules) - 10) . " 條規則</p>";
        }
    } else {
        echo "<p>此類型沒有提取到規則</p>";
    }
}

echo "<p>總共提取了 " . $total_rules . " 條規則</p>";

// 手動添加一些基本規則
$basic_rules = [
    'tense' => [
        ['original' => 'buyed', 'corrected' => ['bought'], 'count' => 1],
        ['original' => 'eated', 'corrected' => ['ate'], 'count' => 1],
        ['original' => 'I plan to ate', 'corrected' => ['I plan to eat'], 'count' => 1]
    ],
    'subject_verb_agreement' => [
        ['original' => 'I has', 'corrected' => ['I have'], 'count' => 1],
        ['original' => 'She write', 'corrected' => ['She writes'], 'count' => 1],
        ['original' => 'This essay express', 'corrected' => ['This essay expresses'], 'count' => 1]
    ],
    'plurals' => [
        ['original' => 'childrens', 'corrected' => ['children'], 'count' => 1]
    ],
    'grammar' => [
        ['original' => 'My brother and me went', 'corrected' => ['My brother and I went'], 'count' => 1]
    ]
];

// 合併基本規則
foreach ($basic_rules as $type => $rules) {
    foreach ($rules as $rule) {
        $rule_key = $rule['original'] . '|' . $rule['corrected'][0];
        
        if (!isset($found_rules[$rule_key])) {
            $found_rules[$rule_key] = true;
            $grammar_rules[$type][] = $rule;
            $total_rules++;
        }
    }
}

echo "<p>添加基本規則後，總共有 " . $total_rules . " 條規則</p>";

// 保存文法規則
$descriptions = [
    'tense' => '時態使用錯誤',
    'subject_verb_agreement' => '主謂一致性問題',
    'plurals' => '複數形式錯誤',
    'article' => '冠詞使用錯誤',
    'grammar' => '文法錯誤',
    'spelling' => '拼寫錯誤',
    'punctuation' => '標點符號錯誤',
    'word_choice' => '詞語選擇不當',
    'structure' => '句子結構問題',
    'preposition' => '介詞使用錯誤',
    'unknown' => '其他錯誤'
];

$final_rules = [
    'rules' => $grammar_rules,
    'descriptions' => $descriptions
];

// 確保模型目錄存在
$models_dir = __DIR__ . '/models';
if (!file_exists($models_dir)) {
    mkdir($models_dir, 0755, true);
}

// 序列化並保存文法規則
$rules_path = $models_dir . '/grammar_rules.pkl';
$serialized = serialize($final_rules);

if (file_put_contents($rules_path, $serialized) !== false) {
    echo "<p style='color:green'>成功保存文法規則到: " . htmlspecialchars($rules_path) . "</p>";
    echo "<p>文件大小: " . filesize($rules_path) . " 字節</p>";
} else {
    echo "<p style='color:red'>保存文法規則失敗!</p>";
}

// 提供下一步操作的建議
echo "<h2>下一步操作</h2>";
echo "<p>文法規則已提取並保存。接下來您可以:</p>";
echo "<ol>";
echo "<li><a href='check_rules.php'>檢查文法規則文件</a> - 確認規則是否正確保存</li>";
echo "<li><a href='simple_test.php'>測試文法檢測功能</a> - 使用簡化版本測試文法檢測</li>";
echo "</ol>";
?>