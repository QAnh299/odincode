-- ============================================================
-- Đồng bộ Giá trị dự kiến (Opportunities.expected_value) theo quy định 21/11/2026:
--   - Không có chỗ nhập tay; lấy TỔNG TIỀN của BÁO GIÁ GẦN NHẤT của cơ hội
--     (mới nhất theo created_at, trùng thì theo quotation_id).
--   - Chỉ có giá trị khi cơ hội ở stage Chốt (STG004); các stage khác để trống (NULL).
--   - Stage Chốt mà chưa có báo giá → NULL.
-- Tổng tiền = cùng công thức Quotation::totalSql(): tạm tính = SUM(line_total);
--   voucher % = ROUND(tạm tính × giá trị / 100, 2); voucher cố định = MIN(giá trị, tạm tính);
--   tổng = MAX(0, tạm tính − giảm giá).
-- Chạy được trên Azure (MySQL 8) hoặc XAMPP (MariaDB). Chạy lại nhiều lần không sao.
-- CHỈ CẦN SỬA DÒNG USE BÊN DƯỚI nếu CSDL trên Azure không tên là odin.
-- ============================================================
USE `odin`;
SET SQL_SAFE_UPDATES = 0;

DROP TEMPORARY TABLE IF EXISTS tmp_latest_quote_total;
CREATE TEMPORARY TABLE tmp_latest_quote_total (PRIMARY KEY (opportunity_id)) AS
SELECT Quotations.opportunity_id,
       GREATEST(0,
         (SELECT COALESCE(SUM(qd.line_total), 0) FROM QuotationDetails AS qd
           WHERE qd.quotation_id = Quotations.quotation_id)
         - COALESCE((SELECT CASE v.discount_type
                              WHEN 'percentage' THEN ROUND((SELECT COALESCE(SUM(qd.line_total), 0) FROM QuotationDetails AS qd
                                                             WHERE qd.quotation_id = Quotations.quotation_id) * v.value / 100, 2)
                              WHEN 'fixed' THEN LEAST(v.value, (SELECT COALESCE(SUM(qd.line_total), 0) FROM QuotationDetails AS qd
                                                                 WHERE qd.quotation_id = Quotations.quotation_id))
                              ELSE 0 END
                     FROM Vouchers AS v WHERE v.voucher_id = Quotations.voucher_id), 0)
       ) AS total
FROM Quotations
WHERE Quotations.quotation_id = (
  SELECT q2.quotation_id FROM Quotations AS q2
  WHERE q2.opportunity_id = Quotations.opportunity_id
  ORDER BY q2.created_at DESC, q2.quotation_id DESC
  LIMIT 1
);

UPDATE Opportunities AS o
LEFT JOIN tmp_latest_quote_total AS t ON t.opportunity_id = o.opportunity_id
SET o.expected_value = IF(o.stage_id = 'STG004', t.total, NULL);

DROP TEMPORARY TABLE IF EXISTS tmp_latest_quote_total;

-- Kiểm tra: các stage khác Chốt phải có_giá_trị = 0
SELECT stage_id, status, COUNT(*) AS so_co_hoi, SUM(expected_value IS NOT NULL) AS co_gia_tri
FROM Opportunities GROUP BY stage_id, status ORDER BY stage_id, status;
