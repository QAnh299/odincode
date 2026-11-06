-- Patch CSDL: cho bảng Appointments nhận trạng thái 'Cancelled'
-- (theo commit ec13baeb "Let salespeople cancel a pending appointment with a reason").
-- Chạy MỘT LẦN trên mỗi CSDL đang dùng bản odin.sql cũ (máy local và Azure). Không mất dữ liệu.
USE `odin`;

-- 1. Tìm tên ràng buộc CHECK cũ của cột status (MySQL tự đặt tên, ví dụ appointments_chk_2)
SET @chk := (
  SELECT cc.CONSTRAINT_NAME
  FROM information_schema.CHECK_CONSTRAINTS cc
  JOIN information_schema.TABLE_CONSTRAINTS tc
    ON tc.CONSTRAINT_SCHEMA = cc.CONSTRAINT_SCHEMA AND tc.CONSTRAINT_NAME = cc.CONSTRAINT_NAME
  WHERE cc.CONSTRAINT_SCHEMA = 'odin'
    AND LOWER(tc.TABLE_NAME) = 'appointments'
    AND cc.CHECK_CLAUSE LIKE '%Scheduled%'
  LIMIT 1
);

-- 2. Xoá ràng buộc cũ (nếu có)
SET @sql := IF(@chk IS NULL, 'SELECT ''Không tìm thấy CHECK cũ'' AS info',
               CONCAT('ALTER TABLE `Appointments` DROP CHECK `', @chk, '`'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. Thêm ràng buộc mới có 'Cancelled'
ALTER TABLE `Appointments`
  ADD CONSTRAINT `chk_appointments_status`
  CHECK (`status` IN ('Scheduled', 'Success', 'Failed', 'Cancelled'));

-- 4. Kiểm tra
SELECT cc.CONSTRAINT_NAME, cc.CHECK_CLAUSE
FROM information_schema.CHECK_CONSTRAINTS cc
WHERE cc.CONSTRAINT_SCHEMA = 'odin' AND cc.CHECK_CLAUSE LIKE '%Scheduled%';
