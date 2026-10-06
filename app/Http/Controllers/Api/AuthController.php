<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/login – cấp token Sanctum.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username'    => 'required|string|max:100',
            'password'    => 'required|string|min:6',
            'device_name' => 'nullable|string|max:100',
        ], [
            'username.required' => 'Vui lòng nhập tên đăng nhập.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min'      => 'Mật khẩu phải có ít nhất :min ký tự.',
        ]);

        $user = User::with('employee.role')->where('username', $data['username'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => 'Tên đăng nhập hoặc mật khẩu không đúng.',
            ]);
        }

        if (! $user->canSignIn()) {
            return response()->json(['message' => 'Tài khoản đã bị vô hiệu hóa.'], 403);
        }

        $token = $user->createToken($data['device_name'] ?? 'api')->plainTextToken;

        return response()->json([
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => $this->profile($user),
        ]);
    }

    /**
     * POST /api/logout – thu hồi token hiện tại.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Đăng xuất thành công.']);
    }

    /**
     * GET /api/me – thông tin tài khoản đang đăng nhập.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->profile($request->user()->load('employee.role'))]);
    }

    private function profile(User $user): array
    {
        $employee = $user->employee;

        return [
            'account_id' => $user->account_id,
            'username'   => $user->username,
            'status'     => $user->status,
            'role'       => $employee?->role ? [
                'role_id'   => $employee->role->role_id,
                'role_name' => $employee->role->role_name,
            ] : null,
            'employee'   => $employee ? [
                'employee_id' => $employee->employee_id,
                'full_name'   => $employee->full_name,
                'email'       => $employee->email,
                'phone'       => $employee->phone,
                'job_title'   => $employee->job_title,
                'team_id'     => $employee->team_id,
                'branch_id'   => $employee->branch_id,
            ] : null,
        ];
    }
}
