<?php
// batch_add_grammar_rules.php
require_once 'db_connection.php';

// 設定執行環境
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 記錄開始時間
$start_time = microtime(true);
$log_messages = ['開始批量添加文法規則 - ' . date('Y-m-d H:i:s')];

// 載入現有規則
$rules_path = __DIR__ . '/models/grammar_rules.pkl';
$backup_path = __DIR__ . '/models/grammar_rules_backup_' . date('Ymd_His') . '.pkl';

// 創建備份
if (file_exists($rules_path)) {
    if (copy($rules_path, $backup_path)) {
        $log_messages[] = "已創建備份文件: {$backup_path}";
    } else {
        $log_messages[] = "警告: 無法創建備份文件: {$backup_path}";
    }
}

try {
    // 讀取現有規則
    $data = file_get_contents($rules_path);
    $grammar_rules = unserialize($data);
    
    if (!$grammar_rules || !isset($grammar_rules['rules'])) {
        throw new Exception("無法解析文法規則文件或格式不正確");
    }
    
    $log_messages[] = "成功載入文法規則";
    
    // 定義新規則
    $new_rules = [
        // 從作文錯誤分析中提取的規則
        
        // 主謂一致性問題 (Subject-Verb Agreement) - 13個錯誤
        ['type' => 'subject_verb_agreement', 'original' => 'I has been dreaming', 'corrected' => 'I have been dreaming'],
        ['type' => 'subject_verb_agreement', 'original' => 'campus are very beautiful', 'corrected' => 'campus is very beautiful'],
        ['type' => 'subject_verb_agreement', 'original' => 'there was so many students', 'corrected' => 'there were so many students'],
        ['type' => 'subject_verb_agreement', 'original' => 'students who comes', 'corrected' => 'students who come'],
        ['type' => 'subject_verb_agreement', 'original' => 'I often goes', 'corrected' => 'I often go'],
        ['type' => 'subject_verb_agreement', 'original' => 'library have', 'corrected' => 'library has'],
        ['type' => 'subject_verb_agreement', 'original' => 'I enjoys borrowing', 'corrected' => 'I enjoy borrowing'],
        ['type' => 'subject_verb_agreement', 'original' => 'I sometimes takes', 'corrected' => 'I sometimes take'],
        ['type' => 'subject_verb_agreement', 'original' => 'roommate usually hangs out', 'corrected' => 'roommate usually hang out'],
        ['type' => 'subject_verb_agreement', 'original' => 'I forgets', 'corrected' => 'I forget'],
        ['type' => 'subject_verb_agreement', 'original' => 'grades wasn\'t', 'corrected' => 'grades weren\'t'],
        ['type' => 'subject_verb_agreement', 'original' => 'students goes', 'corrected' => 'students go'],
        ['type' => 'subject_verb_agreement', 'original' => 'I hopes', 'corrected' => 'I hope'],
        ['type' => 'subject_verb_agreement', 'original' => 'anyone ask me', 'corrected' => 'anyone asks me'],
        
        // 時態錯誤 (Tense) - 6個錯誤
        ['type' => 'tense', 'original' => 'when the weather were nice', 'corrected' => 'when the weather was nice'],
        ['type' => 'tense', 'original' => 'like to explored', 'corrected' => 'like to explore'],
        ['type' => 'tense', 'original' => 'decided to stayed', 'corrected' => 'decided to stay'],
        ['type' => 'tense', 'original' => 'I taked', 'corrected' => 'I took'],
        ['type' => 'tense', 'original' => 'to improved', 'corrected' => 'to improve'],
        ['type' => 'tense', 'original' => 'teached me', 'corrected' => 'taught me'],
        
        // 介詞使用錯誤 (Preposition) - 1個錯誤
        ['type' => 'preposition', 'original' => 'arrived to university', 'corrected' => 'arrived at university'],
        
        // 複數形式錯誤 (Plurals) - 3個錯誤
        ['type' => 'plurals', 'original' => 'I was a children', 'corrected' => 'I was a child'],
        ['type' => 'plurals', 'original' => 'this experiences', 'corrected' => 'this experience'],
        ['type' => 'plurals', 'original' => 'in the futures', 'corrected' => 'in the future'],
        
        // 冠詞使用錯誤 (Article) - 1個錯誤
        ['type' => 'article', 'original' => 'In my first day', 'corrected' => 'On the first day'],
        
        // 詞語選擇不當 (Word Choice) - 2個錯誤
        ['type' => 'word_choice', 'original' => 'was very exciting', 'corrected' => 'was very excited'],
        ['type' => 'word_choice', 'original' => 'manage their time good', 'corrected' => 'manage their time well'],
        
        // 結構問題 (Structure) - 2個錯誤
        ['type' => 'structure', 'original' => 'Although it is difficult sometimes, but', 'corrected' => 'Although it is difficult sometimes'],
        ['type' => 'structure', 'original' => 'I looking forward to', 'corrected' => 'I am looking forward to'],
        
        // 文法錯誤 (Grammar) - 8個錯誤
        ['type' => 'grammar', 'original' => 'I must to finish', 'corrected' => 'I must finish'],
        ['type' => 'grammar', 'original' => 'Me and my roommate', 'corrected' => 'My roommate and I'],
        ['type' => 'grammar', 'original' => 'most hardest thing', 'corrected' => 'hardest thing'],
        ['type' => 'grammar', 'original' => 'trying new restaurants', 'corrected' => 'try new restaurants'],
        ['type' => 'grammar', 'original' => 'always studying until', 'corrected' => 'always study until'],
        ['type' => 'grammar', 'original' => 'didn\'t used to', 'corrected' => 'didn\'t use to'],
        ['type' => 'grammar', 'original' => 'opportunity for me to learning', 'corrected' => 'opportunity for me to learn'],
        ['type' => 'grammar', 'original' => 'don\'t afraid of challenges', 'corrected' => 'don\'t be afraid of challenges'],
        ['type' => 'grammar', 'original' => 'I will tells', 'corrected' => 'I will tell'],
        
        // 以下是原本就有的規則
        
        // 主謂一致性問題 (Subject-Verb Agreement)
        ['type' => 'subject_verb_agreement', 'original' => 'The news are shocking', 'corrected' => 'The news is shocking'],
        ['type' => 'subject_verb_agreement', 'original' => 'Mathematics are difficult', 'corrected' => 'Mathematics is difficult'],
        ['type' => 'subject_verb_agreement', 'original' => 'Every student and teacher have been invited', 'corrected' => 'Every student and teacher has been invited'],
        ['type' => 'subject_verb_agreement', 'original' => 'Two hours are a long time to wait', 'corrected' => 'Two hours is a long time to wait'],
        ['type' => 'subject_verb_agreement', 'original' => 'Her glasses is on the table', 'corrected' => 'Her glasses are on the table'],
        ['type' => 'subject_verb_agreement', 'original' => 'Some of the money are missing', 'corrected' => 'Some of the money is missing'],
        ['type' => 'subject_verb_agreement', 'original' => 'Twenty dollars are not enough', 'corrected' => 'Twenty dollars is not enough'],
        ['type' => 'subject_verb_agreement', 'original' => 'The criteria for success has changed', 'corrected' => 'The criteria for success have changed'],
        ['type' => 'subject_verb_agreement', 'original' => 'Physics are my favorite subject', 'corrected' => 'Physics is my favorite subject'],
        ['type' => 'subject_verb_agreement', 'original' => 'Half of the apples is rotten', 'corrected' => 'Half of the apples are rotten'],

        // 時態使用錯誤 (Tense)
        ['type' => 'tense', 'original' => 'I live here for ten years', 'corrected' => 'I have lived here for ten years'],
        ['type' => 'tense', 'original' => 'She has gone there yesterday', 'corrected' => 'She went there yesterday'],
        ['type' => 'tense', 'original' => 'By the time you get there, I already left', 'corrected' => 'By the time you get there, I will already have left'],
        ['type' => 'tense', 'original' => 'I didn\'t saw him', 'corrected' => 'I didn\'t see him'],
        ['type' => 'tense', 'original' => 'I will told him when he arrives', 'corrected' => 'I will tell him when he arrives'],
        ['type' => 'tense', 'original' => 'He wasn\'t understanding what I meant', 'corrected' => 'He didn\'t understand what I meant'],
        ['type' => 'tense', 'original' => 'They had been working here since 2010 and still do', 'corrected' => 'They have been working here since 2010 and still do'],
        ['type' => 'tense', 'original' => 'What you doing now?', 'corrected' => 'What are you doing now?'],
        ['type' => 'tense', 'original' => 'I\'m believing in ghosts', 'corrected' => 'I believe in ghosts'],
        ['type' => 'tense', 'original' => 'After I will finish, I\'ll call you', 'corrected' => 'After I finish, I\'ll call you'],

        // 冠詞使用錯誤 (Article)
        ['type' => 'article', 'original' => 'I need advice from expert', 'corrected' => 'I need advice from an expert'],
        ['type' => 'article', 'original' => 'Environment is very important', 'corrected' => 'The environment is very important'],
        ['type' => 'article', 'original' => 'Japanese are hardworking', 'corrected' => 'The Japanese are hardworking'],
        ['type' => 'article', 'original' => 'I went to hospital to visit her', 'corrected' => 'I went to the hospital to visit her'],
        ['type' => 'article', 'original' => 'She plays piano very well', 'corrected' => 'She plays the piano very well'],
        ['type' => 'article', 'original' => 'I study history at university', 'corrected' => 'I study history at the university'],
        ['type' => 'article', 'original' => 'We must protect endangered species', 'corrected' => 'We must protect the endangered species'],
        ['type' => 'article', 'original' => 'Unemployment is big problem', 'corrected' => 'Unemployment is a big problem'],
        ['type' => 'article', 'original' => 'He likes playing baseball on weekends', 'corrected' => 'He likes playing baseball on the weekends'],
        ['type' => 'article', 'original' => 'Meeting will start at 9', 'corrected' => 'The meeting will start at 9'],

        // 介詞使用錯誤 (Preposition)
        ['type' => 'preposition', 'original' => 'He is addicted with video games', 'corrected' => 'He is addicted to video games'],
        ['type' => 'preposition', 'original' => 'I agree to your proposal', 'corrected' => 'I agree with your proposal'],
        ['type' => 'preposition', 'original' => 'She is annoyed from the noise', 'corrected' => 'She is annoyed by the noise'],
        ['type' => 'preposition', 'original' => 'We are bored from this lecture', 'corrected' => 'We are bored with this lecture'],
        ['type' => 'preposition', 'original' => 'He died from cancer', 'corrected' => 'He died of cancer'],
        ['type' => 'preposition', 'original' => 'I\'ll take care about it', 'corrected' => 'I\'ll take care of it'],
        ['type' => 'preposition', 'original' => 'She is capable to do it', 'corrected' => 'She is capable of doing it'],
        ['type' => 'preposition', 'original' => 'I\'m concerned of the pollution', 'corrected' => 'I\'m concerned about the pollution'],
        ['type' => 'preposition', 'original' => 'He is dependent from his parents', 'corrected' => 'He is dependent on his parents'],
        ['type' => 'preposition', 'original' => 'She\'s free from work tomorrow', 'corrected' => 'She\'s free of work tomorrow'],

        // 複數形式錯誤 (Plurals)
        ['type' => 'plurals', 'original' => 'There are too many luggages', 'corrected' => 'There is too much luggage'],
        ['type' => 'plurals', 'original' => 'She gave me many advices', 'corrected' => 'She gave me much advice'],
        ['type' => 'plurals', 'original' => 'We need more furnitures', 'corrected' => 'We need more furniture'],
        ['type' => 'plurals', 'original' => 'The childrens are playing', 'corrected' => 'The children are playing'],
        ['type' => 'plurals', 'original' => 'I saw two mouses', 'corrected' => 'I saw two mice'],
        ['type' => 'plurals', 'original' => 'The fishes are swimming', 'corrected' => 'The fish are swimming'],
        ['type' => 'plurals', 'original' => 'Those are nice shoeses', 'corrected' => 'Those are nice shoes'],
        ['type' => 'plurals', 'original' => 'My foots are cold', 'corrected' => 'My feet are cold'],
        ['type' => 'plurals', 'original' => 'Three gooses are in the yard', 'corrected' => 'Three geese are in the yard'],
        ['type' => 'plurals', 'original' => 'I have three homeworks to do', 'corrected' => 'I have three homework assignments to do'],

        // 詞語選擇不當 (Word Choice)
        ['type' => 'word_choice', 'original' => 'I have doubt with his story', 'corrected' => 'I have doubts about his story'],
        ['type' => 'word_choice', 'original' => 'We need to discuss deeply about this', 'corrected' => 'We need to discuss this deeply'],
        ['type' => 'word_choice', 'original' => 'Please be careful from dogs', 'corrected' => 'Please be careful of dogs'],
        ['type' => 'word_choice', 'original' => 'The business is open every days', 'corrected' => 'The business is open every day'],
        ['type' => 'word_choice', 'original' => 'She has an experience of teaching', 'corrected' => 'She has experience in teaching'],
        ['type' => 'word_choice', 'original' => 'He did well on the exam thanks to study hard', 'corrected' => 'He did well on the exam thanks to studying hard'],
        ['type' => 'word_choice', 'original' => 'I hope your family is healthful', 'corrected' => 'I hope your family is healthy'],
        ['type' => 'word_choice', 'original' => 'He entered into the room', 'corrected' => 'He entered the room'],
        ['type' => 'word_choice', 'original' => 'She has an ability of singing well', 'corrected' => 'She has the ability to sing well'],
        ['type' => 'word_choice', 'original' => 'His heart was full of joy and happy', 'corrected' => 'His heart was full of joy and happiness'],

        // 句子結構問題 (Structure)
        ['type' => 'structure', 'original' => 'I don\'t know where does he live', 'corrected' => 'I don\'t know where he lives'],
        ['type' => 'structure', 'original' => 'Because it was raining, so we stayed home', 'corrected' => 'Because it was raining, we stayed home'],
        ['type' => 'structure', 'original' => 'We discussed about many topics', 'corrected' => 'We discussed many topics'],
        ['type' => 'structure', 'original' => 'Let him to go there', 'corrected' => 'Let him go there'],
        ['type' => 'structure', 'original' => 'I want that you come with me', 'corrected' => 'I want you to come with me'],
        ['type' => 'structure', 'original' => 'If I would see him, I would tell him', 'corrected' => 'If I saw him, I would tell him'],
        ['type' => 'structure', 'original' => 'She kept to talk for hours', 'corrected' => 'She kept talking for hours'],
        ['type' => 'structure', 'original' => 'The city which I born in is beautiful', 'corrected' => 'The city in which I was born is beautiful'],
        ['type' => 'structure', 'original' => 'I didn\'t go nowhere yesterday', 'corrected' => 'I didn\'t go anywhere yesterday'],
        ['type' => 'structure', 'original' => 'I\'ll help you always when you need it', 'corrected' => 'I\'ll always help you when you need it'],

        // 拼寫錯誤 (Spelling)
        ['type' => 'spelling', 'original' => 'independant', 'corrected' => 'independent'],
        ['type' => 'spelling', 'original' => 'embarass', 'corrected' => 'embarrass'],
        ['type' => 'spelling', 'original' => 'concious', 'corrected' => 'conscious'],
        ['type' => 'spelling', 'original' => 'adress', 'corrected' => 'address'],
        ['type' => 'spelling', 'original' => 'beleif', 'corrected' => 'belief'],
        ['type' => 'spelling', 'original' => 'foriegn', 'corrected' => 'foreign'],
        ['type' => 'spelling', 'original' => 'existance', 'corrected' => 'existence'],
        ['type' => 'spelling', 'original' => 'knowlege', 'corrected' => 'knowledge'],
        ['type' => 'spelling', 'original' => 'priviledge', 'corrected' => 'privilege'],
        ['type' => 'spelling', 'original' => 'sieze', 'corrected' => 'seize'],

        // 標點符號錯誤 (Punctuation)
        ['type' => 'punctuation', 'original' => 'In conclusion the earth is round', 'corrected' => 'In conclusion, the earth is round'],
        ['type' => 'punctuation', 'original' => 'Its been a long day', 'corrected' => 'It\'s been a long day'],
        ['type' => 'punctuation', 'original' => 'My brothers dog is cute', 'corrected' => 'My brother\'s dog is cute'],
        ['type' => 'punctuation', 'original' => 'The childrens toys', 'corrected' => 'The children\'s toys'],
        ['type' => 'punctuation', 'original' => 'Youre late again', 'corrected' => 'You\'re late again'],
        ['type' => 'punctuation', 'original' => 'The books, are on the shelf', 'corrected' => 'The books are on the shelf'],
        ['type' => 'punctuation', 'original' => 'The teacher says, we should study', 'corrected' => 'The teacher says we should study'],
        ['type' => 'punctuation', 'original' => 'I want to go home she said', 'corrected' => 'I want to go home, she said'],
        ['type' => 'punctuation', 'original' => 'She asked do you like pizza', 'corrected' => 'She asked, "Do you like pizza?"'],
        ['type' => 'punctuation', 'original' => 'I love music,dancing,and singing', 'corrected' => 'I love music, dancing, and singing'],

        // 其他文法問題 (Grammar)
        ['type' => 'grammar', 'original' => 'She would rather stayed at home', 'corrected' => 'She would rather stay at home'],
        ['type' => 'grammar', 'original' => 'He had better to go now', 'corrected' => 'He had better go now'],
        ['type' => 'grammar', 'original' => 'They insisted that he leaves', 'corrected' => 'They insisted that he leave'],
        ['type' => 'grammar', 'original' => 'So difficult the problem was that nobody could solve it', 'corrected' => 'So difficult was the problem that nobody could solve it'],
        ['type' => 'grammar', 'original' => 'Scarcely I had arrived when it started raining', 'corrected' => 'Scarcely had I arrived when it started raining'],
        ['type' => 'grammar', 'original' => 'The sooner we finish, the early we can leave', 'corrected' => 'The sooner we finish, the earlier we can leave'],
        ['type' => 'grammar', 'original' => 'He suggested that we will meet tomorrow', 'corrected' => 'He suggested that we meet tomorrow'],
        ['type' => 'grammar', 'original' => 'She demanded that he apologizes', 'corrected' => 'She demanded that he apologize']
    ];
    
    $added_count = 0;
    $skipped_count = 0;
    
    // 添加新規則
    foreach ($new_rules as $rule_data) {
        $error_type = $rule_data['type'];
        $original = $rule_data['original'];
        $corrected = $rule_data['corrected'];
        
        // 確保該類型存在
        if (!isset($grammar_rules['rules'][$error_type])) {
            $grammar_rules['rules'][$error_type] = [];
        }
        
        // 檢查是否已存在相同規則
        $rule_exists = false;
        foreach ($grammar_rules['rules'][$error_type] as &$rule) {
            if ($rule['original'] === $original) {
                // 更新現有規則
                if (!in_array($corrected, $rule['corrected'])) {
                    $rule['corrected'][] = $corrected;
                }
                $rule['count'] = isset($rule['count']) ? $rule['count'] + 1 : 1;
                $rule_exists = true;
                $skipped_count++;
                break;
            }
        }
        
        // 如果規則不存在，添加新規則
        if (!$rule_exists) {
            $grammar_rules['rules'][$error_type][] = [
                'original' => $original,
                'corrected' => [$corrected],
                'count' => 2, // 給予較高的初始權重
                'examples' => [],
                'weight' => 2
            ];
            $added_count++;
        }
    }
    
    // 保存更新後的規則
    if (file_put_contents($rules_path, serialize($grammar_rules)) !== false) {
        $log_messages[] = "成功添加 {$added_count} 條新規則";
        if ($skipped_count > 0) {
            $log_messages[] = "跳過 {$skipped_count} 條已存在的規則";
        }
    } else {
        $log_messages[] = "錯誤: 無法保存更新後的文法規則";
    }
    
} catch (Exception $e) {
    $log_messages[] = "錯誤: " . $e->getMessage();
}

// 記錄執行時間
$execution_time = microtime(true) - $start_time;
$log_messages[] = "添加完成，耗時: " . round($execution_time, 2) . " 秒";

// 輸出日誌
foreach ($log_messages as $message) {
    echo $message . "<br>\n";
}

// 保存到日誌文件
$log_file = __DIR__ . '/logs/batch_add_rules_' . date('Ymd') . '.log';
$log_dir = dirname($log_file);

if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}

file_put_contents($log_file, implode("\n", $log_messages) . "\n\n", FILE_APPEND);
?>