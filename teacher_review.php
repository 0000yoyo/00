<?php
session_start();
require_once 'db_connection.php';
/**
 * 調試用函數：輸出變量詳細信息到頁面註釋中
 * @param mixed $var 要調試的變量
 * @param string $name 變量名稱
 */
function debug_var($var, $name = 'Debug') {
    echo "<!-- $name: " . htmlspecialchars(var_export($var, true)) . " -->\n";
}
/**
 * 修復單引號問題的函數
 * @param string $text 需要修復的文本
 * @return string 修復後的文本
 */
function fix_quotes($text) {
    // 如果輸入為空，直接返回
    if (empty($text)) return $text;
    
    // 解碼 HTML 實體
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // 處理可能被轉義的單引號
    $text = str_replace(["\'", "&#039;", "&apos;", "'", "'"], "'", $text);
    
    return $text;
}

// 確保只有教師可以訪問
if(!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] != 'teacher'){
    header("location: login.php");
    exit;
}

$essay_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = '';
$error_message = '';

// 檢查作文是否存在
$stmt = $conn->prepare("SELECT e.*, u.username FROM essays e 
                        JOIN users u ON e.user_id = u.id 
                        WHERE e.id = ? AND (e.teacher_review = 1 OR e.teacher_feedback IS NOT NULL)");
$stmt->execute([$essay_id]);
$essay = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$essay) {
    $error_message = "找不到指定的作文或您無權批改";
}

