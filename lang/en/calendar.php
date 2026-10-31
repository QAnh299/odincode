<?php

declare(strict_types=1);

// To-do calendar on the top bar (livewire/task-calendar.blade.php)
return [
    'title'         => 'To-do calendar',
    'month'         => 'Month :month, :year',
    'month_summary' => ':total activities · :todo to do',
    'day_summary'   => ':total activities · :done done',
    'previous'      => 'Previous month',
    'next'          => 'Next month',
    'close'         => 'Close list',
    'open'          => 'Open',
    'overdue'       => 'Overdue',
    'empty'         => 'No activities on this day.',

    'weekday_short' => [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'],
    'weekday'       => [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'],

    'legend' => [
        'todo'    => 'To do',
        'overdue' => 'Overdue',
        'done'    => 'All done',
    ],

    'kind' => [
        'initial'   => 'First contact',
        'recontact' => 'Contact again',
        'quotation' => 'Quotation expiring',
        'care'      => 'Care',
    ],
];
