<?php
session_start();
require_once 'db_connection.php';

// 確保只有管理員可以訪問
if(!isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true){
    header("location: login.php");
    exit;
}

// 初始化訊息變數
$success_message = '';
$error_message = '';

// 處理添加規則請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_rule') {
    $error_type = $_POST['error_type'] ?? '';
    $wrong_expression = $_POST['wrong_expression'] ?? '';
    $correct_expression = $_POST['correct_expression'] ?? '';
    
    if (!empty($error_type) && !empty($wrong_expression) && !empty($correct_expression)) {
        // 定義規則文件路徑
        $models_dir = __DIR__ . '/models';
        $rules_path = $models_dir . '/grammar_rules.pkl';
        
        // 確保目錄存在
        if (!file_exists($models_dir)) {
            mkdir($models_dir, 0755, true);
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
        } else {
            // 如果文件不存在，創建新的規則結構
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
        
        // 確保規則結構完整
        if (!isset($grammar_rules['rules'])) {
            $grammar_rules['rules'] = [];
        }
        
        // 確保錯誤類型存在於規則中
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
        
        // 如果規則不存在，添加新規則
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
            $success_message = "文法規則已成功添加：'{$wrong_expression}' -> '{$correct_expression}'";
        } else {
            $error_message = "保存文法規則失敗";
        }
    } else {
        $error_message = "請填寫所有必填欄位";
    }
}

