# Deploy Odin CRM lên Azure (Azure for Students – không cần thẻ)

Kiến trúc: **Azure App Service (Linux, PHP 8.4)** chạy Laravel + **Azure Database for MySQL – Flexible Server (8.0)**.
Code được build bằng GitHub Actions (`.github/workflows/azure-deploy.yml`) mỗi lần push lên nhánh `main` trên GitHub.

Các file đã chuẩn bị sẵn trong repo:

| File | Tác dụng |
|---|---|
| `azure/startup.sh` | Chạy khi web khởi động: trỏ Nginx vào `public/`, tạo thư mục storage, cache config/route/view, chạy scheduler nền |
| `azure/nginx.conf` | Cấu hình Nginx cho Laravel |
| `azure/php/laravel.ini` | Tăng giới hạn upload (20MB), bộ nhớ, timezone |
| `.github/workflows/azure-deploy.yml` | Build composer + Vite và deploy |
| `bootstrap/app.php` | Thêm `trustProxies` để Laravel/Livewire sinh URL `https` đúng sau proxy của Azure |

---

## Bước 1 – Kích hoạt Azure for Students
1. Vào https://azure.microsoft.com/free/students → **Start free**.
2. Đăng nhập/đăng ký bằng email trường `@hvnh.edu.vn`, xác minh sinh viên.
3. Xong sẽ có subscription **Azure for Students** với 100 USD credit. Không gắn thẻ ⇒ hết credit thì dịch vụ bị tắt, **không bị trừ tiền**.

> **Đã chốt: region East Asia** (Southeast Asia bị policy của gói sinh viên chặn). Web App cũng tạo ở East Asia.
>
> Subscription sinh viên chỉ cho tạo tài nguyên ở một số region. Thử **Southeast Asia** trước; nếu báo lỗi "policy / location not allowed" thì chọn region khác trong danh sách gợi ý (East Asia, Korea Central, Japan East…). Web và MySQL nên cùng một region.

## Bước 2 – Tạo MySQL
1. Azure Portal → **Create a resource** → *Azure Database for MySQL – Flexible Server* → **Create**.
2. Thiết lập:
   - Resource group: tạo mới `odin-rg`
   - Server name: `odin-mysql-qa` (→ host `odin-mysql-qa.mysql.database.azure.com`)
   - MySQL version: **8.4** (bản LTS; 8.0 đã hết hỗ trợ chính thức)
   - Workload type: **For development or hobby projects**
   - Compute + storage: **Burstable, B1ms**, 20 GB (gói rẻ nhất; nếu còn trong 12 tháng dịch vụ miễn phí thì không tốn credit)
   - Authentication: MySQL authentication, đặt **Admin username** + **Password** (ghi lại)
3. Tab **Networking**: Public access → tick **Allow public access from any Azure service** và **Add current client IP address** (để máy bạn import dữ liệu).
4. **Review + create**.

**Import dữ liệu:** mở MySQL Workbench → kết nối tới `odin-mysql.mysql.database.azure.com`, port 3306, user/password ở trên (SSL: *If available* hoặc *Require*) → File → Open SQL Script → chạy `dataset demo SQL/odin.sql` (tự tạo database `odin`), sau đó tuỳ chọn chạy thêm `odin_demo_bulk.sql`, `odin_demo_bulk_2.sql`.

## Bước 3 – Tạo Web App
1. **Create a resource** → **Web App**.
2. Thiết lập:
   - Resource group: `odin-rg`
   - Name: ví dụ `odin-crm` (→ `https://odin-crm.azurewebsites.net`, tên phải chưa ai dùng)
   - Publish: **Code** · Runtime stack: **PHP 8.4** · OS: **Linux** · Region: cùng region MySQL
   - Pricing plan: **Basic B1** (chạy 24/7, có Always On, trừ vào credit) — hoặc **Free F1** nếu muốn không tốn credit (bị giới hạn CPU/ngày và tự ngủ ⇒ scheduler có thể không chạy đúng giờ)
3. **Review + create**.

