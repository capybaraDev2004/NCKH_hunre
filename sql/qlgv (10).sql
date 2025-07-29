-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 14, 2025 at 07:55 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `qlgv`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateTongHopNCKH` (IN `p_employeeID` VARCHAR(50), IN `p_result_year` INT)   BEGIN
    -- Xóa bản ghi cũ trong tonghop_nckh cho employeeID và năm
    DELETE FROM tonghop_nckh 
    WHERE employeeID = p_employeeID AND result_year = p_result_year;

    -- Tính tổng giờ quy đổi từ các bảng lịch sử
    INSERT INTO tong_hop_nckh (employeeID, result_year, tong_gio_nckh)
    SELECT 
        p_employeeID, 
        p_result_year, 
        COALESCE(SUM(gio_quy_doi), 0) as tong_gio
    FROM (
        SELECT gio_quy_doi FROM vietsach_history 
        WHERE employeeID = p_employeeID AND result_year = p_result_year
        UNION ALL
        SELECT gio_quy_doi FROM nckhcc_history 
        WHERE employeeID = p_employeeID AND result_year = p_result_year
        UNION ALL
        SELECT gio_quy_doi FROM huongdansv_history 
        WHERE employeeID = p_employeeID AND result_year = p_result_year
        UNION ALL
        SELECT gio_quy_doi FROM bai_bao_history 
        WHERE employeeID = p_employeeID AND result_year = p_result_year
    ) AS all_history;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `bai_bao_history`
--

CREATE TABLE `bai_bao_history` (
  `id` int(11) NOT NULL,
  `employeeID` int(11) NOT NULL,
  `result_year` int(11) NOT NULL,
  `nckh_id` int(11) NOT NULL,
  `noi_dung` text NOT NULL,
  `ten_san_pham` varchar(255) DEFAULT '',
  `so_luong` float DEFAULT 0,
  `so_tac_gia` int(11) DEFAULT 1,
  `phan_tram_dong_gop` float DEFAULT 0,
  `gsnn_id` int(11) DEFAULT NULL,
  `gio_quy_doi` float DEFAULT 0,
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `vai_tro` varchar(100) DEFAULT 'Thành viên',
  `note` varchar(10) NOT NULL,
  `diem_tap_chi` float DEFAULT NULL,
  `ma_so_xuat_ban` varchar(50) DEFAULT NULL,
  `ten_don_vi_xuat_ban` varchar(100) DEFAULT NULL,
  `ten_hoi_thao` varchar(100) DEFAULT NULL,
  `thanh_vien_chu_nhom` varchar(255) DEFAULT NULL,
  `nation_point` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `bai_bao_history`
--
DELIMITER $$
CREATE TRIGGER `after_baibao_delete` AFTER DELETE ON `bai_bao_history` FOR EACH ROW BEGIN
    CALL UpdateTongHopNCKH(OLD.employeeID, OLD.result_year);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_baibao_insert` AFTER INSERT ON `bai_bao_history` FOR EACH ROW BEGIN
    CALL UpdateTongHopNCKH(NEW.employeeID, NEW.result_year);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_baibao_update` AFTER UPDATE ON `bai_bao_history` FOR EACH ROW BEGIN
    CALL UpdateTongHopNCKH(NEW.employeeID, NEW.result_year);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `coi_thi`
--

CREATE TABLE `coi_thi` (
  `employeeID` int(11) NOT NULL,
  `result_year` int(11) NOT NULL,
  `muc1_so_cau` float DEFAULT 0,
  `muc1_gio_quy_doi` float DEFAULT 0,
  `muc2_so_cau` float DEFAULT 0,
  `muc2_gio_quy_doi` float DEFAULT 0,
  `muc3_so_cau` float DEFAULT 0,
  `muc3_gio_quy_doi` float DEFAULT 0,
  `muc4_so_cau` float DEFAULT 0,
  `muc4_gio_quy_doi` float DEFAULT 0,
  `muc5_so_cau` float DEFAULT 0,
  `muc5_gio_quy_doi` float DEFAULT 0,
  `muc6_so_cau` float DEFAULT 0,
  `muc6_gio_quy_doi` float DEFAULT 0,
  `muc7_so_cau` float DEFAULT 0,
  `muc7_gio_quy_doi` float DEFAULT 0,
  `muc8_so_cau` float DEFAULT 0,
  `muc8_gio_quy_doi` float DEFAULT 0,
  `muc9_so_cau` float DEFAULT 0,
  `muc9_gio_quy_doi` float DEFAULT 0,
  `muc10_so_cau` float DEFAULT 0,
  `muc10_gio_quy_doi` float DEFAULT 0,
  `muc11_so_cau` float DEFAULT 0,
  `muc11_gio_quy_doi` float DEFAULT 0,
  `tong_gio_quy_doi` float DEFAULT 0,
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `muc1_task_type` varchar(20) DEFAULT 'khong',
  `muc2_task_type` varchar(20) DEFAULT 'khong',
  `muc3_task_type` varchar(20) DEFAULT 'khong',
  `muc4_task_type` varchar(20) DEFAULT 'khong',
  `muc5_task_type` varchar(20) DEFAULT 'khong',
  `muc6_task_type` varchar(20) DEFAULT 'khong'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `coi_thi`
--

INSERT INTO `coi_thi` (`employeeID`, `result_year`, `muc1_so_cau`, `muc1_gio_quy_doi`, `muc2_so_cau`, `muc2_gio_quy_doi`, `muc3_so_cau`, `muc3_gio_quy_doi`, `muc4_so_cau`, `muc4_gio_quy_doi`, `muc5_so_cau`, `muc5_gio_quy_doi`, `muc6_so_cau`, `muc6_gio_quy_doi`, `muc7_so_cau`, `muc7_gio_quy_doi`, `muc8_so_cau`, `muc8_gio_quy_doi`, `muc9_so_cau`, `muc9_gio_quy_doi`, `muc10_so_cau`, `muc10_gio_quy_doi`, `muc11_so_cau`, `muc11_gio_quy_doi`, `tong_gio_quy_doi`, `ngay_cap_nhat`, `muc1_task_type`, `muc2_task_type`, `muc3_task_type`, `muc4_task_type`, `muc5_task_type`, `muc6_task_type`) VALUES
(2, 2024, 5, 5, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 5, '2025-04-26 11:51:09', 'ra_de', '', '', '', '', ''),
(2, 2025, 6, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 3, '2025-04-26 11:55:14', 'phan_bien', '', '', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `create_schedules`
--

CREATE TABLE `create_schedules` (
  `id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `weekday` varchar(10) NOT NULL,
  `sessions` varchar(255) NOT NULL,
  `room` varchar(100) NOT NULL,
  `employeeID` int(11) NOT NULL,
  `end_date` date DEFAULT NULL,
  `total_sessions` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dien_tap`
--

CREATE TABLE `dien_tap` (
  `id` int(11) NOT NULL,
  `employeeID` int(11) NOT NULL,
  `result_year` int(4) NOT NULL,
  `so_ngay` float NOT NULL DEFAULT 0,
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dien_tap`
--

INSERT INTO `dien_tap` (`id`, `employeeID`, `result_year`, `so_ngay`, `ngay_cap_nhat`) VALUES
(4, 2, 2025, 10, '2025-04-26 12:08:28'),
(5, 2, 2024, 4, '2025-04-26 12:08:46');

-- --------------------------------------------------------

--
-- Table structure for table `employee`
--

