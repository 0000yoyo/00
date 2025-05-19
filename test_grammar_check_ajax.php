<?php
// 修改 test_grammar_check_ajax.php

session_start();
require_once 'db_connection.php';

// 處理文法檢測請求
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['text'])) {
    try {
        $text = $_POST['text'];
        
        // 直接載入文法規則
        $grammar_rules_path = __DIR__ . '/models/grammar_rules.pkl';
        $grammar_rules = null;
        $descriptions = [];
        
        if (file_exists($grammar_rules_path)) {
            try {
                $data = file_get_contents($grammar_rules_path);
                $grammar_rules = unserialize($data);
                
                if (isset($grammar_rules['descriptions'])) {
                    $descriptions = $grammar_rules['descriptions'];
                }
                
                // 檢查文法問題
                $grammar_issues = [];
                
                if (isset($grammar_rules['rules'])) {
                    foreach ($grammar_rules['rules'] as $error_type => $rules) {
                        $found_issues = [];
                        
                        foreach ($rules as $rule) {
                            if (empty($rule['original'])) continue;
                            
                            $original = $rule['original'];
                            $pattern = '/\b' . preg_quote($original, '/') . '\b/i';
                            
                            if (preg_match($pattern, $text)) {
                                $corrected = !empty($rule['corrected'][0]) ? $rule['corrected'][0] : "";
                                $suggestion = "'{$original}' 可能應為 '{$corrected}'";
                                
                                if (!in_array($suggestion, $found_issues)) {
                                    $found_issues[] = $suggestion;
                                }
                            }
                        }
                        
                        if (!empty($found_issues)) {
                            $grammar_issues[$error_type] = $found_issues;
                        }
                    }
                }
                
                // 返回結果
                header('Content-Type: application/json');
                echo json_encode([
                    'grammar_issues' => $grammar_issues,
                    'descriptions' => $descriptions
                ]);
                exit;
                
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(['error' => '載入文法規則失敗: ' . $e->getMessage()]);
                exit;
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(['error' => '找不到文法規則檔案: ' . $grammar_rules_path]);
            exit;
        }
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '處理請求時發生錯誤: ' . $e->getMessage()]);
        exit;
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => '無效的請求。需要text參數。']);
    exit;
}
?>