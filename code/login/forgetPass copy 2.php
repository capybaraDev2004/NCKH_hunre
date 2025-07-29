<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../connection/connection.php';

// Sử dụng đường dẫn tuyệt đối
$basePath = '/home/vol15_8/infinityfree.com/if0_38986229/quanlynghiepvugiangvienhunre.rf.gd/htdocs/NghiepVuGiangVien';

// Include trực tiếp các file PHPMailer
require_once $basePath . '/vendor/phpmailer/phpmailer/src/Exception.php';
require_once $basePath . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once $basePath . '/vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

session_start();

// Function to generate random verification code
function generateVerificationCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Function to generate random 8-digit password
function generateRandomPassword() {
    $numbers = range(0, 9);
    shuffle($numbers);
    return implode('', array_slice($numbers, 0, 8));
}

// Function to send email using PHPMailer
function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'nguyentientoan28022004@gmail.com';
        $mail->Password = 'momj ghwf mzpg ipuy';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom('nguyentientoan28022004@gmail.com', 'Hệ thống quản lý nghiệp vụ giảng viên');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['verify_info'])) {
        // First step: Verify user information
        $username = trim($_POST["userName"]);
        $phone = trim($_POST["phone"]);
        $email = trim($_POST["email"]);

        $sql = "SELECT * FROM employee WHERE userName = :username AND phone = :phone AND email = :email";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            // Generate and store verification code
            $verificationCode = generateVerificationCode();
            $_SESSION['verification_code'] = $verificationCode;
            $_SESSION['reset_username'] = $username;
            $_SESSION['verification_time'] = time(); // Lưu thời gian tạo mã
            
            // Send verification code via email
            $emailBody = "Mã xác nhận của bạn là: <b>$verificationCode</b><br>Mã này có hiệu lực trong 5 phút.";
            if (sendEmail($email, "Mã xác nhận đặt lại mật khẩu", $emailBody)) {
                echo json_encode(['status' => 'success', 'message' => 'Mã xác nhận đã được gửi đến email của bạn']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Không thể gửi email. Vui lòng thử lại sau']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Thông tin không chính xác']);
        }
        exit;
    }

    if (isset($_POST['verify_code'])) {
        // Second step: Verify code and reset password
        $code = trim($_POST["verification_code"]);
        
        // Kiểm tra thời gian hết hạn của mã (5 phút)
        if (!isset($_SESSION['verification_time']) || (time() - $_SESSION['verification_time']) > 300) {
            echo json_encode(['status' => 'error', 'message' => 'Mã xác nhận đã hết hạn. Vui lòng yêu cầu mã mới']);
            exit;
        }
        
        if (isset($_SESSION['verification_code']) && $code === $_SESSION['verification_code']) {
            $username = $_SESSION['reset_username'];
            $newPassword = generateRandomPassword(); // Tạo mật khẩu ngẫu nhiên 8 số
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password in database
            $updateSql = "UPDATE employee SET password = :password WHERE userName = :username";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
            $updateStmt->bindParam(':username', $username, PDO::PARAM_STR);
            
            if ($updateStmt->execute()) {
                // Send new password via email
                $emailBody = "Mật khẩu mới của bạn là: <b>$newPassword</b><br>Vui lòng đăng nhập và đổi mật khẩu mới.";
                sendEmail($_POST['email'], "Mật khẩu mới", $emailBody);
                
                // Clear session
                unset($_SESSION['verification_code']);
                unset($_SESSION['reset_username']);
                unset($_SESSION['verification_time']);
                
                echo json_encode(['status' => 'success', 'message' => 'Mật khẩu đã được đặt lại thành công, vui lòng kiểm tra email và đổi mật khẩu theo quy định']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Không thể đặt lại mật khẩu']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Mã xác nhận không chính xác']);
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quên mật khẩu</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        html {
            background: linear-gradient(135deg, #1b3276 0%, #2a4494 100%);
            min-height: 100vh;
            color: white;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .header {
            background: #1b3276;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
            text-align: center;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            padding: 0 20px;
            justify-content: center;
        }

        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-img {
            width: 250px;
            height: 130px;
            object-fit: contain;
        }

        .school-info {
            border-left: 2px solid rgba(255, 255, 255, 0.2);
            padding-left: 30px;
            text-align: left;
        }

        .school-type {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #fff;
            text-transform: uppercase;
        }

        .school-name {
            font-size: 28px;
            font-weight: 600;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Form styling */
        .main-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .form_login {
            background: rgba(255, 255, 255, 0.95);
            color: #1b3276;
            width: 600px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            padding-left: 40px;
            padding-right: 40px;
            padding-top: 20px;
            padding-bottom: 20px;   
            backdrop-filter: blur(10px);
        }

        #header_form {
            text-align: center;
            margin-bottom: 30px;
        }

        #header_form h1 {
            font-size: 32px;
            color: #1b3276;
            font-weight: 600;
        }

        .input_content {
            width: 100%;
            height: 55px;
            padding: 0 20px;
            margin: 15px 0;
            border: 2px solid #e1e5ee;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }

        .input_content:focus {
            border-color: #1b3276;
            box-shadow: 0 0 0 3px rgba(27, 50, 118, 0.1);
            outline: none;
        }

        .help-section {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin: 25px 0;
        }

        .forgot-password {
            text-align: right;
        }

        .forgot-password a {
            color: #1b3276;
            text-decoration: none;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .forgot-password a:hover {
            color: #f8843d;
        }

        .login-btn {
            width: 100%;
            height: 55px;
            background: linear-gradient(135deg, #1b3276 0%, #2a4494 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 15px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .login-btn:hover {
            background: linear-gradient(135deg, #f8843d 0%, #f9965f 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(248, 132, 61, 0.3);
        }

        .register-section {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e5ee;
        }

        .register-text {
            color: #666;
            font-size: 16px;
            margin-bottom: 15px;
        }

        .register-link {
            color: #1b3276;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .register-link:hover {
            color: #f8843d;
            background: rgba(248, 132, 61, 0.1);
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }

            .logo-container {
                flex-direction: column;
                gap: 20px;
            }

            .school-info {
                border-left: none;
                padding-left: 0;
                margin-top: 20px;
                text-align: center;
            }

            .school-type, .school-name {
                font-size: 20px;
            }

            .form_login {
                width: 90%;
                padding: 30px;
                max-width: 450px;
            }
        }

        .verification-row {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .verification-input {
            flex: 1;
        }

        .send-code-btn {
            width: 150px;
            height: 55px;
            background: linear-gradient(135deg, #1b3276 0%, #2a4494 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .send-code-btn:hover {
            background: linear-gradient(135deg, #f8843d 0%, #f9965f 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(248, 132, 61, 0.3);
        }

        .verification-section {
            display: none;
        }
        
        .verification-section.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="form_login">
            <form id="resetForm" action="" method="POST">
                <div id="header_form">
                    <h1>QUÊN MẬT KHẨU</h1>
                </div>
                <div id="input_form">
                    <label for="userName">Tên đăng nhập</label>
                    <input class="input_content" type="text" id="userName" name="userName" placeholder="Tên đăng nhập" required>

                    <label for="phone">Số điện thoại</label>
                    <input class="input_content" type="text" id="phone" name="phone" placeholder="Số điện thoại" required>

                    <label for="email">Email</label>
                    <input class="input_content" type="email" id="email" name="email" placeholder="Email" required>

                    <div class="verification-row">
                        <div class="verification-input">
                            <label for="verification_code">Mã xác nhận</label>
                            <div style = "display: flex; align-items: center; justify-content: center;">
                            <input class="input_content" style="width: 70%; margin-right: 20px" type="text" id="verification_code" name="verification_code" placeholder="Nhập mã xác nhận" required>
                            <button type="button" id="verifyInfoBtn" class="send-code-btn">Gửi mã</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; gap: 10px; margin-top: 20px;">
                    <button type="button" id="verifyCodeBtn" class="login-btn">Xác nhận</button>
                    <a href="login.php" class="login-btn" style="background: #ccc; color: #1b3276; text-align: center; text-decoration: none; line-height: 55px;">Quay lại</a>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.getElementById('verifyInfoBtn').addEventListener('click', function() {
        const formData = new FormData();
        formData.append('userName', document.getElementById('userName').value);
        formData.append('phone', document.getElementById('phone').value);
        formData.append('email', document.getElementById('email').value);
        formData.append('verify_info', true);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href, true);
        xhr.onload = function() {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.status === 'success') {
                    alert(response.message);
                } else {
                    alert('Lỗi: ' + response.message);
                }
            } catch (e) {
                console.error('Lỗi parse response:', e);
                console.error('Response text:', xhr.responseText);
                alert('Có lỗi xảy ra. Vui lòng thử lại sau.');
            }
        };
        xhr.onerror = function() {
            console.error('Lỗi kết nối');
            alert('Có lỗi xảy ra. Vui lòng thử lại sau.');
        };
        xhr.send(formData);
    });

    document.getElementById('verifyCodeBtn').addEventListener('click', function() {
        const formData = new FormData();
        formData.append('verification_code', document.getElementById('verification_code').value);
        formData.append('email', document.getElementById('email').value);
        formData.append('verify_code', true);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href, true);
        xhr.onload = function() {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.status === 'success') {
                    alert(response.message);
                    window.location.href = 'login.php';
                } else {
                    alert(response.message);
                }
            } catch (e) {
                alert('Có lỗi xảy ra. Vui lòng thử lại sau.');
            }
        };
        xhr.onerror = function() {
            alert('Có lỗi xảy ra. Vui lòng thử lại sau.');
        };
        xhr.send(formData);
    });
    </script>
</body>
</html>
