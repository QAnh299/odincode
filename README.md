# Odin CRM

Hệ thống CRM quản lý Lead, báo giá, đơn hàng, hóa đơn và học viên cho trung tâm ngoại ngữ **ODIN Language Center**. Đây là đồ án tốt nghiệp.

Mỗi nhân viên đăng nhập sẽ thấy giao diện và menu theo **vai trò** của mình:

| Vai trò | `role_name` | Đường dẫn |
|---|---|---|
| Giám đốc | `director` | `/director` |
| Sale Admin | `sale_admin` | `/sale-admin` |
| Sale Leader | `sale_leader` | `/sale-leader` |
| Salesperson | `salesperson` | `/salesperson` |
| Kế toán | `accountant` | `/accountant` |

> Trạng thái hiện tại: đã có đăng nhập, phân quyền, khung giao diện (menu dọc, menu ngang, đổi màu, đổi ngôn ngữ) và trang chủ của từng vai trò. Các màn hình nghiệp vụ (Lead, báo giá, đơn hàng...) đang được phát triển.

---

## Mục lục

1. [Công nghệ sử dụng](#1-công-nghệ-sử-dụng)
2. [Cài đặt và chạy web](#2-cài-đặt-và-chạy-web)
3. [Tài khoản demo](#3-tài-khoản-demo)
4. [Cấu trúc thư mục](#4-cấu-trúc-thư-mục)
5. [Code giao diện / frontend nằm ở đâu](#5-code-giao-diện--frontend-nằm-ở-đâu)
6. [Thêm một trang mới](#6-thêm-một-trang-mới)
7. [Phân quyền và route](#7-phân-quyền-và-route)
8. [REST API](#8-rest-api)
9. [Cơ sở dữ liệu](#9-cơ-sở-dữ-liệu)
10. [Đa ngôn ngữ](#10-đa-ngôn-ngữ)

---

## 1. Công nghệ sử dụng

| Thành phần | Công nghệ |
|---|---|
| Ngôn ngữ backend | PHP 8.2+ |
| Framework | Laravel 11 |
| Giao diện web | Livewire 3 + Blade |
| CSS / UI | Bootstrap 5.3 + CSS riêng của dự án, font Be Vietnam Pro |
| JavaScript | JS thuần (ES module) + Bootstrap JS (dropdown, modal, offcanvas) |
| Build CSS/JS | Vite 6 (`laravel-vite-plugin`) |
| API | Laravel Sanctum (token) — chuẩn bị cho frontend React sau này |
| Cơ sở dữ liệu | MySQL / MariaDB |
| Đa ngôn ngữ | Laravel Localization + `laravel-lang/common` (Tiếng Việt, English) |

---

## 2. Cài đặt và chạy web

### Yêu cầu

- PHP **8.2** trở lên (có extension `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`)
- Composer 2
- Node.js 18+ và npm
- MySQL 8 hoặc MariaDB 10.4+ (XAMPP / Laragon đều được)

### Các bước

```bash
# 1. Lấy code về
git clone <đường-dẫn-repo>
cd odincode

# 2. Cài thư viện PHP và JS
composer install
npm install

# 3. Tạo file cấu hình và khoá ứng dụng
cp .env.example .env          # Windows CMD: copy .env.example .env
php artisan key:generate
```

**4. Tạo cơ sở dữ liệu:** import file `dataset demo SQL/odin.sql` vào MySQL. File này tự tạo database `odin`, các bảng nghiệp vụ và dữ liệu demo.

```bash
mysql -u root -p < "dataset demo SQL/odin.sql"
```

Hoặc mở phpMyAdmin → **Import** → chọn `odin.sql`.

Sau đó sửa `DB_USERNAME` / `DB_PASSWORD` trong `.env` cho đúng với máy của bạn.

```bash
# 5. Tạo các bảng hệ thống của Laravel (sessions, cache, jobs, token API)
php artisan migrate

# 6. Build CSS/JS
npm run build

# 7. Chạy web
php artisan serve
```

Mở trình duyệt: **http://127.0.0.1:8000** → tự chuyển tới trang đăng nhập.

### Khi đang code giao diện

Chạy song song 2 terminal để CSS/JS tự cập nhật khi lưu file, không cần build lại:

```bash
php artisan serve   # terminal 1
npm run dev         # terminal 2 (Vite dev server)
```

> Khi tắt `npm run dev`, nhớ chạy lại `npm run build` (hoặc xoá file `public/hot`) thì web mới dùng bản CSS/JS đã build.

---

## 3. Tài khoản demo

Có trong `odin.sql`. Mật khẩu của tất cả tài khoản: **`password`**

| Tên đăng nhập | Họ tên | Vai trò |
|---|---|---|
| `director` | Nguyen Minh Anh | Giám đốc |
| `saleadmin` | Tran Thu Ha | Sale Admin |
| `leader1` | Le Quang Huy | Sale Leader |
| `leader2` | Vo Thanh Tam | Sale Leader |
| `sales01` | Pham Ngoc Lan | Salesperson |
| `sales02` | Do Tuan Kiet | Salesperson |
| `sales03` | Bui Gia Han | Salesperson |
| `accountant` | Nguyen Duc Long | Kế toán |

---

## 4. Cấu trúc thư mục

Chỉ liệt kê những phần chính cần biết khi làm việc với dự án.

```
odincode/
├── app/                          # Code PHP (backend)
│   ├── Http/
│   │   ├── Controllers/Api/      # Controller cho REST API (AuthController...)
│   │   └── Middleware/
│   │       ├── CheckRole.php     # Kiểm tra vai trò: middleware 'role:director,...'
│   │       └── SetLocale.php     # Đặt ngôn ngữ theo session (vi / en)
│   ├── Livewire/                 # Class xử lý của từng trang (mỗi vai trò 1 thư mục)
│   │   ├── Auth/Login.php        # Trang đăng nhập
│   │   ├── Director/             # Giám đốc
│   │   ├── SaleAdmin/            # Sale Admin
│   │   ├── SaleLeader/           # Sale Leader
│   │   ├── Salesperson/          # Salesperson
│   │   └── Accountant/           # Kế toán
│   ├── Models/                   # Model Eloquent, ánh xạ 1-1 với bảng trong odin.sql
│   │   ├── Concerns/             # Trait dùng chung: khoá chuỗi (LEAD001...), khoá ghép
│   │   └── User.php              # Bảng Accounts (tài khoản đăng nhập)
│   └── Providers/
│
├── bootstrap/app.php             # Đăng ký middleware, route, xử lý lỗi
│
├── config/
│   ├── sidebar.php               # ★ Menu dọc của từng vai trò (thứ tự, menu con)
│   └── ...                       # Cấu hình chuẩn của Laravel
│
├── database/migrations/          # CHỈ chứa bảng hệ thống Laravel (sessions, cache, jobs, tokens)
├── dataset demo SQL/odin.sql     # ★ Schema + dữ liệu demo — nguồn chuẩn của CSDL
│
├── lang/                         # File dịch
│   ├── vi/                       # Tiếng Việt (menu.php, topbar.php, theme.php, auth.php...)
│   └── en/                       # English
│
├── public/                       # Thư mục web public
│   ├── images/                   # Logo, favicon
│   └── build/                    # CSS/JS đã build bởi Vite (tự sinh, không sửa tay)
│
├── resources/                    # ★ Code giao diện (frontend)
│   ├── css/                      # CSS (mỗi phần 1 file)
│   ├── js/                       # JavaScript
│   └── views/                    # Blade template (HTML)
│       ├── layouts/              # Khung trang
│       ├── components/           # Thành phần dùng lại (menu, popup...)
│       └── livewire/             # Nội dung từng trang, theo vai trò
│
├── routes/
│   ├── web.php                   # Route web chung: đăng nhập, đăng xuất, đổi ngôn ngữ, gom route vai trò
│   ├── web/                      # Route web của từng vai trò (director.php, sale-admin.php...)
│   └── api.php                   # Route REST API (/api/...)
│
├── tests/                        # Test PHPUnit
├── vite.config.js                # Khai báo các file CSS/JS cho Vite build
├── composer.json                 # Thư viện PHP
└── package.json                  # Thư viện JS
```

---

## 5. Code giao diện / frontend nằm ở đâu

Giao diện web dùng **Blade + Livewire**: HTML nằm trong file `.blade.php`, phần xử lý dữ liệu nằm trong class PHP ở `app/Livewire`. CSS/JS nằm trong `resources/css` và `resources/js`, được Vite build ra `public/build`.

### Khung trang (dùng chung cho mọi trang sau đăng nhập)

| File | Nội dung |
|---|---|
| `resources/views/layouts/app.blade.php` | Khung trang: menu dọc trái + menu ngang trên + nội dung bên phải |
| `resources/views/layouts/auth.blade.php` | Khung trang đăng nhập |

Các trang chỉ cần dùng layout `layouts.app` là tự có menu, **không cần code lại menu**.

### Thành phần dùng lại — `resources/views/components/`

| File | Nội dung |
|---|---|
| `sidebar.blade.php` | Menu dọc bên trái (đọc danh sách mục từ `config/sidebar.php`), có menu con, thu gọn chỉ còn icon |
| `topbar.blade.php` | Menu ngang phía trên: nút thu gọn menu, tiêu đề trang, các nút bên phải |
| `notification-bell.blade.php` | Chuông thông báo |
| `account-menu.blade.php` | Tài khoản đang đăng nhập (avatar, tên, vai trò, đăng xuất) |
| `theme-picker.blade.php` | Chọn màu giao diện |
| `language-switcher.blade.php` | Đổi ngôn ngữ Việt / Anh |
| `logout-modal.blade.php` | Popup xác nhận đăng xuất |

### Nội dung từng trang

| Phần | Vị trí |
|---|---|
| Class xử lý (PHP) | `app/Livewire/{VaiTro}/TenTrang.php` |
| Giao diện (Blade) | `resources/views/livewire/{vai-tro}/ten-trang.blade.php` |

Ví dụ trang chủ Giám đốc: `app/Livewire/Director/Home.php` + `resources/views/livewire/director/home.blade.php`.

### CSS — `resources/css/`

| File | Dùng cho |
|---|---|
| `app.css` | Style chung, nạp Bootstrap, biến màu (`--odin-primary`, `--odin-accent`...) |
| `sidebar.css` | Menu dọc + khung trang |
| `topbar.css` | Menu ngang phía trên |
| `theme.css` | Bộ chọn màu, popup đăng xuất |
| `auth.css` | Trang đăng nhập |

Khi thêm file CSS mới: khai báo trong `vite.config.js` (mục `input`) và trong `@vite([...])` của layout.

### JavaScript — `resources/js/`

| File | Dùng cho |
|---|---|
| `app.js` | File chính: nạp Bootstrap JS và các file bên dưới |
| `sidebar.js` | Thu gọn / mở rộng menu dọc, mở menu con (nhớ trạng thái bằng `localStorage`) |
| `theme.js` | Đổi màu giao diện (nhớ màu bằng `localStorage`) |

### Hình ảnh — `public/images/`

`logoodin-sidebar.png` (logo nền trong suốt cho menu), `logoodin-mark.png` (biểu tượng khi menu thu gọn), `favicon.png`...

---

## 6. Thêm một trang mới

Ví dụ: thêm trang **Quản lý Lead** cho Giám đốc.

**Bước 1 — Tạo class Livewire** `app/Livewire/Director/Leads.php`:

```php
<?php

namespace App\Livewire\Director;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Leads extends Component
{
    public function render()
    {
        return view('livewire.director.leads')->title(__('menu.leads'));
    }
}
```

**Bước 2 — Tạo giao diện** `resources/views/livewire/director/leads.blade.php`:

```blade
<div>
    {{-- Nội dung trang, menu đã có sẵn từ layout --}}
</div>
```

**Bước 3 — Khai báo route** trong `routes/web/director.php`:

```php
Route::get('/leads', \App\Livewire\Director\Leads::class)->name('leads');
```

Tên route đầy đủ là `director.leads`. Menu dọc tự nhận link này và tô sáng mục **Quản lý Lead** khi đang mở trang, không cần sửa menu. Quy tắc: tên route = `{vai trò}.{route}`, trong đó `route` lấy từ `config/sidebar.php`.

| Mục menu | Tên route |
|---|---|
| Dashboard & Báo cáo | `{vai trò}.home` |
| Quản lý Lead / Lead | `{vai trò}.leads` |
| Lịch hẹn | `{vai trò}.appointments` |
| Quản lý nhân viên / Nhân viên | `{vai trò}.employees` |
| Đội kinh doanh | `{vai trò}.sales-teams` |
| Báo giá, đơn hàng, hóa đơn | `{vai trò}.quotations`, `.orders`, `.invoices` |
| Học viên, khóa học, voucher | `{vai trò}.students`, `.courses`, `.vouchers` |

Mục menu chưa có route sẽ tạm trỏ tới `#`.

**Sửa menu** (thêm/bớt mục, đổi thứ tự, thêm menu con cho 1 vai trò): chỉ sửa `config/sidebar.php`, tên hiển thị nằm ở `lang/vi/menu.php` và `lang/en/menu.php`.

---

## 7. Phân quyền và route

- Vai trò của người dùng: `Accounts` → `Employees.account_id` → `Roles.role_name`.
- Sau khi đăng nhập, `/` tự chuyển về trang chủ của đúng vai trò.
- Mỗi nhóm route được bảo vệ bởi middleware `auth` + `role:{vai trò}` (khai báo trong `routes/web.php`). Vào sai vai trò sẽ báo lỗi 403.
- Chỉ đăng nhập được khi tài khoản `status = 'active'` và nhân viên `status = 'Working'`.

---

## 8. REST API

Dùng Laravel Sanctum (Bearer token), chạy song song với web Livewire, chuẩn bị cho frontend React sau này. Tất cả route có tiền tố `/api`, khai báo trong `routes/api.php`.

| Phương thức | Đường dẫn | Mô tả |
|---|---|---|
| `POST` | `/api/login` | Đăng nhập (`username`, `password`, `device_name` tuỳ chọn) → trả về `token` |
| `POST` | `/api/logout` | Thu hồi token hiện tại |
| `GET` | `/api/me` | Thông tin người dùng đang đăng nhập |

Các route có token gửi kèm header: `Authorization: Bearer {token}`. Nhóm route theo vai trò (`/api/director`, `/api/sale-admin`...) đã có sẵn khung, sẽ bổ sung sau.

---

## 9. Cơ sở dữ liệu

- **`dataset demo SQL/odin.sql` là nguồn chuẩn** của toàn bộ bảng nghiệp vụ (tên bảng dạng PascalCase như `Leads`, `Employees`; khoá chính dạng chuỗi như `LEAD001`, `EMP001`).
- **Không** tạo migration cho bảng nghiệp vụ. Thư mục `database/migrations` chỉ chứa bảng hệ thống của Laravel: `sessions`, `cache`, `jobs`, `personal_access_tokens`.
- Model trong `app/Models` được viết khớp với `odin.sql`. Khi sửa cấu trúc bảng thì sửa `odin.sql` trước, sau đó cập nhật model tương ứng.

---

## 10. Đa ngôn ngữ

- Hỗ trợ **Tiếng Việt** (mặc định) và **English**, đổi bằng nút ngôn ngữ trên menu ngang (route `/lang/{vi|en}`).
- Chuỗi hiển thị nằm trong `lang/vi/*.php` và `lang/en/*.php`, gọi trong Blade bằng `{{ __('menu.leads') }}`.
- Khi thêm chuỗi mới, nhớ thêm vào **cả 2** thư mục `vi` và `en`.
