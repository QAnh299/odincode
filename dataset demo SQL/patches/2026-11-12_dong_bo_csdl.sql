-- ============================================================
-- Đồng bộ CẤU TRÚC CSDL `odin` đang chạy với odin.sql mới nhất (12/11/2026).
-- Giữ nguyên dữ liệu. Chạy được nhiều lần: phần nào đã có thì tự bỏ qua.
--   1. Appointments.status nhận thêm 'Cancelled'
--   2. Employees.phone đổi thành NOT NULL (chỉ khi mọi nhân viên đã có SĐT)
--   3. Index idx_leads_created (Leads) và idx_opp_lead_status (Opportunities) - NFR01
-- ============================================================
USE `odin`;
SET SQL_SAFE_UPDATES = 0;

-- 1. Appointments: ràng buộc CHECK của status chưa có 'Cancelled' thì tạo lại
SET @chk := (
  SELECT cc.CONSTRAINT_NAME
  FROM information_schema.CHECK_CONSTRAINTS cc
  JOIN information_schema.TABLE_CONSTRAINTS tc
    ON tc.CONSTRAINT_SCHEMA = cc.CONSTRAINT_SCHEMA AND tc.CONSTRAINT_NAME = cc.CONSTRAINT_NAME
  WHERE cc.CONSTRAINT_SCHEMA = 'odin' AND LOWER(tc.TABLE_NAME) = 'appointments'
    AND cc.CHECK_CLAUSE LIKE '%Scheduled%' AND cc.CHECK_CLAUSE NOT LIKE '%Cancelled%'
  LIMIT 1
);
SET @sql := IF(@chk IS NULL, 'DO 0',
  CONCAT('ALTER TABLE `Appointments` DROP CHECK `', @chk, '`, ADD CONSTRAINT `', @chk,
         '` CHECK (`status` IN (''Scheduled'', ''Success'', ''Failed'', ''Cancelled''))'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. Employees.phone: NOT NULL (bỏ qua nếu đã NOT NULL; báo nếu còn nhân viên chưa có SĐT)
SET @nullable := (
  SELECT IS_NULLABLE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'odin' AND LOWER(TABLE_NAME) = 'employees' AND COLUMN_NAME = 'phone'
);
SET @missing := (SELECT COUNT(*) FROM `Employees` WHERE `phone` IS NULL);
SET @sql := CASE
  WHEN @nullable = 'NO' THEN 'DO 0'
  WHEN @missing > 0 THEN 'SELECT employee_id, full_name, ''CHUA CO SDT - cap nhat roi chay lai file nay'' AS canh_bao FROM `Employees` WHERE `phone` IS NULL'
  ELSE 'ALTER TABLE `Employees` MODIFY COLUMN `phone` VARCHAR(10) NOT NULL'
END;
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3a. Index danh sách Lead (mới nhất trước + phân trang)
SET @sql := IF(EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = 'odin' AND LOWER(TABLE_NAME) = 'leads' AND INDEX_NAME = 'idx_leads_created'),
  'DO 0', 'ALTER TABLE `Leads` ADD INDEX `idx_leads_created` (`created_at`, `lead_id`)');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3b. Index kiểm tra Lead có cơ hội đang xử lý
SET @sql := IF(EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = 'odin' AND LOWER(TABLE_NAME) = 'opportunities' AND INDEX_NAME = 'idx_opp_lead_status'),
  'DO 0', 'ALTER TABLE `Opportunities` ADD INDEX `idx_opp_lead_status` (`lead_id`, `status`)');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Kiểm tra: cả 4 cột phải ra 1
SELECT
  (SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS cc
     JOIN information_schema.TABLE_CONSTRAINTS tc
       ON tc.CONSTRAINT_SCHEMA = cc.CONSTRAINT_SCHEMA AND tc.CONSTRAINT_NAME = cc.CONSTRAINT_NAME
     WHERE cc.CONSTRAINT_SCHEMA = 'odin' AND LOWER(tc.TABLE_NAME) = 'appointments'
       AND cc.CHECK_CLAUSE LIKE '%Cancelled%')                                         AS appointments_cancelled,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = 'odin' AND LOWER(TABLE_NAME) = 'employees'
       AND COLUMN_NAME = 'phone' AND IS_NULLABLE = 'NO')                               AS employees_phone_not_null,
  (SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = 'odin' AND INDEX_NAME = 'idx_leads_created')                 AS idx_leads_created,
  (SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = 'odin' AND INDEX_NAME = 'idx_opp_lead_status')               AS idx_opp_lead_status;
