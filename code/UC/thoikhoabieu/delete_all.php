<?php
session_start();
require '../../connection/connection.php'; // Kết nối MySQL bằng PDO

// Kiểm tra đăng nhập
if (!isset($_SESSION['employeeID'])) {
    echo "<script>
        alert('⚠️ Bạn cần đăng nhập để thực hiện thao tác này!');
        window.location.href = '../../login/login.php';
    </script>";
    exit();
}


// Kiểm tra token CSRF để bảo mật
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo "<script>
        alert('⚠️ Xác thực không hợp lệ!');
        window.location.href = 'thoikhoabieu.php';
    </script>";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Kiểm tra xem bảng schedule có dữ liệu không
        $stmt = $conn->query("SELECT COUNT(*) FROM schedule");
        $rowCount = $stmt->fetchColumn();

        if ($rowCount == 0) {
            echo "<script>
                alert('⚠️ Không có dữ liệu để xóa!');
                window.location.href = 'thoikhoabieu.php';
            </script>";
            exit();
        }

        // Bắt đầu transaction (Chỉ bắt đầu nếu chưa có transaction nào đang chạy)
        if (!$conn->inTransaction()) {
            $conn->beginTransaction();
        }

        // Xóa toàn bộ dữ liệu trong bảng (Prepared Statement)
        $deleteStmt = $conn->prepare("DELETE FROM schedule");
        $deleteStmt->execute();

        // Reset lại ID về 1
        $conn->exec("ALTER TABLE schedule AUTO_INCREMENT = 1");

        // Hoàn tất transaction (Chỉ commit nếu transaction đang hoạt động)
        if ($conn->inTransaction()) {
            $conn->commit();
        }

        // Xóa token CSRF sau khi sử dụng
        unset($_SESSION['csrf_token']);

        echo "<script>
            alert('✅ Đã xóa thành công $rowCount dòng dữ liệu!');
            window.location.href = 'thoikhoabieu.php';
        </script>";
        exit();
    } catch (PDOException $e) {
        // Kiểm tra transaction trước khi rollback để tránh lỗi
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        echo "<script>
            alert('❌ Lỗi khi xóa dữ liệu: " . addslashes($e->getMessage()) . "');
            window.location.href = 'thoikhoabieu.php';
        </script>";
        exit();
    }
} else {
    echo "<script>
        alert('⚠️ Yêu cầu không hợp lệ!');
        window.location.href = 'thoikhoabieu.php';
    </script>";
    exit();
}
