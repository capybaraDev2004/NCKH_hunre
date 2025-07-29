<?php
// Kết nối CSDL
require_once '../../connection/connection.php';
require_once '..\..\..\vendor\autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Khởi tạo session
session_start();

// Kiểm tra và lấy tên bảng từ session
if (!isset($_SESSION['current_table'])) {
    die(json_encode(['error' => 'Không tìm thấy bảng dữ liệu']));
}

$sql_table = $_SESSION['current_table'];

// Map tên bảng sang tên file
$tableDisplayNames = [
    'nckhcc_history' => 'Nghien_cuu_khoa_hoc_cac_cap',
    'huongdansv_history' => 'Huong_dan_sinh_vien_NCKH',
    'bai_bao_history' => 'Viet_bai_bao',
    'vietsach_history' => 'Viet_sach'
];

// Lấy tên file từ map
$filename = $tableDisplayNames[$sql_table] ?? $sql_table;

// Định nghĩa các cột và nhãn
$columns = [
    "id", "employeeID", "result_year", "nckh_id", "noi_dung", "ten_san_pham", 
    "so_luong", "vai_tro", "so_tac_gia", "phan_tram_dong_gop", "gio_quy_doi", 
    "ngay_cap_nhat", "note", "diem_tap_chi", "ma_so_xuat_ban", 
    "ten_don_vi_xuat_ban", "ten_hoi_thao", "thanh_vien_chu_nhom", 
    "nation_point", "student_infor"
];

$labels = [
    "STT", "Mã giảng viên", "Năm kết quả", "NCKH ID", "Nội dung", "Tên sản phẩm",
    "Số lượng", "Vai trò", "Số tác giả", "Phần trăm đóng góp", "Giờ quy đổi",
    "Ngày cập nhật", "Ghi chú", "Điểm tạp chí", "Mã số xuất bản",
    "Tên đơn vị xuất bản", "Tên hội thảo", "Tên chủ nhóm", "Điểm quốc tế",
    "Đại diện sinh viên"
];

try {
    // Tạo một Spreadsheet mới
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Thiết lập tiêu đề
    $sheet->setCellValue('A1', 'BÁO CÁO ' . strtoupper(str_replace('_', ' ', $filename)));
    $sheet->setCellValue('A2', 'Ngày xuất: ' . date('d/m/Y H:i:s'));
    
    // Merge cells cho tiêu đề
    $sheet->mergeCells('A1:T1');
    $sheet->mergeCells('A2:T2');
    
    // Style cho tiêu đề
    $sheet->getStyle('A1:T2')->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 14
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ]);

    // Thêm header
    $colIndex = 0;
    $columns = range('A', 'T'); // Tạo mảng các cột từ A đến T
    foreach ($labels as $label) {
        $sheet->setCellValue($columns[$colIndex] . '4', $label);
        $colIndex++;
    }

    // Style cho header
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '1565C0']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN
            ]
        ]
    ];
    $sheet->getStyle('A4:T4')->applyFromArray($headerStyle);

    // Thêm filter cho header
    $sheet->setAutoFilter('A4:T4');

    // Lấy dữ liệu từ bảng
    $sql = "SELECT h.*, e.teacherID, e.fullName 
            FROM $sql_table h 
            LEFT JOIN employee e ON h.employeeID = e.employeeID 
            ORDER BY h.ten_san_pham, h.thanh_vien_chu_nhom, h.result_year DESC, h.id DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Nhóm dữ liệu
    $grouped_data = [];
    foreach ($results as $row) {
        $key = $row['ten_san_pham'] . '|' . $row['thanh_vien_chu_nhom'] . '|' . $row['result_year'];
        if (!isset($grouped_data[$key])) {
            $grouped_data[$key] = [];
        }
        $grouped_data[$key][] = $row;
    }

    // Thêm dữ liệu
    $row = 5;
    $stt = 1;
    foreach ($grouped_data as $group) {
        $rowspan = count($group);
        foreach ($group as $index => $data) {
            $colIndex = 0; // Reset colIndex cho mỗi hàng
            
            // STT
            if ($index == 0) {
                $sheet->setCellValue($columns[$colIndex] . $row, $stt++);
                $sheet->mergeCells($columns[$colIndex] . $row . ':' . $columns[$colIndex] . ($row + $rowspan - 1));
            }
            $colIndex++;

            // Các cột còn lại
            $sheet->setCellValue($columns[$colIndex++] . $row, $data['teacherID'] . ' - ' . $data['fullName']);
            $sheet->setCellValue($columns[$colIndex++] . $row, $data['result_year']);
            $sheet->setCellValue($columns[$colIndex++] . $row, $data['nckh_id']);
            $sheet->setCellValue($columns[$colIndex++] . $row, $data['noi_dung']);
            $sheet->setCellValue($columns[$colIndex++] . $row, $data['ten_san_pham']);
            $sheet->setCellValue($columns[$colIndex++] . $row, $data['so_luong']);
            $sheet->setCellValue($columns[$colIndex++] . $row, $data['vai_tro']);
            $sheet->setCellValue($columns[$colIndex++] . $row, $data['so_tac_gia']);
            $sheet->setCellValue($columns[$colIndex++] . $row, $data['phan_tram_dong_gop'] . '%');
            $sheet->setCellValue($columns[$colIndex++] . $row, $data['gio_quy_doi']);
            $sheet->setCellValue($columns[$colIndex++] . $row, $data['ngay_cap_nhat']);
            $sheet->setCellValue($columns[$colIndex++] . $row, $data['note']);
            $sheet->setCellValue($columns[$colIndex++] . $row, $data['diem_tap_chi']);
            $sheet->setCellValue($columns[$colIndex++] . $row, $data['ma_so_xuat_ban']);
            $sheet->setCellValue($columns[$colIndex++] . $row, $data['ten_don_vi_xuat_ban']);
            $sheet->setCellValue($columns[$colIndex++] . $row, $data['ten_hoi_thao']);
            $sheet->setCellValue($columns[$colIndex++] . $row, $data['thanh_vien_chu_nhom']);
            $sheet->setCellValue($columns[$colIndex++] . $row, $data['nation_point']);
            $sheet->setCellValue($columns[$colIndex++] . $row, $data['student_infor']);

            $row++;
        }
    }

    // Style cho dữ liệu
    $lastRow = $row - 1;
    $dataStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN
            ]
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_LEFT,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true
        ]
    ];
    $sheet->getStyle('A5:T' . $lastRow)->applyFromArray($dataStyle);

    // Tự động điều chỉnh độ rộng cột
    foreach (range('A', 'T') as $col) {
        if ($col === 'E') { // Cột nội dung
            $sheet->getColumnDimension($col)->setWidth(50); // Giới hạn độ rộng cột nội dung
        } else {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

    }
    // Thiết lập header để tải file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');

    // Tạo file Excel
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch(Exception $e) {
    die(json_encode(['error' => 'Lỗi: ' . $e->getMessage()]));
}
?>
