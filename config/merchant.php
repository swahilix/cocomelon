<?php

return [
    'enabled' => (bool) env('GOOGLE_MERCHANT_ENABLED', false),
    'account_id' => env('GOOGLE_MERCHANT_ACCOUNT_ID'),
    'data_source_id' => env('GOOGLE_MERCHANT_DATA_SOURCE_ID'),
    'content_language' => env('GOOGLE_MERCHANT_CONTENT_LANGUAGE', 'en'),
    'feed_label' => env('GOOGLE_MERCHANT_FEED_LABEL', 'TZ'),
    'currency' => env('GOOGLE_MERCHANT_CURRENCY', 'TZS'),
    'country' => env('GOOGLE_MERCHANT_COUNTRY', 'TZ'),
    'shipping_service' => env('GOOGLE_MERCHANT_SHIPPING_SERVICE', 'Standard delivery'),
    'default_shipping_price' => (float) env('GOOGLE_MERCHANT_DEFAULT_SHIPPING_PRICE', 0),
    'local_inventory_enabled' => (bool) env('GOOGLE_MERCHANT_LOCAL_INVENTORY_ENABLED', false),
    'store_code' => env('GOOGLE_MERCHANT_STORE_CODE'),
    'credentials_path' => env('GOOGLE_MERCHANT_CREDENTIALS_PATH', 'storage/app/private/google/service-account.json'),
    'scope' => 'https://www.googleapis.com/auth/content',
    'base_url' => 'https://merchantapi.googleapis.com',
];
