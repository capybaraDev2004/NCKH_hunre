<?php
session_start();
require_once '..\..\..\vendor\autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['taskType']) && $_POST['taskType'] === 'giangday') {
    $year = $_POST['year'] ?? date('Y');
    $filename = $_POST['filename'] ?? 'TongHopGiangDay';
    $filename = preg_replace('/[^A-Za-z0-9_-]/', '', $filename) . '.xlsx'; // Sanitize filename

    // Khởi tạo Spreadsheet
    $spreadsheet = new Spreadsheet();

    // Sheet 1: Bảng 1
    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('Bảng 1');

    // Tiêu đề chính
    $sheet1->mergeCells('A1:R1');
    $sheet1->setCellValue('A1', 'TỔNG HỢP KHỐI LƯỢNG GIẢNG DẠY NĂM ' . $year);
    $sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet1->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Tiêu đề Bảng 1
    $sheet1->setCellValue('A2', 'I. BẢNG 1: TỔNG HỢP KHỐI LƯỢNG GIỜ ĐỨNG LỚP TRỰC TIẾP');
    $sheet1->getStyle('A2')->getFont()->setBold(true);

    // Tiêu đề cột
    $headers1 = [
        'STT', 'GIẢNG VIÊN', 'Học Phần', 'Lớp', 'Số TC', 'Sỹ số',
        'Hình thức học (trực tiếp/trực tuyến)', 'Hình thức học (LT/TH)',
        'Số tiết (Lớp đông)', 'Số tiết (Ngoài giờ)', 'Số tiết (Giảng dạy bằng tiếng anh)',
        'Số tiết (Cao học)', 'Số tiết (Thực hành)', 'Quy đổi giờ chuẩn', 'Tổng',
        'KHOA/BỘ MÔN', 'Ghi chú', 'NĂM HỌC'
    ];
    $sheet1->fromArray($headers1, null, 'A4');
    $sheet1->getStyle('A4:R4')->getFont()->setBold(true);
    $sheet1->getStyle('A4:R4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet1->getStyle('A4:R4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sheet1->getStyle('A4:R4')->getAlignment()->setWrapText(true);

    // Áp dụng viền đen cho tiêu đề cột
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'], // Màu đen
            ],
        ],
    ];
    $sheet1->getStyle('A4:R4')->applyFromArray($styleArray);

    // Định dạng độ rộng cột (ước lượng từ hình ảnh)
    $sheet1->getColumnDimension('A')->setWidth(5);   // STT
    $sheet1->getColumnDimension('B')->setWidth(20);  // Giảng viên
    $sheet1->getColumnDimension('C')->setWidth(15);  // Học Phần
    $sheet1->getColumnDimension('D')->setWidth(10);  // Lớp
    $sheet1->getColumnDimension('E')->setWidth(8);   // Số TC
    $sheet1->getColumnDimension('F')->setWidth(8);   // Sỹ số
    $sheet1->getColumnDimension('G')->setWidth(15);  // Hình thức học (trực tiếp/trực tuyến)
    $sheet1->getColumnDimension('H')->setWidth(15);  // Hình thức học (LT/TH)
    $sheet1->getColumnDimension('I')->setWidth(10);  // Số tiết (Lớp đông)
    $sheet1->getColumnDimension('J')->setWidth(10);  // Số tiết (Ngoài giờ)
    $sheet1->getColumnDimension('K')->setWidth(15);  // Số tiết (Giảng dạy bằng tiếng anh)
    $sheet1->getColumnDimension('L')->setWidth(10);  // Số tiết (Cao học)
    $sheet1->getColumnDimension('M')->setWidth(10);  // Số tiết (Thực hành)
    $sheet1->getColumnDimension('N')->setWidth(15);  // Quy đổi giờ chuẩn
    $sheet1->getColumnDimension('O')->setWidth(10);  // Tổng
    $sheet1->getColumnDimension('P')->setWidth(15);  // KHOA/BỘ MÔN
    $sheet1->getColumnDimension('Q')->setWidth(15);  // Ghi chú
    $sheet1->getColumnDimension('R')->setWidth(10);  // NĂM HỌC

    // Sheet 2: Bảng 2
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('Bảng 2');

    // Tiêu đề chính
    $sheet2->mergeCells('A1:AD1');
    $sheet2->setCellValue('A1', 'TỔNG HỢP KHỐI LƯỢNG GIẢNG DẠY NĂM ' . $year);
    $sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet2->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Tiêu đề Bảng 2
    $sheet2->setCellValue('A2', 'II. BẢNG 2: TỔNG HỢP KHỐI LƯỢNG GIỜ QUY ĐỔI TỪ CÔNG TÁC KHÁC');
    $sheet2->getStyle('A2')->getFont()->setBold(true);

    // Tiêu đề nhóm (gộp ô)
    $sheet2->mergeCells('A3:A4');
    $sheet2->setCellValue('A3', 'STT');
    $sheet2->mergeCells('B3:B4');
    $sheet2->setCellValue('B3', 'Giảng viên');
    $sheet2->mergeCells('C3:H3');
    $sheet2->setCellValue('C3', 'Dữ liệu tổng hợp của phòng KT&ĐBCLGD');
    $sheet2->mergeCells('I3:I4');
    $sheet2->setCellValue('I3', 'Coi thi');
    $sheet2->mergeCells('J3:K3');
    $sheet2->setCellValue('J3', 'Hướng dẫn khóa luận TN');
    $sheet2->mergeCells('L3:M3');
    $sheet2->setCellValue('L3', 'Hướng dẫn đề án TN');
    $sheet2->mergeCells('N3:S3');
    $sheet2->setCellValue('N3', 'Hội đồng thẩm CTĐT');
    $sheet2->mergeCells('T3:AB3');
    $sheet2->setCellValue('T3', 'Xây dựng bài giảng điện tử');
    $sheet2->mergeCells('AC3:AC4');
    $sheet2->setCellValue('AC3', 'KHOA/BỘ MÔN');
    $sheet2->mergeCells('AD3:AD4');
    $sheet2->setCellValue('AD3', 'Môn học');
    $sheet2->mergeCells('AE3:AE4');
    $sheet2->setCellValue('AE3', 'Tình trạng');

    // Tiêu đề cột chi tiết
    $headers2 = [
        '', '', // STT, Giảng viên (đã gộp ở trên)
        'Xây dựng NH câu hỏi thi', 'HD Thẩm định xây NHCHT', 'Ra đề', 'Phản biện đề', 'Chấm thi kết thúc học phần', 'Chấm báo cáo khóa luận tốt nghiệp',
        '', // Coi thi (đã gộp ở trên)
        'Số SV', 'Quy đổi ra tiền',
        'Số HV', 'Quy đổi ra tiền',
        'Số QĐ', 'Chủ tịch HĐ', 'Thư ký HĐ', 'Phản biện', 'Ủy viên', 'Quy đổi ra tiền',
        'Số TC', 'Mức độ 1', 'Mức độ 2', 'Mức độ 3', 'Mức độ 4', 'Quy đổi ra tiền', 'Số QĐ', 'Tỷ lệ đào tạo trực tuyến của học phần (%)', 'Tỷ lệ mức độ đóng góp tham gia xây dựng BGĐT (%)',
        '', '', '' // KHOA/BỘ MÔN, Môn học, Tình trạng (đã gộp ở trên)
    ];
    $sheet2->fromArray($headers2, null, 'A4');
    $sheet2->getStyle('A3:AE4')->getFont()->setBold(true);
    $sheet2->getStyle('A3:AE4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet2->getStyle('A3:AE4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sheet2->getStyle('A3:AE4')->getAlignment()->setWrapText(true);

    // Áp dụng viền đen cho tiêu đề cột
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'], // Màu đen
            ],
        ],
    ];
    $sheet2->getStyle('A3:AE4')->applyFromArray($styleArray);

    // Định dạng độ rộng cột (ước lượng từ hình ảnh)
    $sheet2->getColumnDimension('A')->setWidth(5);   // STT
    $sheet2->getColumnDimension('B')->setWidth(20);  // Giảng viên
    $sheet2->getColumnDimension('C')->setWidth(10);  // Xây dựng NH câu hỏi thi
    $sheet2->getColumnDimension('D')->setWidth(15);  // HD Thẩm định xây NHCHT
    $sheet2->getColumnDimension('E')->setWidth(8);   // Ra đề
    $sheet2->getColumnDimension('F')->setWidth(10);  // Phản biện đề
    $sheet2->getColumnDimension('G')->setWidth(15);  // Chấm thi kết thúc học phần
    $sheet2->getColumnDimension('H')->setWidth(15);  // Chấm báo cáo khóa luận tốt nghiệp
    $sheet2->getColumnDimension('I')->setWidth(8);   // Coi thi
    $sheet2->getColumnDimension('J')->setWidth(8);   // Số SV
    $sheet2->getColumnDimension('K')->setWidth(10);  // Quy đổi ra tiền (Hướng dẫn khóa luận TN)
    $sheet2->getColumnDimension('L')->setWidth(8);   // Số HV
    $sheet2->getColumnDimension('M')->setWidth(10);  // Quy đổi ra tiền (Hướng dẫn đề án TN)
    $sheet2->getColumnDimension('N')->setWidth(8);   // Số QĐ (Hội đồng)
    $sheet2->getColumnDimension('O')->setWidth(10);  // Chủ tịch HĐ
    $sheet2->getColumnDimension('P')->setWidth(10);  // Thư ký HĐ
    $sheet2->getColumnDimension('Q')->setWidth(10);  // Phản biện
    $sheet2->getColumnDimension('R')->setWidth(8);   // Ủy viên
    $sheet2->getColumnDimension('S')->setWidth(10);  // Quy đổi ra tiền (Hội đồng)
    $sheet2->getColumnDimension('T')->setWidth(8);   // Số TC (Bài giảng)
    $sheet2->getColumnDimension('U')->setWidth(8);   // Mức độ 1
    $sheet2->getColumnDimension('V')->setWidth(8);   // Mức độ 2
    $sheet2->getColumnDimension('W')->setWidth(8);   // Mức độ 3
    $sheet2->getColumnDimension('X')->setWidth(8);   // Mức độ 4
    $sheet2->getColumnDimension('Y')->setWidth(10);  // Quy đổi ra tiền (Bài giảng)
    $sheet2->getColumnDimension('Z')->setWidth(8);   // Số QĐ (Bài giảng)
    $sheet2->getColumnDimension('AA')->setWidth(15); // Tỷ lệ đào tạo trực tuyến
    $sheet2->getColumnDimension('AB')->setWidth(15); // Tỷ lệ mức độ đóng góp
    $sheet2->getColumnDimension('AC')->setWidth(15); // KHOA/BỘ MÔN
    $sheet2->getColumnDimension('AD')->setWidth(15); // Môn học
    $sheet2->getColumnDimension('AE')->setWidth(10); // Tình trạng

    // Xuất file
    $writer = new Xlsx($spreadsheet);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer->save('php://output');
    exit;
} else {
    // Nếu không phải taskType=giangday, chuyển hướng về report.php
    header('Location: report.php');
    exit;
}
?>