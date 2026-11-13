# Kiểm thử bảo mật và toàn vẹn dữ liệu

Người thực hiện: Thái Trâm Anh. Ngày kiểm thử: 12/11/2026. Phiên bản mã nguồn: `9726cf3`; các lỗi phát hiện được sửa ở `bec9be6` rồi kiểm thử lại.

| Thư mục | Nội dung | Kết quả |
|---|---|---|
| `bao-mat/` | Kịch bản BM01 – BM24 (file Excel) và ảnh minh chứng | Lần 1: 21/22 đạt (BM10 không đạt). Sau khi sửa và kiểm thử lại: 24/24 đạt |
| `toan-ven/` | Kịch bản TV01 – TV18 (file Excel), câu lệnh SQL đã dùng (`truy-van-doi-soat.sql`) và ảnh minh chứng | Lần 1: 17/18 đạt (TV18 không đạt). Sau khi kiểm thử lại: 18/18 đạt |

Cột "Ảnh minh chứng" trong file Excel ghi tên ảnh, ảnh tương ứng nằm trong thư mục `anh/` cùng cấp (ảnh kiểm thử lại có hậu tố `_lan2`).

Môi trường: Windows 10, PHP 8.2, MariaDB 10.4 (XAMPP), Chrome và Edge; một số ca bảo mật chạy trên bản triển khai odincrm.me.
