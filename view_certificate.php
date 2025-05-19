<?php
session_start();
require_once 'db_connection.php';

// 確保只有管理員可以查看證明文件
if(!isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true){
    header("location: login.php");
    exit;
}

// 檢查是否提供使用者ID
if(!isset($_GET['id'])){
    die("未提供使用者ID");
}

$userId = $_GET['id'];

try {
    // 查詢使用者及其證明文件
    $stmt = $conn->prepare("SELECT email, username, teacher_certificate FROM users WHERE id = ? AND user_type = 'teacher_pending'");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$user){
        die("找不到使用者或使用者不在待審核狀態");
    }

    // 完整的檔案路徑
    $fullCertificatePath = 'uploads/teacher_certificates/' . $user['teacher_certificate'];
    
    // 檢查檔案是否實際存在
    if (!file_exists($fullCertificatePath)) {
        die("證明文件不存在：" . $fullCertificatePath);
    }

    $fileExtension = strtolower(pathinfo($fullCertificatePath, PATHINFO_EXTENSION));
} catch(PDOException $e) {
    die("查詢錯誤：" . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>查看教師證明文件</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-2xl">
        <h1 class="text-2xl font-bold mb-6 text-center">教師證明文件審核</h1>
        
        <div class="mb-4">
            <label class="block text-gray-700 font-bold mb-2">教師姓名</label>
            <p class="bg-gray-50 p-2 rounded"><?php echo htmlspecialchars($user['username']); ?></p>
        </div>
        
        <div class="mb-4">
            <label class="block text-gray-700 font-bold mb-2">電子郵件</label>
            <p class="bg-gray-50 p-2 rounded"><?php echo htmlspecialchars($user['email']); ?></p>
        </div>
        
        <div class="mb-6">
            <label class="block text-gray-700 font-bold mb-2">證明文件</label>
            <?php if(in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                <img src="<?php echo htmlspecialchars($fullCertificatePath); ?>" 
                     alt="教師證明文件" 
                     class="max-w-full h-auto rounded-lg shadow-md">
            <?php elseif($fileExtension == 'pdf'): ?>
                <embed src="<?php echo htmlspecialchars($fullCertificatePath); ?>" 
                       type="application/pdf" 
                       width="100%" 
                       height="600px" 
                       class="rounded-lg shadow-md">
            <?php else: ?>
                <p class="text-red-500">不支援的檔案格式</p>
            <?php endif; ?>
        </div>
        
        <div class="flex justify-between">
            <form method="post" action="admin_dashboard.php" class="flex space-x-4">
                <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                <button type="submit" name="action" value="approve" 
                        class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition">
                    通過審核
                </button>
                <button type="submit" name="action" value="reject" 
                        class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition">
                    拒絕申請
                </button>
            </form>
            <a href="admin_dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">
                返回列表
            </a>
        </div>
    </div>
</body>
</html>