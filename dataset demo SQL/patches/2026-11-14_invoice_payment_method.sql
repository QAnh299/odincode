-- ============================================================
-- Bổ sung phương thức thanh toán cho hóa đơn chưa thanh toán (14/11/2026).
-- Dữ liệu mẫu cũ có hóa đơn Chờ thanh toán / Quá hạn để trống payment_method, trong khi luồng hiện tại
-- bắt buộc chọn Chuyển khoản hoặc Tiền mặt khi lập hóa đơn (không có QR, không xác nhận được, không cảnh báo chuyển lệch).
-- Quy tắc: dùng phương thức của kỳ đã có phương thức gần nhất trước đó của cùng đơn hàng;
--          đơn chưa có kỳ nào thì gán Chuyển khoản (BankTransfer).
-- Chạy được nhiều lần: chỉ sửa dòng còn NULL.
-- ============================================================
USE `odin`;
SET SQL_SAFE_UPDATES = 0;

DROP TEMPORARY TABLE IF EXISTS tmp_invoice_method;
CREATE TEMPORARY TABLE tmp_invoice_method AS
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

-- Kiểm tra: phải ra 0
SELECT COUNT(*) AS hoa_don_chua_thanh_toan_thieu_phuong_thuc
FROM `Invoices`
WHERE payment_method IS NULL AND status IN ('Unpaid', 'Overdue');
