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
        // Optional per-company daily ceiling (checked before each API call). Set 0 to disable.
        'request_budget_per_day' => env('XERO_REQUEST_BUDGET_PER_DAY', 0),
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
        // Minutes past each hour when Xero sync jobs may run (comma-separated). Default ~every 15 minutes.
        // Parsed here so `php artisan config:cache` still works (avoid env() outside config files).
        'sync_schedule_base_minutes' => (static function (): array {
            $raw = explode(',', (string) env('XERO_SYNC_BASE_MINUTES', '0,15,30,45'));
            $minutes = [];
            foreach ($raw as $part) {
                $m = (int) trim($part);
                if ($m >= 0 && $m <= 59) {
                    $minutes[] = $m;
                }
            }
            $minutes = array_values(array_unique($minutes));
            sort($minutes);

            return $minutes !== [] ? $minutes : [0, 15, 30, 45];
        })(),
        // Cap outbound sync batches per scheduler run.
        'invoice_export_max_per_run' => env('XERO_INVOICE_EXPORT_MAX_PER_RUN', 200),
        // Invoices per POST to Xero (API allows multiple ACCREC payloads in one request; max 50).
        'invoice_export_batch_size' => env('XERO_INVOICE_EXPORT_BATCH_SIZE', 25),
        'invoice_export_batch_delay_ms' => env('XERO_INVOICE_EXPORT_BATCH_DELAY_MS', 250),
        'quote_export_max_per_run' => env('XERO_QUOTE_EXPORT_MAX_PER_RUN', 150),
        'quote_export_batch_size' => env('XERO_QUOTE_EXPORT_BATCH_SIZE', 25),
        'quote_export_batch_delay_ms' => env('XERO_QUOTE_EXPORT_BATCH_DELAY_MS', 250),
        'payment_export_max_invoices_per_run' => env('XERO_PAYMENT_EXPORT_MAX_INVOICES_PER_RUN', 120),
        'customer_export_max_per_run' => env('XERO_CUSTOMER_EXPORT_MAX_PER_RUN', 200),
        'customer_export_batch_size' => env('XERO_CUSTOMER_EXPORT_BATCH_SIZE', 100),
        'customer_export_batch_delay_ms' => env('XERO_CUSTOMER_EXPORT_BATCH_DELAY_MS', 250),
        'product_export_max_per_run' => env('XERO_PRODUCT_EXPORT_MAX_PER_RUN', 200),
        'supplier_export_max_per_run' => env('XERO_SUPPLIER_EXPORT_MAX_PER_RUN', 200),
        'supplier_export_batch_size' => env('XERO_SUPPLIER_EXPORT_BATCH_SIZE', 100),
        'supplier_export_batch_delay_ms' => env('XERO_SUPPLIER_EXPORT_BATCH_DELAY_MS', 250),
        'purchase_order_export_batch_size' => env('XERO_PURCHASE_ORDER_EXPORT_BATCH_SIZE', 25),
        'purchase_order_export_batch_delay_ms' => env('XERO_PURCHASE_ORDER_EXPORT_BATCH_DELAY_MS', 250),
    ],

    'google_maps' => [
        /** Map ID for Advanced Markers (Cloud Console); defaults to Google's demo ID for dev. Per-company Maps API keys are stored in Company Settings. */
        'map_id' => env('GOOGLE_MAPS_MAP_ID', 'DEMO_MAP_ID'),
    ],

    'dispatch' => [
        'use_real_locations' => env('DISPATCH_USE_REAL_LOCATIONS', true),
        'test_user_locations' => env('DISPATCH_TEST_USER_LOCATIONS', ''),
        /** How far back dispatch map shows technician GPS pings (minutes). Default 8 hours. */
        'location_window_minutes' => env('DISPATCH_LOCATION_WINDOW_MINUTES', 480),
    ],

    'tracking' => [
        /** Per-user GPS ping retention; older rows are deleted on each ingest. */
        'location_retention_hours' => env('TRACKING_LOCATION_RETENTION_HOURS', 24),
    ],

    'push' => [
        'fcm_server_key' => env('FCM_SERVER_KEY'),
        'apns_key_id' => env('APNS_KEY_ID'),
        'apns_team_id' => env('APNS_TEAM_ID'),
        'apns_app_bundle_id' => env('APNS_APP_BUNDLE_ID'),
        'apns_private_key' => env('APNS_PRIVATE_KEY'),
    ],

    'openai' => [
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'quickbooks' => [
        /** Use sandbox API host and sandbox companies from the Intuit Developer portal. */
        'use_sandbox' => env('QUICKBOOKS_USE_SANDBOX', true),
        /** QuickBooks Accounting API minorversion (Intuit recommends staying current; max 75). */
        'minor_version' => env('QUICKBOOKS_MINOR_VERSION', 75),
        /** Sent on Intuit OAuth and QBO API requests per Intuit integration guidance. */
        'user_agent' => env('QUICKBOOKS_USER_AGENT', 'JobCardOnline/1.0 Laravel QuickBooks Client'),
    ],

    'query_api' => [
        // Shared secret authenticating inbound public query submissions from
        // external sites (e.g. the Revamp marketing landing page). Required for
        // the POST /api/queries endpoint to accept requests.
        'key' => env('QUERY_API_KEY'),
    ],

    'revamp' => [
        // Base URL of the Revamp system for pushing status updates back.
        'url' => env('REVAMP_URL'),
        // Shared secret sent as the X-Api-Key header to Revamp's webhook endpoint.
        'api_key' => env('REVAMP_API_KEY'),
    ],

];
