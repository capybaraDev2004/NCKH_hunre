<?php
session_start();

// Include file kết nối cơ sở dữ liệu
require_once '../connection/connection.php';

$successMessage = '';
$errorMessage = '';

$formData = [
    'fullName' => '',
    'birth' => '',
    'gender' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'hireDate' => '',
    'role' => '',
    'teacherID' => '',
    'rankTeacher' => '',
    'academicTitle' => '',
    'leadershipPosition' => '',
    'faculty' => '',
    'major' => '',
    'userName' => '',
    'password' => ''
];

try {
    if (!isset($conn) || !$conn instanceof PDO) {
        throw new Exception("Kết nối cơ sở dữ liệu không tồn tại hoặc không hợp lệ.");
    }

    // Xử lý khi form được submit
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_employee'])) {
        // Lưu lại dữ liệu form ngay khi submit
        $formData = [
            'fullName' => $_POST['fullName'] ?? '',
            'birth' => $_POST['birth'] ?? '',
            'gender' => $_POST['gender'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'address' => $_POST['address'] ?? '',
            'hireDate' => $_POST['hireDate'] ?? '',
            'role' => $_POST['role'] ?? '',
            'teacherID' => $_POST['teacherID'] ?? '',
            'rankTeacher' => $_POST['rankTeacher'] ?? '',
            'academicTitle' => $_POST['academicTitle'] ?? '',
            'leadershipPosition' => $_POST['leadershipPosition'] ?? '',
            'faculty' => $_POST['faculty'] ?? '',
            'major' => $_POST['major'] ?? '',
            'userName' => $_POST['userName'] ?? '',
            'password' => $_POST['password'] ?? ''
        ];

        $errors = [];
        
        // Validate birth date
        $birthDate = DateTime::createFromFormat('d/m/Y', $_POST['birth']);
        if (!$birthDate) {
            $errors['birth'] = "Định dạng ngày sinh không hợp lệ (dd/mm/yyyy)";
        } else {
            $today = new DateTime();
            if ($birthDate >= $today) {
                $errors['birth'] = "Ngày sinh phải trước ngày hiện tại";
            }
        }

        // Validate phone number
        if (!preg_match('/^0[0-9]{9}$/', $_POST['phone'])) {
            $errors['phone'] = "Số điện thoại phải bắt đầu bằng số 0 và có 10 chữ số";
        } else {
            // Check if phone exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM employee WHERE phone = ?");
            $stmt->execute([$_POST['phone']]);
            if ($stmt->fetchColumn() > 0) {
                $errors['phone'] = "Số điện thoại đã tồn tại trong hệ thống";
            }
        }

        // Validate name and major (only letters and spaces)
        if (!preg_match('/^[\p{L}\s]+$/u', $_POST['fullName'])) {
            $errors['fullName'] = "Họ tên chỉ được chứa chữ cái";
        }
        if (!preg_match('/^[\p{L}\s]+$/u', $_POST['major'])) {
            $errors['major'] = "Chuyên ngành chỉ được chứa chữ cái";
        }

        // Validate teacherID existence
        $stmt = $conn->prepare("SELECT COUNT(*) FROM employee WHERE teacherID = ?");
        $stmt->execute([$_POST['teacherID']]);
        if ($stmt->fetchColumn() > 0) {
            $errors['teacherID'] = "Mã giảng viên đã tồn tại trong hệ thống";
        }

        // Nếu có lỗi, hiển thị thông báo lỗi
        if (!empty($errors)) {
            $errorMessage = "Vui lòng kiểm tra lại thông tin nhập vào:<br>";
            foreach ($errors as $field => $msg) {
                $errorMessage .= "- $msg<br>";
            }
        } else {
            // Xử lý dữ liệu form nếu không có lỗi
            $fullName = filter_var($_POST['fullName'], FILTER_SANITIZE_STRING);
            $birth = filter_var($_POST['birth'], FILTER_SANITIZE_STRING);
            $gender = filter_var($_POST['gender'], FILTER_SANITIZE_STRING);
            $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $address = filter_var($_POST['address'], FILTER_SANITIZE_STRING);
            $major = filter_var($_POST['major'], FILTER_SANITIZE_STRING);
            $hireDate = filter_var($_POST['hireDate'], FILTER_SANITIZE_STRING);
            $role = filter_var($_POST['role'], FILTER_SANITIZE_STRING);
            $userName = filter_var($_POST['userName'], FILTER_SANITIZE_STRING);
            $password = trim($_POST['password']);
            $academicTitle = filter_var($_POST['academicTitle'], FILTER_SANITIZE_STRING);
            $leadershipPosition = filter_var($_POST['leadershipPosition'], FILTER_SANITIZE_STRING);
            $faculty = filter_var($_POST['faculty'], FILTER_SANITIZE_STRING);
            $teacherID = filter_var($_POST['teacherID'], FILTER_SANITIZE_STRING); // Thêm teacherID
            $rankTeacher = ($academicTitle === 'Giảng viên') ? filter_var($_POST['rankTeacher'], FILTER_SANITIZE_STRING) : null;
            
            // Xử lý trường note bổ sung
            $note = '';
            if ($leadershipPosition === "Trưởng khoa, Trưởng Bộ môn trực thuộc trường" || 
                $leadershipPosition === "Phó trưởng khoa, Phó trưởng bộ môn trực thuộc trường") {
                $note = filter_var($_POST['scale'], FILTER_SANITIZE_STRING);
            } elseif ($leadershipPosition === "Giảng viên làm công tác Đoàn TN, Hội sinh viên, Hội liên hiệp thanh niên thực hiện theo Quyết định 13/2013/QĐ-TTg ngày 06/3/2013 của Thủ tướng chính phủ về chế độ chính sách đối với cán bộ Đoàn TNCSHCM, Hội sinh viên Việt Nam, Hội Liên hiệp thanh niên Việt Nam thực hiện như sau") {
                $note = filter_var($_POST['youth_role'], FILTER_SANITIZE_STRING);
                if ($_POST['youth_role'] === "Bí thư Liên chi đoàn") {
                    $note .= " - " . filter_var($_POST['union_scale'], FILTER_SANITIZE_STRING);
                }
            } elseif ($leadershipPosition === "Khác") {
                $note = filter_var($_POST['other_role'], FILTER_SANITIZE_STRING);
                if ($_POST['other_role'] === "Giảng viên làm công tác công đoàn không chuyên trách trong các cơ sở giáo dục đại học thực hiện theo quy định tại Thông tư số 08/2016/TT-BGDĐT ngày 28/3/2016 của Bộ trưởng bộ Giáo dục và Đào tạo") {
                    $note .= " - " . filter_var($_POST['union_position'], FILTER_SANITIZE_STRING);
                }
            }

            // Mã hóa mật khẩu
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Chuyển định dạng ngày
            $birthDateObj = DateTime::createFromFormat('d/m/Y', $birth);
            $birthDB = $birthDateObj ? $birthDateObj->format('Y-m-d') : $birth;

            $hireDateObj = DateTime::createFromFormat('d/m/Y', $hireDate);
            $hireDateDB = $hireDateObj ? $hireDateObj->format('Y-m-d') : $hireDate;

            // Xử lý upload ảnh
            $uploadDir = '../UC/assets/uploads/';  // Đường dẫn thực tế để upload file
            $dbImagePath = '../../assets/uploads/'; // Đường dẫn sẽ lưu vào database
            $defaultImage = '../UC/assets/images/avatar-default.png';
            $imagePath = $defaultImage;

            // Xóa phần code lấy MAX(employeeID) và thay thế bằng cách để database tự tăng
            if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                // Đợi database tự tăng employeeID
                $imageName = 'temp.jpg';
                $uploadPath = $uploadDir . $imageName;
                $imagePath = $dbImagePath . $imageName;
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                    throw new Exception("Không thể tải lên ảnh.");
                }
            }

            // Thêm nhân viên mới vào cơ sở dữ liệu (bao gồm teacherID và rankTeacher)
            $insertStmt = $conn->prepare("
                INSERT INTO employee (
                    teacherID, rankTeacher, fullName, birth, gender, email, phone, address, hireDate, image, 
                    role, userName, password, major, academicTitle, leadershipPosition, faculty, note
                ) VALUES (
                    :teacherID, :rankTeacher, :fullName, :birth, :gender, :email, :phone, :address, :hireDate, :image, 
                    :role, :userName, :password, :major, :academicTitle, :leadershipPosition, :faculty, :note
                )
            ");
            $insertStmt->bindParam(':teacherID', $teacherID);
            $insertStmt->bindParam(':rankTeacher', $rankTeacher);
            $insertStmt->bindParam(':fullName', $fullName);
            $insertStmt->bindParam(':birth', $birthDB);
            $insertStmt->bindParam(':gender', $gender);
            $insertStmt->bindParam(':email', $email);
            $insertStmt->bindParam(':phone', $phone);
            $insertStmt->bindParam(':address', $address);
            $insertStmt->bindParam(':hireDate', $hireDateDB);
            $insertStmt->bindParam(':image', $imagePath);
            $insertStmt->bindParam(':role', $role);
            $insertStmt->bindParam(':userName', $userName);
            $insertStmt->bindParam(':password', $hashedPassword);
            $insertStmt->bindParam(':major', $major);
            $insertStmt->bindParam(':academicTitle', $academicTitle);
            $insertStmt->bindParam(':leadershipPosition', $leadershipPosition);
            $insertStmt->bindParam(':faculty', $faculty);
            $insertStmt->bindParam(':note', $note);

            if ($insertStmt->execute()) {
                // Lấy employeeID vừa được tạo
                $newEmployeeID = $conn->lastInsertId();
                
                // Nếu có ảnh, đổi tên file ảnh theo employeeID
                if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
                    $newImageName = $newEmployeeID . '.jpg';
                    $newUploadPath = $uploadDir . $newImageName;
                    $newImagePath = $dbImagePath . $newImageName;
                    
                    // Đổi tên file
                    if (file_exists($uploadPath)) {
                        rename($uploadPath, $newUploadPath);
                        
                        // Cập nhật đường dẫn ảnh trong database
                        $updateImageStmt = $conn->prepare("UPDATE employee SET image = :image WHERE employeeID = :employeeID");
                        $updateImageStmt->execute([
                            ':image' => $newImagePath,
                            ':employeeID' => $newEmployeeID
                        ]);
                    }
                }
                
                $successMessage = "Thêm nhân viên mới thành công! Điều hướng về trang chủ sau 3 giây!";
                header('Refresh: 3; url=login.php');
            } else {
                $errorMessage = "Không thể thêm nhân viên. Vui lòng thử lại!";
            }
        }
    }
} catch (Exception $e) {
    $errorMessage = "Lỗi: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm nhân viên mới</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/material_blue.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        html {
            background: #1b3276;
            min-height: 100vh;
            color: white;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: #1b3276;
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

        .main-content {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            max-width: 1000px;
            margin: 20px auto;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
        }

        .form-title {
            color: #223771;
            font-size: 32px;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 600;
            position: relative;
            padding-bottom: 15px;
        }

        .form-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: linear-gradient(90deg, #223771, #f8843d);
            border-radius: 3px;
        }

        .avatar-container {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            position: relative;
        }

        .avatar-container img {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
            border: 3px solid #223771;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .avatar-container img:hover {
            transform: scale(1.05);
            border-color: #f8843d;
        }

        .avatar-hint {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(34, 55, 113, 0.9);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .avatar-container:hover .avatar-hint {
            opacity: 1;
        }

        .form-label {
            font-weight: 600;
            color: #223771;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border: 2px solid #e1e5ee;
            border-radius: 8px;
            padding: 12px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #223771;
            box-shadow: 0 0 0 0.2rem rgba(34, 55, 113, 0.25);
        }

        .btn-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }

        .btn-custom {
            flex: 1;
            background: linear-gradient(135deg, #223771 0%, #2a4288 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            border: none;
        }

        .btn-custom:hover {
            background: linear-gradient(135deg, #f8843d 0%, #f9965b 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(248, 132, 61, 0.4);
            color: white;
        }

        .btn-back {
            background: linear-gradient(135deg, #f44336 0%, #e53935 100%);
            color: white;
            border: none;
            align-items: center;
            justify-content: center;
            display: flex;
        }

        .btn-back:hover {
            background: linear-gradient(135deg, #ef5350 0%, #e57373 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 83, 80, 0.4);
            color: white;
        }

        .section-divider {
            border-bottom: 2px solid #e1e5ee;
            margin: 30px 0;
            padding-bottom: 10px;
        }

        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid #e1e5ee;
        }

        .form-section-title {
            color: #223771;
            font-size: 20px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .alert {
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 25px;
            border: none;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
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

            .main-content {
                width: 90%;
                padding: 30px;
                margin: 10px auto;
            }
            
            .form-title {
                font-size: 24px;
            }
            
            .avatar-container img {
                width: 150px;
                height: 150px;
            }
        }

        /* Styles for popup */
        .alert {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            min-width: 300px;
            text-align: center;
            animation: fadeInOut 3s ease-in-out;
        }

        @keyframes fadeInOut {
            0% { opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { opacity: 0; }
        }

        .custom-popup {
            animation: fadeInOut 3s ease-in-out;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        @keyframes fadeInOut {
            0% { opacity: 0; transform: translate(-50%, -20px); }
            15% { opacity: 1; transform: translate(-50%, 0); }
            85% { opacity: 1; transform: translate(-50%, 0); }
            100% { opacity: 0; transform: translate(-50%, -20px); }
        }

        /* Thêm vào phần style */
        .flatpickr-input {
            background-color: white !important;
            cursor: pointer;
        }

        .flatpickr-calendar {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 3px 13px rgba(0, 0, 0, 0.2);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .flatpickr-day.selected, 
        .flatpickr-day.selected:hover,
        .flatpickr-day.selected:focus {
            background: #223771;
            border-color: #223771;
        }

        .flatpickr-day:hover {
            background: #e8eaf6;
        }

        .flatpickr-current-month .flatpickr-monthDropdown-months:hover,
        .flatpickr-current-month input.cur-year:hover {
            background: #e8eaf6;
        }

        .flatpickr-months .flatpickr-prev-month:hover svg,
        .flatpickr-months .flatpickr-next-month:hover svg {
            fill: #223771;
        }

        .flatpickr-calendar.arrowTop:before,
        .flatpickr-calendar.arrowTop:after {
            border-bottom-color: #fff;
        }

        .flatpickr-time input:hover,
        .flatpickr-time .flatpickr-am-pm:hover,
        .flatpickr-time input:focus,
        .flatpickr-time .flatpickr-am-pm:focus {
            background: #e8eaf6;
        }

        .date-input-container {
            position: relative;
        }

        .date-input-container:after {
            content: '\f133';
            font-family: 'Font Awesome 5 Free';
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #223771;
            pointer-events: none;
        }

        .form-control.date-input {
            padding-right: 35px;
        }

        .invalid-feedback {
            display: none;
            color: #dc3545;
            font-size: 0.875em;
            margin-top: 0.25rem;
        }

        .is-invalid {
            border-color: #dc3545 !important;
        }

        .is-invalid:focus {
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/vn.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Xử lý popup messages
            <?php if ($successMessage): ?>
                showPopup('<?php echo addslashes($successMessage); ?>', 'success');
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                showPopup('<?php echo addslashes($errorMessage); ?>', 'error');
            <?php endif; ?>

            let birthDate = null;
            let hireDate = null;

            // Hàm tính số tuổi giữa hai ngày
            function calculateAge(birthDate, currentDate) {
                const diffTime = Math.abs(currentDate - birthDate);
                const diffYears = diffTime / (1000 * 60 * 60 * 24 * 365.25);
                return diffYears;
            }

            // Cấu hình chung cho flatpickr
            const dateConfig = {
                dateFormat: "d/m/Y",
                locale: "vn",
                allowInput: true,
                disableMobile: false,
                animate: true,
                position: "auto",
            };

            // Khởi tạo date picker cho ngày sinh
            const birthPicker = flatpickr("#birth", {
                ...dateConfig,
                maxDate: "today",
                onChange: function(selectedDates, dateStr, instance) {
                    birthDate = selectedDates[0];
                    
                    // Validate ngày sinh
                    if (birthDate) {
                        const today = new Date();
                        if (birthDate > today) {
                            instance.clear();
                            showPopup('Ngày sinh không được lớn hơn ngày hiện tại', 'danger');
                            return;
                        }

                        // Kiểm tra với ngày tuyển dụng nếu đã có
                        if (hireDate) {
                            const age = calculateAge(birthDate, hireDate);
                            if (age < 18) {
                                instance.clear();
                                showPopup('Tuổi tại thời điểm làm việc phải từ 18 tuổi trở lên', 'danger');
                                birthDate = null;
                                return;
                            }
                            if (birthDate >= hireDate) {
                                instance.clear();
                                showPopup('Ngày sinh phải trước ngày tuyển dụng', 'danger');
                                birthDate = null;
                            }
                        }
                    }
                }
            });

            // Khởi tạo date picker cho ngày tuyển dụng
            const hirePicker = flatpickr("#hireDate", {
                ...dateConfig,
                maxDate: "today",
                onChange: function(selectedDates, dateStr, instance) {
                    hireDate = selectedDates[0];
                    
                    // Validate ngày tuyển dụng
                    if (hireDate) {
                        const today = new Date();
                        if (hireDate > today) {
                            instance.clear();
                            showPopup('Ngày tuyển dụng không được lớn hơn ngày hiện tại', 'danger');
                            hireDate = null;
                            return;
                        }

                        // Kiểm tra với ngày sinh nếu đã có
                        if (birthDate) {
                            const age = calculateAge(birthDate, hireDate);
                            if (age < 18) {
                                instance.clear();
                                showPopup('Tuổi tại thời điểm làm việc phải từ 18 tuổi trở lên', 'danger');
                                hireDate = null;
                                return;
                            }
                            if (birthDate >= hireDate) {
                                instance.clear();
                                showPopup('Ngày tuyển dụng phải sau ngày sinh', 'danger');
                                hireDate = null;
                            }
                        }
                    }
                }
            });

            // Xử lý enable/disable rankTeacher dựa trên academicTitle
            const academicTitle = document.getElementById('academicTitle');
            const rankTeacher = document.getElementById('rankTeacher');

            // Hàm xử lý thay đổi trạng thái của rankTeacher
            function updateRankTeacherState() {
                if (academicTitle.value === "Giảng viên") {
                    rankTeacher.disabled = false;
                    rankTeacher.required = true;
                } else {
                    rankTeacher.disabled = true;
                    rankTeacher.required = false;
                    rankTeacher.value = "";
                }
            }

            // Thêm event listener cho academicTitle
            academicTitle.addEventListener('change', updateRankTeacherState);

            // Khởi tạo trạng thái ban đầu
            updateRankTeacherState();

            // Xử lý hiển thị lỗi cho từng trường
            <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $field => $msg): ?>
                const <?php echo $field; ?>Input = document.getElementById('<?php echo $field; ?>');
                if (<?php echo $field; ?>Input) {
                    <?php echo $field; ?>Input.classList.add('is-invalid');
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback';
                    errorDiv.textContent = '<?php echo $msg; ?>';
                    <?php echo $field; ?>Input.parentNode.appendChild(errorDiv);
                }
            <?php endforeach; ?>
            <?php endif; ?>

            // Xử lý xóa class is-invalid khi người dùng bắt đầu nhập lại
            const inputs = document.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    this.classList.remove('is-invalid');
                    const errorDiv = this.parentNode.querySelector('.invalid-feedback');
                    if (errorDiv) {
                        errorDiv.remove();
                    }
                });
            });

            // Xử lý validate số điện thoại
            const phoneInput = document.getElementById('phone');
            const phoneError = document.createElement('div');
            phoneError.className = 'invalid-feedback';
            phoneError.textContent = 'Số điện thoại phải bắt đầu bằng số 0 và có 10 chữ số';
            phoneInput.parentNode.appendChild(phoneError);

            phoneInput.addEventListener('input', function() {
                const phoneValue = this.value;
                const phonePattern = /^0[0-9]{9}$/;
                
                if (phoneValue && !phonePattern.test(phoneValue)) {
                    this.classList.add('is-invalid');
                    phoneError.style.display = 'block';
                } else {
                    this.classList.remove('is-invalid');
                    phoneError.style.display = 'none';
                }
            });

            // Xử lý khi form submit
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const phoneValue = phoneInput.value;
                const phonePattern = /^0[0-9]{9}$/;
                
                if (!phonePattern.test(phoneValue)) {
                    e.preventDefault();
                    phoneInput.classList.add('is-invalid');
                    phoneError.style.display = 'block';
                    showPopup('Số điện thoại phải bắt đầu bằng số 0 và có 10 chữ số', 'error');
                }
            });
        });

        // Hàm hiển thị popup (giữ nguyên)
        function showPopup(message, type) {
            const existingPopup = document.querySelector('.custom-popup');
            if (existingPopup) {
                existingPopup.remove();
            }

            const popup = document.createElement('div');
            popup.className = `alert alert-${type} custom-popup`;
            popup.style.position = 'fixed';
            popup.style.top = '20px';
            popup.style.left = '50%';
            popup.style.transform = 'translateX(-50%)';
            popup.style.zIndex = '9999';
            popup.style.minWidth = '300px';
            popup.style.textAlign = 'center';
            popup.innerHTML = message;

            document.body.appendChild(popup);

            setTimeout(() => {
                popup.remove();
            }, 3000);
        }
    </script>
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

    <div class="main-content">
        <h2 class="form-title">Thêm nhân viên mới</h2>

        <?php if ($successMessage): ?>
            <div class="alert alert-success custom-popup">
                <?php echo $successMessage; ?>
            </div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger custom-popup">
                <?php echo $errorMessage; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <!-- Avatar Section -->
            <div class="avatar-container">
                <input type="file" id="image" name="image" accept="image/*" style="display: none;" onchange="previewImage(event)">
                <img id="avatar-preview" src="../assets/images/avatar-default.png" onclick="document.getElementById('image').click();">
                <div class="avatar-hint">Nhấp để chọn ảnh</div>
            </div>

            <!-- Thông tin cá nhân -->
            <div class="form-section">
                <h3 class="form-section-title">Thông tin cá nhân</h3>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="fullName" class="form-label">Họ và tên:</label>
                        <input type="text" class="form-control" id="fullName" name="fullName" required value="<?php echo $formData['fullName']; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="birth" class="form-label">Ngày sinh:</label>
                        <div class="date-input-container">
                            <input type="text" class="form-control date-input" id="birth" name="birth" placeholder="dd/mm/yyyy" required value="<?php echo $formData['birth']; ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="gender" class="form-label">Giới tính:</label>
                        <select class="form-select" id="gender" name="gender" required>
                            <option value="Nam" <?php echo $formData['gender'] === 'Nam' ? 'selected' : ''; ?>>Nam</option>
                            <option value="Nữ" <?php echo $formData['gender'] === 'Nữ' ? 'selected' : ''; ?>>Nữ</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Số điện thoại:</label>
                        <input type="text" 
                               class="form-control" 
                               id="phone" 
                               name="phone" 
                               required 
                               pattern="^0[0-9]{9}$"
                               placeholder="0xxxxxxxxx"
                               title="Số điện thoại phải bắt đầu bằng số 0 và có 10 chữ số"
                               value="<?php echo $formData['phone']; ?>">
                        <div class="invalid-feedback">
                            Số điện thoại phải bắt đầu bằng số 0 và có 10 chữ số
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="hireDate" class="form-label">Ngày tuyển dụng:</label>
                        <div class="date-input-container">
                            <input type="text" class="form-control date-input" id="hireDate" name="hireDate" placeholder="dd/mm/yyyy" required value="<?php echo $formData['hireDate']; ?>">
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email:</label>
                        <input type="email" class="form-control" id="email" name="email" required value="<?php echo $formData['email']; ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="address" class="form-label">Địa chỉ:</label>
                        <input type="text" class="form-control" id="address" name="address" required value="<?php echo $formData['address']; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="role" class="form-label">Vai trò:</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="Giảng viên" <?php echo $formData['role'] === 'Giảng viên' ? 'selected' : ''; ?>>Giảng viên</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Thông tin công việc -->
            <div class="form-section">
                <h3 class="form-section-title">Thông tin công việc</h3>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="teacherID" class="form-label">Mã giảng viên:</label>
                        <input type="text" class="form-control" id="teacherID" name="teacherID" required value="<?php echo $formData['teacherID']; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="rankTeacher" class="form-label">Cấp hạng giảng viên:</label>
                        <select class="form-select" id="rankTeacher" name="rankTeacher" disabled>
                            <option value="Hạng I" <?php echo $formData['rankTeacher'] === 'Hạng I' ? 'selected' : ''; ?>>Hạng I (Giảng viên cao cấp)</option>
                            <option value="Hạng II" <?php echo $formData['rankTeacher'] === 'Hạng II' ? 'selected' : ''; ?>>Hạng II (Giảng viên chính)</option>
                            <option value="Hạng III" <?php echo $formData['rankTeacher'] === 'Hạng III' ? 'selected' : ''; ?>>Hạng III (Giảng viên/trợ giảng)</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="academicTitle" class="form-label">Chức danh giảng viên:</label>
                        <select class="form-select" id="academicTitle" name="academicTitle">
                            <option value="" <?php echo $formData['academicTitle'] === '' ? 'selected' : ''; ?>>Chọn chức danh</option>
                            <option value="Giảng viên" <?php echo $formData['academicTitle'] === 'Giảng viên' ? 'selected' : ''; ?>>Giảng viên</option>
                            <option value="Giảng viên (tập sự)" <?php echo $formData['academicTitle'] === 'Giảng viên (tập sự)' ? 'selected' : ''; ?>>Giảng viên (tập sự)</option>
                            <option value="Trợ giảng" <?php echo $formData['academicTitle'] === 'Trợ giảng' ? 'selected' : ''; ?>>Trợ giảng</option>
                            <option value="Trợ giảng (tập sự)" <?php echo $formData['academicTitle'] === 'Trợ giảng (tập sự)' ? 'selected' : ''; ?>>Trợ giảng (tập sự)</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="leadershipPosition" class="form-label">Chức vụ lãnh đạo:</label>
                        <select class="form-select" id="leadershipPosition" name="leadershipPosition">
                            <option value="" <?php echo $formData['leadershipPosition'] === '' ? 'selected' : ''; ?>>Không</option>
                            <option value="Chủ tịch Hội đồng trường, Hiệu trưởng" <?php echo $formData['leadershipPosition'] === 'Chủ tịch Hội đồng trường, Hiệu trưởng' ? 'selected' : ''; ?>>Chủ tịch Hội đồng trường, Hiệu trưởng</option>
                            <option value="Phó chủ tịch Hội đồng trường, Phó hiệu trưởng" <?php echo $formData['leadershipPosition'] === 'Phó chủ tịch Hội đồng trường, Phó hiệu trưởng' ? 'selected' : ''; ?>>Phó chủ tịch Hội đồng trường, Phó hiệu trưởng</option>
                            <option value="Trưởng phòng, Giám đốc trung tâm, Thư ký Hội đồng trường" <?php echo $formData['leadershipPosition'] === 'Trưởng phòng, Giám đốc trung tâm, Thư ký Hội đồng trường' ? 'selected' : ''; ?>>Trưởng phòng, Giám đốc trung tâm, Thư ký Hội đồng trường</option>
                            <option value="Phó trưởng phòng, Phó Giám đốc trung tâm" <?php echo $formData['leadershipPosition'] === 'Phó trưởng phòng, Phó Giám đốc trung tâm' ? 'selected' : ''; ?>>Phó trưởng phòng, Phó Giám đốc trung tâm</option>
                            <option value="Trưởng khoa, Trưởng Bộ môn trực thuộc trường (≥ 40 giảng viên hoặc ≥ 800 người học)" <?php echo $formData['leadershipPosition'] === 'Trưởng khoa, Trưởng Bộ môn trực thuộc trường (≥ 40 giảng viên hoặc ≥ 800 người học)' ? 'selected' : ''; ?>>Trưởng khoa, Trưởng Bộ môn trực thuộc trường (≥ 40 giảng viên hoặc ≥ 800 người học)</option>
                            <option value="Trưởng khoa, Trưởng Bộ môn trực thuộc trường (< 40 giảng viên hoặc < 800 người học)" <?php echo $formData['leadershipPosition'] === 'Trưởng khoa, Trưởng Bộ môn trực thuộc trường (< 40 giảng viên hoặc < 800 người học)' ? 'selected' : ''; ?>>Trưởng khoa, Trưởng Bộ môn trực thuộc trường (< 40 giảng viên hoặc < 800 người học)</option>
                            <option value="Phó trưởng khoa, Phó trưởng bộ môn trực thuộc trường (≥ 40 giảng viên hoặc ≥ 800 SV)" <?php echo $formData['leadershipPosition'] === 'Phó trưởng khoa, Phó trưởng bộ môn trực thuộc trường (≥ 40 giảng viên hoặc ≥ 800 SV)' ? 'selected' : ''; ?>>Phó trưởng khoa, Phó trưởng bộ môn trực thuộc trường (≥ 40 giảng viên hoặc ≥ 800 SV)</option>
                            <option value="Phó trưởng khoa, Phó trưởng bộ môn trực thuộc trường (< 40 giảng viên hoặc < 800 SV)" <?php echo $formData['leadershipPosition'] === 'Phó trưởng khoa, Phó trưởng bộ môn trực thuộc trường (< 40 giảng viên hoặc < 800 SV)' ? 'selected' : ''; ?>>Phó trưởng khoa, Phó trưởng bộ môn trực thuộc trường (< 40 giảng viên hoặc < 800 SV)</option>
                            <option value="Trưởng bộ môn trực thuộc khoa" <?php echo $formData['leadershipPosition'] === 'Trưởng bộ môn trực thuộc khoa' ? 'selected' : ''; ?>>Trưởng bộ môn trực thuộc khoa</option>
                            <option value="Phó trưởng bộ môn trực thuộc khoa" <?php echo $formData['leadershipPosition'] === 'Phó trưởng bộ môn trực thuộc khoa' ? 'selected' : ''; ?>>Phó trưởng bộ môn trực thuộc khoa</option>
                            <option value="Chủ nhiệm lớp, Cố vấn học tập (được cộng dồn)" <?php echo $formData['leadershipPosition'] === 'Chủ nhiệm lớp, Cố vấn học tập (được cộng dồn)' ? 'selected' : ''; ?>>Chủ nhiệm lớp, Cố vấn học tập (được cộng dồn)</option>
                            <option value="Bí thư đảng ủy" <?php echo $formData['leadershipPosition'] === 'Bí thư đảng ủy' ? 'selected' : ''; ?>>Bí thư đảng ủy</option>
                            <option value="Phó bí thư Đảng ủy" <?php echo $formData['leadershipPosition'] === 'Phó bí thư Đảng ủy' ? 'selected' : ''; ?>>Phó bí thư Đảng ủy</option>
                            <option value="Bí thư chi bộ, Trưởng ban TTND, Trưởng Ban nữ công, Chủ tịch Hội CCB" <?php echo $formData['leadershipPosition'] === 'Bí thư chi bộ, Trưởng ban TTND, Trưởng Ban nữ công, Chủ tịch Hội CCB' ? 'selected' : ''; ?>>Bí thư chi bộ, Trưởng ban TTND, Trưởng Ban nữ công, Chủ tịch Hội CCB</option>
                            <option value="Phó Bí thư chi bộ" <?php echo $formData['leadershipPosition'] === 'Phó Bí thư chi bộ' ? 'selected' : ''; ?>>Phó Bí thư chi bộ</option>
                            <option value="Giảng viên làm công tác quốc phòng, quân sự không chuyên trách" <?php echo $formData['leadershipPosition'] === 'Giảng viên làm công tác quốc phòng, quân sự không chuyên trách' ? 'selected' : ''; ?>>Giảng viên làm công tác quốc phòng, quân sự không chuyên trách</option>
                            <option value="Bí thư đoàn trường" <?php echo $formData['leadershipPosition'] === 'Bí thư đoàn trường' ? 'selected' : ''; ?>>Bí thư đoàn trường</option>
                            <option value="Phó bí thư đoàn trường" <?php echo $formData['leadershipPosition'] === 'Phó bí thư đoàn trường' ? 'selected' : ''; ?>>Phó bí thư đoàn trường</option>
                            <option value="Bí thư Liên chi đoàn (≥ 1,000 SV)" <?php echo $formData['leadershipPosition'] === 'Bí thư Liên chi đoàn (≥ 1,000 SV)' ? 'selected' : ''; ?>>Bí thư Liên chi đoàn (≥ 1,000 SV)</option>
                            <option value="Bí thư Liên chi đoàn (500 - 1,000 SV)" <?php echo $formData['leadershipPosition'] === 'Bí thư Liên chi đoàn (500 - 1,000 SV)' ? 'selected' : ''; ?>>Bí thư Liên chi đoàn (500 - 1,000 SV)</option>
                            <option value="Bí thư Liên chi đoàn (< 500 SV)" <?php echo $formData['leadershipPosition'] === 'Bí thư Liên chi đoàn (< 500 SV)' ? 'selected' : ''; ?>>Bí thư Liên chi đoàn (< 500 SV)</option>
                            <option value="Giảng viên nữ có con nhỏ dưới 12 tháng" <?php echo $formData['leadershipPosition'] === 'Giảng viên nữ có con nhỏ dưới 12 tháng' ? 'selected' : ''; ?>>Giảng viên nữ có con nhỏ dưới 12 tháng</option>
                            <option value="Cán bộ giảng dạy kiêm quản lý phòng thí nghiệm, thực hành công nghệ, phòng máy" <?php echo $formData['leadershipPosition'] === 'Cán bộ giảng dạy kiêm quản lý phòng thí nghiệm, thực hành công nghệ, phòng máy' ? 'selected' : ''; ?>>Cán bộ giảng dạy kiêm quản lý phòng thí nghiệm, thực hành công nghệ, phòng máy</option>
                            <option value="Chủ tịch Công đoàn trường, Phó chủ tịch Công đoàn trường" <?php echo $formData['leadershipPosition'] === 'Chủ tịch Công đoàn trường, Phó chủ tịch Công đoàn trường' ? 'selected' : ''; ?>>Chủ tịch Công đoàn trường, Phó chủ tịch Công đoàn trường</option>
                            <option value="Ủy viên BCH công đoàn trường, tổ trưởng, tổ phó tổ công đoàn" <?php echo $formData['leadershipPosition'] === 'Ủy viên BCH công đoàn trường, tổ trưởng, tổ phó tổ công đoàn' ? 'selected' : ''; ?>>Ủy viên BCH công đoàn trường, tổ trưởng, tổ phó tổ công đoàn</option>
                            <option value="Giảng viên làm thay công tác trợ lý khoa nghỉ việc" <?php echo $formData['leadershipPosition'] === 'Giảng viên làm thay công tác trợ lý khoa nghỉ việc' ? 'selected' : ''; ?>>Giảng viên làm thay công tác trợ lý khoa nghỉ việc</option>
                            <option value="Giảng viên đang là quân nhân dự bị, tự vệ được triệu tập huấn luyện, diễn tập" <?php echo $formData['leadershipPosition'] === 'Giảng viên đang là quân nhân dự bị, tự vệ được triệu tập huấn luyện, diễn tập' ? 'selected' : ''; ?>>Giảng viên đang là quân nhân dự bị, tự vệ được triệu tập huấn luyện, diễn tập</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="faculty" class="form-label">Khoa:</label>
                        <select class="form-select" id="faculty" name="faculty">
                            <option value="" <?php echo $formData['faculty'] === '' ? 'selected' : ''; ?>>Chọn khoa</option>
                            <option value="Đất đai" <?php echo $formData['faculty'] === 'Đất đai' ? 'selected' : ''; ?>>Đất đai</option>
                            <option value="Công nghệ thông tin" <?php echo $formData['faculty'] === 'Công nghệ thông tin' ? 'selected' : ''; ?>>Công nghệ thông tin</option>
                            <option value="Kinh tế" <?php echo $formData['faculty'] === 'Kinh tế' ? 'selected' : ''; ?>>Kinh tế</option>
                            <option value="Địa Chất" <?php echo $formData['faculty'] === 'Địa Chất' ? 'selected' : ''; ?>>Địa Chất</option>
                            <option value="Môi Trường" <?php echo $formData['faculty'] === 'Môi Trường' ? 'selected' : ''; ?>>Môi Trường</option>
                            <option value="Khí tượng văn học" <?php echo $formData['faculty'] === 'Khí tượng văn học' ? 'selected' : ''; ?>>Khí tượng văn học</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="major" class="form-label">Chuyên ngành:</label>
                        <input type="text" class="form-control" id="major" name="major" required value="<?php echo $formData['major']; ?>">
                    </div>
                </div>
            </div>

            <!-- Thông tin tài khoản -->
            <div class="form-section">
                <h3 class="form-section-title">Thông tin tài khoản</h3>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="userName" class="form-label">Tên đăng nhập:</label>
                        <input type="text" class="form-control" id="userName" name="userName" required value="<?php echo $formData['userName']; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Mật khẩu:</label>
                        <input type="password" id="password" name="password" class="form-control" minlength="8" required
                        oninvalid="this.setCustomValidity('Mật khẩu phải có ít nhất 8 ký tự!')"
                        oninput="this.setCustomValidity('')">
                    </div>
                </div>
            </div>

            <div class="btn-container">
                <button type="submit" name="add_employee" class="btn btn-custom">
                    <i class="fas fa-user-plus me-2"></i>Thêm nhân viên
                </button>
                <a href="login.php" class="btn btn-back">
                    <i class="fas fa-arrow-left me-2"></i>Quay lại
                </a>
            </div>
        </form>
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
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>