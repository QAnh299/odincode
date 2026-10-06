<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Roles hệ thống CRM:
     *   director     – Giám đốc
     *   sale_admin   – Sale Admin
     *   sale_leader  – Sale Leader
     *   salesperson  – Salesperson
     *   accountant   – Kế toán
     *
     * Dùng chung cho web (session) và API (Sanctum). Với /api/*, lỗi 403
     * được trả về dạng JSON (cấu hình trong bootstrap/app.php).
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $user) {
            abort(403, 'Bạn cần đăng nhập để truy cập trang này.');
        }

        if (! $user->canSignIn()) {
            if (! $request->is('api/*')) {
                Auth::guard('web')->logout();
            }
            abort(403, 'Tài khoản đã bị vô hiệu hóa. Vui lòng liên hệ quản trị viên.');
        }

        // Lấy role_name từ Employee → Role
        $roleName = $user->employee?->role?->role_name;

        // Flatten danh sách roles được phép (hỗ trợ "director,sale_admin" hoặc nhiều args)
        $allowed = collect($roles)
            ->flatMap(fn ($r) => explode(',', $r))
            ->map(fn ($r) => trim($r))
            ->filter()
            ->all();

        if (empty($allowed) || in_array($roleName, $allowed)) {
            return $next($request);
        }

        abort(403, 'Bạn không có quyền truy cập trang này.');
    }
}
