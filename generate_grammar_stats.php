<?php
// 創建 generate_grammar_stats.php

require_once 'db_connection.php';

// 設定執行環境
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300);

$log_messages = ['開始生成文法規則統計報告 - ' . date('Y-m-d H:i:s')];

try {
    // 1. 獲取文法規則使用情況
    $grammar_rules_path = __DIR__ . '/models/grammar_rules.pkl';
    $grammar_rules = [];
    
    if (file_exists($grammar_rules_path)) {
        $temp_json_path = __DIR__ . '/temp/temp_rules_' . uniqid() . '.json';
        
        if (!file_exists(__DIR__ . '/temp')) {
            mkdir(__DIR__ . '/temp', 0755, true);
        }
        
        $cmd = "python " . escapeshellarg(__DIR__ . '/scripts/read_grammar_rules.py') . 
               " --input " . escapeshellarg($grammar_rules_path) . 
               " --output " . escapeshellarg($temp_json_path);
        
        exec($cmd, $output, $return_var);
        
        if ($return_var === 0 && file_exists($temp_json_path)) {
            $grammar_rules = json_decode(file_get_contents($temp_json_path), true);
            @unlink($temp_json_path);
            $log_messages[] = '成功讀取文法規則';
        } else {
            $log_messages[] = '讀取文法規則失敗';
        }
    }
    
    // 2. 生成統計數據
    $stats = [
        'rule_count' => 0,
        'type_stats' => [],
        'top_rules' => []
    ];
    
    // 按類型統計規則數量
    if (isset($grammar_rules['rules'])) {
        foreach ($grammar_rules['rules'] as $type => $rules) {
            $rule_count = count($rules);
            $stats['rule_count'] += $rule_count;
            
            $stats['type_stats'][$type] = [
                'count' => $rule_count,
                'description' => $grammar_rules['descriptions'][$type] ?? $type
            ];
            
            // 收集使用次數最多的規則
            foreach ($rules as $rule) {
                $stats['top_rules'][] = [
                    'type' => $type,
                    'original' => $rule['original'],
                    'corrected' => implode(', ', $rule['corrected'] ?? []),
                    'count' => $rule['count'] ?? 0
                ];
            }
        }
    }
    
    // 排序使用次數最多的規則
    usort($stats['top_rules'], function($a, $b) {
        return $b['count'] - $a['count'];
    });
    
    // 只保留前15個最常用規則
    $stats['top_rules'] = array_slice($stats['top_rules'], 0, 15);
    
    // 3. 獲取反饋統計
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN feedback_type = 'missed_issue' THEN 1 ELSE 0 END) as missed_issues,
            SUM(CASE WHEN feedback_type = 'false_positive' THEN 1 ELSE 0 END) as false_positives,
            SUM(CASE WHEN feedback_type = 'general' THEN 1 ELSE 0 END) as general_feedback,
            DATE_FORMAT(created_at, '%Y-%m-%d') as date,
            processed
        FROM ai_grammar_feedback
        GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d'), processed
        ORDER BY date DESC
        LIMIT 30
    ");
    $stmt->execute();
    $feedback_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 4. 評分準確度統計
    $stmt = $conn->prepare("
        SELECT 
            AVG(ABS(score - ai_score)) as avg_score_diff,
            MIN(ABS(score - ai_score)) as min_score_diff,
            MAX(ABS(score - ai_score)) as max_score_diff,
            COUNT(*) as count
        FROM essays
        WHERE teacher_review = 1 
        AND status = 'graded' 
        AND ai_score IS NOT NULL 
        AND score IS NOT NULL
    ");
    $stmt->execute();
    $score_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 5. 生成HTML報告
    $report_date = date('Y-m-d');
    $report_content = <<<HTML
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>文法規則統計報告 - {$report_date}</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6;
            color: #333;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2, h3 { color: #2c5282; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }
        .stats-card {
            background-color: #f0f5ff;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stats-card h3 {
            margin-top: 0;
            border-bottom: 1px solid #cbd5e0;
            padding-bottom: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            text-align: left;
        }
        th {
            background-color: #edf2f7;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .type-bar {
            display: flex;
            height: 30px;
            margin-bottom: 20px;
        }
        .type-segment {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            overflow: hidden;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <h1>文法規則統計報告</h1>
    <p>報告生成時間: {$report_date}</p>
    
    <div class="stats-grid">
        <div class="stats-card">
            <h3>文法規則總數</h3>
            <p style="font-size: 24px; font-weight: bold;">{$stats['rule_count']}</p>
        </div>
        
        <div class="stats-card">
            <h3>AI與教師評分差異</h3>
            <p>平均差異: {$score_stats['avg_score_diff']} 分</p>
            <p>最大差異: {$score_stats['max_score_diff']} 分</p>
            <p>樣本數量: {$score_stats['count']} 篇作文</p>
        </div>
    </div>
    
    <h2>文法規則類型分布</h2>
HTML;

    // 生成文法規則類型分布圖
    if (!empty($stats['type_stats'])) {
        $total_rules = $stats['rule_count'];
        $type_bar = '<div class="type-bar">';
        $type_legend = '<div style="margin-top: 10px;">';
        
        $colors = [
            'tense' => '#3182ce',
            'subject_verb_agreement' => '#e53e3e',
            'article' => '#38a169',
            'plurals' => '#d69e2e',
            'preposition' => '#805ad5',
            'word_choice' => '#dd6b20',
            'spelling' => '#718096',
            'other' => '#4a5568'
        ];
        
        foreach ($stats['type_stats'] as $type => $type_data) {
            $percentage = ($total_rules > 0) ? round(($type_data['count'] / $total_rules) * 100) : 0;
            $color = $colors[$type] ?? $colors['other'];
            
            if ($percentage > 0) {
                $type_bar .= "<div class='type-segment' style='width: {$percentage}%; background-color: {$color};'>{$percentage}%</div>";
                $type_legend .= "<div style='display: flex; align-items: center; margin-bottom: 5px;'>
                    <div style='width: 15px; height: 15px; background-color: {$color}; margin-right: 5px;'></div>
                    <span>{$type_data['description']} ({$type_data['count']})</span>
                </div>";
            }
        }
        
        $type_bar .= '</div>';
        $type_legend .= '</div>';
        
        $report_content .= $type_bar . $type_legend;
    }
    
    // 常用文法規則
    $report_content .= <<<HTML
    <h2>最常用文法規則</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>類型</th>
                <th>錯誤表達</th>
                <th>正確表達</th>
                <th>使用次數</th>
            </tr>
        </thead>
        <tbody>
HTML;
    
    foreach ($stats['top_rules'] as $i => $rule) {
        $type_description = $grammar_rules['descriptions'][$rule['type']] ?? $rule['type'];
        $report_content .= <<<HTML
            <tr>
                <td>{$i}</td>
                <td>{$type_description}</td>
                <td>{$rule['original']}</td>
                <td>{$rule['corrected']}</td>
                <td>{$rule['count']}</td>
            </tr>
HTML;
    }
    
    $report_content .= <<<HTML
        </tbody>
    </table>
    
    <h2>教師反饋統計</h2>
    <table>
        <thead>
            <tr>
                <th>日期</th>
                <th>未識別問題</th>
                <th>誤報問題</th>
                <th>一般建議</th>
                <th>總計</th>
                <th>處理狀態</th>
            </tr>
        </thead>
        <tbody>
HTML;

    foreach ($feedback_stats as $stats) {
        $status = $stats['processed'] ? '已處理' : '待處理';
        $report_content .= <<<HTML
            <tr>
                <td>{$stats['date']}</td>
                <td>{$stats['missed_issues']}</td>
                <td>{$stats['false_positives']}</td>
                <td>{$stats['general_feedback']}</td>
                <td>{$stats['total']}</td>
                <td>{$status}</td>
            </tr>
HTML;
    }
    
    $report_content .= <<<HTML
        </tbody>
    </table>
    
    <p>報告結束 - 生成於 {$report_date}</p>
</body>
</html>
HTML;
    
    // 6. 保存報告
    $reports_dir = __DIR__ . '/reports';
    if (!file_exists($reports_dir)) {
        mkdir($reports_dir, 0755, true);
    }
    
    $report_file = $reports_dir . '/grammar_stats_' . date('Ymd') . '.html';
    file_put_contents($report_file, $report_content);
    
    $log_messages[] = "報告已生成並保存到 {$report_file}";
    
} catch (Exception $e) {
    $log_messages[] = '執行過程中發生錯誤: ' . $e->getMessage();
}

// 輸出日誌
$log_file = __DIR__ . '/logs/grammar_stats_' . date('Ymd') . '.log';
$log_dir = dirname($log_file);
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}
file_put_contents($log_file, implode("\n", $log_messages) . "\n\n", FILE_APPEND);

echo implode("\n", $log_messages);
?>