<?php
// model_management.php
session_start();
require_once 'db_connection.php';
require_once 'model_trainer.php';

// 確保只有管理員可以訪問
if(!isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true){
    header("location: login.php");
    exit;
}

// 初始化 ModelTrainer
$trainer = new ModelTrainer();

// 處理訓練模型請求
$training_result = null;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'train') {
    // 處理上傳的資料集文件
    $upload_success = false;
    $data_file = "";
    
    if (isset($_FILES['dataset']) && $_FILES['dataset']['error'] == 0) {
        $upload_dir = __DIR__ . '/data/essays/';
        
        // 確保目錄存在
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $tmp_name = $_FILES['dataset']['tmp_name'];
        $name = basename($_FILES['dataset']['name']);
        $data_file = $name;
        
        // 移動上傳的文件
        if (move_uploaded_file($tmp_name, $upload_dir . $name)) {
            $upload_success = true;
        }
    }
    
    if ($upload_success || !empty($_POST['existing_data'])) {
        // 如果沒有上傳新文件但選擇了現有文件
        if (!$upload_success && !empty($_POST['existing_data'])) {
            $data_file = $_POST['existing_data'];
        }
        
        // 從表單獲取訓練參數
        $essay_set = isset($_POST['essay_set']) ? intval($_POST['essay_set']) : 1;
        $version = !empty($_POST['version']) ? $_POST['version'] : '1.0.0';
        
        // 訓練模型
        $training_result = $trainer->trainModel($data_file, $essay_set, $version);
        
        // 如果訓練成功且有訓練記錄，保存到資料庫
        if ($training_result['success'] && isset($training_result['training_info'])) {
            $info = $training_result['training_info'];
            
            try {
                $stmt = $conn->prepare("
                    INSERT INTO model_training 
                    (model_version, training_date, dataset_size, accuracy, notes) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $info['model_version'],
                    $info['training_date'],
                    isset($info['dataset_size']) ? $info['dataset_size'] : 0,
                    $info['kappa'],
                    "Trained on essay_set {$info['essay_set']}"
                ]);
                
                $training_result['db_saved'] = true;
            } catch (PDOException $e) {
                $training_result['db_error'] = $e->getMessage();
            }
        }
    } else {
        $training_result = [
            'success' => false,
            'error' => '上傳資料集失敗'
        ];
    }
}

// 獲取可用的模型
$available_models = $trainer->getAvailableModels();

// 獲取資料目錄中的數據集文件
$data_dir = __DIR__ . '/data/essays/';
$data_files = [];

if (file_exists($data_dir)) {
    $files = scandir($data_dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && is_file($data_dir . $file)) {
            $data_files[] = $file;
        }
    }
}

