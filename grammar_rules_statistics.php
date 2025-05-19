<?php
// grammar_rules_statistics.php - 顯示文法規則統計信息

// 設定執行環境
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 載入文法規則
$rules_path = __DIR__ . '/models/grammar_rules.pkl';

echo "========== 文法規則統計 ==========\n\n";

if (!file_exists($rules_path)) {
    echo "錯誤: 找不到文法規則文件: {$rules_path}\n";
    exit(1);
}

try {
    $data = file_get_contents($rules_path);
    $grammar_rules = unserialize($data);
    
    if (!$grammar_rules || !isset($grammar_rules['rules'])) {
        echo "錯誤: 無法解析文法規則文件或格式不正確\n";
        exit(1);
    }
    
    echo "文法規則檔案大小: " . round(filesize($rules_path) / 1024, 2) . " KB\n\n";
    
    // 初始化統計信息
    $total_rules = 0;
    $single_word_rules = 0;
    $multi_word_rules = 0;
    $type_stats = [];
    
    // 處理每種錯誤類型的規則
    foreach ($grammar_rules['rules'] as $error_type => $rules) {
        $single_in_type = 0;
        $multi_in_type = 0;
        
        foreach ($rules as $rule) {
            if (empty($rule['original'])) {
                continue;
            }
            
            // 計算單詞數量
            $word_count = count(array_filter(explode(' ', trim($rule['original']))));
            
            if ($word_count > 1) {
                $multi_word_rules++;
                $multi_in_type++;
            } else {
                $single_word_rules++;
                $single_in_type++;
            }
        }
        
        $total_in_type = $single_in_type + $multi_in_type;
        $total_rules += $total_in_type;
        
        $type_description = isset($grammar_rules['descriptions'][$error_type]) 
            ? $grammar_rules['descriptions'][$error_type] 
            : $error_type;
        
        $type_stats[$error_type] = [
            'description' => $type_description,
            'total' => $total_in_type,
            'single_word' => $single_in_type,
            'multi_word' => $multi_in_type,
            'percent_single' => ($total_in_type > 0) ? round(($single_in_type / $total_in_type) * 100, 2) : 0
        ];
    }
    
    // 計算整體百分比
    $percent_single = ($total_rules > 0) ? round(($single_word_rules / $total_rules) * 100, 2) : 0;
    $percent_multi = ($total_rules > 0) ? round(($multi_word_rules / $total_rules) * 100, 2) : 0;
    
    // 輸出總體統計
    echo "總規則數: {$total_rules}\n";
    echo "單字規則: {$single_word_rules} ({$percent_single}%)\n";
    echo "多字規則: {$multi_word_rules} ({$percent_multi}%)\n\n";
    
    // 按錯誤類型顯示統計
    echo "各錯誤類型統計:\n";
    echo "=================\n\n";
    
    // 按規則總數排序
    uasort($type_stats, function($a, $b) {
        return $b['total'] - $a['total'];
    });
    
    $format = "%-30s | %-10s | %-15s | %-15s | %-15s\n";
    printf($format, "錯誤類型", "總規則數", "單字規則數", "多字規則數", "單字規則佔比");
    echo str_repeat("-", 100) . "\n";
    
    foreach ($type_stats as $type => $stats) {
        printf(
            $format, 
            mb_substr($stats['description'], 0, 28),
            $stats['total'],
            $stats['single_word'],
            $stats['multi_word'],
            $stats['percent_single'] . "%"
        );
    }
    
    echo "\n";
    echo "示例規則:\n";
    echo "=================\n\n";
    
    // 顯示每種類型的示例規則
    $counter = 0;
    foreach ($type_stats as $type => $stats) {
        if ($counter++ >= 5) break; // 只顯示前5種類型
        
        echo "{$stats['description']}:\n";
        
        // 顯示2個多字規則示例
        $found = 0;
        foreach ($grammar_rules['rules'][$type] as $rule) {
            if (empty($rule['original'])) continue;
            
            $word_count = count(array_filter(explode(' ', trim($rule['original']))));
            if ($word_count > 1) {
                echo "  ✓ '{$rule['original']}' → '{$rule['corrected'][0]}'\n";
                if (++$found >= 2) break;
            }
        }
        
        // 顯示1個單字規則示例
        $found = 0;
        foreach ($grammar_rules['rules'][$type] as $rule) {
            if (empty($rule['original'])) continue;
            
            $word_count = count(array_filter(explode(' ', trim($rule['original']))));
            if ($word_count <= 1) {
                echo "  ✗ '{$rule['original']}' → '{$rule['corrected'][0]}'\n";
                if (++$found >= 1) break;
            }
        }
        
        echo "\n";
    }
    
    echo "✓ 標記的是多字規則 (將被保留)\n";
    echo "✗ 標記的是單字規則 (將被移除)\n";
    
} catch (Exception $e) {
    echo "錯誤: " . $e->getMessage() . "\n";
}
?>