<?php
// 創建 review_grammar_rules.php 文件

session_start();
require_once 'db_connection.php';

// 確保只有管理員可以訪問
if(!isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true){
    header("location: login.php");
    exit;
}

$success_message = '';
$error_message = '';
// 在 require_once 'db_connection.php'; 之後添加以下代碼

// 獲取當前頁碼，默認為第1頁
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($current_page < 1) $current_page = 1;

// 每頁顯示的規則數量
$rules_per_page = 30;

// 計算起始索引
$start_index = ($current_page - 1) * $rules_per_page;

// 總規則數量計數變數初始化
$total_rules = 0;
$paged_rules = [];
$page_rules_count = 0;

// 獲取當前的查詢參數，但排除page參數
$query_params = $_GET;
unset($query_params['page']);
$query_string = http_build_query($query_params);

// 構建帶有其他參數的URL
function buildPageUrl($page, $query_string) {
    return '?' . ($query_string ? $query_string . '&' : '') . 'page=' . $page;
}

// 添加判斷拼寫錯誤的函數
function isSpellingError($original, $corrected) {
    // 移除空格，以處理純單詞比較
    $origWord = trim(strtolower($original));
    $corrWord = trim(strtolower($corrected));
    
    // 常見的時態變化，這些不應該被歸類為拼寫錯誤
    $tense_pairs = [
        'buyed' => 'bought',
        'goed' => 'went',
        'writed' => 'wrote',
        'taked' => 'took',
        'eated' => 'ate',
        'teached' => 'taught',
        'readed' => 'read',
        'thinked' => 'thought'
    ];
    
    // 檢查是否為時態變化
    if (isset($tense_pairs[$origWord]) && $tense_pairs[$origWord] === $corrWord) {
        return false;
    }
    
    // 如果是單個單詞，且長度相近（允許1-2個字母的差異）
    if (!strpos($origWord, ' ') && !strpos($corrWord, ' ') && 
        abs(strlen($origWord) - strlen($corrWord)) <= 2) {
        
        // 檢查是否只是字母順序或拼寫錯誤
        // 計算萊文斯坦距離 (編輯距離)
        $distance = levenshtein($origWord, $corrWord);
        
        // 排除規則變化形式
        if (substr($origWord, -2) === 'ed' && substr($corrWord, -2) !== 'ed') {
            return false;
        }
        
        if (substr($origWord, -3) === 'ing' || substr($corrWord, -3) === 'ing') {
            return false;
        }
        
        // 如果編輯距離較小（相對於單詞長度），很可能是拼寫錯誤
        return $distance <= 2 && $distance < strlen($origWord) * 0.4;
    }
    
    return false;
}
// 高亮搜尋關鍵字的函數
function highlightSearchTerm($text, $search) {
    if (empty($search)) {
        return htmlspecialchars($text);
    }
    
    $pattern = '/' . preg_quote($search, '/') . '/i';
    return preg_replace($pattern, '<span class="highlighted">$0</span>', htmlspecialchars($text));
}
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
// 搜索和過濾功能
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
// 假設 $grammar_rules 已經載入，添加以下代碼：

// 在加載文法規則後，計算總規則數的代碼
$total_rules = 0;
if (isset($grammar_rules['rules']) && is_array($grammar_rules['rules'])) {
    foreach ($grammar_rules['rules'] as $type => $rules) {
        if (is_array($rules)) {
            $total_rules += count($rules);
        }
    }
}

// 確保 $total_rules 至少為 0
$total_rules = max(0, $total_rules);

// 計算總頁數
$total_pages = ceil($total_rules / $rules_per_page);

// 確保當前頁不超過總頁數
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
}

// 首先篩選符合搜尋條件的規則
$filtered_rules = [];
$filtered_count = 0;

foreach ($grammar_rules['rules'] as $type => $rules) {
    // 如果指定了錯誤類型過濾條件，且不是 'all'，則跳過不匹配的類型
    if ($filter_type !== 'all' && $filter_type !== $type) {
        continue;
    }
    
    if (!isset($filtered_rules[$type])) {
        $filtered_rules[$type] = [];
    }
    
    foreach ($rules as $index => $rule) {
        $original = $rule['original'];
        $corrected = isset($rule['corrected'][0]) ? $rule['corrected'][0] : '';
        
        // 如果有搜尋關鍵字，則檢查是否匹配錯誤表達或正確表達
        if (!empty($search_query)) {
            $match_original = stripos($original, $search_query) !== false;
            $match_corrected = stripos($corrected, $search_query) !== false;
            
            if (!$match_original && !$match_corrected) {
                continue; // 不匹配搜尋關鍵字，跳過
            }
        }
        
        // 符合條件的規則，加入到篩選結果中
        $filtered_rules[$type][$index] = $rule;
        $filtered_count++;
    }
}

