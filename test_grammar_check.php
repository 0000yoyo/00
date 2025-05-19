<?php
// test_grammar_check.php
require_once 'enhanced_feedback.py'; // 這裡需要一個 PHP 到 Python 的橋接器

$test_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_text'])) {
    $test_text = $_POST['test_text'];
    // 執行 Python 腳本進行文法檢測
    $command = "python -c \"
import sys
sys.path.append('.')
from enhanced_feedback import check_grammar_issues
import json
print(json.dumps(check_grammar_issues('''" . addslashes($test_text) . "''')))
\"";
    
    $output = shell_exec($command);
    $test_result = json_decode($output, true);
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>文法檢測測試</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body class="container py-5">
    <h1>文法檢測測試</h1>
    
    <form method="post" class="mb-4">
        <div class="form-group">
            <label for="test_text">輸入測試文本</label>
            <textarea name="test_text" id="test_text" class="form-control" rows="5" required><?php echo isset($_POST['test_text']) ? htmlspecialchars($_POST['test_text']) : ''; ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">測試文法檢測</button>
    </form>
    
    <?php if ($test_result !== null): ?>
        <div class="card">
            <div class="card-header">檢測結果</div>
            <div class="card-body">
                <?php if (empty($test_result)): ?>
                    <div class="alert alert-success">未發現文法問題</div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <h5>發現以下文法問題：</h5>
                        <ul>
                            <?php foreach ($test_result as $type => $issues): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($type); ?>:</strong>
                                    <ul>
                                        <?php foreach ($issues as $issue): ?>
                                            <li><?php echo htmlspecialchars($issue); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>