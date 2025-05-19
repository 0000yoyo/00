<?php
session_start();
require_once 'db_connection.php';
// 手動引入 PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 直接引入 PHPMailer 類別檔案
require 'vendor/phpmailer/phpmailer/src/Exception.php';
require 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/phpmailer/src/SMTP.php';
// 確保只有管理員可以訪問
if(!isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true){
    header("location: login.php");
    exit;
}

// 處理教師申請審核
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])){
    $userId = $_POST['user_id'];
    $action = $_POST['action'];

    try {
if($action == 'approve'){
    // 通過審核
    $stmt = $conn->prepare("UPDATE users SET user_type = 'teacher', admin_verified = 1 WHERE id = ?");
    $stmt->execute([$userId]);

    // 取得使用者信箱
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 發送審核通過信件
    sendApprovalEmail($user['email'], 'approve');

} elseif($action == 'reject'){
    // 拒絕審核
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 發送審核未通過信件
    sendApprovalEmail($user['email'], 'reject');

    // 刪除使用者
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
}
    } catch(PDOException $e) {
        $error = "處理申請時發生錯誤：" . $e->getMessage();
    }
}

// 查詢待審核教師
$stmt = $conn->prepare("SELECT id, email, username, teacher_certificate FROM users WHERE user_type = 'teacher_pending'");
$stmt->execute();
$pendingTeachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Debug - Pending Teachers Count: " . count($pendingTeachers);
foreach($pendingTeachers as $teacher) {
echo "Debug - Teacher: " . print_r($teacher, true);}
function sendApprovalEmail($email, $status) {
    $mail = new PHPMailer(true);

    try {
        $mail->CharSet = 'UTF-8';
        
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mailforproject12345@gmail.com';
        $mail->Password   = 'bmnh forg xofu piez';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('mailforproject12345@gmail.com', 'Essay Platform');
        $mail->addAddress($email);
        $mail->isHTML(true);

        if ($status === 'approve') {
            $mail->Subject = '教師帳號審核通過通知';
            $mail->Body = "
                <p>恭喜您！</p>
                <p>您的教師帳號已通過管理員審核，現在可以登入系統。</p>
                <p>歡迎使用作文批改平台！</p>
            ";
        } else {
            $mail->Subject = '教師帳號審核未通過通知';
            $mail->Body = "
                <p>非常抱歉</p>
                <p>您的教師帳號審核未通過。</p>
                <p>請檢查您提交的證明文件是否符合要求，或聯繫平台管理員詢問詳細原因。</p>
            ";
        }

        return $mail->send();
    } catch (Exception $e) {
        // 記錄錯誤
        error_log("郵件發送錯誤：" . $mail->ErrorInfo, 3, "email_error.log");
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>管理員後台 - 教師審核</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-6">教師申請審核</h1>

        <?php if(!empty($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <?php if(count($pendingTeachers) > 0): ?>
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">電子郵件</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">使用者名稱</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">證明文件</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach($pendingTeachers as $teacher): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($teacher['email']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($teacher['username']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a href="view_certificate.php?id=<?php echo $teacher['id']; ?>" 
                               class="text-blue-600 hover:text-blue-900">查看證明文件</a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <form method="post" class="inline-block">
                                <input type="hidden" name="user_id" value="<?php echo $teacher['id']; ?>">
                                <button type="submit" name="action" value="approve" 
                                        class="text-green-600 hover:text-green-900 mr-4">
                                    通過
                                </button>
                                <button type="submit" name="action" value="reject" 
                                        class="text-red-600 hover:text-red-900">
                                    拒絕
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="bg-white p-4 rounded shadow">
            目前沒有待審核的教師申請
        </div>
        <?php endif; ?>

        <div class="mt-4">
            <a href="logout.php" class="text-blue-600 hover:underline">登出</a>
        </div>
    </div>
<div class="bg-white p-6 rounded-lg shadow">
    <h2 class="text-xl font-semibold mb-4">AI系統管理</h2>
    
    <div class="space-y-4">
        <a href="admin_ai_monitor.php" class="flex items-center text-blue-700 hover:text-blue-900 hover:bg-blue-50 p-2 rounded-lg transition-colors">
            <i class="fas fa-robot mr-3 text-blue-500"></i>
            <span>AI系統監控</span>
        </a>
        
        <a href="model_management.php" class="flex items-center text-blue-700 hover:text-blue-900 hover:bg-blue-50 p-2 rounded-lg transition-colors">
            <i class="fas fa-cogs mr-3 text-blue-500"></i>
            <span>AI模型管理</span>
        </a>
    </div>
</div>
</body>
</html>