// 獲取資料庫中的訓練記錄
$training_records = [];
try {
    $stmt = $conn->prepare("
        SELECT * FROM model_training 
        ORDER BY training_date DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $training_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>模型管理 - 作文批改平台</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
    <div class="max-w-6xl mx-auto bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-blue-700">AI 模型管理</h1>
            <a href="admin_dashboard.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-1"></i>返回管理面板
            </a>
        </div>

        <?php if ($training_result): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $training_result['success'] ? 'bg-green-100' : 'bg-red-100'; ?>">
            <h2 class="text-lg font-semibold mb-2">
                <?php echo $training_result['success'] ? '訓練成功' : '訓練失敗'; ?>
            </h2>
            <pre class="bg-gray-800 text-white p-3 rounded overflow-auto text-sm"><?php echo $training_result['output']; ?></pre>
            
            <?php if ($training_result['success'] && isset($training_result['training_info'])): ?>
            <div class="mt-4 p-3 bg-blue-50 rounded">
                <h3 class="font-medium">訓練詳情:</h3>
                <ul class="mt-2 space-y-1">
                    <li>模型版本: <?php echo $training_result['training_info']['model_version']; ?></li>
                    <li>訓練日期: <?php echo $training_result['training_info']['training_date']; ?></li>
                    <li>Essay Set: <?php echo $training_result['training_info']['essay_set']; ?></li>
                    <li>RMSE: <?php echo round($training_result['training_info']['rmse'], 4); ?></li>
                    <li>Kappa: <?php echo round($training_result['training_info']['kappa'], 4); ?></li>
                </ul>
                
                <?php if (isset($training_result['db_saved']) && $training_result['db_saved']): ?>
                <p class="text-green-600 mt-2">訓練記錄已保存到資料庫</p>
                <?php elseif (isset($training_result['db_error'])): ?>
                <p class="text-red-600 mt-2">保存到資料庫失敗: <?php echo $training_result['db_error']; ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- 訓練新模型 -->
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-semibold mb-4">訓練新模型</h2>
                
                <form method="post" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="train">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">上傳資料集 (TSV 格式)</label>
                        <input type="file" name="dataset" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    
                    <?php if (!empty($data_files)): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">或選擇現有資料集</label>
                        <select name="existing_data" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="">-- 選擇現有資料集 --</option>
                            <?php foreach ($data_files as $file): ?>
                            <option value="<?php echo $file; ?>"><?php echo $file; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Essay Set ID</label>
                        <input type="number" name="essay_set" value="1" min="1" max="10" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <p class="text-xs text-gray-500 mt-1">指定要使用哪個 Essay Set 的數據進行訓練</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">模型版本</label>
                        <input type="text" name="version" value="1.0.0" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md"
                               pattern="[0-9]+\.[0-9]+\.[0-9]+">
                        <p class="text-xs text-gray-500 mt-1">遵循 major.minor.patch 格式</p>
                    </div>
                    
                    <div>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            開始訓練
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- 可用模型 -->
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-semibold mb-4">可用模型</h2>
                
                <?php if (empty($available_models)): ?>
                <div class="p-4 bg-gray-50 rounded text-center">
                    目前沒有可用的模型
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">版本</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">建立日期</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">準確度</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($available_models as $model): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo $model['version']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo $model['created']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if (isset($model['training_info'])): ?>
                                    Kappa: <?php echo round($model['training_info']['kappa'], 4); ?>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if (isset($model['training_info'])): ?>
                                    <button class="text-blue-600 hover:text-blue-800" 
                                            onclick="showModelDetails('<?php echo addslashes(json_encode($model)); ?>')">
                                        詳情
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 訓練記錄 -->
        <div class="mt-6 bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold mb-4">訓練記錄</h2>
            
            <?php if (empty($training_records)): ?>
            <div class="p-4 bg-gray-50 rounded text-center">
                目前沒有訓練記錄
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">版本</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">訓練日期</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">資料集大小</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">準確度</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">備註</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
					<?php foreach ($training_records as $record): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $record['id']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $record['model_version']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $record['training_date']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $record['dataset_size']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo round($record['accuracy'], 4); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $record['notes']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 模型詳情對話框 -->
    <div id="model-details-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full">
            <h3 class="text-lg font-bold mb-4">模型詳情</h3>
            <div id="model-details-content" class="mb-6"></div>
            <div class="flex justify-end">
                <button id="close-modal" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
                    關閉
                </button>
            </div>
        </div>
    </div>
    
    <script>
    function showModelDetails(modelJson) {
        const model = JSON.parse(modelJson);
        const modal = document.getElementById('model-details-modal');
        const content = document.getElementById('model-details-content');
        
        // 創建HTML內容
        let html = '<div class="space-y-2">';
        html += `<p><strong>模型版本:</strong> ${model.version}</p>`;
        html += `<p><strong>檔案路徑:</strong> ${model.path}</p>`;
        html += `<p><strong>建立日期:</strong> ${model.created}</p>`;
        
        if (model.training_info) {
            const info = model.training_info;
            html += '<hr class="my-2">';
            html += '<h4 class="font-medium">訓練資訊:</h4>';
            html += '<ul class="list-disc list-inside ml-2 mt-1">';
            html += `<li>訓練日期: ${info.training_date}</li>`;
            html += `<li>Essay Set: ${info.essay_set}</li>`;
            html += `<li>RMSE: ${info.rmse.toFixed(4)}</li>`;
            html += `<li>Kappa: ${info.kappa.toFixed(4)}</li>`;
            html += `<li>特徵數量: ${info.feature_count}</li>`;
            html += '</ul>';
        }
        
        html += '</div>';
        
        // 更新對話框內容
        content.innerHTML = html;
        
        // 顯示對話框
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // 設置關閉按鈕
        document.getElementById('close-modal').onclick = function() {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        };
        
        // 點擊背景關閉
        modal.onclick = function(e) {
            if (e.target === modal) {
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            }
        };
    }
    </script>
</body>
</html>