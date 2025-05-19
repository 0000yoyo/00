<?php
// 開啟所有錯誤報告
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connection.php';
require_once 'send_verification_email.php';

// 初始化錯誤陣列
$errors = [];
$registration_success = '';
$verification_code = '';
$upload_dir = 'uploads/teacher_certificates/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

function uploadTeacherCertificate($file) {
    global $upload_dir;
    
    $allowed_types = ['pdf', 'jpg', 'jpeg', 'png'];
    $max_file_size = 5 * 1024 * 1024; // 5MB

    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_error = $file['error'];

    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // 驗證文件
    if ($file_error !== UPLOAD_ERR_OK) {
        return ['error' => '檔案上傳失敗'];
    }

    if (!in_array($file_ext, $allowed_types)) {
        return ['error' => '只允許上傳 PDF、JPG 或 PNG 檔案'];
    }

    if ($file_size > $max_file_size) {
        return ['error' => '檔案大小不能超過 5MB'];
    }

    // 生成唯一檔名
    $new_file_name = uniqid('teacher_cert_', true) . '.' . $file_ext;
    $destination = $upload_dir . $new_file_name;

    // 移動上傳檔案
    if (move_uploaded_file($file_tmp, $destination)) {
        return ['success' => $new_file_name];
    } else {
        return ['error' => '檔案移動失敗'];
    }
}

// 密碼驗證函數
function validatePassword($password) {
    $hasUppercase = preg_match('/[A-Z]/', $password);
    $hasLowercase = preg_match('/[a-z]/', $password);
    $hasNumber = preg_match('/\d/', $password);
    $isLengthValid = strlen($password) == 6;

    return $hasUppercase && $hasLowercase && $hasNumber && $isLengthValid;
}

