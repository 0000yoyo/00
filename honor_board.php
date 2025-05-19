<?php
session_start();
require_once 'db_connection.php';

// 確保用戶已登入
if(!isset($_SESSION["loggedin"])){
    header("location: login.php");
    exit;
}

// 獲取優秀作文 (由教師評分90分以上)
$stmt = $conn->prepare("
    SELECT e.*, u.username, 
    CASE WHEN e.anonymous = 1 THEN '匿名' ELSE u.username END AS display_name
    FROM essays e 
    JOIN users u ON e.user_id = u.id 
    WHERE e.allow_public = 1 
    AND e.status = 'graded' 
    AND e.score >= 90
    ORDER BY e.score DESC, e.created_at DESC
    LIMIT 10
");
$stmt->execute();
$outstanding_essays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 獲取傑出寫作者 (有3篇以上作文且平均分80分以上)
$stmt = $conn->prepare("
    SELECT u.id, u.username, COUNT(e.id) as essay_count, 
    ROUND(AVG(e.score), 1) as avg_score
    FROM users u
    JOIN essays e ON u.id = e.user_id
    WHERE e.status = 'graded'
    GROUP BY u.id, u.username
    HAVING COUNT(e.id) >= 3 AND AVG(e.score) >= 80
    ORDER BY avg_score DESC, essay_count DESC
    LIMIT 10
");
$stmt->execute();
$outstanding_writers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 獲取進步獎 (最近一篇作文比前一篇提升10分以上)
$stmt = $conn->prepare("
    SELECT e1.id, e1.title, u.username, 
    CASE WHEN e1.anonymous = 1 THEN '匿名' ELSE u.username END AS display_name,
    e1.score, e2.score as previous_score,
    (e1.score - e2.score) as improvement
    FROM essays e1
    JOIN essays e2 ON e1.user_id = e2.user_id 
    JOIN users u ON e1.user_id = u.id
    WHERE e1.status = 'graded' 
    AND e2.status = 'graded'
    AND e1.created_at > e2.created_at
    AND e1.allow_public = 1
    AND (e1.score - e2.score) >= 10
    AND e1.id != e2.id
    AND NOT EXISTS (
        SELECT 1 FROM essays e3 
        WHERE e3.user_id = e1.user_id 
        AND e3.created_at > e2.created_at 
        AND e3.created_at < e1.created_at
    )
    ORDER BY improvement DESC, e1.created_at DESC
    LIMIT 10
");
$stmt->execute();
$improved_essays = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>榮譽榜 - 作文批改平台</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
    <div class="max-w-6xl mx-auto bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-blue-700">榮譽榜</h1>
            <a href="<?php echo $_SESSION['user_type'] == 'student' ? 'student_dashboard.php' : 'teacher_dashboard.php'; ?>" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-1"></i>返回首頁
            </a>
        </div>

        <!-- 本月優秀作文 -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">本月優秀作文</h2>
            
            <?php if (empty($outstanding_essays)): ?>
                <div class="bg-yellow-50 rounded-lg p-4 text-center">
                    <p class="text-gray-600">尚無優秀作文記錄</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($outstanding_essays as $essay): ?>
                        <div class="bg-yellow-50 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="font-bold text-yellow-800"><?php echo htmlspecialchars($essay['title']); ?></h3>
                                <span class="px-2 py-1 bg-yellow-200 text-yellow-800 rounded-full text-xs font-bold">
                                    <?php echo $essay['score']; ?>分
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 mb-2">
                                作者: <?php echo htmlspecialchars($essay['display_name']); ?> | 
                                類型: <?php echo isset($categories[$essay['category']]) ? $categories[$essay['category']] : $essay['category']; ?>
                            </p>
                            <div class="text-right">
                                <a href="view_honor_essay.php?id=<?php echo $essay['id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                                    <i class="fas fa-book-open mr-1"></i>閱讀全文
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 傑出寫作者 -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">傑出寫作者</h2>
            
            <?php if (empty($outstanding_writers)): ?>
                <div class="bg-blue-50 rounded-lg p-4 text-center">
                    <p class="text-gray-600">尚無傑出寫作者記錄</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead class="bg-blue-50">
                            <tr>
                                <th class="py-2 px-4 text-left border-b">排名</th>
                                <th class="py-2 px-4 text-left border-b">學生</th>
                                <th class="py-2 px-4 text-center border-b">完成作文數</th>
                                <th class="py-2 px-4 text-center border-b">平均分數</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($outstanding_writers as $index => $writer): ?>
                                <tr class="hover:bg-blue-50">
                                    <td class="py-2 px-4 border-b font-bold text-blue-800"><?php echo $index + 1; ?></td>
                                    <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($writer['username']); ?></td>
                                    <td class="py-2 px-4 text-center border-b"><?php echo $writer['essay_count']; ?> 篇</td>
                                    <td class="py-2 px-4 text-center border-b font-bold"><?php echo $writer['avg_score']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- 進步獎 -->
        <div>
            <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">進步獎</h2>
            
            <?php if (empty($improved_essays)): ?>
                <div class="bg-green-50 rounded-lg p-4 text-center">
                    <p class="text-gray-600">尚無進步獎記錄</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($improved_essays as $essay): ?>
                        <div class="bg-green-50 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="font-bold text-green-800"><?php echo htmlspecialchars($essay['title']); ?></h3>
                                <div>
                                    <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs mr-1">
                                        <?php echo $essay['previous_score']; ?>分
                                    </span>
                                    <i class="fas fa-arrow-right text-gray-500 mx-1"></i>
                                    <span class="px-2 py-1 bg-green-200 text-green-800 rounded-full text-xs font-bold">
                                        <?php echo $essay['score']; ?>分
                                    </span>
                                </div>
                            </div>
                            <p class="text-sm text-gray-600 mb-2">
                                作者: <?php echo htmlspecialchars($essay['display_name']); ?> |
                                進步: <span class="font-bold text-green-600">+<?php echo $essay['improvement']; ?>分</span>
                            </p>
                            <div class="text-right">
                                <a href="view_honor_essay.php?id=<?php echo $essay['id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                                    <i class="fas fa-book-open mr-1"></i>閱讀全文
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>