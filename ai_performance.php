// 建議創建 ai_performance.php 檔案來呈現系統學習成效
<?php
session_start();
require_once 'db_connection.php';

// 確保只有管理員可以訪問
if(!isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true){
    header("location: login.php");
    exit;
}

// 獲取反饋統計
$stmt = $conn->prepare("
    SELECT 
        feedback_type, 
        COUNT(*) as count,
        DATE_FORMAT(created_at, '%Y-%m') as month
    FROM ai_grammar_feedback
    GROUP BY feedback_type, month
    ORDER BY month DESC, feedback_type
");
$stmt->execute();
$feedback_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 獲取規則更新統計
// 這部分需要根據您的實際數據結構來實現
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>AI系統學習表現分析</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- 儀表板內容 -->
</body>
</html>