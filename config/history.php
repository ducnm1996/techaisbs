<?php

return [
    'sources' => ['zalo', 'viber', 'sms', 'call', 'email', 'device', 'network'],
    'network_packages' => [
        'zalo' => ['com.zing.zalo'],
        'viber' => ['com.viber.voip'],
        'email' => ['com.google.android.gm', 'com.microsoft.office.outlook', 'com.samsung.android.email.provider', 'com.android.email'],
    ],
    'retention_days' => (int) env('HISTORY_RETENTION_DAYS', 90),
    'backup_retention_days' => (int) env('BACKUP_RETENTION_DAYS', 7),
    'index_key' => env('HISTORY_INDEX_KEY'),
    'token_days' => (int) env('DEVICE_TOKEN_DAYS', 30),
];
