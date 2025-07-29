<?php
session_start();

// Kiểm tra session
if (!isset($_SESSION['employeeID'])) {
    header("Location: ../../login/login.php");
    exit();
}
// Include file kết nối cơ sở dữ liệu
require_once '../../../connection/connection.php';

$fullName = $_SESSION['fullName'];
$role = $_SESSION['role'] ?? 'Giảng viên';

$successMessage = '';
$errorMessage = '';

try {
    if (!isset($conn) || !$conn instanceof PDO) {
        throw new Exception("Kết nối cơ sở dữ liệu không tồn tại hoặc không hợp lệ.");
    }

    $employeeID = $_SESSION['employeeID'];
    $stmt = $conn->prepare("SELECT teacherID, fullName, birth, gender, email, phone, address, hireDate, image, major, academicTitle, leadershipPosition, faculty, note, role, rankTeacher FROM employee WHERE employeeID = :employeeID");
    $stmt->bindParam(':employeeID', $employeeID, PDO::PARAM_INT);
    $stmt->execute();
    $employeeData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employeeData) {
        throw new Exception("Không tìm thấy thông tin nhân viên với employeeID: $employeeID");
    }

    // Xử lý cập nhật thông tin khi submit form
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
        $fullName = filter_var($_POST['fullName'], FILTER_SANITIZE_STRING);
        $birth = filter_var($_POST['birth'], FILTER_SANITIZE_STRING);
        $gender = filter_var($_POST['gender'], FILTER_SANITIZE_STRING);
        $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $address = filter_var($_POST['address'], FILTER_SANITIZE_STRING);
        $major = filter_var($_POST['major'], FILTER_SANITIZE_STRING);
        $hireDate = filter_var($_POST['hireDate'], FILTER_SANITIZE_STRING);
        $academicTitle = filter_var($_POST['academicTitle'], FILTER_SANITIZE_STRING);
        $leadershipPosition = $_POST['leadershipPosition'];
        $faculty = filter_var($_POST['faculty'], FILTER_SANITIZE_STRING);
        $teacherID = filter_var($_POST['teacherID'], FILTER_SANITIZE_STRING);
        $role = filter_var($_POST['role'], FILTER_SANITIZE_STRING);
        $rankTeacher = ($academicTitle === 'Giảng viên') ? filter_var($_POST['rankTeacher'], FILTER_SANITIZE_STRING) : null;
        $note = filter_var($_POST['note'], FILTER_SANITIZE_STRING);

        // Chuyển định dạng ngày từ dd/mm/yyyy sang yyyy-mm-dd để lưu vào database
        $birthDate = DateTime::createFromFormat('d/m/Y', $birth);
        $birth = $birthDate ? $birthDate->format('Y-m-d') : $birth;
        $hireDateObj = DateTime::createFromFormat('d/m/Y', $hireDate);
        $hireDate = $hireDateObj ? $hireDateObj->format('Y-m-d') : $hireDate;

        // Xử lý upload ảnh
        $uploadDir = '../../assets/uploads/';
        $defaultImage = '../../assets/images/avatar-default.png';
        $imagePath = $employeeData['image'] ?: $defaultImage;

        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $imageName = $employeeID . '.jpg';
            $imagePath = $uploadDir . $imageName;

            if (file_exists($imagePath) && $imagePath !== $defaultImage) {
                unlink($imagePath);
            }

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $imagePath)) {
                throw new Exception("Không thể tải lên ảnh.");
            }
        }

        // Cập nhật thông tin vào cơ sở dữ liệu
        $updateStmt = $conn->prepare("UPDATE employee SET teacherID = :teacherID, fullName = :fullName, birth = :birth, gender = :gender, email = :email, phone = :phone, address = :address, hireDate = :hireDate, image = :image, major = :major, academicTitle = :academicTitle, leadershipPosition = :leadershipPosition, faculty = :faculty, note = :note, role = :role, rankTeacher = :rankTeacher WHERE employeeID = :employeeID");
        $updateStmt->bindParam(':teacherID', $teacherID);
        $updateStmt->bindParam(':fullName', $fullName);
        $updateStmt->bindParam(':birth', $birth);
        $updateStmt->bindParam(':gender', $gender);
        $updateStmt->bindParam(':email', $email);
        $updateStmt->bindParam(':phone', $phone);
        $updateStmt->bindParam(':address', $address);
        $updateStmt->bindParam(':hireDate', $hireDate);
        $updateStmt->bindParam(':image', $imagePath);
        $updateStmt->bindParam(':major', $major);
        $updateStmt->bindParam(':academicTitle', $academicTitle);
        $updateStmt->bindParam(':leadershipPosition', $leadershipPosition);
        $updateStmt->bindParam(':faculty', $faculty);
        $updateStmt->bindParam(':note', $note);
        $updateStmt->bindParam(':role', $role);
        $updateStmt->bindParam(':rankTeacher', $rankTeacher);
        $updateStmt->bindParam(':employeeID', $employeeID, PDO::PARAM_INT);

        if ($updateStmt->execute()) {
            $successMessage = "Cập nhật thông tin thành công!";
            $_SESSION['fullName'] = $fullName;
            $_SESSION['role'] = $role;
            $employeeData = $conn->query("SELECT * FROM employee WHERE employeeID = $employeeID")->fetch(PDO::FETCH_ASSOC);
        } else {
            throw new Exception("Không thể cập nhật thông tin. Vui lòng thử lại!");
        }
    }
} catch (Exception $e) {
    $errorMessage = "Lỗi: " . htmlspecialchars($e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $update_success = true;

    if ($update_success) {
        echo "<script>
            window.parent.showPopup('Cập nhật thông tin thành công!', 'success');
        </script>";
    } else {
        echo "<script>
            window.parent.showPopup('Cập nhật thông tin thất bại!', 'error');
        </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông tin tài khoản</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
        }

        .main-content {
            flex: 1;
            padding: 20px;
        }

        .form-title {
            color: #223771;
            font-size: 24px;
            margin-bottom: 20px;
            text-align: center;
        }

        .research-form table {
            width: 100%;
            border-collapse: collapse;
            max-width: 600px;
            margin: 0 auto;
        }

        .research-form table tr {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .research-form table td {
            flex: 1;
            padding: 10px;
            vertical-align: top;
        }

        .research-form label {
            font-weight: bold;
            color: #223771;
            display: block;
            margin-bottom: 5px;
        }

        .research-form input[type="text"],
        .research-form input[type="email"],
        .research-form input[type="date"],
        .research-form select,
        .research-form textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }

        .research-form textarea {
            resize: vertical;
            height: 100px;
        }

        .research-form input[type="file"] {
            padding: 6px 0;
            width: 100%;
        }

        .research-form button {
            background-color: #223771;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            margin-top: 20px;
        }

        .research-form button:hover {
            background-color: #f8843d;
        }

        .avatar-container {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
            position: relative;
        }

        .avatar-container img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid #ddd;
        }

        .avatar-hint {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
        }

        .avatar-container:hover .avatar-hint {
            display: block;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="research-form">
            <h2 class="form-title">Thông tin tài khoản</h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="avatar-container">
                    <input type="file" id="image" name="image" accept="image/*" style="display: none;" onchange="previewImage(event)">
                    <img id="avatar-preview" src="<?php echo isset($employeeData['image']) && !empty($employeeData['image']) && file_exists($employeeData['image']) ? htmlspecialchars($employeeData['image']) : '../../assets/images/avatar-default.png'; ?>" alt="Avatar" onclick="document.getElementById('image').click();">
                    <div class="avatar-hint">Nhấp để thay đổi ảnh</div>
                </div>
                <table>
                    <tr>
                        <td><label for="fullName">Họ và tên:</label><input type="text" id="fullName" name="fullName" value="<?php echo htmlspecialchars($fullName); ?>" required></td>
                        <td><label for="birth">Ngày sinh:</label><input type="text" id="birth" name="birth" value="<?php echo isset($employeeData['birth']) ? date('d/m/Y', strtotime($employeeData['birth'])) : ''; ?>" placeholder="dd/mm/yyyy" required></td>
                    </tr>
                    <tr>
                        <td><label for="gender">Giới tính:</label>
                            <select id="gender" name="gender" required>
                                <option value="Nam" <?php echo (isset($employeeData['gender']) && $employeeData['gender'] == 'Nam') ? 'selected' : ''; ?>>Nam</option>
                                <option value="Nữ" <?php echo (isset($employeeData['gender']) && $employeeData['gender'] == 'Nữ') ? 'selected' : ''; ?>>Nữ</option>
                            </select>
                        </td>
                        <td><label for="phone">Số điện thoại:</label><input type="text" id="phone" name="phone" value="<?php echo isset($employeeData['phone']) ? htmlspecialchars($employeeData['phone']) : ''; ?>" required></td>
                    </tr>
                    <tr>
                        <td><label for="hireDate">Ngày tuyển dụng:</label><input type="text" id="hireDate" name="hireDate" value="<?php echo isset($employeeData['hireDate']) ? date('d/m/Y', strtotime($employeeData['hireDate'])) : ''; ?>" placeholder="dd/mm/yyyy" required></td>
                        <td><label for="teacherID">Mã nhân viên:</label><input type="text" id="teacherID" name="teacherID" value="<?php echo htmlspecialchars($employeeData['teacherID']); ?>" required></td>
                    </tr>
                    <tr>
                        <td><label for="email">Email:</label><input type="email" id="email" name="email" value="<?php echo isset($employeeData['email']) ? htmlspecialchars($employeeData['email']) : ''; ?>" required></td>
                        <td><label for="major">Chuyên ngành:</label><input type="text" id="major" name="major" value="<?php echo isset($employeeData['major']) ? htmlspecialchars($employeeData['major']) : ''; ?>" required></td>
                    </tr>
                    <tr>
                        <td><label for="address">Địa chỉ:</label><input type="text" id="address" name="address" value="<?php echo isset($employeeData['address']) ? htmlspecialchars($employeeData['address']) : ''; ?>" required></td>
                        <td><label for="role">Vai trò:</label>
                            <select id="role" name="role" required>
                                <option value="Giảng viên" <?php echo (isset($employeeData['role']) && $employeeData['role'] == 'Giảng viên') ? 'selected' : ''; ?>>Giảng viên</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="academicTitle">Chức danh giảng viên:</label>
                            <select id="academicTitle" name="academicTitle">
                                <option value="" <?php echo (isset($employeeData['academicTitle']) && $employeeData['academicTitle'] == '') ? 'selected' : ''; ?>>Không chọn</option>
                                <option value="Giảng viên" <?php echo (isset($employeeData['academicTitle']) && $employeeData['academicTitle'] == 'Giảng viên') ? 'selected' : ''; ?>>Giảng viên</option>
                                <option value="Giảng viên (tập sự)" <?php echo (isset($employeeData['academicTitle']) && $employeeData['academicTitle'] == 'Giảng viên (tập sự)') ? 'selected' : ''; ?>>Giảng viên (tập sự)</option>
                                <option value="Trợ giảng" <?php echo (isset($employeeData['academicTitle']) && $employeeData['academicTitle'] == 'Trợ giảng') ? 'selected' : ''; ?>>Trợ giảng</option>
                                <option value="Trợ giảng (tập sự)" <?php echo (isset($employeeData['academicTitle']) && $employeeData['academicTitle'] == 'Trợ giảng (tập sự)') ? 'selected' : ''; ?>>Trợ giảng (tập sự)</option>
                            </select>
                        </td>
                        <td><label for="rankTeacher">Cấp hạng giảng viên:</label>
                            <select id="rankTeacher" name="rankTeacher" <?php echo ($employeeData['academicTitle'] !== 'Giảng viên') ? 'disabled' : ''; ?>>
                                <option value="Hạng I" <?php echo (isset($employeeData['rankTeacher']) && $employeeData['rankTeacher'] == 'Hạng I') ? 'selected' : ''; ?>>Hạng I (Giảng viên cao cấp)</option>
                                <option value="Hạng II" <?php echo (isset($employeeData['rankTeacher']) && $employeeData['rankTeacher'] == 'Hạng II') ? 'selected' : ''; ?>>Hạng II (Giảng viên chính)</option>
                                <option value="Hạng III" <?php echo (isset($employeeData['rankTeacher']) && $employeeData['rankTeacher'] == 'Hạng III') ? 'selected' : ''; ?>>Hạng III (Giảng viên/trợ giảng)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="leadershipPosition">Vai trò khác:</label>
                            <select id="leadershipPosition" name="leadershipPosition">
                                <option value="" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == '') ? 'selected' : ''; ?>>Không</option>
                                <option value="Chủ tịch Hội đồng trường, Hiệu trưởng" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Chủ tịch Hội đồng trường, Hiệu trưởng') ? 'selected' : ''; ?>>Chủ tịch Hội đồng trường, Hiệu trưởng</option>
                                <option value="Phó chủ tịch Hội đồng trường, Phó hiệu trưởng" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Phó chủ tịch Hội đồng trường, Phó hiệu trưởng') ? 'selected' : ''; ?>>Phó chủ tịch Hội đồng trường, Phó hiệu trưởng</option>
                                <option value="Trưởng phòng, Giám đốc trung tâm, Thư ký Hội đồng trường" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Trưởng phòng, Giám đốc trung tâm, Thư ký Hội đồng trường') ? 'selected' : ''; ?>>Trưởng phòng, Giám đốc trung tâm, Thư ký Hội đồng trường</option>
                                <option value="Phó trưởng phòng, Phó Giám đốc trung tâm" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Phó trưởng phòng, Phó Giám đốc trung tâm') ? 'selected' : ''; ?>>Phó trưởng phòng, Phó Giám đốc trung tâm</option>
                                <option value="Trưởng khoa, Trưởng Bộ môn trực thuộc trường (≥ 40 giảng viên hoặc ≥ 800 người học)" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Trưởng khoa, Trưởng Bộ môn trực thuộc trường (≥ 40 giảng viên hoặc ≥ 800 người học)') ? 'selected' : ''; ?>>Trưởng khoa, Trưởng Bộ môn trực thuộc trường (≥ 40 giảng viên hoặc ≥ 800 người học)</option>
                                <option value="Trưởng khoa, Trưởng Bộ môn trực thuộc trường (< 40 giảng viên hoặc < 800 người học)" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Trưởng khoa, Trưởng Bộ môn trực thuộc trường (< 40 giảng viên hoặc < 800 người học)') ? 'selected' : ''; ?>>Trưởng khoa, Trưởng Bộ môn trực thuộc trường (< 40 giảng viên hoặc < 800 người học)</option>
                                <option value="Phó trưởng khoa, Phó trưởng bộ môn trực thuộc trường (≥ 40 giảng viên hoặc ≥ 800 SV)" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Phó trưởng khoa, Phó trưởng bộ môn trực thuộc trường (≥ 40 giảng viên hoặc ≥ 800 SV)') ? 'selected' : ''; ?>>Phó trưởng khoa, Phó trưởng bộ môn trực thuộc trường (≥ 40 giảng viên hoặc ≥ 800 SV)</option>
                                <option value="Phó trưởng khoa, Phó trưởng bộ môn trực thuộc trường (< 40 giảng viên hoặc < 800 SV)" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Phó trưởng khoa, Phó trưởng bộ môn trực thuộc trường (< 40 giảng viên hoặc < 800 SV)') ? 'selected' : ''; ?>>Phó trưởng khoa, Phó trưởng bộ môn trực thuộc trường (< 40 giảng viên hoặc < 800 SV)</option>
                                <option value="Trưởng bộ môn trực thuộc khoa" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Trưởng bộ môn trực thuộc khoa') ? 'selected' : ''; ?>>Trưởng bộ môn trực thuộc khoa</option>
                                <option value="Phó trưởng bộ môn trực thuộc khoa" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Phó trưởng bộ môn trực thuộc khoa') ? 'selected' : ''; ?>>Phó trưởng bộ môn trực thuộc khoa</option>
                                <option value="Chủ nhiệm lớp, Cố vấn học tập (được cộng dồn)" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Chủ nhiệm lớp, Cố vấn học tập (được cộng dồn)') ? 'selected' : ''; ?>>Chủ nhiệm lớp, Cố vấn học tập (được cộng dồn)</option>
                                <option value="Bí thư đảng ủy" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Bí thư đảng ủy') ? 'selected' : ''; ?>>Bí thư đảng ủy</option>
                                <option value="Phó bí thư Đảng ủy" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Phó bí thư Đảng ủy') ? 'selected' : ''; ?>>Phó bí thư Đảng ủy</option>
                                <option value="Bí thư chi bộ, Trưởng ban TTND, Trưởng Ban nữ công, Chủ tịch Hội CCB" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Bí thư chi bộ, Trưởng ban TTND, Trưởng Ban nữ công, Chủ tịch Hội CCB') ? 'selected' : ''; ?>>Bí thư chi bộ, Trưởng ban TTND, Trưởng Ban nữ công, Chủ tịch Hội CCB</option>
                                <option value="Phó Bí thư chi bộ" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Phó Bí thư chi bộ') ? 'selected' : ''; ?>>Phó Bí thư chi bộ</option>
                                <option value="Giảng viên làm công tác quốc phòng, quân sự không chuyên trách" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Giảng viên làm công tác quốc phòng, quân sự không chuyên trách') ? 'selected' : ''; ?>>Giảng viên làm công tác quốc phòng, quân sự không chuyên trách</option>
                                <option value="Bí thư đoàn trường" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Bí thư đoàn trường') ? 'selected' : ''; ?>>Bí thư đoàn trường</option>
                                <option value="Phó bí thư đoàn trường" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Phó bí thư đoàn trường') ? 'selected' : ''; ?>>Phó bí thư đoàn trường</option>
                                <option value="Bí thư Liên chi đoàn (≥ 1,000 SV)" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Bí thư Liên chi đoàn (≥ 1,000 SV)') ? 'selected' : ''; ?>>Bí thư Liên chi đoàn (≥ 1,000 SV)</option>
                                <option value="Bí thư Liên chi đoàn (500 - 1,000 SV)" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Bí thư Liên chi đoàn (500 - 1,000 SV)') ? 'selected' : ''; ?>>Bí thư Liên chi đoàn (500 - 1,000 SV)</option>
                                <option value="Bí thư Liên chi đoàn (< 500 SV)" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Bí thư Liên chi đoàn (< 500 SV)') ? 'selected' : ''; ?>>Bí thư Liên chi đoàn (< 500 SV)</option>
                                <option value="Giảng viên nữ có con nhỏ dưới 12 tháng" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Giảng viên nữ có con nhỏ dưới 12 tháng') ? 'selected' : ''; ?>>Giảng viên nữ có con nhỏ dưới 12 tháng</option>
                                <option value="Cán bộ giảng dạy kiêm quản lý phòng thí nghiệm, thực hành công nghệ, phòng máy" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Cán bộ giảng dạy kiêm quản lý phòng thí nghiệm, thực hành công nghệ, phòng máy') ? 'selected' : ''; ?>>Cán bộ giảng dạy kiêm quản lý phòng thí nghiệm, thực hành công nghệ, phòng máy</option>
                                <option value="Chủ tịch Công đoàn trường, Phó chủ tịch Công đoàn trường" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Chủ tịch Công đoàn trường, Phó chủ tịch Công đoàn trường') ? 'selected' : ''; ?>>Chủ tịch Công đoàn trường, Phó chủ tịch Công đoàn trường</option>
                                <option value="Ủy viên BCH công đoàn trường, tổ trưởng, tổ phó tổ công đoàn" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Ủy viên BCH công đoàn trường, tổ trưởng, tổ phó tổ công đoàn') ? 'selected' : ''; ?>>Ủy viên BCH công đoàn trường, tổ trưởng, tổ phó tổ công đoàn</option>
                                <option value="Giảng viên làm thay công tác trợ lý khoa nghỉ việc" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Giảng viên làm thay công tác trợ lý khoa nghỉ việc') ? 'selected' : ''; ?>>Giảng viên làm thay công tác trợ lý khoa nghỉ việc</option>
                                <option value="Giảng viên đang là quân nhân dự bị, tự vệ được triệu tập huấn luyện, diễn tập" <?php echo (isset($employeeData['leadershipPosition']) && $employeeData['leadershipPosition'] == 'Giảng viên đang là quân nhân dự bị, tự vệ được triệu tập huấn luyện, diễn tập') ? 'selected' : ''; ?>>Giảng viên đang là quân nhân dự bị, tự vệ được triệu tập huấn luyện, diễn tập</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="faculty">Khoa:</label>
                            <select id="faculty" name="faculty">
                                <option value="" <?php echo (isset($employeeData['faculty']) && $employeeData['faculty'] == '') ? 'selected' : ''; ?>>Không chọn</option>
                                <option value="Đất đai" <?php echo (isset($employeeData['faculty']) && $employeeData['faculty'] == 'Đất đai') ? 'selected' : ''; ?>>Đất đai</option>
                                <option value="Công nghệ thông tin" <?php echo (isset($employeeData['faculty']) && $employeeData['faculty'] == 'Công nghệ thông tin') ? 'selected' : ''; ?>>Công nghệ thông tin</option>
                                <option value="Kinh tế" <?php echo (isset($employeeData['faculty']) && $employeeData['faculty'] == 'Kinh tế') ? 'selected' : ''; ?>>Kinh tế</option>
                                <option value="Địa Chất" <?php echo (isset($employeeData['faculty']) && $employeeData['faculty'] == 'Địa Chất') ? 'selected' : ''; ?>>Địa Chất</option>
                                <option value="Môi Trường" <?php echo (isset($employeeData['faculty']) && $employeeData['faculty'] == 'Môi Trường') ? 'selected' : ''; ?>>Môi Trường</option>
                                <option value="Khí tượng văn học" <?php echo (isset($employeeData['faculty']) && $employeeData['faculty'] == 'Khí tượng văn học') ? 'selected' : ''; ?>>Khí tượng văn học</option>
                            </select>
                        </td>
                        <td><label for="note">Ghi chú:</label><textarea id="note" name="note"><?php echo isset($employeeData['note']) ? htmlspecialchars($employeeData['note']) : ''; ?></textarea></td>
                    </tr>
                    <tr>
                        <td colspan="2"><button type="submit" name="update">Cập nhật</button></td>
                    </tr>
                </table>
            </form>
        </div>
    </div>

    <script>
        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatar-preview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }

        const academicTitle = document.getElementById('academicTitle');
        const rankTeacher = document.getElementById('rankTeacher');

        academicTitle.addEventListener('change', function() {
            if (this.value === "Giảng viên") {
                rankTeacher.disabled = false;
                rankTeacher.value = ""; // Reset về trạng thái chọn
            } else {
                rankTeacher.disabled = true;
                rankTeacher.value = ""; // Đặt về "Không"
            }
        });

        // Initialize the state of the rankTeacher dropdown on page load
        window.addEventListener('DOMContentLoaded', function() {
            if (academicTitle.value !== "Giảng viên") {
                rankTeacher.disabled = true;
                rankTeacher.value = ""; // Đặt về "Không"
            }
        });

        function showPopup(message, type) {
            // Thêm code hiển thị popup tùy theo giao diện của bạn
            alert(message);
        }
    </script>
</body>
</html>