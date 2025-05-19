<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// 產生6位數驗證碼
function generateVerificationCode() {
    return sprintf("%06d", rand(0, 999999));
}

function sendVerificationEmail($email) {
    // 產生6位數驗證碼
    $verification_code = generateVerificationCode();

    $mail = new PHPMailer(true);

    try {
        // 設定編碼
        $mail->CharSet = 'UTF-8';
        
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mailforproject12345@gmail.com';
        $mail->Password   = 'bmnh forg xofu piez';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // 發件人
        $mail->setFrom('mailforproject12345@gmail.com', 'Essay Platform');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = '作文批改平台驗證信件';
        $mail->Body    = "
            感謝您註冊此平台帳戶！<br><br>
            以下是您的驗證碼：<br>
            <span style='font-size: 20pt; font-weight: bold;'>{$verification_code}</span><br><br>
            請您返回平台上輸入驗證碼完成驗證，謝謝您！
        ";

        // 發送郵件
        if ($mail->send()) {
            return $verification_code;  // 返回6位數驗證碼
        } else {
            return false;
        }
    } catch (Exception $e) {
        return false;
    }
}
?>