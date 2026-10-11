<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tài khoản đang dùng mật khẩu tạm (quên mật khẩu) chỉ được vào trang đổi mật khẩu,
 * đăng xuất hoặc đổi ngôn ngữ cho tới khi đặt mật khẩu mới.
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (! $user || ! $user->mustChangePassword()
            || $request->routeIs('profile.password', 'logout', 'lang.switch')
            || $request->is('livewire/*')) {
            return $next($request);
        }

        return redirect()->route('profile.password');
    }
}