// 處理文法檢測請求
$test_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_grammar') {
    $test_text = $_POST['test_text'] ?? '';
    
    if (!empty($test_text)) {
        // 直接在 PHP 中檢測文法問題
        $models_dir = __DIR__ . '/models';
        $rules_path = $models_dir . '/grammar_rules.pkl';
        
        if (file_exists($rules_path)) {
            try {
                $data = file_get_contents($rules_path);
                $grammar_rules = unserialize($data);
                
                // 檢查文法問題
                $grammar_issues = [];
                
                if (isset($grammar_rules['rules'])) {
                    foreach ($grammar_rules['rules'] as $error_type => $rules) {
                        $found_issues = [];
                        
                        foreach ($rules as $rule) {
                            if (empty($rule['original'])) continue;
                            
                            $original = $rule['original'];
                            $pattern = '/\b' . preg_quote($original, '/') . '\b/i';
                            
                            if (preg_match($pattern, $test_text)) {
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
                
                $test_result = [
                    'issues' => $grammar_issues,
                    'descriptions' => $grammar_rules['descriptions'] ?? []
                ];
                
            } catch (Exception $e) {
                $error_message = "文法檢測失敗：" . $e->getMessage();
            }
        } else {
            $error_message = "找不到文法規則檔案";
        }
    } else {
        $error_message = "請輸入要檢測的文本";
    }
}

// 載入現有規則，以便在頁面上顯示
$grammar_rules = null;
$rules_path = __DIR__ . '/models/grammar_rules.pkl';

if (file_exists($rules_path)) {
    try {
        $data = file_get_contents($rules_path);
        $grammar_rules = unserialize($data);
    } catch (Exception $e) {
        $error_message = "讀取文法規則失敗: " . $e->getMessage();
    }
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
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-blue-700">文法規則管理</h1>
                <a href="admin_ai_monitor.php" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-arrow-left mr-1"></i>返回監控面板
                </a>
            </div>
            
            <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php endif; ?>
            
            <!-- 添加新規則表單 -->
            <div class="card mb-6">
            <div class="card-header bg-blue-50 p-4 rounded-t-lg">
                <h2 class="text-xl font-semibold">添加新規則</h2>
            </div>
            <div class="card-body p-4 bg-white rounded-b-lg border border-gray-200">
                <form method="post" id="add-rule-form">
                    <input type="hidden" name="action" value="add_rule">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label for="error_type" class="block text-sm font-medium text-gray-700 mb-1">錯誤類型</label>
                            <select name="error_type" id="error_type" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                <option value="">請選擇錯誤類型</option>
                                <option value="spelling">拼寫錯誤</option>
                                <option value="grammar">文法錯誤</option>
                                <option value="punctuation">標點符號錯誤</option>
                                <option value="word_choice">詞語選擇不當</option>
                                <option value="structure">句子結構問題</option>
                                <option value="article">冠詞使用錯誤</option>
                                <option value="tense">時態使用錯誤</option>
                                <option value="subject_verb_agreement">主謂一致性問題</option>
                                <option value="preposition">介詞使用錯誤</option>
                                <option value="plurals">複數形式錯誤</option>
                            </select>
                        </div>
                        <div>
                            <label for="wrong_expression" class="block text-sm font-medium text-gray-700 mb-1">錯誤表達</label>
                            <input type="text" name="wrong_expression" id="wrong_expression" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="例如: buyed" required>
                        </div>
                        <div>
                            <label for="correct_expression" class="block text-sm font-medium text-gray-700 mb-1">正確表達</label>
                            <input type="text" name="correct_expression" id="correct_expression" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="例如: bought" required>
                        </div>
                    </div>
                    <div class="text-right">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            添加規則
                        </button>
                    </div>
                </form>
                
                <!-- 添加常見規則的快速按鈕 -->
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <h3 class="text-sm font-medium text-gray-700 mb-2">快速添加常見規則:</h3>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="quick-rule px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs hover:bg-blue-200" 
                                data-type="tense" data-wrong="buyed" data-correct="bought">
                            buyed → bought
                        </button>
                        <button type="button" class="quick-rule px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs hover:bg-blue-200" 
                                data-type="tense" data-wrong="I plan to ate" data-correct="I plan to eat">
                            I plan to ate → I plan to eat
                        </button>
                        <button type="button" class="quick-rule px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs hover:bg-blue-200" 
                                data-type="subject_verb_agreement" data-wrong="I has" data-correct="I have">
                            I has → I have
                        </button>
                        <button type="button" class="quick-rule px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs hover:bg-blue-200" 
                                data-type="subject_verb_agreement" data-wrong="She write" data-correct="She writes">
                            She write → She writes
                        </button>
                        <button type="button" class="quick-rule px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs hover:bg-blue-200" 
                                data-type="subject_verb_agreement" data-wrong="This essay express" data-correct="This essay expresses">
                            This essay express → This essay expresses
                        </button>
                        <button type="button" class="quick-rule px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs hover:bg-blue-200" 
                                data-type="plurals" data-wrong="childrens" data-correct="children">
                            childrens → children
                        </button>
                        <button type="button" class="quick-rule px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs hover:bg-blue-200" 
                                data-type="grammar" data-wrong="My brother and me went" data-correct="My brother and I went">
                            My brother and me went → My brother and I went
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        // 快速添加規則功能
        document.addEventListener('DOMContentLoaded', function() {
            const quickRuleButtons = document.querySelectorAll('.quick-rule');
            const errorTypeInput = document.getElementById('error_type');
            const wrongExpressionInput = document.getElementById('wrong_expression');
            const correctExpressionInput = document.getElementById('correct_expression');
            const addRuleForm = document.getElementById('add-rule-form');
            
            quickRuleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const type = this.getAttribute('data-type');
                    const wrong = this.getAttribute('data-wrong');
                    const correct = this.getAttribute('data-correct');
                    
                    errorTypeInput.value = type;
                    wrongExpressionInput.value = wrong;
                    correctExpressionInput.value = correct;
                    
                    // 自動提交表單
                    addRuleForm.submit();
                });
            });
        });
        </script>
            
            <!-- 規則測試區域 -->
            <div class="card mb-6">
                <div class="card-header bg-blue-50 p-4 rounded-t-lg">
                    <h2 class="text-xl font-semibold">文法規則測試</h2>
                </div>
                <div class="card-body p-4 bg-white rounded-b-lg border border-gray-200">
                    <form method="post">
                        <input type="hidden" name="action" value="test_grammar">
                        <div class="mb-4">
                            <label for="test_text" class="block text-sm font-medium text-gray-700 mb-1">輸入測試文本</label>
                            <textarea name="test_text" id="test_text" rows="5" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="輸入要測試的文本..."><?php echo isset($_POST['test_text']) ? htmlspecialchars($_POST['test_text']) : ''; ?></textarea>
                        </div>
                        <div class="text-right">
                            <button type="submit" id="test-button" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                測試文法檢測
                            </button>
                        </div>
                    </form>
                    
                    <?php if ($test_result !== null): ?>
                    <div id="test-results" class="mt-4">
                        <h3 class="text-lg font-semibold mb-2">檢測結果</h3>
                        <div id="results-content" class="bg-yellow-50 p-4 rounded-lg">
                            <?php if (empty($test_result['issues'])): ?>
                                <div class="bg-green-50 p-4 rounded-lg">
                                    <p class="text-green-700">未發現任何文法問題</p>
                                </div>
                            <?php else: ?>
                                <div class="bg-yellow-50 p-4 rounded-lg">
                                    <p class="text-yellow-800 font-semibold mb-2">發現以下文法問題：</p>
                                    <ul class="list-disc pl-5 space-y-1">
                                        <?php foreach ($test_result['issues'] as $type => $issues): ?>
                                            <li>
                                                <span class="font-medium">
                                                    <?php echo htmlspecialchars($test_result['descriptions'][$type] ?? $type); ?>:
                                                </span>
                                                <ul class="list-disc pl-5 text-gray-700">
                                                    <?php foreach ($issues as $issue): ?>
                                                        <li><?php echo htmlspecialchars($issue); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 現有規則列表 -->
            <div class="card">
                <div class="card-header bg-blue-50 p-4 rounded-t-lg">
                    <h2 class="text-xl font-semibold">現有文法規則</h2>
                </div>
                <div class="card-body p-4 bg-white rounded-b-lg border border-gray-200">
                    <?php if ($grammar_rules === null || empty($grammar_rules['rules'])): ?>
                        <div class="bg-gray-50 p-4 rounded-lg text-center">
                            <p>尚未定義任何文法規則</p>
                        </div>
                    <?php else: ?>
                        <div class="mb-4">
                            <select id="rule-filter" class="w-full md:w-auto px-3 py-2 border border-gray-300 rounded-md">
                                <option value="all">所有類型</option>
                                <?php foreach ($grammar_rules['descriptions'] as $type => $description): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>">
                                    <?php echo htmlspecialchars($description); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="accordion" id="rulesAccordion">
                            <?php foreach ($grammar_rules['rules'] as $type => $rules): ?>
                                <?php if (!empty($rules)): ?>
                                <div class="mb-2 rule-category" data-type="<?php echo htmlspecialchars($type); ?>">
                                    <button class="w-full px-4 py-2 text-left bg-gray-100 hover:bg-gray-200 transition-colors rounded-md" 
                                            type="button" 
                                            data-bs-toggle="collapse" 
                                            data-bs-target="#collapse<?php echo htmlspecialchars($type); ?>" 
                                            aria-expanded="false">
                                        <?php echo htmlspecialchars($grammar_rules['descriptions'][$type] ?? $type); ?> 
                                        (<?php echo count($rules); ?>)
                                    </button>
                                    <div id="collapse<?php echo htmlspecialchars($type); ?>" class="collapse mt-2">
                                        <div class="bg-white rounded-md overflow-hidden">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">錯誤表達</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">正確表達</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">頻率</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    <?php foreach ($rules as $rule): ?>
                                                        <tr class="rule-row" data-type="<?php echo htmlspecialchars($type); ?>">
                                                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($rule['original']); ?></td>
                                                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($rule['corrected'][0] ?? ''); ?></td>
                                                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $rule['count'] ?? 1; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Simple accordion toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        // 過濾規則
        const ruleFilter = document.getElementById('rule-filter');
        const ruleCategories = document.querySelectorAll('.rule-category');
        
        if (ruleFilter) {
            ruleFilter.addEventListener('change', function() {
                const selectedType = this.value;
                
                ruleCategories.forEach(category => {
                    if (selectedType === 'all' || category.dataset.type === selectedType) {
                        category.style.display = '';
                    } else {
                        category.style.display = 'none';
                    }
                });
            });
        }
        
        // 折疊/展開功能
        document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(button => {
            button.addEventListener('click', function() {
                const target = document.querySelector(this.getAttribute('data-bs-target'));
                if (target) {
                    if (target.classList.contains('show')) {
                        target.classList.remove('show');
                        this.setAttribute('aria-expanded', 'false');
                    } else {
                        document.querySelectorAll('.collapse.show').forEach(el => {
                            el.classList.remove('show');
                            document.querySelector(`[data-bs-target="#${el.id}"]`).setAttribute('aria-expanded', 'false');
                        });
                        target.classList.add('show');
                        this.setAttribute('aria-expanded', 'true');
                    }
                }
            });
        });
    });
    </script>
</body>
</html>