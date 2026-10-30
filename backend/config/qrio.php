<?php

return [
    // Product identity shown in emails. Kept apart from APP_NAME, which is an internal label.
    'brand' => [
        'name' => env('QRIO_BRAND_NAME', 'Qrio'),
        'support_email' => env('QRIO_SUPPORT_EMAIL'),
    ],

    // Domain encoded into dynamic QR codes. Must route /r/{code} to this API.
    'short_domain' => env('QRIO_SHORT_DOMAIN', env('FRONTEND_URL', 'http://localhost:3000')),

    // Disk for PDFs and logos. Use "s3" in production (any S3-compatible store).
    'files_disk' => env('QRIO_FILES_DISK', 'local'),

    'uploads' => [
        'pdf_mimes' => ['application/pdf'],
        // SVG logos are deliberately excluded: they can carry scripts.
        'logo_mimes' => ['image/png', 'image/jpeg', 'image/webp'],
        // Absolute ceiling regardless of plan, in kilobytes.
        'hard_max_kb' => (int) env('QRIO_UPLOAD_HARD_MAX_KB', 51200),
        'logo_max_kb' => 1024,
    ],

    'billing' => [
        'gateway' => env('BILLING_GATEWAY', 'stripe'),
        // Days a lapsed paid account keeps full management access before reverting to free-plan limits.
        'grace_days' => (int) env('BILLING_GRACE_DAYS', 14),
        'default_currency' => env('BILLING_CURRENCY', 'USD'),
    ],

    'analytics' => [
        // Raw scan events are pruned after this many days; daily aggregates are kept.
        'raw_retention_days' => (int) env('ANALYTICS_RAW_RETENTION_DAYS', 90),
        // Request header carrying an ISO country code set by the CDN or proxy (e.g. CF-IPCountry).
        'country_header' => env('ANALYTICS_COUNTRY_HEADER', 'CF-IPCountry'),
        'region_header' => env('ANALYTICS_REGION_HEADER'),
    ],

    'redirects' => [
        'cache_ttl' => (int) env('REDIRECT_CACHE_TTL', 3600),
        // Hostnames that may never be used as dynamic destinations.
        'blocked_hosts' => array_filter(explode(',', (string) env('REDIRECT_BLOCKED_HOSTS', ''))),
    ],
];
