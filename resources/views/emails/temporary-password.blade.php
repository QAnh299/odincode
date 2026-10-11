<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="UTF-8">
    <title>{{ __('forgot_password.mail.subject') }}</title>
</head>

<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:12px;padding:28px;">
        <h2 style="margin:0 0 16px;color:#0f5132;">ODIN Language Center</h2>

        <p>{{ __('forgot_password.mail.greeting', ['name' => $fullName]) }}</p>
        <p>{{ __('forgot_password.mail.intro') }}</p>

        <table style="margin:16px 0;border-collapse:collapse;">
            <tr>
                <td style="padding:6px 16px 6px 0;color:#6b7280;">{{ __('auth.username') }}</td>
                <td style="padding:6px 0;font-weight:bold;">{{ $username }}</td>
            </tr>
            <tr>
                <td style="padding:6px 16px 6px 0;color:#6b7280;">{{ __('forgot_password.mail.temp_password') }}</td>
                <td style="padding:6px 0;">
                    <span style="display:inline-block;padding:6px 12px;background:#f0fdf4;border:1px dashed #16a34a;
                        border-radius:6px;font-family:Consolas,monospace;font-size:18px;letter-spacing:1px;">{{ $temporaryPassword }}</span>
                </td>
            </tr>
        </table>

        <p>{{ __('forgot_password.mail.change_note') }}</p>
        <p style="margin-top:16px;">
            <a href="{{ route('login') }}"
                style="display:inline-block;padding:10px 20px;background:#0f5132;color:#ffffff;border-radius:8px;text-decoration:none;">
                {{ __('auth.login_button') }}
            </a>
        </p>

        <p style="margin-top:24px;font-size:13px;color:#6b7280;">{{ __('forgot_password.mail.ignore_note') }}</p>
    </div>
</body>

</html>
