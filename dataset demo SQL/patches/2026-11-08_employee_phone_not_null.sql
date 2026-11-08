-- Patch schema: đổi Employees.phone từ NULL thành NOT NULL.
-- Chạy MỘT LẦN trên mỗi CSDL đang dùng (máy local và Azure).
-- Điều kiện tiên quyết: mọi nhân viên đã có SĐT trước khi chạy patch này.
--   Kiểm tra: SELECT employee_id, full_name FROM Employees WHERE phone IS NULL;
-- Nếu còn dòng NULL, cập nhật SĐT cho nhân viên đó trước, sau đó mới chạy patch.
USE `odin`;

-- Đổi cột phone thành NOT NULL (VARCHAR(10), giữ UNIQUE KEY sẵn có)
ALTER TABLE `Employees`
  MODIFY COLUMN `phone` VARCHAR(10) NOT NULL;

-- Kiểm tra: phải ra 0
SELECT COUNT(*) AS phone_null_count FROM `Employees` WHERE `phone` IS NULL;
