<?php
session_start();
require_once 'db_connection.php'; // 確保引入資料庫連接

// 確保只有學生可以訪問
if(!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] != 'student'){
    header("location: login.php");
    exit;
}

// 獲取最近批改的作文（只包含教師已批改的作文）
$stmt = $conn->prepare("
    SELECT e.*, t.username AS teacher_name
    FROM essays e 
    LEFT JOIN users t ON e.teacher_id = t.id
    WHERE e.user_id = ? 
    AND e.teacher_review = 1 
    AND e.status = 'graded'
    ORDER BY e.feedback_time DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['id']]);
$recent_essays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 計算個人統計數據
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_essays,
        SUM(CASE WHEN teacher_review = 1 AND status = 'graded' THEN 1 ELSE 0 END) as teacher_graded,
        SUM(CASE WHEN teacher_review = 0 AND ai_score IS NOT NULL THEN 1 ELSE 0 END) as ai_graded,
        SUM(CASE WHEN teacher_review = 1 AND status = 'pending' THEN 1 ELSE 0 END) as pending,
        AVG(CASE WHEN teacher_review = 1 AND status = 'graded' THEN score ELSE NULL END) as avg_teacher_score,
        AVG(CASE WHEN teacher_review = 0 AND ai_score IS NOT NULL THEN ai_score ELSE NULL END) as avg_ai_score
    FROM essays
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// 獲取寫作進度數據（最近5篇由教師批改的作文評分趨勢）
$stmt = $conn->prepare("
    SELECT 
        id,
        title,
        score as final_score,
        created_at,
        feedback_time
    FROM essays
    WHERE user_id = ? 
    AND teacher_review = 1 
    AND status = 'graded'
    ORDER BY feedback_time DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['id']]);
$progress_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
// 反轉數組，以便從最舊到最新顯示
$progress_data = array_reverse($progress_data);

