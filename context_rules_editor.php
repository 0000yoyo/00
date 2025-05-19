<?php
// context_rules_editor.php - 文法規則上下文條件編輯器

session_start();
require_once 'db_connection.php';

// 確保只有管理員可以訪問
if(!isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true){
    header("location: login.php");
    exit;
}

$success_message = '';
$error_message = '';

// 載入文法規則
$grammar_rules_path = __DIR__ . '/models/grammar_rules.pkl';
$grammar_rules = [];

if (file_exists($grammar_rules_path)) {
    try {
        $data = file_get_contents($grammar_rules_path);
        $grammar_rules = unserialize($data);
    } catch (Exception $e) {
        $error_message = "讀取文法規則失敗: " . $e->getMessage();
    }
}

// 處理上下文條件更新
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_context') {
    $word = isset($_POST['word']) ? $_POST['word'] : '';
    $type = isset($_POST['type']) ? $_POST['type'] : '';
    $context_data = isset($_POST['context']) ? $_POST['context'] : [];
    
    if (!empty($word) && !empty($type) && !empty($context_data)) {
        // 先備份規則文件
        $backup_path = __DIR__ . '/models/grammar_rules_backup_' . date('Ymd_His') . '.pkl';
        if (copy($grammar_rules_path, $backup_path)) {
            // 尋找並更新對應規則的上下文條件
            $rule_updated = false;
            
            if (isset($grammar_rules['rules'][$type])) {
                foreach ($grammar_rules['rules'][$type] as &$rule) {
                    if ($rule['original'] === $word) {
                        // 更新上下文條件
                        $rule['context_enabled'] = true;
                        $rule['context_rules'] = $context_data;
                        $rule_updated = true;
                    }
                }
            }
            
            // 保存更新後的規則
            if ($rule_updated) {
                if (file_put_contents($grammar_rules_path, serialize($grammar_rules)) !== false) {
                    $success_message = "上下文規則已成功更新";
                } else {
                    $error_message = "保存文法規則失敗";
                }
            } else {
                $error_message = "找不到指定的規則";
            }
        } else {
            $error_message = "創建備份失敗";
        }
    } else {
        $error_message = "缺少必要參數";
    }
}

// 獲取要編輯的單詞和類型
$edit_word = isset($_GET['word']) ? $_GET['word'] : '';
$edit_type = isset($_GET['type']) ? $_GET['type'] : '';
$current_rule = null;

// 尋找當前規則
if (!empty($edit_word) && !empty($edit_type) && isset($grammar_rules['rules'][$edit_type])) {
    foreach ($grammar_rules['rules'][$edit_type] as $rule) {
        if ($rule['original'] === $edit_word) {
            $current_rule = $rule;
            break;
        }
    }
}

