<?php
// 創建 admin_grammar_rules.php

session_start();
require_once 'db_connection.php';

// 確保只有管理員可以訪問
if(!isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true){
    header("location: login.php");
    exit;
}

// 處理表單提交 - 手動添加規則
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_rule') {
    $error_type = $_POST['error_type'] ?? '';
    $original = $_POST['original'] ?? '';
    $corrected = $_POST['corrected'] ?? '';
    
    if (!empty($error_type) && !empty($original) && !empty($corrected)) {
        // 讀取現有規則
        $grammar_rules_path = __DIR__ . '/models/grammar_rules.pkl';
        $temp_json_path = __DIR__ . '/temp/temp_rules_' . uniqid() . '.json';
        
        $cmd = "python " . escapeshellarg(__DIR__ . '/scripts/read_grammar_rules.py') . 
               " --input " . escapeshellarg($grammar_rules_path) . 
               " --output " . escapeshellarg($temp_json_path);
        
        exec($cmd, $output, $return_var);
        
        if ($return_var === 0 && file_exists($temp_json_path)) {
            $grammar_rules = json_decode(file_get_contents($temp_json_path), true);
            
            // 確保錯誤類型存在
            if (!isset($grammar_rules['rules'][$error_type])) {
                $grammar_rules['rules'][$error_type] = [];
            }
            
            // 檢查是否已存在相同規則
            $rule_exists = false;
            foreach ($grammar_rules['rules'][$error_type] as &$rule) {
                if ($rule['original'] === $original) {
                    // 更新現有規則
                    if (!in_array($corrected, $rule['corrected'])) {
                        $rule['corrected'][] = $corrected;
                    }
                    $rule['count'] = ($rule['count'] ?? 0) + 1;
                    $rule_exists = true;
                    break;
                }
            }
            
            // 如果規則不存在，添加新規則
            if (!$rule_exists) {
                $grammar_rules['rules'][$error_type][] = [
                    'original' => $original,
                    'corrected' => [$corrected],
                    'count' => 1,
                    'examples' => []
                ];
            }
            
            // 保存更新後的規則
            $updated_json_path = __DIR__ . '/temp/updated_rules_' . uniqid() . '.json';
            file_put_contents($updated_json_path, json_encode($grammar_rules, JSON_UNESCAPED_UNICODE));
            
            $cmd = "python " . escapeshellarg(__DIR__ . '/scripts/save_grammar_rules.py') . 
                   " --input " . escapeshellarg($updated_json_path) . 
                   " --output " . escapeshellarg($grammar_rules_path);
            
            exec($cmd, $output, $return_var);
            
            // 清理臨時文件
            @unlink($temp_json_path);
            @unlink($updated_json_path);
            
            $success_message = "成功添加文法規則: '{$original}' -> '{$corrected}'";
        } else {
            $error_message = "讀取文法規則失敗";
        }
    } else {
        $error_message = "請填寫所有必要欄位";
    }
}

// 處理規則刪除
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_rule') {
    $error_type = $_POST['error_type'] ?? '';
    $rule_index = isset($_POST['rule_index']) ? intval($_POST['rule_index']) : -1;
    
    if (!empty($error_type) && $rule_index >= 0) {
        // 讀取現有規則
        $grammar_rules_path = __DIR__ . '/models/grammar_rules.pkl';
        $temp_json_path = __DIR__ . '/temp/temp_rules_' . uniqid() . '.json';
        
        $cmd = "python " . escapeshellarg(__DIR__ . '/scripts/read_grammar_rules.py') . 
               " --input " . escapeshellarg($grammar_rules_path) . 
               " --output " . escapeshellarg($temp_json_path);
        
        exec($cmd, $output, $return_var);
        
        if ($return_var === 0 && file_exists($temp_json_path)) {
            $grammar_rules = json_decode(file_get_contents($temp_json_path), true);
            
            // 確認錯誤類型和索引是否存在
            if (isset($grammar_rules['rules'][$error_type]) && 
                isset($grammar_rules['rules'][$error_type][$rule_index])) {
                
                // 刪除規則
                $deleted_rule = $grammar_rules['rules'][$error_type][$rule_index];
                array_splice($grammar_rules['rules'][$error_type], $rule_index, 1);
                
                // 保存更新後的規則
                $updated_json_path = __DIR__ . '/temp/updated_rules_' . uniqid() . '.json';
                file_put_contents($updated_json_path, json_encode($grammar_rules, JSON_UNESCAPED_UNICODE));
                
                $cmd = "python " . escapeshellarg(__DIR__ . '/scripts/save_grammar_rules.py') . 
                       " --input " . escapeshellarg($updated_json_path) . 
                       " --output " . escapeshellarg($grammar_rules_path);
                
                exec($cmd, $output, $return_var);
                
                // 清理臨時文件
                @unlink($temp_json_path);
                @unlink($updated_json_path);
                
                $success_message = "成功刪除文法規則: '{$deleted_rule['original']}'";
            } else {
                $error_message = "找不到指定的規則";
            }
        } else {
            $error_message = "讀取文法規則失敗";
        }
    } else {
        $error_message = "缺少必要參數";
    }
}