## Bước 4 – Cấu hình Web App
### 4.1 Environment variables (Settings → Environment variables → App settings)
Thêm từng dòng (giá trị trong `< >` thay bằng của bạn; các giá trị MAIL_*, VIETQR_*, BANK_WEBHOOK_SECRET, APP_KEY lấy từ file `.env` trên máy):

```
APP_NAME=Odin CRM
APP_ENV=production
APP_DEBUG=false
APP_KEY=<copy APP_KEY từ .env>
APP_URL=https://<tên-web-app>.azurewebsites.net
APP_LOCALE=vi
APP_FALLBACK_LOCALE=en
APP_TIMEZONE=Asia/Ho_Chi_Minh
LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=<tên-mysql>.mysql.database.azure.com
DB_PORT=3306
DB_DATABASE=odin
DB_USERNAME=<admin username>
DB_PASSWORD=<admin password>
MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=<...>
MAIL_PORT=<...>
MAIL_USERNAME=<...>
MAIL_PASSWORD=<...>
MAIL_ENCRYPTION=<...>
MAIL_FROM_ADDRESS=<...>
MAIL_FROM_NAME=Odin CRM

VIETQR_BANK_ID=<...>
VIETQR_ACCOUNT_NO=<...>
VIETQR_ACCOUNT_NAME=<...>
BANK_WEBHOOK_SECRET=<...>

PHP_INI_SCAN_DIR=/usr/local/etc/php/conf.d:/home/site/wwwroot/azure/php
SCM_DO_BUILD_DURING_DEPLOYMENT=false
```
Bấm **Apply**.

### 4.2 Configuration → General settings
- **Startup Command:** `bash /home/site/wwwroot/azure/startup.sh`
- **Always On:** On (chỉ có ở gói B1)
- **SCM Basic Auth Publishing Credentials:** On (để GitHub Actions deploy được bằng publish profile)
- **Save**.

## Bước 5 – Kết nối GitHub Actions
1. Trang Web App → **Overview** → **Download publish profile** (file `.PublishSettings`).
2. Trên GitHub, repo `odincode` → **Settings → Secrets and variables → Actions**:
   - Tab **Secrets** → New secret: `AZURE_WEBAPP_PUBLISH_PROFILE` = toàn bộ nội dung file vừa tải.
   - Tab **Variables** → New variable: `AZURE_WEBAPP_NAME` = tên web app (ví dụ `odin-crm`).
3. Commit & push các file mới lên nhánh `main` trên GitHub (nhánh local `odin-main` đang theo dõi `origin/main`):
   ```
   git add azure .github bootstrap/app.php
   git commit -m "Cấu hình deploy Azure App Service"
   git push origin odin-main:main
   ```
4. Vào tab **Actions** trên GitHub xem tiến trình (khoảng 3–5 phút). Xong mở `https://<tên-web-app>.azurewebsites.net`.

## Bước 6 – Sau khi chạy được
- **SePay:** vào my.sepay.vn → Webhooks, đổi URL thành `https://<tên-web-app>.azurewebsites.net/api/payments/bank-webhook`.
- **Kiểm tra credit:** https://www.microsoftazuresponsor.com/balance (hoặc Cost Management trong Portal).
- **Xem lỗi:** Web App → *Log stream*, hoặc mở SSH (Development Tools → SSH) và xem `/home/site/wwwroot/storage/logs/`.
- Bảo vệ xong, muốn dừng hẳn: xoá resource group `odin-rg`.

## Sự cố thường gặp
| Hiện tượng | Cách xử lý |
|---|---|
| Trang mặc định của Azure / 404 | Kiểm tra Startup Command đã lưu chưa, rồi **Restart** web app |
| 500 Server Error | Tạm đặt `APP_DEBUG=true` để xem lỗi, xong nhớ tắt lại |
| Lỗi kết nối DB / SSL | Kiểm tra `DB_HOST`, mật khẩu, `MYSQL_ATTR_SSL_CA`, và Networking của MySQL đã cho phép Azure services |
| CSS/JS không tải | Kiểm tra bước *Build assets* trong GitHub Actions có chạy thành công |
| Workflow lỗi ở bước Deploy (401) | Bật lại *SCM Basic Auth Publishing Credentials* và tải lại publish profile |
