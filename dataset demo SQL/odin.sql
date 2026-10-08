
CREATE DATABASE IF NOT EXISTS `odin`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `odin`;
SET NAMES utf8mb4;

-- Yeu cau MySQL >= 8.0.16 (CHECK duoc thuc thi, DEFAULT (CURRENT_DATE)).
-- Collation utf8mb4_unicode_ci khong phan biet hoa thuong, nen CHECK
-- status IN ('Active', ...) cung chap nhan 'active'.

CREATE TABLE `Branches` (
  `branch_id` VARCHAR(10) NOT NULL,
  `branch_name` VARCHAR(150) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(11) NULL ,
  `email` VARCHAR(254) NULL,
  `status` VARCHAR(10) NOT NULL,
  `established_date` DATE NULL,
  PRIMARY KEY (`branch_id`),
  CHECK (`status` IN ('Active', 'Inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Roles` (
  `role_id` VARCHAR(10) NOT NULL,
  `role_name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Accounts` (
  `account_id` VARCHAR(10) NOT NULL,
  `username` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL ,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` VARCHAR(30) NOT NULL,
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `uq_accounts_username` (`username`),
  CHECK (`status` IN ('Active', 'Inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `SalesTeams` (
  `team_id` VARCHAR(10) NOT NULL,
  `team_name` VARCHAR(150) NOT NULL,
  `established_date` DATE NOT NULL,
  `team_leader_id` VARCHAR(10) NULL,
  PRIMARY KEY (`team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Employees` (
  `employee_id` VARCHAR(10) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `date_of_birth` DATE NULL,
  `email` VARCHAR(254) NULL,
  `phone` VARCHAR(10) NULL,
  `job_title` VARCHAR(100) NOT NULL,
  `hire_date` DATE NOT NULL,
  `status` VARCHAR(30) NOT NULL,
  `role_id` VARCHAR(10) NOT NULL,
  `account_id` VARCHAR(10) NULL,
  `team_id` VARCHAR(10) NULL,
  `branch_id` VARCHAR(10) NOT NULL,
  PRIMARY KEY (`employee_id`),
  UNIQUE KEY `uq_employees_email` (`email`),
  UNIQUE KEY `uq_employees_phone` (`phone`),
  UNIQUE KEY `uq_employees_account` (`account_id`),
  CHECK (`status` IN ('Working', 'Resigned')),
  CONSTRAINT `fk_employees_role_id` FOREIGN KEY (`role_id`)
    REFERENCES `Roles` (`role_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_employees_account_id` FOREIGN KEY (`account_id`)
    REFERENCES `Accounts` (`account_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_employees_team_id` FOREIGN KEY (`team_id`)
    REFERENCES `SalesTeams` (`team_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_employees_branch_id` FOREIGN KEY (`branch_id`)
    REFERENCES `Branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `SalesTargets` (
  `target_id` VARCHAR(10) NOT NULL,
  `target_name` VARCHAR(150) NOT NULL,
  `target_type` VARCHAR(100) NOT NULL,
  `unit` VARCHAR(50) NULL,
  PRIMARY KEY (`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `TeamSalesTargets` (
  `target_id` VARCHAR(10) NOT NULL,
  `team_id` VARCHAR(10) NOT NULL,
  `target_month` DATE NOT NULL,
  `target_value` DECIMAL(18,2) NOT NULL,
  `status` VARCHAR(30) NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`target_id`, `team_id`, `target_month`),
  CHECK (DAYOFMONTH(`target_month`) = 1),
  CHECK (`target_value` >= 0),
  CHECK (`status` IN ('Active', 'Completed', 'Ended', 'Cancelled')),
  CONSTRAINT `fk_teamsalestargets_target_id` FOREIGN KEY (`target_id`)
    REFERENCES `SalesTargets` (`target_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_teamsalestargets_team_id` FOREIGN KEY (`team_id`)
    REFERENCES `SalesTeams` (`team_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Stages` (
  `stage_id` VARCHAR(10) NOT NULL,
  `stage_name` VARCHAR(150) NOT NULL,
  `sort_order` INT NOT NULL,
  `description` TEXT NULL,
  PRIMARY KEY (`stage_id`),
  CHECK (`sort_order` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Leads` (
  `lead_id` VARCHAR(10) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(10) NOT NULL,
  `email` VARCHAR(254) NULL,
  `source_name` VARCHAR(150) NOT NULL,
  `source_url` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `contact_method` VARCHAR(20) NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'New',
  `branch_id` VARCHAR(10) NOT NULL,
  PRIMARY KEY (`lead_id`),
  CHECK (`contact_method` IN ('Zalo', 'Phone', 'Facebook')),
  CHECK (`status` IN ('New', 'Converted')),
  CONSTRAINT `fk_leads_branch_id` FOREIGN KEY (`branch_id`)
    REFERENCES `Branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Opportunities` (
  `opportunity_id` VARCHAR(10) NOT NULL,
  `conversion_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expected_value` DECIMAL(18,2) NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'InProgress',
  `lead_id` VARCHAR(10) NOT NULL,
  `stage_id` VARCHAR(10) NOT NULL,
  `employee_id` VARCHAR(10) NOT NULL,
  PRIMARY KEY (`opportunity_id`),
  CHECK (`expected_value` >= 0),
  CHECK (`status` IN ('InProgress', 'Won', 'Lost', 'Archived')),
  CONSTRAINT `fk_opportunities_lead_id` FOREIGN KEY (`lead_id`)
    REFERENCES `Leads` (`lead_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_opportunities_stage_id` FOREIGN KEY (`stage_id`)
    REFERENCES `Stages` (`stage_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_opportunities_employee_id` FOREIGN KEY (`employee_id`)
    REFERENCES `Employees` (`employee_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Students` (
  `student_id` VARCHAR(10) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(10) NOT NULL,
  `email` VARCHAR(254) NULL,
  `date_of_birth` DATE NULL,
  `conversion_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` VARCHAR(30) NOT NULL,
  `opportunity_id` VARCHAR(10) NOT NULL,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `uq_students_opportunity` (`opportunity_id`),
  CHECK (`status` IN ('Studying', 'Dropped', 'Completed')),
  CONSTRAINT `fk_students_opportunity_id` FOREIGN KEY (`opportunity_id`)
    REFERENCES `Opportunities` (`opportunity_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Appointments` (
  `appointment_id` VARCHAR(10) NOT NULL,
  `appointment_type` VARCHAR(50) NOT NULL,
  `location` VARCHAR(255) NULL,
  `scheduled_time` DATETIME NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'Scheduled',
  `notes` TEXT NULL,
  `employee_id` VARCHAR(10) NOT NULL,
  `opportunity_id` VARCHAR(10) NOT NULL,
  PRIMARY KEY (`appointment_id`),
  CHECK (`status` IN ('Scheduled', 'Success', 'Failed')),
  CONSTRAINT `fk_appointments_employee_id` FOREIGN KEY (`employee_id`)
    REFERENCES `Employees` (`employee_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_appointments_opportunity_id` FOREIGN KEY (`opportunity_id`)
    REFERENCES `Opportunities` (`opportunity_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `CareActivities` (
  `activity_id` VARCHAR(10) NOT NULL,
  `activity_name` VARCHAR(150) NOT NULL,
  `notes` TEXT NULL,
  PRIMARY KEY (`activity_id`),
  CHECK (`activity_name` IN ('Liên hệ lần 1', 'Liên hệ lần 2', 'Liên hệ lần 3', 'Liên hệ lần 4'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `CareResults` (
  `result_id` VARCHAR(10) NOT NULL,
  `performed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `result` VARCHAR(30) NOT NULL,
  `notes` TEXT NULL,
  `activity_id` VARCHAR(10) NOT NULL,
  `opportunity_id` VARCHAR(10) NOT NULL,
  `employee_id` VARCHAR(10) NOT NULL,
  PRIMARY KEY (`result_id`),
  UNIQUE KEY `uq_careresults_opportunity_activity` (`opportunity_id`, `activity_id`),
  CHECK (`result` IN ('Success', 'Failed')),
  CONSTRAINT `fk_careresults_activity_id` FOREIGN KEY (`activity_id`)
    REFERENCES `CareActivities` (`activity_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_careresults_opportunity_id` FOREIGN KEY (`opportunity_id`)
    REFERENCES `Opportunities` (`opportunity_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_careresults_employee_id` FOREIGN KEY (`employee_id`)
    REFERENCES `Employees` (`employee_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Courses` (
  `course_id` VARCHAR(10) NOT NULL,
  `course_name` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `unit_price` DECIMAL(18,2) NOT NULL,
  `VAT` DECIMAL(5,2) NOT NULL DEFAULT 0 ,
  `duration` VARCHAR(100) NULL ,
  `status` VARCHAR(30) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`course_id`),
  CHECK (`unit_price` >= 0),
  CHECK (`VAT` BETWEEN 0 AND 100),
  CHECK (`status` IN ('Active', 'Inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Vouchers` (
  `voucher_id` VARCHAR(10) NOT NULL,
  `discount_type` VARCHAR(30) NOT NULL,
  `value` DECIMAL(18,2) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `status` VARCHAR(30) NOT NULL,
  PRIMARY KEY (`voucher_id`),
  CHECK (`discount_type` IN ('percentage', 'fixed')),
  CHECK (`value` >= 0 AND (`discount_type` <> 'percentage' OR `value` <= 100)),
  CHECK (`end_date` >= `start_date`),
  CHECK (`status` IN ('Active', 'Inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Quotations` (
  `quotation_id` VARCHAR(10) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expiry_date` DATE NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'Draft',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `notes` TEXT NULL,
  `employee_id` VARCHAR(10) NOT NULL,
  `voucher_id` VARCHAR(10) NULL,
  `opportunity_id` VARCHAR(10) NOT NULL,
  PRIMARY KEY (`quotation_id`),
  CHECK (`status` IN ('Draft', 'Confirmed', 'Rejected')),
  CONSTRAINT `fk_quotations_employee_id` FOREIGN KEY (`employee_id`)
    REFERENCES `Employees` (`employee_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_quotations_voucher_id` FOREIGN KEY (`voucher_id`)
    REFERENCES `Vouchers` (`voucher_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_quotations_opportunity_id` FOREIGN KEY (`opportunity_id`)
    REFERENCES `Opportunities` (`opportunity_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `QuotationDetails` (
  `quotation_id` VARCHAR(10) NOT NULL,
  `course_id` VARCHAR(10) NOT NULL,
  `unit_price` DECIMAL(18,2) NOT NULL,
  `quantity` INT NOT NULL,
  `VAT` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `line_total` DECIMAL(18,2) NOT NULL ,
  PRIMARY KEY (`quotation_id`, `course_id`),
  CHECK (`unit_price` >= 0),
  CHECK (`quantity` > 0),
  CHECK (`VAT` BETWEEN 0 AND 100),
  CHECK (`line_total` >= 0),
  CONSTRAINT `fk_quotationdetails_quotation_id` FOREIGN KEY (`quotation_id`)
    REFERENCES `Quotations` (`quotation_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_quotationdetails_course_id` FOREIGN KEY (`course_id`)
    REFERENCES `Courses` (`course_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `SalesOrders` (
  `order_id` VARCHAR(10) NOT NULL,
  `payment_type` VARCHAR(30) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total_amount` DECIMAL(18,2) NOT NULL,
  `paid_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `remaining_amount` DECIMAL(18,2) NOT NULL ,
  `status` VARCHAR(30) NOT NULL,
  `notes` TEXT NULL,
  `quotation_id` VARCHAR(10) NOT NULL,
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `uq_salesorders_quotation` (`quotation_id`),
  CHECK (`payment_type` IN ('Full', 'Installment')),
  CHECK (`status` IN ('InProgress', 'Completed')),
  CHECK (`total_amount` >= 0),
  CHECK (`paid_amount` >= 0 AND `paid_amount` <= `total_amount`),
  CHECK (`remaining_amount` >= 0 AND `remaining_amount` = `total_amount` - `paid_amount`),
  CONSTRAINT `fk_salesorders_quotation_id` FOREIGN KEY (`quotation_id`)
    REFERENCES `Quotations` (`quotation_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Invoices` (
  `invoice_id` VARCHAR(10) NOT NULL,
  `issued_date` DATE NOT NULL DEFAULT (CURRENT_DATE),
  `due_date` DATE NOT NULL,
  `payment_amount` DECIMAL(18,2) NOT NULL,
  `status` VARCHAR(30) NOT NULL,
  `payment_method` VARCHAR(30) NULL ,
  `payment_date` DATETIME NULL,
  `order_id` VARCHAR(10) NOT NULL,
  `payment_installment` INT NOT NULL DEFAULT 1,
  `created_by_employee_id` VARCHAR(10) NOT NULL,
  `accountant_id` VARCHAR(10) NULL,
  PRIMARY KEY (`invoice_id`),
  UNIQUE KEY `uq_invoices_order_installment` (`order_id`, `payment_installment`),
  CHECK (`payment_amount` > 0),
  CHECK (`payment_installment` > 0),
  CHECK (`due_date` >= `issued_date`),
  CHECK (`payment_method` IN ('BankTransfer', 'Cash')),
  CHECK (`status` IN ('Unpaid', 'Paid')),
  CONSTRAINT `fk_invoices_order_id` FOREIGN KEY (`order_id`)
    REFERENCES `SalesOrders` (`order_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_invoices_created_by_employee_id` FOREIGN KEY (`created_by_employee_id`)
    REFERENCES `Employees` (`employee_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_invoices_accountant_id` FOREIGN KEY (`accountant_id`)
    REFERENCES `Employees` (`employee_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm khóa ngoại sau khi cả hai bảng đã tồn tại.
ALTER TABLE `SalesTeams`
  ADD CONSTRAINT `fk_salesteams_team_leader_id`
  FOREIGN KEY (`team_leader_id`) REFERENCES `Employees` (`employee_id`)
  ON DELETE RESTRICT ON UPDATE RESTRICT;

-- ============================================================
-- DU LIEU DEMO ODIN CRM
-- Import file nay vao CSDL moi mot lan (khong import chong du lieu cu).
-- Tai khoan demo dung chung mat khau: password
-- Hash bcrypt duoi day tuong ung voi mat khau demo; khong luu mat khau ro.
-- Username: director, saleadmin, leader1, sales01, sales02,
--           leader2, sales03, accountant
-- Account status dung 'active' de phu hop kiem tra dang nhap cua ung dung.
-- ============================================================

INSERT INTO `Branches`
  (`branch_id`, `branch_name`, `address`, `phone`, `email`, `status`, `established_date`)
VALUES
  ('BR001', 'Trụ sở chính & Hội đồng thi IELTS', 'Số 01 Đông Tác, phường Kim Liên, Hà Nội', '0965754776', 'cs1odin@odin.com.vn', 'Active', '2020-06-01'),
  ('BR002', 'Cơ sở 02', 'Số 103 Trần Quốc Vượng, phường Cầu Giấy, Hà Nội', '0865750776', 'cs2odin@odin.com.vn', 'Active', '2021-03-15'),
  ('BR003', 'Cơ sở 03', 'Tầng 4 & 5, số 70 Trần Đại Nghĩa, phường Bạch Mai, Hà Nội', '0956854776', 'cs3odin@odin.com.vn', 'Active', '2022-09-05'),
  ('BR004', 'Cơ sở 04', 'Số 17 Nguyễn Văn Lộc, phường Hà Đông, Hà Nội', '0965789770', 'cs4odin@odin.com.vn', 'Active', '2023-08-21'),
  ('BR005', 'Cơ sở 05', 'Số 58 Phố Vọng, phường Bạch Mai, Hà Nội', '0965750076', 'cs5odin@odin.com.vn', 'Active', '2024-11-11');

INSERT INTO `Roles` (`role_id`, `role_name`, `description`) VALUES
  ('ROLE01', 'director', 'Giam doc: xem tong quan va bao cao toan he thong.'),
  ('ROLE02', 'sale_admin', 'Sale Admin: quan ly nhan vien, doi va phan bo Lead.'),
  ('ROLE03', 'sale_leader', 'Sale Leader: quan ly doi va hoat dong tu van.'),
  ('ROLE04', 'salesperson', 'Salesperson: cham soc Lead va xu ly co hoi kinh doanh.'),
  ('ROLE05', 'accountant', 'Ke toan: theo doi hoa don va xac nhan thanh toan.');

INSERT INTO `Accounts` (`account_id`, `username`, `password`, `created_at`, `status`) VALUES
  ('ACC001', 'director', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-01-05 09:00:00', 'active'),
  ('ACC002', 'saleadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-01-05 09:05:00', 'active'),
  ('ACC003', 'leader1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-01-05 09:10:00', 'active'),
  ('ACC004', 'sales01', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-01-05 09:15:00', 'active'),
  ('ACC005', 'sales02', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-01-05 09:20:00', 'active'),
  ('ACC006', 'leader2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-01-05 09:25:00', 'active'),
  ('ACC007', 'sales03', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-01-05 09:30:00', 'active'),
  ('ACC008', 'accountant', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-01-05 09:35:00', 'active');

-- Tao team truoc, sau do tao Employees va gan truong nhom o phia duoi.
INSERT INTO `SalesTeams` (`team_id`, `team_name`, `established_date`, `team_leader_id`) VALUES
  ('TEAM01', 'ODIN Sales 1', '2022-01-10', NULL),
  ('TEAM02', 'ODIN Sales 2', '2022-04-01', NULL);

INSERT INTO `SalesTargets` (`target_id`, `target_name`, `target_type`, `unit`) VALUES
  ('TGT001', 'Doanh thu', 'Revenue', 'VND'),
  ('TGT002', 'Hoc vien moi', 'NewStudents', 'nguoi'),
  ('TGT003', 'Lead moi', 'NewLeads', 'Lead');

INSERT INTO `Stages` (`stage_id`, `stage_name`, `sort_order`, `description`) VALUES
  ('STG001', 'Data chưa tương tác', 1, 'Cơ hội mới tạo, nhân viên chưa liên hệ với khách hàng.'),
  ('STG002', 'Data đã có lịch hẹn', 2, 'Đã liên hệ và đặt lịch hẹn tư vấn với khách hàng.'),
  ('STG003', 'Data đã xử lý', 3, 'Đã tư vấn, chăm sóc và gửi báo giá; đang chờ khách hàng quyết định.'),
  ('STG004', 'Chốt', 4, 'Khách hàng đồng ý đăng ký khóa học.'),
  ('STG005', 'Lưu trữ', 5, 'Cơ hội không tiếp tục xử lý và được lưu trữ.');

INSERT INTO `CareActivities` (`activity_id`, `activity_name`, `notes`) VALUES
  ('ACT001', 'Liên hệ lần 1', 'Liên hệ đầu tiên sau khi tiếp nhận cơ hội.'),
  ('ACT002', 'Liên hệ lần 2', 'Liên hệ lại khi lần 1 chưa có kết quả hoặc cần tư vấn thêm.'),
  ('ACT003', 'Liên hệ lần 3', 'Liên hệ lại lần thứ ba.'),
  ('ACT004', 'Liên hệ lần 4', 'Liên hệ lần cuối trước khi đóng cơ hội.');

INSERT INTO `Courses` (`course_id`, `course_name`, `description`, `unit_price`, `VAT`, `duration`, `status`, `created_at`) VALUES
  ('CRS001', 'Tieng Anh giao tiếp', 'Khóa học từ cơ bản đến nâng cao.', 6000000.00, 0.00, '3 thang', 'Active', '2026-01-01 08:00:00'),
  ('CRS002', 'IELTS Foundation', 'Nen tang tu vung, ngu phap va ky nang IELTS.', 8500000.00, 0.00, '4 thang', 'Active', '2026-01-01 08:05:00'),
  ('CRS003', 'IELTS Intensive', 'Luyen thi IELTS chuyen sau.', 12000000.00, 0.00, '5 thang', 'Active', '2026-01-01 08:10:00'),
  ('CRS004', 'English for Kids', 'Tieng Anh giao tiep danh cho tre em.', 5000000.00, 10.00, '3 thang', 'Active', '2026-01-01 08:15:00');

INSERT INTO `Vouchers` (`voucher_id`, `discount_type`, `value`, `start_date`, `end_date`, `status`) VALUES
  ('VCH001', 'percentage', 10.00, '2026-01-01', '2026-12-31', 'Active'),
  ('VCH002', 'fixed', 1000000.00, '2026-01-01', '2026-12-31', 'Active'),
  ('VCH003', 'fixed', 500000.00, '2026-01-01', '2026-12-31', 'Active');

INSERT INTO `Employees`
  (`employee_id`, `full_name`, `date_of_birth`, `email`, `phone`, `job_title`, `hire_date`, `status`, `role_id`, `account_id`, `team_id`, `branch_id`)
VALUES
  ('EMP001', 'Nguyễn Minh Anh', '1982-04-12', 'minhanh@odin.example', '0901000001', 'Giam doc', '2020-06-01', 'Working', 'ROLE01', 'ACC001', NULL, 'BR001'),
  ('EMP002', 'Trần Thu Hà', '1990-08-21', 'thuha@odin.example', '0901000002', 'Sale Admin', '2021-02-15', 'Working', 'ROLE02', 'ACC002', NULL, 'BR001'),
  ('EMP003', 'Lê Quang Huy', '1988-02-08', 'quanghuy@odin.example', '0901000003', 'Sale Leader', '2021-05-10', 'Working', 'ROLE03', 'ACC003', 'TEAM01', 'BR001'),
  ('EMP004', 'Phạm Ngọc Lan', '1995-11-03', 'ngoclan@odin.example', '0901000004', 'Salesperson', '2022-03-01', 'Working', 'ROLE04', 'ACC004', 'TEAM01', 'BR001'),
  ('EMP005', 'Đỗ Tuấn Kiệt', '1997-06-17', 'tuankiet@odin.example', '0901000005', 'Salesperson', '2023-01-09', 'Working', 'ROLE04', 'ACC005', 'TEAM01', 'BR001'),
  ('EMP006', 'Võ Thanh Tâm', '1989-09-27', 'thanhtam@odin.example', '0901000006', 'Sale Leader', '2022-04-01', 'Working', 'ROLE03', 'ACC006', 'TEAM02', 'BR002'),
  ('EMP007', 'Bùi Gia Hân', '1996-12-14', 'giahan@odin.example', '0901000007', 'Salesperson', '2023-06-12', 'Working', 'ROLE04', 'ACC007', 'TEAM02', 'BR002'),
  ('EMP008', 'Nguyễn Đức Long', '1991-03-30', 'duclong@odin.example', '0901000008', 'Ke toan', '2021-09-20', 'Working', 'ROLE05', 'ACC008', NULL, 'BR001');

UPDATE `SalesTeams` SET `team_leader_id` = 'EMP003' WHERE `team_id` = 'TEAM01';
UPDATE `SalesTeams` SET `team_leader_id` = 'EMP006' WHERE `team_id` = 'TEAM02';

INSERT INTO `TeamSalesTargets` (`target_id`, `team_id`, `target_month`, `target_value`, `status`, `updated_at`) VALUES
  ('TGT001', 'TEAM01', '2026-08-01', 180000000.00, 'Completed', '2026-09-01 09:00:00'),
  ('TGT001', 'TEAM01', '2026-09-01', 200000000.00, 'Completed', '2026-10-01 09:00:00'),
  ('TGT001', 'TEAM01', '2026-10-01', 220000000.00, 'Active', '2026-10-01 09:00:00'),
  ('TGT001', 'TEAM02', '2026-08-01', 150000000.00, 'Completed', '2026-09-01 09:00:00'),
  ('TGT001', 'TEAM02', '2026-09-01', 170000000.00, 'Completed', '2026-10-01 09:00:00'),
  ('TGT001', 'TEAM02', '2026-10-01', 190000000.00, 'Active', '2026-10-01 09:00:00'),
  ('TGT002', 'TEAM01', '2026-10-01', 25.00, 'Active', '2026-10-01 09:00:00'),
  ('TGT002', 'TEAM02', '2026-10-01', 20.00, 'Active', '2026-10-01 09:00:00'),
  ('TGT003', 'TEAM01', '2026-10-01', 120.00, 'Active', '2026-10-01 09:00:00'),
  ('TGT003', 'TEAM02', '2026-10-01', 100.00, 'Active', '2026-10-01 09:00:00');

INSERT INTO `Leads`
  (`lead_id`, `full_name`, `phone`, `email`, `source_name`, `source_url`, `created_at`, `contact_method`, `status`, `branch_id`)
VALUES
  ('LEAD001', 'Nguyễn Bảo Châu', '0912000001', 'baochau@example.test', 'Facebook', 'https://facebook.example.test/odin', '2026-09-01 08:30:00', 'Facebook', 'Converted', 'BR001'),
  ('LEAD002', 'Trần Hoàng Nam', '0912000002', 'hoangnam@example.test', 'Website', 'https://odin.example.test/ielts', '2026-09-02 09:10:00', 'Phone', 'Converted', 'BR001'),
  ('LEAD003', 'Lê Khanh Vy', '0912000003', 'khanhvy@example.test', 'Workshop', 'https://odin.example.test/events', '2026-09-04 10:00:00', 'Zalo', 'Converted', 'BR001'),
  ('LEAD004', 'Phạm Đức Thịnh', '0912000004', 'ducthinh@example.test', 'Referral', NULL, '2026-09-05 13:20:00', 'Phone', 'Converted', 'BR002'),
  ('LEAD005', 'Võ Minh Thu', '0912000005', 'minhthu@example.test', 'Facebook', 'https://facebook.example.test/odin', '2026-09-08 14:00:00', 'Zalo', 'Converted', 'BR002'),
  ('LEAD006', 'Bùi Quốc Bảo', '0912000006', 'quocbao@example.test', 'Website', 'https://odin.example.test/contact', '2026-09-10 08:45:00', 'Phone', 'Converted', 'BR001'),
  ('LEAD007', 'Đặng Ngọc Mai', '0912000007', 'ngocmai@example.test', 'Education Fair', NULL, '2026-09-12 15:30:00', 'Facebook', 'Converted', 'BR002'),
  ('LEAD008', 'Hoàng Gia Bảo', '0912000008', 'giabao@example.test', 'Facebook', 'https://facebook.example.test/odin', '2026-09-15 09:25:00', 'Facebook', 'New', 'BR001'),
  ('LEAD009', 'Ngô Phương Linh', '0912000009', 'phuonglinh@example.test', 'Workshop', 'https://odin.example.test/events', '2026-09-18 11:15:00', 'Zalo', 'New', 'BR001'),
  ('LEAD010', 'Đinh Thành Đạt', '0912000010', 'thanhdat@example.test', 'Referral', NULL, '2026-09-20 16:40:00', 'Phone', 'New', 'BR002'),
  ('LEAD011', 'Mai Thảo NGuyên', '0912000011', 'thaonguyen@example.test', 'Website', 'https://odin.example.test/english', '2026-09-25 10:05:00', 'Phone', 'New', 'BR002'),
  ('LEAD012', 'Phan Hoài An', '0912000012', 'hoaian@example.test', 'Facebook', 'https://facebook.example.test/odin', '2026-10-01 08:50:00', 'Zalo', 'New', 'BR001');

INSERT INTO `Opportunities`
  (`opportunity_id`, `conversion_date`, `expected_value`, `status`, `lead_id`, `stage_id`, `employee_id`)
VALUES
  ('OPP001', '2026-09-03 10:00:00', 5400000.00, 'Won', 'LEAD001', 'STG004', 'EMP004'),
  ('OPP002', '2026-09-06 14:30:00', 8500000.00, 'InProgress', 'LEAD002', 'STG003', 'EMP005'),
  ('OPP003', '2026-09-08 11:00:00', 8500000.00, 'Won', 'LEAD003', 'STG004', 'EMP004'),
  ('OPP004', '2026-09-09 09:30:00', NULL, 'Lost', 'LEAD004', 'STG005', 'EMP007'),
  ('OPP005', '2026-09-11 15:00:00', 5000000.00, 'InProgress', 'LEAD005', 'STG003', 'EMP007'),
  ('OPP006', '2026-09-13 13:00:00', 11000000.00, 'Won', 'LEAD006', 'STG004', 'EMP005'),
  ('OPP007', '2026-09-14 10:45:00', 12700000.00, 'Won', 'LEAD007', 'STG004', 'EMP007');

INSERT INTO `Students`
  (`student_id`, `full_name`, `phone`, `email`, `date_of_birth`, `conversion_date`, `status`, `opportunity_id`)
VALUES
  ('STD001', 'Nguyễn Bảo Châu', '0912000001', 'baochau@example.test', '2002-05-18', '2026-09-10 09:00:00', 'Studying', 'OPP001'),
  ('STD002', 'Lê Khanh Vy', '0912000003', 'khanhvy@example.test', '2004-02-23', '2026-09-15 10:00:00', 'Studying', 'OPP003'),
  ('STD003', 'Bùi Quốc Bảo', '0912000006', 'quocbao@example.test', '2001-11-06', '2026-09-20 14:00:00', 'Studying', 'OPP006'),
  ('STD004', 'Đặng Ngọc Mai', '0912000007', 'ngocmai@example.test', '2003-07-29', '2026-09-22 11:30:00', 'Studying', 'OPP007');

INSERT INTO `Appointments`
  (`appointment_id`, `appointment_type`, `location`, `scheduled_time`, `status`, `notes`, `employee_id`, `opportunity_id`)
VALUES
  ('APT001', 'Phone', 'Dien thoai', '2026-10-06 09:00:00', 'Success', 'Da goi lai tu van lo trinh IELTS.', 'EMP005', 'OPP002'),
  ('APT002', 'Consultation', 'Trụ sở chính - Số 01 Đông Tác', '2026-09-09 14:00:00', 'Success', 'Khach hang da chon khoa giao tiep.', 'EMP004', 'OPP001'),
  ('APT003', 'Consultation', 'Cơ sở 02 - Số 103 Trần Quốc Vượng', '2026-10-07 15:30:00', 'Success', 'Da tu van va kiem tra dau vao.', 'EMP007', 'OPP005'),
  ('APT004', 'Phone', 'Dien thoai', '2026-09-16 10:00:00', 'Failed', 'Khach khong den, xin doi lich.', 'EMP005', 'OPP006'),
  ('APT005', 'Online', 'Google Meet', '2026-10-08 13:30:00', 'Scheduled', 'Tu van khoa IELTS Intensive.', 'EMP007', 'OPP007');

INSERT INTO `CareResults`
  (`result_id`, `performed_at`, `result`, `notes`, `activity_id`, `opportunity_id`, `employee_id`)
VALUES
  ('CARE001', '2026-09-03 10:15:00', 'Success', 'Khach quan tam khoa giao tiep, gui lich khai giang qua Zalo.', 'ACT001', 'OPP001', 'EMP004'),
  ('CARE002', '2026-09-06 15:00:00', 'Failed', 'Khach khong nghe may.', 'ACT001', 'OPP002', 'EMP005'),
  ('CARE003', '2026-09-07 09:30:00', 'Success', 'Khach can tu van muc tieu IELTS 6.5, hen goi lai.', 'ACT002', 'OPP002', 'EMP005'),
  ('CARE004', '2026-09-08 12:00:00', 'Success', 'Khach dong y dang ky IELTS Foundation.', 'ACT001', 'OPP003', 'EMP004'),
  ('CARE005', '2026-09-09 10:00:00', 'Failed', 'Khach khong nghe may.', 'ACT001', 'OPP004', 'EMP007'),
  ('CARE006', '2026-09-10 10:00:00', 'Failed', 'Khach tu choi, da dang ky trung tam khac.', 'ACT002', 'OPP004', 'EMP007'),
  ('CARE007', '2026-09-11 16:00:00', 'Success', 'Da gui thong tin khoa hoc va uu dai, cho phan hoi bao gia.', 'ACT001', 'OPP005', 'EMP007'),
  ('CARE008', '2026-09-13 14:00:00', 'Success', 'Khach xac nhan dang ky IELTS Intensive.', 'ACT001', 'OPP006', 'EMP005'),
  ('CARE009', '2026-09-14 11:00:00', 'Success', 'Khach dong y hoc theo nhom, da gui huong dan thanh toan.', 'ACT001', 'OPP007', 'EMP007');

INSERT INTO `Quotations`
  (`quotation_id`, `created_at`, `expiry_date`, `status`, `updated_at`, `notes`, `employee_id`, `voucher_id`, `opportunity_id`)
VALUES
  ('QUO001', '2026-09-05 10:00:00', '2026-10-05', 'Confirmed', '2026-09-06 09:00:00', 'Uu dai 10 phan tram cho khoa giao tiep.', 'EMP004', 'VCH001', 'OPP001'),
  ('QUO002', '2026-09-09 11:30:00', '2026-10-09', 'Confirmed', '2026-09-10 10:00:00', 'Bao gia IELTS Foundation.', 'EMP004', NULL, 'OPP003'),
  ('QUO003', '2026-09-15 09:30:00', '2026-10-15', 'Confirmed', '2026-09-16 10:00:00', 'Ap dung voucher giam gia co dinh.', 'EMP005', 'VCH002', 'OPP006'),
  ('QUO004', '2026-09-18 14:00:00', '2026-10-18', 'Draft', '2026-09-18 14:00:00', 'Ban nhap bao gia English for Kids.', 'EMP007', NULL, 'OPP005'),
  ('QUO005', '2026-09-16 13:00:00', '2026-10-16', 'Confirmed', '2026-09-17 09:00:00', 'Dang ky hai suat hoc, thanh toan theo dot.', 'EMP007', 'VCH003', 'OPP007');

INSERT INTO `QuotationDetails`
  (`quotation_id`, `course_id`, `unit_price`, `quantity`, `VAT`, `line_total`)
VALUES
  ('QUO001', 'CRS001', 6000000.00, 1, 0.00, 6000000.00),
  ('QUO002', 'CRS002', 8500000.00, 1, 0.00, 8500000.00),
  ('QUO003', 'CRS003', 12000000.00, 1, 0.00, 12000000.00),
  ('QUO004', 'CRS004', 5000000.00, 1, 10.00, 5500000.00),
  ('QUO005', 'CRS001', 6000000.00, 2, 10.00, 13200000.00);

INSERT INTO `SalesOrders`
  (`order_id`, `payment_type`, `created_at`, `total_amount`, `paid_amount`, `remaining_amount`, `status`, `notes`, `quotation_id`)
VALUES
  ('ORD001', 'Full', '2026-09-06 09:30:00', 5400000.00, 5400000.00, 0.00, 'Completed', 'Da thanh toan du.', 'QUO001'),
  ('ORD002', 'Installment', '2026-09-10 10:30:00', 8500000.00, 3000000.00, 5500000.00, 'InProgress', 'Thanh toan theo hai dot.', 'QUO002'),
  ('ORD003', 'Full', '2026-09-16 10:30:00', 11000000.00, 0.00, 11000000.00, 'InProgress', 'Cho thanh toan.', 'QUO003'),
  ('ORD004', 'Installment', '2026-09-17 09:30:00', 12700000.00, 6350000.00, 6350000.00, 'InProgress', 'Da thanh toan dot mot.', 'QUO005');

INSERT INTO `Invoices`
  (`invoice_id`, `issued_date`, `due_date`, `payment_amount`, `status`, `payment_method`, `payment_date`, `order_id`, `payment_installment`, `created_by_employee_id`, `accountant_id`)
VALUES
  ('INV001', '2026-09-06', '2026-09-06', 5400000.00, 'Paid', 'BankTransfer', '2026-09-06 09:45:00', 'ORD001', 1, 'EMP004', 'EMP008'),
  ('INV002', '2026-09-10', '2026-09-10', 3000000.00, 'Paid', 'BankTransfer', '2026-09-10 11:00:00', 'ORD002', 1, 'EMP004', 'EMP008'),
  ('INV003', '2026-09-10', '2026-10-10', 5500000.00, 'Unpaid', NULL, NULL, 'ORD002', 2, 'EMP004', NULL),
  ('INV004', '2026-09-16', '2026-10-16', 11000000.00, 'Unpaid', NULL, NULL, 'ORD003', 1, 'EMP005', NULL),
  ('INV005', '2026-09-17', '2026-09-17', 6350000.00, 'Paid', 'Cash', '2026-09-17 10:00:00', 'ORD004', 1, 'EMP007', 'EMP008'),
  ('INV006', '2026-09-17', '2026-10-17', 6350000.00, 'Unpaid', NULL, NULL, 'ORD004', 2, 'EMP007', NULL);
