<?php
session_start();
// 確保只有學生可以訪問
if(!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] != 'student'){
    header("location: login.php");
    exit;
}
// 引入資料庫連接
require_once 'db_connection.php'; 
// 引入 OCR 服務
require_once 'tesseract_ocr_service.php';
// 引入作文類型說明
require_once 'essay_types.php';
// 初始化變數
$success_message = "";
$error_message = "";
$ocr_text = "";
$ocr_image_path = "";

// 處理表單提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 檢查是提交文字作文還是圖片 OCR
    // 更新處理圖片上傳和 OCR 的部分
	// 在 upload_essay.php 中，找到處理 OCR 的部分，並修改如下：

	// 處理圖片上傳和 OCR
	// 在處理上傳的圖片時使用新方法
	if(isset($_POST['submit_type']) && $_POST['submit_type'] == 'ocr') {
		// 處理圖片上傳和 OCR
		if(isset($_FILES['essay_image']) && $_FILES['essay_image']['error'] == 0) {
			$allowed = array('jpg', 'jpeg', 'png');
			$filename = $_FILES['essay_image']['name'];
			$ext = pathinfo($filename, PATHINFO_EXTENSION);
			
			if(in_array(strtolower($ext), $allowed)) {
				$upload_dir = 'uploads/essay_images/';
				
				// 確保上傳目錄存在
				if (!file_exists($upload_dir)) {
					mkdir($upload_dir, 0755, true);
				}
				
				$new_filename = uniqid('essay_', true) . '.' . $ext;
				$destination = $upload_dir . $new_filename;
				
				if(move_uploaded_file($_FILES['essay_image']['tmp_name'], $destination)) {
					// 保存圖片路徑，用於顯示
					$ocr_image_path = $destination;
					
					// 初始化 OCR 服務
					$ocr_service = new TesseractOCRService();
					
					// 使用增強的多引擎識別
					$ocr_results = $ocr_service->multipleEngineRecognition($destination);
					
					if($ocr_results['status'] == 'success') {
						// 將所有結果保存到session，供用戶選擇
						$_SESSION['ocr_results'] = $ocr_results['all_results'];
						$_SESSION['ocr_image_path'] = $ocr_image_path;
						
						// 默認使用最佳結果
						$ocr_text = $ocr_results['text'];
						$success_message = "圖片上傳成功，已識別英文內容！您可以在下方編輯識別的文字。";
					} else {
						$error_message = "文字識別失敗：" . $ocr_results['text'];
					}
				} else {
					$error_message = "圖片上傳失敗，請重試。";
				}
			} else {
				$error_message = "僅支持 JPG、JPEG 和 PNG 格式的圖片。";
			}
		} else {
			$error_message = "請選擇要上傳的作文圖片。";
		}
	} elseif(isset($_POST['submit_type']) && $_POST['submit_type'] == 'text') {
		// 處理文字作文提交
		if(!empty($_POST['title']) && !empty($_POST['content']) && !empty($_POST['category'])) {
			try {
				// 取得是否需要老師批改的選項
				$teacher_review = isset($_POST['teacher_review']) ? 1 : 0;
				
				// 將作文保存到資料庫
				$stmt = $conn->prepare("INSERT INTO essays (user_id, title, category, content, ocr_source, teacher_review, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
				$stmt->execute([
					$_SESSION['id'],
					$_POST['title'],
					$_POST['category'],
					$_POST['content'],
					isset($_POST['ocr_source']) ? 1 : 0,
					$teacher_review
				]);
				
				$essay_id = $conn->lastInsertId();
				
				// 無論是否勾選，都調用AI批改服務
				require_once 'advanced_ai_review_service.php';
				$ai_service = new AdvancedAIReviewService();
				$review_result = $ai_service->reviewEssay($_POST['content'], $_POST['category']);
				
				$ai_feedback = $review_result['feedback'];
				$ai_score = $review_result['score'];
				
				// 設置狀態變量
				$status = $teacher_review ? "pending" : "graded";
				
				// 更新作文狀態
				$stmt = $conn->prepare("UPDATE essays SET status = ? WHERE id = ?");
				$stmt->execute([$status, $essay_id]);
				
				// 更新資料庫中的AI評分
				$stmt = $conn->prepare("UPDATE essays SET ai_feedback = ?, ai_score = ? WHERE id = ?");
				$stmt->execute([$ai_feedback, $ai_score, $essay_id]);
				
				// 如果有評分指標，保存到指標表
				if (!isset($review_result['is_simulated']) || !$review_result['is_simulated']) {
					// 計算文本特徵
					$word_count = str_word_count($_POST['content']);
					$sentence_count = preg_match_all('/[.!?]+/', $_POST['content'], $matches);
					
					// 插入評分指標記錄
					$stmt = $conn->prepare("INSERT INTO essay_metrics
										(essay_id, user_id, ai_score, word_count, sentence_count, 
										category, ai_version, created_at)
										VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
					
					$stmt->execute([
						$essay_id, 
						$_SESSION['id'], 
						$ai_score, 
						$word_count,
						$sentence_count,
						$_POST['category'],
						$review_result['model_version'] ?? 'unknown'
					]);
					
					$metrics_id = $conn->lastInsertId();
					
					// 更新作文中的 ai_metrics_id
					$stmt = $conn->prepare("UPDATE essays SET ai_feedback = ?, ai_score = ?, status = ? WHERE id = ?");
					$stmt->execute([$ai_feedback, $ai_score, $status, $essay_id]);
				}
				
				// 根據是否勾選教師批改設置不同的狀態和信息
				if($teacher_review) {
					$success_message = "作文已成功提交！AI已完成初步評分，等待老師批改。";
				} else {
					// 設置狀態為已批改，因為只需AI批改
					$stmt = $conn->prepare("UPDATE essays SET status = 'graded' WHERE id = ?");
					$stmt->execute([$essay_id]);
					$success_message = "作文已成功提交！AI已完成批改評分。";
				}
				
				// 重置表單
				$_POST = array();
				
			} catch (PDOException $e) {
				$error_message = "作文提交失敗：" . $e->getMessage();
			}
		} else {
			$error_message = "標題、類別和內容都不能為空。";
		}
	}
}


?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>上傳作文 - 作文批改平台</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 2s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
    <!-- 載入動畫 -->
    <div id="loading-overlay" class="loading-overlay">
        <div class="spinner"></div>
        <p class="text-white mt-4">處理中，請稍候...</p>
    </div>

    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-blue-700">上傳作文</h1>
            <a href="student_dashboard.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-1"></i>返回首頁
            </a>
        </div>

        <?php if (!empty($success_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            <?php echo $success_message; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <!-- 標籤切換 -->
        <div class="flex border-b mb-6">
            <button id="tab-text" class="px-4 py-2 border-b-2 border-blue-500 text-blue-600 font-medium">
                <i class="fas fa-pencil-alt mr-1"></i>直接輸入文字
            </button>
            <button id="tab-image" class="px-4 py-2 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium">
                <i class="fas fa-camera mr-1"></i>上傳圖片辨識
            </button>
        </div>

        <!-- 文字輸入區塊 -->
        <div id="content-text" class="block">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-6">
                <input type="hidden" name="submit_type" value="text">
                
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">作文標題</label>
                    <input type="text" id="title" name="title" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                </div>

                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-1">作文類型</label>
                    <select id="category" name="category" required
					class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
					<option value="">請選擇類型</option>
					<option value="narrative">敘事文（Narrative Essay）</option>
					<option value="descriptive">描述文（Descriptive Essay）</option>
					<option value="argumentative">論說文（Argumentative Essay）</option>
					<option value="expository">說明文（Expository Essay）</option>
					<option value="compare_contrast">比較對比文（Compare and Contrast Essay）</option>
					<option value="persuasive">議論文（Persuasive Essay）</option>
					<option value="reflective">反思文（Reflective Essay）</option>
					<option value="critical_analysis">批評性分析文（Critical Analysis Essay）</option>
				</select>
                </div>

                <div>
                    <label for="content" class="block text-sm font-medium text-gray-700 mb-1">作文內容</label>
                    <textarea id="content" name="content" rows="10" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="請在此輸入您的作文內容..."><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
                    <div class="flex justify-between mt-1 text-sm text-gray-500">
                        <span id="word-count">0 字</span>
						<span class="text-xs text-gray-500">(以單詞計算，每個英文單詞算作1個字)</span>
                        <span>建議字數：</br>雅思小作文150-180字</br>雅思大作文250-280字</br>托福寫作150-225字</br>獨立寫作300-400字</span>
                    </div>
                </div>
				<div class="mt-4">
				<label class="flex items-center">
					<input type="checkbox" name="teacher_review" value="1" checked class="form-checkbox h-5 w-5 text-blue-600">
					<span class="ml-2 text-gray-700">請老師批改（如不勾選，僅由AI批改評分）</span>
				</label>
				</div>

                <div class="flex justify-end space-x-4">
                    <a href="student_dashboard.php" 
                    class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        取消
                    </a>
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 rounded-lg text-white hover:bg-blue-700 transition-colors">
                        提交作文
                    </button>
                </div>
            </form>
        </div>

        <!-- 圖片上傳區塊 -->
		<div id="content-image" class="hidden">
			<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data" class="space-y-6" id="ocr-form">
				<input type="hidden" name="submit_type" value="ocr">
				
				<div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
					<div class="mb-4">
						<i class="fas fa-file-image text-4xl text-gray-400"></i>
					</div>
					<p class="text-sm text-blue-700 font-medium mb-4">英文文字辨識系統</p>
					<p class="text-sm text-gray-500 mb-4">上傳英文作文圖片，系統將自動識別英文文字內容</p>
					<p class="text-xs text-gray-500 mb-4">支持JPG、JPEG和PNG格式，圖片大小不超過5MB</p>
					<p class="text-xs text-gray-500 mb-4">注意：系統目前只支持辨識英文文字，中文或其他語言可能無法正確識別</p>
					
					<label class="inline-block px-6 py-2 bg-blue-600 rounded-lg text-white hover:bg-blue-700 transition-colors cursor-pointer">
						<input type="file" name="essay_image" id="essay_image" class="hidden" accept=".jpg,.jpeg,.png">
						選擇圖片
					</label>
					<p id="file-name" class="mt-2 text-sm text-gray-600"></p>
				</div>
				
				<div class="mt-4 p-4 bg-blue-50 rounded-lg">
					<h4 class="text-sm font-medium text-blue-700 mb-2">提高英文辨識品質的技巧</h4>
					<ul class="text-xs text-blue-600 list-disc pl-5 space-y-1">
						<li>確保圖片清晰，無陰影和反光</li>
						<li>盡量使用純白背景，黑色文字的圖片</li>
						<li>如果辨識結果不理想，可嘗試以不同角度重新拍攝</li>
						<li>對於手寫文字，盡量使用整潔的筆跡</li>
						<li>如果結果仍有錯誤，您可以在下方編輯框中修改</li>
					</ul>
				</div>
				
				<div class="mt-4">
					<label class="flex items-center">
						<input type="checkbox" name="teacher_review" value="1" checked class="form-checkbox h-5 w-5 text-blue-600">
						<span class="ml-2 text-gray-700">請老師批改（如不勾選，僅由AI批改評分）</span>
					</label>
				</div>
		
				<div class="flex justify-end">
					<button type="submit" 
							class="px-6 py-2 bg-blue-600 rounded-lg text-white hover:bg-blue-700 transition-colors"
							id="ocr-submit">
						上傳並識別英文文字
					</button>
				</div>
			</form>
		

            <?php if (!empty($ocr_text)): ?>
			
            <div class="mt-8">
                <h3 class="text-lg font-semibold mb-4">識別結果</h3>
                <!---<?php if (!empty($_SESSION['ocr_results'])): ?>
				<div class="mt-4 p-4 bg-blue-50 rounded-lg">
					<h4 class="text-sm font-medium text-blue-700 mb-2">請選擇最準確的辨識結果</h4>
					<div class="space-y-3">
						<?php foreach ($_SESSION['ocr_results'] as $index => $result): ?>
						<div class="p-3 border rounded-lg bg-white hover:bg-blue-50 cursor-pointer ocr-result <?php echo $index === 0 ? 'bg-blue-100 border-blue-500' : ''; ?>" 
							data-text="<?php echo htmlspecialchars($result['text']); ?>">
							<p class="text-sm font-medium">辨識結果 <?php echo $index+1; ?> (評分: <?php echo $result['score']; ?>)</p>
							<p class="text-xs text-gray-600 mt-1"><?php echo nl2br(htmlspecialchars(substr($result['text'], 0, 150))); ?>...</p>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
				
				<script>
				// 點擊選擇最佳結果
				document.querySelectorAll('.ocr-result').forEach(function(el) {
					el.addEventListener('click', function() {
						document.getElementById('ocr_content').value = this.dataset.text;
						
						// 視覺反饋
						document.querySelectorAll('.ocr-result').forEach(function(item) {
							item.classList.remove('bg-blue-100', 'border-blue-500');
						});
						this.classList.add('bg-blue-100', 'border-blue-500');
					});
				});
				</script> --->
				<?php endif; ?>
                <!-- 顯示原始圖片和識別結果 -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- 原始圖片 -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 mb-2">原始圖片</h4>
                        <div class="border rounded-lg overflow-hidden">
                            <img src="<?php echo $ocr_image_path; ?>" alt="上傳的作文圖片" class="w-full h-auto">
                        </div>
                    </div>
                </div>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-6">
                    <input type="hidden" name="submit_type" value="text">

                    <div>
                        <label for="ocr_title" class="block text-sm font-medium text-gray-700 mb-1">作文標題</label>
                        <input type="text" id="ocr_title" name="title" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="ocr_category" class="block text-sm font-medium text-gray-700 mb-1">作文類型</label>
                       <select id="category" name="category" required
					class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
					<option value="">請選擇類型</option>
					<option value="narrative">敘事文（Narrative Essay）</option>
					<option value="descriptive">描述文（Descriptive Essay）</option>
					<option value="argumentative">論說文（Argumentative Essay）</option>
					<option value="expository">說明文（Expository Essay）</option>
					<option value="compare_contrast">比較對比文（Compare and Contrast Essay）</option>
					<option value="persuasive">議論文（Persuasive Essay）</option>
					<option value="reflective">反思文（Reflective Essay）</option>
					<option value="critical_analysis">批評性分析文（Critical Analysis Essay）</option>
				</select>
                    </div>

                    <div>
                        <label for="ocr_content" class="block text-sm font-medium text-gray-700 mb-1">辨識內容（可編輯）</label>
                        <textarea id="ocr_content" name="content" rows="10" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($ocr_text); ?></textarea>
                        <div class="flex justify-between mt-1 text-sm text-gray-500">
                            <span id="ocr-word-count">0 字</span>
                            <span>建議字數：</br>雅思小作文150-180字</br>雅思大作文250-280字</br>托福寫作150-225字</br>獨立寫作300-400字</span>
                        </div>
                    </div>
					
					<div class="mt-4">
						<label class="flex items-center">
							<input type="checkbox" name="teacher_review" value="1" checked class="form-checkbox h-5 w-5 text-blue-600">
							<span class="ml-2 text-gray-700">請老師批改（如不勾選，僅由AI批改評分）</span>
						</label>
					</div>

                    <div class="flex justify-end space-x-4">
                        <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" 
                        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            重新上傳
                        </a>
                        <button type="submit" 
                                class="px-6 py-2 bg-blue-600 rounded-lg text-white hover:bg-blue-700 transition-colors">
                            提交作文
                        </button>
                    </div>
                </form>
                
                <!-- OCR 反饋區 -->
                <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">OCR 識別品質反饋</h4>
                    <p class="text-xs text-gray-600 mb-2">您認為 OCR 識別的準確度如何？這將幫助我們改進系統。</p>
                    <div class="flex items-center space-x-2">
                        <button class="ocr-feedback px-2 py-1 border rounded text-xs" data-rating="1">很差</button>
                        <button class="ocr-feedback px-2 py-1 border rounded text-xs" data-rating="2">較差</button>
                        <button class="ocr-feedback px-2 py-1 border rounded text-xs" data-rating="3">一般</button>
                        <button class="ocr-feedback px-2 py-1 border rounded text-xs" data-rating="4">較好</button>
                        <button class="ocr-feedback px-2 py-1 border rounded text-xs" data-rating="5">很好</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 標籤切換功能
        const tabText = document.getElementById('tab-text');
        const tabImage = document.getElementById('tab-image');
        const contentText = document.getElementById('content-text');
        const contentImage = document.getElementById('content-image');
		

        tabText.addEventListener('click', function() {
            tabText.classList.add('border-blue-500', 'text-blue-600');
            tabText.classList.remove('border-transparent', 'text-gray-500');
            tabImage.classList.add('border-transparent', 'text-gray-500');
            tabImage.classList.remove('border-blue-500', 'text-blue-600');
            contentText.classList.remove('hidden');
            contentText.classList.add('block');
            contentImage.classList.remove('block');
            contentImage.classList.add('hidden');
        });

        tabImage.addEventListener('click', function() {
            tabImage.classList.add('border-blue-500', 'text-blue-600');
            tabImage.classList.remove('border-transparent', 'text-gray-500');
            tabText.classList.add('border-transparent', 'text-gray-500');
            tabText.classList.remove('border-blue-500', 'text-blue-600');
            contentImage.classList.remove('hidden');
            contentImage.classList.add('block');
            contentText.classList.remove('block');
            contentText.classList.add('hidden');
        });

        // 字數統計功能 - 文字輸入區塊
		const contentTextarea = document.getElementById('content');
		const wordCount = document.getElementById('word-count');
		
		contentTextarea.addEventListener('input', function() {
			// 使用正則表達式將文本分割成單詞並計數
			// 這個正則表達式匹配英文單詞、數字和漢字
			const text = this.value;
			const words = text.match(/\b\w+\b|\p{Script=Han}/gu) || [];
			const count = words.length;
			
			wordCount.textContent = count + ' 個字';
		});
		
		// 字數統計功能 - OCR 內容區塊
		const ocrContentTextarea = document.getElementById('ocr_content');
		if (ocrContentTextarea) {
			const ocrWordCount = document.getElementById('ocr-word-count');
			
			ocrContentTextarea.addEventListener('input', function() {
				const text = this.value;
				const words = text.match(/\b\w+\b|\p{Script=Han}/gu) || [];
				const count = words.length;
				
				ocrWordCount.textContent = count + ' 個字';
			});
			
			// 頁面載入時計算 OCR 內容字數
			const initialText = ocrContentTextarea.value;
			const initialWords = initialText.match(/\b\w+\b|\p{Script=Han}/gu) || [];
			const initialCount = initialWords.length;
			
			ocrWordCount.textContent = initialCount + ' 個字';
		}

        // 顯示選擇的檔案名稱
        const fileInput = document.getElementById('essay_image');
        const fileName = document.getElementById('file-name');

        fileInput.addEventListener('change', function() {
            if(this.files.length > 0) {
                fileName.textContent = '已選擇: ' + this.files[0].name;
            } else {
                fileName.textContent = '';
            }
        });

        // 如果頁面有OCR結果，自動切換到圖片識別標籤
        <?php if (!empty($ocr_text)): ?>
        window.onload = function() {
            tabImage.click();
        };
        <?php endif; ?>
		

        // 增加 OCR 表單提交時的載入動畫
        const ocrForm = document.getElementById('ocr-form');
        const loadingOverlay = document.getElementById('loading-overlay');
        
        ocrForm.addEventListener('submit', function() {
            // 檢查是否選擇了文件
            if(fileInput.files.length > 0) {
                loadingOverlay.style.display = 'flex';
            }
        });

        // OCR 反饋功能
        const feedbackButtons = document.querySelectorAll('.ocr-feedback');
        feedbackButtons.forEach(button => {
            button.addEventListener('click', function() {
                // 視覺效果
                feedbackButtons.forEach(btn => {
                    btn.classList.remove('bg-blue-500', 'text-white');
                    btn.classList.add('bg-white', 'text-gray-700');
                });
                
                this.classList.remove('bg-white', 'text-gray-700');
                this.classList.add('bg-blue-500', 'text-white');
                
                // 發送反饋數據
                // 這裡您可以添加一個 AJAX 請求，將反饋數據發送到伺服器
                const rating = this.dataset.rating;
                console.log('OCR 反饋評分: ' + rating);
                
                // 顯示感謝訊息
                const thankYou = document.createElement('div');
                thankYou.className = 'text-green-600 text-xs mt-2';
                thankYou.textContent = '感謝您的反饋！';
                this.parentNode.appendChild(thankYou);
                
                // 3秒後移除感謝訊息
                setTimeout(() => {
                    thankYou.remove();
                }, 3000);
            });
        });
		// 作文類型說明功能
		const categorySelect = document.getElementById('category');
		const essayTypeDescription = document.getElementById('essay-type-description');
		const essayTypes = <?php echo json_encode($essay_types); ?>;
		
		// 顯示選中類型的說明
		categorySelect.addEventListener('change', function() {
			const selectedType = this.value;
			if (selectedType && essayTypes[selectedType]) {
				essayTypeDescription.textContent = essayTypes[selectedType];
			} else {
				essayTypeDescription.textContent = '';
			}
		});
		
		// 為 OCR 結果表單添加相同功能
		const ocrCategorySelect = document.getElementById('ocr_category');
		const ocrEssayTypeDescription = document.getElementById('ocr-essay-type-description');
		
		if (ocrCategorySelect) {
			ocrCategorySelect.addEventListener('change', function() {
				const selectedType = this.value;
				if (selectedType && essayTypes[selectedType]) {
					ocrEssayTypeDescription.textContent = essayTypes[selectedType];
				} else {
					ocrEssayTypeDescription.textContent = '';
				}
			});
		}
    </script>
</body>
</html>