// 更新總規則數為篩選後的數量
$total_rules = $filtered_count;

// 計算總頁數
$total_pages = ceil($total_rules / $rules_per_page);

// 確保當前頁不超過總頁數
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
}

// 準備當前頁的規則
$current_index = 0;
$paged_rules = []; // 儲存當前頁要顯示的規則
$page_rules_count = 0;

foreach ($filtered_rules as $type => $rules) {
    if (!isset($paged_rules[$type])) {
        $paged_rules[$type] = [];
    }
    
    foreach ($rules as $index => $rule) {
        if ($current_index >= $start_index && $current_index < ($start_index + $rules_per_page)) {
            // 這條規則屬於當前頁，加入到分頁規則中
            $paged_rules[$type][$index] = $rule;
            $page_rules_count++;
        }
        
        $current_index++;
        
        // 如果已經收集了足夠的規則，就停止
        if ($page_rules_count >= $rules_per_page) {
            break 2; // 跳出兩層循環
        }
    }
}
// 處理規則刪除或標記
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action']) && $_POST['action'] == 'remove_rules') {
        // 檢查是否有規則被選中移除
        if (isset($_POST['rules_to_remove']) && is_array($_POST['rules_to_remove'])) {
            $rules_to_remove = $_POST['rules_to_remove'];
            $removed_count = 0;
            
            // 備份原始規則
            $backup_path = __DIR__ . '/models/grammar_rules_backup_' . date('Ymd_His') . '.pkl';
            if (copy($grammar_rules_path, $backup_path)) {
                // 移除選中的規則
                foreach ($rules_to_remove as $rule_info) {
                    list($type, $index) = explode('|', $rule_info);
                    
                    if (isset($grammar_rules['rules'][$type][$index])) {
                        // 移除規則
                        unset($grammar_rules['rules'][$type][$index]);
                        $removed_count++;
                    }
                }
                
                // 重建索引
                foreach ($grammar_rules['rules'] as $type => &$rules) {
                    $rules = array_values($rules);
                }
                
                // 保存更新後的規則
                if (file_put_contents($grammar_rules_path, serialize($grammar_rules)) !== false) {
                    $success_message = "成功移除 {$removed_count} 條規則，並保存了規則文件。";
                } else {
                    $error_message = "保存規則文件失敗";
                }
            } else {
                $error_message = "創建備份文件失敗";
            }
        } else {
            $error_message = "沒有選擇要移除的規則";
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
    <title>文法規則審核 - 作文批改平台</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .highlighted {
            background-color: #ffffcc;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-blue-700">文法規則審核</h1>
                <a href="admin_ai_monitor.php" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-arrow-left mr-1"></i>返回監控面板
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
            
            <!-- 搜索和過濾工具 -->
            <div class="mb-6 bg-gray-50 p-4 rounded-lg">
                <h2 class="text-lg font-semibold mb-3">搜索和過濾</h2>
                <form method="get" class="space-y-4 md:space-y-0 md:flex md:space-x-4">
                    <div class="flex-1">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">搜索關鍵詞</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md"
                               placeholder="搜索錯誤表達或正確表達...">
                    </div>
                    
                    <div class="md:w-1/4">
                        <label for="type" class="block text-sm font-medium text-gray-700 mb-1">錯誤類型</label>
                        <select id="type" name="type" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="all" <?php echo $filter_type == 'all' ? 'selected' : ''; ?>>所有類型</option>
                            <?php foreach ($error_types as $type => $description): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" 
                                    <?php echo $filter_type == $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($description); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="self-end">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <i class="fas fa-search mr-1"></i>搜索
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- 規則列表和審核表單 -->
			
            <form method="post" id="rules-form">
                <input type="hidden" name="action" value="remove_rules">
                
                <div class="mb-4 flex justify-between items-center">
                    <h2 class="text-xl font-semibold">文法規則列表</h2>
                    <div>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                            <i class="fas fa-trash-alt mr-1"></i>移除選擇的規則
                        </button>
                        <button type="button" id="select-all" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 ml-2">
                            全選
                        </button>
                        <button type="button" id="deselect-all" class="px-4 py-2 bg-gray-400 text-white rounded-md hover:bg-gray-500 ml-2">
                            取消全選
                        </button>
                    </div>
                </div>
                
                <div class="overflow-x-auto bg-white rounded-lg shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" id="check-all" class="form-checkbox h-5 w-5 text-blue-600">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    錯誤類型
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    錯誤表達
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    正確表達
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    使用次數
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    操作
                                </th>
                            </tr>
                        </thead>
                        
						<tbody>
							<?php
							// 使用分頁後的規則集來生成表格內容
							if (empty($paged_rules) || $page_rules_count == 0) {
								echo '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">目前沒有規則或該頁沒有規則</td></tr>';
							} else {
								foreach ($paged_rules as $type => $rules) {
									foreach ($rules as $index => $rule) {
										// 提取規則資訊
										$original = $rule['original'];
										$corrected = isset($rule['corrected'][0]) ? $rule['corrected'][0] : '';
										$count = isset($rule['count']) ? $rule['count'] : 0;
										$type_desc = isset($grammar_rules['descriptions'][$type]) ? $grammar_rules['descriptions'][$type] : $type;
										
										// 檢查是否為純拼寫錯誤
										$isRealSpellingError = ($type === 'spelling' && isSpellingError($original, $corrected));
										$typeDisplay = htmlspecialchars($type_desc);
										if ($isRealSpellingError) {
											$typeDisplay = '<span class="bg-green-100 text-green-800 px-2 py-0.5 rounded text-xs">純拼寫</span> ' . $typeDisplay;
										}
										
										
										
										// 生成表格行
										echo '<tr class="hover:bg-gray-50">';
										echo '<td class="px-4 py-3">';
										echo '<input type="checkbox" name="rules_to_remove[]" value="' . $type . '|' . $index . '" class="form-checkbox h-5 w-5 text-blue-600 rule-checkbox">';
										echo '</td>';
										echo '<td class="px-4 py-3 max-w-[120px]">' . $typeDisplay . '</td>';
										echo '<td class="px-4 py-3 max-w-[150px] break-words">' . highlightSearchTerm($original, $search_query) . '</td>';
										echo '<td class="px-4 py-3 max-w-[150px] break-words">' . highlightSearchTerm($corrected, $search_query) . '</td>';
										echo '<td class="px-4 py-3 text-center">' . $count . '</td>';
										echo '<td class="px-4 py-3">';
										echo '<div class="flex flex-row space-x-2 items-center whitespace-nowrap">';
										echo '<a href="context_rules_editor.php?word=' . urlencode($original) . '&type=' . urlencode($type) . '" class="text-blue-600 hover:text-blue-800 inline-flex items-center text-sm">';
										echo '<i class="fas fa-edit mr-1"></i> 編輯上下文';
										echo '</a>';
										echo '<button type="button" class="remove-single text-red-600 hover:text-red-900 inline-flex items-center text-sm" data-type="' . $type . '" data-index="' . $index . '">';
										echo '<i class="fas fa-trash-alt mr-1"></i> 移除';
										echo '</button>';
										echo '</div>';
										echo '</td>';
										echo '</tr>';
									}
								}
							}
							?>
						</tbody>
                    </table>
					<!-- 在表格後面添加分頁導航 -->
					
					<div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6 mt-4">
						<div class="flex-1 flex items-center justify-between">
							<div>
								<p class="text-sm text-gray-700">
									顯示 <span class="font-medium"><?php echo $start_index + 1; ?></span> 至 
									<span class="font-medium"><?php echo min($start_index + $page_rules_count, $total_rules); ?></span> 筆，
									共 <span class="font-medium"><?php echo $total_rules; ?></span> 筆規則
								</p>
							</div>
							<div>
								<nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
									<?php if ($current_page > 1): ?>
									<!-- 上一頁按鈕 -->
									
									<a href="<?php echo buildPageUrl($current_page - 1, $query_string); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
										<span class="sr-only">上一頁</span>
										<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
											<path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
										</svg>
									</a>
									<?php endif; ?>
									
									<!-- 頁碼 -->
									<?php 
									// 決定要顯示哪些頁碼
									$page_range = 2; // 當前頁左右各顯示多少頁
									$start_page = max(1, $current_page - $page_range);
									$end_page = min($total_pages, $current_page + $page_range);
									
									// 如果需要顯示第一頁
									if ($start_page > 1) {
										echo '<a href="' . buildPageUrl(1, $query_string) . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
										
										if ($start_page > 2) {
											echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
										}
									}
									
									// 顯示頁碼範圍
									for ($i = $start_page; $i <= $end_page; $i++) {
										$is_current = ($i == $current_page);
										$class = $is_current 
											? 'z-10 bg-blue-50 border-blue-500 text-blue-600' 
											: 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50';
										
										echo '<a href="' . buildPageUrl($i, $query_string) . '" class="relative inline-flex items-center px-4 py-2 border ' . $class . ' text-sm font-medium">' . $i . '</a>';
									}
									
									// 如果需要顯示最後一頁
									if ($end_page < $total_pages) {
										if ($end_page < $total_pages - 1) {
											echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
										}
										
										echo '<a href="' . buildPageUrl($total_pages, $query_string) . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $total_pages . '</a>';
									}
									?>
									
									<?php if ($current_page < $total_pages): ?>
									<!-- 下一頁按鈕 -->
									<a href="<?php echo buildPageUrl($current_page + 1, $query_string); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
										<span class="sr-only">下一頁</span>
										<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
											<path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
										</svg>
									</a>
									<?php endif; ?>
								</nav>
							</div>
						</div>
					</div>
                </div>
                
                <div class="mt-4 text-right">
                    <!-- 在表單中添加這個隱藏字段 -->
			<input type="hidden" name="page" value="<?php echo $current_page; ?>">
                </div>
            </form>
            
            <!-- 查看文法規則統計 -->
            <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                <h2 class="text-lg font-semibold mb-3">文法規則統計</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="py-2 px-4 border">錯誤類型</th>
                                <th class="py-2 px-4 border">規則數量</th>
                                <th class="py-2 px-4 border">百分比</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stats_total_rules = 0;
							$type_counts = [];
							
							// 如果是在搜尋或過濾狀態，使用篩選後的規則進行統計
							if (!empty($search_query) || $filter_type !== 'all') {
								foreach ($filtered_rules as $type => $rules) {
									$type_counts[$type] = count($rules);
									$stats_total_rules += count($rules);
								}
							} else {
								// 否則使用所有規則進行統計
								if (isset($grammar_rules['rules'])) {
									foreach ($grammar_rules['rules'] as $type => $rules) {
										$type_counts[$type] = count($rules);
										$stats_total_rules += count($rules);
									}
								}
							}
                                
                                // 按數量排序
                                arsort($type_counts);
                                
                                foreach ($type_counts as $type => $count) {
                                    $percent = $total_rules > 0 ? round(($count / $total_rules) * 100, 1) : 0;
                                    $type_desc = $error_types[$type] ?? $type;
                                    
                                    echo '<tr class="hover:bg-gray-50">';
                                    echo '<td class="py-2 px-4 border">' . htmlspecialchars($type_desc) . '</td>';
                                    echo '<td class="py-2 px-4 border">' . $count . '</td>';
                                    echo '<td class="py-2 px-4 border">' . $percent . '%</td>';
                                    echo '</tr>';
                                }
                            
                            ?>
                        </tbody>
                        <tfoot class="bg-gray-100">
							<tr>
								<th class="py-2 px-4 border">總計</th>
								<th class="py-2 px-4 border"><?php echo $stats_total_rules; ?></th>
								<th class="py-2 px-4 border">100%</th>
							</tr>
						</tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 全選/取消全選功能
        const checkAll = document.getElementById('check-all');
        const ruleCheckboxes = document.querySelectorAll('.rule-checkbox');
        
        checkAll.addEventListener('change', function() {
            ruleCheckboxes.forEach(checkbox => {
                checkbox.checked = checkAll.checked;
            });
        });
        
        // 全選和取消全選按鈕
        document.getElementById('select-all').addEventListener('click', function() {
            checkAll.checked = true;
            ruleCheckboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
        });
        
        document.getElementById('deselect-all').addEventListener('click', function() {
            checkAll.checked = false;
            ruleCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
        });
        
        // 移除單個規則按鈕
        document.querySelectorAll('.remove-single').forEach(btn => {
            btn.addEventListener('click', function() {
                const type = this.dataset.type;
                const index = this.dataset.index;
                
                // 找到對應的複選框並勾選
                const checkbox = document.querySelector(`input[value="${type}|${index}"]`);
                if (checkbox) {
                    checkbox.checked = true;
                    
                    // 提交表單
                    if (confirm('確定要移除這條規則?')) {
                        document.getElementById('rules-form').submit();
                    } else {
                        checkbox.checked = false;
                    }
                }
            });
        });
        
        // 表單提交前確認
        document.getElementById('rules-form').addEventListener('submit', function(e) {
            const selectedCount = document.querySelectorAll('input[name="rules_to_remove[]"]:checked').length;
            
            if (selectedCount === 0) {
                e.preventDefault();
                alert('請至少選擇一條規則進行移除');
                return false;
            }
            
            if (!confirm(`確定要移除選中的 ${selectedCount} 條規則?`)) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    });
    </script>
</body>
</html>