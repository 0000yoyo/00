<?php
session_start();
require_once 'db_connection.php';

// 確保只有管理員可以訪問
if(!isset($_SESSION["loggedin"]) || $_SESSION["is_admin"] != 1){
    header("location: login.php");
    exit;
}

// 取得待審核的教師帳號
$stmt = $conn->prepare("SELECT * FROM users WHERE user_type = 'teacher_pending'");
$stmt->execute();
$pending_teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 處理審核
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $teacher_id = $_POST['teacher_id'];
    $action = $_POST['action'];

    if ($action == 'approve') {
        $update_stmt = $conn->prepare("UPDATE users SET user_type = 'teacher', admin_verified = 1 WHERE id = ?");
        $update_stmt->execute([$teacher_id]);
        
        // 可以在這裡發送審核通過的郵件
    } elseif ($action == 'reject') {
        $update_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $update_stmt->execute([$teacher_id]);
    }

    // 重新導向
    header("Location: admin_verify.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>教師帳號審核</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="container mx-auto">
        <h1 class="text-3xl font-bold mb-6">教師帳號審核</h1>
        <?php if (empty($pending_teachers)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                目前沒有待審核的教師帳號
            </div>
        <?php else: ?>
            <div class="bg-white shadow-md rounded">
                <?php foreach ($pending_teachers as $teacher): ?>
                    <div class="p-4 border-b flex justify-between items-center">
                        <div>
                            <h2 class="font-bold"><?php echo htmlspecialchars($teacher['username']); ?></h2>
                            <p><?php echo htmlspecialchars($teacher['email']); ?></p>
                            <a href="view_certificate.php?id=<?php echo $teacher['id']; ?>" target="_blank" class="text-blue-600">查看證明文件</a>
                        </div>
                        <div>
                            <form method="post" class="inline">
                                <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                <button type="submit" name="action" value="approve" class="bg-green-500 text-white px-4 py-2 rounded mr-2">通過</button>
                                <button type="submit" name="action" value="reject" class="bg-red-500 text-white px-4 py-2 rounded">拒絕</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>