<?php
// Start the session
session_start();

// Include the database connection
require_once __DIR__ . '../../../connection/connection.php';

// Kiểm tra session
if (!isset($_SESSION['employeeID'])) {
    header("Location: ../../login/login.php");
    exit();
}

$fullName = $_SESSION['fullName'];

// Lấy role từ session (giả sử role đã được lưu vào session khi đăng nhập)
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';

// Truy xuất thông tin nhân viên từ cơ sở dữ liệu để lấy ảnh
try {
    if (!isset($conn) || !$conn instanceof PDO) {
        throw new Exception("Kết nối cơ sở dữ liệu không tồn tại hoặc không hợp lệ.");
    }

    $employeeID = $_SESSION['employeeID'];
    $stmt = $conn->prepare("SELECT image FROM employee WHERE employeeID = :employeeID");
    $stmt->bindParam(':employeeID', $employeeID, PDO::PARAM_INT);
    $stmt->execute();
    $employeeData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employeeData) {
        throw new Exception("Không tìm thấy thông tin nhân viên với employeeID: $employeeID");
    }

    // Xác định đường dẫn thư mục uploads
    $uploadDir = '../assets/uploads/';
    $defaultImage = './assets/images/avatar-default.png';

    // Tạo tên file ảnh dựa trên employeeID
    $imageName = $employeeID . '.jpg';
    $imagePath = $uploadDir . $imageName;

    // Kiểm tra xem file ảnh có tồn tại trong thư mục uploads không
    if (file_exists($imagePath)) {
        $avatarSrc = $imagePath; // Lưu đường dẫn ảnh vào biến tạm nếu tìm thấy
    } else {
        $avatarSrc = $defaultImage; // Nếu không tìm thấy, dùng ảnh mặc định
    }

} catch (Exception $e) {
    // Ghi log lỗi để debug (nếu cần)
    error_log("Lỗi: " . $e->getMessage());
    $avatarSrc = './assets/images/avatar-default.png'; // Dùng ảnh mặc định nếu có lỗi
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý báo cáo</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/index.css">
    <style>
        /* CSS cho modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 90%;
            max-width: 800px;
            border-radius: 8px;
            position: relative;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }

        /* Popup styles */
        .popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            z-index: 1001;
            text-align: center;
            width: 300px;
            max-width: 400px;
            min-width: 250px;
            overflow-y: auto;
        }

        .popup.success {
            border: 2px solid #28a745;
        }

        .popup.error {
            border: 2px solid #dc3545;
        }

        .popup p {
            margin: 0 0 15px 0;
            font-size: 16px;
            word-wrap: break-word;
        }

        .popup button {
            background-color: #223771;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }

        .popup button:hover {
            background-color: #f8843d;
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .user-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .account-settings {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Form xuất báo cáo căn giữa và đẹp */
        .export-report-container {
            display: flex;
            justify-content: center;
            margin-top: 24px; /* Cách main-title 24px */
        }

        .export-report-form {
            background: #fff;
            padding: 32px 32px 24px 32px;
            border-radius: 14px;
            box-shadow: 0 4px 24px rgba(34, 55, 113, 0.10), 0 1.5px 6px rgba(34, 55, 113, 0.04);
            min-width: 370px;
            max-width: 100%;
            margin: 10px;
        }

        .export-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 12px;
        }

        .export-label-td {
            text-align: right;
            padding-right: 16px;
            vertical-align: middle;
            min-width: 120px;
        }

        .export-label {
            font-size: 16px;
            font-weight: 600;
            color: #223771;
        }

        .export-input {
            width: 100%;
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            font-size: 15px;
            background: #f8fafc;
            color: #223771;
            outline: none;
            transition: border 0.2s;
            box-sizing: border-box;
        }
        .export-input:focus {
            border: 1.5px solid #223771;
        }

        .export-btn {
            background-color: #223771;
            color: white;
            padding: 12px 0;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            width: 60%;
        }
        .export-btn:hover {
            background-color: #f8843d;
        }

        /* Thêm CSS cho footer */
        .footer {
            background-color: #223771;
            color: white;
            font-size: 0.8rem;
            position: relative;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1.5fr 1fr;
            gap: 1rem;
            align-items: start;
        }

        .footer-section {
            padding: 0.5rem;
        }

        .footer-section h3 {
            color: #f8843d;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
        }

        .footer-section .contact-info {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .footer-section .contact-info i {
            color: #f8843d;
            font-size: 0.9rem;
            margin-top: 0.2rem;
        }

        .footer-section .contact-info p {
            margin: 0;
            line-height: 1.4;
        }

        .map-container {
            width: 100%;
            height: 150px;
            border: none;
        }

        .facebook-container {
            width: 100%;
            height: 150px;
            background: #fff;
            border-radius: 4px;
        }

        .copyright {
            text-align: center;
            padding: 0.5rem 0;
            font-size: 0.75rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 1rem;
        }
    </style>
</head>
<body>
<header class="header">
    <nav class="nav">
        <ul class="nav-list">
            <li class="nav-item"><a href="../formRegister/formRegister.php" class="nav-link">Thống kê giảng dạy</a></li>
            <li class="nav-item"><a href="../thoikhoabieu/thoikhoabieu.php" class="nav-link">Thời khóa biểu</a></li>
            <li class="nav-item"><a href="./UC/report/report.php" class="nav-link">Báo cáo</a></li>
        </ul>
        <div class="account-settings">
            <span class="account-name"><?php echo htmlspecialchars($fullName); ?></span>
            <div class="user-avatar">
                <img id="avatar-preview" src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="Avatar">
            </div>
            <div class="account-menu">
                <a href="../../login/logout.php" class="account-menu-item">Đăng xuất</a>
            </div>
        </div>
    </nav>
</header>

<main class="main">
    <h1 class="main-title">Chào mừng đến với trang quản lý báo cáo</h1>
    
    <!-- Form 1: Giảng dạy -->
    <div class="export-report-container">
        <form action="./export_report.php" method="post" class="export-report-form">
            <h3 style="text-align: center; color: #223771;">Báo cáo Giảng dạy</h3>
            <table class="export-table">
                <tr>
                    <td class="export-label-td">
                        <label for="year_giangday" class="export-label">Năm:</label>
                    </td>
                    <td>
                        <select id="year_giangday" name="year" required class="export-input">
                            <?php
                                $currentYear = date('Y');
                                for ($y = $currentYear; $y >= $currentYear - 10; $y--) {
                                    echo "<option value=\"$y\">$y</option>";
                                }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="export-label-td">
                        <label for="taskType_giangday" class="export-label">Loại nhiệm vụ:</label>
                    </td>
                    <td>
                    <select id="taskType" name="taskType" required class="export-input">
                                <option value="">-- Chọn loại nhiệm vụ --</option>
                                <option value="giangday">Giảng dạy</option>
                            </select>
                    </td>
                </tr>
                <tr>
                    <td class="export-label-td">
                        <label for="filename_giangday" class="export-label">Tên file xuất:</label>
                    </td>
                    <td>
                        <input type="text" id="filename_giangday" name="filename" class="export-input" placeholder="Tên file (không cần .xlsx)" required pattern="[A-Za-z0-9_-]+" title="Tên file chỉ được chứa chữ cái, số, dấu gạch dưới hoặc gạch ngang">
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align:center; padding-top: 18px;">
                        <button type="submit" class="export-btn">Xuất báo cáo</button>
                    </td>
                </tr>
            </table>
        </form>

        <!-- Form 2: Nghiên cứu khoa học -->
        <!-- Form 2: Nghiên cứu khoa học -->
<form action="export_nckh.php" method="post" class="export-report-form">
    <h3 style="text-align: center; color: #223771;">Báo cáo Nghiên cứu khoa học</h3>
    <table class="export-table">
        <tr>
            <td class="export-label-td">
                <label for="year_nghiencuu" class="export-label">Năm:</label>
            </td>
            <td>
                <select id="year_nghiencuu" name="year" required class="export-input">
                    <?php
                        $currentYear = date('Y');
                        for ($y = $currentYear; $y >= $currentYear - 10; $y--) {
                            echo "<option value=\"$y\">$y</option>";
                        }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <td class="export-label-td">
                <label for="taskType_nghiencuu" class="export-label">Loại nhiệm vụ:</label>
            </td>
            <td>
                <select id="taskType" name="taskType" required class="export-input">
                    <option value="">-- Chọn loại nhiệm vụ --</option>
                    <option value="nckh">Nghiên cứu khoa học</option>
                </select>
            </td>
        </tr>
        <tr>
            <td class="export-label-td">
                <label for="filename_nghiencuu" class="export-label">Tên file xuất:</label>
            </td>
            <td>
                <input type="text" id="filename_nghiencuu" name="filename" class="export-input" placeholder="Tên file (không cần .xlsx)" required pattern="[A-Za-z0-9_-]+" title="Tên file chỉ được chứa chữ cái, số, dấu gạch dưới hoặc gạch ngang">
            </td>
        </tr>
        <tr>
            <td colspan="2" style="text-align:center; padding-top: 18px;">
                <button type="submit" class="export-btn">Xuất báo cáo</button>
            </td>
        </tr>
    </table>
</form>

        <!-- Form 3: Nhiệm vụ khác -->
        <form action="export_pl4.php" method="post" class="export-report-form">
            <h3 style="text-align: center; color: #223771;">Báo cáo Nhiệm vụ khác</h3>
            <table class="export-table">
                <tr>
                    <td class="export-label-td">
                        <label for="year_nhiemvukhac" class="export-label">Năm:</label>
                    </td>
                    <td>
                        <select id="year_nhiemvukhac" name="year" required class="export-input">
                            <?php
                                $currentYear = date('Y');
                                for ($y = $currentYear; $y >= $currentYear - 10; $y--) {
                                    echo "<option value=\"$y\">$y</option>";
                                }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="export-label-td">
                        <label for="taskType_nhiemvukhac" class="export-label">Loại nhiệm vụ:</label>
                    </td>
                    <td>
                    <select id="taskType" name="taskType" required class="export-input">
                                <option value="">-- Chọn loại nhiệm vụ --</option>
                                <option value="nhiemvukhac">Nhiệm vụ khác</option>
                            </select>
                    </td>
                </tr>
                <tr>
                    <td class="export-label-td">
                        <label for="filename_nhiemvukhac" class="export-label">Tên file xuất:</label>
                    </td>
                    <td>
                        <input type="text" id="filename_nhiemvukhac" name="filename" class="export-input" placeholder="Tên file (không cần .xlsx)" required pattern="[A-Za-z0-9_-]+" title="Tên file chỉ được chứa chữ cái, số, dấu gạch dưới hoặc gạch ngang">
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align:center; padding-top: 18px;">
                        <button type="submit" class="export-btn">Xuất báo cáo</button>
                    </td>
                </tr>
            </table>
        </form>
    </div>
</main>

<!-- ==== FOOTER (copy từ trangchu.php) ==== -->
<footer class="footer">
    <div class="footer-content">
        <div class="footer-section">
            <h3>Thông tin liên hệ</h3>
            <div class="contact-info">
                <i>📍</i>
                <p>Trường Đại học Tài nguyên và Môi Trường Hà Nội</p>
            </div>
            <div class="contact-info">
                <i>✉️</i>
                <p>nguyentientoan28022004@gmail.com</p>
            </div>
            <div class="contact-info">
                <i>📞</i>
                <p>0352135115</p>
            </div>
            <div class="contact-info">
                <i>🌐</i>
                <p>Website: Quản lý nghiệp vụ giảng viên</p>
            </div>
        </div>

        <div class="footer-section">
            <h3>Bản đồ</h3>
            <iframe 
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3724.6409878661737!2d105.75986221476873!3d21.047048685988825!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x313454c3ce577141%3A0xb1a1ac92701777bc!2zVHLGsOG7nW5nIMSQ4bqhaSho4buNYyBUw6BpIG5ndXnDqm4gdsOgIE3DtGkgdHLGsOG7nW5nIEjDoCBO4buZaQ!5e0!3m2!1svi!2s!4v1710080669634!5m2!1svi!2s"                    class="map-container"
            allowfullscreen=""
            loading="lazy">
            </iframe>
        </div>

        <div class="footer-section">
            <h3>Fanpage Facebook</h3>
            <div class="facebook-container" style="display: flex; align-items: center; justify-content: center;">
                <a href="https://www.facebook.com/toan.nguyen.750637" 
                   target="_blank" 
                   style="text-decoration: none; 
                          background-color: #1877f2; 
                          color: white; 
                          padding: 10px 20px; 
                          border-radius: 6px;
                          display: flex;
                          align-items: center;
                          gap: 10px;
                          font-size: 16px;">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/0/05/Facebook_Logo_%282019%29.png/600px-Facebook_Logo_%282019%29.png" 
                         alt="Facebook" 
                         style="width: 24px; height: 24px;">
                    Truy cập Facebook
                </a>
            </div>
        </div>
    </div>
    
    <div class="copyright">
        <p>© 2025 nhóm sinh viên NCKH ĐH12C5 - HUNRE</p>
    </div>
</footer>
<!-- ==== END FOOTER ==== -->

</body>
</html>