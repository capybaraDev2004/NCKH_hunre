<?php
session_start();

if (!isset($_SESSION['employeeID'])) {
    header("Location: ../../login/login.php");
    exit();
}

$fullName = $_SESSION['fullName'];
$role = $_SESSION['role'] ?? 'Giảng viên';

require_once '../../../connection/connection.php';

$successMessage = '';
$errorMessage = '';

try {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['changePassword'])) {
        $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
        $oldPassword = filter_var($_POST['oldPassword'], FILTER_SANITIZE_STRING);
        $newPassword = filter_var($_POST['newPassword'], FILTER_SANITIZE_STRING);
        $confirmPassword = filter_var($_POST['confirmPassword'], FILTER_SANITIZE_STRING);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);

        $employeeID = $_SESSION['employeeID'];
        $stmt = $conn->prepare("SELECT userName, password, email, phone FROM employee WHERE employeeID = :employeeID");
        $stmt->bindParam(':employeeID', $employeeID, PDO::PARAM_INT);
        $stmt->execute();
        $employeeData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employeeData) {
            throw new Exception("Không tìm thấy thông tin nhân viên trong cơ sở dữ liệu.");
        }

        if ($employeeData['userName'] !== $username) {
            throw new Exception("Tên đăng nhập không khớp.");
        }
        if ($employeeData['email'] !== $email) {
            throw new Exception("Email không khớp.");
        }
        if ($employeeData['phone'] !== $phone) {
            throw new Exception("Số điện thoại không khớp.");
        }
        if (!password_verify($oldPassword, $employeeData['password'])) {
            throw new Exception("Mật khẩu cũ không đúng.");
        }

        if ($newPassword !== $confirmPassword) {
            throw new Exception("Mật khẩu mới và xác nhận mật khẩu không khớp!");
        }

        $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $updateStmt = $conn->prepare("UPDATE employee SET password = :password WHERE employeeID = :employeeID");
        $updateStmt->bindParam(':password', $hashedNewPassword, PDO::PARAM_STR);
        $updateStmt->bindParam(':employeeID', $employeeID, PDO::PARAM_INT);
        $rowsAffected = $updateStmt->execute();

        if ($rowsAffected) {
            $successMessage = "Đổi mật khẩu thành công!";
        } else {
            throw new Exception("Không thể cập nhật mật khẩu. Vui lòng thử lại!");
        }
    }
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đổi mật khẩu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f6f8fb;
            width: 842px;
            height: 690px;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        .form-title {
            color: #223771;
            font-size: 30px;
            margin-bottom: 32px;
            text-align: center;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .research-form table {
            width: 100%;
        }

        .research-form table td {
            padding: 8px 0;
            vertical-align: middle;
        }

        .research-form table td:first-child {
            width: 160px;
            text-align: right;
            padding-right: 12px;
        }

        .research-form table td:last-child {
            text-align: left;
            padding-left: 0;
        }

        .research-form label {
            font-weight: 600;
            color: #223771;
            display: block;
            margin-bottom: 7px;
            font-size: 16px;
        }

        .research-form input[type="text"],
        .research-form input[type="email"],
        .research-form input[type="password"] {
            width: 90%;
            padding: 11px 14px;
            border: 1.5px solid #e0e4ea;
            border-radius: 7px;
            font-size: 15px;
            background: #f8fafc;
            transition: border 0.2s;
        }

        .research-form input[type="text"]:focus,
        .research-form input[type="email"]:focus,
        .research-form input[type="password"]:focus {
            border-color: #f8843d;
            outline: none;
            background: #fff;
        }

        .research-form button {
            background: linear-gradient(90deg, #223771 60%, #f8843d 100%);
            color: white;
            padding: 13px 0;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 600;
            width: 100%;
            margin-top: 22px;
            box-shadow: 0 2px 8px rgba(248, 132, 61, 0.08);
            transition: background 0.2s;
        }

        .research-form button:hover {
            background: linear-gradient(90deg, #f8843d 0%, #223771 100%);
        }

        @media (max-width: 900px) {
            body {
                width: 100vw;
                height: 100vh;
            }
            .research-form {
                max-width: 98vw;
                min-width: unset;
                padding: 18px 8px 12px 8px;
            }
            .form-title {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="research-form">
        <h2 class="form-title">Đổi mật khẩu</h2>
        <form method="POST" action="">
            <table>
                <tr>
                    <td><label for="username">Tên đăng nhập:</label></td>
                    <td><input type="text" id="username" name="username" value="" required></td>
                </tr>
                <tr>
                    <td><label for="oldPassword">Mật khẩu cũ:</label></td>
                    <td><input type="password" id="oldPassword" name="oldPassword" minlength="8" required></td>
                </tr>
                <tr>
                    <td><label for="newPassword">Mật khẩu mới:</label></td>
                    <td><input type="password" id="newPassword" name="newPassword" minlength="8" required></td>
                </tr>
                <tr>
                    <td><label for="confirmPassword">Xác nhận mật khẩu mới:</label></td>
                    <td><input type="password" id="confirmPassword" name="confirmPassword" minlength="8" required></td>
                </tr>
                <tr>
                    <td><label for="email">Email:</label></td>
                    <td><input type="email" id="email" name="email" value="" required></td>
                </tr>
                <tr>
                    <td><label for="phone">Số điện thoại:</label></td>
                    <td><input type="text" id="phone" name="phone" value="" required></td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align: center;">
                        <button type="submit" name="changePassword">Đổi mật khẩu</button>
                    </td>
                </tr>
            </table>
        </form>
        <?php if ($successMessage): ?>
            <script>parent.showPopup('<?php echo addslashes($successMessage); ?>', 'success');</script>
        <?php elseif ($errorMessage): ?>
            <script>parent.showPopup('<?php echo addslashes($errorMessage); ?>', 'error');</script>
        <?php endif; ?>
    </div>
</body>
</html>