<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Seeding Super Admin Awal (Hanya Dibaca via Config)
    |--------------------------------------------------------------------------
    | Jangan pernah menyimpan kredensial default secara hardcoded di repo publik.
    | Atur SEED_SUPER_ADMIN_EMAIL dan SEED_SUPER_ADMIN_PASSWORD di file .env.
    */
    'seed_super_admin_id' => env('SEED_SUPER_ADMIN_ID', 'usr_super_admin'),
    'seed_super_admin_name' => env('SEED_SUPER_ADMIN_NAME', 'Super Admin'),
    'seed_super_admin_email' => env('SEED_SUPER_ADMIN_EMAIL'),
    'seed_super_admin_password' => env('SEED_SUPER_ADMIN_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Seeding Data Dummy (Hanya untuk Local / Testing Non-Produksi)
    |--------------------------------------------------------------------------
    */
    'seed_dummy_data' => (bool) env('SEED_DUMMY_DATA', false),
    'seed_dummy_password' => env('SEED_DUMMY_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Keamanan Transport (HTTPS) & Retensi Backup Database
    |--------------------------------------------------------------------------
    */
    'force_https' => (bool) env('APP_FORCE_HTTPS', env('APP_ENV') === 'production'),
    'backup_retention_days' => max(1, (int) env('DB_BACKUP_RETENTION_DAYS', 14)),
];