// 處理表單提交
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'review') {
    $score = isset($_POST['score']) ? intval($_POST['score']) : 0;
    $feedback = isset($_POST['feedback']) ? $_POST['feedback'] : '';
    
    // 確保分數在有效範圍內
    if ($score < 0 || $score > 100) {
        $error_message = "分數必須在 0-100 之間";
    } else {
        try {
            $conn->beginTransaction();
            
            // 初始化 teacher_grammar_feedback 變數
            $teacher_grammar_feedback = NULL; // 默認為 NULL

            // 檢查是否有文法反饋數據
            if (isset($_POST['missed_issues']) || isset($_POST['false_issues']) || isset($_POST['ai_improvement_suggestions'])) {
                // 初始化反饋數組
                $grammar_feedback = [];
                
                // 處理未識別的文法問題
                if (isset($_POST['missed_issues']) && is_array($_POST['missed_issues'])) {
                    $corrections = isset($_POST['corrections']) && is_array($_POST['corrections']) ? $_POST['corrections'] : [];
                    
                    for ($i = 0; $i < count($_POST['missed_issues']); $i++) {
                        $wrong = trim($_POST['missed_issues'][$i]);
                        $correct = isset($corrections[$i]) ? trim($corrections[$i]) : '';
                        
                        if (!empty($wrong)) {
                            $grammar_feedback[] = [
                                'type' => 'missed',
                                'wrong' => $wrong,
                                'correct' => $correct
                            ];
                        }
                    }
                }
                
                // 處理錯誤識別的問題
                if (isset($_POST['false_issues']) && !empty($_POST['false_issues'])) {
                    $false_issues = explode("\n", trim($_POST['false_issues']));
                    
                    foreach ($false_issues as $issue) {
                        $issue = trim($issue);
                        if (!empty($issue)) {
                            $grammar_feedback[] = [
                                'type' => 'false_positive',
                                'expression' => $issue
                            ];
                        }
                    }
                }
                
                // 處理其他建議
                if (isset($_POST['ai_improvement_suggestions']) && !empty($_POST['ai_improvement_suggestions'])) {
                    $grammar_feedback[] = [
                        'type' => 'suggestion',
                        'content' => trim($_POST['ai_improvement_suggestions'])
                    ];
                }
                
                // 將文法反饋保存為 JSON
                if (!empty($grammar_feedback)) {
                    $teacher_grammar_feedback = json_encode($grammar_feedback, JSON_UNESCAPED_UNICODE);
                }
            }
            
            // 更新作文批改資訊
			$stmt = $conn->prepare("UPDATE essays SET 
									teacher_id = ?, 
									score = ?, 
									teacher_feedback = ?, 
									teacher_grammar_feedback = ?,
									status = 'graded', 
									feedback_time = NOW(),
									graded_at = NOW() 
									WHERE id = ?");
			$stmt->execute([$_SESSION['id'], $score, $feedback, $teacher_grammar_feedback, $essay_id]);
            
            // 獲取 AI 評分信息（如果有）
            $ai_score = $essay['ai_score'];
            $ai_metrics_id = $essay['ai_metrics_id'];
            
            // 如果有 AI 評分和評分指標記錄
            if ($ai_score && $ai_metrics_id) {
                // 更新評分指標記錄
                $stmt = $conn->prepare("UPDATE essay_metrics 
                                        SET teacher_id = ?, 
                                            teacher_score = ?, 
                                            score_difference = ? 
                                        WHERE id = ?");
                
                $score_diff = $score - $ai_score;
                $stmt->execute([$_SESSION['id'], $score, $score_diff, $ai_metrics_id]);
            } 
            // 如果有 AI 評分但沒有評分指標記錄，創建一個
            else if ($ai_score && !$ai_metrics_id) {
                // 計算評分指標
                $word_count = str_word_count($essay['content']);
                $sentence_count = preg_match_all('/[.!?]+/', $essay['content'], $matches);
                
                // 插入評分指標記錄
                $stmt = $conn->prepare("INSERT INTO essay_metrics
                                        (essay_id, user_id, teacher_id, ai_score, teacher_score, 
                                        score_difference, word_count, sentence_count, category)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $score_diff = $score - $ai_score;
                
                $stmt->execute([
                    $essay_id, 
                    $essay['user_id'], 
                    $_SESSION['id'], 
                    $ai_score, 
                    $score, 
                    $score_diff,
                    $word_count,
                    $sentence_count,
                    $essay['category']
                ]);
                
                $metrics_id = $conn->lastInsertId();
                
                // 更新作文中的 ai_metrics_id
                $stmt = $conn->prepare("UPDATE essays SET ai_metrics_id = ? WHERE id = ?");
                $stmt->execute([$metrics_id, $essay_id]);
            }
            
            // 儲存文法反饋到 ai_grammar_feedback 表
            if (isset($_POST['missed_issues']) || isset($_POST['false_issues']) || isset($_POST['ai_improvement_suggestions'])) {
				try {
					// Processing missed issues
					if (isset($_POST['missed_issues']) && is_array($_POST['missed_issues'])) {
						$corrections = isset($_POST['corrections']) && is_array($_POST['corrections']) ? $_POST['corrections'] : [];
						$issue_types = isset($_POST['missed_issue_types']) && is_array($_POST['missed_issue_types']) ? $_POST['missed_issue_types'] : [];
						
						for ($i = 0; $i < count($_POST['missed_issues']); $i++) {
							$wrong = trim($_POST['missed_issues'][$i]);
							$correct = isset($corrections[$i]) ? trim($corrections[$i]) : '';
							$issue_type = isset($issue_types[$i]) && !empty($issue_types[$i]) ? $issue_types[$i] : 'unknown';
							
							if (!empty($wrong)) {
								// Insert into feedback table
								$stmt = $conn->prepare("
									INSERT INTO ai_grammar_feedback 
									(essay_id, teacher_id, feedback_type, wrong_expression, correct_expression, error_type, created_at)
									VALUES (?, ?, 'missed_issue', ?, ?, ?, NOW())
								");
								$stmt->execute([$essay_id, $_SESSION['id'], $wrong, $correct, $issue_type]);
							}
						}
					}
                    
                    // 處理 AI 錯誤識別的文法問題
                    if (isset($_POST['false_issues']) && !empty($_POST['false_issues'])) {
                        $false_issues = explode("\n", trim($_POST['false_issues']));
                        
                        foreach ($false_issues as $issue) {
                            $issue = trim($issue);
                            if (!empty($issue)) {
                                // 插入到反饋表
                                $stmt = $conn->prepare("
                                    INSERT INTO ai_grammar_feedback 
                                    (essay_id, teacher_id, feedback_type, wrong_expression, created_at)
                                    VALUES (?, ?, 'false_positive', ?, NOW())
                                ");
                                $stmt->execute([$essay_id, $_SESSION['id'], $issue]);
                            }
                        }
                    }
                    
                    // 處理其他建議
                    if (isset($_POST['ai_improvement_suggestions']) && !empty($_POST['ai_improvement_suggestions'])) {
                        $suggestion = trim($_POST['ai_improvement_suggestions']);
                        
                        // 插入到反饋表
                        $stmt = $conn->prepare("
                            INSERT INTO ai_grammar_feedback 
                            (essay_id, teacher_id, feedback_type, comment, created_at)
                            VALUES (?, ?, 'general', ?, NOW())
                        ");
                        $stmt->execute([$essay_id, $_SESSION['id'], $suggestion]);
                    }
                    
                    // 設置成功訊息
                    $success_message .= " AI評分反饋已記錄，感謝您的貢獻！";
                    
                } catch (PDOException $e) {
                    // 記錄錯誤但不影響正常批改流程
                    error_log("記錄AI反饋時發生錯誤：" . $e->getMessage());
                }
            }

            // 計算評語差異 (如果有AI評語)
            if (!empty($essay['ai_feedback']) && !empty($feedback)) {
                $feedback_diff = "教師評語與AI評語的差異部分";
                $stmt = $conn->prepare("UPDATE essays SET feedback_difference = ? WHERE id = ?");
                $stmt->execute([$feedback_diff, $essay_id]);
            }
            
            // 計算評分差異
            if (!empty($essay['ai_score']) && !empty($score)) {
                $score_diff = $score - $essay['ai_score'];
                $stmt = $conn->prepare("UPDATE essays SET score_difference = ? WHERE id = ?");
                $stmt->execute([$score_diff, $essay_id]);
            }
            
            $conn->commit();
            $success_message = "批改完成！評分和評語已保存。";
            
            // 更新頁面上的作文資訊
            $stmt = $conn->prepare("SELECT e.*, u.username FROM essays e 
                                    JOIN users u ON e.user_id = u.id 
                                    WHERE e.id = ?");
            $stmt->execute([$essay_id]);
            $essay = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $error_message = "保存失敗：" . $e->getMessage();
        }
    }
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
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>批改作文 - 作文批改平台</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-blue-700">批改作文</h1>
            <a href="teacher_dashboard.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-1"></i>返回儀表板
            </a>
        </div>

        <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            <?php echo $success_message; ?>
        </div>
        <?php endif; ?>

        <?php if ($essay): ?>
        <div class="mb-6">
            <div class="flex justify-between items-center mb-2">
                <h2 class="text-xl font-semibold"><?php echo htmlspecialchars($essay['title']); ?></h2>
                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
                    <?php echo $category_name; ?>
                </span>
            </div>
            <div class="text-gray-600 text-sm mb-4">
                <span>學生: <?php echo htmlspecialchars($essay['username']); ?></span>
                <span class="mx-2">|</span>
                <span>提交時間: <?php echo date('Y-m-d H:i', strtotime($essay['created_at'])); ?></span>
            </div>
            
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 prose max-w-none mb-4">
                <?php echo nl2br(htmlspecialchars($essay['content'])); ?>
            </div>
            
            <!-- AI 評分區塊 -->
            <?php if ($essay['ai_score']): ?>
            <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                <h3 class="text-lg font-semibold mb-2">AI 評分參考</h3>
                <div class="flex items-center mb-2">
                    <div class="text-3xl font-bold text-blue-600 mr-2"><?php echo $essay['ai_score']; ?></div>
                    <div class="text-sm text-gray-600">/ 100</div>
                </div>
                
                <?php if ($essay['ai_feedback']): ?>
				<div class="mt-3">
					<h4 class="font-medium mb-1">AI 評語:</h4>
					
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
					echo '<p class="text-gray-700">' . nl2br(htmlspecialchars($general_feedback)) . '</p>';
					
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
									
									// 不進行特殊處理，直接構建文本
									$issue_text = "'{$wrong}'";
									if (!empty($correct)) {
										$issue_text .= " 可能應為 '{$correct}'";
									}
									
									// 確保存儲未經處理的原始文本
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
					
					// 計算不包括拼寫錯誤的總數
					$filtered_total_count = $total_issues_count;
					$filtered_types_count = count($grammar_issues);
					
					// 如果存在拼寫錯誤類型，從總數中減去
					if (isset($grammar_issues['spelling'])) {
						$filtered_total_count -= count($grammar_issues['spelling']);
						$filtered_types_count--;
					}
					?>
					
					<!-- 文法分析部分 -->
					<?php
					// 最簡單的美化版本，保持原始內容不變
					
					$grammar_section = "";
					if (!empty($essay['ai_feedback'])) {
						if (preg_match('/【文法分析】(.*?)(?:【|$)/s', $essay['ai_feedback'] . '【', $matches)) {
							$grammar_section = $matches[1];
							?>
							
							<!-- 簡單美化的 AI 文法分析結果 -->
							<div class="mb-3 p-4 bg-white rounded-lg border border-blue-200 shadow-sm">
								<h4 class="text-md font-medium text-blue-800 mb-2 flex items-center">
									<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
									</svg>
									AI 文法分析結果
								</h4>
								<div class="text-sm text-gray-700 bg-gray-50 p-3 rounded border border-gray-100">
									<?php echo nl2br($grammar_section); ?>
								</div>
							</div>
							<?php
						}
					}
					?>
							</div>
						<?php endif; ?>
					</div>
					<?php endif; ?>
					
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
				</div>
				<?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- 教師批改表單 -->
            <div class="mt-8">
                <h3 class="text-lg font-semibold mb-4">教師批改</h3>
                
                <?php if ($essay['status'] == 'graded' && $essay['score']): ?>
                <!-- 已批改 -->
                <div class="p-4 bg-yellow-50 rounded-lg">
                    <div class="flex items-center mb-3">
                        <div class="text-3xl font-bold text-yellow-600 mr-2"><?php echo $essay['score']; ?></div>
                        <div class="text-sm text-gray-600">/ 100</div>
                    </div>
                    
                    <div class="mt-3">
                        <h4 class="font-medium mb-1">教師評語:</h4>
                        <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($essay['teacher_feedback'])); ?></p>
                    </div>
                    
                    <div class="mt-3 text-sm text-gray-500">
                        批改時間: <?php echo date('Y-m-d H:i', strtotime($essay['feedback_time'])); ?>
                    </div>
                </div>
                <?php else: ?>
                <!-- 未批改，顯示表單 -->
                <form method="post" class="space-y-6">
                    <input type="hidden" name="action" value="review">
                    
                    <div>
                        <label for="score" class="block text-sm font-medium text-gray-700 mb-1">評分 (0-100)</label>
                        <input type="number" id="score" name="score" required min="0" max="100"
                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            value="">  <!-- 移除預設值 -->
                    </div>
                    
                    <div>
                        <label for="feedback" class="block text-sm font-medium text-gray-700 mb-1">評語</label>
                        <textarea id="feedback" name="feedback" rows="6" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="請提供詳細的評語和改進建議..."></textarea>  <!-- 移除預設值 -->
                    </div>
                    
                    <!-- 增強版教師反饋區域 - 替換現有的反饋區塊 -->
					<div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
						<h3 class="text-md font-semibold mb-3 text-blue-800">AI 評分反饋</h3>
						<p class="text-sm text-gray-600 mb-3">
							您的反饋對改進AI評分系統非常寶貴。請針對AI的評分和文法分析提供您的專業意見。
							系統會自動學習您的反饋，以改進未來的文法分析。
						</p>
						
						<!-- 顯示AI分數與教師分數的比較 -->
						<?php if($essay['ai_score']): ?>
						<div class="mb-3 p-2 bg-white rounded border border-blue-100">
							<p class="text-sm">
								<span class="font-medium">AI評分: </span><?php echo $essay['ai_score']; ?> / 100
								<?php if(isset($_POST['score'])): ?>
								<span class="mx-2">|</span>
								<span class="font-medium">您的評分: </span><?php echo $_POST['score']; ?> / 100
								<span class="mx-2">|</span>
								<span class="font-medium">差異: </span>
								<span class="<?php echo abs($_POST['score'] - $essay['ai_score']) > 10 ? 'text-red-600' : 'text-green-600'; ?>">
									<?php echo $_POST['score'] - $essay['ai_score']; ?> 分
								</span>
								<?php endif; ?>
							</p>
						</div>
						<?php endif; ?>
						
						<!-- 顯示AI的文法分析結果 -->
						<?php
						$grammar_section = "";
						if (!empty($essay['ai_feedback'])) {
							if (preg_match('/【文法分析】(.*?)(?:【|$)/s', $essay['ai_feedback'] . '【', $matches)) {
								$grammar_section = $matches[1];
								
								echo '<div class="mb-3 p-2 bg-white rounded border border-blue-100">';
								echo '<p class="text-sm font-medium text-gray-700 mb-1">AI 文法分析結果：</p>';
								echo '<div class="text-sm text-gray-600">' . nl2br(htmlspecialchars($grammar_section)) . '</div>';
								echo '</div>';
							}
						}
						?>
						
						<!-- 收集教師反饋 -->
						<div class="space-y-3">
							<!-- 漏檢文法問題 -->
							<div>
								<label class="block text-sm font-medium text-blue-700 mb-1">
									<i class="fas fa-plus-circle mr-1"></i>AI 未識別的文法問題：
								</label>
								<div class="flex flex-col space-y-2" id="missed-issues-container">
									<div class="flex space-x-2">
										<!-- 添加錯誤類型選單 -->
										<select name="missed_issue_types[]" class="w-1/4 px-3 py-2 text-sm border border-gray-300 rounded-md">
											<option value="">選擇錯誤類型</option>
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
										
										<input type="text" name="missed_issues[]" placeholder="錯誤表達 (例如: they is)"
											class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md">
										<input type="text" name="corrections[]" placeholder="正確表達 (例如: they are)"
											class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md">
									</div>
								</div>
								<button type="button" id="add-more-issues" class="mt-2 px-3 py-1 text-xs bg-blue-500 text-white rounded hover:bg-blue-600">
									<i class="fas fa-plus mr-1"></i>添加更多
								</button>
							</div>
							
							<!-- 誤報文法問題 -->
							<div>
								<label class="block text-sm font-medium text-blue-700 mb-1">
									<i class="fas fa-times-circle mr-1"></i>AI 錯誤識別的文法問題：
								</label>
								<textarea name="false_issues" rows="2" placeholder="請填寫 AI 錯誤識別的文法問題，每個問題一行。例如：'a university' (AI誤報它應該是 'an university')"
										class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md"></textarea>
								<p class="text-xs text-gray-500 mt-1">直接複製 AI 文法分析中的錯誤表達，無需引號。每行一個表達。</p>
							</div>
							
							<!-- 改進建議 -->
							<div>
								<label class="block text-sm font-medium text-blue-700 mb-1">
									<i class="fas fa-comment-alt mr-1"></i>對 AI 文法分析的改進建議：
								</label>
								<textarea name="ai_improvement_suggestions" rows="2" placeholder="請提供對 AI 評分系統的其他建議..."
										class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md"></textarea>
								<p class="text-xs text-gray-500 mt-1">您的建議將幫助我們改進 AI 評分系統。</p>
							</div>
						</div>
						
						<div class="mt-4 p-2 bg-yellow-50 rounded border border-yellow-200 text-sm">
							<p class="flex items-center">
								<i class="fas fa-lightbulb text-yellow-600 mr-2"></i>
								<span class="text-yellow-800">提示：為獲得最佳學習效果，請提供簡短、具體的文法表達，而非完整句子。例如：「they is → they are」而非「They is going to school → They are going to school」。</span>
							</p>
						</div>
					</div>                    
                    <div class="flex justify-end space-x-4">
                        <a href="teacher_dashboard.php" 
                        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            取消
                        </a>
                        <button type="submit" 
                                class="px-6 py-2 bg-blue-600 rounded-lg text-white hover:bg-blue-700 transition-colors">
                            提交批改
                        </button>
                    </div>
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
						// 添加更多文法問題按鈕事件
						const addMoreButton = document.getElementById('add-more-issues');
						if (addMoreButton) {
							addMoreButton.addEventListener('click', function() {
								const container = document.getElementById('missed-issues-container');
								const newRow = document.createElement('div');
								newRow.className = 'flex space-x-2';
								newRow.innerHTML = `
									<!-- 添加錯誤類型選單 -->
									<select name="missed_issue_types[]" class="w-1/4 px-3 py-2 text-sm border border-gray-300 rounded-md">
										<option value="">選擇錯誤類型</option>
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
									<input type="text" name="missed_issues[]" placeholder="錯誤表達 (例如: they is)"
										class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md">
									<input type="text" name="corrections[]" placeholder="正確表達 (例如: they are)"
										class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md">
									<button type="button" class="remove-issue px-2 py-1 bg-red-500 text-white rounded hover:bg-red-600">
										<i class="fas fa-times"></i>
									</button>
								`;
								container.appendChild(newRow);
								
								// 添加移除按鈕事件
								newRow.querySelector('.remove-issue').addEventListener('click', function() {
									container.removeChild(newRow);
								});
							});
						} else {
							console.error('找不到添加更多按鈕元素');
						}
						
						// 添加除錯代碼，幫助檢查問題
						console.log('DOM已載入，JS初始化完成');
						console.log('添加更多按鈕元素:', addMoreButton);
						console.log('表單容器元素:', document.getElementById('missed-issues-container'));
					});
                    </script>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="p-6 bg-gray-50 rounded-lg text-center">
            <p class="text-gray-700">找不到指定的作文或您無權批改</p>
            <a href="teacher_dashboard.php" class="inline-block mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                返回儀表板
            </a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>