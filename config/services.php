<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'cpanel' => [
        'host' => env('CPANEL_HOST'),
        'port' => env('CPANEL_PORT', 2083),
        'username' => env('CPANEL_USERNAME'),
        'api_token' => env('CPANEL_API_TOKEN'),
        'domain' => env('CPANEL_DOMAIN'),
        'home_dir' => env('CPANEL_HOME_DIR', '/home'),
    ],

    'xero' => [
        // HMAC key from Xero Developer Portal → your app → Webhooks (required for POST /xero/webhook).
        'webhook_key' => env('XERO_WEBHOOK_KEY'),
        // Optional per-company safety ceiling. Set 0 to disable.
        'request_budget_per_minute' => env('XERO_REQUEST_BUDGET_PER_MINUTE', 50),
        // Throttle invoice imports so a single run doesn't consume excessive resources.
        'invoice_import_page_size' => env('XERO_INVOICE_IMPORT_PAGE_SIZE', 50),
        'invoice_import_max_pages_per_run' => env('XERO_INVOICE_IMPORT_MAX_PAGES_PER_RUN', 5),
        'invoice_import_max_invoices_per_run' => env('XERO_INVOICE_IMPORT_MAX_INVOICES_PER_RUN', 250),
        'invoice_import_max_seconds_per_run' => env('XERO_INVOICE_IMPORT_MAX_SECONDS_PER_RUN', 45),
        'invoice_import_page_delay_ms' => env('XERO_INVOICE_IMPORT_PAGE_DELAY_MS', 250),
        // Throttle quote imports.
        'quote_import_page_size' => env('XERO_QUOTE_IMPORT_PAGE_SIZE', 50),
        'quote_import_max_pages_per_run' => env('XERO_QUOTE_IMPORT_MAX_PAGES_PER_RUN', 3),
        'quote_import_max_quotes_per_run' => env('XERO_QUOTE_IMPORT_MAX_QUOTES_PER_RUN', 150),
        'quote_import_max_seconds_per_run' => env('XERO_QUOTE_IMPORT_MAX_SECONDS_PER_RUN', 35),
        'quote_import_page_delay_ms' => env('XERO_QUOTE_IMPORT_PAGE_DELAY_MS', 300),
        // Throttle purchase order imports.
        'purchase_order_import_page_size' => env('XERO_PURCHASE_ORDER_IMPORT_PAGE_SIZE', 50),
        'purchase_order_import_max_pages_per_run' => env('XERO_PURCHASE_ORDER_IMPORT_MAX_PAGES_PER_RUN', 3),
        'purchase_order_import_max_pos_per_run' => env('XERO_PURCHASE_ORDER_IMPORT_MAX_POS_PER_RUN', 150),
        'purchase_order_import_max_seconds_per_run' => env('XERO_PURCHASE_ORDER_IMPORT_MAX_SECONDS_PER_RUN', 35),
        'purchase_order_import_page_delay_ms' => env('XERO_PURCHASE_ORDER_IMPORT_PAGE_DELAY_MS', 300),
        // Cache contact list to avoid frequent /Contacts pulls in scheduler runs.
        'contacts_cache_ttl_seconds' => env('XERO_CONTACTS_CACHE_TTL_SECONDS', 300),
        // Cap outbound sync batches per scheduler run.
        'invoice_export_max_per_run' => env('XERO_INVOICE_EXPORT_MAX_PER_RUN', 200),
        'quote_export_max_per_run' => env('XERO_QUOTE_EXPORT_MAX_PER_RUN', 150),
        'quote_export_delay_ms' => env('XERO_QUOTE_EXPORT_DELAY_MS', 250),
        'payment_export_max_invoices_per_run' => env('XERO_PAYMENT_EXPORT_MAX_INVOICES_PER_RUN', 120),
        'customer_export_max_per_run' => env('XERO_CUSTOMER_EXPORT_MAX_PER_RUN', 200),
        'product_export_max_per_run' => env('XERO_PRODUCT_EXPORT_MAX_PER_RUN', 200),
        'supplier_export_max_per_run' => env('XERO_SUPPLIER_EXPORT_MAX_PER_RUN', 200),
    ],

];
