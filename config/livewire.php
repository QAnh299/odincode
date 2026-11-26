<?php

return [
    'class_namespace' => 'App\\Livewire',

    'view_path' => resource_path('views'),

    'layout' => 'layouts.app',

    'lazy_placeholder' => null,

    'temporary_file_upload' => [
        'disk' => 'public',
        'rules' => ['required', 'file', 'max:524288'],
        'directory' => 'livewire-tmp',
        'middleware' => null,
        'preview_mimes' => [
            'png',
            'gif',
            'bmp',
            'svg',
            'wav',
            'mp4',
            'mov',
            'avi',
            'wmv',
            'mp3',
            'flac',
            'ogg',
            'wma',
        ],
        'max_upload_time' => 5,
    ],

    'render_on_redirect' => false,

    'legacy_model_binding' => false,

    'inject_assets' => true,

    'navigate' => [
        'show_progress_bar' => true,
        'progress_bar_color' => '#2299dd',
    ],

    'inject_morph_markers' => true,

    'pagination_theme' => 'bootstrap',
];
