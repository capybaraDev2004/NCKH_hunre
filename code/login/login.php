<?php
require '../connection/connection.php';

// Kiểm tra xem form đã được gửi hay chưa
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lấy dữ liệu từ form đăng nhập
    $username = trim($_POST["userName"]);
    $password = trim($_POST["password"]);

    // Sử dụng prepared statement để kiểm tra thông tin đăng nhập
    $sql = "SELECT password, fullName, role, employeeID FROM employee WHERE userName = :username";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        die("Lỗi chuẩn bị truy vấn: " . htmlspecialchars($conn->errorInfo()[2]));
    }

    // Bind giá trị vào tham số
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();

    // Lấy dữ liệu từ cơ sở dữ liệu
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // Lấy thông tin người dùng từ cơ sở dữ liệu
        $hashed_password_from_db = $row['password'];
        $role = $row['role']; // Lấy giá trị role
        $fullName = $row['fullName']; // Lấy giá trị fullName
        $employeeID = $row['employeeID']; // Lấy giá trị employeeID
        $teacherID = $row['teacherID']; // Lấy giá trị teacherID

        // Kiểm tra mật khẩu
        if (password_verify($password, $hashed_password_from_db)) {
            // Mật khẩu đúng, đăng nhập thành công
            session_start(); // Khởi động session
            $_SESSION['userName'] = $username;
            $_SESSION['fullName'] = $fullName;
            $_SESSION['role'] = $role;
            $_SESSION['employeeID'] = $employeeID;
            $_SESSION['teacherID'] = $teacherID;
            $_SESSION['academicTitle'] = $academicTitle;
            $_SESSION['leadershipPosition'] = $leadershipPosition;
            // Nếu có cột image trong database, bạn cần lấy nó ở đây, ví dụ:
            // $_SESSION['image'] = $row['image'];

            switch($role){
                case 'Chuyên viên':
                    header('Location: ../trangchu.php');
                    break;
                case 'Quản trị viên':
                    header('Location: ../trangchu.php');
                    break;
                case 'Ban giám hiệu':
                    header('Location: ../trangchu.php');
                    break;
                case 'Giảng viên':
                    header('Location: ../trangchu.php');
                    break;
                default:
                    echo "Chưa được phân quyền, liên hệ quản trị viên để được hỗ trợ";
                    header('Location: login.php');
            }
            exit();
        } else {
            // Mật khẩu sai
            echo "<script>alert('Sai tên đăng nhập hoặc mật khẩu!'); window.location.href = 'login.php';</script>";
        }
    } else {
        // Không tìm thấy người dùng
        echo "<script>alert('Sai tên đăng nhập hoặc mật khẩu!'); window.location.href = 'login.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Trường Đại học Tài nguyên và Môi trường Hà Nội</title>
    <link rel="stylesheet" href="font/themify-icons-font/themify-icons/themify-icons.css">
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
    </style>
</head>
<body>
    <!-- Header với Logo -->
    <header class="header">
        <div class="header-content">
            <div class="logo-container">
                <img class="logo-img" src="../assets/images/hunre_logo.png" alt="HUNRE Logo">
                <div class="school-info">
                    <div class="school-type">TRƯỜNG ĐẠI HỌC</div>
                    <div class="school-name">TÀI NGUYÊN VÀ MÔI TRƯỜNG HÀ NỘI</div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Form đăng nhập -->
        <div class="form_login">
            <form action="" method="POST">
                <div id="header_form">
                    <h1>ĐĂNG NHẬP</h1>
                </div>
                
                <div id="input_form">
                    <input class="input_content" type="text" name="userName" placeholder="Tên đăng nhập" required>
                    <input class="input_content" type="password" name="password" placeholder="Mật khẩu" required>
                </div>

                <div class="help-section">
                    <div class="forgot-password">
                        <a href="forgetPass.php">Quên mật khẩu?</a>
                    </div>
                </div>
                    
                <button name="dangnhap" class="login-btn" type="submit">Đăng nhập</button>

                <div class="register-section">
                    <p class="register-text">Bạn chưa có tài khoản?</p>
                    <a href="add_teacher.php" class="register-link">Đăng ký ngay</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
