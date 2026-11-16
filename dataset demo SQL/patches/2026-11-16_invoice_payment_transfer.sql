-- ============================================================
-- Gộp 2 patch 14/11 + 15/11/2026 thành 1 file, chạy trên Azure (MySQL 8) hoặc XAMPP (MariaDB).
--   1) Bỏ CHECK "chuyển khoản thì accountant_id phải trống" trên Invoices (patch 15/11)
--   2) Bổ sung payment_method cho hóa đơn Chờ thanh toán / Quá hạn còn trống (patch 14/11)
-- Bỏ CHECK trước để bước 2 không bị CHECK chặn.
-- Chạy lại nhiều lần không sao.
-- CHỈ CẦN SỬA DÒNG USE BÊN DƯỚI nếu CSDL trên Azure không tên là odin.
-- ============================================================
USE `odin`;
SET SQL_SAFE_UPDATES = 0;

-- ---------- 1) Bỏ CHECK chuyển khoản / accountant_id ----------
SET @tbl := NULL, @chk := NULL;
SELECT tc.TABLE_NAME, cc.CONSTRAINT_NAME INTO @tbl, @chk
FROM information_schema.CHECK_CONSTRAINTS cc
JOIN information_schema.TABLE_CONSTRAINTS tc
  ON tc.CONSTRAINT_SCHEMA = cc.CONSTRAINT_SCHEMA AND tc.CONSTRAINT_NAME = cc.CONSTRAINT_NAME
WHERE cc.CONSTRAINT_SCHEMA = DATABASE() AND LOWER(tc.TABLE_NAME) = 'invoices'
  AND cc.CHECK_CLAUSE LIKE '%accountant_id%' AND cc.CHECK_CLAUSE LIKE '%BankTransfer%'
LIMIT 1;
SET @sql := IF(@chk IS NULL, 'DO 0', CONCAT('ALTER TABLE `', @tbl, '` DROP CONSTRAINT `', @chk, '`'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------- 2) Bổ sung payment_method ----------
-- Quy tắc: lấy phương thức của kỳ gần nhất trước đó (cùng đơn hàng) đã có phương thức;
--          không có thì gán BankTransfer.
DROP TEMPORARY TABLE IF EXISTS tmp_invoice_method;
CREATE TEMPORARY TABLE tmp_invoice_method (PRIMARY KEY (invoice_id)) AS
SELECT i.invoice_id,
       COALESCE((SELECT j.payment_method
                 FROM `Invoices` j
                 WHERE j.order_id = i.order_id
                   AND j.payment_installment < i.payment_installment
                   AND j.payment_method IS NOT NULL
                 ORDER BY j.payment_installment DESC
                 LIMIT 1), 'BankTransfer') AS payment_method
FROM `Invoices` i
WHERE i.payment_method IS NULL
  AND i.status IN ('Unpaid', 'Overdue');

UPDATE `Invoices` i
JOIN tmp_invoice_method t ON t.invoice_id = i.invoice_id
SET i.payment_method = t.payment_method
WHERE i.payment_method IS NULL;

DROP TEMPORARY TABLE tmp_invoice_method;

-- ---------- Kiểm tra: cả 2 cột phải bằng 0, cột ten_csdl phải đúng CSDL của hệ thống ----------
SELECT DATABASE() AS ten_csdl,
       (SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND CHECK_CLAUSE LIKE '%accountant_id%' AND CHECK_CLAUSE LIKE '%BankTransfer%') AS check_chuyen_khoan_con_lai,
       (SELECT COUNT(*) FROM `Invoices`
         WHERE payment_method IS NULL AND status IN ('Unpaid', 'Overdue')) AS hoa_don_thieu_phuong_thuc;
