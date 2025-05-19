<?php
session_start();
require_once 'db_connection.php';

// 確保用戶已登入
if(!isset($_SESSION["loggedin"])){
    header("location: login.php");
    exit;
}

$essay_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 查詢作文信息，只查詢公開的作文
$stmt = $conn->prepare("
    SELECT e.*, 
    CASE WHEN e.anonymous = 1 THEN '匿名' ELSE u.username END AS display_name, 
    u.username AS real_username
    FROM essays e 
    JOIN users u ON e.user_id = u.id 
    WHERE e.id = ? AND e.allow_public = 1 AND e.status = 'graded'
");
$stmt->execute([$essay_id]);
$essay = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$essay) {
    // 作文不存在或不公開
    $_SESSION['error_message'] = "找不到指定的作文或該作文不公開";
    header("location: honor_board.php");
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
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($essay['title']); ?> - 榮譽榜作文</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-blue-700">榮譽榜作文</h1>
            <a href="honor_board.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-1"></i>返回榮譽榜
            </a>
        </div>

        <div class="mb-6">
            <div class="flex justify-between items-center mb-2">
                <h2 class="text-xl font-semibold"><?php echo htmlspecialchars($essay['title']); ?></h2>
                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
                    <?php echo $category_name; ?>
                </span>
            </div>
            <div class="text-gray-600 text-sm mb-4">
                <span>作者: <?php echo htmlspecialchars($essay['display_name']); ?></span>
                <span class="mx-2">|</span>
                <span>提交時間: <?php echo date('Y/m/d H:i', strtotime($essay['created_at'])); ?></span>
                <span class="mx-2">|</span>
                <span>評分: <span class="font-bold"><?php echo $essay['score']; ?></span>/100</span>
            </div>
            
            <!-- 作文內容 -->
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 prose max-w-none mb-4">
                <?php echo nl2br(htmlspecialchars($essay['content'])); ?>
            </div>
            
            <!-- 教師評語 -->
            <div class="mt-6">
                <h3 class="text-lg font-semibold mb-3 pb-1 border-b border-gray-200">教師評語</h3>
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <?php echo nl2br(htmlspecialchars($essay['teacher_feedback'])); ?>
                </div>
            </div>
            
            <!-- AI評語 (如果有) -->
            <?php if (!empty($essay['ai_feedback'])): ?>
            <div class="mt-6">
                <h3 class="text-lg font-semibold mb-3 pb-1 border-b border-gray-200">AI評語</h3>
                <div class="bg-blue-50 p-4 rounded-lg">
                    <?php echo nl2br(htmlspecialchars($essay['ai_feedback'])); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 文法分析 (如果有) -->
            <?php if (!empty($essay['teacher_grammar_feedback'])): ?>
            <div class="mt-6">
                <h3 class="text-lg font-semibold mb-3 pb-1 border-b border-gray-200">文法分析</h3>
                <?php
                $grammar_feedback = json_decode($essay['teacher_grammar_feedback'], true);
                if ($grammar_feedback):
                ?>
                <div class="bg-blue-50 p-4 rounded-lg">
                    <?php
                    $missed_issues = array_filter($grammar_feedback, function($item) {
                        return $item['type'] == 'missed';
                    });
                    
                    if (!empty($missed_issues)):
                    ?>
                    <div class="mb-4">
                        <h4 class="font-medium text-blue-800 mb-2">文法錯誤:</h4>
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
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>