// 獲取常見問題分析
$stmt = $conn->prepare("
    SELECT 
        category,
        COUNT(*) as count,
        AVG(score) as avg_score
    FROM essays
    WHERE user_id = ? 
    AND teacher_review = 1 
    AND status = 'graded'
    GROUP BY category
    ORDER BY count DESC
");
$stmt->execute([$_SESSION['id']]);
$category_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>學生面板 - 作文批改平台</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 min-h-screen p-4">
    <div class="max-w-6xl mx-auto">
        <header class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-blue-700">歡迎回來，<?php echo htmlspecialchars($_SESSION["username"]); ?></h1>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded transition-colors">
                    <i class="fas fa-sign-out-alt mr-1"></i>登出
                </a>
            </div>
        </header>

        <!-- 統計信息卡片 -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow-md text-center">
                <div class="text-gray-600 mb-1">總作文數</div>
                <div class="text-2xl font-bold"><?php echo $stats['total_essays'] ?: 0; ?></div>
            </div>
            
            <div class="bg-white p-4 rounded-lg shadow-md text-center">
                <div class="text-gray-600 mb-1">待批改</div>
                <div class="text-2xl font-bold text-yellow-600"><?php echo $stats['pending'] ?: 0; ?></div>
            </div>
            
            <div class="bg-white p-4 rounded-lg shadow-md text-center">
                <div class="text-gray-600 mb-1">教師平均分</div>
                <div class="text-2xl font-bold text-green-600">
                    <?php echo $stats['avg_teacher_score'] ? number_format($stats['avg_teacher_score'], 1) : '-'; ?>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg shadow-md text-center">
                <div class="text-gray-600 mb-1">AI平均分</div>
                <div class="text-2xl font-bold text-blue-600">
                    <?php echo $stats['avg_ai_score'] ? number_format($stats['avg_ai_score'], 1) : '-'; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- 快速功能區塊 -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold text-blue-800 mb-4 flex items-center">
                    <i class="fas fa-bolt text-yellow-500 mr-2"></i>快速功能
                </h2>
                <ul class="space-y-4">
                    <li>
                        <a href="upload_essay.php" class="flex items-center text-blue-700 hover:text-blue-900 hover:bg-blue-50 p-2 rounded-lg transition-colors">
                            <i class="fas fa-upload mr-3 text-blue-500"></i>
                            <span>上傳新作文</span>
                        </a>
                    </li>
                    <li>
                        <a href="my_essays.php" class="flex items-center text-blue-700 hover:text-blue-900 hover:bg-blue-50 p-2 rounded-lg transition-colors">
                            <i class="fas fa-list-alt mr-3 text-blue-500"></i>
                            <span>作文列表</span>
                        </a>
                    </li>
                    <li>
                        <a href="my_essays.php?status=pending" class="flex items-center text-blue-700 hover:text-blue-900 hover:bg-blue-50 p-2 rounded-lg transition-colors">
                            <i class="fas fa-hourglass-half mr-3 text-yellow-500"></i>
                            <span>待批改作文</span>
                        </a>
                    </li>
                    <li>
                        <a href="my_essays.php?status=graded" class="flex items-center text-blue-700 hover:text-blue-900 hover:bg-blue-50 p-2 rounded-lg transition-colors">
                            <i class="fas fa-check-circle mr-3 text-green-500"></i>
                            <span>已批改作文</span>
                        </a>
                    </li>
                    <li>
                        <a href="writing_progress.php" class="flex items-center text-blue-700 hover:text-blue-900 hover:bg-blue-50 p-2 rounded-lg transition-colors">
                            <i class="fas fa-chart-line mr-3 text-purple-500"></i>
                            <span>寫作進度追蹤</span>
                        </a>
                    </li>
                    <li>
                        <a href="honor_board.php" class="flex items-center text-blue-700 hover:text-blue-900 hover:bg-blue-50 p-2 rounded-lg transition-colors">
                            <i class="fas fa-trophy mr-3 text-yellow-500"></i>
                            <span>榮譽榜</span>
                        </a>
                    </li>
                </ul>
                
                <!-- 寫作進度追蹤小組件 -->
                <?php if (count($progress_data) >= 2): ?>
                <div class="mt-6 bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <h3 class="text-sm font-semibold text-purple-800 mb-3">
                        <i class="fas fa-chart-line mr-1"></i>寫作進度概覽
                    </h3>
                    <div class="h-32">
                        <canvas id="progressMiniChart"></canvas>
                    </div>
                    <div class="text-center mt-2">
                        <a href="writing_progress.php" class="text-xs text-blue-600 hover:text-blue-800">
                            查看詳細分析 <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- 最近批改區塊 -->
            <div class="md:col-span-2 bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold text-blue-800 mb-4 flex items-center">
                    <i class="fas fa-history text-blue-500 mr-2"></i>最近批改
                </h2>
                
                <?php if (empty($recent_essays)): ?>
                <div class="bg-blue-50 p-4 rounded-lg text-center">
                    <p class="text-gray-600 mb-4">目前尚無教師批改記錄</p>
                    <a href="upload_essay.php" class="inline-block px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        立即上傳作文
                    </a>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($recent_essays as $essay): ?>
                    <div class="bg-gray-50 p-4 rounded-lg hover:shadow-md transition-shadow border border-gray-200">
                        <div class="flex justify-between items-start">
                            <h3 class="font-medium">
                                <a href="view_essay.php?id=<?php echo $essay['id']; ?>" class="text-blue-600 hover:text-blue-800">
                                    <?php echo htmlspecialchars($essay['title']); ?>
                                </a>
                            </h3>
                            <span class="text-sm bg-green-100 text-green-800 px-2 py-0.5 rounded">
                                教師批改
                            </span>
                        </div>
                        <div class="mt-2 flex justify-between items-center text-sm">
                            <div>
                                <span class="text-gray-600">
                                    分數: <span class="font-bold text-green-600">
                                        <?php echo $essay['score']; ?>
                                    </span>
                                </span>
                                <?php
                                // 顯示作文類型
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
                                $category = isset($categories[$essay['category']]) ? $categories[$essay['category']] : $essay['category'];
                                ?>
                                <span class="text-gray-500 ml-3">
                                    <i class="fas fa-tag text-xs"></i> <?php echo $category; ?>
                                </span>
                            </div>
                            <div class="flex items-center">
                                <?php if ($essay['teacher_name']): ?>
                                <span class="text-gray-500 mr-3">
                                    <i class="fas fa-user text-xs mr-1"></i> <?php echo htmlspecialchars($essay['teacher_name']); ?>
                                </span>
                                <?php endif; ?>
                                <span class="text-gray-500">
                                    <?php 
                                    $time = new DateTime($essay['feedback_time']); 
                                    $now = new DateTime();
                                    $diff = $now->diff($time);
                                    
                                    if ($diff->days == 0) {
                                        if ($diff->h == 0) {
                                            echo $diff->i . " 分鐘前";
                                        } else {
                                            echo $diff->h . " 小時前";
                                        }
                                    } elseif ($diff->days == 1) {
                                        echo "昨天";
                                    } else {
                                        echo date('Y/m/d', strtotime($essay['feedback_time']));
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-6 text-right">
                    <a href="my_essays.php?status=graded" class="inline-flex items-center text-blue-600 hover:text-blue-800">
                        查看所有批改記錄 <i class="fas fa-chevron-right ml-1"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (count($progress_data) >= 2): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 準備圖表數據
        const progressData = {
            labels: [
                <?php foreach ($progress_data as $data): ?>
                "<?php echo htmlspecialchars(substr($data['title'], 0, 10)) . (strlen($data['title']) > 10 ? '...' : ''); ?>",
                <?php endforeach; ?>
            ],
            datasets: [{
                label: '教師評分',
                data: [
                    <?php foreach ($progress_data as $data): ?>
                    <?php echo $data['final_score']; ?>,
                    <?php endforeach; ?>
                ],
                borderColor: '#10B981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.3,
                fill: true
            }]
        };
        
        // 繪製進度迷你圖表
        const progressCtx = document.getElementById('progressMiniChart').getContext('2d');
        new Chart(progressCtx, {
            type: 'line',
            data: progressData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: true
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 100,
                        ticks: {
                            font: {
                                size: 10
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 8
                            }
                        }
                    }
                }
            }
        });
    });
    </script>
    <?php endif; ?>
</body>
</html>