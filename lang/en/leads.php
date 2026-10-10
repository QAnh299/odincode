<?php

declare(strict_types=1);

// Lead list page (livewire/leads/index)
return [
    'title'                  => 'Leads',
    'subtitle'               => 'Track newly added leads and their distribution to the sales teams',
    'subtitle_branch'        => 'Leads of :branch',

    // Action buttons (Distribute not functional yet)
    'add'                    => 'Add lead',
    'import'                 => 'Import Excel',
    'close'                  => 'Close',
    'cancel'                 => 'Cancel',

    // Field names – used by the Add lead popup, the Excel template headers and error messages
    'field' => [
        'full_name'      => 'Full name',
        'phone'          => 'Phone',
        'email'          => 'Email',
        'source_name'    => 'Lead source',
        'source_url'     => 'Source link',
        'contact_method' => 'Contact channel',
        'branch_id'      => 'Branch code',
    ],

    // Add lead popup
    'form' => [
        'title'              => 'Add a new lead',
        'sub'                => 'New leads start as "New" and wait to be distributed to the sales teams.',
        'required_hint'      => 'Fields marked with * are required.',
        'placeholder_name'   => 'e.g. Nguyen Van A',
        'placeholder_phone'  => 'e.g. 0912345678',
        'placeholder_email'  => 'e.g. nguyenvana@example.com',
        'placeholder_source' => 'e.g. Facebook, Website, Workshop…',
        'placeholder_url'    => 'https://…',
        'choose_method'      => '— Choose a channel —',
        'choose_branch'      => '— Choose a branch —',
        'save'               => 'Save lead',
        'saving'             => 'Saving…',
        'created'            => 'Lead :id – :name added.',
        'phone_invalid'      => 'The phone number must have 10 digits and start with 0.',
        'method_invalid'     => 'The contact channel must be one of: :values.',
        'branch_invalid'     => 'The branch code does not exist.',
    ],

    // Import Excel popup + template + result file
    'excel' => [
        'title'          => 'Import leads from Excel',
        'sub'            => 'Valid rows are saved, invalid rows are skipped and explained in the result file.',
        'step_template'  => 'Download the template',
        'step_template_sub' => 'Enter leads in the first sheet and keep the header row. See the "Guide" sheet for valid values.',
        'download_template' => 'Download template (.xlsx)',
        'step_upload'    => 'Upload the file',
        'step_upload_sub' => '.xlsx only, up to 5MB and :max rows.',
        'choose_file'    => 'Choose an Excel file',
        'uploading'      => 'Uploading…',
        'submit'         => 'Import',
        'processing'     => 'Checking data…',
        'file_required'  => 'Please choose an Excel file.',
        'file_type'      => 'Only .xlsx Excel files are accepted.',
        'file_size'      => 'The file must not be larger than 5MB.',

        'error_unreadable' => 'The file could not be read. Please check that it is a valid .xlsx file.',
        'error_template'   => 'Wrong template: the header row does not match. Please download the template and try again.',
        'error_empty'      => 'The file has no data rows.',
        'error_too_many'   => 'The file has more than :max data rows. Please split it.',

        'done_all'       => 'All :count leads imported successfully.',
        'done_partial'   => 'Imported :success/:total leads. :failed rows failed – download the result file for details.',
        'done_none'      => 'Import failed: all :count rows have errors – download the result file for details.',
        'result_title'   => 'Import result',
        'result_total'   => 'Total rows',
        'result_success' => 'Imported',
        'result_failed'  => 'Failed',
        'download_result' => 'Download result file',
        'result_missing' => 'The result file no longer exists, please import again.',
        'import_another' => 'Import another file',

        'sheet_data'     => 'Leads',
        'sheet_guide'    => 'Guide',
        'sheet_result'   => 'Import result',
        'col_line'       => 'Row in file',
        'col_result'     => 'Result',
        'col_lead_id'    => 'Lead code',
        'col_reason'     => 'Error reason',
        'status_ok'      => 'Imported',
        'status_fail'    => 'Failed',

        'guide_column'   => 'Column',
        'guide_note'     => 'How to fill',
        'guide_methods'  => 'Valid contact channels',
        'guide_branches' => 'Valid branch codes',
        'guide' => [
            'full_name'      => 'Required, up to 150 characters.',
            'phone'          => 'Required, 10 digits starting with 0 (may repeat an existing lead – a student registering for another course). Format the cell as Text to keep the leading 0.',
            'email'          => 'Optional, a valid email address.',
            'source_name'    => 'Required, up to 150 characters (e.g. Facebook, Website, Workshop, Referral).',
            'source_url'     => 'Optional, a link starting with http:// or https://.',
            'contact_method' => 'Required: Zalo, Phone or Facebook.',
            'branch_id'      => 'Required, an existing branch code (e.g. BR001).',
        ],
    ],
    'distribute_team'        => 'Distribute leads to sales teams',
    'distribute_salesperson' => 'Distribute leads to salespeople',

    // Statistics
    'stat' => [
        'all'       => 'Total leads',
        'New'       => 'Not distributed',
        'Converted' => 'Converted',
        'rate'      => 'Conversion rate',
    ],

    // Filters
    'filters'                => 'Lead filters',
    'search'                 => 'Search',
    'search_placeholder'     => 'Search by code, name, phone or email…',
    'all'                    => 'All',
    'all_branches'           => 'All branches',
    'from'                   => 'From',
    'to'                     => 'To',
    'reset'                  => 'Clear filters',
    'loading'                => 'Loading…',
    'result_count'           => ':count lead(s) found',
    'empty'                  => 'No leads found',
    'empty_sub'              => 'Try another keyword or remove some filters.',

    // Table columns
    'code'                   => 'Lead code',
    'customer'               => 'Customer',
    'phone'                  => 'Phone',
    'source'                 => 'Source',
    'contact_method'         => 'Contact channel',
    'branch'                 => 'Branch',
    'created_at'             => 'Created',
    'owner'                  => 'Owner',
    'unassigned'             => 'Not distributed',
    'status'                 => 'Status',

    'status_name' => [
        'New'       => 'New',
        'Converted' => 'Converted',
    ],

    'method' => [
        'Zalo'     => 'Zalo',
        'Phone'    => 'Phone',
        'Facebook' => 'Facebook',
    ],
];
