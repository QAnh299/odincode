-- Patch dữ liệu: bỏ VAT (VAT = 0) cho toàn bộ khoá học, báo giá, đơn hàng và hoá đơn.
-- Chạy MỘT LẦN trên mỗi CSDL đang dùng dữ liệu cũ (máy local và Azure). Chạy lại lần hai không đổi gì thêm.
--
-- Thứ tự tính lại (cùng công thức với app):
--   1. Courses.VAT = 0
--   2. QuotationDetails: VAT = 0, line_total = unit_price × quantity
--   3. Tổng báo giá mới = MAX(0, tạm tính − giảm giá voucher)   (Quotation::totalSql)
--   4. Invoices của đơn bị ảnh hưởng: payment_amount × (tổng mới / tổng cũ), làm tròn tới đồng;
--      nếu các đợt cộng lại đúng bằng tổng cũ thì đợt cuối nhận phần lệch để vẫn cộng đúng bằng tổng mới
--   5. SalesOrders: total_amount = tổng mới, paid_amount = tổng hoá đơn Paid, remaining_amount = total − paid
USE `odin`;

-- Bảng phụ (tạo trước transaction vì CREATE TABLE tự commit ngầm), xoá ở cuối
DROP TABLE IF EXISTS `_vat_quotes`, `_vat_orders`, `_vat_last`;
CREATE TABLE `_vat_quotes` (quotation_id VARCHAR(10) PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `_vat_orders` (
  order_id VARCHAR(10) PRIMARY KEY, quotation_id VARCHAR(10) NOT NULL,
  old_total DECIMAL(18,2) NOT NULL, old_invoiced DECIMAL(18,2) NOT NULL, new_total DECIMAL(18,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `_vat_last` (order_id VARCHAR(10) PRIMARY KEY, last_installment INT NOT NULL, diff DECIMAL(18,2) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

-- Báo giá đang có VAT ≠ 0
INSERT INTO `_vat_quotes`
SELECT DISTINCT quotation_id FROM `QuotationDetails` WHERE `VAT` <> 0;

-- Đơn hàng của các báo giá đó + tổng cũ + tổng hoá đơn cũ
INSERT INTO `_vat_orders` (order_id, quotation_id, old_total, old_invoiced)
SELECT o.order_id, o.quotation_id, o.total_amount,
       (SELECT COALESCE(SUM(i.payment_amount), 0) FROM `Invoices` i WHERE i.order_id = o.order_id)
FROM `SalesOrders` o
WHERE o.quotation_id IN (SELECT quotation_id FROM `_vat_quotes`);

-- 1. Khoá học
UPDATE `Courses` SET `VAT` = 0 WHERE `VAT` <> 0;

-- 2. Chi tiết báo giá
UPDATE `QuotationDetails`
SET `VAT` = 0, `line_total` = ROUND(`unit_price` * `quantity`, 2)
WHERE `VAT` <> 0;

-- 3. Tổng báo giá mới (tạm tính − voucher, không âm)
UPDATE `_vat_orders` t
JOIN `Quotations` q ON q.quotation_id = t.quotation_id
JOIN (SELECT quotation_id, SUM(line_total) AS subtotal FROM `QuotationDetails` GROUP BY quotation_id) s
  ON s.quotation_id = t.quotation_id
LEFT JOIN `Vouchers` v ON v.voucher_id = q.voucher_id
SET t.new_total = GREATEST(0, s.subtotal - COALESCE(
      CASE v.discount_type
        WHEN 'percentage' THEN ROUND(s.subtotal * v.value / 100, 2)
        WHEN 'fixed'      THEN LEAST(v.value, s.subtotal)
        ELSE 0
      END, 0));

-- 4a. Hoá đơn: thu nhỏ theo tỷ lệ
UPDATE `Invoices` i
JOIN `_vat_orders` t ON t.order_id = i.order_id
SET i.payment_amount = ROUND(i.payment_amount * t.new_total / t.old_total, 0)
WHERE t.old_total > 0 AND t.new_total <> t.old_total;

-- 4b. Đợt cuối nhận phần lệch do làm tròn (chỉ khi các đợt cũ cộng đúng bằng tổng cũ)
INSERT INTO `_vat_last`
SELECT t.order_id,
       (SELECT MAX(i.payment_installment) FROM `Invoices` i WHERE i.order_id = t.order_id) AS last_installment,
       t.new_total - (SELECT SUM(i.payment_amount) FROM `Invoices` i WHERE i.order_id = t.order_id) AS diff
FROM `_vat_orders` t
WHERE t.old_total = t.old_invoiced AND t.new_total <> t.old_total;

UPDATE `Invoices` i
JOIN `_vat_last` l ON l.order_id = i.order_id AND l.last_installment = i.payment_installment
SET i.payment_amount = i.payment_amount + l.diff
WHERE l.diff <> 0;

-- 5. Đơn hàng
UPDATE `SalesOrders` o
JOIN `_vat_orders` t ON t.order_id = o.order_id
JOIN (SELECT order_id, COALESCE(SUM(CASE WHEN status = 'Paid' THEN payment_amount END), 0) AS paid
      FROM `Invoices` GROUP BY order_id) p ON p.order_id = o.order_id
SET o.total_amount     = t.new_total,
    o.paid_amount      = p.paid,
    o.remaining_amount = t.new_total - p.paid;

-- Đơn chưa có hoá đơn nào
UPDATE `SalesOrders` o
JOIN `_vat_orders` t ON t.order_id = o.order_id
SET o.total_amount = t.new_total, o.remaining_amount = t.new_total - o.paid_amount
WHERE NOT EXISTS (SELECT 1 FROM `Invoices` i WHERE i.order_id = o.order_id);

COMMIT;

DROP TABLE `_vat_last`, `_vat_orders`, `_vat_quotes`;

-- Kiểm tra: tất cả phải ra 0
SELECT
  (SELECT COUNT(*) FROM `Courses` WHERE `VAT` <> 0)          AS courses_vat,
  (SELECT COUNT(*) FROM `QuotationDetails` WHERE `VAT` <> 0) AS quote_lines_vat,
  (SELECT COUNT(*) FROM `SalesOrders` o
     WHERE ABS(o.paid_amount - (SELECT COALESCE(SUM(payment_amount), 0) FROM `Invoices` i
                                WHERE i.order_id = o.order_id AND i.status = 'Paid')) > 0.01) AS paid_mismatch;
