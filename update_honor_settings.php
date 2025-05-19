<?php
session_start();
require_once 'db_connection.php';

// 確保只有學生可以訪問
if(!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] != 'student'){
    header("location: login.php");
    exit;
}

// 檢查是否是POST請求且提供了作文ID
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['essay_id'])) {
    $essay_id = intval($_POST['essay_id']);
    $allow_public = isset($_POST['allow_public']) ? 1 : 0;
    $anonymous = isset($_POST['anonymous']) ? 1 : 0;
    
    try {
        // 先檢查這篇作文是否屬於當前用戶且已被批改
        $stmt = $conn->prepare("SELECT user_id, status FROM essays WHERE id = ?");
        $stmt->execute([$essay_id]);
        $essay = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($essay && $essay['user_id'] == $_SESSION['id']) {
            // 只有已批改的作文才能設置榮譽榜選項
            if ($essay['status'] == 'graded') {
                $stmt = $conn->prepare("UPDATE essays SET allow_public = ?, anonymous = ? WHERE id = ?");
                $stmt->execute([$allow_public, $anonymous, $essay_id]);
                
                $_SESSION['success_message'] = "榮譽榜設置已更新！";
            } else {
                $_SESSION['error_message'] = "只有已批改完成的作文才能設置榮譽榜選項。";
            }
        } else {
            $_SESSION['error_message'] = "您沒有權限修改這篇作文的設置。";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "更新設置時發生錯誤：" . $e->getMessage();
    }
}

// 重定向回作文列表頁面
header("Location: my_essays.php");
exit;
?>