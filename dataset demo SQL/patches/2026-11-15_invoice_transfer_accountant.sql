-- ============================================================
-- Ghi nhận kế toán xác nhận chuyển khoản thủ công (15/11/2026).
-- Trước đây Invoices có CHECK "chuyển khoản thì accountant_id phải trống" (chuyển khoản luôn do hệ thống xác nhận).
-- Luồng hiện tại kế toán có thể xác nhận thủ công khi khách chuyển lệch / không qua QR / chuyển vào hóa đơn quá hạn,
-- nên accountant_id lưu mã kế toán đã xác nhận; để trống nghĩa là hệ thống tự khớp giao dịch SePay.
-- Patch bỏ CHECK đó. Không đổi dữ liệu. Chạy được nhiều lần: không còn CHECK thì bỏ qua.
-- Chạy được trên MariaDB (XAMPP) và MySQL 8 (Azure).
-- ============================================================
USE `odin`;

SET @chk := (
  SELECT cc.CONSTRAINT_NAME
  FROM information_schema.CHECK_CONSTRAINTS cc
  JOIN information_schema.TABLE_CONSTRAINTS tc
    ON tc.CONSTRAINT_SCHEMA = cc.CONSTRAINT_SCHEMA AND tc.CONSTRAINT_NAME = cc.CONSTRAINT_NAME
  WHERE cc.CONSTRAINT_SCHEMA = 'odin' AND LOWER(tc.TABLE_NAME) = 'invoices'
    AND cc.CHECK_CLAUSE LIKE '%accountant_id%' AND cc.CHECK_CLAUSE LIKE '%BankTransfer%'
  LIMIT 1
);
SET @sql := IF(@chk IS NULL, 'DO 0', CONCAT('ALTER TABLE `Invoices` DROP CONSTRAINT `', @chk, '`'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Kiểm tra: phải ra 0
SELECT COUNT(*) AS check_chuyen_khoan_con_lai
FROM information_schema.CHECK_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = 'odin' AND CHECK_CLAUSE LIKE '%accountant_id%' AND CHECK_CLAUSE LIKE '%BankTransfer%';
