<?php
// 開啟所有錯誤報告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 輸出伺服器和 POST 資訊
echo "<pre>";
print_r($_SERVER);
echo "\n\nPOST Data:\n";
print_r($_POST);
echo "</pre>";
?>

<!DOCTYPE html>
<html>
<body>
    <form action="" method="POST">
        <input type="text" name="test" placeholder="測試">
        <input type="submit" value="提交">
    </form>
</body>
</html>