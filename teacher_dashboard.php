<?php
session_start();
require_once 'db_connection.php';
// 確保只有教師可以訪問
if(!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] != 'teacher'){
    header("location: login.php");
    exit;
}
// 檢查是否登入
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    // 未登入，重新導向到登入頁面
    header("location: login.php");
    exit;
}

// 增加額外的類型檢查
if($_SESSION["user_type"] == 'student' && basename($_SERVER['PHP_SELF']) == 'teacher_dashboard.php') {
    header("location: student_dashboard.php");
    exit;
}

if($_SESSION["user_type"] == 'teacher' && basename($_SERVER['PHP_SELF']) == 'student_dashboard.php') {
    header("location: teacher_dashboard.php");
    exit;
}
// 獲取待批改作文
$stmt = $conn->prepare("
    SELECT e.*, u.username 
    FROM essays e 
    JOIN users u ON e.user_id = u.id 
    WHERE e.teacher_review = 1 AND e.status = 'pending' 
    ORDER BY e.created_at DESC
");
$stmt->execute();
$pending_essays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 獲取已批改作文
$stmt = $conn->prepare("
    SELECT e.*, u.username 
    FROM essays e 
    JOIN users u ON e.user_id = u.id 
    WHERE e.teacher_feedback IS NOT NULL 
    AND e.status = 'graded' 
    ORDER BY e.feedback_time DESC 
    LIMIT 10
");
$stmt->execute();
$graded_essays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 獲取評分統計
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_graded,
        AVG(score) as avg_score,
        MIN(score) as min_score,
        MAX(score) as max_score,
        AVG(TIMESTAMPDIFF(HOUR, created_at, feedback_time)) as avg_grading_time
    FROM essays
    WHERE teacher_feedback IS NOT NULL AND status = 'graded'
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

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
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>教師面板 - 作文批改平台</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-2xl font-bold text-blue-700">教師面板</h1>
                <div>
                    <a href="logout.php" class="text-red-600 hover:text-red-800 ml-4">
                        <i class="fas fa-sign-out-alt mr-1"></i>登出
                    </a>
                </div>
            </div>
            
            <div class="text-gray-600 mb-6">
                <p>歡迎，<?php echo htmlspecialchars($_SESSION["username"]); ?>！今天是 <?php echo date('Y年m月d日'); ?></p>
            </div>
            
            <!-- 統計信息卡片 -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="font-medium text-blue-800 mb-1">待批改作文</div>
                    <div class="text-2xl font-bold"><?php echo count($pending_essays); ?></div>
                </div>
                
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="font-medium text-green-800 mb-1">已批改作文</div>
                    <div class="text-2xl font-bold"><?php echo $stats['total_graded'] ?: 0; ?></div>
                </div>
                
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <div class="font-medium text-yellow-800 mb-1">平均評分</div>
                    <div class="text-2xl font-bold"><?php echo $stats['avg_score'] ? round($stats['avg_score'], 1) : '-'; ?></div>
                </div>
                
                <div class="bg-purple-50 p-4 rounded-lg">
                    <div class="font-medium text-purple-800 mb-1">平均批改時間</div>
                    <div class="text-2xl font-bold">
                        <?php
                        if ($stats['avg_grading_time']) {
                            $hours = floor($stats['avg_grading_time']);
                            echo $hours . ' 小時';
                        } else {
                            echo '-';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 待批改作文列表 -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">待批改作文</h2>
            
            <?php if (empty($pending_essays)): ?>
            <div class="bg-gray-50 p-4 rounded text-center">
                目前沒有待批改的作文
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-2 px-4 text-left border-b">標題</th>
                            <th class="py-2 px-4 text-left border-b">學生</th>
                            <th class="py-2 px-4 text-left border-b">類型</th>
                            <th class="py-2 px-4 text-left border-b">提交時間</th>
                            <th class="py-2 px-4 text-left border-b">AI評分</th>
                            <th class="py-2 px-4 text-left border-b">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_essays as $essay): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($essay['title']); ?></td>
                            <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($essay['username']); ?></td>
                            <td class="py-2 px-4 border-b">
                                <?php 
                                    $category = $essay['category'];
                                    echo isset($categories[$category]) ? $categories[$category] : $category;
                                ?>
                            </td>
                            <td class="py-2 px-4 border-b"><?php echo date('Y/m/d H:i', strtotime($essay['created_at'])); ?></td>
                            <td class="py-2 px-4 border-b">
                                <?php echo $essay['ai_score'] ? $essay['ai_score'] : '—'; ?>
                            </td>
                            <td class="py-2 px-4 border-b">
                                <a href="teacher_review.php?id=<?php echo $essay['id']; ?>" class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-pencil-alt mr-1"></i>批改
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- 已批改作文列表 -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">最近批改的作文</h2>
            
            <?php if (empty($graded_essays)): ?>
            <div class="bg-gray-50 p-4 rounded text-center">
                您尚未批改任何作文
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-2 px-4 text-left border-b">標題</th>
                            <th class="py-2 px-4 text-left border-b">學生</th>
                            <th class="py-2 px-4 text-left border-b">類型</th>
                            <th class="py-2 px-4 text-left border-b">批改時間</th>
                            <th class="py-2 px-4 text-left border-b">評分</th>
                            <th class="py-2 px-4 text-left border-b">AI評分</th>
                            <th class="py-2 px-4 text-left border-b">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($graded_essays as $essay): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($essay['title']); ?></td>
                            <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($essay['username']); ?></td>
                            <td class="py-2 px-4 border-b">
                                <?php 
                                    $category = $essay['category'];
                                    echo isset($categories[$category]) ? $categories[$category] : $category;
                                ?>
                            </td>
                            <td class="py-2 px-4 border-b"><?php echo date('Y/m/d H:i', strtotime($essay['graded_at'])); ?></td>
                            <td class="py-2 px-4 border-b font-medium"><?php echo $essay['score']; ?></td>
                            <td class="py-2 px-4 border-b text-gray-600">
                                <?php echo $essay['ai_score'] ? $essay['ai_score'] : '—'; ?>
                            </td>
                            <td class="py-2 px-4 border-b">
                                <a href="teacher_review.php?id=<?php echo $essay['id']; ?>" class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-eye mr-1"></i>查看
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>