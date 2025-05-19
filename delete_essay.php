<?php
session_start();
// 確保只有學生可以訪問
if(!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] != 'student'){
    header("location: login.php");
    exit;
}

require_once 'db_connection.php';

// 檢查是否是POST請求且提供了作文ID
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['essay_id'])) {
    $essay_id = $_POST['essay_id'];
    
    try {
        // 先檢查這篇作文是否屬於當前用戶且是否已被老師批改
        $stmt = $conn->prepare("SELECT user_id, teacher_review, status FROM essays WHERE id = ?");
        $stmt->execute([$essay_id]);
        $essay = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 檢查是否是當前用戶的作文且未被老師批改
        if ($essay && $essay['user_id'] == $_SESSION['id']) {
            // 檢查是否已被老師批改
            if ($essay['teacher_review'] && $essay['status'] == 'graded') {
                $_SESSION['error_message'] = "此作文已被老師批改，無法刪除。";
            } else {
                // 刪除作文
                $stmt = $conn->prepare("DELETE FROM essays WHERE id = ?");
                $stmt->execute([$essay_id]);
                
                // 設置成功訊息
                $_SESSION['success_message'] = "作文已成功刪除！";
            }
        } else {
            // 設置錯誤訊息
            $_SESSION['error_message'] = "您沒有權限刪除這篇作文。";
        }
    } catch (PDOException $e) {
        // 設置錯誤訊息
        $_SESSION['error_message'] = "刪除作文時發生錯誤：" . $e->getMessage();
    }
}

// 重定向回作文列表頁面
header("Location: my_essays.php");
exit;
?>