<?php

return [
    'disk' => env('MEDIA_DISK', 'public'),
    'avif_quality' => (int) env('MEDIA_AVIF_QUALITY', 90),
    'avif_effort' => (int) env('MEDIA_AVIF_EFFORT', 6),
    'webp_quality' => (int) env('MEDIA_WEBP_QUALITY', 90),
    'responsive_webp_quality' => (int) env('MEDIA_RESPONSIVE_WEBP_QUALITY', 80),
    'max_width' => (int) env('MEDIA_MAX_WIDTH', 2000),
    'max_height' => (int) env('MEDIA_MAX_HEIGHT', 2000),
    'creator' => env('MEDIA_IMAGE_CREATOR', env('APP_NAME', 'Prinx Tech')),
    'credit' => env('MEDIA_IMAGE_CREDIT', env('APP_NAME', 'Prinx Tech')),
    'copyright_notice' => env('MEDIA_COPYRIGHT_NOTICE', ''),
    'rights_url' => env('MEDIA_RIGHTS_URL', rtrim((string) env('APP_URL', ''), '/').'/pages/terms'),
];
