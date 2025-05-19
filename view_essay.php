<?php
session_start();
require_once 'db_connection.php';

// 確保用戶已登入
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

$essay_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 檢查作文是否存在且屬於當前用戶（學生）或是教師的作文
$stmt = $conn->prepare("
    SELECT e.*, u.username AS student_name, t.username AS teacher_name 
    FROM essays e 
    JOIN users u ON e.user_id = u.id 
    LEFT JOIN users t ON e.teacher_id = t.id 
    WHERE e.id = ? AND (e.user_id = ? OR (? = 'teacher' AND e.teacher_review = 1))
");
$stmt->execute([$essay_id, $_SESSION['id'], $_SESSION['user_type']]);
$essay = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$essay) {
    // 作文不存在或不屬於當前用戶
    header("location: " . ($_SESSION['user_type'] == 'student' ? 'my_essays.php' : 'teacher_dashboard.php'));
    exit;
}

// 獲取作文類型中文名稱
$categories = [
    'narrative' => '敘事文',
    'descriptive' => '描述文',
    'argumentative' => '論說文',
    'expository' => '說明文',
    'compare_contrast' => '比較對比文',
    'persuasive' => '議論文',
    'reflective' => '反思文',
    'critical_analysis' => '批評性分析文'
];

$category_name = isset($categories[$essay['category']]) ? $categories[$essay['category']] : $essay['category'];
// 在文件頂部的PHP代碼部分添加此函數
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
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>查看作文 - 作文批改平台</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .grammar-issue {
            color: #e53e3e;
            margin-left: 20px;
            margin-bottom: 5px;
            list-style-type: none;
        }
        
        .ai-feedback {
            line-height: 1.6;
        }
        
        .ai-feedback strong {
            display: block;
            margin-top: 12px;
            margin-bottom: 6px;
        }
        
        .highlight-text {
            background-color: #FFEB3B;
            padding: 2px;
        }
        
        .correction-text {
            background-color: #81C784;
            padding: 2px;
        }
		/* 文法分析下拉選單樣式 */
		.grammar-issues-list {
			max-height: 300px;
			overflow-y: auto;
		}
		
		.toggle-icon {
			transition: transform 0.3s ease;
		}
		
		/* 動畫效果 */
		.grammar-issues-list.hidden + .toggle-icon {
			transform: rotate(180deg);
		}
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md overflow-hidden">
        <!-- 頂部導航欄 -->
        <div class="bg-orange-400 px-6 py-4 text-white flex justify-between items-center">
            <h1 class="text-xl font-bold">作文批改詳情</h1>
            <a href="<?php echo $_SESSION['user_type'] == 'student' ? 'my_essays.php' : 'teacher_dashboard.php'; ?>" class="text-white hover:text-blue-200">
                <i class="fas fa-arrow-left mr-1"></i>返回
            </a>
        </div>

        <div class="p-6">
            <!-- 作文標題和基本信息 -->
            <div class="mb-6">
                <div class="flex justify-between items-center mb-2 flex-wrap">
                    <h2 class="text-2xl font-semibold"><?php echo htmlspecialchars($essay['title']); ?></h2>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm mt-2 sm:mt-0">
                        <?php echo $category_name; ?>
                    </span>
                </div>
                <div class="text-gray-600 text-sm mb-4 flex flex-wrap gap-2">
                    <span>
                        <i class="fas fa-user mr-1"></i>
                        <?php echo htmlspecialchars($essay['student_name']); ?>
                    </span>
                    <span class="hidden sm:inline">|</span>
                    <span>
                        <i class="fas fa-calendar-alt mr-1"></i>
                        <?php 
                            $created_time = new DateTime($essay['created_at']);
                            echo $created_time->format('Y/m/d H:i'); 
                        ?>
                    </span>
                    <?php if ($essay['status'] == 'graded'): ?>
                    <span class="hidden sm:inline">|</span>
                    <span>
                        <i class="fas fa-check-circle mr-1"></i>
                        <?php 
                            $graded_time = new DateTime($essay['feedback_time'] ?? $essay['created_at']);
                            echo $graded_time->format('Y/m/d H:i'); 
                        ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if($essay['teacher_review'] && $essay['status'] == 'pending'): ?>
            <!-- 等待批改提示 -->
            <div class="bg-yellow-50 p-4 rounded-lg text-center mb-6 border border-yellow-200">
                <p class="text-yellow-600 flex items-center justify-center">
                    <i class="fas fa-hourglass-half mr-2 animate-pulse"></i>
                    您的作文正在等待教師批改，請耐心等待。
                </p>
            </div>
            <?php endif; ?>
            <!-- 作文內容 -->
			<div class="bg-gray-50 p-4 rounded-lg border border-gray-200 prose max-w-none mb-6">
				<h3 class="text-lg font-semibold mb-2 text-gray-700 border-b pb-1">作文內容</h3>
				<?php
				// 提取所有AI檢測到的錯誤表達
				$errorExpressions = [];
				if (!empty($essay['ai_feedback'])) {
					// 查找所有格式為 - "錯誤表達" 的部分
					preg_match_all("/- ['\"](.+?)['\"](?:\s+(?:可能應為|應改為|應該為)\s+['\"](.+?)['\"])?/", 
						$essay['ai_feedback'], $matches, PREG_SET_ORDER);
					
					foreach ($matches as $match) {
						$errorExpressions[] = preg_quote($match[1], '/');
					}
					
					// 如果有教師補充的錯誤
					if (!empty($essay['teacher_grammar_feedback'])) {
						$grammar_feedback = json_decode($essay['teacher_grammar_feedback'], true);
						if ($grammar_feedback) {
							$missed_issues = array_filter($grammar_feedback, function($item) {
								return $item['type'] == 'missed';
							});
							
							foreach ($missed_issues as $issue) {
								if (!empty($issue['wrong'])) {
									$errorExpressions[] = preg_quote($issue['wrong'], '/');
								}
							}
						}
					}
				}
				
				// 新的改進方法：使用DOM解析處理
				$content = htmlspecialchars($essay['content']);
				$lines = explode("\n", $content);
				$markedContent = '';
				
				foreach ($lines as $line) {
					if (!empty($errorExpressions)) {
						// 創建一個正則表達式匹配所有錯誤表達
						$pattern = '/(' . implode('|', $errorExpressions) . ')/i';
						$line = preg_replace_callback($pattern, function($matches) {
							return '<span class="error-highlight">' . $matches[0] . '</span>';
						}, $line);
					}
					$markedContent .= $line . "\n";
				}
				
				// 輸出帶有標記的內容
				echo nl2br($markedContent);
				?>
			</div>
			
			<!-- 添加錯誤高亮樣式 -->
			<style>
				.error-highlight {
					position: relative;
					display: inline-block;
				}
				
				.error-highlight::after {
					content: "";
					position: absolute;
					left: 0;
					bottom: -2px;
					width: 100%;
					height: 2px;
					background-color: #e53e3e;
					background-image: linear-gradient(to right, #e53e3e 70%, transparent 30%);
					background-position: bottom;
					background-size: 8px 2px;
					background-repeat: repeat-x;
				}
			</style>
            
            <!-- 評分信息卡片 -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <?php if($essay['teacher_review'] && $essay['status'] == 'graded'): ?>
                <!-- 教師評分卡片 -->
                <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-chalkboard-teacher text-yellow-500 text-xl mr-2"></i>
                        <h3 class="text-lg font-medium text-yellow-800">教師評分</h3>
                    </div>
                    <div class="flex items-center justify-center my-3">
                        <span class="text-3xl font-bold text-yellow-700"><?php echo $essay['score']; ?></span>
                        <span class="text-lg text-gray-600 ml-1">/100</span>
                    </div>
                    <?php if($essay['teacher_name']): ?>
                    <div class="text-center text-sm text-gray-600 mt-2">
                        批改教師: <?php echo htmlspecialchars($essay['teacher_name']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if($essay['ai_score']): ?>
                <!-- AI評分卡片 -->
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-robot text-blue-500 text-xl mr-2"></i>
                        <h3 class="text-lg font-medium text-blue-800">AI評分</h3>
                    </div>
                    <div class="flex items-center justify-center my-3">
                        <span class="text-3xl font-bold text-blue-700"><?php echo $essay['ai_score']; ?></span>
                        <span class="text-lg text-gray-600 ml-1">/100</span>
                    </div>
                    <?php if(isset($essay['model_version'])): ?>
                    <div class="text-center text-sm text-gray-600 mt-2">
                        AI模型版本: <?php echo htmlspecialchars($essay['model_version']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if($essay['teacher_review'] && $essay['status'] == 'graded'): ?>
            <!-- 教師評語 -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold mb-3 text-yellow-800 border-b pb-2">
                    <i class="fas fa-comment-alt mr-2"></i>教師評語
                </h3>
                <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                    <?php echo nl2br(htmlspecialchars($essay['teacher_feedback'])); ?>
                </div>
            </div>
            
            <?php if (!empty($essay['teacher_grammar_feedback'])): ?>
            <!-- 教師對AI文法分析的補充 -->
            <div class="mt-6 mb-8">
                <h3 class="text-lg font-semibold mb-3 text-blue-800 border-b pb-2">
                    <i class="fas fa-spell-check mr-2"></i>教師對AI文法分析的補充
                </h3>
                
                <div class="bg-yellow-50 p-4 rounded-lg mb-4 text-sm border border-yellow-200">
                    <p class="flex items-center">
                        <i class="fas fa-info-circle text-yellow-700 mr-2"></i>
                        <span>以下是您作文中的所有文法問題，包括AI已識別的問題和教師額外發現的問題。</span>
                    </p>
                </div>
                
                <!-- 解析教師文法反饋 -->
                <?php 
                $grammar_feedback = json_decode($essay['teacher_grammar_feedback'], true);
                if ($grammar_feedback): 
                ?>
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <!-- 顯示AI未識別的錯誤 -->
                        <?php
                        $missed_issues = array_filter($grammar_feedback, function($item) {
                            return $item['type'] == 'missed';
                        });
                        
                        if (!empty($missed_issues)):
                        ?>
                            <div class="mb-4">
                                <h4 class="font-medium text-blue-800 mb-2">
                                    <i class="fas fa-exclamation-circle mr-1"></i>教師發現的其他文法錯誤:
                                </h4>
                                <ul class="list-disc pl-5 space-y-1">
                                    <?php foreach ($missed_issues as $issue): ?>
                                        <li>
                                            <span class="text-red-600">"<?php echo htmlspecialchars($issue['wrong']); ?>"</span> 
                                            應改為 
                                            <span class="text-green-600">"<?php echo htmlspecialchars($issue['correct']); ?>"</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <!-- 顯示AI錯誤識別的問題 -->
                        <?php
                        $false_positives = array_filter($grammar_feedback, function($item) {
                            return $item['type'] == 'false_positive';
                        });
                        
                        if (!empty($false_positives)):
                        ?>
                            <div class="mb-4">
                                <h4 class="font-medium text-blue-800 mb-2">
                                    <i class="fas fa-times-circle mr-1"></i>AI錯誤識別的表達:
                                </h4>
                                <ul class="list-disc pl-5 space-y-1">
                                    <?php foreach ($false_positives as $issue): ?>
                                        <li>
                                            <span class="line-through text-gray-500">"<?php echo htmlspecialchars($issue['expression']); ?>"</span> 
                                            <span class="text-gray-600">（實際上是正確的表達）</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <!-- 顯示教師其他建議 -->
                        <?php
                        $suggestions = array_filter($grammar_feedback, function($item) {
                            return $item['type'] == 'suggestion';
                        });
                        
                        if (!empty($suggestions)):
                            foreach ($suggestions as $suggestion):
                        ?>
                            <div class="mb-4">
                                <h4 class="font-medium text-blue-800 mb-2">
                                    <i class="fas fa-lightbulb mr-1"></i>教師對文法分析的建議:
                                </h4>
                                <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($suggestion['content'])); ?></p>
                            </div>
                        <?php 
                            endforeach;
                        endif; 
                        ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <?php if($essay['ai_score']): ?>
           <!-- AI評語 -->
			<div class="mb-8">
				<h3 class="text-lg font-semibold mb-3 text-blue-800 border-b pb-2">
					<i class="fas fa-robot mr-2"></i>AI評語
				</h3>
				<div class="bg-blue-50 p-4 rounded-lg border border-blue-200 ai-feedback">
					<?php
					// 初始化變數
					$grammar_issues = [];
					$error_descriptions = [
						"tense" => "時態使用錯誤",
						"subject_verb_agreement" => "主謂一致性問題",
						"plurals" => "複數形式錯誤",
						"article" => "冠詞使用錯誤",
						"word_choice" => "詞語選擇不當",
						"structure" => "句子結構問題",
						"preposition" => "介詞使用錯誤",
						"conjunction" => "拼接詞錯誤",
						"vocabulary" => "詞彙問題",
						"spelling" => "拼寫錯誤",
						"punctuation" => "標點符號錯誤",
						"unknown" => "其他錯誤"
					];
					
					// 提取評語的一般部分（非文法分析部分）
					$general_feedback = '';
					if (preg_match('/(.+?)【文法分析】/s', $essay['ai_feedback'], $matches)) {
						$general_feedback = $matches[1];
					} else {
						$general_feedback = $essay['ai_feedback']; // 如果沒有【文法分析】標記，顯示完整評語
					}
					
					// 顯示一般評語部分
					echo nl2br(htmlspecialchars($general_feedback));
					
					// 檢查是否有文法分析部分
					$has_grammar_section = preg_match('/【文法分析】(.*?)(?:【|$)/s', $essay['ai_feedback'] . '【', $matches);
					$grammar_section = $has_grammar_section ? $matches[1] : '';
					
					// 檢查是否有錯誤統計信息，例如"共發現 14 個文法問題"
					$total_issues_count = 0;
					if (preg_match('/共發現\s+(\d+)\s+個文法問題/i', $grammar_section, $matches)) {
						$total_issues_count = intval($matches[1]);
					}
					
					// 檢查是否有"未發現明顯的文法問題"
					$no_grammar_issues = false;
					if ($has_grammar_section && (
						strpos($grammar_section, '未發現明顯的文法問題') !== false || 
						strpos($grammar_section, '文法使用良好') !== false
					)) {
						$no_grammar_issues = true;
					} else if ($has_grammar_section) {
						// 新的解析策略：先收集所有錯誤類型的段落
					
						// 1. 使用正則表達式找出所有的"發現可能的XXX"段落
						preg_match_all('/發現可能的([^:]+)(?:\s*\(\d+個\))?[:：](.*?)(?=發現可能的|$)/s', 
									$grammar_section . '發現可能的', $sections, PREG_SET_ORDER);
						
						// 如果沒有找到段落，嘗試其他格式
						if (empty($sections)) {
							preg_match_all('/發現可能的([^(（\r\n]+)[（(]?(\d+)個[)）]?(.*?)(?=發現可能的|$)/s',
										$grammar_section . '發現可能的', $sections, PREG_SET_ORDER);
						}
						
						// 2. 遍歷每個段落提取問題
						foreach ($sections as $section) {
							$description = trim($section[1]);
							$content = isset($section[2]) ? trim($section[2]) : '';
							
							// 查找錯誤類型
							$type = null;
							foreach ($error_descriptions as $key => $desc) {
								if (strpos($description, $desc) !== false) {
									$type = $key;
									break;
								}
							}
							
							// 如果找不到匹配的類型，使用unknown
							if ($type === null) {
								$type = 'unknown';
							}
							
							// 提取該段落中的所有問題
							preg_match_all("/- ['\"](.+?)['\"](?:\s+(?:可能應為|應改為|應該為)\s+['\"](.+?)['\"])?/", 
										$content, $issue_matches, PREG_SET_ORDER);
							
							if (!empty($issue_matches)) {
								if (!isset($grammar_issues[$type])) {
									$grammar_issues[$type] = [];
								}
								
								foreach ($issue_matches as $issue) {
									$wrong = $issue[1];
									$correct = isset($issue[2]) ? $issue[2] : "";
									
									$issue_text = "'{$wrong}'";
									if (!empty($correct)) {
										$issue_text .= " 可能應為 '{$correct}'";
									}
									
									$grammar_issues[$type][] = $issue_text;
								}
							}
						}
						
						// 3. 如果上面的方法沒有找到問題，使用更簡單的方法：直接搜索所有問題行
						if (empty($grammar_issues)) {
							preg_match_all("/- ['\"](.+?)['\"](?:\s+(?:可能應為|應改為|應該為)\s+['\"](.+?)['\"])?/", 
										$grammar_section, $all_issues, PREG_SET_ORDER);
							
							if (!empty($all_issues)) {
								$grammar_issues['unknown'] = [];
								
								foreach ($all_issues as $issue) {
									$wrong = $issue[1];
									$correct = isset($issue[2]) ? $issue[2] : "";
									
									$issue_text = "'{$wrong}'";
									if (!empty($correct)) {
										$issue_text .= " 可能應為 '{$correct}'";
									}
									
									$grammar_issues['unknown'][] = $issue_text;
								}
							}
						}
					}
					
					// 特別處理：檢查是否找到的問題數量與統計數量匹配
					$found_issues_count = 0;
					foreach ($grammar_issues as $issues) {
						$found_issues_count += count($issues);
					}
					
					// 添加調試信息（可選，幫助您定位問題）
					$debug_info = "預期找到 {$total_issues_count} 個問題，實際找到 {$found_issues_count} 個問題";
					
					// 如果找到的問題數量與統計數量不匹配，嘗試使用最暴力的方法
					if ($total_issues_count > 0 && $found_issues_count < $total_issues_count) {
						// 直接搜索所有以 "- " 開頭且包含引號的行
						preg_match_all("/- ['\"](.+?)['\"](?:\s+(?:可能應為|應改為|應該為)\s+['\"](.+?)['\"])?/", 
									$grammar_section, $all_issues, PREG_SET_ORDER);
						
						if (count($all_issues) > $found_issues_count) {
							// 重置分類，所有問題放入unknown類別
							$grammar_issues = ['unknown' => []];
							
							foreach ($all_issues as $issue) {
								$wrong = $issue[1];
								$correct = isset($issue[2]) ? $issue[2] : "";
								
								$issue_text = "'{$wrong}'";
								if (!empty($correct)) {
									$issue_text .= " 可能應為 '{$correct}'";
								}
								
								$grammar_issues['unknown'][] = $issue_text;
							}
						}
					}
					?>
					
					<!-- 文法分析部分 -->
					<div class="grammar-analysis-container mt-4">
						<h3 class="text-lg font-semibold mb-3">【文法分析】</h3>
						
						<?php if ($no_grammar_issues || empty($grammar_issues)): ?>
							<p class="text-green-600">未發現明顯的文法問題，文法使用良好。</p>
						<?php else: ?>
							<!-- 錯誤統計摘要 -->
							<!-- 頁面頂部或文法分析部分開始的總統計 -->
							<?php 
							// 計算不包括拼寫錯誤的總數
							$filtered_total_count = $total_issues_count;
							$filtered_types_count = count($grammar_issues);
							
							// 如果存在拼寫錯誤類型，從總數中減去
							if (isset($grammar_issues['spelling'])) {
								$filtered_total_count -= count($grammar_issues['spelling']);
								$filtered_types_count--;
							}
							?>
							<div class="bg-yellow-50 p-4 rounded-lg mb-4">
								<p>共發現 <?php echo $filtered_total_count; ?> 個文法問題，分屬 <?php echo $filtered_types_count; ?> 種不同類型。</p>
							</div>
							
							<!-- 文法問題分類下拉選單 -->
							<div class="space-y-3 mb-6">
								<?php foreach ($grammar_issues as $type => $issues): ?>
								<?php 
								// 跳過拼寫錯誤類別
								if ($type === 'spelling') continue; 
								?>
										<div class="border border-gray-200 rounded-lg overflow-hidden">
										<!-- 類別標題 (可點擊) -->
										<div class="bg-blue-50 p-3 flex justify-between items-center cursor-pointer" 
											onclick="toggleGrammarCategory('<?php echo $type; ?>')">
											<div class="font-medium text-blue-800">
												發現可能的<?php echo htmlspecialchars($error_descriptions[$type] ?? $type); ?>
												<span class="text-sm text-gray-600 ml-2">(<?php echo count($issues); ?>個)</span>
											</div>
											<span class="toggle-icon" id="toggle-icon-<?php echo $type; ?>">▼</span>
										</div>
										
										<!-- 問題列表 (默認隱藏) -->
										<div class="p-3 bg-white grammar-issues-list hidden" id="grammar-issues-<?php echo $type; ?>">
											<ul class="list-disc pl-5 space-y-1.5">
												<?php foreach ($issues as $issue): ?>
													<?php 
													// 解析問題文本以提取錯誤和正確表達
													$wrong = '';
													$correct = '';
													if (preg_match("/'([^']+)'\s+可能應為\s+'([^']+)'/", $issue, $matches)) {
														$wrong = $matches[1];
														$correct = $matches[2];
													} elseif (preg_match("/'([^']+)'/", $issue, $matches)) {
														$wrong = $matches[1];
													}
													
													// 檢查是否為純拼寫錯誤
													$isPureSpelling = false;
													if ($type === 'spelling' && !empty($wrong) && !empty($correct)) {
														// 判斷是否為純拼寫錯誤的邏輯
														$isPureSpelling = isSpellingError($wrong, $correct);
													}
													?>
													
													<?php if ($type === 'spelling' && $isPureSpelling): ?>
														<li class="text-red-600">
															<span class="bg-yellow-100 px-1 py-0.5 text-xs rounded mr-1">純拼寫</span>
															<?php echo htmlspecialchars($issue); ?>
														</li>
													<?php else: ?>
														<li class="text-red-600"><?php echo htmlspecialchars($issue); ?></li>
													<?php endif; ?>
												<?php endforeach; ?>
											</ul>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
							
							<!-- 添加顯示/隱藏所有按鈕 -->
							<div class="flex justify-end mb-4">
								<button id="toggle-all-issues" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
									顯示所有錯誤
								</button>
							</div>
						<?php endif; ?>
					</div>
					
					
				</div>
			</div>
			
			<style>
			/* 文法分析下拉選單樣式 */
			.grammar-issues-list {
				max-height: 300px;
				overflow-y: auto;
			}
			</style>
			
			<script>
			// 切換單個類別的顯示/隱藏
			function toggleGrammarCategory(type) {
				const issuesList = document.getElementById('grammar-issues-' + type);
				const toggleIcon = document.getElementById('toggle-icon-' + type);
				
				if (issuesList.classList.contains('hidden')) {
					// 顯示
					issuesList.classList.remove('hidden');
					toggleIcon.textContent = '▲';
				} else {
					// 隱藏
					issuesList.classList.add('hidden');
					toggleIcon.textContent = '▼';
				}
			}
			
			// 切換所有類別的顯示/隱藏
			document.addEventListener('DOMContentLoaded', function() {
				const toggleAllBtn = document.getElementById('toggle-all-issues');
				if (toggleAllBtn) {
					toggleAllBtn.addEventListener('click', function() {
						const issuesLists = document.querySelectorAll('.grammar-issues-list');
						const toggleIcons = document.querySelectorAll('.toggle-icon');
						const showAll = this.textContent.includes('顯示');
						
						issuesLists.forEach(list => {
							if (showAll) {
								list.classList.remove('hidden');
							} else {
								list.classList.add('hidden');
							}
						});
						
						toggleIcons.forEach(icon => {
							icon.textContent = showAll ? '▲' : '▼';
						});
						
						this.textContent = showAll ? '隱藏所有錯誤' : '顯示所有錯誤';
					});
				}
			});
			</script>
					
					<!-- 顯示評語的其餘部分 -->
					<?php
					// 顯示詞彙豐富度部分
					if (preg_match('/【詞彙豐富度】(.*?)(?:【|$)/s', $essay['ai_feedback'] . '【', $matches)) {
						echo '<strong class="text-blue-700 block mt-4">【詞彙豐富度】</strong>' . nl2br(htmlspecialchars($matches[1]));
					}
					
					// 顯示句子流暢度部分
					if (preg_match('/【句子流暢度】(.*?)(?:【|$)/s', $essay['ai_feedback'] . '【', $matches)) {
						echo '<strong class="text-blue-700 block mt-4">【句子流暢度】</strong>' . nl2br(htmlspecialchars($matches[1]));
					}
					
					// 顯示結構分析部分
					if (preg_match('/【結構分析】(.*?)(?:【|$)/s', $essay['ai_feedback'] . '【', $matches)) {
						echo '<strong class="text-blue-700 block mt-4">【結構分析】</strong>' . nl2br(htmlspecialchars($matches[1]));
					}
					?>
					<!--  在 view_essay.php 中添加分享功能-->
					<?php if($_SESSION['user_type'] == 'student' && $essay['user_id'] == $_SESSION['id']): ?>
					<div class="mt-6 p-4 bg-gray-50 rounded-lg border">
						<h3 class="text-lg font-semibold mb-3">分享作文</h3>
						<p class="text-gray-600 mb-3">將您的作文分享到學習社群，幫助其他學生學習。</p>
						
						<form method="post" action="share_essay.php">
							<input type="hidden" name="essay_id" value="<?php echo $essay['id']; ?>">
							<div class="mb-3">
								<label class="flex items-center">
									<input type="checkbox" name="anonymous" value="1" class="mr-2">
									<span>匿名分享</span>
								</label>
							</div>
							<button type="submit" name="share" value="1" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
								<i class="fas fa-share-alt mr-1"></i>分享到社群
							</button>
						</form>
					</div>
					<?php endif; ?>
				</div>
			</div>
			
			<script>
			function toggleCategory(type) {
				var summaryDiv = document.getElementById('summary-' + type);
				var detailDiv = document.getElementById('detail-' + type);
				var toggleIcon = document.getElementById('toggle-' + type);
				
				if (summaryDiv.classList.contains('hidden')) {
					// 切換到簡要視圖
					summaryDiv.classList.remove('hidden');
					detailDiv.classList.add('hidden');
					toggleIcon.innerHTML = '+';
				} else {
					// 切換到詳細視圖
					summaryDiv.classList.add('hidden');
					detailDiv.classList.remove('hidden');
					toggleIcon.innerHTML = '-';
				}
			}
			
			// 確保DOM元素存在後再添加事件監聽器
			document.addEventListener('DOMContentLoaded', function() {
				var showAllBtn = document.getElementById('show-all-errors');
				if (showAllBtn) {
					showAllBtn.addEventListener('click', function() {
						// 獲取所有簡要和詳細視圖
						var summaries = document.querySelectorAll('[id^="summary-"]');
						var details = document.querySelectorAll('[id^="detail-"]');
						var toggleIcons = document.querySelectorAll('.expand-icon');
						
						// 切換顯示模式
						var showAll = this.innerHTML === '顯示所有錯誤';
						
						summaries.forEach(function(el) { 
							el.classList.toggle('hidden', showAll);
						});
						
						details.forEach(function(el) { 
							el.classList.toggle('hidden', !showAll);
						});
						
						toggleIcons.forEach(function(el) { 
							el.innerHTML = showAll ? '-' : '+';
						});
						
						this.innerHTML = showAll ? '收起詳細錯誤' : '顯示所有錯誤';
					});
				}
			});
			</script>
            <?php endif; ?>
			
            
            
        </div>
    </div>
</body>
</html>