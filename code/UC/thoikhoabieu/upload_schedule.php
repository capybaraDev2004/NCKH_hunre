<?php
session_start();
require '../../connection/connection.php';
require __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Kiểm tra đăng nhập
if (!isset($_SESSION['employeeID'])) {
    header("Location: ../../login/login.php");
    exit();
}

// Lấy employeeID và fullName từ session
$employeeID = $_SESSION['employeeID'] ?? '';
$fullName = $_SESSION['fullName'] ?? '';

if (empty($employeeID) || empty($fullName)) {
    echo "⚠️ Thiếu thông tin employeeID hoặc fullName trong session.";
    exit();
}

// Lấy teacherID từ bảng employee dựa trên employeeID
try {
    $stmt = $conn->prepare("SELECT teacherID FROM employee WHERE employeeID = :employeeID");
    $stmt->bindValue(":employeeID", $employeeID, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result || empty($result['teacherID'])) {
        echo "⚠️ Không tìm thấy teacherID cho employeeID: $employeeID trong bảng employee.";
        exit();
    }

    $teacherID = $result['teacherID'];
} catch (PDOException $e) {
    echo "⚠️ Lỗi khi truy vấn teacherID từ bảng employee: " . $e->getMessage();
    exit();
}

if (!isset($_FILES['file']['name']) || empty($_FILES['file']['name'])) {
    echo "⚠️ Vui lòng chọn file Excel để tải lên.";
    exit();
}

