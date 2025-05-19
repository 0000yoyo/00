<?php
// model_trainer.php
class ModelTrainer {
    private $python_script_path;
    private $data_path;
    
    /**
     * 構造函數
     */
    public function __construct() {
        $this->python_script_path = __DIR__ . '/scripts/train_model.py';
        $this->data_path = __DIR__ . '/data/essays/';
    }
    
    /**
     * 訓練模型
     * @param string $data_file 資料集文件名
     * @param int $essay_set 使用哪個 essay_set 進行訓練
     * @param string $version 模型版本
     * @return array 訓練結果
     */
    public function trainModel($data_file, $essay_set = 1, $version = '1.0.0') {
        // 確保資料目錄存在
        if (!file_exists($this->data_path)) {
            mkdir($this->data_path, 0755, true);
        }
        
        // 完整的資料檔案路徑
        $full_data_path = $this->data_path . $data_file;
        
        // 構建 Python 命令
        $command = "python " . escapeshellarg($this->python_script_path) . 
                  " --data " . escapeshellarg($full_data_path) . 
                  " --essay_set " . escapeshellarg($essay_set) . 
                  " --version " . escapeshellarg($version);
        
        // 執行命令並捕獲輸出
        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);
        
        // 解析輸出
        $result = [
            'success' => ($return_var === 0),
            'output' => implode("\n", $output),
            'command' => $command,
            'return_code' => $return_var
        ];
        
        // 檢查是否有保存訓練記錄
        $record_path = __DIR__ . "/models/training_record_v{$version}.json";
        if (file_exists($record_path)) {
            $training_info = json_decode(file_get_contents($record_path), true);
            $result['training_info'] = $training_info;
        }
        
        return $result;
    }
    
    /**
     * 獲取已訓練模型的列表
     * @return array 模型列表
     */
    public function getAvailableModels() {
        $models_dir = __DIR__ . '/models/';
        $models = [];
        
        if (!file_exists($models_dir)) {
            return $models;
        }
        
        // 尋找模型文件
        $files = scandir($models_dir);
        foreach ($files as $file) {
            if (preg_match('/essay_scoring_model_v(.+)\.pkl$/', $file, $matches)) {
                $version = $matches[1];
                
                // 檢查是否有訓練記錄
                $record_file = "training_record_v{$version}.json";
                $record_path = $models_dir . $record_file;
                
                $model_info = [
                    'version' => $version,
                    'file' => $file,
                    'path' => $models_dir . $file,
                    'created' => date("Y-m-d H:i:s", filemtime($models_dir . $file))
                ];
                
                if (file_exists($record_path)) {
                    $training_info = json_decode(file_get_contents($record_path), true);
                    $model_info['training_info'] = $training_info;
                }
                
                $models[] = $model_info;
            }
        }
        
        // 按版本降序排序
        usort($models, function($a, $b) {
            return version_compare($b['version'], $a['version']);
        });
        
        return $models;
    }
}
?>