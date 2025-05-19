<?php
session_start();
require_once 'db_connection.php';

// 確保只有管理員可以訪問
if(!isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true){
    header("location: login.php");
    exit;
}

// 檢查並創建必要的表格
$tables_created = false;
$create_table_messages = [];

try {
    // 檢查是否存在 ai_grammar_feedback 表
    $result = $conn->query("SHOW TABLES LIKE 'ai_grammar_feedback'");
    if ($result->rowCount() == 0) {
        // 創建表格
        $sql = "CREATE TABLE `ai_grammar_feedback` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `essay_id` int(11) NOT NULL,
            `teacher_id` int(11) NOT NULL,
            `feedback_type` enum('missed_issue','false_positive','general') NOT NULL,
            `wrong_expression` varchar(255) DEFAULT NULL,
            `correct_expression` varchar(255) DEFAULT NULL,
            `comment` text DEFAULT NULL,
            `processed` tinyint(1) NOT NULL DEFAULT 0,
            `processed_at` datetime DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `essay_id` (`essay_id`),
            KEY `teacher_id` (`teacher_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $conn->exec($sql);
        $create_table_messages[] = "成功創建 ai_grammar_feedback 表格";
        $tables_created = true;
    }
    
    // 檢查是否存在 ai_training_logs 表
    $result = $conn->query("SHOW TABLES LIKE 'ai_training_logs'");
    if ($result->rowCount() == 0) {
        // 創建表格
        $sql = "CREATE TABLE `ai_training_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `feedback_id` int(11) DEFAULT NULL,
            `process_type` enum('grammar_feedback','score_feedback','model_training') NOT NULL,
            `status` enum('pending','processing','failed','success','processed') NOT NULL DEFAULT 'pending',
            `processed_at` datetime DEFAULT NULL,
            `notes` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `feedback_id` (`feedback_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $conn->exec($sql);
        $create_table_messages[] = "成功創建 ai_training_logs 表格";
        $tables_created = true;
    }
    
    // 檢查 essays 表中是否有 processed_for_training 欄位
    $result = $conn->query("SHOW COLUMNS FROM essays LIKE 'processed_for_training'");
    if ($result->rowCount() == 0) {
        // 添加 processed_for_training 欄位
        $sql = "ALTER TABLE essays ADD COLUMN processed_for_training TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已用於AI訓練'";
        $conn->exec($sql);
        $create_table_messages[] = "成功添加 processed_for_training 欄位到 essays 表格";
        $tables_created = true;
    }
    
    // 確保日誌目錄存在
    $log_dir = __DIR__ . '/logs';
    if (!file_exists($log_dir)) {
        if (mkdir($log_dir, 0755, true)) {
            $create_table_messages[] = "成功創建日誌目錄: {$log_dir}";
            $tables_created = true;
        }
    }
    
} catch (PDOException $e) {
    $create_table_messages[] = "創建表格時出錯: " . $e->getMessage();
}

// 獲取反饋統計
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN processed = 1 THEN 1 ELSE 0 END) as processed
        FROM ai_grammar_feedback
    ");
    $stmt->execute();
    $feedback_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $feedback_stats = ['total' => 0, 'processed' => 0];
}

// 獲取訓練任務記錄
try {
    $stmt = $conn->prepare("
        SELECT * FROM ai_training_logs
        WHERE process_type = 'model_training'
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $training_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $training_tasks = [];
}

// 獲取反饋處理記錄
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count, process_type, status
        FROM ai_training_logs
        GROUP BY process_type, status
    ");
    $stmt->execute();
    $feedback_stats_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $feedback_stats_logs = [];
}

// 獲取模型訓練歷史
try {
    $stmt = $conn->prepare("
        SELECT * FROM model_training
        ORDER BY training_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $model_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $model_history = [];
}

// 獲取評分差異統計
try {
    $stmt = $conn->prepare("
        SELECT 
            ROUND(AVG(ABS(IFNULL(score_difference, score - ai_score))), 2) as avg_diff,
            MAX(ABS(IFNULL(score_difference, score - ai_score))) as max_diff,
            COUNT(*) as count
        FROM essays
        WHERE ai_score IS NOT NULL AND score IS NOT NULL
    ");
    $stmt->execute();
    $score_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $score_stats = ['avg_diff' => 0, 'max_diff' => 0, 'count' => 0];
}

// 獲取最近的文法反饋
try {
    $stmt = $conn->prepare("
        SELECT f.*, e.title as essay_title, u.username as teacher_name,
			   f.error_type as specific_error_type
        FROM ai_grammar_feedback f
        JOIN essays e ON f.essay_id = e.id
        JOIN users u ON f.teacher_id = u.id
        ORDER BY f.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_feedback = [];
}

// 獲取訓練日誌文件列表
$logs = [];
$log_dir = __DIR__ . '/logs';
if (file_exists($log_dir)) {
    $files = scandir($log_dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && is_file($log_dir . '/' . $file)) {
            $logs[] = $file;
        }
    }
}

// 手動啟動訓練
$training_message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'start_training') {
    try {
        // 計算新版本號
        $stmt = $conn->prepare("
            SELECT MAX(CAST(SUBSTRING_INDEX(model_version, '.', -1) AS UNSIGNED)) as last_patch
            FROM model_training
            WHERE model_version LIKE '1.0.%'
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $last_patch = $result['last_patch'] ?? 0;
        $new_version = "1.0." . ($last_patch + 1);
        
        // 創建訓練任務
        $stmt = $conn->prepare("
            INSERT INTO ai_training_logs 
            (process_type, status, notes)
            VALUES ('model_training', 'pending', ?)
        ");
        $notes = "手動啟動訓練 {$new_version} 版模型";
        $stmt->execute([$notes]);
        $task_id = $conn->lastInsertId();
        
        // 執行訓練腳本
        $command = "php execute_model_training.php --task_id={$task_id} --version={$new_version} > /dev/null 2>&1 &";
        exec($command);
        
        $training_message = "已成功啟動模型訓練任務 (ID: {$task_id}，版本: {$new_version})";
    } catch (Exception $e) {
        $training_message = "啟動訓練失敗: " . $e->getMessage();
    }
}
// 手動優化文法規則
$optimize_message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'optimize_rules') {
    try {
        // 直接在這裡執行優化邏輯，而不是調用外部文件
        
        // 載入現有規則
        $rules_path = __DIR__ . '/models/grammar_rules.pkl';
        $backup_path = __DIR__ . '/models/grammar_rules_backup_' . date('Ymd_His') . '.pkl';
        
        // 創建備份
        if (file_exists($rules_path)) {
            if (copy($rules_path, $backup_path)) {
                $optimize_message .= "已創建備份文件: {$backup_path}<br>";
            } else {
                $optimize_message .= "警告: 無法創建備份文件: {$backup_path}<br>";
            }
        }
        
        // 讀取現有規則
        $data = file_get_contents($rules_path);
        $grammar_rules = unserialize($data);
        
        if (!$grammar_rules || !isset($grammar_rules['rules'])) {
            throw new Exception("無法解析文法規則文件或格式不正確");
        }
        
        // 1. 清理因多次反饋而標記為刪除的規則
        $total_rules_before = 0;
        $removed_rules = 0;
        
        foreach ($grammar_rules['rules'] as $error_type => &$rules) {
            $total_rules_before += count($rules);
            $original_count = count($rules);
            
            // 移除標記為已刪除的規則
            $rules = array_filter($rules, function($rule) {
                return !(isset($rule['is_removed']) && $rule['is_removed']);
            });
            
            // 如果有移除規則，重新索引數組
            if (count($rules) < $original_count) {
                $rules = array_values($rules);
                $removed_count = $original_count - count($rules);
                $removed_rules += $removed_count;
            }
        }
        
        // 2. 合併相似的規則
        $merged_rules = 0;
        
        foreach ($grammar_rules['rules'] as $error_type => &$rules) {
            $original_count = count($rules);
            
            // 按原始表達式分組
            $grouped_rules = [];
            foreach ($rules as $rule) {
                $original = $rule['original'];
                if (!isset($grouped_rules[$original])) {
                    $grouped_rules[$original] = [];
                }
                $grouped_rules[$original][] = $rule;
            }
            
            // 合併相同原始表達式的規則
            $merged_type_rules = [];
            foreach ($grouped_rules as $original => $similar_rules) {
                if (count($similar_rules) > 1) {
                    // 合併多個相同原始表達式的規則
                    $merged_rule = array_reduce($similar_rules, function($carry, $item) {
                        // 初始化合併規則
                        if (!$carry) {
                            return $item;
                        }
                        
                        // 合併可能的正確表達式
                        $corrected = array_unique(array_merge(
                            isset($carry['corrected']) ? $carry['corrected'] : [], 
                            isset($item['corrected']) ? $item['corrected'] : []
                        ));
                        
                        // 合併示例
                        $examples = isset($carry['examples']) ? $carry['examples'] : [];
                        if (isset($item['examples'])) {
                            $examples = array_unique(array_merge($examples, $item['examples']));
                        }
                        
                        // 合併上下文
                        $contexts = isset($carry['contexts']) ? $carry['contexts'] : [];
                        if (isset($item['contexts'])) {
                            $contexts = array_unique(array_merge($contexts, $item['contexts']));
                        }
                        
                        // 合併計數和權重，使用最大值
                        $count = max(
                            isset($carry['count']) ? $carry['count'] : 0,
                            isset($item['count']) ? $item['count'] : 0
                        );
                        
                        $weight = max(
                            isset($carry['weight']) ? $carry['weight'] : 0,
                            isset($item['weight']) ? $item['weight'] : 0
                        );
                        
                        // 保留反饋記錄
                        $feedback = isset($carry['feedback']) ? $carry['feedback'] : [];
                        if (isset($item['feedback'])) {
                            $feedback = array_merge($feedback, $item['feedback']);
                        }
                        
                        return [
                            'original' => $original,
                            'corrected' => $corrected,
                            'count' => $count,
                            'weight' => $weight,
                            'examples' => $examples,
                            'contexts' => $contexts,
                            'feedback' => $feedback,
                            'last_updated' => date('Y-m-d H:i:s')
                        ];
                    });
                    
                    $merged_type_rules[] = $merged_rule;
                    $merged_rules += (count($similar_rules) - 1);
                } else {
                    // 只有一個規則，直接添加
                    $merged_type_rules[] = $similar_rules[0];
				}
			}
		}
	}
	catch (Exception $e) {
    $optimize_message = "優化文法規則時出錯: " . $e->getMessage();
	}
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>AI系統監控 - 作文批改平台</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-2xl font-bold text-blue-700">AI系統監控</h1>
                <div>
                    <a href="admin_dashboard.php" class="text-blue-600 hover:text-blue-800 ml-4">
                        <i class="fas fa-arrow-left mr-1"></i>返回管理面板
                    </a>
                </div>
            </div>
            
            <?php if ($tables_created): ?>
            <div class="mb-6 bg-green-100 p-4 rounded-lg">
                <h2 class="font-semibold text-green-800">系統設置更新</h2>
                <ul class="mt-2 list-disc list-inside">
                    <?php foreach ($create_table_messages as $message): ?>
                    <li><?php echo htmlspecialchars($message); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($training_message)): ?>
			<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
				<?php echo htmlspecialchars($training_message); ?>
			</div>
			<?php endif; ?>
			
			<?php if (!empty($optimize_message)): ?>
			<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
				<?php echo $optimize_message; ?>
			</div>
			<?php endif; ?>
            
            <!-- 系統概況 -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="font-medium text-blue-800 mb-1">AI評分模型版本</div>
                    <div class="text-2xl font-bold">
                        <?php 
                        echo !empty($model_history) ? htmlspecialchars($model_history[0]['model_version']) : '未訓練'; 
                        ?>
                    </div>
                    <div class="text-sm text-gray-600 mt-1">
                        <?php 
                        echo !empty($model_history) ? "最後更新: " . htmlspecialchars($model_history[0]['training_date']) : ''; 
                        ?>
                    </div>
                </div>
                
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="font-medium text-green-800 mb-1">AI vs 教師評分差異</div>
                    <div class="text-2xl font-bold">
                        平均 <?php echo $score_stats['avg_diff'] ?? '0'; ?> 分
                    </div>
                    <div class="text-sm text-gray-600 mt-1">
                        最大差異: <?php echo $score_stats['max_diff'] ?? '0'; ?> 分
                        (基於 <?php echo $score_stats['count'] ?? '0'; ?> 個樣本)
                    </div>
                </div>
                
                <div class="bg-purple-50 p-4 rounded-lg">
                    <div class="font-medium text-purple-800 mb-1">文法反饋統計</div>
                    <div class="text-2xl font-bold">
                        <?php echo number_format($feedback_stats['processed'] ?? 0); ?> / <?php echo number_format($feedback_stats['total'] ?? 0); ?>
                    </div>
                    <div class="text-sm text-gray-600 mt-1">
                        已處理 / 總計
                    </div>
                </div>
            </div>
            
            <!-- 操作按鈕 -->
			<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
				<a href="process_grammar_feedback.php" class="bg-blue-600 text-white p-4 rounded-lg text-center hover:bg-blue-700 transition">
					<div class="font-medium">處理文法反饋</div>
					<div class="text-sm mt-1">執行文法反饋處理</div>
				</a>
				
				<a href="collect_teacher_feedback.php" class="bg-green-600 text-white p-4 rounded-lg text-center hover:bg-green-700 transition">
					<div class="font-medium">收集評分反饋</div>
					<div class="text-sm mt-1">收集教師評分資料</div>
				</a>
				
				<a href="admin_grammar_rules.php" class="bg-yellow-600 text-white p-4 rounded-lg text-center hover:bg-yellow-700 transition">
					<div class="font-medium">文法規則管理</div>
					<div class="text-sm mt-1">查看與編輯文法規則</div>
				</a>
				
				<a href="admin_reports.php" class="bg-purple-600 text-white p-4 rounded-lg text-center hover:bg-purple-700 transition">
					<div class="font-medium">系統報告</div>
					<div class="text-sm mt-1">查看系統分析報告</div>
				</a>
				<a href="grammar_rules_manager.php" class="bg-purple-600 text-white p-4 rounded-lg text-center hover:bg-purple-700 transition">
					<div class="font-medium">文法規則管理</div>
					<div class="text-sm mt-1">管理和測試文法規則</div>
				</a>
				<a href="review_grammar_rules.php" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
					<i class="fas fa-check-double mr-1"></i>審核文法規則
				</a>
				<a href="optimize_grammar_rules.php" class="bg-purple-600 text-white p-4 rounded-lg text-center hover:bg-purple-700 transition">
					<div class="font-medium">優化文法規則</div>
					<div class="text-sm mt-1">合併和優化規則資料庫</div>
				</a>
			</div>
            
            <!-- 手動訓練按鈕 -->
            <div class="mb-6 bg-gray-50 p-4 rounded-lg">
                <h3 class="font-medium text-gray-700 mb-2">模型訓練</h3>
                <form method="post" class="flex items-center">
                    <input type="hidden" name="action" value="start_training">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-play mr-1"></i>手動啟動模型訓練
                    </button>
                    <span class="ml-3 text-sm text-gray-600">
                        只有當您確定需要立即訓練新模型時才使用此功能
                    </span>
                </form>
            </div>
			<!-- 文法規則優化按鈕 -->
			<div class="mb-6 bg-gray-50 p-4 rounded-lg">
				<h3 class="font-medium text-gray-700 mb-2">文法規則優化</h3>
				<form method="post" class="flex items-center">
					<input type="hidden" name="action" value="optimize_rules">
					<button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
						<i class="fas fa-magic mr-1"></i>優化文法規則庫
					</button>
					<span class="ml-3 text-sm text-gray-600">
						清理無效規則、合併相似規則，提高文法分析準確度
					</span>
				</form>
			</div>
            
            <!-- 訓練任務列表 -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold mb-3">最近訓練任務</h2>
                
                <?php if (empty($training_tasks)): ?>
                <div class="bg-gray-50 p-4 rounded text-center">
                    尚無訓練任務記錄
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="py-2 px-4 border">ID</th>
                                <th class="py-2 px-4 border">狀態</th>
                                <th class="py-2 px-4 border">建立時間</th>
                                <th class="py-2 px-4 border">處理時間</th>
                                <th class="py-2 px-4 border">備註</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($training_tasks as $task): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-4 border"><?php echo htmlspecialchars($task['id']); ?></td>
                                <td class="py-2 px-4 border">
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    switch ($task['status']) {
                                        case 'pending':
                                            $status_class = 'bg-yellow-100 text-yellow-800';
                                            $status_text = '待處理';
                                            break;
                                        case 'processing':
                                            $status_class = 'bg-blue-100 text-blue-800';
                                            $status_text = '處理中';
                                            break;
                                        case 'success':
                                            $status_class = 'bg-green-100 text-green-800';
                                            $status_text = '成功';
                                            break;
                                        case 'failed':
                                            $status_class = 'bg-red-100 text-red-800';
                                            $status_text = '失敗';
                                            break;
                                        default:
                                            $status_class = 'bg-gray-100 text-gray-800';
                                            $status_text = $task['status'];
                                    }
                                    ?>
                                    <span class="px-2 py-1 rounded <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td class="py-2 px-4 border"><?php echo htmlspecialchars($task['created_at']); ?></td>
                                <td class="py-2 px-4 border"><?php echo $task['processed_at'] ? htmlspecialchars($task['processed_at']) : '-'; ?></td>
                                <td class="py-2 px-4 border"><?php echo nl2br(htmlspecialchars($task['notes'] ?? '')); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- 模型訓練歷史 -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold mb-3">模型版本歷史</h2>
                
                <?php if (empty($model_history)): ?>
                <div class="bg-gray-50 p-4 rounded text-center">
                    尚無模型訓練記錄
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="py-2 px-4 border">版本</th>
                                <th class="py-2 px-4 border">訓練日期</th>
                                <th class="py-2 px-4 border">訓練集大小</th>
                                <th class="py-2 px-4 border">準確度</th>
                                <th class="py-2 px-4 border">備註</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($model_history as $model): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-4 border font-medium"><?php echo htmlspecialchars($model['model_version']); ?></td>
                                <td class="py-2 px-4 border"><?php echo htmlspecialchars($model['training_date']); ?></td>
                                <td class="py-2 px-4 border"><?php echo htmlspecialchars($model['dataset_size']); ?></td>
                                <td class="py-2 px-4 border"><?php echo round($model['accuracy'] * 100, 2); ?>%</td>
                                <td class="py-2 px-4 border"><?php echo htmlspecialchars($model['notes'] ?? ''); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- 最近文法反饋 -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold mb-3">最近文法反饋</h2>
                
                <?php if (empty($recent_feedback)): ?>
                <div class="bg-gray-50 p-4 rounded text-center">
                    尚無文法反饋記錄
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="py-2 px-4 border">ID</th>
                                <th class="py-2 px-4 border">作文標題</th>
                                <th class="py-2 px-4 border">教師</th>
                                <th class="py-2 px-4 border">反饋類型</th>
                                <th class="py-2 px-4 border">錯誤表達</th>
                                <th class="py-2 px-4 border">正確表達</th>
                                <th class="py-2 px-4 border">已處理</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_feedback as $feedback): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-4 border"><?php echo htmlspecialchars($feedback['id']); ?></td>
                                <td class="py-2 px-4 border"><?php echo htmlspecialchars($feedback['essay_title']); ?></td>
                                <td class="py-2 px-4 border"><?php echo htmlspecialchars($feedback['teacher_name']); ?></td>
                                <!-- 在顯示部分 -->
								<td class="py-2 px-4 border-b">
									<?php 
									$types = [
										'missed_issue' => '未識別問題',
										'false_positive' => '誤報問題',
										'general' => '一般反饋'
									];
									$error_types = [
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
									
									// 顯示主要反饋類型
									echo $types[$feedback['feedback_type']] ?? $feedback['feedback_type']; 
									
									// 如果是未識別問題，並且有具體的錯誤類型，則顯示具體類型
									if ($feedback['feedback_type'] == 'missed_issue' && !empty($feedback['specific_error_type'])) {
										echo ' - ' . ($error_types[$feedback['specific_error_type']] ?? $feedback['specific_error_type']);
									}
									?>
								</td>
                                <td class="py-2 px-4 border"><?php echo htmlspecialchars($feedback['wrong_expression'] ?? ''); ?></td>
                                <td class="py-2 px-4 border"><?php echo htmlspecialchars($feedback['correct_expression'] ?? ''); ?></td>
                                <td class="py-2 px-4 border">
                                    <?php if ($feedback['processed']): ?>
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded">是</span>
                                    <?php else: ?>
                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded">否</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- 日誌文件 -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold mb-3">日誌文件</h2>
                
                <?php if (empty($logs)): ?>
                <div class="bg-gray-50 p-4 rounded text-center">
                    尚無日誌文件
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($logs as $log): ?>
                    <div class="p-3 bg-gray-50 rounded border flex justify-between items-center">
                        <span><?php echo htmlspecialchars($log); ?></span>
                        <a href="view_log.php?file=<?php echo urlencode($log); ?>" class="text-blue-600 hover:text-blue-800">查看</a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- 設置和幫助 -->
            <div class="bg-gray-50 p-4 rounded-lg mt-8">
                <h3 class="font-medium text-gray-700 mb-2">系統設置和幫助</h3>
                <div class="flex flex-wrap gap-4">
                    <a href="setup_tables.php" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-database mr-1"></i>檢查及創建資料表
                    </a>
                    <a href="model_management.php" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-cogs mr-1"></i>模型管理
                    </a>
                    <a href="#" class="text-blue-600 hover:text-blue-800" onclick="alert('此功能開發中')">
                        <i class="fas fa-question-circle mr-1"></i>幫助文檔
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 添加查看日誌的模態框 -->
    <div id="log-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-3xl w-full max-h-screen overflow-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold" id="log-modal-title">查看日誌</h3>
                <button type="button" id="close-log-modal" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="bg-gray-50 p-4 rounded border overflow-auto max-h-[70vh]">
                <pre class="text-sm" id="log-modal-content"></pre>
            </div>
        </div>
    </div>

    <script>
    // 日誌查看模態框功能
    document.addEventListener('DOMContentLoaded', function() {
        const logModal = document.getElementById('log-modal');
        const logModalTitle = document.getElementById('log-modal-title');
        const logModalContent = document.getElementById('log-modal-content');
        const closeLogModal = document.getElementById('close-log-modal');
        
        // 關閉模態框
        closeLogModal.addEventListener('click', function() {
            logModal.classList.add('hidden');
        });
        
        // 點擊背景關閉模態框
        logModal.addEventListener('click', function(e) {
            if (e.target === logModal) {
                logModal.classList.add('hidden');
            }
        });
        
        // 查看日誌的功能可以在此添加
    });
    </script>
</body>
</html>