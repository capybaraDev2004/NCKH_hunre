<?php
// Start the session
session_start();

// Include the database connection
require_once __DIR__ . '/connection/connection.php';

// Kiểm tra session
if (!isset($_SESSION['employeeID'])) {
    header("Location: ./login/login.php");
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
    $uploadDir = './UC/assets/uploads/';
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

// Kiểm tra thời khóa biểu
$hasSchedule = false;
try {
    // Kiểm tra trong bảng schedule
    $stmt = $conn->prepare("SELECT COUNT(*) FROM schedule WHERE employeeID = :employeeID");
    $stmt->execute([':employeeID' => $employeeID]);
    $scheduleCount = $stmt->fetchColumn();

    // Kiểm tra trong bảng create_schedules
    $stmt = $conn->prepare("SELECT COUNT(*) FROM create_schedules WHERE employeeID = :employeeID");
    $stmt->execute([':employeeID' => $employeeID]);
    $createScheduleCount = $stmt->fetchColumn();

    // Nếu có dữ liệu trong ít nhất một bảng
    $hasSchedule = ($scheduleCount > 0 || $createScheduleCount > 0);
} catch (PDOException $e) {
    error_log("Lỗi kiểm tra thời khóa biểu: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý nghiệp vụ giảng viên</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="./assets/css/index.css">
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

        .action-boxes {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin: 2rem auto;
            max-width: 1200px;
            padding: 0 1rem;
        }

        .action-box {
            padding: 2rem;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .action-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .action-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
        }

        .action-title {
            color: #223771;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .action-description {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.4;
            margin: 0;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .action-boxes {
                flex-direction: column;
                align-items: center;
            }
            
            .action-box-link {
                width: 100%;
                max-width: none;
            }
        }

        .action-box-link {
            flex: 1;
            max-width: 400px;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .action-box-link:hover {
            text-decoration: none;
        }

        /* Thêm CSS cho footer */
        .footer {
            background-color: #223771;
            color: white;
            font-size: 0.8rem;
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
    <!-- Add Facebook SDK -->
    <div id="fb-root"></div>
    <script async defer crossorigin="anonymous" 
        src="https://connect.facebook.net/vi_VN/sdk.js#xfbml=1&version=v18.0" 
        nonce="random_nonce">
    </script>

    <header class="header">
        <nav class="nav">
            <ul class="nav-list">
                <li class="nav-item">
                    <?php if ($hasSchedule): ?>
                        <a href="./UC/formRegister/formRegister.php" class="nav-link">Thống kê giảng dạy</a>
                    <?php else: ?>
                        <a href="#" class="nav-link" onclick="showScheduleWarning()">Thống kê giảng dạy</a>
                    <?php endif; ?>
                </li>
                <li class="nav-item"><a href="./UC/thoikhoabieu/thoikhoabieu.php" class="nav-link">Thời khóa biểu</a></li>
                <li class="nav-item"><a href="./UC/report/report.php" class="nav-link">Báo cáo</a></li>
            </ul>
            <div class="account-settings">
                <span class="account-name"><?php echo htmlspecialchars($fullName); ?></span>
                <div class="user-avatar">
                    <img id="avatar-preview" src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="Avatar">
                </div>
                <div class="account-menu">
                    <a href="#" class="account-menu-item" onclick="openAccountModal()">Cài đặt tài khoản</a>
                    <a href="#" class="account-menu-item" onclick="openPasswordModal()">Đổi mật khẩu</a>
                    <?php
                    if ($role === "Chuyên viên") {
                        echo '<a href="manage_account.php" class="account-menu-item">Quản lý người dùng</a>';
                        // echo '<a href="./UC/manage_NCKH/manage_NCKH.php" class="account-menu-item">Quản lý NCKH</a>';
                        echo '<a href="./manage_progress.php" class="account-menu-item">Quản lý tiến độ</a>';
                    }
                    ?>                    
                    <a href="./login/logout.php" class="account-menu-item">Đăng xuất</a>
                </div>
            </div>
        </nav>
    </header>

    <main class="main">
        <h1 class="main-title">Chào mừng đến với hệ thống quản lý nghiệp vụ giảng viên</h1>
        
        <div class="action-boxes">
            <a href="./assets/file/HDSD website quản lý nghiệp vụ giảng viên.docx" class="action-box-link" download>
                <div class="action-box">
                    <i class="action-icon">📖</i>
                    <h2 class="action-title">Hướng dẫn sử dụng</h2>
                    <p class="action-description">Xem hướng dẫn chi tiết về cách sử dụng hệ thống</p>
                    <p class="action-description" style="color: red;">Lưu ý: Phải mở bằng Microsoft Word</p>
                </div>
            </a>
        </div>
    </main>

    <!-- Modal cho Cài đặt tài khoản -->
    <div id="accountModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAccountModal()">×</span>
            <iframe id="accountFrame" src="" width="100%" height="600px" frameborder="0"></iframe>
        </div>
    </div>

    <!-- Modal cho Đổi mật khẩu -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closePasswordModal()">×</span>
            <iframe id="passwordFrame" src="" width="100%" height="600px" frameborder="0"></iframe>
        </div>
    </div>

    <!-- Popup và overlay -->
    <div class="overlay" id="overlay"></div>
    <div class="popup" id="popup">
        <p id="popup-message"></p>
        <button onclick="closePopup()">Đóng</button>
    </div>

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
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3724.6409878661737!2d105.75986221476873!3d21.047048685988825!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x313454c3ce577141%3A0xb1a1ac92701777bc!2zVHLGsOG7nW5nIMSQ4bqhaSBo4buNYyBUw6BpIG5ndXnDqm4gdsOgIE3DtGkgdHLGsOG7nW5nIEjDoCBO4buZaQ!5e0!3m2!1svi!2s!4v1710080669634!5m2!1svi!2s"                    class="map-container"
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

    <script>
        function openAccountModal() {
            const iframe = document.getElementById('accountFrame');
            iframe.src = './UC/formRegister/accountSetting/account_setting.php';
            document.getElementById('accountModal').style.display = 'block';
            iframe.onload = () => adjustPopupSize('accountFrame');
        }

        function closeAccountModal() {
            document.getElementById('accountModal').style.display = 'none';
            document.getElementById('accountFrame').src = '';
        }

        function openPasswordModal() {
            const iframe = document.getElementById('passwordFrame');
            iframe.src = './UC/formRegister/accountSetting/change_password.php';
            document.getElementById('passwordModal').style.display = 'block';
            iframe.onload = () => adjustPopupSize('passwordFrame');
        }

        function closePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
            document.getElementById('passwordFrame').src = '';
        }

        function openAddTeacherModal() {
            const iframe = document.getElementById('addTeacherFrame');
            iframe.src = './UC/formRegister/accountSetting/add_teacher.php';
            document.getElementById('addTeacherModal').style.display = 'block';
            iframe.onload = () => adjustPopupSize('addTeacherFrame');
        }

        function closeAddTeacherModal() {
            document.getElementById('addTeacherModal').style.display = 'none';
            document.getElementById('addTeacherFrame').src = '';
        }

        window.onclick = function(event) {
            var accountModal = document.getElementById('accountModal');
            var passwordModal = document.getElementById('passwordModal');
            var addTeacherModal = document.getElementById('addTeacherModal');
            if (event.target == accountModal) {
                closeAccountModal();
            } else if (event.target == passwordModal) {
                closePasswordModal();
            } else if (event.target == addTeacherModal) {
                closeAddTeacherModal();
            }
        }

        function adjustPopupSize(iframeId) {
            const popup = document.getElementById('popup');
            const iframe = document.getElementById(iframeId);
            if (iframe && popup.style.display === 'block') {
                setTimeout(() => {
                    const iframeRect = iframe.getBoundingClientRect();
                    popup.style.width = iframeRect.width + 'px';
                    popup.style.maxHeight = iframeRect.height + 'px';
                }, 100);
            }
        }

        function showPopup(message, type) {
            const popup = document.getElementById('popup');
            const overlay = document.getElementById('overlay');
            const popupMessage = document.getElementById('popup-message');

            popupMessage.textContent = message;
            popup.className = 'popup ' + type;
            popup.style.display = 'block';
            overlay.style.display = 'block';
        }

        function closePopup() {
            const popup = document.getElementById('popup');
            const overlay = document.getElementById('overlay');
            popup.style.display = 'none';
            overlay.style.display = 'none';
        }

        function showScheduleWarning() {
            showPopup('Vui lòng tải lên thời khóa biểu trước khi xem thống kê giảng dạy', 'error');
        }
    </script>
</body>
</html>