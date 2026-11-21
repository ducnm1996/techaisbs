<?php

return [
    'sources' => ['zalo', 'viber', 'sms', 'call', 'email', 'device'],
    'retention_days' => (int) env('HISTORY_RETENTION_DAYS', 90),
    'backup_retention_days' => (int) env('BACKUP_RETENTION_DAYS', 7),
    'index_key' => env('HISTORY_INDEX_KEY'),
    'token_days' => (int) env('DEVICE_TOKEN_DAYS', 30),
];
