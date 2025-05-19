<?php
session_start();
// 確保只有學生可以訪問
if(!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] != 'student'){
    header("location: login.php");
    exit;
}

require_once 'db_connection.php';
require_once 'essay_types.php';

// 設定過濾條件
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// 先獲取所有作文
$stmt = $conn->prepare("SELECT * FROM essays WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['id']]);
$all_essays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 根據過濾條件進行 PHP 過濾
$essays = [];
foreach ($all_essays as $essay) {
    if ($status_filter == 'all') {
        $essays[] = $essay;
    } 
    else if ($status_filter == 'pending') {
        // 待批改：teacher_review=1 且 status='pending'，或者還沒有AI評分
        if (($essay['teacher_review'] == 1 && $essay['status'] == 'pending') || 
            ($essay['teacher_review'] == 0 && empty($essay['ai_score']))) {
            $essays[] = $essay;
        }
    } 
    else if ($status_filter == 'graded') {
        // 已批改：teacher_review=1 且 status='graded'，或者有AI評分
        if (($essay['teacher_review'] == 1 && $essay['status'] == 'graded') || 
            ($essay['teacher_review'] == 0 && !empty($essay['ai_score']))) {
            $essays[] = $essay;
        }
    }
}
// 顯示成功訊息
if (isset($_SESSION['success_message'])) {
    echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">';
    echo $_SESSION['success_message'];
    echo '</div>';
    unset($_SESSION['success_message']);
}

// 顯示錯誤訊息
if (isset($_SESSION['error_message'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">';
    echo $_SESSION['error_message'];
    echo '</div>';
    unset($_SESSION['error_message']);
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的作文 - 作文批改平台</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-blue-700">我的作文列表</h1>
            <a href="student_dashboard.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-1"></i>返回首頁
            </a>
        </div>

        <div class="mb-6 flex justify-between items-center">
            <div class="space-x-2">
                <a href="?status=all" class="px-3 py-1 <?php echo $status_filter == 'all' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300'; ?> rounded-md text-sm">所有作文</a>
                <a href="?status=pending" class="px-3 py-1 <?php echo $status_filter == 'pending' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300'; ?> rounded-md text-sm">待批改</a>
                <a href="?status=graded" class="px-3 py-1 <?php echo $status_filter == 'graded' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300'; ?> rounded-md text-sm">已批改</a>
            </div>
            <a href="upload_essay.php" class="px-4 py-1.5 bg-green-600 text-white rounded-md text-sm hover:bg-green-700">
                <i class="fas fa-plus mr-1"></i>新增作文
            </a>
        </div>

        <?php if (empty($essays)): ?>
            <div class="bg-blue-50 rounded-lg p-6 text-center">
                <?php if ($status_filter == 'graded'): ?>
                    <p class="text-gray-600 mb-4">尚未擁有已批改完成作文</p>
                <?php elseif ($status_filter == 'pending'): ?>
                    <p class="text-gray-600 mb-4">尚未擁有待批改作文</p>
                <?php else: ?>
                    <p class="text-gray-600 mb-4">目前尚無作文記錄</p>
                <?php endif; ?>
                <a href="upload_essay.php" class="inline-block px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    立即上傳第一篇作文
                </a>
            </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
				<thead class="bg-gray-100">
					<tr>
						<th class="py-2 px-4 text-left border-b">標題</th>
						<th class="py-2 px-4 text-left border-b">類型</th>
						<th class="py-2 px-4 text-left border-b">提交日期</th>
						<th class="py-2 px-4 text-left border-b" style="width:15%">狀態</th>
						<th class="py-2 px-4 text-left border-b" style="width:10%">評分</th>
						<th class="py-2 px-4 text-left border-b" style="width:20%">操作</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($essays as $essay): ?>
					<tr class="hover:bg-gray-50">
						<td class="py-2 px-4 border-b"><?php echo htmlspecialchars($essay['title']); ?></td>
						<td class="py-2 px-4 border-b">
							<?php 
								$category = $essay['category'];
								$category_names = [
									'narrative' => '敘事文',
									'descriptive' => '描述文',
									'argumentative' => '論說文',
									'expository' => '說明文',
									'compare_contrast' => '比較對比文',
									'persuasive' => '議論文',
									'reflective' => '反思文',
									'critical_analysis' => '批評性分析文'
								];
								echo isset($category_names[$category]) ? $category_names[$category] : $category;
							?>
						</td>
						<td class="py-2 px-4 border-b"><?php echo date('Y/m/d', strtotime($essay['created_at'])); ?></td>
						<td class="py-2 px-4 border-b whitespace-nowrap">
							<?php if($essay['teacher_review']): ?>
								<?php if($essay['status'] == 'pending'): ?>
									<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">待老師批改</span>
								<?php else: ?>
									<span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">老師已批改</span>
								<?php endif; ?>
							<?php else: ?>
								<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">AI已批改</span>
							<?php endif; ?>
						</td>
						<td class="py-2 px-4 border-b text-center">
							<?php 
								if($essay['teacher_review'] && $essay['status'] == 'graded') {
									echo isset($essay['score']) ? $essay['score'] : '—';
								} elseif(!$essay['teacher_review'] && isset($essay['ai_score'])) {
									echo $essay['ai_score'];
								} else {
									echo '—';
								}
							?>
						</td>
						<td class="py-2 px-4 border-b">
							<div class="flex space-x-2 whitespace-nowrap">
								<a href="view_essay.php?id=<?php echo $essay['id']; ?>" class="text-blue-600 hover:text-blue-800">
									<i class="fas fa-eye mr-1"></i>查看
								</a>
								<?php if($essay['teacher_review'] && $essay['status'] == 'graded'): ?>
									<!-- 添加榮譽榜設置 -->
									<button type="button" class="honor-settings text-green-600 hover:text-green-800" 
											data-id="<?php echo $essay['id']; ?>"
											data-public="<?php echo $essay['allow_public']; ?>"
											data-anonymous="<?php echo $essay['anonymous']; ?>">
										<i class="fas fa-trophy mr-1"></i>榮譽榜
									</button>
								<?php endif; ?>
								<?php if(!($essay['teacher_review'] && $essay['status'] == 'graded')): ?>
									<button type="button" class="delete-essay text-red-600 hover:text-red-800" data-id="<?php echo $essay['id']; ?>">
										<i class="fas fa-trash mr-1"></i>刪除
									</button>
								<?php endif; ?>
							</div>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
        </div>
		<!-- 榮譽榜設置對話框 -->
		<div id="honor-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
			<div class="bg-white rounded-lg p-6 max-w-md w-full">
				<h3 class="text-lg font-bold mb-4">榮譽榜設置</h3>
				<form id="honor-form" action="update_honor_settings.php" method="POST">
					<input type="hidden" id="honor-essay-id" name="essay_id" value="">
					
					<div class="mb-4">
						<label class="flex items-center space-x-2">
							<input type="checkbox" id="allow-public" name="allow_public" value="1" class="form-checkbox h-5 w-5 text-blue-600">
							<span>允許公開在榮譽榜上（只有被批改完成的作文可公開）</span>
						</label>
					</div>
					
					<div class="mb-6">
						<label class="flex items-center space-x-2">
							<input type="checkbox" id="anonymous" name="anonymous" value="1" class="form-checkbox h-5 w-5 text-blue-600">
							<span>在榮譽榜上匿名顯示（不顯示您的姓名）</span>
						</label>
					</div>
					
					<div class="flex justify-end space-x-3">
						<button type="button" id="cancel-honor" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
							取消
						</button>
						<button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
							保存設置
						</button>
					</div>
				</form>
			</div>
		</div>
        
        <!-- 刪除確認對話框 -->
        <div id="delete-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-bold mb-4">確認刪除</h3>
                <p class="mb-6">您確定要刪除這篇作文嗎？這個操作無法撤銷。</p>
                <div class="flex justify-end space-x-3">
                    <button id="cancel-delete" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        取消
                    </button>
                    <form id="delete-form" action="delete_essay.php" method="POST">
                        <input type="hidden" id="delete-essay-id" name="essay_id" value="">
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                            確認刪除
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteModal = document.getElementById('delete-modal');
        const deleteForm = document.getElementById('delete-form');
        const deleteEssayId = document.getElementById('delete-essay-id');
        const cancelDelete = document.getElementById('cancel-delete');
        const deleteButtons = document.querySelectorAll('.delete-essay');
        
        console.log('Delete buttons found:', deleteButtons.length); // 調試用
        
        // 點擊刪除按鈕時顯示確認對話框
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const essayId = this.getAttribute('data-id');
                console.log('Delete button clicked, essay ID:', essayId); // 調試用
                deleteEssayId.value = essayId;
                deleteModal.style.display = 'flex';
            });
        });
        
        // 點擊取消按鈕時隱藏確認對話框
        cancelDelete.addEventListener('click', function() {
            deleteModal.style.display = 'none';
        });
        
        // 點擊對話框外部區域時隱藏對話框
        deleteModal.addEventListener('click', function(e) {
            if (e.target === deleteModal) {
                deleteModal.style.display = 'none';
            }
        });
    });
	// 榮譽榜設置處理
	const honorModal = document.getElementById('honor-modal');
	const honorForm = document.getElementById('honor-form');
	const honorEssayId = document.getElementById('honor-essay-id');
	const allowPublic = document.getElementById('allow-public');
	const anonymous = document.getElementById('anonymous');
	const cancelHonor = document.getElementById('cancel-honor');
	const honorButtons = document.querySelectorAll('.honor-settings');
	// 點擊榮譽榜按鈕時顯示設置對話框
	honorButtons.forEach(button => {
		button.addEventListener('click', function() {
			const essayId = this.getAttribute('data-id');
			const isPublic = this.getAttribute('data-public') === '1';
			const isAnonymous = this.getAttribute('data-anonymous') === '1';
			
			honorEssayId.value = essayId;
			allowPublic.checked = isPublic;
			anonymous.checked = isAnonymous;
			
			honorModal.style.display = 'flex';
		});
	});
	
	// 點擊取消按鈕時隱藏設置對話框
	cancelHonor.addEventListener('click', function() {
		honorModal.style.display = 'none';
	});
	
	// 點擊對話框外部區域時隱藏對話框
	honorModal.addEventListener('click', function(e) {
		if (e.target === honorModal) {
			honorModal.style.display = 'none';
		}
	});
    </script>
</body>
</html>