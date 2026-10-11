<?php

declare(strict_types=1);

// Trang đổi mật khẩu (livewire/profile/change-password.blade.php)
return [
    'title'               => 'Đổi mật khẩu',
    'subtitle'            => 'Nhập mật khẩu hiện tại để xác nhận, sau đó đặt mật khẩu mới',
    'back'                => 'Thông tin tài khoản',
    'forced_subtitle'     => 'Đặt mật khẩu mới để tiếp tục sử dụng hệ thống',
    'forced_title'        => 'Bạn đang dùng mật khẩu tạm',
    'forced_message'      => 'Mật khẩu tạm được gửi qua email chỉ dùng để đăng nhập một lần. Vui lòng đặt mật khẩu mới trước khi sử dụng các chức năng khác.',

    'current'             => 'Mật khẩu hiện tại',
    'new'                 => 'Mật khẩu mới',
    'confirm'             => 'Nhập lại mật khẩu mới',
    'current_placeholder' => 'Nhập mật khẩu đang dùng',
    'new_placeholder'     => 'Ít nhất 8 ký tự, gồm chữ và số',
    'confirm_placeholder' => 'Nhập lại mật khẩu mới',
    'matched'             => 'Mật khẩu khớp',

    'submit'              => 'Cập nhật mật khẩu',
    'cancel'              => 'Huỷ',

    'success'             => 'Đổi mật khẩu thành công',
    'success_sub'         => 'Lần đăng nhập sau, hãy dùng mật khẩu mới.',

    // Lỗi
    'current_required'    => 'Vui lòng nhập mật khẩu hiện tại.',
    'current_wrong'       => 'Mật khẩu hiện tại không đúng.',
    'new_required'        => 'Vui lòng nhập mật khẩu mới.',
    'mismatch'            => 'Mật khẩu nhập lại không khớp.',
    'same_as_current'     => 'Mật khẩu mới phải khác mật khẩu hiện tại.',
    'too_short'           => 'Mật khẩu mới phải có ít nhất :min ký tự.',
    'too_long'            => 'Mật khẩu mới không được quá :max ký tự.',
    'need_letter'         => 'Mật khẩu mới phải có ít nhất một chữ cái.',
    'need_number'         => 'Mật khẩu mới phải có ít nhất một chữ số.',
    'throttle'            => 'Bạn đã nhập sai mật khẩu hiện tại quá nhiều lần. Vui lòng thử lại sau :seconds giây.',

    'strength' => [
        'weak'   => 'Yếu',
        'fair'   => 'Trung bình',
        'good'   => 'Khá',
        'strong' => 'Mạnh',
    ],

    'rules' => [
        'length'  => 'Ít nhất 8 ký tự',
        'letter'  => 'Có chữ cái',
        'number'  => 'Có chữ số',
        'mixed'   => 'Có chữ hoa và chữ thường (khuyến nghị)',
        'special' => 'Có ký tự đặc biệt như ! @ # (khuyến nghị)',
    ],

    'tips_title'          => 'Gợi ý đặt mật khẩu an toàn',
    'tips' => [
        'Không dùng ngày sinh, số điện thoại hay tên đăng nhập làm mật khẩu.',
        'Không dùng lại mật khẩu của email hay mạng xã hội.',
        'Một cụm từ dài, dễ nhớ thường an toàn hơn một từ ngắn, khó nhớ.',
        'Không chia sẻ mật khẩu cho người khác, kể cả đồng nghiệp.',
    ],
    'api_note'            => 'Sau khi đổi mật khẩu, các thiết bị đang đăng nhập qua ứng dụng (API) sẽ bị đăng xuất.',
];
