-- Kiểm thử tính toàn vẹn dữ liệu – các câu lệnh dùng trong kịch bản TV01–TV18
-- CSDL: odin (MariaDB 10.4). Chạy trong tab SQL của phpMyAdmin.
-- Lưu ý: nhóm A cố tình ghi dữ liệu sai để kiểm tra CSDL có chặn hay không,
-- chỉ chạy trên CSDL thử nghiệm và phải sao lưu trước:
--   mysqldump -u root odin > odin_truoc_kiem_thu.sql


-- ============================================================
-- A. Ràng buộc trong CSDL (TV01 – TV10)
--    Mỗi câu phải bị CSDL báo lỗi, dữ liệu không đổi.
-- ============================================================

-- TV01: số tiền hóa đơn phải > 0              -> lỗi #4025 (CHECK)
UPDATE invoices SET payment_amount = 0 WHERE invoice_id = 'INV215';

-- TV02: hạn thanh toán không trước ngày lập   -> lỗi #4025 (CHECK)
UPDATE invoices SET due_date = '2026-01-01' WHERE invoice_id = 'INV215';

-- TV03: phương thức chỉ BankTransfer / Cash   -> lỗi #4025 (CHECK)
UPDATE invoices SET payment_method = 'Momo' WHERE invoice_id = 'INV215';

-- TV04: một đơn không có hai hóa đơn cùng kỳ  -> lỗi #1062 (UNIQUE)
UPDATE invoices SET payment_installment = 1 WHERE invoice_id = 'INV224';

-- TV05: đã trả không vượt tổng tiền đơn       -> lỗi #4025 (CHECK)
UPDATE salesorders SET paid_amount = 999999999 WHERE order_id = 'ORD169';

-- TV06: còn lại = tổng tiền - đã trả          -> lỗi #4025 (CHECK)
UPDATE salesorders SET remaining_amount = 1 WHERE order_id = 'ORD169';

-- TV07: một báo giá chỉ có một đơn hàng       -> lỗi #1062 (UNIQUE)
UPDATE salesorders SET quotation_id = 'QUO201' WHERE order_id = 'ORD179';

-- TV08: không xóa báo giá đã có dữ liệu con   -> lỗi #1451 (khóa ngoại)
DELETE FROM quotations WHERE quotation_id = 'QUO201';

-- TV09: hóa đơn phải thuộc đơn hàng có thật   -> lỗi #1452 (khóa ngoại)
UPDATE invoices SET order_id = 'ORD999' WHERE invoice_id = 'INV215';

-- TV10: tên đăng nhập không trùng             -> lỗi #1062 (UNIQUE)
UPDATE accounts SET username = 'sales01' WHERE username = 'sales02';

-- Kiểm tra lại sau nhóm A: dữ liệu thử vẫn giữ nguyên
SELECT invoice_id, payment_amount, due_date, payment_method, payment_installment, order_id
FROM invoices WHERE invoice_id IN ('INV215','INV224');
SELECT order_id, quotation_id, total_amount, paid_amount, remaining_amount
FROM salesorders WHERE order_id IN ('ORD169','ORD179');


-- ============================================================
-- B. Cập nhật đồng bộ khi xác nhận thanh toán (TV14, TV15)
--    Chạy trước và sau khi bấm "Xác nhận thu tiền" để so sánh.
-- ============================================================

SELECT i.invoice_id, i.status, i.payment_date, i.accountant_id,
       o.order_id, o.paid_amount, o.remaining_amount, o.status AS don_hang,
       op.opportunity_id, op.status AS co_hoi,
       (SELECT COUNT(*) FROM students s WHERE s.opportunity_id = op.opportunity_id) AS hoc_vien
FROM invoices i
JOIN salesorders o    ON o.order_id = i.order_id
JOIN quotations q     ON q.quotation_id = o.quotation_id
JOIN opportunities op ON op.opportunity_id = q.opportunity_id
WHERE i.invoice_id IN ('INV215','INV223','INV224');


-- ============================================================
-- C. Hóa đơn quá hạn (TV16)
--    Chạy trước và sau lệnh: php artisan invoices:mark-overdue
-- ============================================================

