<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Log Channel
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channel for your Elasticsearch client.
    | This allows you to customize the logging output for Elasticsearch, or
    | disable it entirely if you don't want any log events at all.
    | By default, the Elasticsearch channel will log to the same output as
    | the stack channel, which is usually the application's log file.
    |
    */
    'channels' => [
        'elasticsearch' => [
            'driver' => 'stack',
            'channels' => ['stack'],
            'name' => 'elasticsearch',
            'ignore_exceptions' => false,
            'level' => 'info',
        ],
    ],
];
