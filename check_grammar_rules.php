

<?php
// 載入資料庫連線
require_once 'db_connection.php';

// 設定路徑
$grammar_rules_path = __DIR__ . '/models/grammar_rules.pkl';

// 檢查文件是否存在
if (!file_exists($grammar_rules_path)) {
    echo "文法規則文件不存在: {$grammar_rules_path}\n";
    exit;
}

try {
    // 讀取文法規則
    $data = file_get_contents($grammar_rules_path);
    $grammar_rules = unserialize($data);
    
    if (!$grammar_rules) {
        echo "無法反序列化文法規則檔案\n";
        exit;
    }
    
    // 輸出基本統計
    echo "文法規則載入成功!\n";
    echo "規則類型數: " . count($grammar_rules['rules']) . "\n\n";
    
    // 檢查各類規則
    foreach ($grammar_rules['rules'] as $type => $rules) {
        echo "類型: {$type} (" . ($grammar_rules['descriptions'][$type] ?? $type) . ")\n";
        echo "規則數量: " . count($rules) . "\n";
        
        // 顯示前5條規則作為範例
        if (count($rules) > 0) {
            echo "範例規則:\n";
            $i = 0;
            foreach ($rules as $rule) {
                if ($i >= 5) break; // 只顯示前5條
                echo "  - '{$rule['original']}' → '";
                echo isset($rule['corrected'][0]) ? $rule['corrected'][0] : "無";
                echo "' (頻率: " . ($rule['count'] ?? '未知') . ")\n";
                $i++;
            }
        }
        echo "\n";
    }
    
    // 檢查訓練反饋是否被處理
    if ($conn) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total, 
                  SUM(CASE WHEN processed = 1 THEN 1 ELSE 0 END) as processed
            FROM ai_grammar_feedback
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "反饋資料統計:\n";
        echo "總計: {$result['total']}\n";
        echo "已處理: {$result['processed']} (" . 
             round(($result['processed'] / max($result['total'], 1)) * 100, 2) . "%)\n";
    }
    
} catch (Exception $e) {
    echo "檢查過程中發生錯誤: " . $e->getMessage() . "\n";
}
?>