// 獲取當前規則
$grammar_rules = [];
$grammar_rules_path = __DIR__ . '/models/grammar_rules.pkl';

if (file_exists($grammar_rules_path)) {
    $temp_json_path = __DIR__ . '/temp/temp_rules_' . uniqid() . '.json';
    
    if (!file_exists(__DIR__ . '/temp')) {
        mkdir(__DIR__ . '/temp', 0755, true);
    }
    
    $cmd = "python " . escapeshellarg(__DIR__ . '/scripts/read_grammar_rules.py') . 
           " --input " . escapeshellarg($grammar_rules_path) . 
           " --output " . escapeshellarg($temp_json_path);
    
    exec($cmd, $output, $return_var);
    
    if ($return_var === 0 && file_exists($temp_json_path)) {
        $grammar_rules = json_decode(file_get_contents($temp_json_path), true);
        @unlink($temp_json_path);
    } else {
        $error_message = "讀取文法規則失敗";
    }
}

// 獲取錯誤類型和描述
$error_types = [];
if (isset($grammar_rules['descriptions'])) {
    $error_types = $grammar_rules['descriptions'];
} else {
    $error_types = [
        'tense' => '時態使用錯誤',
        'subject_verb_agreement' => '主謂一致性問題',
        'article' => '冠詞使用錯誤',
        'plurals' => '複數形式錯誤',
        'preposition' => '介詞使用錯誤',
        'word_choice' => '詞語選擇不當',
        'spelling' => '拼寫錯誤'
    ];
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>文法規則管理 - 作文批改平台</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-blue-700">文法規則管理</h1>
                <a href="admin_ai_monitor.php" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-arrow-left mr-1"></i>返回監控面板
                </a>
            </div>
            
            <?php if (isset($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>
            
            <!-- 添加規則表單 -->
            <div class="bg-gray-50 p-6 rounded-lg shadow-sm mb-8">
                <h2 class="text-xl font-semibold mb-4">添加新規則</h2>
                <form method="post" class="space-y-4">
                    <input type="hidden" name="action" value="add_rule">
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">錯誤類型</label>
                            <select name="error_type" required class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <?php foreach ($error_types as $type => $description): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>">
                                    <?php echo htmlspecialchars($description); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">錯誤表達</label>
                            <input type="text" name="original" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                   placeholder="例如: they is">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">正確表達</label>
                            <input type="text" name="corrected" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                   placeholder="例如: they are">
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            添加規則
                        </button>
                    </div>
                </form>
				<div class="mt-6">
                    <p class="text-sm text-gray-600 mb-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        提示：良好的文法規則應該簡短明確，例如「they is → they are」，而不是完整的句子。
                        文法規則將自動應用於所有作文的批改過程中。
                    </p>
                </div>
            </div>
            
            <!-- 查看現有規則 -->
            <div class="bg-white rounded-lg shadow-sm">
                <h2 class="text-xl font-semibold mb-4">現有文法規則</h2>
                
                <div class="mb-4">
                    <select id="rule-filter" class="w-full md:w-auto px-3 py-2 border border-gray-300 rounded-md">
                        <option value="all">所有類型</option>
                        <?php foreach ($error_types as $type => $description): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>">
                            <?php echo htmlspecialchars($description); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if (empty($grammar_rules['rules']) || count(array_filter($grammar_rules['rules'], function($rules) { return !empty($rules); })) === 0): ?>
                <div class="bg-gray-50 p-4 rounded text-center">
                    尚未定義任何文法規則
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">錯誤類型</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">錯誤表達</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">正確表達</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">使用次數</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($grammar_rules['rules'] as $error_type => $rules): ?>
                                <?php 
                                $description = isset($error_types[$error_type]) ? $error_types[$error_type] : $error_type;
                                foreach ($rules as $index => $rule): 
                                ?>
                                <tr class="rule-row" data-type="<?php echo htmlspecialchars($error_type); ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($description); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($rule['original']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars(implode(', ', $rule['corrected'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo isset($rule['count']) ? $rule['count'] : 0; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <form method="post" onsubmit="return confirm('確定要刪除此規則嗎？');">
                                            <input type="hidden" name="action" value="delete_rule">
                                            <input type="hidden" name="error_type" value="<?php echo htmlspecialchars($error_type); ?>">
                                            <input type="hidden" name="rule_index" value="<?php echo $index; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash-alt"></i> 刪除
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- 規則測試區域 -->
            <div class="mt-8 bg-white p-6 rounded-lg shadow-sm">
                <h2 class="text-xl font-semibold mb-4">文法規則測試</h2>
                
                <form id="test-form" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">輸入測試文本</label>
                        <textarea id="test-text" rows="6" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                  placeholder="輸入一段包含文法錯誤的文本來測試系統的檢測功能..."></textarea>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="button" id="test-button"
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            測試文法檢測
                        </button>
                    </div>
                </form>
                
                <div id="test-results" class="mt-4 hidden">
                    <h3 class="font-medium text-lg mb-2">檢測結果</h3>
                    <div id="results-content" class="bg-gray-50 p-4 rounded-lg border"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 過濾規則
        const ruleFilter = document.getElementById('rule-filter');
        const ruleRows = document.querySelectorAll('.rule-row');
        
        ruleFilter.addEventListener('change', function() {
            const selectedType = this.value;
            
            ruleRows.forEach(row => {
                if (selectedType === 'all' || row.dataset.type === selectedType) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // 文法規則測試
        const testButton = document.getElementById('test-button');
        const testText = document.getElementById('test-text');
        const testResults = document.getElementById('test-results');
        const resultsContent = document.getElementById('results-content');
        
        testButton.addEventListener('click', function() {
            const text = testText.value.trim();
            
            if (!text) {
                alert('請輸入測試文本');
                return;
            }
            
            // 顯示載入狀態
            resultsContent.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i>檢測中...</div>';
            testResults.classList.remove('hidden');
            
            // 發送 AJAX 請求檢測文法
            fetch('test_grammar_check_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'text=' + encodeURIComponent(text)
            })
            .then(response => response.json())
            .then(data => {
                let html = '';
                
                if (data.grammar_issues && Object.keys(data.grammar_issues).length > 0) {
                    html += '<ul class="space-y-2">';
                    
                    for (const [type, issues] of Object.entries(data.grammar_issues)) {
                        html += `<li><strong>${data.descriptions[type] || type}:</strong>`;
                        html += '<ul class="list-disc list-inside pl-4 mt-1">';
                        
                        issues.forEach(issue => {
                            html += `<li>${issue}</li>`;
                        });
                        
                        html += '</ul></li>';
                    }
                    
                    html += '</ul>';
                } else {
                    html = '<p class="text-green-600">未發現任何文法問題</p>';
                }
                
                resultsContent.innerHTML = html;
            })
            .catch(error => {
                resultsContent.innerHTML = `<p class="text-red-600">發生錯誤: ${error.message}</p>`;
            });
        });
    });
    </script>
</body>
</html>