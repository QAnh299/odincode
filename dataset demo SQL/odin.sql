
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
  `phone` VARCHAR(10) NULL,
  `email` VARCHAR(254) NULL,
  `source_name` VARCHAR(150) NOT NULL,
  `source_url` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `contact_method` VARCHAR(20) NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'New',
  `branch_id` VARCHAR(10) NOT NULL,
  PRIMARY KEY (`lead_id`),
  CHECK (`source_name` IN ('Facebook', 'Workshop', 'Website ODIN', 'Zalo', 'Instagram', 'TikTok')),
  -- Thong tin bat buoc phu thuoc kenh lien he (khong phu thuoc nguon Lead):
  --   Phone, Zalo: bat buoc co SDT; Facebook: bat buoc co link nguon (link trang ca nhan/bai viet)
  CONSTRAINT `chk_leads_phone` CHECK (
    `contact_method` NOT IN ('Phone', 'Zalo')
    OR (`phone` IS NOT NULL AND `phone` <> '')
  ),
  CONSTRAINT `chk_leads_source_url` CHECK (
    `contact_method` <> 'Facebook'
    OR (`source_url` IS NOT NULL AND `source_url` <> '')
  ),
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
  `scheduled_time` DATETIME NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'Scheduled',
  `notes` TEXT NULL,
  `employee_id` VARCHAR(10) NOT NULL,
  `opportunity_id` VARCHAR(10) NOT NULL,
  PRIMARY KEY (`appointment_id`, `appointment_type`),
  CHECK (`appointment_type` IN ('Test', 'Consultation')),
  -- Cancelled: Salesperson hủy buổi hẹn trước giờ hẹn (khách không tham gia buổi đó nữa)
  CHECK (`status` IN ('Scheduled', 'Success', 'Failed', 'Cancelled')),
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
  CHECK (`activity_name` IN ('Liên hệ ban đầu', 'Xếp lại lịch', 'Chốt đăng ký'))
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
  CHECK (`result` IN ('Success', 'Failed', 'Unreachable')),
  CONSTRAINT `fk_careresults_activity_id` FOREIGN KEY (`activity_id`)
    REFERENCES `CareActivities` (`activity_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_careresults_opportunity_id` FOREIGN KEY (`opportunity_id`)
    REFERENCES `Opportunities` (`opportunity_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_careresults_employee_id` FOREIGN KEY (`employee_id`)
    REFERENCES `Employees` (`employee_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `StagesHistory` (
  `history_id` VARCHAR(10) NOT NULL,
  `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT NULL,
  `opportunity_id` VARCHAR(10) NOT NULL,
  `from_stage_id` VARCHAR(10) NOT NULL,
  `to_stage_id` VARCHAR(10) NOT NULL,
  PRIMARY KEY (`history_id`),
  CHECK (`from_stage_id` <> `to_stage_id`),
  CONSTRAINT `fk_stageshistory_opportunity_id` FOREIGN KEY (`opportunity_id`)
    REFERENCES `Opportunities` (`opportunity_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_stageshistory_from_stage_id` FOREIGN KEY (`from_stage_id`)
    REFERENCES `Stages` (`stage_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_stageshistory_to_stage_id` FOREIGN KEY (`to_stage_id`)
    REFERENCES `Stages` (`stage_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lich su chuyen nguoi phu trach Opportunity (Sale Leader chuyen cho salesperson khac trong doi).
-- stage_id: giai doan luc chuyen; du lieu cham soc truoc do giu nguyen nguoi thuc hien cu.
CREATE TABLE `OpportunityTransfers` (
  `transfer_id` VARCHAR(10) NOT NULL,
  `transferred_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reason` TEXT NOT NULL,
  `opportunity_id` VARCHAR(10) NOT NULL,
  `from_employee_id` VARCHAR(10) NOT NULL,
  `to_employee_id` VARCHAR(10) NOT NULL,
  `transferred_by` VARCHAR(10) NOT NULL,
  PRIMARY KEY (`transfer_id`),
  CHECK (`from_employee_id` <> `to_employee_id`),
  CONSTRAINT `fk_opportunitytransfers_opportunity_id` FOREIGN KEY (`opportunity_id`)
    REFERENCES `Opportunities` (`opportunity_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_opportunitytransfers_from_employee_id` FOREIGN KEY (`from_employee_id`)
    REFERENCES `Employees` (`employee_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_opportunitytransfers_to_employee_id` FOREIGN KEY (`to_employee_id`)
    REFERENCES `Employees` (`employee_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_opportunitytransfers_transferred_by` FOREIGN KEY (`transferred_by`)
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
  -- NULL = chua xac dinh: don vua tao khi xac nhan bao gia (HD17), SP chon Full / Installment o hoa don dau tien (HD21-22)
  `payment_type` VARCHAR(30) NULL,
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
  -- Chuyen khoan do he thong xac nhan giao dich nen khong co ke toan; tien mat do ke toan duyet (accountant_id)
  CHECK (`payment_method` IS NULL OR `payment_method` <> 'BankTransfer' OR `accountant_id` IS NULL),
  CHECK (`status` IN ('Unpaid', 'Paid', 'Overdue')),
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

-- Du lieu demo: chay odin_data.sql sau file nay.
