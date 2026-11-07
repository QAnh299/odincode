<?php

declare(strict_types=1);

// Forgot password (form on the login page + temporary password email)
return [
    'title'                  => 'Forgot password',
    'subtitle'               => 'Enter the email linked to your account to receive a temporary password',
    'identifier'             => 'Email',
    'identifier_placeholder' => 'Enter your email',
    'identifier_required'    => 'Please enter your email.',
    'identifier_email'       => 'Please enter a valid email address.',
    'identifier_max'         => 'Must not exceed :max characters.',
    'submit'                 => 'Send temporary password',
    'sending'                => 'Sending...',
    'back_to_login'          => 'Back to login',
    'sent'                   => 'If the email matches an active account, a temporary password has been sent to it. Please check your inbox (and spam folder).',
    'mail_failed'            => 'Unable to send the email right now. Please try again later or contact the administrator.',

    'mail' => [
        'subject'       => 'ODIN CRM - Your temporary password',
        'greeting'      => 'Hello :name,',
        'intro'         => 'We received a request to reset the password for your account. Your old password no longer works, please sign in with the details below:',
        'temp_password' => 'Temporary password',
        'change_note'   => 'After signing in, you will be asked to set a new password.',
        'ignore_note'   => 'If you did not request a password reset, please contact the administrator immediately.',
    ],
];
