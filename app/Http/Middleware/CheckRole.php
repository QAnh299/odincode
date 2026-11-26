<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
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

        $roleName = $user->employee?->role?->role_name;

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
