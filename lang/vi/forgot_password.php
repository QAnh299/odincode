<?php

declare(strict_types=1);

// Quên mật khẩu (form trên trang đăng nhập + email mật khẩu tạm)
return [
    'title'                  => 'Quên mật khẩu',
    'subtitle'               => 'Điền email gắn với tài khoản của bạn để nhận mật khẩu tạm',
    'identifier'             => 'Email',
    'identifier_placeholder' => 'Nhập email của bạn',
    'identifier_required'    => 'Vui lòng nhập email.',
    'identifier_email'       => 'Email không đúng định dạng.',
    'identifier_max'         => 'Không được quá :max ký tự.',
    'submit'                 => 'Gửi mật khẩu tạm',
    'sending'                => 'Đang gửi...',
    'back_to_login'          => 'Quay lại đăng nhập',
    'sent'                   => 'Nếu email khớp với một tài khoản đang hoạt động, mật khẩu tạm đã được gửi tới email đó. Vui lòng kiểm tra hộp thư (kể cả thư rác).',
    'mail_failed'            => 'Không gửi được email lúc này. Vui lòng thử lại sau hoặc liên hệ quản trị viên.',

    'mail' => [
        'subject'       => 'ODIN CRM - Mật khẩu tạm của bạn',
        'greeting'      => 'Xin chào :name,',
        'intro'         => 'Chúng tôi đã nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Mật khẩu cũ không còn sử dụng được, hãy đăng nhập bằng thông tin dưới đây:',
        'temp_password' => 'Mật khẩu tạm',
        'change_note'   => 'Sau khi đăng nhập, hệ thống sẽ yêu cầu bạn đổi sang mật khẩu mới.',
        'ignore_note'   => 'Nếu bạn không yêu cầu đặt lại mật khẩu, hãy liên hệ quản trị viên ngay.',
    ],
];