SELECT invoice_id, status, due_date
FROM invoices WHERE status = 'Unpaid' AND due_date < CURDATE();


-- ============================================================
-- D. Đối soát toàn bộ CSDL (TV17, TV18)
--    Mỗi dòng đếm số bản ghi sai, kết quả đúng là tất cả bằng 0.
-- ============================================================

SELECT 'Đã trả của đơn khác tổng hóa đơn đã thanh toán' AS kiem_tra, COUNT(*) AS so_loi FROM salesorders o WHERE o.paid_amount <> (SELECT IFNULL(SUM(payment_amount),0) FROM invoices i WHERE i.order_id=o.order_id AND i.status='Paid')
UNION ALL SELECT 'Đơn Hoàn tất mà còn nợ', COUNT(*) FROM salesorders WHERE status='Completed' AND remaining_amount<>0
UNION ALL SELECT 'Đơn Đang xử lý mà đã trả đủ', COUNT(*) FROM salesorders WHERE status='InProgress' AND remaining_amount=0
UNION ALL SELECT 'Hóa đơn còn hiệu lực vượt tổng đơn', COUNT(*) FROM salesorders o WHERE (SELECT IFNULL(SUM(payment_amount),0) FROM invoices i WHERE i.order_id=o.order_id AND i.status<>'Overdue') > o.total_amount
UNION ALL SELECT 'Hóa đơn Đã thanh toán thiếu ngày/phương thức', COUNT(*) FROM invoices WHERE status='Paid' AND (payment_date IS NULL OR payment_method IS NULL)
UNION ALL SELECT 'Tiền mặt Đã thanh toán thiếu kế toán', COUNT(*) FROM invoices WHERE status='Paid' AND payment_method='Cash' AND accountant_id IS NULL
UNION ALL SELECT 'Chưa thanh toán mà có ngày thanh toán', COUNT(*) FROM invoices WHERE status<>'Paid' AND payment_date IS NOT NULL
UNION ALL SELECT 'Báo giá Đã xác nhận không có đơn', COUNT(*) FROM quotations q WHERE status='Confirmed' AND NOT EXISTS (SELECT 1 FROM salesorders o WHERE o.quotation_id=q.quotation_id)
UNION ALL SELECT 'Báo giá Nháp/Từ chối lại có đơn', COUNT(*) FROM quotations q WHERE status<>'Confirmed' AND EXISTS (SELECT 1 FROM salesorders o WHERE o.quotation_id=q.quotation_id)
UNION ALL SELECT 'Kỳ thanh toán bị nhảy số', COUNT(*) FROM invoices i WHERE payment_installment>1 AND NOT EXISTS (SELECT 1 FROM invoices j WHERE j.order_id=i.order_id AND j.payment_installment=i.payment_installment-1)
UNION ALL SELECT 'Cơ hội Won chưa có học viên', COUNT(*) FROM opportunities op WHERE status='Won' AND NOT EXISTS (SELECT 1 FROM students s WHERE s.opportunity_id=op.opportunity_id);

-- TV18: liệt kê cơ hội Won chưa có học viên kèm báo giá (dùng khi dòng cuối ở trên khác 0)
SELECT op.opportunity_id, op.status,
       (SELECT GROUP_CONCAT(q.quotation_id, ':', q.status) FROM quotations q WHERE q.opportunity_id = op.opportunity_id) AS bao_gia
FROM opportunities op
WHERE status = 'Won' AND NOT EXISTS (SELECT 1 FROM students s WHERE s.opportunity_id = op.opportunity_id);

-- Số liệu tổng của CSDL kiểm thử lại (3.931 lead, 699 Won, 699 học viên, 858 hóa đơn)
SELECT (SELECT COUNT(*) FROM leads) AS so_lead,
       (SELECT COUNT(*) FROM opportunities WHERE status='Won') AS so_won,
       (SELECT COUNT(*) FROM students) AS so_hoc_vien,
       (SELECT COUNT(*) FROM invoices) AS so_hoa_don;
