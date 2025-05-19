<?php
// 資料庫連接配置
$host = 'localhost';     // 資料庫主機
$db_name = 'project';    // 資料庫名稱
$username = 'root';      // 資料庫使用者名稱
$password = '';          // 資料庫密碼

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    
    // 設定 PDO 錯誤模式為拋出例外
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 設定時區（可選）
    $conn->exec("SET time_zone = '+00:00'");
} catch(PDOException $e) {
    // 輸出詳細的連接錯誤
    die("連接失敗: " . $e->getMessage());
}
?>