// 處理表單提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 接收表單資料
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $username = htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type = $_POST['user_type'] ?? '';

    // 電子郵件驗證
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "電子郵件格式不正確";
    }

    // 密碼驗證
    if (!validatePassword($password)) {
        $errors[] = "密碼必須是6位數，包含大寫、小寫字母和數字";
    }

    // 確認密碼是否一致
    if ($password !== $confirm_password) {
        $errors[] = "兩次輸入的密碼不一致";
    }

    // 使用者名稱長度驗證
    if (strlen($username) < 2) {
        $errors[] = "使用者名稱至少需要2個字元";
    }

    // 檢查使用者類型
    if (!in_array($user_type, ['student', 'teacher'])) {
        $errors[] = "請選擇正確的使用者類型";
    }

    // 如果是教師，處理證明文件
    $teacher_certificate = null;
    if ($user_type == 'teacher') {
        if (isset($_FILES['teacher_certificate']) && $_FILES['teacher_certificate']['error'] == UPLOAD_ERR_OK) {
            $upload_result = uploadTeacherCertificate($_FILES['teacher_certificate']);
            
            if (isset($upload_result['error'])) {
                $errors[] = $upload_result['error'];
            } else {
                $teacher_certificate = $upload_result['success'];
            }
        } else {
            $errors[] = "請上傳教師證明文件";
        }
    }

    // 如果沒有錯誤
    if (empty($errors)) {
        try {
            // 檢查電子郵件是否已存在
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "此電子郵件已被註冊";
            } else {
                // 密碼雜湊
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // 對於教師，設定 user_type 為 teacher_pending
                $final_user_type = ($user_type == 'teacher') ? 'teacher_pending' : 'student';

                // 發送驗證信
                $verification_code = sendVerificationEmail($email);
                if ($verification_code) {
                    $verification_expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                    // 插入使用者資料
                    $stmt = $conn->prepare("INSERT INTO users (email, username, password, user_type, teacher_certificate, verification_code, verification_expires, is_verified, admin_verified) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0)");
                    $stmt->execute([
                        $email, 
                        $username, 
                        $hashed_password, 
                        $final_user_type, 
                        $teacher_certificate, 
                        $verification_code,  // 直接存入6位數驗證碼 
                        $verification_expires
                    ]);

                    session_start();
                    $_SESSION['registration_email'] = $email;
                    $_SESSION['registration_success'] = "已發送驗證碼至 $email";
                    
                    header("Location: verify.php");
                    exit();
                } else {
                    $errors[] = "驗證信發送失敗";
                }
            }
        } catch(PDOException $e) {
            $errors[] = "資料庫錯誤：" . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>使用者註冊</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white shadow-2xl rounded-2xl overflow-hidden">
        <div class="p-8">
            <h2 class="text-3xl font-bold text-center text-blue-800 mb-6">
                使用者註冊
            </h2>

            <?php 
            // 顯示錯誤訊息
            if (!empty($errors)) {
                echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4'>";
                foreach ($errors as $error) {
                    echo "<p>" . htmlspecialchars($error) . "</p>";
                }
                echo "</div>";
            }

            // 顯示成功訊息
            if (!empty($registration_success)) {
                echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4'>" . 
                     htmlspecialchars($registration_success) . "</div>";
            }
            ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()" class="space-y-4">
                <div>
                    <label class="block mb-2">使用者類型</label>
                    <div class="flex justify-center space-x-4">
                        <label class="inline-flex items-center">
                            <input 
                                type="radio" 
                                name="user_type" 
                                value="student" 
                                class="form-radio"
                                onchange="toggleTeacherCertificate()"
                            >
                            <span class="ml-2">學生</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input 
                                type="radio" 
                                name="user_type" 
                                value="teacher" 
                                class="form-radio"
                                onchange="toggleTeacherCertificate()"
                            >
                            <span class="ml-2">教師</span>
                        </label>
                    </div>
                </div>

                <div id="teacher-certificate-section" style="display:none;">
                    <label class="block mb-2">上傳教師證明文件</label>
                    <input 
                        type="file" 
                        name="teacher_certificate" 
                        accept=".pdf,.jpg,.jpeg,.png"
                        class="w-full px-3 py-2 border rounded-lg"
                    >
                    <small class="text-gray-600">請上傳PDF或圖片格式，檔案大小不超過5MB</small>
                </div>

                <div>
                    <label for="email" class="block mb-2">電子郵件</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        required 
                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="請輸入電子郵件"
                    >
                </div>

                <div>
                    <label for="username" class="block mb-2">使用者名稱</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required 
                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="請輸入使用者名稱"
                    >
                </div>

                <div>
                    <label for="password" class="block mb-2">密碼</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="6位數，包含大小寫和數字"
                    >
                </div>

                <div>
                    <label for="confirm_password" class="block mb-2">確認密碼</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        required 
                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="再次輸入密碼"
                    >
                </div>

                <button 
                    type="submit" 
                    class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition-colors duration-300"
                >
                    註冊
                </button>
            </form>

            <div class="text-center mt-4">
                <p class="text-gray-600">
                    已經有帳號？
                    <a href="login.php" class="text-blue-600 hover:underline">
                        直接登入
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script>
    function toggleTeacherCertificate() {
        const teacherRadio = document.querySelector('input[name="user_type"][value="teacher"]');
        const certificateSection = document.getElementById('teacher-certificate-section');
        
        if (teacherRadio.checked) {
            certificateSection.style.display = 'block';
        } else {
            certificateSection.style.display = 'none';
        }
    }

    function validateForm() {
        // 確認使用者類型
        const userTypes = document.getElementsByName('user_type');
        let userTypeSelected = false;
        for (let i = 0; i < userTypes.length; i++) {
            if (userTypes[i].checked) {
                userTypeSelected = true;
                break;
            }
        }

        if (!userTypeSelected) {
            alert('請選擇使用者類型');
            return false;
        }

        // 電子郵件驗證
        const email = document.getElementById('email').value;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            alert('請輸入有效的電子郵件地址');
            return false;
        }

        // 密碼驗證
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        if (password.length !== 6) {
            alert('密碼必須是6位數');
            return false;
        }

        const hasUppercase = /[A-Z]/.test(password);
        const hasLowercase = /[a-z]/.test(password);
        const hasNumber = /\d/.test(password);
        
        if (!hasUppercase) {
            alert('密碼必須包含至少一個大寫字母');
            return false;
        }

if (!hasLowercase) {
            alert('密碼必須包含至少一個小寫字母');
            return false;
        }

        if (!hasNumber) {
            alert('密碼必須包含至少一個數字');
            return false;
        }

        // 確認密碼
        if (password !== confirmPassword) {
            alert('兩次輸入的密碼不一致');
            return false;
        }

        // 如果選擇教師，檢查是否上傳證明文件
        const teacherRadio = document.querySelector('input[name="user_type"][value="teacher"]');
        if (teacherRadio.checked) {
            const certificateInput = document.querySelector('input[name="teacher_certificate"]');
            if (!certificateInput.files.length) {
                alert('請上傳教師證明文件');
                return false;
            }
        }

        return true;
    }
    </script>
</body>
</html>