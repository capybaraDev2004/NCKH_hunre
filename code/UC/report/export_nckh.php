<?php
session_start();

// Đảm bảo thư viện PhpSpreadsheet được tải
try {
    require_once '..\..\..\vendor\autoload.php';
} catch (Exception $e) {
    die('Lỗi: Không thể tải thư viện PhpSpreadsheet. Vui lòng kiểm tra đường dẫn hoặc cài đặt thư viện: ' . $e->getMessage());
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['taskType']) && $_POST['taskType'] === 'nckh') {
    $year = $_POST['year'] ?? '2024';
    $filename = $_POST['filename'] ?? 'BaoCaoNCKH';
    $filename = preg_replace('/[^A-Za-z0-9_-]/', '', $filename) . '.xlsx'; // Sanitize filename

    try {
        // Khởi tạo Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('NCKH');

        // Tiêu đề chính
        $sheet->mergeCells('A1:S1');
        $sheet->setCellValue('A1', 'DANH SÁCH GIẢNG VIÊN KÊ KHAI SẢN PHẨM KHOA HỌC VÀ ĐỀ NGHỊ CÔNG NHẬN ĐỊNH MỨC NGHIÊN CỨU KHOA HỌC LẦN 2 NĂM ' . $year);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Tiêu đề phụ
        $sheet->mergeCells('A2:S2');
        $sheet->setCellValue('A2', '(Kèm theo Quyết định số: /QĐ-TĐHHN ngày tháng 12 năm 2024 của Trường Đại học Tài nguyên và Môi trường Hà Nội)');
        $sheet->getStyle('A2')->getFont()->setItalic(true);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Tiêu đề cột
        $headers = [
            'TT', 'Họ và tên', 'Tên sản phẩm khoa học', 'Chức danh nghề nghiệp', 'Tên tạp chí/ Hội thảo',
            'Tên đơn vị xuất bản/Tổ chức Hội thảo', 'Mã số XB; Số XB', 'Năm xuất bản', 'Điểm Tạp chí',
            'Tác giả', '', '', '', 'Khối lượng giờ giảng', 'Tổng Khối lượng NCKH',
            'Định mức NCKH phải hoàn thành', 'Khối lượng NCKH còn thiếu', 'Tạp chí trong nước', 'Tạp chí QT'
        ];
        $sheet->fromArray($headers, null, 'A3');
        $sheet->getStyle('A3:S3')->getFont()->setBold(true);
        $sheet->getStyle('A3:S3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A3:S3')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A3:S3')->getAlignment()->setWrapText(true);

        // Gộp ô và tiêu đề phụ cho cột "Tác giả"
        $sheet->mergeCells('J3:M3'); // Gộp ô J3:M3 cho tiêu đề "Tác giả"
        $sheet->setCellValue('J4', 'Tổng số');
        $sheet->mergeCells('K4:L4'); // Gộp ô K4:L4 cho "Trong trường"
        $sheet->setCellValue('K4', 'Trong trường');
        $sheet->setCellValue('M4', 'Ngoài trường');
        $sheet->setCellValue('K5', 'Họ và tên');
        $sheet->setCellValue('L5', 'Đơn vị');
        $sheet->getStyle('J4:M5')->getFont()->setBold(true);
        $sheet->getStyle('J4:M5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('J4:M5')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // Đặt chiều cao hàng để ô con của "Tác giả" ngang bằng với "Điểm Tạp chí"
        $sheet->getRowDimension(4)->setRowHeight(30);
        $sheet->getRowDimension(5)->setRowHeight(30);

        // Áp dụng viền đen cho tiêu đề cột
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'], // Màu đen
                ],
            ],
        ];
        $sheet->getStyle('A3:S5')->applyFromArray($styleArray);

        // Định dạng độ rộng cột
        $sheet->getColumnDimension('A')->setWidth(5);   // TT
        $sheet->getColumnDimension('B')->setWidth(15);  // Họ và tên
        $sheet->getColumnDimension('C')->setWidth(40);  // Tên sản phẩm khoa học
        $sheet->getColumnDimension('D')->setWidth(10);  // Chức danh nghề nghiệp
        $sheet->getColumnDimension('E')->setWidth(20);  // Tên tạp chí/Hội thảo
        $sheet->getColumnDimension('F')->setWidth(30);  // Tên đơn vị xuất bản
        $sheet->getColumnDimension('G')->setWidth(20);  // Mã số XB; Số XB
        $sheet->getColumnDimension('H')->setWidth(10);  // Năm xuất bản
        $sheet->getColumnDimension('I')->setWidth(10);  // Điểm Tạp chí
        $sheet->getColumnDimension('J')->setWidth(10);  // Tác giả: Tổng số
        $sheet->getColumnDimension('K')->setWidth(20);  // Tác giả: Họ và tên
        $sheet->getColumnDimension('L')->setWidth(15);  // Tác giả: Đơn vị
        $sheet->getColumnDimension('M')->setWidth(15);  // Tác giả: Ngoài trường
        $sheet->getColumnDimension('N')->setWidth(15);  // Khối lượng giờ giảng
        $sheet->getColumnDimension('O')->setWidth(15);  // Tổng Khối lượng NCKH
        $sheet->getColumnDimension('P')->setWidth(15);  // Định mức NCKH phải hoàn thành
        $sheet->getColumnDimension('Q')->setWidth(15);  // Khối lượng NCKH còn thiếu
        $sheet->getColumnDimension('R')->setWidth(10);  // Tạp chí trong nước
        $sheet->getColumnDimension('S')->setWidth(10);  // Tạp chí QT

        // Dữ liệu mẫu (hard-coded từ STT 1 - Lê Phú Hưng)
        // Hàng đầu tiên chỉ chứa TT và Họ và tên
        $sheet->setCellValue('A6', '1');
        $sheet->setCellValue('B6', 'Lê Phú Hưng');
        $sheet->getStyle('A6:S6')->applyFromArray($styleArray);

        // Các sản phẩm khoa học bắt đầu từ hàng 7
        $products = [
            // Sản phẩm 1
            ['', '', 'Hướng dẫn NCKH SV Đề tài: Xây dựng nền tảng khoa học mở: ứng dụng thí điểm lĩnh vực tài nguyên nước', 'GV', 'NCKH SV', 'Trường Đại học Tài nguyên và Môi trường Hà Nội', 'QĐ 2780/QĐ-TĐHHN 28/6/2024', '2024', '', '', '', '', '', '198', '692', '590', '0', '', ''],
            // Sản phẩm 2
            ['', '', 'Nghiên cứu ứng dụng trí tuệ nhân tạo trong các dịch vụ tài chính phục vụ cho dạy học chuyên ngành', '', 'Tạp chí Thiết bị giáo dục', 'Cơ quan của Hiệp hội Thiết bị Giáo dục Việt Nam', 'ISSN: 1859-0810; Số 323 kỳ 2 tháng 10/2024', '2024', '0.5', '2', '1. Nguyễn Hải Đăng (70%) 2. Lê Phú Hưng (30%)', 'Khoa CNTT', '', '', '', '', '', 'X', ''],
            // Sản phẩm 3
            ['', '', 'Đề xuất mô hình mạng Nơ-ron nhân tạo trong xây dựng nhận dạng các phương tiện giao thông qua Video', '', 'Tạp chí Thiết bị giáo dục', 'Cơ quan của Hiệp hội Thiết bị Giáo dục Việt Nam', 'ISSN: 1859-0810; Số tháng 11/2024', '2024', '0.5', '2', 'Lê Phú Hưng, Nguyễn Văn Hách', 'Khoa CNTT', '', '', '', '', '', 'X', '']
        ];

        // Thêm dữ liệu sản phẩm vào sheet
        $rowIndex = 7;
        foreach ($products as $rowData) {
            $sheet->fromArray($rowData, null, 'A' . $rowIndex);
            // Gộp ô K:L cho cột "Họ và tên" và "Đơn vị" trong "Trong trường"
            $sheet->mergeCells('K' . $rowIndex . ':L' . $rowIndex);
            $sheet->getStyle('A' . $rowIndex . ':S' . $rowIndex)->applyFromArray($styleArray);
            $rowIndex++;
        }

        // Gộp ô cho các cột cần gộp (theo số dòng sản phẩm = 4 dòng: 1 dòng TT + Họ và tên, 3 dòng sản phẩm)
        $sheet->mergeCells('A3:A5'); // TT
        $sheet->mergeCells('B3:C5'); // TT
        $sheet->mergeCells('C3:C5'); // TT
        $sheet->mergeCells('D3:D5'); // TT
        $sheet->mergeCells('E3:E5'); // TT
        $sheet->mergeCells('F3:F5'); // TT
        $sheet->mergeCells('G3:G5'); // TT
        $sheet->mergeCells('H3:H5'); // TT
        $sheet->mergeCells('I3:I5'); // TT
        $sheet->mergeCells('N3:N5'); // TT
        $sheet->mergeCells('O3:O5'); // TT
        $sheet->mergeCells('P3:P5'); // TT
        $sheet->mergeCells('Q3:Q5'); // TT
        $sheet->mergeCells('R3:R5'); // TT
        $sheet->mergeCells('S3:S5'); // TT
        $sheet->mergeCells('A7:A9'); // TT
        $sheet->mergeCells('B7:B9'); // Họ và tên
        $sheet->mergeCells('D7:D9'); // Chức danh nghề nghiệp (bắt đầu từ hàng 7)
        $sheet->mergeCells('N7:N9'); // Khối lượng giờ giảng
        $sheet->mergeCells('O7:O9'); // Tổng Khối lượng NCKH
        $sheet->mergeCells('P7:P9'); // Định mức NCKH phải hoàn thành
        $sheet->mergeCells('Q7:Q9'); // Khối lượng NCKH còn thiếu
        $sheet->mergeCells('R7:R9'); // Tạp chí trong nước
        $sheet->mergeCells('S7:S9'); // Tạp chí QT

        // Thiết lập headers để tải file Excel
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        header('Expires: 0');

        // Xuất file
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } catch (Exception $e) {
        // Hiển thị lỗi nếu có vấn đề khi tạo file
        die('Lỗi khi tạo file Excel: ' . $e->getMessage());
    }
} else {
    // Nếu không phải taskType=nckh, chuyển hướng về nckh.php
    header('Location: nckh.php');
    exit;
}
?>