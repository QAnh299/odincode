<?php

declare(strict_types=1);

// Change password page (livewire/profile/change-password.blade.php)
return [
    'title'               => 'Change password',
    'subtitle'            => 'Enter your current password to confirm, then set a new one',
    'back'                => 'My profile',

    'current'             => 'Current password',
    'new'                 => 'New password',
    'confirm'             => 'Confirm new password',
    'current_placeholder' => 'Enter your current password',
    'new_placeholder'     => 'At least 8 characters, letters and numbers',
    'confirm_placeholder' => 'Re-enter the new password',
    'matched'             => 'Passwords match',

    'submit'              => 'Update password',
    'cancel'              => 'Cancel',

    'success'             => 'Password changed',
    'success_sub'         => 'Use your new password the next time you sign in.',

    // Errors
    'current_required'    => 'Please enter your current password.',
    'current_wrong'       => 'Your current password is incorrect.',
    'new_required'        => 'Please enter a new password.',
    'mismatch'            => 'The password confirmation does not match.',
    'same_as_current'     => 'The new password must be different from the current one.',
    'too_short'           => 'The new password must be at least :min characters.',
    'too_long'            => 'The new password may not be longer than :max characters.',
    'need_letter'         => 'The new password must contain at least one letter.',
    'need_number'         => 'The new password must contain at least one number.',
    'throttle'            => 'Too many incorrect attempts. Please try again in :seconds seconds.',

    'strength' => [
        'weak'   => 'Weak',
        'fair'   => 'Fair',
        'good'   => 'Good',
        'strong' => 'Strong',
    ],

    'rules' => [
        'length'  => 'At least 8 characters',
        'letter'  => 'Contains a letter',
        'number'  => 'Contains a number',
        'mixed'   => 'Upper and lower case letters (recommended)',
        'special' => 'A special character such as ! @ # (recommended)',
    ],

    'tips_title'          => 'Tips for a safe password',
    'tips' => [
        'Do not use your birthday, phone number or username.',
        'Do not reuse the password of your email or social accounts.',
        'A long, memorable phrase is usually safer than a short, complex word.',
        'Never share your password, even with colleagues.',
    ],
    'api_note'            => 'After changing your password, devices signed in through the app (API) will be signed out.',
];