CREATE TABLE `employee` (
  `employeeID` int(11) NOT NULL,
  `fullName` varchar(100) NOT NULL,
  `birth` date DEFAULT NULL,
  `gender` enum('Nam','Nữ') NOT NULL,
  `phone` varchar(15) NOT NULL,
  `email` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `major` varchar(100) NOT NULL,
  `hireDate` date DEFAULT NULL,
  `role` enum('Quản trị viên','Ban giám hiệu','Giảng viên','Chuyên viên') NOT NULL,
  `userName` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `academicTitle` enum('Giảng viên','Giảng viên (tập sự)','Trợ giảng','Trợ giảng (tập sự)','') DEFAULT '',
  `leadershipPosition` varchar(255) DEFAULT '',
  `faculty` enum('Đất đai','Công nghệ thông tin','Kinh tế','Địa Chất','Môi Trường','Khí tượng văn học','') DEFAULT '',
  `image` varchar(255) DEFAULT '../../assets/images/avatar-default.png',
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `teacherID` varchar(20) DEFAULT NULL,
  `rankTeacher` enum('Hạng I','Hạng II','Hạng III','') DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee`
--

INSERT INTO `employee` (`employeeID`, `fullName`, `birth`, `gender`, `phone`, `email`, `address`, `major`, `hireDate`, `role`, `userName`, `password`, `academicTitle`, `leadershipPosition`, `faculty`, `image`, `note`, `created_at`, `teacherID`, `rankTeacher`) VALUES
(1, 'Nguyễn Tiến Toán', '2004-02-28', 'Nam', '0352135115', 'nguyentientoan28022004@gmail.com', 'từ sơn - bắc ninh', 'Công nghệ thông tin', '2025-04-24', 'Giảng viên', 'toan', '$2y$10$s3GH4u4fQTubqxOX332MjuwbMb.NUQyBjEK7OEHLutd0YdvAjTHwW', 'Giảng viên', 'Chủ tịch Hội đồng trường, Hiệu trưởng', 'Công nghệ thông tin', '../../assets/uploads/1.jpg', '', '2025-04-24 12:10:11', '1', 'Hạng I'),
(2, 'Phạm Thị Thanh Thủy', '1998-12-19', 'Nữ', '0352135114', '21@gmail.com', '12312421', 'Công nghệ thông tin', '2025-04-24', 'Giảng viên', 'thuy', '$2y$10$TMfn2cgWunnJ3KPYKOc4XO1eF74QfBsKsIIcpsfdNmI9tQkm2oeo6', 'Giảng viên', 'Giảng viên đang là quân nhân dự bị, tự vệ được triệu tập huấn luyện, diễn tập', 'Công nghệ thông tin', '../../assets/uploads/2.jpg', '', '2025-04-24 12:11:28', '13.025.GV', ''),
(3, 'Nguyễn Văn A', '1980-05-01', 'Nam', '0901000001', 'a1@gmail.com', 'Hà Nội', 'Công nghệ thông tin', '2010-09-01', 'Giảng viên', '1', '$2y$10$8OK4QGfdyOUGdTjpPfDxq.hIudbHcBZhl2c00Ib4B2yoaFM0HsYoy', 'Giảng viên', '', 'Công nghệ thông tin', '', '', '2025-04-29 11:24:29', 'GV001', 'Hạng I'),
(4, 'Trần Thị B', '1985-07-12', 'Nữ', '0901000002', 'b2@gmail.com', 'Hà Nội', 'Kinh tế', '2011-08-15', 'Giảng viên', '2', '2', 'Giảng viên (tập sự)', '', 'Kinh tế', '', '', '2025-04-29 11:24:29', 'GV002', 'Hạng II'),
(5, 'Lê Văn C', '1982-03-21', 'Nam', '0901000003', 'c3@gmail.com', 'HCM', 'Công nghệ thông tin', '2009-07-20', 'Giảng viên', '3', '3', 'Trợ giảng', '', 'Công nghệ thông tin', '', '', '2025-04-29 11:24:29', 'GV003', 'Hạng III'),
(6, 'Phạm Thị D', '1990-11-05', 'Nữ', '0901000004', 'd4@gmail.com', 'Đà Nẵng', 'Kinh tế', '2013-01-10', 'Giảng viên', '4', '4', 'Giảng viên', '', 'Kinh tế', '', '', '2025-04-29 11:24:29', 'GV004', 'Hạng I'),
(7, 'Hoàng Văn E', '1987-06-30', 'Nam', '0901000005', 'e5@gmail.com', 'Hà Nội', 'Công nghệ thông tin', '2012-03-25', 'Giảng viên', '5', '5', 'Trợ giảng', '', 'Công nghệ thông tin', '', '', '2025-04-29 11:24:29', 'GV005', 'Hạng II'),
(8, 'Đặng Thị F', '1992-09-14', 'Nữ', '0901000006', 'f6@gmail.com', 'HCM', 'Kinh tế', '2015-07-22', 'Giảng viên', '6', '6', 'Giảng viên (tập sự)', '', 'Kinh tế', '', '', '2025-04-29 11:24:29', 'GV006', 'Hạng III'),
(9, 'Ngô Văn G', '1989-04-18', 'Nam', '0901000007', 'g7@gmail.com', 'Đà Nẵng', 'Công nghệ thông tin', '2011-09-30', 'Giảng viên', '7', '7', 'Giảng viên', '', 'Công nghệ thông tin', '', '', '2025-04-29 11:24:29', 'GV007', 'Hạng I'),
(10, 'Vũ Thị H', '1991-02-26', 'Nữ', '0901000008', 'h8@gmail.com', 'Hà Nội', 'Kinh tế', '2014-05-16', 'Giảng viên', '8', '8', 'Giảng viên (tập sự)', '', 'Kinh tế', '', '', '2025-04-29 11:24:29', 'GV008', 'Hạng II'),
(11, 'Bùi Văn I', '1983-12-09', 'Nam', '0901000009', 'i9@gmail.com', 'HCM', 'Công nghệ thông tin', '2008-11-12', 'Giảng viên', '9', '9', 'Trợ giảng', '', 'Công nghệ thông tin', '', '', '2025-04-29 11:24:29', 'GV009', 'Hạng III'),
(12, 'Phan Thị K', '1993-01-20', 'Nữ', '0901000010', 'k10@gmail.com', 'Hà Nội', 'Kinh tế', '2016-04-18', 'Giảng viên', '10', '10', 'Giảng viên', '', 'Kinh tế', '', '', '2025-04-29 11:24:29', 'GV010', 'Hạng I'),
(13, 'Nguyễn Văn L', '1988-08-13', 'Nam', '0901000011', 'l11@gmail.com', 'Đà Nẵng', 'Công nghệ thông tin', '2010-10-05', 'Giảng viên', '11', '11', 'Giảng viên', '', 'Công nghệ thông tin', '', '', '2025-04-29 11:24:29', 'GV011', 'Hạng II'),
(14, 'Trần Thị M', '1995-03-22', 'Nữ', '0901000012', 'm12@gmail.com', 'HCM', 'Kinh tế', '2017-06-09', 'Giảng viên', '12', '12', 'Trợ giảng', '', 'Kinh tế', '', '', '2025-04-29 11:24:29', 'GV012', 'Hạng III'),
(15, 'Lê Văn N', '1986-09-27', 'Nam', '0901000013', 'n13@gmail.com', 'Hà Nội', 'Công nghệ thông tin', '2011-08-14', 'Giảng viên', '13', '13', 'Giảng viên', '', 'Công nghệ thông tin', '', '', '2025-04-29 11:24:29', 'GV013', 'Hạng I'),
(16, 'Phạm Thị O', '1994-12-05', 'Nữ', '0901000014', 'o14@gmail.com', 'Đà Nẵng', 'Kinh tế', '2016-11-11', 'Giảng viên', '14', '14', 'Giảng viên (tập sự)', '', 'Kinh tế', '', '', '2025-04-29 11:24:29', 'GV014', 'Hạng II'),
(17, 'Hoàng Văn P', '1981-04-02', 'Nam', '0901000015', 'p15@gmail.com', 'HCM', 'Công nghệ thông tin', '2009-05-20', 'Giảng viên', '15', '15', 'Giảng viên', '', 'Công nghệ thông tin', '', '', '2025-04-29 11:24:29', 'GV015', 'Hạng III'),
(18, 'Đặng Thị Q', '1990-10-17', 'Nữ', '0901000016', 'q16@gmail.com', 'Hà Nội', 'Kinh tế', '2013-12-22', 'Giảng viên', '16', '16', 'Trợ giảng', '', 'Kinh tế', '', '', '2025-04-29 11:24:29', 'GV016', 'Hạng I'),
(19, 'Ngô Văn R', '1984-05-29', 'Nam', '0901000017', 'r17@gmail.com', 'Đà Nẵng', 'Công nghệ thông tin', '2008-07-18', 'Giảng viên', '17', '17', 'Giảng viên', '', 'Công nghệ thông tin', '', '', '2025-04-29 11:24:29', 'GV017', 'Hạng II'),
(20, 'Vũ Thị S', '1996-02-03', 'Nữ', '0901000018', 's18@gmail.com', 'HCM', 'Kinh tế', '2018-09-14', 'Giảng viên', '18', '18', 'Giảng viên (tập sự)', '', 'Kinh tế', '', '', '2025-04-29 11:24:29', 'GV018', 'Hạng III'),
(21, 'Bùi Văn T', '1982-11-23', 'Nam', '0901000019', 't19@gmail.com', 'Hà Nội', 'Công nghệ thông tin', '2010-02-17', 'Giảng viên', '19', '19', 'Giảng viên', '', 'Công nghệ thông tin', '', '', '2025-04-29 11:24:29', 'GV019', 'Hạng I'),
(22, 'Phan Thị U', '1991-07-09', 'Nữ', '0901000020', 'u20@gmail.com', 'Đà Nẵng', 'Kinh tế', '2014-10-01', 'Giảng viên', '20', '20', 'Giảng viên', '', 'Kinh tế', '', '', '2025-04-29 11:24:29', 'GV020', 'Hạng II');

-- --------------------------------------------------------

--
-- Table structure for table `giangday`
--

CREATE TABLE `giangday` (
  `employeeID` int(11) NOT NULL,
  `result_year` int(11) NOT NULL,
  `muc1_tong_tiet` float DEFAULT 0,
  `muc1_sv_tren_40` tinyint(1) DEFAULT 0,
  `muc1_tong_sv` int(11) DEFAULT 0,
  `muc1_gio_quy_doi` float DEFAULT 0,
  `muc2_tong_tiet` float DEFAULT 0,
  `muc2_gio_quy_doi` float DEFAULT 0,
  `muc3_tong_tiet` float DEFAULT 0,
  `muc3_gio_quy_doi` float DEFAULT 0,
  `muc4_tong_tiet` float DEFAULT 0,
  `muc4_gio_quy_doi` float DEFAULT 0,
  `muc5_tong_tiet` float DEFAULT 0,
  `muc5_sv_tren_40` tinyint(1) DEFAULT 0,
  `muc5_tong_sv` int(11) DEFAULT 0,
  `muc5_gio_quy_doi` float DEFAULT 0,
  `muc6_tong_tiet` float DEFAULT 0,
  `muc6_sv_tren_30` tinyint(1) DEFAULT 0,
  `muc6_tong_sv` int(11) DEFAULT 0,
  `muc6_gio_quy_doi` float DEFAULT 0,
  `muc7_tong_ngay` float DEFAULT 0,
  `muc7_sv_tren_25` tinyint(1) DEFAULT 0,
  `muc7_tong_sv` int(11) DEFAULT 0,
  `muc7_gio_quy_doi` float DEFAULT 0,
  `muc8_tong_tin_chi` float DEFAULT 0,
  `muc8_gio_quy_doi` float DEFAULT 0,
  `muc9_tong_ngay` float DEFAULT 0,
  `muc9_sv_tren_40` tinyint(1) DEFAULT 0,
  `muc9_tong_sv` int(11) DEFAULT 0,
  `muc9_them_gv` tinyint(1) DEFAULT 0,
  `muc9_gio_quy_doi` float DEFAULT 0,
  `tong_gio_quy_doi` float DEFAULT 0,
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `giangday`
--

INSERT INTO `giangday` (`employeeID`, `result_year`, `muc1_tong_tiet`, `muc1_sv_tren_40`, `muc1_tong_sv`, `muc1_gio_quy_doi`, `muc2_tong_tiet`, `muc2_gio_quy_doi`, `muc3_tong_tiet`, `muc3_gio_quy_doi`, `muc4_tong_tiet`, `muc4_gio_quy_doi`, `muc5_tong_tiet`, `muc5_sv_tren_40`, `muc5_tong_sv`, `muc5_gio_quy_doi`, `muc6_tong_tiet`, `muc6_sv_tren_30`, `muc6_tong_sv`, `muc6_gio_quy_doi`, `muc7_tong_ngay`, `muc7_sv_tren_25`, `muc7_tong_sv`, `muc7_gio_quy_doi`, `muc8_tong_tin_chi`, `muc8_gio_quy_doi`, `muc9_tong_ngay`, `muc9_sv_tren_40`, `muc9_tong_sv`, `muc9_them_gv`, `muc9_gio_quy_doi`, `tong_gio_quy_doi`, `ngay_cap_nhat`) VALUES
(1, 2025, 0, 0, 0, 0, 15, 29.25, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 29.25, '2025-04-26 15:50:04'),
(2, 2024, 30, 0, 0, 30, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 30, '2025-04-26 11:18:13'),
(2, 2025, 360, 0, 0, 360, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 360, 0, 0, 198, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 558, '2025-04-25 15:07:30'),
(3, 2025, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2025-04-29 11:28:17');

-- --------------------------------------------------------

--
-- Table structure for table `hoi_dong`
--

CREATE TABLE `hoi_dong` (
  `id` int(11) NOT NULL,
  `employeeID` varchar(50) NOT NULL,
  `result_year` int(4) NOT NULL,
  `so_sach` float DEFAULT 0,
  `vai_tro` varchar(50) DEFAULT NULL,
  `so_gio_ngoai_gio` float DEFAULT 0,
  `so_gio_ngoai_truong` float DEFAULT 0,
  `khoang_cach` varchar(20) DEFAULT NULL,
  `tong_gio_quy_doi` float DEFAULT 0,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp(),
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hoi_dong`
--

INSERT INTO `hoi_dong` (`id`, `employeeID`, `result_year`, `so_sach`, `vai_tro`, `so_gio_ngoai_gio`, `so_gio_ngoai_truong`, `khoang_cach`, `tong_gio_quy_doi`, `ngay_tao`, `ngay_cap_nhat`) VALUES
(8, '2', 2024, 2, 'Phản biện', 5, 2, 'duoi_200km', 20.1, '2025-04-25 03:25:56', '2025-04-26 12:08:46'),
(9, '2', 2025, 5, 'Phản biện', 0, 0, '', 27.5, '2025-04-25 15:01:33', '2025-04-26 12:08:28');

-- --------------------------------------------------------

--
-- Table structure for table `huongdansvnckh`
--

CREATE TABLE `huongdansvnckh` (
  `ID` int(11) NOT NULL,
  `NoiDung` text NOT NULL,
  `KhoiLuongGio` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `huongdansvnckh`
--

INSERT INTO `huongdansvnckh` (`ID`, `NoiDung`, `KhoiLuongGio`) VALUES
(1, 'Hướng dẫn sinh viên thực hiện đề tài NCKH được nghiệm thu', '220'),
(2, 'Hướng dẫn SV NCKH có đề tài đạt giải thưởng cấp quốc gia, quốc tế và tương đương - Giải nhất', '590'),
(3, 'Hướng dẫn SV NCKH có đề tài đạt giải thưởng cấp quốc gia, quốc tế và tương đương - Giải nhì', '590'),
(4, 'Hướng dẫn SV NCKH có đề tài đạt giải thưởng cấp quốc gia, quốc tế và tương đương - Giải ba', '590'),
(5, 'Hướng dẫn SV NCKH có đề tài đạt giải thưởng cấp quốc gia, quốc tế và tương đương - Giải khuyến khích', '590'),
(6, 'Hướng dẫn SV NCKH có đề tài đạt giải thưởng cấp Trường - Giải nhất', '415'),
(7, 'Hướng dẫn SV NCKH có đề tài đạt giải thưởng cấp Trường - Giải nhì', '300'),
(8, 'Hướng dẫn SV NCKH có đề tài đạt giải thưởng cấp Trường - Giải ba', '240'),
(9, 'Hướng dẫn SV NCKH có đề tài đạt giải thưởng cấp Trường - Giải khuyến khích', '120');

-- --------------------------------------------------------

--
-- Table structure for table `huongdansv_history`
--

CREATE TABLE `huongdansv_history` (
  `id` int(11) NOT NULL,
  `employeeID` int(11) NOT NULL,
  `result_year` int(11) NOT NULL,
  `nckh_id` int(11) NOT NULL,
  `noi_dung` text NOT NULL,
  `ten_san_pham` varchar(255) DEFAULT '',
  `so_luong` float DEFAULT 0,
  `vai_tro` varchar(100) DEFAULT '',
  `so_tac_gia` int(11) DEFAULT 1,
  `phan_tram_dong_gop` float DEFAULT 0,
  `gio_quy_doi` float DEFAULT 0,
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `note` int(11) NOT NULL,
  `diem_tap_chi` float DEFAULT NULL,
  `ma_so_xuat_ban` varchar(50) DEFAULT NULL,
  `ten_don_vi_xuat_ban` varchar(100) DEFAULT NULL,
  `ten_hoi_thao` varchar(100) DEFAULT NULL,
  `thanh_vien_chu_nhom` varchar(255) DEFAULT NULL,
  `nation_point` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `huongdansv_history`
--
DELIMITER $$
CREATE TRIGGER `after_huongdansv_delete` AFTER DELETE ON `huongdansv_history` FOR EACH ROW BEGIN
    CALL UpdateTongHopNCKH(OLD.employeeID, OLD.result_year);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_huongdansv_insert` AFTER INSERT ON `huongdansv_history` FOR EACH ROW BEGIN
    CALL UpdateTongHopNCKH(NEW.employeeID, NEW.result_year);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_huongdansv_update` AFTER UPDATE ON `huongdansv_history` FOR EACH ROW BEGIN
    CALL UpdateTongHopNCKH(NEW.employeeID, NEW.result_year);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `nckhcc_history`
--

CREATE TABLE `nckhcc_history` (
  `id` int(11) NOT NULL,
  `employeeID` int(11) NOT NULL,
  `result_year` int(11) NOT NULL,
  `nckh_id` int(11) NOT NULL,
  `noi_dung` text NOT NULL,
  `ten_san_pham` varchar(255) DEFAULT '',
  `so_luong` float DEFAULT 0,
  `vai_tro` varchar(100) DEFAULT NULL,
  `so_tac_gia` int(11) DEFAULT 1,
  `phan_tram_dong_gop` float DEFAULT 0,
  `gio_quy_doi` float DEFAULT 0,
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `note` int(11) NOT NULL,
  `diem_tap_chi` float DEFAULT NULL,
  `ma_so_xuat_ban` varchar(50) DEFAULT NULL,
  `ten_don_vi_xuat_ban` varchar(100) DEFAULT NULL,
  `ten_hoi_thao` varchar(100) DEFAULT NULL,
  `thanh_vien_chu_nhom` varchar(255) DEFAULT NULL,
  `nation_point` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nckhcc_history`
--

INSERT INTO `nckhcc_history` (`id`, `employeeID`, `result_year`, `nckh_id`, `noi_dung`, `ten_san_pham`, `so_luong`, `vai_tro`, `so_tac_gia`, `phan_tram_dong_gop`, `gio_quy_doi`, `ngay_cap_nhat`, `note`, `diem_tap_chi`, `ma_so_xuat_ban`, `ten_don_vi_xuat_ban`, `ten_hoi_thao`, `thanh_vien_chu_nhom`, `nation_point`) VALUES
(121, 1, 2025, 1, 'Đề tài KHCN cấp cơ sở không được bố trí kinh phí từ NSNN, được nghiệm thu và có bài báo đăng trên tạp chí chuyên ngành với điểm HĐ chức danh GSNN &lt; 0,5 hoặc Kỷ yếu hội thảo khoa học quốc gia có chỉ số ISBN', '1', 1, 'Tác giả', 2, 50, 295, '2025-05-06 11:55:01', 0, 1, '1', '1', '1', '13.025.GV - Phạm Thị Thanh Thủy', 'Q1'),
(122, 2, 2025, 1, 'Đề tài KHCN cấp cơ sở không được bố trí kinh phí từ NSNN, được nghiệm thu và có bài báo đăng trên tạp chí chuyên ngành với điểm HĐ chức danh GSNN &lt; 0,5 hoặc Kỷ yếu hội thảo khoa học quốc gia có chỉ số ISBN', '1', 1, 'Tác giả', 2, 50, 295, '2025-05-06 11:55:01', 0, 1, '1', '1', '1', '13.025.GV - Phạm Thị Thanh Thủy', 'Q1'),
(137, 2, 2024, 1, 'Đề tài KHCN cấp cơ sở không được bố trí kinh phí từ NSNN, được nghiệm thu và có bài báo đăng trên tạp chí chuyên ngành với điểm HĐ chức danh GSNN &lt; 0,5 hoặc Kỷ yếu hội thảo khoa học quốc gia có chỉ số ISBN', '1', 1, 'Chủ nhiệm', 1, 0, 590, '2025-05-14 05:55:46', 0, NULL, NULL, NULL, NULL, NULL, '');

--
-- Triggers `nckhcc_history`
--
DELIMITER $$
CREATE TRIGGER `after_nckhcc_delete` AFTER DELETE ON `nckhcc_history` FOR EACH ROW BEGIN
    CALL UpdateTongHopNCKH(OLD.employeeID, OLD.result_year);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_nckhcc_insert` AFTER INSERT ON `nckhcc_history` FOR EACH ROW BEGIN
    CALL UpdateTongHopNCKH(NEW.employeeID, NEW.result_year);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_nckhcc_update` AFTER UPDATE ON `nckhcc_history` FOR EACH ROW BEGIN
    CALL UpdateTongHopNCKH(NEW.employeeID, NEW.result_year);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `nghiencuukhoahoccaccap`
--

CREATE TABLE `nghiencuukhoahoccaccap` (
  `ID` int(11) NOT NULL,
  `NoiDung` text NOT NULL,
  `KhoiLuongGio` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nghiencuukhoahoccaccap`
--

INSERT INTO `nghiencuukhoahoccaccap` (`ID`, `NoiDung`, `KhoiLuongGio`) VALUES
(1, 'Đề tài KHCN cấp cơ sở không được bố trí kinh phí từ NSNN, được nghiệm thu và có bài báo đăng trên tạp chí chuyên ngành với điểm HĐ chức danh GSNN < 0,5 hoặc Kỷ yếu hội thảo khoa học quốc gia có chỉ số ISBN', '590'),
(2, 'Đề tài KHCN cấp cơ sở không được bố trí kinh phí từ NSNN, được nghiệm thu và có bài báo đăng trên tạp chí chuyên ngành trong nước với điểm HĐ chức danh GSNN là 0,5 hoặc Kỷ yếu hội thảo quốc tế có mã số ISBN', '590'),
(3, 'Đề tài KHCN cấp cơ sở không được bố trí kinh phí từ NSNN, được nghiệm thu và có bài báo đăng trên tạp chí chuyên ngành trong nước với điểm HĐ chức danh GSNN là 0,75 hoặc Kỷ yếu hội thảo quốc tế có điểm HĐ chức danh GSNN', '590'),
(4, 'Đề tài KHCN cấp cơ sở không được bố trí kinh phí từ NSNN, được nghiệm thu và có bài báo đăng trên tạp chí chuyên ngành trong nước với điểm HĐ chức danh GSNN > 0,75 hoặc bài báo quốc tế có chỉ số ISSN', '590'),
(5, 'Công trình được giải thưởng tại các cuộc thi KH&CN trong nước', '590'),
(6, 'Công trình được giải thưởng tại các cuộc thi KH&CN quốc tế', '1180'),
(7, 'Tham gia xây dựng dự án của Trường hợp tác với đối tác nước ngoài được phê duyệt', '700'),
(8, 'Tham gia thực hiện dự án nghiên cứu với đối tác nước ngoài mà không được hưởng phụ cấp lương từ dự án (dự án không quá 03 năm)', '295'),
(9, 'Các nhóm nghiên cứu mạnh/nghiên cứu chuyên sâu nếu có chương trình, đề tài, dự án được cấp có thẩm quyền phê duyệt, kinh phí không thuộc NSNN', '590');

-- --------------------------------------------------------

--
-- Table structure for table `nv3_hours`
--

CREATE TABLE `nv3_hours` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `total_nv3_hours` float DEFAULT 180
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ra_de_thi`
--

CREATE TABLE `ra_de_thi` (
  `employeeID` int(11) NOT NULL,
  `result_year` int(11) NOT NULL,
  `muc1_1_so_cau` float DEFAULT 0,
  `muc1_1_gio_quy_doi` float DEFAULT 0,
  `muc1_2_so_cau` float DEFAULT 0,
  `muc1_2_gio_quy_doi` float DEFAULT 0,
  `muc1_3_so_cau` float DEFAULT 0,
  `muc1_3_gio_quy_doi` float DEFAULT 0,
  `muc1_4_so_cau` float DEFAULT 0,
  `muc1_4_gio_quy_doi` float DEFAULT 0,
  `muc2_1_so_cau` float DEFAULT 0,
  `muc2_1_gio_quy_doi` float DEFAULT 0,
  `muc2_2_so_cau` float DEFAULT 0,
  `muc2_2_gio_quy_doi` float DEFAULT 0,
  `muc2_3_so_cau` float DEFAULT 0,
  `muc2_3_gio_quy_doi` float DEFAULT 0,
  `muc3_1_so_cau` float DEFAULT 0,
  `muc3_1_gio_quy_doi` float DEFAULT 0,
  `muc3_2_so_cau` float DEFAULT 0,
  `muc3_2_gio_quy_doi` float DEFAULT 0,
  `muc3_3_so_cau` float DEFAULT 0,
  `muc3_3_gio_quy_doi` float DEFAULT 0,
  `tong_gio_quy_doi` float DEFAULT 0,
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ra_de_thi`
--

INSERT INTO `ra_de_thi` (`employeeID`, `result_year`, `muc1_1_so_cau`, `muc1_1_gio_quy_doi`, `muc1_2_so_cau`, `muc1_2_gio_quy_doi`, `muc1_3_so_cau`, `muc1_3_gio_quy_doi`, `muc1_4_so_cau`, `muc1_4_gio_quy_doi`, `muc2_1_so_cau`, `muc2_1_gio_quy_doi`, `muc2_2_so_cau`, `muc2_2_gio_quy_doi`, `muc2_3_so_cau`, `muc2_3_gio_quy_doi`, `muc3_1_so_cau`, `muc3_1_gio_quy_doi`, `muc3_2_so_cau`, `muc3_2_gio_quy_doi`, `muc3_3_so_cau`, `muc3_3_gio_quy_doi`, `tong_gio_quy_doi`, `ngay_cap_nhat`) VALUES
(2, 2024, 410, 410, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 410, '2025-04-26 11:20:08'),
(2, 2025, 110, 110, 0, 0, 110, 11, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 121, '2025-04-26 11:49:23');

-- --------------------------------------------------------

--
-- Table structure for table `schedule`
--

CREATE TABLE `schedule` (
  `id` int(11) NOT NULL,
  `class` varchar(50) DEFAULT NULL,
  `subject_name` varchar(255) DEFAULT NULL,
  `teacher_name` varchar(255) DEFAULT NULL,
  `LT` int(10) DEFAULT NULL,
  `TH` int(10) DEFAULT NULL,
  `Number_of_weeks` int(10) DEFAULT NULL,
  `credits` int(11) DEFAULT NULL,
  `study_type` varchar(50) DEFAULT NULL,
  `time` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `total_period` double DEFAULT NULL,
  `employeeID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule`
--

INSERT INTO `schedule` (`id`, `class`, `subject_name`, `teacher_name`, `LT`, `TH`, `Number_of_weeks`, `credits`, `study_type`, `time`, `note`, `total_period`, `employeeID`) VALUES
(1, 'ĐH14QĐ1', 'Tin học đại cương', 'PHẠM THỊ THANH THỦY (13.025.GV)', 15, 30, 8, 2, 'Trực tiếp', '03/03 - 27/04/25', '', 360, 2),
(2, 'ĐH14QĐ1', 'Tin học đại cương', 'PHẠM THỊ THANH THỦY (13.025.GV)', 15, 30, 6, 2, 'Trực tiếp', '05/05 - 15/06/25', '', 360, 2),
(3, 'ĐH14QĐ1', 'Tin học đại cương', 'PHẠM THỊ THANH THỦY (13.025.GV)', 15, 30, 1, 2, 'Trực tiếp', '16/06 - 22/06/25', '', 360, 2),
(4, 'ĐH14QĐ2', 'Tin học đại cương', 'PHẠM THỊ THANH THỦY (13.025.GV)', 15, 30, 8, 2, 'Trực tiếp', '03/03 - 27/04/25', '', 360, 2),
(5, 'ĐH14QĐ2', 'Tin học đại cương', 'PHẠM THỊ THANH THỦY (13.025.GV)', 15, 30, 6, 2, 'Trực tiếp', '05/05 - 15/06/25', '', 360, 2),
(6, 'ĐH14QĐ2', 'Tin học đại cương', 'PHẠM THỊ THANH THỦY (13.025.GV)', 15, 30, 1, 2, 'Trực tiếp', '16/06 - 22/06/25', '', 360, 2),
(7, 'ĐH14QTKD4', 'Tin học đại cương', 'PHẠM THỊ THANH THỦY (13.025.GV)', 15, 30, 8, 2, 'Trực tiếp', '03/03 - 27/04/25', '', 360, 2),
(8, 'ĐH14QTKD4', 'Tin học đại cương', 'PHẠM THỊ THANH THỦY (13.025.GV)', 15, 30, 7, 2, 'Trực tiếp', '05/05 - 22/06/25', '', 360, 2);

-- --------------------------------------------------------

--
-- Table structure for table `task_registrations`
--

CREATE TABLE `task_registrations` (
  `registration_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `task_id` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `total_hours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `real_hours` decimal(10,2) DEFAULT NULL,
  `section` varchar(10) NOT NULL DEFAULT 'b1',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `year` int(11) NOT NULL DEFAULT 2025,
  `evidence_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_registrations`
--

INSERT INTO `task_registrations` (`registration_id`, `employee_id`, `task_id`, `quantity`, `total_hours`, `real_hours`, `section`, `created_at`, `year`, `evidence_path`) VALUES
(87, 2, '3_chu_tich_thu_ky_hoi_dong_dam_bao_chat_luong', 10, 180.00, NULL, 'a', '2025-04-25 03:28:16', 2024, NULL),
(88, 1, '1_chu_tich_thu_ky_hoi_dong_khoa_hoc_dao_tao', 15, 405.00, NULL, 'a', '2025-04-26 15:47:46', 2024, '1_1_chu_tich_thu_ky_hoi_dong_khoa_hoc_dao_tao_2024_1745682466.docx'),
(89, 1, '2.1_dang_tin_khoa', 15, 75.00, NULL, 'b2_3', '2025-04-26 15:49:01', 2024, NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `tonghop_nckh`
-- (See below for the actual view)
--
CREATE TABLE `tonghop_nckh` (
`employeeID` int(11)
,`result_year` int(11)
,`tong_gio_nckh` float
,`dinh_muc_toi_thieu` float
,`trang_thai_hoan_thanh` enum('Hoàn thành','Chưa hoàn thành')
,`ngay_cap_nhat` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `tong_hop_nckh`
--

CREATE TABLE `tong_hop_nckh` (
  `employeeID` int(11) NOT NULL,
  `result_year` int(11) NOT NULL,
  `tong_gio_nckh` float DEFAULT 0,
  `dinh_muc_toi_thieu` float DEFAULT 0,
  `trang_thai_hoan_thanh` enum('Hoàn thành','Chưa hoàn thành') DEFAULT 'Chưa hoàn thành',
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tong_hop_nckh`
--

INSERT INTO `tong_hop_nckh` (`employeeID`, `result_year`, `tong_gio_nckh`, `dinh_muc_toi_thieu`, `trang_thai_hoan_thanh`, `ngay_cap_nhat`) VALUES
(1, 2025, 295, 0, 'Chưa hoàn thành', '2025-05-06 11:55:01'),
(2, 2024, 590, 0, 'Chưa hoàn thành', '2025-05-14 05:55:46'),
(2, 2025, 1180, 0, 'Chưa hoàn thành', '2025-05-06 11:56:51');

-- --------------------------------------------------------

--
-- Table structure for table `total_hours`
--

CREATE TABLE `total_hours` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `total_completed_hours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `year` int(11) NOT NULL DEFAULT 2025
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `total_hours`
--

INSERT INTO `total_hours` (`id`, `employee_id`, `total_completed_hours`, `updated_at`, `year`) VALUES
(11, 2, 180.00, '2025-04-25 03:28:16', 2024),
(12, 1, 480.00, '2025-04-26 15:49:01', 2024);

-- --------------------------------------------------------

--
-- Table structure for table `tot_nghiep`
--

CREATE TABLE `tot_nghiep` (
  `employeeID` int(11) NOT NULL,
  `result_year` int(11) NOT NULL,
  `muc1_so_de` float DEFAULT 0,
  `muc1_vai_tro` enum('Ra đề','Phản biện') DEFAULT 'Ra đề',
  `muc1_gio_quy_doi` float DEFAULT 0,
  `muc2_so_ca` float DEFAULT 0,
  `muc2_gio_quy_doi` float DEFAULT 0,
  `muc3_so_bai` float DEFAULT 0,
  `muc3_gio_quy_doi` float DEFAULT 0,
  `muc4_so_lop` float DEFAULT 0,
  `muc4_so_sv` int(11) DEFAULT 0,
  `muc4_gio_quy_doi` float DEFAULT 0,
  `muc5_so_sv` float DEFAULT 0,
  `muc5_gio_quy_doi` float DEFAULT 0,
  `muc6_so_luan_van` float DEFAULT 0,
  `muc6_vai_tro` enum('TSKH_GVCC_PGS_GS','TS_GVC','') DEFAULT '',
  `muc6_gio_quy_doi` float DEFAULT 0,
  `muc7_so_luan_van` float DEFAULT 0,
  `muc7_so_gv` tinyint(4) DEFAULT 1,
  `muc7_vai_tro` enum('GV1','GV2','') DEFAULT '',
  `muc7_gio_quy_doi` float DEFAULT 0,
  `muc8_so_khoa_luan` float DEFAULT 0,
  `muc8_vai_tro` enum('Chủ tịch','Phản biện','Thư ký','') DEFAULT '',
  `muc8_gio_quy_doi` float DEFAULT 0,
  `muc9_so_de` float DEFAULT 0,
  `muc9_vai_tro` enum('Chủ tịch','Phản biện','Thư ký','Ủy viên','') DEFAULT '',
  `muc9_gio_quy_doi` float DEFAULT 0,
  `muc10_so_luan_van` float DEFAULT 0,
  `muc10_vai_tro` enum('Chủ tịch','Phản biện','Thư ký','Ủy viên','') DEFAULT '',
  `muc10_gio_quy_doi` float DEFAULT 0,
  `tong_gio_quy_doi` float DEFAULT 0,
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `muc4_sv_exceed` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tot_nghiep`
--

INSERT INTO `tot_nghiep` (`employeeID`, `result_year`, `muc1_so_de`, `muc1_vai_tro`, `muc1_gio_quy_doi`, `muc2_so_ca`, `muc2_gio_quy_doi`, `muc3_so_bai`, `muc3_gio_quy_doi`, `muc4_so_lop`, `muc4_so_sv`, `muc4_gio_quy_doi`, `muc5_so_sv`, `muc5_gio_quy_doi`, `muc6_so_luan_van`, `muc6_vai_tro`, `muc6_gio_quy_doi`, `muc7_so_luan_van`, `muc7_so_gv`, `muc7_vai_tro`, `muc7_gio_quy_doi`, `muc8_so_khoa_luan`, `muc8_vai_tro`, `muc8_gio_quy_doi`, `muc9_so_de`, `muc9_vai_tro`, `muc9_gio_quy_doi`, `muc10_so_luan_van`, `muc10_vai_tro`, `muc10_gio_quy_doi`, `tong_gio_quy_doi`, `ngay_cap_nhat`, `muc4_sv_exceed`) VALUES
(2, 2024, 5, 'Phản biện', 6.25, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, NULL, NULL, 0, 0, '', 0, 0, '', 0, 0, '', 0, 6.25, '2025-04-26 12:00:04', 0),
(2, 2025, 5, 'Ra đề', 12.5, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, NULL, NULL, 0, 0, '', 0, 0, '', 0, 0, '', 0, 12.5, '2025-04-26 11:59:44', 0);

-- --------------------------------------------------------

--
-- Table structure for table `vietbaibao`
--

CREATE TABLE `vietbaibao` (
  `ID` int(11) NOT NULL,
  `NoiDung` text NOT NULL,
  `KhoiLuongGio` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vietbaibao`
--

INSERT INTO `vietbaibao` (`ID`, `NoiDung`, `KhoiLuongGio`) VALUES
(1, 'Bài báo đăng trên tạp chí khoa học quốc tế trong danh mục ISI, SCI, SCIE, Scopus (Q1) hoặc kỷ yếu hội nghị quốc tế CORE A', '3175'),
(2, 'Bài báo đăng trên tạp chí khoa học quốc tế trong danh mục ISI, SCI, SCIE, Scopus (Q2) hoặc kỷ yếu hội nghị quốc tế CORE A', '2540'),
(3, 'Bài báo đăng trên tạp chí khoa học quốc tế trong danh mục ISI, SCI, SCIE, Scopus (Q3) hoặc kỷ yếu hội nghị quốc tế CORE A', '1905'),
(4, 'Bài báo đăng trên tạp chí khoa học quốc tế trong danh mục ISI, SCI, SCIE, Scopus (Q4) hoặc kỷ yếu hội nghị quốc tế CORE A', '1475'),
(5, 'Bài báo đăng trên tạp chí khoa học quốc tế trong danh mục ISI, Scopus, SCI, SCIE không đủ điều kiện của mục 8-11', '1180'),
(6, 'Bài báo (p&e) được công bố trên các tạp chí khoa học quốc tế có mã số ISSN', '885'),
(7, 'Bài báo được công bố trên các tạp chí khoa học trong nước có mã số ISSN - Loại nghiên cứu', '355'),
(8, 'Bài báo được công bố trên các tạp chí khoa học trong nước có mã số ISSN - Loại mục khác', '180'),
(9, 'Bài báo được công bố trên các tạp chí khoa học trong nước có mã số ISSN và điểm HĐ chức danh GSNN < 0,5 - Loại nghiên cứu', '415'),
(10, 'Bài báo được công bố trên các tạp chí khoa học trong nước có mã số ISSN và điểm HĐ chức danh GSNN < 0,5 - Loại mục khác', '180'),
(11, 'Bài báo được công bố trên các tạp chí khoa học trong nước có mã số ISSN và điểm HĐ chức danh GSNN là 0,5 - Loại nghiên cứu', '590'),
(12, 'Bài báo được công bố trên các tạp chí khoa học trong nước có mã số ISSN và điểm HĐ chức danh GSNN là 0,5 - Loại mục khác', '180'),
(13, 'Bài báo được công bố trên các tạp chí khoa học trong nước có mã số ISSN và điểm HĐ chức danh GSNN là 0,75 - Loại nghiên cứu', '885'),
(14, 'Bài báo được công bố trên các tạp chí khoa học trong nước có mã số ISSN và điểm HĐ chức danh GSNN là 0,75 - Loại mục khác', '180'),
(15, 'Bài báo được công bố trên các tạp chí khoa học trong nước có mã số ISSN và điểm HĐ chức danh GSNN ≥ 1,0 - Loại nghiên cứu', '1180'),
(16, 'Bài báo được công bố trên các tạp chí khoa học trong nước có mã số ISSN và điểm HĐ chức danh GSNN ≥ 1,0 - Loại mục khác', '180'),
(17, 'Bài báo công bố trên các tạp chí xuất bản bằng tiếng Anh trong nước có chỉ số ISSN và điểm HĐ chức danh GSNN - Loại nghiên cứu', '1180'),
(18, 'Bài báo công bố trên các tạp chí xuất bản bằng tiếng Anh trong nước có chỉ số ISSN và điểm HĐ chức danh GSNN - Loại mục khác', '180'),
(19, 'Bài báo đăng ở tạp chí Khoa học Tài nguyên và Môi trường của Trường - Tiếng Việt, loại nghiên cứu', '885'),
(20, 'Bài báo đăng ở tạp chí Khoa học Tài nguyên và Môi trường của Trường - Tiếng Việt, loại mục khác', '174'),
(21, 'Bài báo đăng ở tạp chí Khoa học Tài nguyên và Môi trường của Trường - Tiếng Anh, loại nghiên cứu', '1180'),
(22, 'Bài báo đăng ở tạp chí Khoa học Tài nguyên và Môi trường của Trường - Tiếng Anh, loại mục khác', '180'),
(23, 'Bài báo đăng ở các báo và tạp chí khác trong nước', '90'),
(24, 'Bài đăng kỷ yếu hội thảo khoa học quốc tế tổ chức trong nước và nước ngoài, có xuất bản ấn phẩm mã số ISBN', '1180'),
(25, 'Bài đăng trong tuyển tập các tóm tắt (abstract) hoặc bài đầy đủ, không xuất bản tại hội thảo quốc tế tổ chức trong nước và quốc tế', '413'),
(26, 'Báo cáo trình bày tại hội thảo khoa học quốc tế tổ chức trong nước và quốc tế - Có ISBN', '1770'),
(27, 'Báo cáo trình bày tại hội thảo khoa học quốc tế tổ chức trong nước và quốc tế - Không ISBN', '590'),
(28, 'Báo cáo trình bày tại hội thảo khoa học quốc tế tổ chức trong nước và quốc tế, được Ban tổ chức tặng thưởng vì những đóng góp khoa học - Có ISBN', '1770'),
(29, 'Báo cáo trình bày tại hội thảo khoa học quốc tế tổ chức trong nước và quốc tế, được Ban tổ chức tặng thưởng vì những đóng góp khoa học - Không ISBN', '590'),
(30, 'Bài đăng kỷ yếu hội thảo khoa học trong nước, xuất bản có chỉ số ISBN', '413'),
(31, 'Báo cáo trình bày tại hội nghị, hội thảo khoa học trong nước trên danh nghĩa Trường', '295'),
(32, 'Có sáng chế hoặc giải pháp hữu ích được cấp có thẩm quyền \nquyết định công nhận', '0'),
(33, 'Là chủ biên hoặc đồng chủ biên hoặc là tác giả duy nhất của 01 quyển sách chuyên khảo/tham khảo bằng tiếng nước ngoài do NXB quốc tế có uy tín phát hành', '0'),
(34, 'Là thành viên nhóm tác giả của 01 quyển sách chuyên khảo/tham khảo \nbằng tiếng nước ngoài do nhà xuất bản quốc tế có uy tín phát hành', '0');

-- --------------------------------------------------------

--
-- Table structure for table `vietsach`
--

CREATE TABLE `vietsach` (
  `ID` int(11) NOT NULL,
  `NoiDung` text NOT NULL,
  `KhoiLuongGio` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vietsach`
--

INSERT INTO `vietsach` (`ID`, `NoiDung`, `KhoiLuongGio`) VALUES
(1, 'Biên soạn giáo trình do Nhà trường đặt hàng không được NSNN cấp kinh phí, được nghiệm thu', '885'),
(2, 'Xuất bản giáo trình không theo đặt hàng của Nhà trường tại NXB có uy tín trong nước có mã số xuất bản ISBN', '885'),
(3, 'Viết sách chuyên khảo có mã số ISBN xuất bản ở trong nước', '1770'),
(4, 'Viết sách tham khảo có mã số ISBN xuất bản ở trong nước', '885'),
(5, 'Viết sách chuyên khảo hoặc tham khảo có mã số ISBN và xuất bản ở nước ngoài - Trường hợp là chủ biên', '3175'),
(6, 'Viết sách chuyên khảo hoặc tham khảo có mã số ISBN và xuất bản ở nước ngoài - Trường hợp không phải là chủ biên', '1180'),
(7, 'Tài liệu dịch chuyên môn có đăng ký và được cơ quan có thẩm quyền thẩm định', '2'),
(8, 'Biên tập ngôn ngữ tiếng Anh trên tạp chí Khoa học Tài nguyên và Môi trường của Trường (Chỉ áp dụng cho giảng viên tham gia thực hiện)', '10');

-- --------------------------------------------------------

--
-- Table structure for table `vietsach_history`
--

CREATE TABLE `vietsach_history` (
  `id` int(11) NOT NULL,
  `employeeID` int(11) NOT NULL,
  `result_year` int(11) NOT NULL,
  `nckh_id` int(11) NOT NULL,
  `noi_dung` text NOT NULL,
  `ten_san_pham` varchar(255) DEFAULT '',
  `so_luong` float DEFAULT 0,
  `vai_tro` varchar(100) DEFAULT '',
  `so_tac_gia` int(11) DEFAULT 1,
  `phan_tram_dong_gop` float DEFAULT 0,
  `gio_quy_doi` float DEFAULT 0,
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `note` int(11) NOT NULL,
  `diem_tap_chi` float DEFAULT NULL,
  `ma_so_xuat_ban` varchar(50) DEFAULT NULL,
  `ten_don_vi_xuat_ban` varchar(100) DEFAULT NULL,
  `ten_hoi_thao` varchar(100) DEFAULT NULL,
  `thanh_vien_chu_nhom` varchar(255) DEFAULT NULL,
  `nation_point` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vietsach_history`
--

INSERT INTO `vietsach_history` (`id`, `employeeID`, `result_year`, `nckh_id`, `noi_dung`, `ten_san_pham`, `so_luong`, `vai_tro`, `so_tac_gia`, `phan_tram_dong_gop`, `gio_quy_doi`, `ngay_cap_nhat`, `note`, `diem_tap_chi`, `ma_so_xuat_ban`, `ten_don_vi_xuat_ban`, `ten_hoi_thao`, `thanh_vien_chu_nhom`, `nation_point`) VALUES
(7, 1, 2024, 1, 'Biên soạn giáo trình do Nhà trường đặt hàng không được NSNN cấp kinh phí, được nghiệm thu', '1', 1, 'Chủ biên', 1, 100, 885, '2025-05-06 05:34:46', 0, 1, '1', '1', '1', NULL, ''),
(8, 2, 2025, 1, 'Biên soạn giáo trình do Nhà trường đặt hàng không được NSNN cấp kinh phí, được nghiệm thu', '1', 1, 'Chủ biên', 1, 100, 885, '2025-05-06 05:39:24', 0, 1, '1', '1', '1', NULL, '');

--
-- Triggers `vietsach_history`
--
DELIMITER $$
CREATE TRIGGER `after_vietsach_delete` AFTER DELETE ON `vietsach_history` FOR EACH ROW BEGIN
    CALL UpdateTongHopNCKH(OLD.employeeID, OLD.result_year);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_vietsach_insert` AFTER INSERT ON `vietsach_history` FOR EACH ROW BEGIN
    CALL UpdateTongHopNCKH(NEW.employeeID, NEW.result_year);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_vietsach_update` AFTER UPDATE ON `vietsach_history` FOR EACH ROW BEGIN
    CALL UpdateTongHopNCKH(NEW.employeeID, NEW.result_year);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure for view `tonghop_nckh`
--
DROP TABLE IF EXISTS `tonghop_nckh`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `tonghop_nckh`  AS SELECT `tong_hop_nckh`.`employeeID` AS `employeeID`, `tong_hop_nckh`.`result_year` AS `result_year`, `tong_hop_nckh`.`tong_gio_nckh` AS `tong_gio_nckh`, `tong_hop_nckh`.`dinh_muc_toi_thieu` AS `dinh_muc_toi_thieu`, `tong_hop_nckh`.`trang_thai_hoan_thanh` AS `trang_thai_hoan_thanh`, `tong_hop_nckh`.`ngay_cap_nhat` AS `ngay_cap_nhat` FROM `tong_hop_nckh` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bai_bao_history`
--
ALTER TABLE `bai_bao_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_year` (`employeeID`,`result_year`),
  ADD KEY `idx_nckh_id` (`nckh_id`);

--
-- Indexes for table `coi_thi`
--
ALTER TABLE `coi_thi`
  ADD PRIMARY KEY (`employeeID`,`result_year`);

--
-- Indexes for table `create_schedules`
--
ALTER TABLE `create_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employeeID` (`employeeID`);

--
-- Indexes for table `dien_tap`
--
ALTER TABLE `dien_tap`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee_year` (`employeeID`,`result_year`);

--
-- Indexes for table `employee`
--
ALTER TABLE `employee`
  ADD PRIMARY KEY (`employeeID`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `userName` (`userName`),
  ADD UNIQUE KEY `teacherID` (`teacherID`);

--
-- Indexes for table `giangday`
--
ALTER TABLE `giangday`
  ADD PRIMARY KEY (`employeeID`,`result_year`);

--
-- Indexes for table `hoi_dong`
--
ALTER TABLE `hoi_dong`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee_year` (`employeeID`,`result_year`);

--
-- Indexes for table `huongdansvnckh`
--
ALTER TABLE `huongdansvnckh`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `huongdansv_history`
--
ALTER TABLE `huongdansv_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_year` (`employeeID`,`result_year`),
  ADD KEY `huongDanSV_history_ibfk_2` (`nckh_id`);

--
-- Indexes for table `nckhcc_history`
--
ALTER TABLE `nckhcc_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_year` (`employeeID`,`result_year`),
  ADD KEY `nckhcc_history_ibfk_2` (`nckh_id`);

--
-- Indexes for table `nghiencuukhoahoccaccap`
--
ALTER TABLE `nghiencuukhoahoccaccap`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `nv3_hours`
--
ALTER TABLE `nv3_hours`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `ra_de_thi`
--
ALTER TABLE `ra_de_thi`
  ADD PRIMARY KEY (`employeeID`,`result_year`);

--
-- Indexes for table `schedule`
--
ALTER TABLE `schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employeeID` (`employeeID`);

--
-- Indexes for table `task_registrations`
--
ALTER TABLE `task_registrations`
  ADD PRIMARY KEY (`registration_id`),
  ADD KEY `idx_employee_section` (`employee_id`,`section`),
  ADD KEY `idx_year` (`year`);

--
-- Indexes for table `tong_hop_nckh`
--
ALTER TABLE `tong_hop_nckh`
  ADD PRIMARY KEY (`employeeID`,`result_year`);

--
-- Indexes for table `total_hours`
--
ALTER TABLE `total_hours`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee_year` (`employee_id`,`year`),
  ADD KEY `idx_year` (`year`);

--
-- Indexes for table `tot_nghiep`
--
ALTER TABLE `tot_nghiep`
  ADD PRIMARY KEY (`employeeID`,`result_year`);

--
-- Indexes for table `vietbaibao`
--
ALTER TABLE `vietbaibao`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `vietsach`
--
ALTER TABLE `vietsach`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `vietsach_history`
--
ALTER TABLE `vietsach_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_year` (`employeeID`,`result_year`),
  ADD KEY `vietSach_history_ibfk_2` (`nckh_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bai_bao_history`
--
ALTER TABLE `bai_bao_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=181;

--
-- AUTO_INCREMENT for table `create_schedules`
--
ALTER TABLE `create_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dien_tap`
--
ALTER TABLE `dien_tap`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `employee`
--
ALTER TABLE `employee`
  MODIFY `employeeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `hoi_dong`
--
ALTER TABLE `hoi_dong`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `huongdansv_history`
--
ALTER TABLE `huongdansv_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `nckhcc_history`
--
ALTER TABLE `nckhcc_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=138;

--
-- AUTO_INCREMENT for table `nv3_hours`
--
ALTER TABLE `nv3_hours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule`
--
ALTER TABLE `schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `task_registrations`
--
ALTER TABLE `task_registrations`
  MODIFY `registration_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `total_hours`
--
ALTER TABLE `total_hours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `vietbaibao`
--
ALTER TABLE `vietbaibao`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `vietsach_history`
--
ALTER TABLE `vietsach_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bai_bao_history`
--
ALTER TABLE `bai_bao_history`
  ADD CONSTRAINT `bai_bao_history_ibfk_1` FOREIGN KEY (`employeeID`) REFERENCES `employee` (`employeeID`) ON DELETE CASCADE;

--
-- Constraints for table `coi_thi`
--
ALTER TABLE `coi_thi`
  ADD CONSTRAINT `coi_thi_ibfk_1` FOREIGN KEY (`employeeID`) REFERENCES `employee` (`employeeID`) ON DELETE CASCADE;

--
-- Constraints for table `create_schedules`
--
ALTER TABLE `create_schedules`
  ADD CONSTRAINT `create_schedules_ibfk_1` FOREIGN KEY (`employeeID`) REFERENCES `employee` (`employeeID`);

--
-- Constraints for table `dien_tap`
--
ALTER TABLE `dien_tap`
  ADD CONSTRAINT `dien_tap_ibfk_1` FOREIGN KEY (`employeeID`) REFERENCES `employee` (`employeeID`);

--
-- Constraints for table `giangday`
--
ALTER TABLE `giangday`
  ADD CONSTRAINT `giangday_ibfk_1` FOREIGN KEY (`employeeID`) REFERENCES `employee` (`employeeID`) ON DELETE CASCADE;

--
-- Constraints for table `huongdansv_history`
--
ALTER TABLE `huongdansv_history`
  ADD CONSTRAINT `huongDanSV_history_ibfk_1` FOREIGN KEY (`employeeID`) REFERENCES `employee` (`employeeID`) ON DELETE CASCADE,
  ADD CONSTRAINT `huongDanSV_history_ibfk_2` FOREIGN KEY (`nckh_id`) REFERENCES `huongdansvnckh` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `nckhcc_history`
--
ALTER TABLE `nckhcc_history`
  ADD CONSTRAINT `nckhcc_history_ibfk_1` FOREIGN KEY (`employeeID`) REFERENCES `employee` (`employeeID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `nckhcc_history_ibfk_2` FOREIGN KEY (`nckh_id`) REFERENCES `nghiencuukhoahoccaccap` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `nv3_hours`
--
ALTER TABLE `nv3_hours`
  ADD CONSTRAINT `nv3_hours_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employeeID`);

--
-- Constraints for table `ra_de_thi`
--
ALTER TABLE `ra_de_thi`
  ADD CONSTRAINT `ra_de_thi_ibfk_1` FOREIGN KEY (`employeeID`) REFERENCES `employee` (`employeeID`) ON DELETE CASCADE;

--
-- Constraints for table `tong_hop_nckh`
--
ALTER TABLE `tong_hop_nckh`
  ADD CONSTRAINT `tong_hop_nckh_ibfk_1` FOREIGN KEY (`employeeID`) REFERENCES `employee` (`employeeID`) ON DELETE CASCADE;

--
-- Constraints for table `tot_nghiep`
--
ALTER TABLE `tot_nghiep`
  ADD CONSTRAINT `tot_nghiep_ibfk_1` FOREIGN KEY (`employeeID`) REFERENCES `employee` (`employeeID`) ON DELETE CASCADE;

--
-- Constraints for table `vietsach_history`
--
ALTER TABLE `vietsach_history`
  ADD CONSTRAINT `vietSach_history_ibfk_1` FOREIGN KEY (`employeeID`) REFERENCES `employee` (`employeeID`) ON DELETE CASCADE,
  ADD CONSTRAINT `vietSach_history_ibfk_2` FOREIGN KEY (`nckh_id`) REFERENCES `vietsach` (`ID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