try {
    $file = $_FILES['file']['tmp_name'];
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $data = $sheet->toArray();

    // Kiểm tra dữ liệu từ file Excel
    if (empty($data) || count($data) <= 1) {
        echo "⚠️ File Excel không có dữ liệu (chỉ có tiêu đề hoặc rỗng).";
        exit();
    }

    // Định nghĩa ánh xạ cột từ Excel vào Database
    $columnMapping = [
        "Lớp" => "class",
        "Tên lớp học phần" => "subject_name",
        "Giảng viên giảng dạy" => "teacher_name",
        "LT" => "LT",
        "TH" => "TH",
        "Số tuần" => "Number_of_weeks",
        "Số TC" => "credits",
        "Hình thức học" => "study_type",
        "Thời gian" => "time",
        "Lịch học trong tuần" => "note"
    ];

    // Lấy tiêu đề từ dòng đầu tiên
    $headers = array_map('trim', $data[0]);

    // Kiểm tra tiêu đề
    if (empty($headers)) {
        echo "⚠️ File Excel không có tiêu đề.";
        exit();
    }

    // Lấy index của từng cột theo tiêu đề
    $columnIndexes = [];
    $missingColumns = [];
    foreach ($columnMapping as $excelColumn => $dbColumn) {
        $index = array_search($excelColumn, $headers);
        if ($index === false) {
            $missingColumns[] = $excelColumn;
        } else {
            $columnIndexes[$dbColumn] = $index;
        }
    }

    // Kiểm tra nếu có cột bị thiếu
    if (!empty($missingColumns)) {
        echo "⚠️ Các cột sau không tồn tại trong file Excel: " . implode(", ", $missingColumns);
        exit();
    }

    // Kiểm tra nếu $columnIndexes rỗng
    if (empty($columnIndexes)) {
        echo "⚠️ Không thể ánh xạ cột từ file Excel.";
        exit();
    }

    // Chuẩn bị câu lệnh SQL cho schedule
    $stmtSchedule = $conn->prepare("INSERT INTO schedule 
        (class, subject_name, teacher_name, LT, TH, Number_of_weeks, credits, study_type, time, note, employeeID) 
        VALUES (:class, :subject_name, :teacher_name, :LT, :TH, :Number_of_weeks, :credits, :study_type, :time, :note, :employeeID)");

    // Lấy năm hiện tại
    $currentYear = date('Y');
    
    // Khởi tạo biến tổng số tiết
    $totalLT = 0;  // Tổng số tiết lý thuyết
    $totalTH = 0;  // Tổng số tiết thực hành
    $totalPeriods = 0;  // Tổng số tiết (LT + TH)
    $totalMuc5 = 0;  // Tổng số tiết cho muc5 (tổng các hàng có cả LT > 0 và TH > 0)
    $totalMuc6 = 0;  // Tổng số tiết cho muc6 (tổng các hàng có LT = 0 và TH > 0)

    // Lặp qua từng dòng dữ liệu (bỏ qua tiêu đề)
    $insertedRows = 0;
    for ($i = 1; $i < count($data); $i++) {
        $row = $data[$i];

        // Kiểm tra dữ liệu có đủ không
        if (count($row) < count($columnMapping)) {
            continue;
        }

        // Ánh xạ dữ liệu đúng cột
        $values = [];
        foreach ($columnIndexes as $dbColumn => $index) {
            $values[$dbColumn] = isset($row[$index]) && trim($row[$index]) !== '' ? trim($row[$index]) : '';
        }

        // Tách fullName và teacherID từ cột teacher_name
        $teacherInfo = $values['teacher_name'];
        if (!preg_match('/^(.*?)\s*\(\s*(.*?)\s*\)$/', $teacherInfo, $matches)) {
            continue; // Bỏ qua dòng này nếu không đúng định dạng
        }

        $extractedFullName = trim($matches[1]);
        $extractedTeacherID = trim($matches[2]);

        // Chuẩn hóa dữ liệu để so sánh: bỏ dấu chấm, dấu cách
        $extractedFullNameNormalized = preg_replace('/[\.\s]+/', '', mb_strtolower($extractedFullName, 'UTF-8'));
        $fullNameNormalized = preg_replace('/[\.\s]+/', '', mb_strtolower($fullName, 'UTF-8'));
        $extractedTeacherIDNormalized = preg_replace('/[\.\s]+/', '', mb_strtolower($extractedTeacherID, 'UTF-8'));
        $teacherIDNormalized = preg_replace('/[\.\s]+/', '', mb_strtolower($teacherID, 'UTF-8'));

        // Kiểm tra cả fullName và teacherID có khớp không
        $fullNameMatch = ($extractedFullNameNormalized === $fullNameNormalized);
        $teacherIDMatch = ($extractedTeacherIDNormalized === $teacherIDNormalized);

        if ($fullNameMatch && $teacherIDMatch) {
            try {
                $stmtSchedule->execute([
                    ':class' => $values['class'],
                    ':subject_name' => $values['subject_name'],
                    ':teacher_name' => $values['teacher_name'],
                    ':LT' => $values['LT'],
                    ':TH' => $values['TH'],
                    ':Number_of_weeks' => $values['Number_of_weeks'],
                    ':credits' => $values['credits'],
                    ':study_type' => $values['study_type'],
                    ':time' => $values['time'],
                    ':note' => $values['note'],
                    ':employeeID' => $_SESSION['employeeID']
                ]);
                
                // Tính tổng số tiết cho mỗi loại
                $lt = intval($values['LT']);
                $th = intval($values['TH']);
                $totalLT += $lt;  // Cộng dồn số tiết lý thuyết
                $totalTH += $th;  // Cộng dồn số tiết thực hành
                
                // Tính tổng cho mục 5 (chỉ tính các hàng có cả LT > 0 và TH > 0)
                if ($lt > 0 && $th > 0) {
                    $totalMuc5 += ($lt + $th);
                }
                
                // Tính tổng cho mục 6 (chỉ tính các hàng có LT = 0 và TH > 0)
                if ($lt == 0 && $th > 0) {
                    $totalMuc6 += $th;
                }
                
                $insertedRows++;
            } catch (PDOException $e) {
                echo "❌ Lỗi khi thêm dòng $i: " . $e->getMessage() . "<br>";
                continue;
            }
        }
    }

    if ($insertedRows > 0) {
        // Tính tổng số tiết cuối cùng
        $totalPeriods = $totalLT + $totalTH;

        // Cập nhật hoặc thêm mới vào bảng giangday
        $stmtGiangday = $conn->prepare("
            INSERT INTO giangday (employeeID, result_year, muc1_tong_tiet, muc1_gio_quy_doi, muc5_tong_tiet, muc6_tong_tiet) 
            VALUES (:employeeID, :result_year, :total_periods1, :total_periods2, :total_muc5, :total_muc6)
            ON DUPLICATE KEY UPDATE 
            muc1_tong_tiet = :total_periods3,
            muc1_gio_quy_doi = :total_periods4,
            muc5_tong_tiet = :total_muc5_update,
            muc6_tong_tiet = :total_muc6_update,
            ngay_cap_nhat = CURRENT_TIMESTAMP
        ");
        
        $stmtGiangday->execute([
            ':employeeID' => $employeeID,
            ':result_year' => $currentYear,
            ':total_periods1' => $totalPeriods,
            ':total_periods2' => $totalPeriods,
            ':total_periods3' => $totalPeriods,
            ':total_periods4' => $totalPeriods,
            ':total_muc5' => $totalMuc5,
            ':total_muc5_update' => $totalMuc5,
            ':total_muc6' => $totalMuc6,
            ':total_muc6_update' => $totalMuc6
        ]);

        echo "✅ Tải file Excel thành công! Đã thêm $insertedRows dòng dữ liệu.";
        header("Location: thoikhoabieu.php");
        exit();
    } else {
        echo "⚠️ Không có dòng dữ liệu nào được thêm. Kiểm tra lại file Excel hoặc thông tin giảng viên.";
    }
} catch (Exception $e) {
    echo "⚠️ Lỗi khi đọc file Excel: " . $e->getMessage();
}