// 獲取錯誤類型描述
$error_types = $grammar_rules['descriptions'] ?? [
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
];
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>文法規則上下文編輯 - 作文批改平台</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-blue-700">文法規則上下文編輯</h1>
                <a href="review_grammar_rules.php" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-arrow-left mr-1"></i>返回規則審核
                </a>
            </div>
            
            <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($current_rule): ?>
            <div class="mb-6 bg-blue-50 p-4 rounded-lg">
                <h2 class="text-lg font-medium text-blue-800 mb-2">
                    編輯規則: "<?php echo htmlspecialchars($edit_word); ?>" 
                    (<?php echo htmlspecialchars($error_types[$edit_type] ?? $edit_type); ?>)
                </h2>
                <p class="text-gray-600 mb-2">
                    原始表達: <span class="font-medium"><?php echo htmlspecialchars($current_rule['original']); ?></span>
                </p>
                <p class="text-gray-600">
                    建議修改為: <span class="font-medium"><?php echo htmlspecialchars($current_rule['corrected'][0] ?? ''); ?></span>
                </p>
            </div>
            
            <form method="post" class="space-y-6">
                <input type="hidden" name="action" value="update_context">
                <input type="hidden" name="word" value="<?php echo htmlspecialchars($edit_word); ?>">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($edit_type); ?>">
                
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="font-medium text-gray-700 mb-4">上下文規則設置</h3>
                    
                    <!-- 啟用上下文檢查 -->
                    <div class="mb-4">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="context[enabled]" value="1" class="form-checkbox h-5 w-5 text-blue-600"
                                   <?php echo (isset($current_rule['context_enabled']) && $current_rule['context_enabled']) ? 'checked' : ''; ?>>
                            <span class="ml-2 text-gray-700">啟用上下文檢查</span>
                        </label>
                        <p class="text-sm text-gray-500 mt-1">啟用後，系統將基於上下文來判斷該表達是否確實錯誤。</p>
                    </div>
                    
                    <!-- 上下文規則 -->
                    <div class="space-y-4">
                        <!-- 不作為特定詞組的一部分時才標記為錯誤 -->
                        <div class="p-3 border border-gray-200 rounded-lg">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="context[not_in_phrase]" value="1" class="form-checkbox h-5 w-5 text-blue-600"
                                       <?php echo (isset($current_rule['context_rules']['not_in_phrase'])) ? 'checked' : ''; ?>>
                                <span class="ml-2 text-gray-700">當不是特定詞組的一部分時才標記為錯誤</span>
                            </label>
                            
                            <div class="mt-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">排除的詞組(不會被標記為錯誤)</label>
                                <textarea name="context[phrases_to_exclude]" rows="2" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                        placeholder="每行一個詞組，例如: a lot of"><?php echo htmlspecialchars($current_rule['context_rules']['phrases_to_exclude'] ?? ''); ?></textarea>
                                <p class="text-xs text-gray-500 mt-1">如果單詞作為這些詞組的一部分，則不會被標記為錯誤。</p>
                            </div>
                        </div>
                        
                        <!-- 前置詞限制 -->
                        <div class="p-3 border border-gray-200 rounded-lg">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="context[check_preceding]" value="1" class="form-checkbox h-5 w-5 text-blue-600"
                                       <?php echo (isset($current_rule['context_rules']['check_preceding'])) ? 'checked' : ''; ?>>
                                <span class="ml-2 text-gray-700">檢查前置詞</span>
                            </label>
                            
                            <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">不應該在這些詞之後</label>
                                    <textarea name="context[not_after_words]" rows="2" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                            placeholder="每行一個詞"><?php echo htmlspecialchars($current_rule['context_rules']['not_after_words'] ?? ''); ?></textarea>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">必須在這些詞之後</label>
                                    <textarea name="context[must_after_words]" rows="2" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                            placeholder="每行一個詞"><?php echo htmlspecialchars($current_rule['context_rules']['must_after_words'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 後置詞限制 -->
                        <div class="p-3 border border-gray-200 rounded-lg">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="context[check_following]" value="1" class="form-checkbox h-5 w-5 text-blue-600"
                                       <?php echo (isset($current_rule['context_rules']['check_following'])) ? 'checked' : ''; ?>>
                                <span class="ml-2 text-gray-700">檢查後置詞</span>
                            </label>
                            
                            <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">不應該在這些詞之前</label>
                                    <textarea name="context[not_before_words]" rows="2" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                            placeholder="每行一個詞"><?php echo htmlspecialchars($current_rule['context_rules']['not_before_words'] ?? ''); ?></textarea>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">必須在這些詞之前</label>
                                    <textarea name="context[must_before_words]" rows="2" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                            placeholder="每行一個詞"><?php echo htmlspecialchars($current_rule['context_rules']['must_before_words'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 針對常見詞的特殊規則 -->
                        <div class="p-3 border border-gray-200 rounded-lg">
                            <h4 class="font-medium text-gray-700 mb-2">常見詞特殊規則</h4>
                            
                            <?php 
                            $special_rules = [
                                'when' => [
                                    'description' => '用於時間連接詞時通常是正確的',
                                    'check' => isset($current_rule['context_rules']['special_rules']['when'])
                                ],
                                'if' => [
                                    'description' => '用於條件句時通常是正確的',
                                    'check' => isset($current_rule['context_rules']['special_rules']['if'])
                                ],
                                'first' => [
                                    'description' => '用作序數詞時通常是正確的',
                                    'check' => isset($current_rule['context_rules']['special_rules']['first'])
                                ],
                                'lot' => [
                                    'description' => '作為"a lot of"的一部分時通常是正確的',
                                    'check' => isset($current_rule['context_rules']['special_rules']['lot'])
                                ],
                                'university' => [
                                    'description' => '除非前面有many/several等複數限定詞，否則通常不需要變為複數',
                                    'check' => isset($current_rule['context_rules']['special_rules']['university'])
                                ]
                            ];
                            
                            if (in_array(strtolower($edit_word), array_keys($special_rules))) {
                                $rule_info = $special_rules[strtolower($edit_word)];
                            ?>
                            <div class="mb-2">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="context[special_rules][<?php echo strtolower($edit_word); ?>]" value="1" class="form-checkbox h-5 w-5 text-blue-600"
                                           <?php echo $rule_info['check'] ? 'checked' : ''; ?>>
                                    <span class="ml-2 text-gray-700">應用"<?php echo htmlspecialchars($edit_word); ?>"的特殊規則</span>
                                </label>
                                <p class="text-sm text-gray-500 ml-7"><?php echo htmlspecialchars($rule_info['description']); ?></p>
                            </div>
                            <?php } else { ?>
                            <p class="text-sm text-gray-500">沒有針對"<?php echo htmlspecialchars($edit_word); ?>"的特殊規則。</p>
                            <?php } ?>
                        </div>
                        
                        <!-- 其他設置 -->
                        <div class="p-3 border border-gray-200 rounded-lg">
                            <label class="block text-sm font-medium text-gray-700 mb-1">其他上下文註釋</label>
                            <textarea name="context[notes]" rows="3" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                    placeholder="添加任何其他關於此規則的上下文信息..."><?php echo htmlspecialchars($current_rule['context_rules']['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4">
                    <a href="review_grammar_rules.php" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        取消
                    </a>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        保存上下文規則
                    </button>
                </div>
            </form>
            <?php else: ?>
            <div class="bg-yellow-50 p-4 rounded-lg">
                <p class="text-yellow-700">找不到指定的規則。請從規則審核頁面選擇一個規則進行編輯。</p>
                <a href="review_grammar_rules.php" class="text-blue-600 hover:underline mt-2 inline-block">
                    返回規則審核頁面
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>