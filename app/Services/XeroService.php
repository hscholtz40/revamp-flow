<?php

namespace App\Services;

use App\Models\XeroSettings;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Payment;
use App\Models\Company;
use App\Models\TaxRate;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\CreditNoteLineItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\XeroSyncState;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class XeroService
{
    private const SYNC_MODULE_CUSTOMERS = 'customers';
    private const SYNC_MODULE_PRODUCTS = 'products';
    private const SYNC_MODULE_INVOICES = 'invoices';
    private const SYNC_MODULE_SUPPLIERS = 'suppliers';
    private const SYNC_MODULE_QUOTES = 'quotes';
    private const SYNC_MODULE_PAYMENTS = 'payments';
    private const SYNC_MODULE_TAX_RATES = 'tax_rates';
    private const SYNC_MODULE_BANK_ACCOUNTS = 'bank_accounts';
    private const SYNC_MODULE_CHART_OF_ACCOUNTS = 'chart_of_accounts';
    private const SYNC_MODULE_CREDIT_NOTES = 'credit_notes';
    private const SYNC_MODULE_PURCHASE_ORDERS = 'purchase_orders';
    private const OUTBOUND_SYNC_MIN_DRIFT_SECONDS = 60;

    private $settings;
    private $baseUrl = 'https://api.xero.com';
    private array $cachedXeroContacts = [];
    private array $cachedXeroAccounts = [];
    private array $xeroInvoiceCache = [];
    private array $xeroProductSkuCache = [];

    public static function getInitialSyncCompletedCacheKey(int $companyId, string $module): string
    {
        return "xero_{$module}_full_sync_completed_company_{$companyId}";
    }

    public static function getInitialSyncCursorCacheKey(int $companyId, string $module): string
    {
        return "xero_{$module}_import_cursor_company_{$companyId}";
    }

    public static function getInitialSyncPaginationCacheKey(int $companyId, string $module): string
    {
        return "xero_{$module}_import_pagination_company_{$companyId}";
    }

    public static function resetInitialSyncStatus(int $companyId, string $module): void
    {
        Cache::forget(self::getInitialSyncCompletedCacheKey($companyId, $module));
        Cache::forget(self::getInitialSyncCursorCacheKey($companyId, $module));
        Cache::forget(self::getInitialSyncPaginationCacheKey($companyId, $module));
    }

    public function __construct(Company $company = null)
    {
        if ($company) {
            $this->settings = XeroSettings::getForCompany($company->id);
        } else {
            $this->settings = XeroSettings::getCurrent();
        }
    }

    /**
     * Check if Xero integration is properly configured
     */
    public function isConfigured(): bool
    {
        return $this->settings->isConfigured() && !$this->settings->isTokenExpired();
    }

    /**
     * Refresh access token if needed
     */
    public function refreshTokenIfNeeded(): bool
    {
        // Check if token needs refresh (with 5-minute buffer)
        if (!$this->settings->isTokenExpired() && !$this->shouldRefreshToken()) {
            return true;
        }

        // If no refresh token, we can't refresh
        if (!$this->settings->refresh_token) {
            Log::warning('Cannot refresh Xero token: no refresh token available', [
                'company_id' => $this->settings->company_id,
                'tenant_id' => $this->settings->tenant_id,
            ]);
            return false;
        }

        Log::info('Refreshing Xero token', [
            'company_id' => $this->settings->company_id,
            'tenant_id' => $this->settings->tenant_id,
            'expires_at' => $this->settings->token_expires_at,
        ]);

        try {
            $response = Http::timeout(30)->asForm()->post('https://identity.xero.com/connect/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $this->settings->client_id,
                'client_secret' => $this->settings->client_secret,
                'refresh_token' => $this->settings->refresh_token,
            ]);

            if (!$response->successful()) {
                $errorBody = $response->body();
                $statusCode = $response->status();
                
                Log::error('Failed to refresh Xero token', [
                    'company_id' => $this->settings->company_id,
                    'status' => $statusCode,
                    'response' => $errorBody,
                ]);

                // Handle specific error cases
                if ($statusCode === 401 || $statusCode === 403) {
                    Log::warning('Xero refresh token is invalid or expired, clearing tokens', [
                        'company_id' => $this->settings->company_id,
                        'status' => $statusCode,
                    ]);
                    $this->clearInvalidTokens();
                }

                return false;
            }

            $tokens = $response->json();
            
            $updateData = [
                'access_token' => $tokens['access_token'],
                'token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
            ];
            
            // Only update refresh_token if it exists in the response
            if (isset($tokens['refresh_token'])) {
                $updateData['refresh_token'] = $tokens['refresh_token'];
            }
            
            $this->settings->update($updateData);

            Log::info('Successfully refreshed Xero token', [
                'company_id' => $this->settings->company_id,
                'new_expires_at' => $updateData['token_expires_at'],
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error refreshing Xero token', [
                'company_id' => $this->settings->company_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Check if token should be refreshed (with buffer time)
     */
    private function shouldRefreshToken(): bool
    {
        if (!$this->settings->token_expires_at) {
            return true;
        }

        // Refresh if token expires within 5 minutes
        return $this->settings->token_expires_at->isBefore(now()->addMinutes(5));
    }

    /**
     * Proactively refresh token for all companies
     */
    public static function refreshAllTokens(): array
    {
        $results = [];
        $allSettings = XeroSettings::getAllCompanies();

        foreach ($allSettings as $settings) {
            if (!$settings->isConfigured()) {
                continue;
            }

            try {
                $xeroService = new XeroService($settings->company);
                $success = $xeroService->refreshTokenIfNeeded();
                
                $results[] = [
                    'company_id' => $settings->company_id,
                    'company_name' => $settings->company->name,
                    'success' => $success,
                ];
            } catch (\Exception $e) {
                Log::error('Error refreshing token for company', [
                    'company_id' => $settings->company_id,
                    'error' => $e->getMessage(),
                ]);
                
                $results[] = [
                    'company_id' => $settings->company_id,
                    'company_name' => $settings->company->name,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get headers for Xero API requests
     */
    private function getHeaders(): array
    {
        if (!$this->refreshTokenIfNeeded()) {
            Log::error('Xero token refresh failed, cannot make API request', [
                'company_id' => $this->settings->company_id,
                'tenant_id' => $this->settings->tenant_id,
                'has_refresh_token' => !empty($this->settings->refresh_token),
                'token_expires_at' => $this->settings->token_expires_at,
            ]);
            
            // Clear invalid tokens to force re-authorization
            $this->clearInvalidTokens();
            
            throw new \Exception('Xero authentication failed. Please re-authorize your Xero connection in the settings.');
        }

        return [
            'Authorization' => 'Bearer ' . $this->settings->access_token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Xero-tenant-id' => $this->settings->tenant_id,
        ];
    }

    /**
     * Get the company from XeroSettings, ensuring it's loaded
     */
    private function getCompany(): Company
    {
        if (!$this->settings->relationLoaded('company')) {
            $this->settings->load('company');
        }
        return $this->settings->company;
    }

    private function isRoundingAdjustmentLineDescription(?string $description): bool
    {
        $normalized = strtolower(trim((string) $description));
        if ($normalized === '') {
            return false;
        }

        return str_contains($normalized, 'rounding adjustment');
    }

    private function resolveXeroTaxTypeForLineItem($lineItem, string $defaultTaxCode, int $companyId): string
    {
        // Null tax in JCO must remain non-taxable in Xero.
        if (empty($lineItem->tax_rate_id)) {
            return 'NONE';
        }

        $lineTaxRate = null;
        if (isset($lineItem->taxRate) && $lineItem->taxRate) {
            $lineTaxRate = $lineItem->taxRate;
        } else {
            $lineTaxRate = TaxRate::where('company_id', $companyId)
                ->where('id', (int) $lineItem->tax_rate_id)
                ->first();
        }

        if ($lineTaxRate) {
            return $lineTaxRate->xero_tax_rate_id ?? $lineTaxRate->code ?? $defaultTaxCode;
        }

        return $defaultTaxCode;
    }

    private function resolveXeroItemCodeForLineItem($lineItem): ?string
    {
        $productId = (int) ($lineItem->product_id ?? 0);
        if ($productId <= 0) {
            return null;
        }

        if (array_key_exists($productId, $this->xeroProductSkuCache)) {
            return $this->xeroProductSkuCache[$productId];
        }

        $product = null;
        if (isset($lineItem->product) && $lineItem->product) {
            $product = $lineItem->product;
        } else {
            $product = Product::query()
                ->select('id', 'sku')
                ->find($productId);
        }

        $sku = trim((string) ($product?->sku ?? ''));
        if ($sku === '') {
            $this->xeroProductSkuCache[$productId] = null;
            return null;
        }

        // Xero item codes are max 30 chars.
        $itemCode = substr($sku, 0, 30);
        $this->xeroProductSkuCache[$productId] = $itemCode;

        return $itemCode;
    }

    private function resolveDefaultRoundingAccountForCompany(int $companyId): ?ChartOfAccount
    {
        // Prefer the active default rounding account, but fall back to any
        // account marked as default rounding to avoid silent 1000 fallback.
        $activeDefault = ChartOfAccount::getDefaultRoundingForCompany($companyId);
        if ($activeDefault) {
            return $activeDefault;
        }

        return ChartOfAccount::where('company_id', $companyId)
            ->where('is_default_rounding', true)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Make HTTP request with rate limiting handling
     * 
     * @param string $method HTTP method (get, post, put, patch, delete)
     * @param string $url Full URL to request
     * @param array $data Optional data for POST/PUT requests
     * @param int $maxRetries Maximum number of retries for rate limit errors
     * @return \Illuminate\Http\Client\Response
     * @throws \Exception
     */
    private function makeXeroRequest(string $method, string $url, array $data = [], int $maxRetries = 2, array $additionalHeaders = []): \Illuminate\Http\Client\Response
    {
        $attempt = 0;
        $timeoutSeconds = max(10, (int) config('services.xero.request_timeout_seconds', 60));
        $connectTimeoutSeconds = max(5, (int) config('services.xero.connect_timeout_seconds', 15));
        
        while ($attempt <= $maxRetries) {
            try {
                $this->assertRequestBudgetWithinLimit($method, $url);
                $headers = array_merge($this->getHeaders(), $additionalHeaders);
                $request = Http::withHeaders($headers)
                    ->connectTimeout($connectTimeoutSeconds)
                    ->timeout($timeoutSeconds);
                $requestStartedAt = microtime(true);
                
                switch (strtolower($method)) {
                    case 'get':
                        $response = $request->get($url);
                        break;
                    case 'post':
                        $response = $request->post($url, $data);
                        break;
                    case 'put':
                        $response = $request->put($url, $data);
                        break;
                    case 'patch':
                        $response = $request->patch($url, $data);
                        break;
                    case 'delete':
                        $response = $request->delete($url);
                        break;
                    default:
                        throw new \Exception("Unsupported HTTP method: {$method}");
                }

                $elapsedMs = (int) ((microtime(true) - $requestStartedAt) * 1000);
                if ($elapsedMs > 15000) {
                    Log::warning('Slow Xero API response detected', [
                        'method' => strtoupper($method),
                        'url' => $url,
                        'status' => $response->status(),
                        'elapsed_ms' => $elapsedMs,
                        'attempt' => $attempt + 1,
                    ]);
                }
                
                // If successful or not a rate limit error, return response
                if ($response->successful() || $response->status() !== 429) {
                    return $response;
                }
                
                // Handle rate limiting (429)
                if ($response->status() === 429) {
                    $retryAfterHeader = (int) ($response->header('Retry-After') ?? 0);
                    // Xero can return Retry-After: 0; treat that as a real backoff window.
                    $retryAfter = $retryAfterHeader > 0 ? $retryAfterHeader : max(10, 10 * ($attempt + 1));
                    $retryAfter += random_int(0, 2);
                    
                    if ($attempt < $maxRetries) {
                        Log::warning('Rate limit hit, waiting before retry', [
                            'attempt' => $attempt + 1,
                            'max_retries' => $maxRetries,
                            'retry_after' => $retryAfter,
                            'url' => $url,
                        ]);
                        
                        // Wait for the specified retry time
                        sleep($retryAfter);
                        $attempt++;
                        continue;
                    } else {
                        // Max retries reached
                        throw new \Exception("Rate limit exceeded (429) after {$maxRetries} retries. Please try again later.");
                    }
                }
                
                return $response;
                
            } catch (ConnectionException $e) {
                if ($attempt >= $maxRetries) {
                    throw new \Exception("Xero API connection timed out after {$maxRetries} retries for {$method} {$url}: " . $e->getMessage());
                }

                $waitTime = min(30, 5 * ($attempt + 1));
                Log::warning('Xero API connection timeout, retrying', [
                    'attempt' => $attempt + 1,
                    'wait_time' => $waitTime,
                    'method' => strtoupper($method),
                    'url' => $url,
                    'timeout_seconds' => $timeoutSeconds,
                    'connect_timeout_seconds' => $connectTimeoutSeconds,
                ]);

                sleep($waitTime);
                $attempt++;
            } catch (\Exception $e) {
                // If it's not a rate limit error, throw immediately
                if (!str_contains($e->getMessage(), '429') && !str_contains($e->getMessage(), 'rate limit')) {
                    throw $e;
                }
                
                // If max retries reached, throw
                if ($attempt >= $maxRetries) {
                    throw $e;
                }
                
                // Otherwise, wait and retry
                $waitTime = 60 * ($attempt + 1); // Exponential backoff
                Log::warning('Rate limit error, waiting before retry', [
                    'attempt' => $attempt + 1,
                    'wait_time' => $waitTime,
                    'url' => $url,
                ]);
                
                sleep($waitTime);
                $attempt++;
            }
        }
        
        throw new \Exception("Failed to make Xero API request after {$maxRetries} retries");
    }

    private function assertRequestBudgetWithinLimit(string $method, string $url): void
    {
        $budgetPerMinute = max(0, (int) config('services.xero.request_budget_per_minute', 0));
        $companyId = $this->settings->company_id ?? null;
        if (!$companyId) {
            return;
        }

        $requestCount = $this->trackRequestMetric($companyId, $method, $url);
        if ($budgetPerMinute === 0) {
            return;
        }

        if ($requestCount > $budgetPerMinute) {
            throw new \Exception(
                "Local Xero request budget exceeded ({$budgetPerMinute}/minute) for company {$companyId}. Last request: "
                . strtoupper($method) . " {$url}"
            );
        }
    }

    private function trackRequestMetric(int $companyId, string $method, string $url): int
    {
        $now = now();
        $minuteWindow = $now->format('YmdHi');
        $dayWindow = $now->format('Ymd');
        $endpoint = $this->normalizeXeroEndpoint($method, $url);
        $endpointHash = sha1($endpoint);

        $counterKey = "xero_request_budget_company_{$companyId}_{$minuteWindow}_total";
        $requestCount = Cache::increment($counterKey);
        if ($requestCount === 1) {
            Cache::put($counterKey, 1, now()->addSeconds(120));
        }

        $dayTotalKey = "xero_request_usage_company_{$companyId}_{$dayWindow}_total";
        $dayTotal = Cache::increment($dayTotalKey);
        if ($dayTotal === 1) {
            Cache::put($dayTotalKey, 1, now()->endOfDay()->addHour());
        }

        $endpointDayCountKey = "xero_request_usage_company_{$companyId}_{$dayWindow}_endpoint_{$endpointHash}";
        $endpointDayCount = Cache::increment($endpointDayCountKey);
        if ($endpointDayCount === 1) {
            Cache::put($endpointDayCountKey, 1, now()->endOfDay()->addHour());
        }

        $endpointMapKey = "xero_request_usage_company_{$companyId}_{$dayWindow}_endpoint_map";
        $endpointMap = Cache::get($endpointMapKey, []);
        if (!isset($endpointMap[$endpointHash])) {
            $endpointMap[$endpointHash] = $endpoint;
            Cache::put($endpointMapKey, $endpointMap, now()->endOfDay()->addHour());
        }

        return $requestCount;
    }

    private function normalizeXeroEndpoint(string $method, string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? $url;
        $path = preg_replace('#^/api\.xro/2\.0/#', '', (string) $path);
        $path = trim((string) $path, '/');
        if ($path === '') {
            $path = 'root';
        }

        return strtoupper($method) . ' ' . $path;
    }

    public static function getRequestUsageSnapshot(int $companyId, int $topLimit = 5): array
    {
        $now = now();
        $minuteWindow = $now->format('YmdHi');
        $dayWindow = $now->format('Ymd');

        $minuteTotal = (int) Cache::get("xero_request_budget_company_{$companyId}_{$minuteWindow}_total", 0);
        $dayTotal = (int) Cache::get("xero_request_usage_company_{$companyId}_{$dayWindow}_total", 0);

        $endpointMap = Cache::get("xero_request_usage_company_{$companyId}_{$dayWindow}_endpoint_map", []);
        $endpointCounts = [];
        if (is_array($endpointMap)) {
            foreach ($endpointMap as $hash => $endpoint) {
                $count = (int) Cache::get("xero_request_usage_company_{$companyId}_{$dayWindow}_endpoint_{$hash}", 0);
                if ($count > 0) {
                    $endpointCounts[] = [
                        'endpoint' => $endpoint,
                        'count' => $count,
                    ];
                }
            }
        }

        usort($endpointCounts, fn ($a, $b) => $b['count'] <=> $a['count']);

        return [
            'minute_total' => $minuteTotal,
            'day_total' => $dayTotal,
            'budget_per_minute' => max(0, (int) config('services.xero.request_budget_per_minute', 0)),
            'top_endpoints' => array_slice($endpointCounts, 0, max(1, $topLimit)),
            'captured_at' => $now->toIso8601String(),
        ];
    }

    /**
     * Clear invalid tokens to force re-authorization
     */
    private function clearInvalidTokens(): void
    {
        Log::warning('Clearing invalid Xero tokens to force re-authorization', [
            'company_id' => $this->settings->company_id,
            'tenant_id' => $this->settings->tenant_id,
        ]);

        $this->settings->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
        ]);
    }

    /**
     * Sync customers to Xero
     */
    public function syncCustomersToXero(Company $company = null): array
    {
        if (!$this->settings->sync_customers_to_xero) {
            return ['skipped' => true, 'message' => 'Customer sync to Xero is disabled'];
        }

        $currentCompany = $this->getCompany();
        $results = [];
        $maxPerRun = max(1, (int) config('services.xero.customer_export_max_per_run', 200));

        $customersQuery = Customer::where('company_id', $currentCompany->id)
            ->where(function ($query) {
                $query->whereNull('xero_contact_id')
                    ->orWhereNull('xero_updated_at')
                    ->orWhereColumn('updated_at', '>', 'xero_updated_at');
            })
            ->orderByDesc('updated_at');

        if (!$customersQuery->exists()) {
            return ['skipped' => true, 'message' => 'No customer changes to sync to Xero'];
        }

        $xeroContactsMap = [];
        // If customer import-from-Xero is disabled, keep protective comparison fetch.
        if (!$this->settings->sync_customers_from_xero) {
            try {
                $xeroContacts = $this->fetchXeroContacts(self::SYNC_MODULE_CUSTOMERS);
                foreach ($xeroContacts as $xeroContact) {
                    $xeroContactsMap[$xeroContact['ContactID']] = $xeroContact;
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch Xero contacts for comparison', ['error' => $e->getMessage()]);
            }
        }

        $customersToSync = [];
        $batchSize = 100;

        $customers = $customersQuery->take($maxPerRun)->get();
        foreach ($customers as $customer) {
            try {
                if ($customer->xero_contact_id && isset($xeroContactsMap[$customer->xero_contact_id])) {
                    $xeroCustomer = $xeroContactsMap[$customer->xero_contact_id];
                    
                    if ($this->isXeroRecordNewerThanLocal($customer, $xeroCustomer)) {
                        $this->updateCustomerFromXero($customer, $xeroCustomer);
                        $results[] = [
                            'customer_id' => $customer->id,
                            'customer_name' => $customer->name,
                            'status' => 'updated_from_xero',
                            'message' => 'Customer updated from Xero (Xero was newer)',
                        ];
                        continue;
                    }
                }
                
                $contactData = [
                    'Name' => $customer->name,
                    'EmailAddress' => $customer->email,
                    'Phones' => $customer->phone ? [
                        ['PhoneType' => 'DEFAULT', 'PhoneNumber' => $customer->phone]
                    ] : [],
                    'Addresses' => $customer->address ? [
                        ['AddressType' => 'STREET', 'AddressLine1' => $customer->address]
                    ] : [],
                ];
                
                if ($customer->account_code) {
                    $contactData['AccountNumber'] = $customer->account_code;
                }

                if ($customer->vat_number) {
                    $contactData['TaxNumber'] = $customer->vat_number;
                }
                
                if ($customer->xero_contact_id) {
                    $contactData['ContactID'] = $customer->xero_contact_id;
                }
                
                $customersToSync[] = [
                    'customer' => $customer,
                    'contactData' => $contactData,
                ];
                
                if (count($customersToSync) >= $batchSize) {
                    $batchResults = $this->batchCreateOrUpdateCustomersInXero($customersToSync);
                    $results = array_merge($results, $batchResults);
                    $customersToSync = [];
                    sleep(1);
                }
            } catch (\Exception $e) {
                $results[] = [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        if (!empty($customersToSync)) {
            $batchResults = $this->batchCreateOrUpdateCustomersInXero($customersToSync);
            $results = array_merge($results, $batchResults);
        }

        return $results;
    }

    /**
     * Batch create or update customers in Xero
     * 
     * @param array $customersData Array of ['customer' => Customer, 'contactData' => array]
     * @return array Results array
     */
    private function batchCreateOrUpdateCustomersInXero(array $customersData): array
    {
        if (empty($customersData)) {
            return [];
        }

        $results = [];
        $contactsData = array_map(fn($item) => $item['contactData'], $customersData);

        try {
            $response = $this->makeXeroRequest('post', $this->baseUrl . '/api.xro/2.0/Contacts', [
                'Contacts' => $contactsData
            ]);

            if (!$response->successful()) {
                $errorBody = $response->body();
                throw new \Exception('Failed to batch create/update customers in Xero: ' . $errorBody);
            }

            $result = $response->json();
            $xeroContacts = $result['Contacts'] ?? [];
            $elements = $result['Elements'] ?? [];

            // Create a map of ContactID to Xero contact for matching
            $xeroContactsMap = [];
            foreach ($xeroContacts as $xeroContact) {
                if (isset($xeroContact['ContactID'])) {
                    // Try to match by ContactID first (for updates)
                    $xeroContactsMap[$xeroContact['ContactID']] = $xeroContact;
                    // Also match by Name (for new contacts)
                    if (isset($xeroContact['Name'])) {
                        $xeroContactsMap[strtolower($xeroContact['Name'])] = $xeroContact;
                    }
                }
            }

            // Map results back to customers
            foreach ($customersData as $index => $item) {
                $customer = $item['customer'];
                $contactData = $item['contactData'];
                
                // Try to find matching Xero contact
                $xeroContact = null;
                
                // First try by ContactID if we're updating
                if ($customer->xero_contact_id && isset($xeroContactsMap[$customer->xero_contact_id])) {
                    $xeroContact = $xeroContactsMap[$customer->xero_contact_id];
                }
                // Then try by name
                elseif (isset($contactData['Name']) && isset($xeroContactsMap[strtolower($contactData['Name'])])) {
                    $xeroContact = $xeroContactsMap[strtolower($contactData['Name'])];
                }
                // Fallback to index-based matching
                elseif (isset($xeroContacts[$index])) {
                    $xeroContact = $xeroContacts[$index];
                }
                // Check elements array for errors
                elseif (isset($elements[$index])) {
                    $element = $elements[$index];
                    if (isset($element['ValidationErrors']) && !empty($element['ValidationErrors'])) {
                        $errorMessages = collect($element['ValidationErrors'])->pluck('Message')->implode(', ');
                        $results[] = [
                            'customer_id' => $customer->id,
                            'customer_name' => $customer->name,
                            'status' => 'error',
                            'error' => $errorMessages,
                        ];
                        continue;
                    }
                    // If element exists but no ContactID, it might be in Contacts array
                    if (isset($element['ContactID']) && isset($xeroContactsMap[$element['ContactID']])) {
                        $xeroContact = $xeroContactsMap[$element['ContactID']];
                    }
                }

                if ($xeroContact && isset($xeroContact['ContactID'])) {
                    $customer->update([
                        'xero_contact_id' => $xeroContact['ContactID'],
                        ...$this->getXeroTimestamps($xeroContact),
                    ]);
                    $this->alignLocalUpdatedAtWithXero($customer);
                    
                    $results[] = [
                        'customer_id' => $customer->id,
                        'customer_name' => $customer->name,
                        'status' => 'success',
                        'xero_contact_id' => $xeroContact['ContactID'],
                    ];
                } else {
                    // If we couldn't match, check for validation errors
                    $errorMessages = 'Failed to create/update customer in Xero';
                    if (isset($elements[$index]['ValidationErrors'])) {
                        $errorMessages = collect($elements[$index]['ValidationErrors'])->pluck('Message')->implode(', ');
                    }
                    
                    $results[] = [
                        'customer_id' => $customer->id,
                        'customer_name' => $customer->name,
                        'status' => 'error',
                        'error' => $errorMessages,
                    ];
                }
            }
        } catch (\Exception $e) {
            // If batch fails, fall back to individual requests
            Log::warning('Batch customer sync failed, falling back to individual requests', [
                'error' => $e->getMessage(),
                'batch_size' => count($customersData),
            ]);

            foreach ($customersData as $item) {
                try {
                    $customer = $item['customer'];
                    $xeroCustomer = $this->createOrUpdateCustomerInXero($customer);
                    
                    if (isset($xeroCustomer['ContactID'])) {
                        $customer->update([
                            'xero_contact_id' => $xeroCustomer['ContactID'],
                            ...$this->getXeroTimestamps($xeroCustomer),
                        ]);
                        $this->alignLocalUpdatedAtWithXero($customer);
                    }
                    
                    $results[] = [
                        'customer_id' => $customer->id,
                        'customer_name' => $customer->name,
                        'status' => 'success',
                        'xero_contact_id' => $xeroCustomer['ContactID'] ?? null,
                    ];
                } catch (\Exception $individualError) {
                    $results[] = [
                        'customer_id' => $item['customer']->id,
                        'customer_name' => $item['customer']->name,
                        'status' => 'error',
                        'error' => $individualError->getMessage(),
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Sync customers from Xero (import existing Xero customers to app)
     */
    public function syncCustomersFromXero(Company $company = null, bool $forceFullFetch = false): array
    {
        if (!$this->settings->sync_customers_from_xero) {
            return ['skipped' => true, 'message' => 'Customer sync from Xero is disabled'];
        }

        $currentCompany = $this->getCompany();
        $results = [];
        $syncStartedAt = now();

        try {
            $xeroContacts = $this->fetchXeroContacts(self::SYNC_MODULE_CUSTOMERS, $forceFullFetch);
            
            foreach ($xeroContacts as $xeroContact) {
                try {
                    // Skip if contact doesn't have required fields
                    if (empty($xeroContact['Name'])) {
                        continue;
                    }

                    // Check if customer already exists in app by Xero contact ID
                    $existingCustomer = Customer::where('company_id', $currentCompany->id)
                        ->where('xero_contact_id', $xeroContact['ContactID'])
                        ->first();

                    // If not found by Xero ID, check by name (case-insensitive)
                    if (!$existingCustomer) {
                        $existingCustomer = Customer::where('company_id', $currentCompany->id)
                            ->whereRaw('LOWER(name) = ?', [strtolower($xeroContact['Name'])])
                            ->whereNull('xero_contact_id') // Only match customers that don't have a Xero ID yet
                            ->first();
                    }

                    if ($existingCustomer) {
                        if (!$this->xeroUpdatedAtChanged($existingCustomer, $xeroContact)) {
                            $this->syncContactPersonsFromXero($xeroContact, $currentCompany->id, $existingCustomer->id, null);
                            $results[] = [
                                'customer_id' => $existingCustomer->id,
                                'customer_name' => $xeroContact['Name'],
                                'status' => 'skipped',
                                'message' => 'Customer unchanged in Xero',
                            ];
                            continue;
                        }
                        $this->updateCustomerFromXero($existingCustomer, $xeroContact);
                        $this->syncContactPersonsFromXero($xeroContact, $currentCompany->id, $existingCustomer->id, null);
                        $results[] = [
                            'customer_id' => $existingCustomer->id,
                            'customer_name' => $xeroContact['Name'],
                            'status' => 'updated',
                            'message' => 'Customer updated from Xero and linked to existing customer',
                        ];
                    } else {
                        // Create new customer
                        $customer = $this->createCustomerFromXero($xeroContact, $currentCompany);
                        $this->syncContactPersonsFromXero($xeroContact, $currentCompany->id, $customer->id, null);
                        $results[] = [
                            'customer_id' => $customer->id,
                            'customer_name' => $xeroContact['Name'],
                            'status' => 'created',
                            'message' => 'Customer imported from Xero',
                        ];
                    }
                } catch (\Exception $e) {
                    $results[] = [
                        'customer_name' => $xeroContact['Name'] ?? 'Unknown',
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to sync customers from Xero', [
                'company_id' => $currentCompany->id,
                'error' => $e->getMessage(),
            ]);
            
            return ['skipped' => true, 'message' => 'Failed to sync customers from Xero: ' . $e->getMessage()];
        }

        $this->recordSyncDatetimeForModule(self::SYNC_MODULE_CUSTOMERS, $syncStartedAt);

        return $results;
    }

    /**
     * Create or update customer in Xero (individual request - used as fallback)
     */
    private function createOrUpdateCustomerInXero(Customer $customer): array
    {
        $contactData = [
            'Name' => $customer->name,
            'EmailAddress' => $customer->email,
            'Phones' => $customer->phone ? [
                [
                    'PhoneType' => 'DEFAULT',
                    'PhoneNumber' => $customer->phone,
                ]
            ] : [],
            'Addresses' => $customer->address ? [
                [
                    'AddressType' => 'STREET',
                    'AddressLine1' => $customer->address,
                ]
            ] : [],
        ];
        
        // Add AccountNumber if account_code exists
        if ($customer->account_code) {
            $contactData['AccountNumber'] = $customer->account_code;
        }

        // Add TaxNumber if vat_number exists
        if ($customer->vat_number) {
            $contactData['TaxNumber'] = $customer->vat_number;
        }

        // Add ContactID if customer already exists in Xero
        if ($customer->xero_contact_id) {
            $contactData['ContactID'] = $customer->xero_contact_id;
        }

        $response = $this->makeXeroRequest('post', $this->baseUrl . '/api.xro/2.0/Contacts', [
            'Contacts' => [$contactData]
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create/update customer in Xero: ' . $response->body());
        }

        $result = $response->json();
        return $result['Contacts'][0];
    }

    /**
     * Sync products to Xero
     */
    public function syncProductsToXero(Company $company = null): array
    {
        if (!$this->settings->sync_products_to_xero) {
            return ['skipped' => true, 'message' => 'Product sync to Xero is disabled'];
        }

        $currentCompany = $this->getCompany();
        $results = [];
        $maxPerRun = max(1, (int) config('services.xero.product_export_max_per_run', 200));

        $productsToSync = [];
        $batchSize = 100;
        $products = Product::where('company_id', $currentCompany->id)
            ->where(function ($query) {
                $query->whereNull('xero_item_id')
                    ->orWhereNull('xero_updated_at')
                    ->orWhereColumn('updated_at', '>', 'xero_updated_at');
            })
            ->orderByDesc('updated_at')
            ->limit($maxPerRun)
            ->get();

        foreach ($products as $product) {
            try {
                $itemData = [
                    'Code' => $this->resolveXeroProductCode($product),
                    'Name' => $product->name,
                    'Description' => $product->description,
                    'UnitPrice' => $product->price,
                    'SalesDetails' => [
                        'UnitPrice' => $product->price,
                    ],
                ];

                if ($product->xero_item_id) {
                    $itemData['ItemID'] = $product->xero_item_id;
                }

                $productsToSync[] = [
                    'product' => $product,
                    'itemData' => $itemData,
                ];

                if (count($productsToSync) >= $batchSize) {
                    $batchResults = $this->batchCreateOrUpdateProductsInXero($productsToSync);
                    $results = array_merge($results, $batchResults);
                    $productsToSync = [];
                    sleep(1);
                }
            } catch (\Exception $e) {
                $results[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        if (!empty($productsToSync)) {
            $batchResults = $this->batchCreateOrUpdateProductsInXero($productsToSync);
            $results = array_merge($results, $batchResults);
        }

        return $results;
    }

    /**
     * Batch create or update products in Xero
     * 
     * @param array $productsData Array of ['product' => Product, 'itemData' => array]
     * @return array Results array
     */
    private function batchCreateOrUpdateProductsInXero(array $productsData): array
    {
        if (empty($productsData)) {
            return [];
        }

        $results = [];
        $itemsData = array_map(fn($item) => $item['itemData'], $productsData);

        try {
            $response = $this->makeXeroRequest('post', $this->baseUrl . '/api.xro/2.0/Items', [
                'Items' => $itemsData
            ]);

            if (!$response->successful()) {
                $errorBody = $response->body();
                throw new \Exception('Failed to batch create/update products in Xero: ' . $errorBody);
            }

            $result = $response->json();
            $xeroItems = $result['Items'] ?? [];
            $elements = $result['Elements'] ?? [];

            // Create a map of ItemID to Xero item for matching
            $xeroItemsMap = [];
            foreach ($xeroItems as $xeroItem) {
                if (isset($xeroItem['ItemID'])) {
                    // Try to match by ItemID first (for updates)
                    $xeroItemsMap[$xeroItem['ItemID']] = $xeroItem;
                    // Also match by Code/SKU (for new items)
                    if (isset($xeroItem['Code'])) {
                        $xeroItemsMap[strtolower($xeroItem['Code'])] = $xeroItem;
                    }
                    // Also match by Name
                    if (isset($xeroItem['Name'])) {
                        $xeroItemsMap[strtolower($xeroItem['Name'])] = $xeroItem;
                    }
                }
            }

            // Map results back to products
            foreach ($productsData as $index => $item) {
                $product = $item['product'];
                $itemData = $item['itemData'];
                
                // Try to find matching Xero item
                $xeroItem = null;
                
                // First try by ItemID if we're updating
                if ($product->xero_item_id && isset($xeroItemsMap[$product->xero_item_id])) {
                    $xeroItem = $xeroItemsMap[$product->xero_item_id];
                }
                // Then try by SKU/Code
                elseif (isset($itemData['Code']) && isset($xeroItemsMap[strtolower($itemData['Code'])])) {
                    $xeroItem = $xeroItemsMap[strtolower($itemData['Code'])];
                }
                // Then try by Name
                elseif (isset($itemData['Name']) && isset($xeroItemsMap[strtolower($itemData['Name'])])) {
                    $xeroItem = $xeroItemsMap[strtolower($itemData['Name'])];
                }
                // Fallback to index-based matching
                elseif (isset($xeroItems[$index])) {
                    $xeroItem = $xeroItems[$index];
                }
                // Check elements array for errors
                elseif (isset($elements[$index])) {
                    $element = $elements[$index];
                    if (isset($element['ValidationErrors']) && !empty($element['ValidationErrors'])) {
                        $errorMessages = collect($element['ValidationErrors'])->pluck('Message')->implode(', ');
                        $results[] = [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'status' => 'error',
                            'error' => $errorMessages,
                        ];
                        continue;
                    }
                    // If element exists but no ItemID, it might be in Items array
                    if (isset($element['ItemID']) && isset($xeroItemsMap[$element['ItemID']])) {
                        $xeroItem = $xeroItemsMap[$element['ItemID']];
                    }
                }

                if ($xeroItem && isset($xeroItem['ItemID'])) {
                    $product->update([
                        'xero_item_id' => $xeroItem['ItemID'],
                        ...$this->getXeroTimestamps($xeroItem),
                    ]);
                    $this->alignLocalUpdatedAtWithXero($product);
                    
                    $results[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'status' => 'success',
                        'xero_item_id' => $xeroItem['ItemID'],
                    ];
                } else {
                    // If we couldn't match, check for validation errors
                    $errorMessages = 'Failed to create/update product in Xero';
                    if (isset($elements[$index]['ValidationErrors'])) {
                        $errorMessages = collect($elements[$index]['ValidationErrors'])->pluck('Message')->implode(', ');
                    }
                    
                    $results[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'status' => 'error',
                        'error' => $errorMessages,
                    ];
                }
            }
        } catch (\Exception $e) {
            // If batch fails, fall back to individual requests
            Log::warning('Batch product sync failed, falling back to individual requests', [
                'error' => $e->getMessage(),
                'batch_size' => count($productsData),
            ]);

            foreach ($productsData as $item) {
                try {
                    $product = $item['product'];
                    $xeroItem = $this->createOrUpdateProductInXero($product);
                    
                    if (isset($xeroItem['ItemID'])) {
                        $product->update([
                            'xero_item_id' => $xeroItem['ItemID'],
                            ...$this->getXeroTimestamps($xeroItem),
                        ]);
                        $this->alignLocalUpdatedAtWithXero($product);
                    }
                    
                    $results[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'status' => 'success',
                        'xero_item_id' => $xeroItem['ItemID'] ?? null,
                    ];
                } catch (\Exception $individualError) {
                    $results[] = [
                        'product_id' => $item['product']->id,
                        'product_name' => $item['product']->name,
                        'status' => 'error',
                        'error' => $individualError->getMessage(),
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Sync products from Xero (import existing Xero products to app)
     */
    public function syncProductsFromXero(Company $company = null): array
    {
        if (!$this->settings->sync_products_from_xero) {
            return ['skipped' => true, 'message' => 'Product sync from Xero is disabled'];
        }

        $currentCompany = $this->getCompany();
        $results = [];
        $syncStartedAt = now();

        try {
            $lastSync = $this->getLastSyncDatetimeForModule(self::SYNC_MODULE_PRODUCTS);

            $response = $this->makeXeroRequest(
                'get',
                $this->baseUrl . '/api.xro/2.0/Items',
                [],
                2,
                $this->buildIfModifiedSinceHeader($lastSync)
            );

            if (!$response->successful()) {
                $errorBody = $response->body();
                $statusCode = $response->status();
                
                // Handle authentication errors specifically
                if ($statusCode === 403 && str_contains($errorBody, 'AuthenticationUnsuccessful')) {
                    Log::error('Xero authentication failed during product sync', [
                        'company_id' => $currentCompany->id,
                        'status' => $statusCode,
                        'response' => $errorBody,
                    ]);
                    
                    $this->clearInvalidTokens();
                    
                    return ['skipped' => true, 'message' => 'Xero authentication failed. Please re-authorize your Xero connection in the settings.'];
                }
                
                throw new \Exception('Failed to fetch items from Xero: ' . $errorBody);
            }

            $xeroItems = $response->json()['Items'] ?? [];
            
            foreach ($xeroItems as $xeroItem) {
                try {
                    // Skip if item doesn't have required fields
                    if (empty($xeroItem['Name'])) {
                        continue;
                    }

                    // Check if product already exists in app by Xero item ID
                    $existingProduct = Product::where('company_id', $currentCompany->id)
                        ->where('xero_item_id', $xeroItem['ItemID'])
                        ->first();

                    // If not found by Xero ID, check by name (case-insensitive)
                    if (!$existingProduct) {
                        $existingProduct = Product::where('company_id', $currentCompany->id)
                            ->whereRaw('LOWER(name) = ?', [strtolower($xeroItem['Name'])])
                            ->whereNull('xero_item_id') // Only match products that don't have a Xero ID yet
                            ->first();
                    }

                    if ($existingProduct) {
                        if (!$this->xeroUpdatedAtChanged($existingProduct, $xeroItem)) {
                            $results[] = [
                                'product_id' => $existingProduct->id,
                                'product_name' => $xeroItem['Name'],
                                'status' => 'skipped',
                                'message' => 'Product unchanged in Xero',
                            ];
                            continue;
                        }
                        $this->updateProductFromXero($existingProduct, $xeroItem);
                        $results[] = [
                            'product_id' => $existingProduct->id,
                            'product_name' => $xeroItem['Name'],
                            'status' => 'updated',
                            'message' => 'Product updated from Xero and linked to existing product',
                        ];
                    } else {
                        // Create new product
                        $product = $this->createProductFromXero($xeroItem, $currentCompany);
                        $results[] = [
                            'product_id' => $product->id,
                            'product_name' => $xeroItem['Name'],
                            'status' => 'created',
                            'message' => 'Product imported from Xero',
                        ];
                    }
                } catch (\Exception $e) {
                    $results[] = [
                        'product_name' => $xeroItem['Name'] ?? 'Unknown',
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to sync products from Xero', [
                'company_id' => $currentCompany->id,
                'error' => $e->getMessage(),
            ]);
            
            return ['skipped' => true, 'message' => 'Failed to sync products from Xero: ' . $e->getMessage()];
        }

        $this->recordSyncDatetimeForModule(self::SYNC_MODULE_PRODUCTS, $syncStartedAt);

        return $results;
    }

    /**
     * Create or update product in Xero
     */
    /**
     * Create or update product in Xero (individual request - used as fallback)
     */
    private function createOrUpdateProductInXero(Product $product): array
    {
        $itemData = [
            'Code' => $this->resolveXeroProductCode($product),
            'Name' => $product->name,
            'Description' => $product->description,
            'UnitPrice' => $product->price,
            'SalesDetails' => [
                'UnitPrice' => $product->price,
            ],
        ];

        // Add ItemID if product already exists in Xero
        if ($product->xero_item_id) {
            $itemData['ItemID'] = $product->xero_item_id;
        }

        $response = $this->makeXeroRequest('post', $this->baseUrl . '/api.xro/2.0/Items', [
            'Items' => [$itemData]
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create/update product in Xero: ' . $response->body());
        }

        $result = $response->json();
        return $result['Items'][0];
    }

    private function resolveXeroProductCode(Product $product): string
    {
        $rawCode = trim((string) ($product->sku ?? ''));
        if ($rawCode === '') {
            $rawCode = 'JCO-' . $product->company_id . '-' . $product->id;
        }

        $normalized = strtoupper((string) preg_replace('/[^A-Z0-9._-]/', '', strtoupper($rawCode)));
        if ($normalized === '') {
            $normalized = 'JCO-' . $product->company_id . '-' . $product->id;
        }

        return substr($normalized, 0, 30);
    }

    /**
     * Sync invoices to Xero
     */
    public function syncInvoicesToXero(Company $company = null): array
    {
        if (!$this->settings->sync_invoices_to_xero) {
            return ['skipped' => true, 'message' => 'Invoice sync to Xero is disabled'];
        }

        $currentCompany = $this->getCompany();

        $maxInvoicesPerRun = max(1, (int) config('services.xero.invoice_export_max_per_run', 200));
        $createCandidates = Invoice::where('company_id', $currentCompany->id)
            ->whereNull('xero_invoice_id')
            ->orderByDesc('updated_at')
            ->limit($maxInvoicesPerRun)
            ->with(['customer', 'lineItems'])
            ->get();

        $remainingSlots = max(0, $maxInvoicesPerRun - $createCandidates->count());
        $updateCandidates = collect();
        if ($remainingSlots > 0) {
            $updateCandidates = Invoice::where('company_id', $currentCompany->id)
                ->whereNotNull('xero_invoice_id')
                ->whereNotNull('xero_updated_at')
                ->whereColumn('updated_at', '>', 'xero_updated_at')
                ->orderByDesc('updated_at')
                ->limit($maxInvoicesPerRun * 3)
                ->with(['customer', 'lineItems'])
                ->get()
                ->filter(fn (Invoice $invoice) => $this->hasSignificantLocalSyncDrift($invoice->updated_at, $invoice->xero_updated_at))
                ->take($remainingSlots)
                ->values();
        }

        $invoices = $createCandidates->concat($updateCandidates)->values();
        $results = [];
        
        Log::info('Starting invoice sync to Xero', [
            'company_id' => $currentCompany->id,
            'invoice_count' => $invoices->count(),
            'create_candidates' => $createCandidates->count(),
            'update_candidates' => $updateCandidates->count(),
            'filter_applied' => 'creates_first_then_updates_with_min_drift',
            'max_invoices_per_run' => $maxInvoicesPerRun,
        ]);

        foreach ($invoices as $index => $invoice) {
            try {
                if ($index > 0) {
                    sleep(1);
                }

                $hasLocalChangesForExport = !$invoice->xero_updated_at
                    || $this->hasSignificantLocalSyncDrift($invoice->updated_at, $invoice->xero_updated_at);
                
                // Avoid pre-read requests to Xero for each invoice. We rely on write responses
                // and periodic inbound sync/webhooks to keep local and remote state aligned.

                Log::info('Attempting outbound invoice sync to Xero', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'xero_invoice_id' => $invoice->xero_invoice_id,
                    'updated_at' => optional($invoice->updated_at)?->toDateTimeString(),
                    'xero_updated_at' => optional($invoice->xero_updated_at)?->toDateTimeString(),
                    'has_local_changes_for_export' => $hasLocalChangesForExport,
                ]);
                
                $xeroInvoice = $this->createOrUpdateInvoiceInXero($invoice);
                if (isset($xeroInvoice['InvoiceID'])) {
                    $invoice->update([
                        'xero_invoice_id' => $xeroInvoice['InvoiceID'],
                        ...$this->getXeroTimestamps($xeroInvoice),
                    ]);
                    $this->alignLocalUpdatedAtWithXero($invoice);
                }
                $results[] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'status' => 'success',
                    'xero_invoice_id' => $xeroInvoice['InvoiceID'] ?? null,
                ];
                Log::info('Invoice exported to Xero', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'xero_invoice_id' => $xeroInvoice['InvoiceID'] ?? null,
                    'decision_reason' => 'exported',
                ]);

                // If this invoice is already fully paid locally, sync payment(s) immediately
                // so Xero receives invoice + payment in the same sync run.
                if ($invoice->isFullyPaid()) {
                    try {
                        $paymentResults = $this->syncPaymentsToXero($invoice, $xeroInvoice);
                        $successCount = collect($paymentResults)->where('status', 'success')->count();

                        if ($successCount > 0) {
                            $results[] = [
                                'invoice_id' => $invoice->id,
                                'invoice_number' => $invoice->invoice_number,
                                'status' => 'payments_synced',
                                'message' => "Synced {$successCount} payment(s) to Xero immediately after invoice export",
                            ];
                        }

                        Log::info('Immediate payment sync attempted after invoice export', [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'xero_invoice_id' => $invoice->xero_invoice_id,
                            'payment_sync_success_count' => $successCount,
                        ]);
                    } catch (\Exception $paymentSyncError) {
                        Log::error('Immediate payment sync failed after invoice export', [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'xero_invoice_id' => $invoice->xero_invoice_id,
                            'error' => $paymentSyncError->getMessage(),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Invoice sync failed', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                $results[] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Sync invoices FROM Xero (import invoices from Xero to local database)
     */
    public function syncInvoicesFromXero(): array
    {
        if (!$this->settings->sync_invoices_from_xero) {
            return ['skipped' => true, 'message' => 'Invoice sync from Xero is disabled'];
        }

        $currentCompany = $this->getCompany();
        $results = [];
        $syncStartedAt = now();
        $syncFullyCompleted = false;

        try {
            $hasAnyLocalInvoices = Invoice::where('company_id', $currentCompany->id)->exists();
            $fullSyncCompletedKey = self::getInitialSyncCompletedCacheKey($currentCompany->id, 'invoice');
            $cursorKey = self::getInitialSyncCursorCacheKey($currentCompany->id, 'invoice');
            $paginationKey = self::getInitialSyncPaginationCacheKey($currentCompany->id, 'invoice');
            $lastSync = $this->getLastSyncDatetimeForModule(self::SYNC_MODULE_INVOICES);
            $fullSyncCompleted = (bool) Cache::get($fullSyncCompletedKey, false);
            if (!$fullSyncCompleted && $hasAnyLocalInvoices && !empty($lastSync)) {
                $fullSyncCompleted = true;
                Cache::put($fullSyncCompletedKey, true, now()->addDays(365));
                Log::info('Rehydrated invoice full-sync completion state from xero_sync_states', [
                    'company_id' => $currentCompany->id,
                    'last_sync' => $lastSync,
                ]);
            }
            $isInitialInvoiceImport = !$hasAnyLocalInvoices;
            $isBackfillMode = $isInitialInvoiceImport || !$fullSyncCompleted;
            $cursor = Cache::get($cursorKey, ['page' => 1]);
            // During backfill mode we intentionally avoid If-Modified-Since so older pages are not skipped.
            $ifModifiedSince = $isBackfillMode ? [] : $this->buildIfModifiedSinceHeader($lastSync);

            $page = (int) ($isBackfillMode ? ($cursor['page'] ?? 1) : 1);
            if ($page < 1) {
                $page = 1;
            }
            $pageSize = max(1, min((int) config('services.xero.invoice_import_page_size', 50), 100));
            $maxPagesPerRun = max(1, (int) config('services.xero.invoice_import_max_pages_per_run', 5));
            $maxInvoicesPerRun = max(1, (int) config('services.xero.invoice_import_max_invoices_per_run', 250));
            $maxSecondsPerRun = max(5, (int) config('services.xero.invoice_import_max_seconds_per_run', 45));
            $pageDelayMs = max(0, (int) config('services.xero.invoice_import_page_delay_ms', 250));
            $totalProcessed = 0;
            $pagesProcessed = 0;
            $processedThisRun = 0;
            $processedInvoiceKeys = [];
            $startedAt = microtime(true);
            $stopReason = null;
            $hasMorePages = false;

            Log::info('Starting paginated invoice import from Xero with throttling', [
                'company_id' => $currentCompany->id,
                'mode' => $isBackfillMode ? 'backfill' : 'incremental',
                'start_page' => $page,
                'page_size' => $pageSize,
                'max_pages_per_run' => $maxPagesPerRun,
                'max_invoices_per_run' => $maxInvoicesPerRun,
                'max_seconds_per_run' => $maxSecondsPerRun,
                'page_delay_ms' => $pageDelayMs,
                'is_initial_invoice_import' => $isInitialInvoiceImport,
            ]);

            // While backfill is in progress, also check newest updates first so newly created
            // Xero invoices don't wait until the historical cursor reaches page 1 again.
            if ($isBackfillMode && !$isInitialInvoiceImport && !empty($lastSync)) {
                try {
                    $salesInvoiceWhere = rawurlencode('Type=="ACCREC"');
                    $incrementalResponse = $this->makeXeroRequest(
                        'get',
                        $this->baseUrl . '/api.xro/2.0/Invoices?page=1&pageSize=' . $pageSize . '&summaryOnly=false&where=' . $salesInvoiceWhere,
                        [],
                        2,
                        $this->buildIfModifiedSinceHeader($lastSync)
                    );

                    if ($incrementalResponse->successful()) {
                        $incrementalInvoices = $incrementalResponse->json()['Invoices'] ?? [];
                        foreach ($incrementalInvoices as $xeroInvoice) {
                            if (($xeroInvoice['Type'] ?? null) !== 'ACCREC') {
                                continue;
                            }
                            $invoiceProcessingKey = $this->getInvoiceProcessingKey($xeroInvoice);
                            if ($invoiceProcessingKey !== null && isset($processedInvoiceKeys[$invoiceProcessingKey])) {
                                continue;
                            }
                            $xeroInvoice = $this->hydrateInvoiceDetails($xeroInvoice);

                            $existingInvoice = Invoice::where('company_id', $currentCompany->id)
                                ->where('xero_invoice_id', $xeroInvoice['InvoiceID'] ?? null)
                                ->first();

                            if (!$existingInvoice) {
                                $invoice = $this->createInvoiceFromXero($xeroInvoice, $currentCompany);
                                $results[] = [
                                    'invoice_id' => $invoice->id,
                                    'invoice_number' => $invoice->invoice_number,
                                    'status' => 'created',
                                    'message' => 'Invoice imported from Xero (incremental pre-pass)',
                                ];
                            }
                            if ($invoiceProcessingKey !== null) {
                                $processedInvoiceKeys[$invoiceProcessingKey] = true;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Incremental pre-pass failed during invoice backfill', [
                        'company_id' => $currentCompany->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            do {
                if (!$isBackfillMode && $processedThisRun >= $maxInvoicesPerRun) {
                    $stopReason = 'max_invoices_per_run_reached';
                    break;
                }
                if (!$isBackfillMode && (microtime(true) - $startedAt) >= $maxSecondsPerRun) {
                    $stopReason = 'max_seconds_per_run_reached';
                    break;
                }

                Log::info('Fetching invoice page from Xero', [
                    'company_id' => $currentCompany->id,
                    'page' => $page,
                    'page_size' => $pageSize,
                ]);

                $salesInvoiceWhere = rawurlencode('Type=="ACCREC"');
                $response = $this->makeXeroRequest(
                    'get',
                    $this->baseUrl . '/api.xro/2.0/Invoices?page=' . $page . '&pageSize=' . $pageSize . '&summaryOnly=false&where=' . $salesInvoiceWhere,
                    [],
                    2,
                    $ifModifiedSince
                );

                if (!$response->successful()) {
                    $errorBody = $response->body();
                    $statusCode = $response->status();
                    
                    // Handle authentication errors specifically
                    if ($statusCode === 403 && str_contains($errorBody, 'AuthenticationUnsuccessful')) {
                        Log::error('Xero authentication failed during invoice sync', [
                            'company_id' => $currentCompany->id,
                            'status' => $statusCode,
                            'response' => $errorBody,
                        ]);
                        
                        $this->clearInvalidTokens();
                        
                        return ['skipped' => true, 'message' => 'Xero authentication failed. Please re-authorize your Xero connection in the settings.'];
                    }
                    
                    throw new \Exception('Failed to fetch invoices from Xero: ' . $errorBody);
                }

                $responseData = $response->json();
                $xeroInvoices = $responseData['Invoices'] ?? [];
                
                // Check if there are more pages
                $pagination = $responseData['Pagination'] ?? null;
                $currentPage = $pagination['Page'] ?? $page;
                $pageCount = $pagination['PageCount'] ?? 1;
                $itemCount = $pagination['ItemCount'] ?? count($xeroInvoices);
                
                $invoicesOnPage = count($xeroInvoices);
                // Prefer Xero pagination metadata when available to avoid looping forever
                // on APIs that can return a full final page or clamp page numbers.
                $hasPaginationPageCount = is_array($pagination) && isset($pagination['PageCount']) && is_numeric($pagination['PageCount']);

                // Self-heal stale cursors when switching query strategy (e.g., adding Type filter).
                // If the saved page is beyond the available filtered pages, restart at page 1.
                $normalizedPageCount = is_numeric($pageCount) ? (int) $pageCount : null;
                if ($isBackfillMode && $hasPaginationPageCount && $normalizedPageCount !== null && $normalizedPageCount >= 1 && $page > $normalizedPageCount) {
                    Log::warning('Invoice backfill cursor exceeded available pages; resetting to page 1', [
                        'company_id' => $currentCompany->id,
                        'requested_page' => $page,
                        'reported_current_page' => $currentPage,
                        'reported_page_count' => $normalizedPageCount,
                    ]);
                    $page = 1;
                    Cache::put($cursorKey, ['page' => $page], now()->addDays(7));
                    $hasMorePages = true;
                    continue;
                }

                if ($hasPaginationPageCount) {
                    $hasMorePages = $currentPage < $pageCount;
                } else {
                    $hasMorePages = $invoicesOnPage >= $pageSize;
                }
                
                Log::info('Fetched invoice page from Xero, processing now', [
                    'company_id' => $currentCompany->id,
                    'requested_page' => $page,
                    'current_page' => $currentPage,
                    'page_count' => $pageCount,
                    'item_count' => $itemCount,
                    'invoices_on_page' => $invoicesOnPage,
                    'page_size' => $pageSize,
                    'has_more_pages' => $hasMorePages,
                ]);
                Cache::put($paginationKey, [
                    'page' => (int) $currentPage,
                    'page_count' => is_numeric($pageCount) ? (int) $pageCount : null,
                    'item_count' => is_numeric($itemCount) ? (int) $itemCount : null,
                    'page_size' => $pageSize,
                    'captured_at' => now()->toIso8601String(),
                ], now()->addDays(7));

                // Process invoices from this page immediately
                foreach ($xeroInvoices as $xeroInvoice) {
                    if (!$isBackfillMode && $processedThisRun >= $maxInvoicesPerRun) {
                        $stopReason = 'max_invoices_per_run_reached';
                        break;
                    }
                    if (!$isBackfillMode && (microtime(true) - $startedAt) >= $maxSecondsPerRun) {
                        $stopReason = 'max_seconds_per_run_reached';
                        break;
                    }

                    try {
                        // Only import sales invoices (ACCREC), skip supplier bills (ACCPAY) and others.
                        if (($xeroInvoice['Type'] ?? null) !== 'ACCREC') {
                            continue;
                        }
                        $invoiceProcessingKey = $this->getInvoiceProcessingKey($xeroInvoice);
                        if ($invoiceProcessingKey !== null && isset($processedInvoiceKeys[$invoiceProcessingKey])) {
                            $results[] = [
                                'invoice_id' => null,
                                'invoice_number' => $xeroInvoice['InvoiceNumber'] ?? $xeroInvoice['Reference'] ?? 'Unknown',
                                'status' => 'skipped',
                                'message' => 'Invoice already processed in this run',
                            ];
                            continue;
                        }

                        $xeroInvoice = $this->hydrateInvoiceDetails($xeroInvoice);

                        // Skip if invoice doesn't have required fields
                        if (empty($xeroInvoice['InvoiceNumber']) && empty($xeroInvoice['Reference'])) {
                            continue;
                        }

                        // Check if invoice already exists in app by Xero invoice ID
                        $existingInvoice = Invoice::where('company_id', $currentCompany->id)
                            ->where('xero_invoice_id', $xeroInvoice['InvoiceID'])
                            ->first();

                        // If not found by Xero ID, check by invoice number
                        if (!$existingInvoice) {
                            $invoiceNumber = $xeroInvoice['InvoiceNumber'] ?? $xeroInvoice['Reference'] ?? null;
                            if ($invoiceNumber) {
                                $existingInvoice = Invoice::where('company_id', $currentCompany->id)
                                    ->where('invoice_number', $invoiceNumber)
                                    ->first();
                            }
                        }

                        if ($existingInvoice) {
                            $localLineItemCount = $existingInvoice->lineItems()->count();
                            $xeroLineItemCount = isset($xeroInvoice['LineItems']) && is_array($xeroInvoice['LineItems'])
                                ? count($xeroInvoice['LineItems'])
                                : 0;
                            $needsLineItemBackfill = $localLineItemCount === 0 && $xeroLineItemCount > 0;

                            if (!$this->xeroUpdatedAtChanged($existingInvoice, $xeroInvoice) && !$needsLineItemBackfill) {
                                $results[] = [
                                    'invoice_id' => $existingInvoice->id,
                                    'invoice_number' => $existingInvoice->invoice_number,
                                    'status' => 'skipped',
                                    'message' => 'Invoice unchanged in Xero',
                                ];
                                $totalProcessed++;
                                $processedThisRun++;
                                continue;
                            }

                            if ($needsLineItemBackfill) {
                                Log::info('Forcing invoice update to backfill missing local line items', [
                                    'company_id' => $currentCompany->id,
                                    'invoice_id' => $existingInvoice->id,
                                    'invoice_number' => $existingInvoice->invoice_number,
                                    'local_line_items' => $localLineItemCount,
                                    'xero_line_items' => $xeroLineItemCount,
                                ]);
                            }

                            $this->updateInvoiceFromXeroData($existingInvoice, $xeroInvoice);
                            if (!$existingInvoice->xero_invoice_id) {
                                $existingInvoice->update(['xero_invoice_id' => $xeroInvoice['InvoiceID']]);
                            }
                            $results[] = [
                                'invoice_id' => $existingInvoice->id,
                                'invoice_number' => $existingInvoice->invoice_number,
                                'status' => 'updated',
                                'message' => 'Invoice updated from Xero and linked to existing invoice',
                            ];
                            $processedThisRun++;
                        } else {
                            // Create new invoice
                            $invoice = $this->createInvoiceFromXero($xeroInvoice, $currentCompany);
                            $results[] = [
                                'invoice_id' => $invoice->id,
                                'invoice_number' => $invoice->invoice_number,
                                'status' => 'created',
                                'message' => 'Invoice imported from Xero',
                            ];
                            $processedThisRun++;
                        }
                        
                        $totalProcessed++;
                        if ($invoiceProcessingKey !== null) {
                            $processedInvoiceKeys[$invoiceProcessingKey] = true;
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to process invoice from Xero', [
                            'company_id' => $currentCompany->id,
                            'invoice_id' => $xeroInvoice['InvoiceID'] ?? 'Unknown',
                            'invoice_number' => $xeroInvoice['InvoiceNumber'] ?? $xeroInvoice['Reference'] ?? 'Unknown',
                            'xero_contact_id' => $xeroInvoice['Contact']['ContactID'] ?? 'Unknown',
                            'has_line_items' => !empty($xeroInvoice['LineItems']),
                            'line_items_count' => isset($xeroInvoice['LineItems']) ? count($xeroInvoice['LineItems']) : 0,
                            'error_class' => get_class($e),
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        
                        $results[] = [
                            'invoice_id' => null,
                            'invoice_number' => $xeroInvoice['InvoiceNumber'] ?? $xeroInvoice['Reference'] ?? 'Unknown',
                            'status' => 'error',
                            'error' => $e->getMessage(),
                        ];
                        $processedThisRun++;
                        if (isset($invoiceProcessingKey) && $invoiceProcessingKey !== null) {
                            $processedInvoiceKeys[$invoiceProcessingKey] = true;
                        }
                    }
                }

                Log::info('Completed processing invoice page', [
                    'company_id' => $currentCompany->id,
                    'page' => $page,
                    'invoices_processed_on_page' => $invoicesOnPage,
                    'total_processed_so_far' => $totalProcessed,
                    'processed_this_run' => $processedThisRun,
                ]);
                
                $pagesProcessed++;
                if (!$isBackfillMode && $pagesProcessed >= $maxPagesPerRun) {
                    $stopReason = 'max_pages_per_run_reached';
                }

                $page++;
                if ($isBackfillMode) {
                    Cache::put($cursorKey, ['page' => $page], now()->addDays(7));
                }
                
                // Add delay before fetching next page to avoid rate limiting
                if ($hasMorePages && !$stopReason && $pageDelayMs > 0) {
                    usleep($pageDelayMs * 1000);
                }
            } while ($hasMorePages && !$stopReason);

            if ($isBackfillMode && !$hasMorePages && !$stopReason) {
                Cache::put($fullSyncCompletedKey, true, now()->addDays(365));
                Cache::forget($cursorKey);
            }

            Log::info('Finished processing all invoices from Xero', [
                'company_id' => $currentCompany->id,
                'mode' => $isBackfillMode ? 'backfill' : 'incremental',
                'total_invoices_processed' => $totalProcessed,
                'pages_processed' => $pagesProcessed,
                'processed_this_run' => $processedThisRun,
                'stop_reason' => $stopReason,
            ]);
            $syncFullyCompleted = !$stopReason && !$hasMorePages;

        } catch (\Exception $e) {
            Log::error('Failed to sync invoices from Xero', [
                'company_id' => $currentCompany->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }

        if ($syncFullyCompleted) {
            $this->recordSyncDatetimeForModule(self::SYNC_MODULE_INVOICES, $syncStartedAt);
        }

        return $results;
    }

    /**
     * Create or update invoice in Xero
     */
    private function createOrUpdateInvoiceInXero(Invoice $invoice): array
    {
        // Ensure line items are loaded
        if (!$invoice->relationLoaded('lineItems')) {
            $invoice->load('lineItems');
        }
        
        $invoice->load(['lineItems.product', 'lineItems.account', 'lineItems.taxRate']);
        
        // Ensure customer is loaded
        if (!$invoice->relationLoaded('customer')) {
            $invoice->load('customer');
        }
        
        if ($invoice->lineItems->isEmpty()) {
            throw new \Exception("Invoice '{$invoice->invoice_number}' has no line items. Cannot sync to Xero.");
        }
        
        $documentCompanyId = (int) ($invoice->company_id ?: $this->settings->company_id);
        $defaultTaxRate = TaxRate::getDefaultSalesForCompany($documentCompanyId);
        $defaultTaxCode = $defaultTaxRate && $defaultTaxRate->xero_tax_rate_id 
            ? $defaultTaxRate->xero_tax_rate_id 
            : ($defaultTaxRate && $defaultTaxRate->code 
                ? $defaultTaxRate->code 
                : 'TAX002');
        
        $fallbackSalesAccountCode = ChartOfAccount::where('company_id', $documentCompanyId)
            ->where('is_active', true)
            ->where('account_code', '1000')
            ->value('account_code')
            ?? ChartOfAccount::getDefaultSalesForCompany($documentCompanyId)?->account_code
            ?? '1000';
        $defaultRoundingAccount = $this->resolveDefaultRoundingAccountForCompany($documentCompanyId);
        $defaultRoundingAccountCode = $defaultRoundingAccount?->account_code;
        $defaultRoundingAccountId = $defaultRoundingAccount?->id;

        $lineItems = [];
        foreach ($invoice->lineItems as $lineItem) {
            // Use the line item's total field which already includes discount
            // This ensures consistency with what's stored in the database
            $lineAmount = (float) $lineItem->total;
            $quantity = (float) $lineItem->quantity;
            $unitAmount = (float) $lineItem->unit_price;
            
            // Log discount information for debugging
            $discountAmount = (float) ($lineItem->discount_amount ?? 0);
            $discountPercentage = (float) ($lineItem->discount_percentage ?? 0);
            
            $accountCode = $fallbackSalesAccountCode;
            if ($lineItem->account_id && $lineItem->account && !empty($lineItem->account->account_code)) {
                $accountCode = $lineItem->account->account_code;
            }
            $isRoundingAdjustmentLine = $this->isRoundingAdjustmentLineDescription($lineItem->description ?? '')
                || ($defaultRoundingAccountId && (int) $lineItem->account_id === (int) $defaultRoundingAccountId);
            if ($isRoundingAdjustmentLine && !empty($defaultRoundingAccountCode)) {
                $accountCode = $defaultRoundingAccountCode;
            }
            $taxTypeCode = $this->resolveXeroTaxTypeForLineItem($lineItem, $defaultTaxCode, $documentCompanyId);
            
            // Calculate expected subtotal (Quantity * UnitAmount)
            $expectedSubtotal = $quantity * $unitAmount;
            
            // Verify LineAmount matches expected calculation
            $calculatedLineAmount = $expectedSubtotal;
            $hasDiscount = false;
            
            // Determine discount type and calculate LineAmount
            if ($discountPercentage > 0) {
                // Percentage-based discount
                $hasDiscount = true;
                $calculatedLineAmount = $expectedSubtotal * (1 - ($discountPercentage / 100));
            } elseif ($discountAmount > 0) {
                // Amount-based discount
                $hasDiscount = true;
                $calculatedLineAmount = $expectedSubtotal - $discountAmount;
            }
            
            // Use calculated amount if it differs significantly from stored total
            // (within 0.01 tolerance for rounding differences)
            if ($hasDiscount && abs($calculatedLineAmount - $lineAmount) > 0.01) {
                Log::warning('LineAmount mismatch detected, using calculated value', [
                    'invoice_id' => $invoice->id,
                    'line_item_id' => $lineItem->id,
                    'stored_total' => $lineAmount,
                    'calculated_total' => $calculatedLineAmount,
                    'expected_subtotal' => $expectedSubtotal,
                    'discount_amount' => $discountAmount,
                    'discount_percentage' => $discountPercentage,
                ]);
                $lineAmount = round($calculatedLineAmount, 2);
            }

            // Xero validates LineAmount against Quantity * UnitAmount.
            // For non-discounted lines, align outbound UnitAmount to the stored line total
            // when totals differ (common on explicit rounding adjustment rows).
            if (!$hasDiscount && abs(($quantity * $unitAmount) - $lineAmount) > 0.01) {
                $unitAmount = $quantity != 0.0
                    ? round($lineAmount / $quantity, 4)
                    : round($lineAmount, 4);

                Log::info('Adjusted invoice unit amount for Xero line validation', [
                    'invoice_id' => $invoice->id,
                    'line_item_id' => $lineItem->id,
                    'quantity' => $quantity,
                    'original_unit_amount' => (float) $lineItem->unit_price,
                    'adjusted_unit_amount' => $unitAmount,
                    'line_amount' => $lineAmount,
                ]);
            }
            
            Log::debug('Processing invoice line item for Xero', [
                'invoice_id' => $invoice->id,
                'line_item_id' => $lineItem->id,
                'product_id' => $lineItem->product_id,
                'account_code' => $accountCode,
                'quantity' => $quantity,
                'unit_price' => $unitAmount,
                'discount_amount' => $discountAmount,
                'discount_percentage' => $discountPercentage,
                'line_item_total' => $lineAmount,
                'expected_subtotal' => $expectedSubtotal,
                'calculated_line_amount' => $calculatedLineAmount,
            ]);
            
            $lineItemData = [
                'Description' => $lineItem->description ?? 'Item',
                'Quantity' => $quantity,
                'UnitAmount' => $unitAmount,
                'LineAmount' => round($lineAmount, 2),
                'AccountCode' => $accountCode,
                'TaxType' => $taxTypeCode,
            ];

            $itemCode = $this->resolveXeroItemCodeForLineItem($lineItem);
            if (!empty($itemCode)) {
                $lineItemData['ItemCode'] = $itemCode;
            }
            
            // Add discount field based on discount type
            // Xero requires DiscountRate for percentage discounts or DiscountAmount for amount discounts
            if ($discountPercentage > 0) {
                // Use DiscountRate for percentage-based discounts
                $lineItemData['DiscountRate'] = round($discountPercentage, 2);
            } elseif ($discountAmount > 0) {
                // Use DiscountAmount for amount-based discounts
                $lineItemData['DiscountAmount'] = round($discountAmount, 2);
            }
            
            $lineItems[] = $lineItemData;
        }

        // Get customer Xero contact ID
        $xeroContactId = $this->getXeroContactId($invoice->customer);
        
        $invoiceData = [
            'Type' => 'ACCREC',
            'Contact' => [
                'ContactID' => $xeroContactId,
            ],
            'Date' => $invoice->invoice_date->format('Y-m-d'),
            'DueDate' => $invoice->due_date->format('Y-m-d'),
            'LineItems' => $lineItems,
            'Status' => $this->mapInvoiceStatus($invoice->status),
            'Reference' => $invoice->invoice_number,
        ];
        
        // Add invoice number as InvoiceNumber if available
        if ($invoice->invoice_number) {
            $invoiceData['InvoiceNumber'] = $invoice->invoice_number;
        }
        
        Log::info('Creating/updating invoice in Xero', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'xero_invoice_id' => $invoice->xero_invoice_id,
            'customer_id' => $invoice->customer_id,
            'xero_contact_id' => $xeroContactId,
            'line_items_count' => count($lineItems),
            'subtotal' => $invoice->subtotal,
            'tax_amount' => $invoice->tax_amount,
            'total' => $invoice->total,
        ]);

        // If invoice already has a Xero ID, update it; otherwise create new.
        // Avoid extra pre-read requests for invoice details; let Xero validate the payload.
        if ($invoice->xero_invoice_id) {
            $invoiceData['InvoiceID'] = $invoice->xero_invoice_id;
        }
        
        $response = $this->makeXeroRequest('post', $this->baseUrl . '/api.xro/2.0/Invoices', [
            'Invoices' => [$invoiceData]
        ]);

        if (!$response->successful()) {
            $errorBody = $response->body();
            $statusCode = $response->status();
            
            Log::error('Failed to create/update invoice in Xero', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $statusCode,
                'response' => $errorBody,
            ]);
            
            // Parse Xero error for more specific message
            $errorData = json_decode($errorBody, true);
            if (is_array($errorData) && $this->shouldMarkInvoiceAsSyncedOnValidationError($errorData)) {
                $syncStamp = $this->resolveInvoiceValidationSyncTimestamp($errorData, $invoice);
                $invoice->update(['xero_updated_at' => $syncStamp]);
                $this->alignLocalUpdatedAtWithXero($invoice);

                Log::warning('Marked invoice as synced after non-retriable Xero validation response', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'xero_invoice_id' => $invoice->xero_invoice_id,
                    'xero_updated_at' => $syncStamp->toDateTimeString(),
                    'status' => $statusCode,
                    'response' => $errorData,
                ]);

                return [
                    'InvoiceID' => $invoice->xero_invoice_id,
                    'UpdatedDateUTC' => $syncStamp->toIso8601String(),
                ];
            }

            if (isset($errorData['Elements'][0]['ValidationErrors'])) {
                $errors = collect($errorData['Elements'][0]['ValidationErrors'])
                    ->pluck('Message')
                    ->implode(', ');
                throw new \Exception('Failed to create/update invoice in Xero: ' . $errors);
            }
            
            throw new \Exception('Failed to create/update invoice in Xero: ' . $errorBody);
        }

        $result = $response->json();
        
        if (empty($result['Invoices'])) {
            Log::error('Xero API returned no invoices in response', [
                'invoice_id' => $invoice->id,
                'response' => $result,
            ]);
            throw new \Exception('Xero API returned no invoices in response');
        }
        
        $xeroInvoice = $result['Invoices'][0];
        
        Log::info('Invoice successfully synced to Xero', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'xero_invoice_id' => $xeroInvoice['InvoiceID'] ?? null,
        ]);
        
        // Store the Xero invoice ID if it's new
        if (!$invoice->xero_invoice_id && isset($xeroInvoice['InvoiceID'])) {
            $invoice->update(['xero_invoice_id' => $xeroInvoice['InvoiceID']]);
        }
        
        return $xeroInvoice;
    }

    /**
     * Get Xero contact ID for customer
     */
    private function getXeroContactId(Customer $customer): string
    {
        if (!$customer->xero_contact_id) {
            throw new \Exception("Customer '{$customer->name}' has not been synced to Xero yet. Please sync customers first.");
        }
        
        return $customer->xero_contact_id;
    }

    /**
     * Map invoice status to Xero status
     */
    private function mapInvoiceStatus(string $status): string
    {
        return match($status) {
            'draft' => 'AUTHORISED',
            'sent' => 'AUTHORISED',
            'paid' => 'AUTHORISED', // Always create as AUTHORISED, payments will be synced separately
            'overdue' => 'AUTHORISED',
            'cancelled' => 'VOIDED',
            default => 'AUTHORISED',
        };
    }

    /**
     * Handle webhook from Xero for invoice updates
     */
    public function handleInvoiceWebhook(array $webhookData): void
    {
        if (!$this->settings->sync_invoices_from_xero) {
            return;
        }

        foreach ($webhookData as $event) {
            if ($event['EventType'] === 'UPDATE' && $event['EventCategory'] === 'INVOICE') {
                $this->updateInvoiceFromXero($event['ResourceId']);
            }
        }
    }

    /**
     * Update invoice from Xero data
     */
    private function updateInvoiceFromXero(string $xeroInvoiceId): void
    {
        try {
            $response = $this->makeXeroRequest(
                'get',
                $this->baseUrl . '/api.xro/2.0/Invoices?where=' . rawurlencode('InvoiceID==Guid("' . $xeroInvoiceId . '")') . '&summaryOnly=false'
            );

            if (!$response->successful()) {
                Log::error('Failed to fetch invoice from Xero: ' . $response->body());
                return;
            }

            $xeroInvoice = $response->json()['Invoices'][0];
            
            $currentCompany = $this->getCompany();

            // Find local invoice by Xero ID or invoice number within the active company.
            $invoice = Invoice::where('company_id', $currentCompany->id)
                ->where(function ($query) use ($xeroInvoice, $xeroInvoiceId) {
                    $invoiceNumber = $xeroInvoice['Reference'] ?? $xeroInvoice['InvoiceNumber'] ?? null;

                    $query->where('xero_invoice_id', $xeroInvoiceId);

                    if ($invoiceNumber) {
                        $query->orWhere('invoice_number', $invoiceNumber);
                    }
                })
                ->first();

            if ($invoice) {
                $status = $this->mapXeroStatusToLocal($xeroInvoice['Status']);
                $updated = $this->updateModelIfChanged($invoice, [
                    'status' => $status,
                    ...$this->getXeroTimestamps($xeroInvoice),
                ], 'invoice', [
                    'invoice_id' => $invoice->id,
                    'xero_invoice_id' => $xeroInvoiceId,
                ]);
                if ($updated) {
                    $this->alignLocalUpdatedAtWithXero($invoice);
                }
                
                Log::info("Updated invoice {$invoice->id} status to {$status} from Xero");
            }
        } catch (\Exception $e) {
            Log::error('Error updating invoice from Xero: ' . $e->getMessage());
        }
    }

    /**
     * Map Xero status to local status
     */
    private function mapXeroStatusToLocal(string $xeroStatus): string
    {
        return match($xeroStatus) {
            'DRAFT' => 'draft',
            'SUBMITTED' => 'sent',
            'PAID' => 'paid',
            'VOIDED' => 'cancelled',
            default => 'sent',
        };
    }

    /**
     * Get Xero contact by ID (used for individual lookups when needed)
     */
    private function getXeroContact(string $contactId): ?array
    {
        try {
            $response = $this->makeXeroRequest('get', $this->baseUrl . '/api.xro/2.0/Contacts/' . $contactId);

            if ($response->successful()) {
                $result = $response->json();
                return $result['Contacts'][0] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('Failed to get Xero contact', [
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Parse Xero date format
     */
    private function parseXeroDate(string $xeroDate): \Carbon\Carbon
    {
        // Xero dates are in format: /Date(1758240000000)/ or /Date(1758240000000+0000)/
        if (preg_match('/\/Date\((\d+)([+-]\d{4})?\)\//', $xeroDate, $matches)) {
            $timestamp = $matches[1] / 1000; // Convert from milliseconds
            return \Carbon\Carbon::createFromTimestamp($timestamp);
        }
        
        // Fallback to regular date parsing
        return \Carbon\Carbon::parse($xeroDate);
    }

    private function getXeroTimestamps(array $xeroData): array
    {
        $timestamps = [];

        if (!empty($xeroData['UpdatedDateUTC'])) {
            $timestamps['xero_updated_at'] = $this->parseXeroDate($xeroData['UpdatedDateUTC']);
        }

        if (!empty($xeroData['CreatedDateUTC'])) {
            $timestamps['xero_created_at'] = $this->parseXeroDate($xeroData['CreatedDateUTC']);
        } elseif (!empty($timestamps['xero_updated_at'])) {
            $timestamps['xero_created_at'] = $timestamps['xero_updated_at'];
        }

        return $timestamps;
    }

    /**
     * Keep local updated_at aligned to Xero updated timestamp for imported records.
     * This prevents outbound sync loops that use updated_at > xero_updated_at.
     */
    private function alignLocalUpdatedAtWithXero(Model $model): void
    {
        $xeroUpdatedAt = $model->getAttribute('xero_updated_at');
        if (!$xeroUpdatedAt) {
            return;
        }

        $model->newQuery()
            ->whereKey($model->getKey())
            ->update(['updated_at' => $xeroUpdatedAt]);

        $model->setAttribute('updated_at', $xeroUpdatedAt);
    }

    private function updateModelIfChanged(Model $model, array $updateData, string $entity, array $context = []): bool
    {
        $changes = [];
        foreach ($updateData as $key => $value) {
            if ($this->syncValuesDiffer($model->getAttribute($key), $value)) {
                $changes[$key] = $value;
            }
        }

        if (empty($changes)) {
            Log::info('Skipping local model update (no material changes)', array_merge([
                'entity' => $entity,
                'decision_reason' => 'skip_noop',
                'model' => $model::class,
                'model_id' => $model->getKey(),
            ], $context));
            return false;
        }

        $model->update($changes);
        return true;
    }

    private function syncValuesDiffer(mixed $current, mixed $incoming): bool
    {
        if ($current instanceof \DateTimeInterface) {
            $current = \Carbon\Carbon::parse($current)->toIso8601String();
        }
        if ($incoming instanceof \DateTimeInterface) {
            $incoming = \Carbon\Carbon::parse($incoming)->toIso8601String();
        }

        if (is_numeric($current) && is_numeric($incoming)) {
            return abs((float) $current - (float) $incoming) > 0.00001;
        }

        if (is_bool($current) || is_bool($incoming)) {
            return (bool) $current !== (bool) $incoming;
        }

        return $current !== $incoming;
    }

    private function xeroUpdatedAtChanged($existingModel, array $xeroData): bool
    {
        if (empty($xeroData['UpdatedDateUTC'])) {
            return true;
        }

        if (empty($existingModel->xero_updated_at)) {
            return true;
        }

        $xeroUpdated = $this->parseXeroDate($xeroData['UpdatedDateUTC']);

        return !$existingModel->xero_updated_at->eq($xeroUpdated);
    }

    /**
     * Returns true only when Xero timestamp is strictly newer than local record timestamp.
     * We compare against local updated_at so local edits win if they happened after last Xero change.
     */
    private function isXeroRecordNewerThanLocal($existingModel, array $xeroData): bool
    {
        if (empty($xeroData['UpdatedDateUTC'])) {
            return false;
        }

        if (empty($existingModel->updated_at)) {
            return true;
        }

        $xeroUpdated = $this->parseXeroDate($xeroData['UpdatedDateUTC']);
        return $xeroUpdated->gt($existingModel->updated_at);
    }

    private function buildIfModifiedSinceHeader(?string $lastSync): array
    {
        if (!$lastSync) {
            return [];
        }

        return ['If-Modified-Since' => \Carbon\Carbon::parse($lastSync)->format('D, d M Y H:i:s \G\M\T')];
    }

    private function getInvoiceProcessingKey(array $xeroInvoice): ?string
    {
        $invoiceId = trim((string) ($xeroInvoice['InvoiceID'] ?? ''));
        if ($invoiceId !== '') {
            return 'id:' . strtolower($invoiceId);
        }

        $invoiceNumber = trim((string) ($xeroInvoice['InvoiceNumber'] ?? $xeroInvoice['Reference'] ?? ''));
        if ($invoiceNumber !== '') {
            return 'number:' . strtolower($invoiceNumber);
        }

        return null;
    }

    private function getLastSyncDatetimeForModule(string $module): ?string
    {
        $state = XeroSyncState::where('company_id', $this->getCompany()->id)
            ->where('module', $module)
            ->first();

        return $state?->last_synced_at?->toDateTimeString();
    }

    private function recordSyncDatetimeForModule(string $module, \Carbon\CarbonInterface $syncedAt): void
    {
        XeroSyncState::updateOrCreate(
            [
                'company_id' => $this->getCompany()->id,
                'module' => $module,
            ],
            [
                'last_synced_at' => $syncedAt->copy()->utc(),
            ]
        );
    }

    /**
     * Fetch contacts from Xero with caching and If-Modified-Since.
     * Shared by customer and supplier syncs to avoid duplicate API calls.
     */
    private function fetchXeroContacts(string $module, bool $forceFullFetch = false): array
    {
        $cacheKeySuffix = $forceFullFetch ? 'full' : 'delta';
        $cacheKey = "{$module}:{$cacheKeySuffix}";

        if (array_key_exists($cacheKey, $this->cachedXeroContacts)) {
            return $this->cachedXeroContacts[$cacheKey];
        }

        $companyId = $this->getCompany()->id;
        $cacheTtlSeconds = max(60, (int) config('services.xero.contacts_cache_ttl_seconds', 300));
        $persistentCacheKey = "xero_contacts_cache_company_{$companyId}_module_{$module}_{$cacheKeySuffix}";
        $cached = $forceFullFetch ? null : Cache::get($persistentCacheKey);
        if (is_array($cached) && array_key_exists('contacts', $cached) && is_array($cached['contacts'])) {
            $this->cachedXeroContacts[$cacheKey] = $cached['contacts'];
            return $this->cachedXeroContacts[$cacheKey];
        }

        $headers = [];
        if (!$forceFullFetch) {
            $lastSync = $this->getLastSyncDatetimeForModule($module);
            $headers = $this->buildIfModifiedSinceHeader($lastSync);
        }

        $response = $this->makeXeroRequest(
            'get',
            $this->baseUrl . '/api.xro/2.0/Contacts',
            [],
            2,
            $headers
        );

        if (!$response->successful()) {
            $errorBody = $response->body();
            $statusCode = $response->status();

            if ($statusCode === 403 && str_contains($errorBody, 'AuthenticationUnsuccessful')) {
                $this->clearInvalidTokens();
                throw new \Exception('Xero authentication failed. Please re-authorize your Xero connection in the settings.');
            }

            if (is_array($cached) && array_key_exists('contacts', $cached) && is_array($cached['contacts'])) {
                Log::warning('Falling back to cached Xero contacts after failed fetch', [
                    'company_id' => $companyId,
                    'status' => $statusCode,
                ]);
                $this->cachedXeroContacts[$cacheKey] = $cached['contacts'];
                return $this->cachedXeroContacts[$cacheKey];
            }

            throw new \Exception('Failed to fetch contacts from Xero: ' . $errorBody);
        }

        $this->cachedXeroContacts[$cacheKey] = $response->json()['Contacts'] ?? [];
        Cache::put($persistentCacheKey, ['contacts' => $this->cachedXeroContacts[$cacheKey]], now()->addSeconds($cacheTtlSeconds));
        return $this->cachedXeroContacts[$cacheKey];
    }

    /**
     * Fetch accounts from Xero with caching and If-Modified-Since.
     * Shared by bank account and chart of accounts syncs.
     */
    private function fetchXeroAccounts(string $module, bool $forceFullFetch = false): array
    {
        $cacheKeySuffix = $forceFullFetch ? 'full' : 'delta';
        $cacheKey = "{$module}:{$cacheKeySuffix}";
        if (array_key_exists($cacheKey, $this->cachedXeroAccounts)) {
            return $this->cachedXeroAccounts[$cacheKey];
        }

        $headers = [];
        if (!$forceFullFetch) {
            $lastSync = $this->getLastSyncDatetimeForModule($module);
            $headers = $this->buildIfModifiedSinceHeader($lastSync);
        }

        $response = $this->makeXeroRequest(
            'get',
            $this->baseUrl . '/api.xro/2.0/Accounts',
            [],
            2,
            $headers
        );

        if (!$response->successful()) {
            $errorBody = $response->body();
            $statusCode = $response->status();

            if ($statusCode === 403 && str_contains($errorBody, 'AuthenticationUnsuccessful')) {
                $this->clearInvalidTokens();
                throw new \Exception('Xero authentication failed. Please re-authorize your Xero connection in the settings.');
            }

            throw new \Exception('Failed to fetch accounts from Xero: ' . $errorBody);
        }

        $this->cachedXeroAccounts[$cacheKey] = $response->json()['Accounts'] ?? [];
        return $this->cachedXeroAccounts[$cacheKey];
    }

    /**
     * Update customer from Xero data
     */
    private function updateCustomerFromXero(Customer $customer, array $xeroCustomer): void
    {
        $resolvedEmail = $this->resolveCustomerEmailFromXero($xeroCustomer, $customer->company_id);
        $updateData = [
            'name' => $xeroCustomer['Name'] ?? $customer->name,
            'email' => $resolvedEmail ?: $customer->email,
            'xero_contact_id' => $xeroCustomer['ContactID'] ?? $customer->xero_contact_id,
            ...$this->getXeroTimestamps($xeroCustomer),
        ];

        // Update account_code from Xero AccountNumber if available
        if (isset($xeroCustomer['AccountNumber']) && !empty($xeroCustomer['AccountNumber'])) {
            $updateData['account_code'] = $xeroCustomer['AccountNumber'];
        }

        // Update VAT number from Xero TaxNumber if available
        if (isset($xeroCustomer['TaxNumber']) && !empty($xeroCustomer['TaxNumber'])) {
            $updateData['vat_number'] = $xeroCustomer['TaxNumber'];
        }

        // Update phone if available
        if (isset($xeroCustomer['Phones']) && !empty($xeroCustomer['Phones'])) {
            $phone = collect($xeroCustomer['Phones'])->first();
            $updateData['phone'] = $phone['PhoneNumber'] ?? $customer->phone;
        }

        // Update address if available
        if (isset($xeroCustomer['Addresses']) && !empty($xeroCustomer['Addresses'])) {
            $address = collect($xeroCustomer['Addresses'])->first();
            $updateData['address'] = $address['AddressLine1'] ?? $customer->address;
            $updateData['city'] = $address['City'] ?? $customer->city;
            $updateData['country'] = $address['Country'] ?? $customer->country;
        }

        if ($this->updateModelIfChanged($customer, $updateData, 'customer', [
            'customer_id' => $customer->id,
            'xero_contact_id' => $xeroCustomer['ContactID'] ?? $customer->xero_contact_id,
        ])) {
            $this->alignLocalUpdatedAtWithXero($customer);
        }
    }

    /**
     * Create customer from Xero contact data
     */
    private function createCustomerFromXero(array $xeroContact, Company $company): Customer
    {
        $customerData = [
            'company_id' => $company->id,
            'name' => $xeroContact['Name'],
            'email' => $this->resolveCustomerEmailFromXero($xeroContact, $company->id),
            'xero_contact_id' => $xeroContact['ContactID'],
            ...$this->getXeroTimestamps($xeroContact),
        ];

        // Add account_code from Xero AccountNumber if available
        if (isset($xeroContact['AccountNumber']) && !empty($xeroContact['AccountNumber'])) {
            $customerData['account_code'] = $xeroContact['AccountNumber'];
        }

        // Add VAT number from Xero TaxNumber if available
        if (isset($xeroContact['TaxNumber']) && !empty($xeroContact['TaxNumber'])) {
            $customerData['vat_number'] = $xeroContact['TaxNumber'];
        }

        // Add phone if available
        if (isset($xeroContact['Phones']) && !empty($xeroContact['Phones'])) {
            $phone = collect($xeroContact['Phones'])->first();
            $customerData['phone'] = $phone['PhoneNumber'] ?? null;
        }

        // Add address if available
        if (isset($xeroContact['Addresses']) && !empty($xeroContact['Addresses'])) {
            $address = collect($xeroContact['Addresses'])->first();
            $customerData['address'] = $address['AddressLine1'] ?? null;
            $customerData['city'] = $address['City'] ?? null;
            $customerData['country'] = $address['Country'] ?? null;
        }

        $customer = Customer::create($customerData);
        $this->alignLocalUpdatedAtWithXero($customer);
        return $customer;
    }

    private function resolveCustomerEmailFromXero(array $xeroContact, int $companyId): string
    {
        $email = strtolower(trim((string) ($xeroContact['EmailAddress'] ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        $contactId = strtolower((string) ($xeroContact['ContactID'] ?? ''));
        $token = preg_replace('/[^a-z0-9]/', '', $contactId);

        if ($token === '') {
            $nameSeed = strtolower(trim((string) ($xeroContact['Name'] ?? 'unknown')));
            $token = substr(sha1($companyId . '|' . $nameSeed), 0, 16);
        } else {
            $token = substr($token, 0, 32);
        }

        return "xero-{$companyId}-{$token}@placeholder.invalid";
    }

    /**
     * Sync suppliers to Xero
     */
    public function syncSuppliersToXero(Company $company = null): array
    {
        if (!$this->settings->sync_suppliers_to_xero) {
            return ['skipped' => true, 'message' => 'Supplier sync to Xero is disabled'];
        }

        $currentCompany = $this->getCompany();
        $results = [];
        $maxPerRun = max(1, (int) config('services.xero.supplier_export_max_per_run', 200));

        $suppliersQuery = Supplier::where('company_id', $currentCompany->id)
            ->where(function ($query) {
                $query->whereNull('xero_contact_id')
                    ->orWhereNull('xero_updated_at')
                    ->orWhereColumn('updated_at', '>', 'xero_updated_at');
            })
            ->orderByDesc('updated_at');

        if (!$suppliersQuery->exists()) {
            return ['skipped' => true, 'message' => 'No supplier changes to sync to Xero'];
        }

        $xeroContactsMap = [];
        // If supplier import-from-Xero is disabled, keep protective comparison fetch.
        if (!$this->settings->sync_suppliers_from_xero) {
            try {
                $xeroContacts = $this->fetchXeroContacts(self::SYNC_MODULE_SUPPLIERS);
                foreach ($xeroContacts as $xeroContact) {
                    $xeroContactsMap[$xeroContact['ContactID']] = $xeroContact;
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch Xero contacts for supplier comparison', ['error' => $e->getMessage()]);
            }
        }

        $suppliers = $suppliersQuery->take($maxPerRun)->get();
        foreach ($suppliers as $supplier) {
            try {
                if ($supplier->xero_contact_id && isset($xeroContactsMap[$supplier->xero_contact_id])) {
                    $xeroSupplier = $xeroContactsMap[$supplier->xero_contact_id];
                    if ($this->isXeroRecordNewerThanLocal($supplier, $xeroSupplier)) {
                        $this->updateSupplierFromXero($supplier, $xeroSupplier);
                        $results[] = [
                            'supplier_id' => $supplier->id,
                            'supplier_name' => $supplier->name,
                            'status' => 'updated_from_xero',
                            'message' => 'Supplier updated from Xero (Xero was newer)',
                        ];
                        continue;
                    }
                }
                
                $xeroSupplier = $this->createOrUpdateSupplierInXero($supplier);
                
                if (isset($xeroSupplier['ContactID'])) {
                    $supplier->update([
                        'xero_contact_id' => $xeroSupplier['ContactID'],
                        ...$this->getXeroTimestamps($xeroSupplier),
                    ]);
                    $this->alignLocalUpdatedAtWithXero($supplier);
                }
                
                $results[] = [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'status' => 'success',
                    'message' => 'Supplier synced to Xero',
                ];
            } catch (\Exception $e) {
                Log::error('Failed to sync supplier to Xero', [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'error' => $e->getMessage(),
                ]);
                
                $results[] = [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Sync suppliers from Xero (import existing Xero suppliers to app)
     */
    public function syncSuppliersFromXero(Company $company = null): array
    {
        if (!$this->settings->sync_suppliers_from_xero) {
            return ['skipped' => true, 'message' => 'Supplier sync from Xero is disabled'];
        }

        $currentCompany = $this->getCompany();
        $results = [];
        $syncStartedAt = now();

        try {
            $xeroContacts = $this->fetchXeroContacts(self::SYNC_MODULE_SUPPLIERS);
            
            // Filter for suppliers only (contacts where IsSupplier is true)
            $xeroSuppliers = array_filter($xeroContacts, function($contact) {
                return isset($contact['IsSupplier']) && $contact['IsSupplier'] === true;
            });
            
            foreach ($xeroSuppliers as $xeroContact) {
                try {
                    // Skip if contact doesn't have required fields
                    if (empty($xeroContact['Name'])) {
                        continue;
                    }

                    // Check if supplier already exists in app by Xero contact ID
                    $existingSupplier = $this->findExistingSupplierForXero($xeroContact, $currentCompany);

                    if ($existingSupplier) {
                        if (!$this->xeroUpdatedAtChanged($existingSupplier, $xeroContact)) {
                            $this->syncContactPersonsFromXero($xeroContact, $currentCompany->id, null, $existingSupplier->id);
                            $results[] = [
                                'supplier_id' => $existingSupplier->id,
                                'supplier_name' => $xeroContact['Name'],
                                'status' => 'skipped',
                                'message' => 'Supplier unchanged in Xero',
                            ];
                            continue;
                        }
                        $this->updateSupplierFromXero($existingSupplier, $xeroContact);
                        $this->syncContactPersonsFromXero($xeroContact, $currentCompany->id, null, $existingSupplier->id);
                        $results[] = [
                            'supplier_id' => $existingSupplier->id,
                            'supplier_name' => $xeroContact['Name'],
                            'status' => 'updated',
                            'message' => 'Supplier updated from Xero and linked to existing supplier',
                        ];
                    } else {
                        // Create new supplier
                        $supplier = $this->createSupplierFromXero($xeroContact, $currentCompany);
                        $this->syncContactPersonsFromXero($xeroContact, $currentCompany->id, null, $supplier->id);
                        $results[] = [
                            'supplier_id' => $supplier->id,
                            'supplier_name' => $xeroContact['Name'],
                            'status' => 'created',
                            'message' => 'Supplier imported from Xero',
                        ];
                    }
                } catch (\Exception $e) {
                    $results[] = [
                        'supplier_name' => $xeroContact['Name'] ?? 'Unknown',
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to sync suppliers from Xero', [
                'company_id' => $currentCompany->id,
                'error' => $e->getMessage(),
            ]);
            
            return ['skipped' => true, 'message' => 'Failed to sync suppliers from Xero: ' . $e->getMessage()];
        }

        $this->recordSyncDatetimeForModule(self::SYNC_MODULE_SUPPLIERS, $syncStartedAt);

        return $results;
    }

    /**
     * Create or update supplier in Xero
     */
    private function createOrUpdateSupplierInXero(Supplier $supplier): array
    {
        $contactData = [
            'Name' => $supplier->name,
            'IsSupplier' => true,
            'EmailAddress' => $supplier->email,
            'Phones' => $supplier->phone ? [
                [
                    'PhoneType' => 'DEFAULT',
                    'PhoneNumber' => $supplier->phone,
                ]
            ] : [],
            'Addresses' => $supplier->address ? [
                [
                    'AddressType' => 'STREET',
                    'AddressLine1' => $supplier->address,
                    'City' => $supplier->city,
                    'Region' => $supplier->state,
                    'PostalCode' => $supplier->postal_code,
                    'Country' => $supplier->country,
                ]
            ] : [],
        ];

        if ($supplier->vat_number) {
            $contactData['TaxNumber'] = $supplier->vat_number;
        }

        if ($supplier->xero_contact_id) {
            $contactData['ContactID'] = $supplier->xero_contact_id;
        }

        $response = $this->makeXeroRequest('post', $this->baseUrl . '/api.xro/2.0/Contacts', [
            'Contacts' => [$contactData]
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create/update supplier in Xero: ' . $response->body());
        }

        $result = $response->json();
        return $result['Contacts'][0];
    }

    /**
     * Update supplier from Xero data
     */
    private function updateSupplierFromXero(Supplier $supplier, array $xeroSupplier): void
    {
        $updateData = [
            'name' => $xeroSupplier['Name'] ?? $supplier->name,
            'email' => $xeroSupplier['EmailAddress'] ?? $supplier->email,
            'xero_contact_id' => $xeroSupplier['ContactID'] ?? $supplier->xero_contact_id,
            ...$this->getXeroTimestamps($xeroSupplier),
        ];

        if (isset($xeroSupplier['TaxNumber']) && !empty($xeroSupplier['TaxNumber'])) {
            $updateData['vat_number'] = $xeroSupplier['TaxNumber'];
        }

        // Update phone if available
        if (isset($xeroSupplier['Phones']) && !empty($xeroSupplier['Phones'])) {
            $phone = collect($xeroSupplier['Phones'])->first();
            $updateData['phone'] = $phone['PhoneNumber'] ?? $supplier->phone;
        }

        // Update address if available
        if (isset($xeroSupplier['Addresses']) && !empty($xeroSupplier['Addresses'])) {
            $address = collect($xeroSupplier['Addresses'])->first();
            $updateData['address'] = $address['AddressLine1'] ?? $supplier->address;
            $updateData['city'] = $address['City'] ?? $supplier->city;
            $updateData['state'] = $address['Region'] ?? $supplier->state;
            $updateData['postal_code'] = $address['PostalCode'] ?? $supplier->postal_code;
            $updateData['country'] = $address['Country'] ?? $supplier->country;
        }

        if ($this->updateModelIfChanged($supplier, $updateData, 'supplier', [
            'supplier_id' => $supplier->id,
            'xero_contact_id' => $xeroSupplier['ContactID'] ?? $supplier->xero_contact_id,
        ])) {
            $this->alignLocalUpdatedAtWithXero($supplier);
        }
    }

    /**
     * Create supplier from Xero contact data
     */
    private function createSupplierFromXero(array $xeroContact, Company $company): Supplier
    {
        $existingSupplier = $this->findExistingSupplierForXero($xeroContact, $company);
        if ($existingSupplier) {
            $this->updateSupplierFromXero($existingSupplier, $xeroContact);
            return $existingSupplier;
        }

        $supplierData = [
            'company_id' => $company->id,
            'name' => $xeroContact['Name'],
            'email' => $xeroContact['EmailAddress'] ?? null,
            'xero_contact_id' => $xeroContact['ContactID'],
            ...$this->getXeroTimestamps($xeroContact),
        ];

        if (isset($xeroContact['TaxNumber']) && !empty($xeroContact['TaxNumber'])) {
            $supplierData['vat_number'] = $xeroContact['TaxNumber'];
        }

        // Add phone if available
        if (isset($xeroContact['Phones']) && !empty($xeroContact['Phones'])) {
            $phone = collect($xeroContact['Phones'])->first();
            $supplierData['phone'] = $phone['PhoneNumber'] ?? null;
        }

        // Add address if available
        if (isset($xeroContact['Addresses']) && !empty($xeroContact['Addresses'])) {
            $address = collect($xeroContact['Addresses'])->first();
            $supplierData['address'] = $address['AddressLine1'] ?? null;
            $supplierData['city'] = $address['City'] ?? null;
            $supplierData['state'] = $address['Region'] ?? null;
            $supplierData['postal_code'] = $address['PostalCode'] ?? null;
            $supplierData['country'] = $address['Country'] ?? null;
        }

        $supplier = Supplier::create($supplierData);
        $this->alignLocalUpdatedAtWithXero($supplier);
        return $supplier;
    }

    private function findExistingSupplierForXero(array $xeroContact, Company $company): ?Supplier
    {
        $contactId = $xeroContact['ContactID'] ?? null;
        if ($contactId) {
            $byXeroId = Supplier::where('company_id', $company->id)
                ->where('xero_contact_id', $contactId)
                ->first();
            if ($byXeroId) {
                return $byXeroId;
            }
        }

        $email = strtolower(trim((string) ($xeroContact['EmailAddress'] ?? '')));
        if ($email !== '') {
            $byEmail = Supplier::where('company_id', $company->id)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->orderByRaw('CASE WHEN xero_contact_id IS NULL THEN 0 ELSE 1 END')
                ->first();
            if ($byEmail) {
                return $byEmail;
            }
        }

        $name = strtolower(trim((string) ($xeroContact['Name'] ?? '')));
        if ($name !== '') {
            $byName = Supplier::where('company_id', $company->id)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$name])
                ->orderByRaw('CASE WHEN xero_contact_id IS NULL THEN 0 ELSE 1 END')
                ->first();
            if ($byName) {
                return $byName;
            }
        }

        return null;
    }

    /**
     * Create/update JCO contacts from Xero ContactPersons for a customer or supplier.
     * This is intentionally one-way (Xero -> JCO) during import sync.
     */
    private function syncContactPersonsFromXero(array $xeroContact, int $companyId, ?int $customerId = null, ?int $supplierId = null): void
    {
        if (empty($xeroContact['ContactPersons']) || (!is_array($xeroContact['ContactPersons']))) {
            return;
        }

        if (!$customerId && !$supplierId) {
            return;
        }

        foreach ($xeroContact['ContactPersons'] as $contactPerson) {
            $name = trim((string) ($contactPerson['FirstName'] ?? ''));
            $lastName = trim((string) ($contactPerson['LastName'] ?? ''));
            $fullName = trim($name . ' ' . $lastName);

            if ($fullName === '') {
                continue;
            }

            $payload = [
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'supplier_id' => $supplierId,
                'name' => $fullName,
                'email' => $this->normalizeNullableString($contactPerson['EmailAddress'] ?? null),
                'phone' => $this->normalizeNullableString($contactPerson['PhoneNumber'] ?? null),
                'position' => !empty($contactPerson['IncludeInEmails']) ? 'Primary Contact' : null,
                'xero_contact_person_id' => $this->normalizeNullableString($contactPerson['ContactPersonID'] ?? null),
                'is_primary' => false,
            ];

            $existingContact = null;
            if (!empty($payload['xero_contact_person_id'])) {
                $existingContact = Contact::where('company_id', $companyId)
                    ->where('xero_contact_person_id', $payload['xero_contact_person_id'])
                    ->where(function ($query) use ($customerId, $supplierId) {
                        if ($customerId) {
                            $query->where('customer_id', $customerId);
                        } else {
                            $query->where('supplier_id', $supplierId);
                        }
                    })
                    ->first();
            }

            if (!$existingContact) {
                $existingContact = Contact::where('company_id', $companyId)
                    ->where('name', $fullName)
                    ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
                    ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
                    ->first();
            }

            if ($existingContact) {
                $this->updateModelIfChanged($existingContact, $payload, 'contact', [
                    'contact_id' => $existingContact->id,
                    'company_id' => $companyId,
                    'customer_id' => $customerId,
                    'supplier_id' => $supplierId,
                    'xero_contact_person_id' => $payload['xero_contact_person_id'],
                ]);
                continue;
            }

            Contact::create($payload);
        }
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Sync quotes to Xero
     */
    public function syncQuotesToXero(Company $company = null): array
    {
        if (!$this->settings->sync_quotes_to_xero) {
            return ['skipped' => true, 'message' => 'Quote sync to Xero is disabled'];
        }

        $currentCompany = $this->getCompany();
        $maxPerRun = max(1, (int) config('services.xero.quote_export_max_per_run', 150));
        $quoteDelayMs = max(0, (int) config('services.xero.quote_export_delay_ms', 250));
        $quotes = Quote::where('company_id', $currentCompany->id)
            ->where(function ($query) {
                $query->whereNull('xero_quote_id')
                    ->orWhereNull('xero_updated_at')
                    ->orWhereColumn('updated_at', '>', 'xero_updated_at');
            })
            ->orderByDesc('updated_at')
            ->limit($maxPerRun)
            ->with(['customer', 'lineItems'])
            ->get();
        $results = [];
        
        Log::info('Starting quote sync to Xero', [
            'company_id' => $currentCompany->id,
            'quote_count' => $quotes->count(),
            'max_per_run' => $maxPerRun,
        ]);

        if ($quotes->isEmpty()) {
            return ['skipped' => true, 'message' => 'No quote changes to sync to Xero'];
        }

        foreach ($quotes as $index => $quote) {
            try {
                if ($index > 0 && $quoteDelayMs > 0) {
                    usleep($quoteDelayMs * 1000);
                }

                $xeroQuote = $this->createOrUpdateQuoteInXero($quote);
                if (isset($xeroQuote['QuoteID'])) {
                    $quote->update([
                        'xero_quote_id' => $xeroQuote['QuoteID'],
                        ...$this->getXeroTimestamps($xeroQuote),
                    ]);
                    $this->alignLocalUpdatedAtWithXero($quote);
                }
                $results[] = [
                    'quote_id' => $quote->id,
                    'quote_number' => $quote->quote_number,
                    'status' => 'success',
                    'xero_quote_id' => $xeroQuote['QuoteID'] ?? null,
                ];
            } catch (\Exception $e) {
                // Enhanced error logging with more context
                Log::error('Quote sync to Xero failed', [
                    'company_id' => $currentCompany->id,
                    'quote_id' => $quote->id,
                    'quote_number' => $quote->quote_number,
                    'xero_quote_id' => $quote->xero_quote_id,
                    'customer_id' => $quote->customer_id,
                    'has_line_items' => $quote->lineItems()->exists(),
                    'line_items_count' => $quote->lineItems()->count(),
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                $results[] = [
                    'quote_id' => $quote->id,
                    'quote_number' => $quote->quote_number,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Sync quotes from Xero (import existing Xero quotes to app)
     */
    public function syncQuotesFromXero(Company $company = null): array
    {
        if (!$this->settings->sync_quotes_from_xero) {
            return ['skipped' => true, 'message' => 'Quote sync from Xero is disabled'];
        }

        $currentCompany = $this->getCompany();
        $results = [];
        $syncStartedAt = now();
        $syncFullyCompleted = false;

        try {
            $hasAnyLocalQuotes = Quote::where('company_id', $currentCompany->id)->exists();
            $fullSyncCompletedKey = self::getInitialSyncCompletedCacheKey($currentCompany->id, 'quote');
            $cursorKey = self::getInitialSyncCursorCacheKey($currentCompany->id, 'quote');
            $paginationKey = self::getInitialSyncPaginationCacheKey($currentCompany->id, 'quote');
            $lastSync = $this->getLastSyncDatetimeForModule(self::SYNC_MODULE_QUOTES);
            $fullSyncCompleted = (bool) Cache::get($fullSyncCompletedKey, false);
            if (!$fullSyncCompleted && $hasAnyLocalQuotes && !empty($lastSync)) {
                $fullSyncCompleted = true;
                Cache::put($fullSyncCompletedKey, true, now()->addDays(365));
                Log::info('Rehydrated quote full-sync completion state from xero_sync_states', [
                    'company_id' => $currentCompany->id,
                    'last_sync' => $lastSync,
                ]);
            }
            $isInitialQuoteImport = !$hasAnyLocalQuotes;
            $isBackfillMode = $isInitialQuoteImport || !$fullSyncCompleted;
            $cursor = Cache::get($cursorKey, ['page' => 1]);
            // During backfill mode we intentionally avoid If-Modified-Since so older pages are not skipped.
            $ifModifiedSince = $isBackfillMode ? [] : $this->buildIfModifiedSinceHeader($lastSync);

            $page = (int) ($isBackfillMode ? ($cursor['page'] ?? 1) : 1);
            if ($page < 1) {
                $page = 1;
            }
            $pageSize = max(1, min((int) config('services.xero.quote_import_page_size', 50), 100));
            $maxPagesPerRun = max(1, (int) config('services.xero.quote_import_max_pages_per_run', 3));
            $maxQuotesPerRun = max(1, (int) config('services.xero.quote_import_max_quotes_per_run', 150));
            $maxSecondsPerRun = max(5, (int) config('services.xero.quote_import_max_seconds_per_run', 35));
            $pageDelayMs = max(0, (int) config('services.xero.quote_import_page_delay_ms', 300));
            $totalProcessed = 0;
            $pagesProcessed = 0;
            $processedThisRun = 0;
            $startedAt = microtime(true);
            $stopReason = null;
            $hasMorePages = false;

            do {
                if (!$isBackfillMode && $processedThisRun >= $maxQuotesPerRun) {
                    $stopReason = 'max_quotes_per_run_reached';
                    break;
                }
                if (!$isBackfillMode && (microtime(true) - $startedAt) >= $maxSecondsPerRun) {
                    $stopReason = 'max_seconds_per_run_reached';
                    break;
                }

                $response = $this->makeXeroRequest(
                    'get',
                    $this->baseUrl . '/api.xro/2.0/Quotes?page=' . $page . '&pageSize=' . $pageSize . '&summaryOnly=false',
                    [],
                    2,
                    $ifModifiedSince
                );

                if (!$response->successful()) {
                    $errorBody = $response->body();
                    $statusCode = $response->status();
                    
                    if ($statusCode === 403 && str_contains($errorBody, 'AuthenticationUnsuccessful')) {
                        Log::error('Xero authentication failed during quote sync', [
                            'company_id' => $currentCompany->id,
                            'status' => $statusCode,
                            'response' => $errorBody,
                        ]);
                        
                        $this->clearInvalidTokens();
                        
                        return ['skipped' => true, 'message' => 'Xero authentication failed. Please re-authorize your Xero connection in the settings.'];
                    }
                    
                    throw new \Exception('Failed to fetch quotes from Xero: ' . $errorBody);
                }

                $responseData = $response->json();
                $xeroQuotes = $responseData['Quotes'] ?? [];
                
                $pagination = $responseData['Pagination'] ?? null;
                $currentPage = $pagination['Page'] ?? $page;
                $pageCount = $pagination['PageCount'] ?? 1;
                $quotesOnPage = count($xeroQuotes);
                $hasPaginationPageCount = is_array($pagination) && isset($pagination['PageCount']) && is_numeric($pagination['PageCount']);
                $normalizedPageCount = is_numeric($pageCount) ? (int) $pageCount : null;
                if ($isBackfillMode && $hasPaginationPageCount && $normalizedPageCount !== null && $normalizedPageCount >= 1 && $page > $normalizedPageCount) {
                    Log::warning('Quote backfill cursor exceeded available pages; resetting to page 1', [
                        'company_id' => $currentCompany->id,
                        'requested_page' => $page,
                        'reported_current_page' => $currentPage,
                        'reported_page_count' => $normalizedPageCount,
                    ]);
                    $page = 1;
                    Cache::put($cursorKey, ['page' => $page], now()->addDays(7));
                    $hasMorePages = true;
                    continue;
                }
                if ($hasPaginationPageCount) {
                    $hasMorePages = $currentPage < $pageCount;
                } else {
                    $hasMorePages = $quotesOnPage >= $pageSize;
                }
                Cache::put($paginationKey, [
                    'page' => is_numeric($currentPage) ? (int) $currentPage : $page,
                    'page_count' => is_numeric($pageCount) ? (int) $pageCount : null,
                    'item_count' => is_numeric($pagination['ItemCount'] ?? null) ? (int) $pagination['ItemCount'] : null,
                    'page_size' => $pageSize,
                    'captured_at' => now()->toIso8601String(),
                ], now()->addDays(7));
                
                Log::info('Fetched quote page from Xero, processing now', [
                    'company_id' => $currentCompany->id,
                    'requested_page' => $page,
                    'current_page' => $currentPage,
                    'page_count' => $pageCount,
                    'quotes_on_page' => $quotesOnPage,
                    'has_more_pages' => $hasMorePages,
                ]);

                foreach ($xeroQuotes as $xeroQuote) {
                    if (!$isBackfillMode && $processedThisRun >= $maxQuotesPerRun) {
                        $stopReason = 'max_quotes_per_run_reached';
                        break;
                    }
                    if (!$isBackfillMode && (microtime(true) - $startedAt) >= $maxSecondsPerRun) {
                        $stopReason = 'max_seconds_per_run_reached';
                        break;
                    }

                    try {
                        if (empty($xeroQuote['QuoteNumber'])) {
                            continue;
                        }

                        $existingQuote = Quote::where('company_id', $currentCompany->id)
                            ->where('xero_quote_id', $xeroQuote['QuoteID'])
                            ->first();

                        if (!$existingQuote) {
                            $existingQuote = Quote::where('company_id', $currentCompany->id)
                                ->where('quote_number', $xeroQuote['QuoteNumber'])
                                ->first();
                        }

                        if ($existingQuote) {
                            $localLineItemCount = $existingQuote->lineItems()->count();
                            $xeroLineItemCount = isset($xeroQuote['LineItems']) && is_array($xeroQuote['LineItems'])
                                ? count($xeroQuote['LineItems'])
                                : 0;
                            $needsLineItemBackfill = $localLineItemCount === 0 && $xeroLineItemCount > 0;

                            if (!$this->xeroUpdatedAtChanged($existingQuote, $xeroQuote) && !$needsLineItemBackfill) {
                                $results[] = [
                                    'quote_id' => $existingQuote->id,
                                    'quote_number' => $xeroQuote['QuoteNumber'],
                                    'status' => 'skipped',
                                    'message' => 'Quote unchanged in Xero',
                                ];
                                $totalProcessed++;
                                $processedThisRun++;
                                continue;
                            }
                            $this->updateQuoteFromXeroData($existingQuote, $xeroQuote);
                            $results[] = [
                                'quote_id' => $existingQuote->id,
                                'quote_number' => $xeroQuote['QuoteNumber'],
                                'status' => 'updated',
                                'message' => 'Quote updated from Xero and linked to existing quote',
                            ];
                        } else {
                            $quote = $this->createQuoteFromXero($xeroQuote, $currentCompany);
                            $results[] = [
                                'quote_id' => $quote->id,
                                'quote_number' => $xeroQuote['QuoteNumber'],
                                'status' => 'created',
                                'message' => 'Quote imported from Xero',
                            ];
                        }
                        
                        $totalProcessed++;
                        $processedThisRun++;
                    } catch (\Exception $e) {
                        Log::error('Failed to process quote from Xero', [
                            'company_id' => $currentCompany->id,
                            'quote_id' => $xeroQuote['QuoteID'] ?? null,
                            'quote_number' => $xeroQuote['QuoteNumber'] ?? 'Unknown',
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        
                        $results[] = [
                            'quote_number' => $xeroQuote['QuoteNumber'] ?? 'Unknown',
                            'status' => 'error',
                            'error' => $e->getMessage(),
                        ];
                        $processedThisRun++;
                    }
                }

                $pagesProcessed++;
                if (!$isBackfillMode && $pagesProcessed >= $maxPagesPerRun) {
                    $stopReason = 'max_pages_per_run_reached';
                }

                $page++;
                if ($isBackfillMode) {
                    Cache::put($cursorKey, ['page' => $page], now()->addDays(7));
                }

                if ($hasMorePages && !$stopReason && $pageDelayMs > 0) {
                    usleep($pageDelayMs * 1000);
                }
            } while ($hasMorePages && !$stopReason);

            if ($isBackfillMode && !$hasMorePages && !$stopReason) {
                Cache::put($fullSyncCompletedKey, true, now()->addDays(365));
                Cache::forget($cursorKey);
            }

            Log::info('Finished processing all quotes from Xero', [
                'company_id' => $currentCompany->id,
                'mode' => $isBackfillMode ? 'backfill' : 'incremental',
                'total_quotes_processed' => $totalProcessed,
                'processed_this_run' => $processedThisRun,
                'pages_processed' => $pagesProcessed,
                'stop_reason' => $stopReason,
            ]);
            $syncFullyCompleted = !$stopReason && !$hasMorePages;

        } catch (\Exception $e) {
            Log::error('Failed to sync quotes from Xero', [
                'company_id' => $currentCompany->id,
                'error' => $e->getMessage(),
            ]);
            
            return ['skipped' => true, 'message' => 'Failed to sync quotes from Xero: ' . $e->getMessage()];
        }

        if ($syncFullyCompleted) {
            $this->recordSyncDatetimeForModule(self::SYNC_MODULE_QUOTES, $syncStartedAt);
        }

        return $results;
    }

    /**
     * Create or update quote in Xero
     */
    private function createOrUpdateQuoteInXero(Quote $quote): array
    {
        // Ensure line items are loaded
        if (!$quote->relationLoaded('lineItems')) {
            $quote->load('lineItems');
        }
        
        $quote->load(['lineItems.product', 'lineItems.account', 'lineItems.taxRate']);
        
        // Ensure customer is loaded
        if (!$quote->relationLoaded('customer')) {
            $quote->load('customer');
        }
        
        if ($quote->lineItems->isEmpty()) {
            throw new \Exception("Quote '{$quote->quote_number}' has no line items. Cannot sync to Xero.");
        }
        
        // Validate customer exists
        if (!$quote->customer) {
            throw new \Exception("Quote '{$quote->quote_number}' has no customer. Cannot sync to Xero.");
        }
        
        $documentCompanyId = (int) ($quote->company_id ?: $this->settings->company_id);
        $defaultTaxRate = TaxRate::getDefaultSalesForCompany($documentCompanyId);
        $defaultTaxCode = $defaultTaxRate && $defaultTaxRate->xero_tax_rate_id 
            ? $defaultTaxRate->xero_tax_rate_id 
            : ($defaultTaxRate && $defaultTaxRate->code 
                ? $defaultTaxRate->code 
                : 'OUTPUT3');
        
        $fallbackSalesAccountCode = ChartOfAccount::where('company_id', $documentCompanyId)
            ->where('is_active', true)
            ->where('account_code', '1000')
            ->value('account_code')
            ?? ChartOfAccount::getDefaultSalesForCompany($documentCompanyId)?->account_code
            ?? '1000';
        $defaultRoundingAccount = $this->resolveDefaultRoundingAccountForCompany($documentCompanyId);
        $defaultRoundingAccountCode = $defaultRoundingAccount?->account_code;
        $defaultRoundingAccountId = $defaultRoundingAccount?->id;

        $lineItems = [];
        foreach ($quote->lineItems as $lineItem) {
            $accountCode = $fallbackSalesAccountCode;
            if ($lineItem->account_id && $lineItem->account && !empty($lineItem->account->account_code)) {
                $accountCode = $lineItem->account->account_code;
            }
            $isRoundingAdjustmentLine = $this->isRoundingAdjustmentLineDescription($lineItem->description ?? '')
                || ($defaultRoundingAccountId && (int) $lineItem->account_id === (int) $defaultRoundingAccountId);
            if ($isRoundingAdjustmentLine && !empty($defaultRoundingAccountCode)) {
                $accountCode = $defaultRoundingAccountCode;
            }
            $taxTypeCode = $this->resolveXeroTaxTypeForLineItem($lineItem, $defaultTaxCode, $documentCompanyId);
            
            $lineItemData = [
                'Description' => $lineItem->description,
                'Quantity' => $lineItem->quantity,
                'UnitAmount' => $lineItem->unit_price,
                'LineAmount' => $lineItem->total,
                'AccountCode' => $accountCode,
                'TaxType' => $taxTypeCode,
            ];

            $itemCode = $this->resolveXeroItemCodeForLineItem($lineItem);
            if (!empty($itemCode)) {
                $lineItemData['ItemCode'] = $itemCode;
            }

            $lineItems[] = $lineItemData;
        }

        $quoteData = [
            'Contact' => [
                'ContactID' => $this->getXeroContactId($quote->customer),
            ],
            'Date' => $quote->created_at->format('Y-m-d'),
            'ExpiryDate' => $quote->expiry_date ? $quote->expiry_date->format('Y-m-d') : null,
            'LineItems' => $lineItems,
            'SubTotal' => $quote->subtotal,
            'TotalTax' => $quote->tax_amount,
            'Total' => $quote->total,
            'Status' => $this->mapQuoteStatus($quote->status),
            'QuoteNumber' => $quote->quote_number,
        ];

        Log::info('Creating/updating quote in Xero', [
            'quote_id' => $quote->id,
            'quote_number' => $quote->quote_number,
            'xero_quote_id' => $quote->xero_quote_id,
            'customer_id' => $quote->customer_id,
            'line_items_count' => count($lineItems),
            'subtotal' => $quote->subtotal,
            'tax_amount' => $quote->tax_amount,
            'total' => $quote->total,
        ]);
        
        // If quote already has a Xero ID, update it; otherwise create new
        if ($quote->xero_quote_id) {
            $quoteData['QuoteID'] = $quote->xero_quote_id;

            // Xero can reject status codes during quote updates.
            // Keep status immutable on update and only sync editable fields.
            unset($quoteData['Status']);
        }
        
        $response = $this->makeXeroRequest('post', $this->baseUrl . '/api.xro/2.0/Quotes', [
            'Quotes' => [$quoteData]
        ]);

        if (!$response->successful()) {
            $errorBody = $response->body();
            $statusCode = $response->status();

            // Defensive fallback: if Xero rejects status code, retry once without Status.
            if (
                isset($quoteData['Status'])
                && str_contains($errorBody, 'Please provide a valid Status Code')
            ) {
                $retryPayload = $quoteData;
                unset($retryPayload['Status']);

                $retryResponse = $this->makeXeroRequest('post', $this->baseUrl . '/api.xro/2.0/Quotes', [
                    'Quotes' => [$retryPayload],
                ]);

                if ($retryResponse->successful()) {
                    $retryResult = $retryResponse->json();
                    return $retryResult['Quotes'][0] ?? [];
                }
            }
            
            Log::error('Failed to create/update quote in Xero', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'status' => $statusCode,
                'response' => $errorBody,
            ]);
            
            throw new \Exception('Failed to create/update quote in Xero: ' . $errorBody);
        }

        $result = $response->json();
        $xeroQuote = $result['Quotes'][0];
        
        // Store the Xero quote ID if it's new
        if (!$quote->xero_quote_id && isset($xeroQuote['QuoteID'])) {
            $quote->update(['xero_quote_id' => $xeroQuote['QuoteID']]);
        }
        
        return $xeroQuote;
    }

    /**
     * Map quote status to Xero status
     */
    private function mapQuoteStatus(string $status): string
    {
        return match($status) {
            'draft' => 'DRAFT',
            'sent' => 'SENT',
            'accepted' => 'ACCEPTED',
            'rejected' => 'DECLINED',
            // Xero derives expiry from ExpiryDate; EXPIRED is not a reliable write status.
            'expired' => 'SENT',
            default => 'DRAFT',
        };
    }

    /**
     * Map Xero quote status to local status
     */
    private function mapXeroQuoteStatusToLocal(string $xeroStatus): string
    {
        return match($xeroStatus) {
            'DRAFT' => 'draft',
            'SENT' => 'sent',
            'ACCEPTED' => 'accepted',
            'DECLINED' => 'rejected',
            'EXPIRED' => 'expired',
            default => 'draft',
        };
    }

    /**
     * Update quote from Xero data
     */
    private function updateQuoteFromXeroData(Quote $quote, array $xeroQuote): void
    {
        $subtotal = $xeroQuote['SubTotal'] ?? $quote->subtotal;
        $taxAmount = $xeroQuote['TotalTax'] ?? $quote->tax_amount;
        
        // Calculate tax rate from tax amount and subtotal
        $taxRate = $quote->tax_rate;
        if ($subtotal > 0 && $taxAmount > 0) {
            $taxRate = ($taxAmount / $subtotal) * 100;
        }

        $updateData = [
            'status' => $this->mapXeroQuoteStatusToLocal($xeroQuote['Status'] ?? $quote->status),
            'total' => $xeroQuote['Total'] ?? $quote->total,
            'subtotal' => $subtotal,
            'tax_rate' => round($taxRate, 2),
            'tax_amount' => $taxAmount,
            'xero_quote_id' => $xeroQuote['QuoteID'] ?? $quote->xero_quote_id,
            ...$this->getXeroTimestamps($xeroQuote),
        ];

        // Update dates if available
        if (isset($xeroQuote['DateString'])) {
            // Date is already set from created_at, skip
        }
        if (isset($xeroQuote['ExpiryDateString'])) {
            $updateData['expiry_date'] = \Carbon\Carbon::parse($xeroQuote['ExpiryDateString'])->format('Y-m-d');
        }

        $updated = $this->updateModelIfChanged($quote, $updateData, 'quote', [
            'quote_id' => $quote->id,
            'xero_quote_id' => $xeroQuote['QuoteID'] ?? $quote->xero_quote_id,
        ]);
        if ($updated) {
            $this->alignLocalUpdatedAtWithXero($quote);
        }

        if (isset($xeroQuote['LineItems']) && is_array($xeroQuote['LineItems'])) {
            if (count($xeroQuote['LineItems']) === 0) {
                Log::info('Skipping quote line item replacement due to empty Xero LineItems payload', [
                    'quote_id' => $quote->id,
                    'quote_number' => $quote->quote_number,
                    'xero_quote_id' => $xeroQuote['QuoteID'] ?? $quote->xero_quote_id,
                    'decision_reason' => 'skip_noop',
                ]);
            } else {
                $quote->lineItems()->delete();
                foreach ($xeroQuote['LineItems'] as $index => $xeroLineItem) {
                    \App\Models\QuoteLineItem::create([
                        'quote_id' => $quote->id,
                        'description' => $xeroLineItem['Description'] ?? '',
                        'quantity' => $xeroLineItem['Quantity'] ?? 1,
                        'unit_price' => $xeroLineItem['UnitAmount'] ?? 0,
                        'total' => $xeroLineItem['LineAmount'] ?? 0,
                        'account_id' => $this->resolveAccountId($xeroLineItem['AccountCode'] ?? null, $quote->company_id),
                        'sort_order' => $index,
                    ]);
                }
            }
        }
    }

    /**
     * Create quote from Xero quote data
     */
    private function createQuoteFromXero(array $xeroQuote, Company $company): Quote
    {
        // Find customer by Xero contact ID
        $customer = Customer::where('company_id', $company->id)
            ->where('xero_contact_id', $xeroQuote['Contact']['ContactID'])
            ->first();

        // If customer doesn't exist, fetch from Xero and create it
        if (!$customer) {
            $xeroContactId = $xeroQuote['Contact']['ContactID'];
            Log::info('Customer not found locally, fetching from Xero', [
                'company_id' => $company->id,
                'xero_contact_id' => $xeroContactId,
                'quote_number' => $xeroQuote['QuoteNumber'] ?? 'Unknown',
            ]);
            
            // Fetch the contact from Xero
            $xeroContact = $this->getXeroContact($xeroContactId);
            
            if (!$xeroContact) {
                throw new \Exception("Customer with Xero contact ID {$xeroContactId} not found in Xero. Cannot create quote.");
            }
            
            // Create the customer from Xero contact data
            $customer = $this->createCustomerFromXero($xeroContact, $company);
            
            Log::info('Created customer from Xero during quote import', [
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'xero_contact_id' => $xeroContactId,
            ]);
        }

        $subtotal = $xeroQuote['SubTotal'] ?? 0;
        $taxAmount = $xeroQuote['TotalTax'] ?? 0;
        
        // Calculate tax rate from tax amount and subtotal
        $taxRate = 0;
        if ($subtotal > 0 && $taxAmount > 0) {
            $taxRate = ($taxAmount / $subtotal) * 100;
        }

        $quoteData = [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'quote_number' => $xeroQuote['QuoteNumber'],
            'xero_quote_id' => $xeroQuote['QuoteID'],
            'title' => $xeroQuote['Reference'] ?? 'Quote from Xero',
            'status' => $this->mapXeroQuoteStatusToLocal($xeroQuote['Status'] ?? 'DRAFT'),
            'subtotal' => $subtotal,
            'tax_rate' => round($taxRate, 2),
            'tax_amount' => $taxAmount,
            'total' => $xeroQuote['Total'] ?? 0,
            ...$this->getXeroTimestamps($xeroQuote),
        ];

        if (isset($xeroQuote['ExpiryDateString'])) {
            $quoteData['expiry_date'] = \Carbon\Carbon::parse($xeroQuote['ExpiryDateString'])->format('Y-m-d');
        }

        $quote = Quote::create($quoteData);
        $this->alignLocalUpdatedAtWithXero($quote);

        // Create line items
        if (isset($xeroQuote['LineItems']) && is_array($xeroQuote['LineItems'])) {
            foreach ($xeroQuote['LineItems'] as $index => $xeroLineItem) {
                \App\Models\QuoteLineItem::create([
                    'quote_id' => $quote->id,
                    'description' => $xeroLineItem['Description'] ?? '',
                    'quantity' => $xeroLineItem['Quantity'] ?? 1,
                    'unit_price' => $xeroLineItem['UnitAmount'] ?? 0,
                    'total' => $xeroLineItem['LineAmount'] ?? 0,
                    'account_id' => $this->resolveAccountId($xeroLineItem['AccountCode'] ?? null, $company->id),
                    'sort_order' => $index,
                ]);
            }
        }

        // Don't recalculate totals - we already have the correct values from Xero
        // The tax_rate is set correctly above, so if totals need recalculation later,
        // they will be correct

        return $quote;
    }

    /**
     * Create invoice from Xero invoice data
     */
    private function createInvoiceFromXero(array $xeroInvoice, Company $company): Invoice
    {
        // Find customer by Xero contact ID
        $customer = Customer::where('company_id', $company->id)
            ->where('xero_contact_id', $xeroInvoice['Contact']['ContactID'])
            ->first();

        // If customer doesn't exist, fetch from Xero and create it
        if (!$customer) {
            $xeroContactId = $xeroInvoice['Contact']['ContactID'];
            Log::info('Customer not found locally, fetching from Xero', [
                'company_id' => $company->id,
                'xero_contact_id' => $xeroContactId,
                'invoice_number' => $xeroInvoice['InvoiceNumber'] ?? $xeroInvoice['Reference'] ?? 'Unknown',
            ]);
            
            // Fetch the contact from Xero
            $xeroContact = $this->getXeroContact($xeroContactId);
            
            if (!$xeroContact) {
                throw new \Exception("Customer with Xero contact ID {$xeroContactId} not found in Xero. Cannot create invoice.");
            }
            
            // Create the customer from Xero contact data
            $customer = $this->createCustomerFromXero($xeroContact, $company);
            
            Log::info('Created customer from Xero during invoice import', [
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'xero_contact_id' => $xeroContactId,
            ]);
        }

        $subtotal = $xeroInvoice['SubTotal'] ?? 0;
        $taxAmount = $xeroInvoice['TotalTax'] ?? 0;
        $total = $xeroInvoice['Total'] ?? 0;
        
        // Calculate discount amount if available
        $discountAmount = 0;
        if (isset($xeroInvoice['TotalDiscount'])) {
            $discountAmount = $xeroInvoice['TotalDiscount'];
        }
        
        // Calculate tax rate from tax amount and subtotal
        $taxRate = 0;
        if ($subtotal > 0 && $taxAmount > 0) {
            $taxRate = ($taxAmount / $subtotal) * 100;
        }

        // Get invoice number (prefer InvoiceNumber, fallback to Reference)
        $invoiceNumber = $xeroInvoice['InvoiceNumber'] ?? $xeroInvoice['Reference'] ?? null;
        if (!$invoiceNumber) {
            throw new \Exception('Invoice from Xero has no InvoiceNumber or Reference');
        }

        $invoiceData = [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'invoice_number' => $invoiceNumber,
            'xero_invoice_id' => $xeroInvoice['InvoiceID'],
            'title' => $xeroInvoice['Reference'] ?? 'Invoice from Xero',
            'status' => $this->mapXeroStatusToLocal($xeroInvoice['Status'] ?? 'AUTHORISED'),
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_rate' => round($taxRate, 2),
            'tax_amount' => $taxAmount,
            'total' => $total,
            ...$this->getXeroTimestamps($xeroInvoice),
        ];

        // Set invoice date
        if (isset($xeroInvoice['DateString'])) {
            $invoiceData['invoice_date'] = \Carbon\Carbon::parse($xeroInvoice['DateString'])->format('Y-m-d');
        } elseif (isset($xeroInvoice['Date'])) {
            $invoiceData['invoice_date'] = \Carbon\Carbon::parse($xeroInvoice['Date'])->format('Y-m-d');
        } else {
            $invoiceData['invoice_date'] = now()->format('Y-m-d');
        }

        // Set due date
        if (isset($xeroInvoice['DueDateString'])) {
            $invoiceData['due_date'] = \Carbon\Carbon::parse($xeroInvoice['DueDateString'])->format('Y-m-d');
        } elseif (isset($xeroInvoice['DueDate'])) {
            $invoiceData['due_date'] = \Carbon\Carbon::parse($xeroInvoice['DueDate'])->format('Y-m-d');
        } else {
            // Default to invoice date + 30 days if not set
            $invoiceData['due_date'] = \Carbon\Carbon::parse($invoiceData['invoice_date'])->addDays(30)->format('Y-m-d');
        }

        // Set salesperson if available (from Contact's Salesperson field or similar)
        // Note: This might need to be adjusted based on your Xero data structure

        $invoice = Invoice::create($invoiceData);
        $this->alignLocalUpdatedAtWithXero($invoice);

        // Create line items
        if (isset($xeroInvoice['LineItems']) && is_array($xeroInvoice['LineItems'])) {
            foreach ($xeroInvoice['LineItems'] as $index => $xeroLineItem) {
                // Try to find product by Xero item ID or SKU
                $product = null;
                
                // Xero line items can have ItemID/Code directly or nested in Item object
                // ItemID is the Xero item ID, Code is the SKU
                $itemId = $xeroLineItem['ItemID'] ?? $xeroLineItem['Item']['ItemID'] ?? null;
                $itemCode = $xeroLineItem['Code'] ?? $xeroLineItem['ItemCode'] ?? $xeroLineItem['Item']['Code'] ?? null;
                
                // First try to match by Xero ItemID (xero_item_id)
                if (!empty($itemId)) {
                    $product = Product::where('company_id', $company->id)
                        ->where('xero_item_id', $itemId)
                        ->first();
                    
                    Log::debug('Trying to match product by ItemID', [
                        'item_id' => $itemId,
                        'found_product' => $product ? $product->id : null,
                        'company_id' => $company->id,
                    ]);
                }
                
                // If not found by ItemID, try to match by SKU (Code)
                if (!$product && !empty($itemCode)) {
                    $product = Product::where('company_id', $company->id)
                        ->where('sku', $itemCode)
                        ->first();
                    
                    Log::debug('Trying to match product by Code/SKU', [
                        'item_code' => $itemCode,
                        'found_product' => $product ? $product->id : null,
                        'company_id' => $company->id,
                    ]);
                }
                
                // Fallback: If no ItemID or Code, try matching by product name/description
                // This handles custom line items that weren't linked to Xero Items
                if (!$product && !empty($xeroLineItem['Description'])) {
                    $description = trim($xeroLineItem['Description']);
                    // Try exact match first (case-insensitive)
                    $product = Product::where('company_id', $company->id)
                        ->whereRaw('LOWER(name) = ?', [strtolower($description)])
                        ->first();
                    
                    // If no exact match, try partial match
                    if (!$product) {
                        $product = Product::where('company_id', $company->id)
                            ->where(function($query) use ($description) {
                                $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($description) . '%'])
                                      ->orWhereRaw('LOWER(description) LIKE ?', ['%' . strtolower($description) . '%']);
                            })
                            ->first();
                    }
                    
                    if ($product) {
                        Log::debug('Matched product by name/description', [
                            'description' => $description,
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'company_id' => $company->id,
                        ]);
                    }
                }
                
                // Log if no product found - include full line item structure for debugging
                if (!$product) {
                    Log::info('No product match found for line item', [
                        'description' => $xeroLineItem['Description'] ?? '',
                        'item_id' => $itemId,
                        'item_code' => $itemCode,
                        'line_item_keys' => array_keys($xeroLineItem),
                        'has_item_object' => isset($xeroLineItem['Item']),
                        'item_object_keys' => isset($xeroLineItem['Item']) ? array_keys($xeroLineItem['Item']) : [],
                        'full_line_item' => $xeroLineItem, // Full structure for debugging
                    ]);
                } else {
                    Log::info('Product matched for line item', [
                        'description' => $xeroLineItem['Description'] ?? '',
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'matched_by' => !empty($itemId) ? 'ItemID' : (!empty($itemCode) ? 'Code/SKU' : 'Name/Description'),
                        'item_id' => $itemId,
                        'item_code' => $itemCode,
                    ]);
                }
                
                // Calculate discount for line item
                $lineItemTotal = $xeroLineItem['LineAmount'] ?? 0;
                $lineItemQuantity = $xeroLineItem['Quantity'] ?? 1;
                $lineItemUnitPrice = $xeroLineItem['UnitAmount'] ?? 0;
                $lineItemSubtotal = $lineItemQuantity * $lineItemUnitPrice;
                $lineItemDiscountAmount = $lineItemSubtotal - $lineItemTotal;
                $lineItemDiscountPercentage = 0;
                if ($lineItemSubtotal > 0) {
                    $lineItemDiscountPercentage = ($lineItemDiscountAmount / $lineItemSubtotal) * 100;
                }

                \App\Models\InvoiceLineItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $product?->id,
                    'description' => $xeroLineItem['Description'] ?? '',
                    'quantity' => $lineItemQuantity,
                    'unit_price' => $lineItemUnitPrice,
                    'discount_amount' => round($lineItemDiscountAmount, 2),
                    'discount_percentage' => round($lineItemDiscountPercentage, 2),
                    'total' => $lineItemTotal,
                    'tax_rate_id' => $this->resolveTaxRateId($xeroLineItem['TaxType'] ?? null, $company->id),
                    'tax_amount' => (float) ($xeroLineItem['TaxAmount'] ?? 0),
                    'account_id' => $this->resolveAccountId($xeroLineItem['AccountCode'] ?? null, $company->id),
                    'sort_order' => $index,
                ]);
            }
        }

        // Don't recalculate totals - we already have the correct values from Xero
        // The tax_rate is set correctly above, so if totals need recalculation later,
        // they will be correct

        return $invoice;
    }

    /**
     * Get Xero quote by ID
     */
    private function getXeroQuote(string $quoteId): ?array
    {
        try {
            $response = $this->makeXeroRequest('get', $this->baseUrl . '/api.xro/2.0/Quotes/' . $quoteId);

            if ($response->successful()) {
                $result = $response->json();
                return $result['Quotes'][0] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('Failed to get Xero quote', [
                'quote_id' => $quoteId,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Get Xero invoice by ID using filtered list endpoint (avoids /Invoices/{id})
     */
    private function getXeroInvoice(string $invoiceId): ?array
    {
        try {
            $response = $this->makeXeroRequest(
                'get',
                $this->baseUrl . '/api.xro/2.0/Invoices?where=' . rawurlencode('InvoiceID==Guid("' . $invoiceId . '")') . '&summaryOnly=false'
            );

            if ($response->successful()) {
                $result = $response->json();
                return $result['Invoices'][0] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('Failed to get Xero invoice', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    private function getXeroCreditNote(string $creditNoteId): ?array
    {
        try {
            $response = $this->makeXeroRequest('get', $this->baseUrl . '/api.xro/2.0/CreditNotes/' . $creditNoteId);
            if ($response->successful()) {
                $result = $response->json();
                return $result['CreditNotes'][0] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('Failed to get Xero credit note', [
                'credit_note_id' => $creditNoteId,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    private function getXeroPurchaseOrder(string $purchaseOrderId): ?array
    {
        try {
            $response = $this->makeXeroRequest('get', $this->baseUrl . '/api.xro/2.0/PurchaseOrders/' . $purchaseOrderId);
            if ($response->successful()) {
                $result = $response->json();
                return $result['PurchaseOrders'][0] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('Failed to get Xero purchase order', [
                'purchase_order_id' => $purchaseOrderId,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    private function hydrateInvoiceDetails(array $xeroInvoice): array
    {
        // Keep import request volume predictable; do not fetch per-invoice detail.
        return $xeroInvoice;
    }

    /**
     * Update invoice from Xero data
     */
    private function updateInvoiceFromXeroData(Invoice $invoice, array $xeroInvoice): void
    {
        $subtotal = $xeroInvoice['SubTotal'] ?? $invoice->subtotal;
        $taxAmount = $xeroInvoice['TotalTax'] ?? $invoice->tax_amount;
        $total = $xeroInvoice['Total'] ?? $invoice->total;
        
        // Calculate discount amount if available
        $discountAmount = $invoice->discount_amount;
        if (isset($xeroInvoice['TotalDiscount'])) {
            $discountAmount = $xeroInvoice['TotalDiscount'];
        }
        
        // Calculate tax rate from tax amount and subtotal
        $taxRate = $invoice->tax_rate;
        if ($subtotal > 0 && $taxAmount > 0) {
            $taxRate = ($taxAmount / $subtotal) * 100;
        }

        $updateData = [
            'status' => $this->mapXeroStatusToLocal($xeroInvoice['Status'] ?? $invoice->status),
            'total' => $total,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_rate' => round($taxRate, 2),
            'tax_amount' => $taxAmount,
            ...$this->getXeroTimestamps($xeroInvoice),
        ];

        // Update Xero invoice ID if not set
        if (!$invoice->xero_invoice_id && isset($xeroInvoice['InvoiceID'])) {
            $updateData['xero_invoice_id'] = $xeroInvoice['InvoiceID'];
        }

        // Update dates if available
        if (isset($xeroInvoice['DateString'])) {
            $updateData['invoice_date'] = \Carbon\Carbon::parse($xeroInvoice['DateString'])->format('Y-m-d');
        } elseif (isset($xeroInvoice['Date'])) {
            $updateData['invoice_date'] = \Carbon\Carbon::parse($xeroInvoice['Date'])->format('Y-m-d');
        }
        if (isset($xeroInvoice['DueDateString'])) {
            $updateData['due_date'] = \Carbon\Carbon::parse($xeroInvoice['DueDateString'])->format('Y-m-d');
        } elseif (isset($xeroInvoice['DueDate'])) {
            $updateData['due_date'] = \Carbon\Carbon::parse($xeroInvoice['DueDate'])->format('Y-m-d');
        }

        $updated = $this->updateModelIfChanged($invoice, $updateData, 'invoice', [
            'invoice_id' => $invoice->id,
            'xero_invoice_id' => $xeroInvoice['InvoiceID'] ?? $invoice->xero_invoice_id,
        ]);
        if ($updated) {
            $this->alignLocalUpdatedAtWithXero($invoice);
        }
        
        // Update line items only when Xero sends non-empty line item arrays.
        // Some list endpoints can include LineItems: [] even when the invoice has lines.
        if (isset($xeroInvoice['LineItems']) && is_array($xeroInvoice['LineItems'])) {
            if (count($xeroInvoice['LineItems']) === 0) {
                Log::warning('Skipping invoice line item replacement due to empty Xero LineItems payload', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'xero_invoice_id' => $xeroInvoice['InvoiceID'] ?? $invoice->xero_invoice_id,
                ]);
            } else {
                // Delete existing line items and recreate from Xero data
                $invoice->lineItems()->delete();

                foreach ($xeroInvoice['LineItems'] as $index => $xeroLineItem) {
                // Try to find product by Xero item ID or SKU
                $product = null;
                
                // Xero line items can have ItemID/Code directly or nested in Item object
                // ItemID is the Xero item ID, Code is the SKU
                $itemId = $xeroLineItem['ItemID'] ?? $xeroLineItem['Item']['ItemID'] ?? null;
                $itemCode = $xeroLineItem['Code'] ?? $xeroLineItem['ItemCode'] ?? $xeroLineItem['Item']['Code'] ?? null;
                
                // First try to match by Xero ItemID (xero_item_id)
                if (!empty($itemId)) {
                    $product = Product::where('company_id', $invoice->company_id)
                        ->where('xero_item_id', $itemId)
                        ->first();
                    
                    Log::debug('Trying to match product by ItemID (update)', [
                        'item_id' => $itemId,
                        'found_product' => $product ? $product->id : null,
                        'company_id' => $invoice->company_id,
                    ]);
                }
                
                // If not found by ItemID, try to match by SKU (Code)
                if (!$product && !empty($itemCode)) {
                    $product = Product::where('company_id', $invoice->company_id)
                        ->where('sku', $itemCode)
                        ->first();
                    
                    Log::debug('Trying to match product by Code/SKU (update)', [
                        'item_code' => $itemCode,
                        'found_product' => $product ? $product->id : null,
                        'company_id' => $invoice->company_id,
                    ]);
                }
                
                // Fallback: If no ItemID or Code, try matching by product name/description
                // This handles custom line items that weren't linked to Xero Items
                if (!$product && !empty($xeroLineItem['Description'])) {
                    $description = trim($xeroLineItem['Description']);
                    // Try exact match first (case-insensitive)
                    $product = Product::where('company_id', $invoice->company_id)
                        ->whereRaw('LOWER(name) = ?', [strtolower($description)])
                        ->first();
                    
                    // If no exact match, try partial match
                    if (!$product) {
                        $product = Product::where('company_id', $invoice->company_id)
                            ->where(function($query) use ($description) {
                                $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($description) . '%'])
                                      ->orWhereRaw('LOWER(description) LIKE ?', ['%' . strtolower($description) . '%']);
                            })
                            ->first();
                    }
                    
                    if ($product) {
                        Log::debug('Matched product by name/description (update)', [
                            'description' => $description,
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'company_id' => $invoice->company_id,
                        ]);
                    }
                }
                
                // Log if no product found - include full line item structure for debugging
                if (!$product) {
                    Log::info('No product match found for line item (update)', [
                        'description' => $xeroLineItem['Description'] ?? '',
                        'item_id' => $itemId,
                        'item_code' => $itemCode,
                        'line_item_keys' => array_keys($xeroLineItem),
                        'has_item_object' => isset($xeroLineItem['Item']),
                        'item_object_keys' => isset($xeroLineItem['Item']) ? array_keys($xeroLineItem['Item']) : [],
                        'full_line_item' => $xeroLineItem, // Full structure for debugging
                    ]);
                } else {
                    Log::info('Product matched for line item (update)', [
                        'description' => $xeroLineItem['Description'] ?? '',
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'matched_by' => !empty($itemId) ? 'ItemID' : (!empty($itemCode) ? 'Code/SKU' : 'Name/Description'),
                        'item_id' => $itemId,
                        'item_code' => $itemCode,
                    ]);
                }
                
                // Calculate discount for line item
                $lineItemTotal = $xeroLineItem['LineAmount'] ?? 0;
                $lineItemQuantity = $xeroLineItem['Quantity'] ?? 1;
                $lineItemUnitPrice = $xeroLineItem['UnitAmount'] ?? 0;
                $lineItemSubtotal = $lineItemQuantity * $lineItemUnitPrice;
                $lineItemDiscountAmount = $lineItemSubtotal - $lineItemTotal;
                $lineItemDiscountPercentage = 0;
                if ($lineItemSubtotal > 0) {
                    $lineItemDiscountPercentage = ($lineItemDiscountAmount / $lineItemSubtotal) * 100;
                }

                    \App\Models\InvoiceLineItem::create([
                        'invoice_id' => $invoice->id,
                        'product_id' => $product?->id,
                        'description' => $xeroLineItem['Description'] ?? '',
                        'quantity' => $lineItemQuantity,
                        'unit_price' => $lineItemUnitPrice,
                        'discount_amount' => round($lineItemDiscountAmount, 2),
                        'discount_percentage' => round($lineItemDiscountPercentage, 2),
                        'total' => $lineItemTotal,
                        'tax_rate_id' => $this->resolveTaxRateId($xeroLineItem['TaxType'] ?? null, $invoice->company_id),
                        'tax_amount' => (float) ($xeroLineItem['TaxAmount'] ?? 0),
                        'account_id' => $this->resolveAccountId($xeroLineItem['AccountCode'] ?? null, $invoice->company_id),
                        'sort_order' => $index,
                    ]);
                }
            }
        }
    }

    /**
     * Get Xero item by ID
     */
    /**
     * Get Xero item by ID (used for individual lookups when needed)
     */
    private function getXeroItem(string $itemId): ?array
    {
        try {
            $response = $this->makeXeroRequest('get', $this->baseUrl . '/api.xro/2.0/Items/' . $itemId);

            if ($response->successful()) {
                $result = $response->json();
                return $result['Items'][0] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('Failed to get Xero item', [
                'item_id' => $itemId,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Update product from Xero data
     */
    private function updateProductFromXero(Product $product, array $xeroItem): void
    {
        $updateData = [
            'name' => $xeroItem['Name'] ?? $product->name,
            'description' => $xeroItem['Description'] ?? $product->description,
            'sku' => $xeroItem['Code'] ?? $product->sku,
            'xero_item_id' => $xeroItem['ItemID'] ?? $product->xero_item_id,
            'sales_account_code' => $xeroItem['SalesDetails']['AccountCode'] ?? $product->sales_account_code,
            'purchase_account_code' => $xeroItem['PurchaseDetails']['AccountCode'] ?? $product->purchase_account_code,
            ...$this->getXeroTimestamps($xeroItem),
        ];

        // Update pricing if available
        if (isset($xeroItem['SalesDetails']['UnitPrice'])) {
            $updateData['price'] = $xeroItem['SalesDetails']['UnitPrice'];
        }
        if (isset($xeroItem['PurchaseDetails']['UnitPrice'])) {
            $updateData['cost'] = $xeroItem['PurchaseDetails']['UnitPrice'];
        }

        if ($this->updateModelIfChanged($product, $updateData, 'product', [
            'product_id' => $product->id,
            'xero_item_id' => $xeroItem['ItemID'] ?? $product->xero_item_id,
        ])) {
            $this->alignLocalUpdatedAtWithXero($product);
        }
    }

    /**
     * Create product from Xero item data
     */
    private function createProductFromXero(array $xeroItem, Company $company): Product
    {
        $productData = [
            'company_id' => $company->id,
            'name' => $xeroItem['Name'],
            'description' => $xeroItem['Description'] ?? null,
            'sku' => $xeroItem['Code'] ?? null,
            'xero_item_id' => $xeroItem['ItemID'],
            'sales_account_code' => $xeroItem['SalesDetails']['AccountCode'] ?? '1000',
            'purchase_account_code' => $xeroItem['PurchaseDetails']['AccountCode'] ?? null,
            ...$this->getXeroTimestamps($xeroItem),
            'price' => 0
        ];

        // Add pricing if available
        if (isset($xeroItem['SalesDetails']['UnitPrice'])) {
            $productData['price'] = $xeroItem['SalesDetails']['UnitPrice'];
        }
        if (isset($xeroItem['PurchaseDetails']['UnitPrice'])) {
            $productData['cost'] = $xeroItem['PurchaseDetails']['UnitPrice'];
        }

        $product = Product::create($productData);
        $this->alignLocalUpdatedAtWithXero($product);
        return $product;
    }

    /**
     * Sync payments to Xero for a specific invoice
     */
    public function syncPaymentsToXero(Invoice $invoice, ?array $xeroInvoice = null): array
    {
        if (!$this->settings->sync_invoices_to_xero) {
            return ['skipped' => true, 'message' => 'Invoice sync to Xero is disabled'];
        }

        if (!$invoice->xero_invoice_id) {
            return ['error' => 'Invoice has not been synced to Xero yet'];
        }

        $results = [];
        // Do not pass cached xeroInvoice - each payment needs fresh AmountDue after prior payments in batch
        $payments = $invoice->payments()
            ->where(function ($query) {
                $query->whereNull('xero_payment_id')
                    ->orWhereNull('xero_synced_at')
                    ->orWhereColumn('payments.updated_at', '>', 'payments.xero_synced_at');
            })
            ->orderBy('id')
            ->get()
            ->filter(fn (Payment $payment) => $this->hasSignificantLocalSyncDrift($payment->updated_at, $payment->xero_synced_at))
            ->sortBy(fn (Payment $payment) => empty($payment->xero_payment_id) ? 0 : 1)
            ->values();

        if ($payments->isEmpty()) {
            return [[
                'invoice_id' => $invoice->id,
                'status' => 'skipped',
                'message' => 'No unsynced payment changes found',
            ]];
        }

        foreach ($payments as $payment) {
            try {
                $result = $this->createPaymentInXero($invoice, $payment, null);
                
                // Use the result from createPaymentInXero (which may include 'skipped' status)
                $results[] = $result;
                
            } catch (\Exception $e) {
                Log::error('Payment sync failed', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
                
                $results[] = [
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Create payment in Xero
     */
    private function createPaymentInXero(Invoice $invoice, Payment $payment, ?array $xeroInvoice = null): array
    {
        if (!empty($payment->xero_payment_id) && !empty($payment->xero_synced_at) && !$this->hasSignificantLocalSyncDrift($payment->updated_at, $payment->xero_synced_at)) {
            return [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'status' => 'skipped',
                'message' => 'Payment already synced to Xero',
            ];
        }

        $amountToSend = round((float) $payment->amount, 2);
        if ($amountToSend <= 0) {
            return [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'status' => 'skipped',
                'message' => 'Payment amount is zero',
            ];
        }

        // Get the default bank account for the company
        // Use company from XeroSettings
        $currentCompany = $this->getCompany();
        $defaultBankAccount = BankAccount::getDefaultForCompany($currentCompany->id);
        
        if (!$defaultBankAccount || !$defaultBankAccount->xero_account_id) {
            throw new \Exception("No default bank account configured with Xero account ID. Please set a default bank account with Xero integration in Bank Accounts settings.");
        }
        
        // Log the payment details for debugging
        Log::info('Creating payment in Xero', [
            'payment_id' => $payment->id,
            'payment_method' => $payment->payment_method,
            'bank_account_id' => $defaultBankAccount->id,
            'xero_account_id' => $defaultBankAccount->xero_account_id,
            'amount' => $amountToSend,
            'original_amount' => $payment->amount,
            'invoice_id' => $invoice->id,
            'xero_invoice_id' => $invoice->xero_invoice_id,
        ]);
        
        $paymentData = [
            'Invoice' => [
                'InvoiceID' => $invoice->xero_invoice_id,
            ],
            'Account' => [
                'AccountID' => $defaultBankAccount->xero_account_id,
            ],
            'Date' => $payment->payment_date->format('Y-m-d'),
            'Amount' => $amountToSend,
            'Reference' => 'Payment for ' . $invoice->invoice_number,
        ];

        // Add notes if available
        if ($payment->notes) {
            $paymentData['Reference'] .= ' - ' . $payment->notes;
        }

        $response = $this->makeXeroRequest('post', $this->baseUrl . '/api.xro/2.0/Payments', [
            'Payments' => [$paymentData]
        ]);

        if (!$response->successful()) {
            $errorBody = $response->body();
            Log::error('Xero payment creation failed', [
                'payment_id' => $payment->id,
                'bank_account_id' => $defaultBankAccount->id,
                'xero_account_id' => $defaultBankAccount->xero_account_id,
                'payment_method' => $payment->payment_method,
                'response' => $errorBody,
            ]);
            
            // Parse Xero error for more specific message
            $errorData = json_decode($errorBody, true);
            if (is_array($errorData) && $this->shouldMarkPaymentAsSyncedOnValidationError($errorData)) {
                $syncStamp = $this->resolvePaymentValidationSyncTimestamp($errorData, $payment);
                $payment->update(['xero_synced_at' => $syncStamp]);

                Log::warning('Marked payment as synced after non-retriable Xero validation response', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'xero_invoice_id' => $invoice->xero_invoice_id,
                    'xero_synced_at' => $syncStamp->toDateTimeString(),
                    'response' => $errorData,
                ]);

                return [
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'status' => 'skipped',
                    'message' => 'Marked as synced after non-retriable Xero validation response',
                ];
            }

            if (isset($errorData['Elements'][0]['ValidationErrors'][0]['Message'])) {
                $specificError = $errorData['Elements'][0]['ValidationErrors'][0]['Message'];
                throw new \Exception("Failed to create payment in Xero: {$specificError}. The default bank account may not be valid for payments in Xero.");
            }
            
            throw new \Exception('Failed to create payment in Xero: ' . $errorBody);
        }

        $result = $response->json();
        $xeroPayment = $result['Payments'][0] ?? [];

        $syncStamp = now();
        if (!empty($xeroPayment['UpdatedDateUTC'])) {
            $syncStamp = $this->parseXeroDate($xeroPayment['UpdatedDateUTC']);
        }

        if (!empty($xeroPayment['PaymentID'])) {
            $payment->update([
                'xero_payment_id' => $xeroPayment['PaymentID'],
                'xero_synced_at' => $syncStamp,
            ]);
        }

        return [
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'status' => 'success',
            'xero_payment_id' => $xeroPayment['PaymentID'] ?? null,
        ];
    }

    private function shouldMarkInvoiceAsSyncedOnValidationError(array $errorData): bool
    {
        if (($errorData['Type'] ?? null) !== 'ValidationException' || (int) ($errorData['ErrorNumber'] ?? 0) !== 10) {
            return false;
        }

        $messages = collect(data_get($errorData, 'Elements.0.ValidationErrors', []))
            ->pluck('Message')
            ->filter(fn ($message) => is_string($message) && trim($message) !== '')
            ->map(fn (string $message) => strtolower($message))
            ->values();

        if ($messages->isEmpty()) {
            return false;
        }

        $nonRetriableMessageFragments = [
            'must supply a lineitemid',
            'status authorised cannot be applied',
            'payments or credit notes allocated',
        ];

        foreach ($messages as $message) {
            foreach ($nonRetriableMessageFragments as $fragment) {
                if (str_contains($message, $fragment)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function shouldMarkPaymentAsSyncedOnValidationError(array $errorData): bool
    {
        if (($errorData['Type'] ?? null) !== 'ValidationException' || (int) ($errorData['ErrorNumber'] ?? 0) !== 10) {
            return false;
        }

        $messages = collect(data_get($errorData, 'Elements.0.ValidationErrors', []))
            ->pluck('Message')
            ->filter(fn ($message) => is_string($message) && trim($message) !== '')
            ->map(fn (string $message) => strtolower($message))
            ->values();

        if ($messages->isEmpty()) {
            return false;
        }

        $nonRetriableMessageFragments = [
            'payments can only be made against authorised documents',
            'payment amount exceeds the amount outstanding',
        ];

        foreach ($messages as $message) {
            foreach ($nonRetriableMessageFragments as $fragment) {
                if (str_contains($message, $fragment)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function resolveInvoiceValidationSyncTimestamp(array $errorData, Invoice $invoice): \Carbon\Carbon
    {
        $updatedDateUtc = data_get($errorData, 'Elements.0.UpdatedDateUTC')
            ?? data_get($errorData, 'Elements.0.Invoice.UpdatedDateUTC');
        if (is_string($updatedDateUtc) && trim($updatedDateUtc) !== '') {
            return $this->parseXeroDate($updatedDateUtc);
        }

        $date = data_get($errorData, 'Elements.0.Date');
        if (is_string($date) && trim($date) !== '') {
            return $this->parseXeroDate($date);
        }

        $dateString = data_get($errorData, 'Elements.0.DateString');
        if (is_string($dateString) && trim($dateString) !== '') {
            return $this->parseXeroDate($dateString);
        }

        return $invoice->xero_updated_at ?: now();
    }

    private function resolvePaymentValidationSyncTimestamp(array $errorData, Payment $payment): \Carbon\Carbon
    {
        $updatedDateUtc = data_get($errorData, 'Elements.0.UpdatedDateUTC')
            ?? data_get($errorData, 'Elements.0.Invoice.UpdatedDateUTC');
        if (is_string($updatedDateUtc) && trim($updatedDateUtc) !== '') {
            return $this->parseXeroDate($updatedDateUtc);
        }

        $date = data_get($errorData, 'Elements.0.Date');
        if (is_string($date) && trim($date) !== '') {
            return $this->parseXeroDate($date);
        }

        return $payment->xero_synced_at ?: now();
    }

    private function hasSignificantLocalSyncDrift(
        ?\Carbon\CarbonInterface $updatedAt,
        ?\Carbon\CarbonInterface $xeroSyncedAt
    ): bool {
        if (empty($updatedAt) || empty($xeroSyncedAt)) {
            return true;
        }

        return $updatedAt->diffInSeconds($xeroSyncedAt, false) >= self::OUTBOUND_SYNC_MIN_DRIFT_SECONDS;
    }

    private function invalidateXeroInvoiceCache(?string $xeroInvoiceId): void
    {
        if ($xeroInvoiceId && array_key_exists($xeroInvoiceId, $this->xeroInvoiceCache)) {
            unset($this->xeroInvoiceCache[$xeroInvoiceId]);
        }
    }

    private function getXeroInvoiceCached(?string $xeroInvoiceId): ?array
    {
        if (!$xeroInvoiceId) {
            return null;
        }

        if (array_key_exists($xeroInvoiceId, $this->xeroInvoiceCache)) {
            return $this->xeroInvoiceCache[$xeroInvoiceId];
        }

        $invoice = $this->getXeroInvoice($xeroInvoiceId);
        $this->xeroInvoiceCache[$xeroInvoiceId] = $invoice;
        return $invoice;
    }

    public function deletePaymentInXero(Payment $payment): array
    {
        if (empty($payment->xero_payment_id)) {
            return [
                'payment_id' => $payment->id,
                'status' => 'skipped',
                'message' => 'Payment has not been synced to Xero',
            ];
        }

        $response = $this->makeXeroRequest(
            'post',
            $this->baseUrl . '/api.xro/2.0/Payments/' . $payment->xero_payment_id,
            ['Status' => 'DELETED']
        );

        if (!$response->successful()) {
            $errorBody = $response->body();
            $statusCode = $response->status();
            $normalizedError = strtolower($errorBody);
            $alreadyDeleted = $statusCode === 404
                || str_contains($normalizedError, 'not found')
                || str_contains($normalizedError, 'cannot be found')
                || str_contains($normalizedError, 'already deleted')
                || str_contains($normalizedError, '"status":"deleted"');

            if (!$alreadyDeleted) {
                throw new \Exception('Failed to delete payment in Xero: ' . $errorBody);
            }

            Log::warning('Xero payment already absent while reconciling deletion', [
                'payment_id' => $payment->id,
                'xero_payment_id' => $payment->xero_payment_id,
                'status' => $statusCode,
                'response' => $errorBody,
            ]);

            return [
                'payment_id' => $payment->id,
                'status' => 'success',
                'message' => 'Payment already absent in Xero',
            ];
        }

        $responseData = $response->json();
        $xeroPayment = $responseData['Payments'][0] ?? $responseData['Payment'] ?? [];
        if (!empty($xeroPayment['UpdatedDateUTC'])) {
            $payment->update([
                'xero_synced_at' => $this->parseXeroDate($xeroPayment['UpdatedDateUTC']),
            ]);
        }

        return [
            'payment_id' => $payment->id,
            'status' => 'success',
            'xero_payment_id' => $payment->xero_payment_id,
        ];
    }

    private function findExistingPaymentForImport(
        ?int $invoiceId,
        ?int $creditNoteId,
        ?string $xeroPaymentId,
        float $amount,
        \Carbon\CarbonInterface $paymentDate
    ): ?Payment {
        if ($xeroPaymentId) {
            $existingPayment = Payment::where('xero_payment_id', $xeroPaymentId)->first();
            if ($existingPayment) {
                return $existingPayment;
            }

            $deletedPayment = Payment::onlyTrashed()->where('xero_payment_id', $xeroPaymentId)->first();
            if ($deletedPayment) {
                return $deletedPayment;
            }
        }

        $activePaymentQuery = Payment::query()
            ->where('amount', $amount)
            ->whereDate('payment_date', $paymentDate->format('Y-m-d'));
        if ($invoiceId) {
            $activePaymentQuery->where('invoice_id', $invoiceId);
        }
        if ($creditNoteId) {
            $activePaymentQuery->where('credit_note_id', $creditNoteId);
        }

        $existingPayment = $activePaymentQuery->first();
        if ($existingPayment) {
            return $existingPayment;
        }

        $deletedPaymentQuery = Payment::onlyTrashed()
            ->where('amount', $amount)
            ->whereDate('payment_date', $paymentDate->format('Y-m-d'));
        if ($invoiceId) {
            $deletedPaymentQuery->where('invoice_id', $invoiceId);
        }
        if ($creditNoteId) {
            $deletedPaymentQuery->where('credit_note_id', $creditNoteId);
        }

        return $deletedPaymentQuery->first();
    }

    private function syncLocalStatusAfterPaymentChange(?Invoice $invoice = null, ?CreditNote $creditNote = null): void
    {
        if ($invoice) {
            $invoice->refresh();
            if ($invoice->isFullyPaid()) {
                if ($invoice->status !== 'paid') {
                    $invoice->update(['status' => 'paid']);
                }
            } elseif ($invoice->status === 'paid') {
                $invoice->update(['status' => 'sent']);
            }
        }

        if ($creditNote) {
            $creditNote->refresh();
            $remaining = max(0, round((float) $creditNote->total - (float) $creditNote->payments()->sum('amount'), 2));
            $creditNote->update(['remaining_credit' => $remaining]);

            if ($creditNote->status !== 'voided') {
                if ($creditNote->invoice_id && in_array($creditNote->status, ['draft', 'submitted'], true)) {
                    $creditNote->update(['status' => 'authorised']);
                }

                if ($remaining <= 0.01 && $creditNote->status !== 'paid') {
                    $creditNote->update(['status' => 'paid']);
                }

                if (!$creditNote->invoice_id && $remaining > 0.01 && $creditNote->status === 'paid') {
                    $creditNote->update(['status' => 'authorised']);
                }
            }
        }
    }


    /**
     * Sync payments for a specific invoice FROM Xero
     */
    private function syncPaymentsForInvoiceFromXero(Invoice $invoice, array $xeroInvoice = null): void
    {
        // Get payments from Xero invoice data
        // Note: Payments might not be included in the invoice response, so we may need to fetch them separately
        $xeroPayments = $xeroInvoice['Payments'] ?? [];
        
        // If no payments in invoice data, try fetching payments for this invoice
        if (empty($xeroPayments) && $invoice->xero_invoice_id) {
            try {
                // Fetch payments for this invoice from Xero Payments API
                $response = $this->makeXeroRequest('get', $this->baseUrl . '/api.xro/2.0/Payments?where=Invoice.InvoiceID==Guid("' . $invoice->xero_invoice_id . '")');
                
                if ($response->successful()) {
                    $responseData = $response->json();
                    $xeroPayments = $responseData['Payments'] ?? [];
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch payments for invoice from Xero', [
                    'invoice_id' => $invoice->id,
                    'xero_invoice_id' => $invoice->xero_invoice_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        if (empty($xeroPayments)) {
            Log::info('No payments found in Xero for invoice', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'xero_invoice_id' => $invoice->xero_invoice_id,
            ]);
            return;
        }

        $currentCompany = $this->getCompany();
        $createdCount = 0;

        foreach ($xeroPayments as $xeroPayment) {
            try {
                // Parse payment date
                $paymentDate = null;
                if (isset($xeroPayment['Date'])) {
                    $dateValue = $xeroPayment['Date'];
                    if (is_string($dateValue) && (strpos($dateValue, '/Date(') === 0)) {
                        $paymentDate = $this->parseXeroDate($dateValue);
                    } else {
                        $paymentDate = \Carbon\Carbon::parse($dateValue);
                    }
                } elseif (isset($xeroPayment['DateString'])) {
                    $paymentDate = \Carbon\Carbon::parse($xeroPayment['DateString']);
                } else {
                    Log::warning('Payment from Xero invoice has no date', [
                        'invoice_id' => $invoice->id,
                        'payment_id' => $xeroPayment['PaymentID'] ?? 'Unknown',
                    ]);
                    continue;
                }

                $amount = $xeroPayment['Amount'] ?? 0;
                if ($amount <= 0) {
                    continue;
                }

                $xeroPaymentId = $xeroPayment['PaymentID'] ?? null;
                $xeroPaymentStatus = strtoupper((string) ($xeroPayment['Status'] ?? ''));
                $existingPayment = $this->findExistingPaymentForImport(
                    $invoice->id,
                    null,
                    $xeroPaymentId,
                    (float) $amount,
                    $paymentDate
                );

                if ($xeroPaymentStatus === 'DELETED') {
                    if ($existingPayment && !$existingPayment->trashed()) {
                        $existingPayment->delete();
                        $this->syncLocalStatusAfterPaymentChange($invoice, null);
                    }

                    Log::info('Payment deletion reconciled from Xero invoice sync', [
                        'invoice_id' => $invoice->id,
                        'xero_payment_id' => $xeroPaymentId,
                        'had_local_match' => (bool) $existingPayment,
                    ]);
                    continue;
                }

                if ($existingPayment) {
                    if ($existingPayment->trashed()) {
                        Log::info('Skipping Xero payment re-import because payment was intentionally deleted locally', [
                            'invoice_id' => $invoice->id,
                            'xero_payment_id' => $xeroPaymentId,
                            'payment_id' => $existingPayment->id,
                        ]);
                        continue;
                    }

                    if (!empty($xeroPayment['PaymentID']) && empty($existingPayment->xero_payment_id)) {
                        $existingPayment->update([
                            'xero_payment_id' => $xeroPayment['PaymentID'],
                            'xero_synced_at' => isset($xeroPayment['UpdatedDateUTC']) ? $this->parseXeroDate($xeroPayment['UpdatedDateUTC']) : now(),
                        ]);
                    }
                    Log::info('Payment already exists locally', [
                        'payment_id' => $existingPayment->id,
                        'invoice_id' => $invoice->id,
                        'amount' => $amount,
                        'date' => $paymentDate->format('Y-m-d'),
                    ]);
                    continue;
                }

                // Determine payment method from Xero payment type
                $paymentMethod = 'eft';
                if (isset($xeroPayment['PaymentType'])) {
                    $paymentType = strtoupper($xeroPayment['PaymentType']);
                    if (strpos($paymentType, 'CASH') !== false) {
                        $paymentMethod = 'cash';
                    } elseif (strpos($paymentType, 'CARD') !== false || strpos($paymentType, 'CREDIT') !== false) {
                        $paymentMethod = 'card';
                    }
                }

                // Extract notes from reference
                $notes = $xeroPayment['Reference'] ?? null;

                // Create payment
                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'company_id' => $currentCompany->id,
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                    'payment_date' => $paymentDate->format('Y-m-d'),
                    'notes' => $notes,
                    'xero_payment_id' => $xeroPayment['PaymentID'] ?? null,
                    'xero_synced_at' => isset($xeroPayment['UpdatedDateUTC']) ? $this->parseXeroDate($xeroPayment['UpdatedDateUTC']) : now(),
                ]);

                $createdCount++;
                
                Log::info('Created payment from Xero invoice', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $amount,
                    'date' => $paymentDate->format('Y-m-d'),
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to process payment from Xero invoice', [
                    'invoice_id' => $invoice->id,
                    'payment_id' => $xeroPayment['PaymentID'] ?? 'Unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Update invoice status if fully paid
        if ($createdCount > 0) {
            $invoice->refresh();
            if ($invoice->isFullyPaid()) {
                $invoice->update(['status' => 'paid']);
            }
        }

        Log::info('Finished syncing payments for invoice from Xero', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'payments_created' => $createdCount,
        ]);
    }

    /**
     * Sync all payments for all invoices to Xero
     */
    public function syncAllPaymentsToXero(Company $company = null): array
    {
        if (!$this->settings->sync_invoices_to_xero) {
            return ['skipped' => true, 'message' => 'Invoice sync to Xero is disabled'];
        }

        // Use company from XeroSettings
        $currentCompany = $this->getCompany();
        $maxInvoicesPerRun = max(1, (int) config('services.xero.payment_export_max_invoices_per_run', 120));
        $invoices = Invoice::where('company_id', $currentCompany->id)
            ->whereNotNull('xero_invoice_id')
            ->whereHas('payments')
            ->orderByDesc('updated_at')
            ->limit($maxInvoicesPerRun)
            ->get();
        
        $results = [];

        foreach ($invoices as $invoice) {
            $paymentResults = $this->syncPaymentsToXero($invoice);
            $results[] = [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'payments' => $paymentResults,
            ];
        }

        return $results;
    }

    /**
     * Sync payments FROM Xero (import payments created in Xero in the last hour)
     */
    public function syncPaymentsFromXero(): array
    {
        if (!$this->settings->sync_invoices_to_xero) {
            return ['skipped' => true, 'message' => 'Invoice sync to Xero is disabled'];
        }

        // Use company from XeroSettings
        $currentCompany = $this->getCompany();
        $results = [];
        $createdCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $syncStartedAt = now();

        try {
            $lastSync = $this->getLastSyncDatetimeForModule(self::SYNC_MODULE_PAYMENTS);
            $windowStart = $lastSync ? \Carbon\Carbon::parse($lastSync) : now()->subHour();
            $headers = $this->buildIfModifiedSinceHeader($windowStart->toDateTimeString());
            
            Log::info('Starting payment sync from Xero with date filter', [
                'company_id' => $currentCompany->id,
                'if_modified_since' => $windowStart->toIso8601String(),
                'last_sync' => $lastSync,
            ]);

            // Fetch payments from Xero created in the last hour
            // Note: Xero Payments API doesn't directly support IfModifiedSince, so we'll fetch and filter
            $response = $this->makeXeroRequest(
                'get',
                $this->baseUrl . '/api.xro/2.0/Payments',
                [],
                2,
                $headers
            );

            $statusCode = $response->status();
            
            // Handle 304 Not Modified - no payments have been modified since the stored watermark
            if ($statusCode === 304) {
                Log::info('No payments modified since last sync', [
                    'company_id' => $currentCompany->id,
                    'if_modified_since' => $windowStart->toIso8601String(),
                ]);
                $this->recordSyncDatetimeForModule(self::SYNC_MODULE_PAYMENTS, $syncStartedAt);
                return [
                    'skipped' => true,
                    'created' => 0,
                    'skipped_count' => 0,
                    'errors' => 0,
                    'message' => 'No payments modified since last sync',
                ];
            }

            if (!$response->successful()) {
                $errorBody = $response->body();
                Log::error('Failed to fetch payments from Xero', [
                    'company_id' => $currentCompany->id,
                    'status' => $statusCode,
                    'response' => $errorBody,
                ]);
                throw new \Exception('Failed to fetch payments from Xero: ' . $errorBody);
            }

            $responseData = $response->json();
            $xeroPayments = $responseData['Payments'] ?? [];

            Log::info('Fetched payments from Xero', [
                'company_id' => $currentCompany->id,
                'total_payments' => count($xeroPayments),
            ]);

            foreach ($xeroPayments as $xeroPayment) {
                try {
                    // Check if payment was updated/created after last sync
                    // Use UpdatedDateUTC if available, otherwise use payment Date
                    $paymentCreatedDate = null;
                    if (isset($xeroPayment['UpdatedDateUTC'])) {
                        $paymentCreatedDate = $this->parseXeroDate($xeroPayment['UpdatedDateUTC']);
                    } elseif (isset($xeroPayment['Date'])) {
                        // Date might be in Xero format /Date(...)/ or standard format
                        $dateValue = $xeroPayment['Date'];
                        if (is_string($dateValue) && (strpos($dateValue, '/Date(') === 0)) {
                            $paymentCreatedDate = $this->parseXeroDate($dateValue);
                        } else {
                            $paymentCreatedDate = \Carbon\Carbon::parse($dateValue);
                        }
                    } elseif (isset($xeroPayment['DateString'])) {
                        $paymentCreatedDate = \Carbon\Carbon::parse($xeroPayment['DateString']);
                    }
                    
                    if (!$paymentCreatedDate) {
                        Log::warning('Payment from Xero has no date', [
                            'payment_id' => $xeroPayment['PaymentID'] ?? 'Unknown',
                            'payment_data' => $xeroPayment,
                        ]);
                        continue;
                    }

                    // Only process payments created/updated after the watermark.
                    if ($paymentCreatedDate->lt($windowStart)) {
                        continue;
                    }

                    // Parse payment date for storing in database
                    $paymentDate = null;
                    if (isset($xeroPayment['Date'])) {
                        // Date might be in Xero format /Date(...)/ or standard format
                        $dateValue = $xeroPayment['Date'];
                        if (is_string($dateValue) && (strpos($dateValue, '/Date(') === 0)) {
                            $paymentDate = $this->parseXeroDate($dateValue);
                        } else {
                            $paymentDate = \Carbon\Carbon::parse($dateValue);
                        }
                    } elseif (isset($xeroPayment['DateString'])) {
                        $paymentDate = \Carbon\Carbon::parse($xeroPayment['DateString']);
                    } else {
                        $paymentDate = $paymentCreatedDate; // Fallback to created date
                    }

                    $xeroInvoiceId = $xeroPayment['Invoice']['InvoiceID'] ?? null;
                    $xeroCreditNoteId = $xeroPayment['CreditNote']['CreditNoteID'] ?? null;
                    $invoice = null;
                    $creditNote = null;

                    if ($xeroInvoiceId) {
                        $invoice = Invoice::where('company_id', $currentCompany->id)
                            ->where('xero_invoice_id', $xeroInvoiceId)
                            ->first();
                    } elseif ($xeroCreditNoteId) {
                        $creditNote = CreditNote::where('company_id', $currentCompany->id)
                            ->where('xero_credit_note_id', $xeroCreditNoteId)
                            ->first();
                    } else {
                        Log::warning('Payment from Xero has no invoice or credit note ID', [
                            'payment_id' => $xeroPayment['PaymentID'] ?? 'Unknown',
                        ]);
                        continue;
                    }

                    if (!$invoice && !$creditNote) {
                        Log::info('Invoice/Credit note not found locally for Xero payment', [
                            'company_id' => $currentCompany->id,
                            'xero_invoice_id' => $xeroInvoiceId,
                            'xero_credit_note_id' => $xeroCreditNoteId,
                            'payment_id' => $xeroPayment['PaymentID'] ?? 'Unknown',
                        ]);
                        $skippedCount++;
                        continue;
                    }

                    // Check if payment already exists (match by entity, amount, and date)
                    $amount = $xeroPayment['Amount'] ?? 0;
                    $existingPayment = null;
                    $xeroPaymentId = $xeroPayment['PaymentID'] ?? null;
                    $xeroPaymentStatus = strtoupper((string) ($xeroPayment['Status'] ?? ''));
                    $existingPayment = $this->findExistingPaymentForImport(
                        $invoice?->id,
                        $creditNote?->id,
                        $xeroPaymentId,
                        (float) $amount,
                        $paymentDate
                    );

                    if ($xeroPaymentStatus === 'DELETED') {
                        if ($existingPayment && !$existingPayment->trashed()) {
                            $deletedInvoice = $existingPayment->invoice;
                            $deletedCreditNote = $existingPayment->creditNote;
                            $existingPayment->delete();
                            $this->syncLocalStatusAfterPaymentChange($deletedInvoice, $deletedCreditNote);
                        }

                        Log::info('Payment deletion reconciled from Xero payments sync', [
                            'company_id' => $currentCompany->id,
                            'xero_payment_id' => $xeroPaymentId,
                            'had_local_match' => (bool) $existingPayment,
                        ]);
                        $skippedCount++;
                        continue;
                    }

                    if ($existingPayment) {
                        if ($existingPayment->trashed()) {
                            Log::info('Skipping Xero payment re-import because payment was intentionally deleted locally', [
                                'company_id' => $currentCompany->id,
                                'xero_payment_id' => $xeroPaymentId,
                                'payment_id' => $existingPayment->id,
                            ]);
                            $skippedCount++;
                            continue;
                        }

                        if (!empty($xeroPayment['PaymentID']) && empty($existingPayment->xero_payment_id)) {
                            $existingPayment->update([
                                'xero_payment_id' => $xeroPayment['PaymentID'],
                                'xero_synced_at' => $paymentCreatedDate ?? now(),
                            ]);
                        }
                        Log::info('Payment already exists locally', [
                            'payment_id' => $existingPayment->id,
                            'invoice_id' => $invoice?->id,
                            'credit_note_id' => $creditNote?->id,
                            'amount' => $amount,
                            'date' => $paymentDate->format('Y-m-d'),
                        ]);
                        $skippedCount++;
                        continue;
                    }

                    // Determine payment method from Xero payment type or account
                    // Default to 'eft' if we can't determine
                    $paymentMethod = 'eft';
                    if (isset($xeroPayment['PaymentType'])) {
                        $paymentType = strtoupper($xeroPayment['PaymentType']);
                        if (str_contains($paymentType, 'CASH')) {
                            $paymentMethod = 'cash';
                        } elseif (str_contains($paymentType, 'CARD') || str_contains($paymentType, 'CREDIT')) {
                            $paymentMethod = 'card';
                        }
                    }

                    // Extract notes from reference
                    $notes = $xeroPayment['Reference'] ?? null;

                    // Create payment
                    $payment = Payment::create([
                        'invoice_id' => $invoice?->id,
                        'credit_note_id' => $creditNote?->id,
                        'company_id' => $currentCompany->id,
                        'amount' => $amount,
                        'payment_method' => $paymentMethod,
                        'payment_date' => $paymentDate->format('Y-m-d'),
                        'notes' => $notes,
                        'xero_payment_id' => $xeroPayment['PaymentID'] ?? null,
                        'xero_synced_at' => $paymentCreatedDate ?? now(),
                    ]);

                    $createdCount++;
                    
                    Log::info('Created payment from Xero', [
                        'payment_id' => $payment->id,
                        'invoice_id' => $invoice?->id,
                        'invoice_number' => $invoice?->invoice_number,
                        'credit_note_id' => $creditNote?->id,
                        'credit_note_number' => $creditNote?->credit_note_number,
                        'amount' => $amount,
                        'date' => $paymentDate->format('Y-m-d'),
                    ]);

                    // Update invoice status if fully paid
                    if ($invoice) {
                        $invoice->refresh();
                        if ($invoice->isFullyPaid()) {
                            $invoice->update(['status' => 'paid']);
                        }
                    }

                    if ($creditNote) {
                        $creditNote->refresh();
                        $remaining = max(0, round((float) $creditNote->total - (float) $creditNote->payments()->sum('amount'), 2));
                        $creditNote->update(['remaining_credit' => $remaining]);
                        if ($creditNote->status !== 'voided' && $remaining <= 0.01 && $creditNote->status !== 'paid') {
                            $creditNote->update(['status' => 'paid']);
                        }
                    }

                    $results[] = [
                        'payment_id' => $payment->id,
                        'invoice_id' => $invoice?->id,
                        'invoice_number' => $invoice?->invoice_number,
                        'credit_note_id' => $creditNote?->id,
                        'credit_note_number' => $creditNote?->credit_note_number,
                        'amount' => $amount,
                        'status' => 'created',
                    ];

                } catch (\Exception $e) {
                    Log::error('Failed to process payment from Xero', [
                        'company_id' => $currentCompany->id,
                        'payment_id' => $xeroPayment['PaymentID'] ?? 'Unknown',
                        'xero_invoice_id' => $xeroPayment['Invoice']['InvoiceID'] ?? 'Unknown',
                        'payment_date' => $xeroPayment['Date'] ?? 'N/A',
                        'payment_date_type' => gettype($xeroPayment['Date'] ?? null),
                        'updated_date_utc' => $xeroPayment['UpdatedDateUTC'] ?? 'N/A',
                        'error' => $e->getMessage(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    
                    $errorCount++;
                    $results[] = [
                        'payment_id' => null,
                        'xero_payment_id' => $xeroPayment['PaymentID'] ?? 'Unknown',
                        'xero_invoice_id' => $xeroPayment['Invoice']['InvoiceID'] ?? null,
                        'xero_credit_note_id' => $xeroPayment['CreditNote']['CreditNoteID'] ?? null,
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            Log::info('Finished syncing payments from Xero', [
                'company_id' => $currentCompany->id,
                'created' => $createdCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
            ]);
            $this->recordSyncDatetimeForModule(self::SYNC_MODULE_PAYMENTS, $syncStartedAt);

        } catch (\Exception $e) {
            Log::error('Failed to sync payments from Xero', [
                'company_id' => $currentCompany->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return [
            'created' => $createdCount,
            'skipped' => $skippedCount,
            'errors' => $errorCount,
            'results' => $results,
            'message' => "Synced {$createdCount} payments, {$skippedCount} skipped, {$errorCount} errors",
        ];
    }

    /**
     * Sync tax rates from Xero (import existing Xero tax rates to app)
     */
    public function syncTaxRatesFromXero(Company $company = null): array
    {
        if (!$this->settings->sync_tax_rates_from_xero) {
            return ['skipped' => true, 'message' => 'Tax rate sync from Xero is disabled'];
        }

        $currentCompany = $this->getCompany();
        $results = [];
        $syncStartedAt = now();

        try {
            $lastSync = $this->getLastSyncDatetimeForModule(self::SYNC_MODULE_TAX_RATES);

            $response = $this->makeXeroRequest(
                'get',
                $this->baseUrl . '/api.xro/2.0/TaxRates',
                [],
                2,
                $this->buildIfModifiedSinceHeader($lastSync)
            );

            if (!$response->successful()) {
                $errorBody = $response->body();
                $statusCode = $response->status();
                
                if ($statusCode === 403 && str_contains($errorBody, 'AuthenticationUnsuccessful')) {
                    Log::error('Xero authentication failed during tax rate sync', [
                        'company_id' => $currentCompany->id,
                        'status' => $statusCode,
                        'response' => $errorBody,
                    ]);
                    
                    $this->clearInvalidTokens();
                    
                    return ['skipped' => true, 'message' => 'Xero authentication failed. Please re-authorize your Xero connection in the settings.'];
                }
                
                throw new \Exception('Failed to fetch tax rates from Xero: ' . $errorBody);
            }

            $xeroTaxRates = $response->json()['TaxRates'] ?? [];
            
            foreach ($xeroTaxRates as $xeroTaxRate) {
                try {
                    // Skip if tax rate doesn't have required fields
                    if (empty($xeroTaxRate['Name'])) {
                        continue;
                    }

                    // Check if tax rate already exists in app by Xero ID
                    $existingTaxRate = TaxRate::where('company_id', $currentCompany->id)
                        ->where('xero_tax_rate_id', $xeroTaxRate['TaxType'])
                        ->first();

                    // If not found by Xero ID, check by name (case-insensitive)
                    if (!$existingTaxRate) {
                        $existingTaxRate = TaxRate::where('company_id', $currentCompany->id)
                            ->whereRaw('LOWER(name) = ?', [strtolower($xeroTaxRate['Name'])])
                            ->whereNull('xero_tax_rate_id')
                            ->first();
                    }

                    $rate = 0;
                    if (isset($xeroTaxRate['EffectiveRate'])) {
                        $rate = (float) $xeroTaxRate['EffectiveRate'];
                    } elseif (isset($xeroTaxRate['TaxComponents']) && count($xeroTaxRate['TaxComponents']) > 0) {
                        $rate = (float) ($xeroTaxRate['TaxComponents'][0]['Rate'] ?? 0);
                    }

                    if ($existingTaxRate) {
                        if (!$this->xeroUpdatedAtChanged($existingTaxRate, $xeroTaxRate)) {
                            $results[] = [
                                'tax_rate_id' => $existingTaxRate->id,
                                'tax_rate_name' => $xeroTaxRate['Name'],
                                'status' => 'skipped',
                                'message' => 'Tax rate unchanged in Xero',
                            ];
                            continue;
                        }
                        $updated = $this->updateModelIfChanged($existingTaxRate, [
                            'xero_tax_rate_id' => $xeroTaxRate['TaxType'],
                            'name' => $xeroTaxRate['Name'],
                            'code' => $xeroTaxRate['TaxType'] ?? null,
                            'rate' => $rate,
                            'description' => $xeroTaxRate['ReportTaxType'] ?? null,
                            'is_active' => true,
                            ...$this->getXeroTimestamps($xeroTaxRate),
                        ], 'tax_rate', [
                            'tax_rate_id' => $existingTaxRate->id,
                            'xero_tax_rate_id' => $xeroTaxRate['TaxType'] ?? null,
                        ]);
                        if ($updated) {
                            $this->alignLocalUpdatedAtWithXero($existingTaxRate);
                        }
                        
                        $results[] = [
                            'tax_rate_id' => $existingTaxRate->id,
                            'tax_rate_name' => $xeroTaxRate['Name'],
                            'status' => 'updated',
                            'message' => 'Tax rate updated from Xero',
                        ];
                    } else {
                        // Create new tax rate
                        $taxRate = TaxRate::create([
                            'company_id' => $currentCompany->id,
                            'xero_tax_rate_id' => $xeroTaxRate['TaxType'],
                            'name' => $xeroTaxRate['Name'],
                            'code' => $xeroTaxRate['TaxType'] ?? null,
                            'rate' => $rate,
                            'description' => $xeroTaxRate['ReportTaxType'] ?? null,
                            'is_active' => true,
                            ...$this->getXeroTimestamps($xeroTaxRate),
                        ]);
                        $this->alignLocalUpdatedAtWithXero($taxRate);
                        
                        $results[] = [
                            'tax_rate_id' => $taxRate->id,
                            'tax_rate_name' => $xeroTaxRate['Name'],
                            'status' => 'created',
                            'message' => 'Tax rate imported from Xero',
                        ];
                    }
                } catch (\Exception $e) {
                    $results[] = [
                        'tax_rate_name' => $xeroTaxRate['Name'] ?? 'Unknown',
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to sync tax rates from Xero', [
                'company_id' => $currentCompany->id,
                'error' => $e->getMessage(),
            ]);
            
            return ['skipped' => true, 'message' => 'Failed to sync tax rates from Xero: ' . $e->getMessage()];
        }

        $this->recordSyncDatetimeForModule(self::SYNC_MODULE_TAX_RATES, $syncStartedAt);

        return $results;
    }

    /**
     * Sync bank accounts from Xero (import existing Xero bank accounts to app)
     */
    public function syncBankAccountsFromXero(Company $company = null): array
    {
        if (!$this->settings->sync_bank_accounts_from_xero) {
            return ['skipped' => true, 'message' => 'Bank account sync from Xero is disabled'];
        }

        $currentCompany = $this->getCompany();
        $results = [];
        $syncStartedAt = now();

        try {
            $hasExistingBankAccounts = BankAccount::where('company_id', $currentCompany->id)
                ->whereNotNull('xero_account_id')
                ->exists();

            $allAccounts = $this->fetchXeroAccounts(self::SYNC_MODULE_BANK_ACCOUNTS, !$hasExistingBankAccounts);
            $xeroAccounts = array_values(array_filter($allAccounts, function ($a) {
                $isBank = ($a['Type'] ?? '') === 'BANK';
                $acceptsPayments = !empty($a['EnablePaymentsToAccount']);
                return $isBank || $acceptsPayments;
            }));
            
            foreach ($xeroAccounts as $xeroAccount) {
                try {
                    if (empty($xeroAccount['Name']) || empty($xeroAccount['Code'])) {
                        continue;
                    }

                    $isBank = ($xeroAccount['Type'] ?? '') === 'BANK';

                    $existingBankAccount = BankAccount::where('company_id', $currentCompany->id)
                        ->where('xero_account_id', $xeroAccount['AccountID'])
                        ->first();

                    if (!$existingBankAccount && !empty($xeroAccount['BankAccountNumber'])) {
                        $existingBankAccount = BankAccount::where('company_id', $currentCompany->id)
                            ->where('account_number', $xeroAccount['BankAccountNumber'])
                            ->whereNull('xero_account_id')
                            ->first();
                    }

                    if (!$existingBankAccount) {
                        $existingBankAccount = BankAccount::where('company_id', $currentCompany->id)
                            ->where('account_number', $xeroAccount['Code'])
                            ->whereNull('xero_account_id')
                            ->first();
                    }

                    $accountType = 'Other';
                    if ($isBank && isset($xeroAccount['BankAccountType'])) {
                        $typeMap = [
                            'CHECKING' => 'Current',
                            'SAVINGS' => 'Savings',
                            'CREDITCARD' => 'Credit Card',
                            'LOAN' => 'Loan',
                        ];
                        $accountType = $typeMap[$xeroAccount['BankAccountType']] ?? 'Current';
                    }

                    $accountNumber = $isBank
                        ? ($xeroAccount['BankAccountNumber'] ?? $xeroAccount['Code'])
                        : $xeroAccount['Code'];

                    $bankName = $isBank
                        ? (!empty($xeroAccount['BankAccountNumber']) ? ($xeroAccount['Name'] ?? 'Unknown') : 'Unknown')
                        : ($xeroAccount['Type'] ?? 'Payment Account');

                    $accountData = [
                        'xero_account_id' => $xeroAccount['AccountID'],
                        'account_name' => $xeroAccount['Name'],
                        'account_number' => $accountNumber,
                        'bank_name' => $bankName,
                        'branch_code' => null,
                        'account_type' => $accountType,
                        'currency' => $xeroAccount['CurrencyCode'] ?? 'ZAR',
                        'opening_balance' => (float) ($xeroAccount['Balance'] ?? 0),
                        'is_active' => ($xeroAccount['Status'] ?? 'ACTIVE') === 'ACTIVE',
                        ...$this->getXeroTimestamps($xeroAccount),
                    ];

                    if ($existingBankAccount) {
                        if (!$this->xeroUpdatedAtChanged($existingBankAccount, $xeroAccount)) {
                            $results[] = [
                                'bank_account_id' => $existingBankAccount->id,
                                'bank_account_name' => $xeroAccount['Name'],
                                'status' => 'skipped',
                                'message' => 'Bank account unchanged in Xero',
                            ];
                            continue;
                        }
                        $updated = $this->updateModelIfChanged($existingBankAccount, $accountData, 'bank_account', [
                            'bank_account_id' => $existingBankAccount->id,
                            'xero_account_id' => $xeroAccount['AccountID'] ?? null,
                        ]);
                        if ($updated) {
                            $this->alignLocalUpdatedAtWithXero($existingBankAccount);
                        }
                        
                        $results[] = [
                            'bank_account_id' => $existingBankAccount->id,
                            'bank_account_name' => $xeroAccount['Name'],
                            'status' => 'updated',
                            'message' => $isBank ? 'Bank account updated from Xero' : 'Payment account updated from Xero',
                        ];
                    } else {
                        $bankAccount = BankAccount::create([
                            'company_id' => $currentCompany->id,
                            ...$accountData,
                        ]);
                        $this->alignLocalUpdatedAtWithXero($bankAccount);
                        
                        $results[] = [
                            'bank_account_id' => $bankAccount->id,
                            'bank_account_name' => $xeroAccount['Name'],
                            'status' => 'created',
                            'message' => $isBank ? 'Bank account imported from Xero' : 'Payment account imported from Xero',
                        ];
                    }
                } catch (\Exception $e) {
                    $results[] = [
                        'bank_account_name' => $xeroAccount['Name'] ?? 'Unknown',
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to sync bank accounts from Xero', [
                'company_id' => $currentCompany->id,
                'error' => $e->getMessage(),
            ]);
            
            return ['skipped' => true, 'message' => 'Failed to sync bank accounts from Xero: ' . $e->getMessage()];
        }

        $this->recordSyncDatetimeForModule(self::SYNC_MODULE_BANK_ACCOUNTS, $syncStartedAt);

        return $results;
    }

    /**
     * Sync chart of accounts from Xero (import existing Xero accounts to app)
     */
    public function syncChartOfAccountsFromXero(Company $company = null): array
    {
        if (!$this->settings->sync_chart_of_accounts_from_xero) {
            return ['skipped' => true, 'message' => 'Chart of accounts sync from Xero is disabled'];
        }

        $currentCompany = $this->getCompany();
        $results = [];
        $syncStartedAt = now();

        try {
            $hasExistingCoa = ChartOfAccount::where('company_id', $currentCompany->id)
                ->whereNotNull('xero_account_id')
                ->exists();

            $allAccounts = $this->fetchXeroAccounts(self::SYNC_MODULE_CHART_OF_ACCOUNTS, !$hasExistingCoa);
            $xeroAccounts = array_values(array_filter($allAccounts, fn($a) => ($a['Type'] ?? '') !== 'BANK'));
            
            Log::info('Chart of accounts sync - After filtering bank accounts', [
                'company_id' => $currentCompany->id,
                'total_accounts' => count($allAccounts),
                'non_bank_accounts' => count($xeroAccounts),
            ]);
            
            // First pass: create/update accounts without parent relationships
            $accountMap = [];
            $processedCount = 0;
            $skippedCount = 0;
            
            foreach ($xeroAccounts as $xeroAccount) {
                try {
                    // Skip if account doesn't have required fields
                    if (empty($xeroAccount['Name'])) {
                        $skippedCount++;
                        Log::debug('Skipping account - missing Name', [
                            'account' => $xeroAccount,
                        ]);
                        continue;
                    }
                    
                    // Skip bank accounts (should already be filtered, but double-check)
                    if (($xeroAccount['Type'] ?? '') === 'BANK') {
                        $skippedCount++;
                        continue;
                    }
                    
                    // Generate a code if missing (use AccountID as fallback)
                    $accountCode = $xeroAccount['Code'] ?? $xeroAccount['AccountID'] ?? 'ACC-' . uniqid();

                    // Map Xero account type to our account type
                    $accountType = 'Expense';
                    if (isset($xeroAccount['Type'])) {
                        $typeMap = [
                            'ASSET' => 'Asset',
                            'BANK' => 'Asset',
                            'CURRASSET' => 'Asset',
                            'FIXED' => 'Asset',
                            'INVENTORY' => 'Asset',
                            'PREPAYMENT' => 'Asset',
                            'EQUITY' => 'Equity',
                            'EXPENSE' => 'Expense',
                            'DEPRECIATN' => 'Expense',
                            'DIRECTCOSTS' => 'Expense',
                            'OVERHEADS' => 'Expense',
                            'LIABILITY' => 'Liability',
                            'CURRLIAB' => 'Liability',
                            'PAYGLIABILITY' => 'Liability',
                            'TERMLIAB' => 'Liability',
                            'REVENUE' => 'Revenue',
                            'OTHERINCOME' => 'Revenue',
                            'SALES' => 'Revenue',
                        ];
                        $accountType = $typeMap[$xeroAccount['Type']] ?? 'Expense';
                    }

                    // Check if account already exists in app by Xero ID
                    $existingAccount = ChartOfAccount::where('company_id', $currentCompany->id)
                        ->where('xero_account_id', $xeroAccount['AccountID'])
                        ->first();

                    // If not found by Xero ID, check by account code so we can relink
                    // pre-existing or previously mismatched local accounts instead of failing
                    // on the unique account_code constraint.
                    if (!$existingAccount && !empty($accountCode)) {
                        $existingAccount = ChartOfAccount::where('company_id', $currentCompany->id)
                            ->where('account_code', $accountCode)
                            ->first();
                    }

                    $accountData = [
                        'company_id' => $currentCompany->id,
                        'xero_account_id' => $xeroAccount['AccountID'],
                        'account_code' => $accountCode,
                        'account_name' => $xeroAccount['Name'],
                        'account_type' => $accountType,
                        'description' => $xeroAccount['Description'] ?? null,
                        'is_active' => ($xeroAccount['Status'] ?? 'ACTIVE') === 'ACTIVE',
                        'sort_order' => (int) ($xeroAccount['SortOrder'] ?? 0),
                        ...$this->getXeroTimestamps($xeroAccount),
                    ];

                    if ($existingAccount) {
                        if (!$this->xeroUpdatedAtChanged($existingAccount, $xeroAccount)) {
                            $accountMap[$xeroAccount['AccountID']] = $existingAccount;
                            $results[] = [
                                'account_id' => $existingAccount->id,
                                'account_name' => $xeroAccount['Name'],
                                'status' => 'skipped',
                                'message' => 'Account unchanged in Xero',
                            ];
                            continue;
                        }
                        $updated = $this->updateModelIfChanged($existingAccount, $accountData, 'chart_of_account', [
                            'account_id' => $existingAccount->id,
                            'xero_account_id' => $xeroAccount['AccountID'] ?? null,
                        ]);
                        if ($updated) {
                            $this->alignLocalUpdatedAtWithXero($existingAccount);
                        }
                        $accountMap[$xeroAccount['AccountID']] = $existingAccount;
                        $processedCount++;
                        
                        $results[] = [
                            'account_id' => $existingAccount->id,
                            'account_name' => $xeroAccount['Name'],
                            'status' => 'updated',
                            'message' => 'Account updated from Xero',
                        ];
                    } else {
                        // Create new account (parent will be set in second pass)
                        $account = ChartOfAccount::create($accountData);
                        $this->alignLocalUpdatedAtWithXero($account);
                        $accountMap[$xeroAccount['AccountID']] = $account;
                        $processedCount++;
                        
                        $results[] = [
                            'account_id' => $account->id,
                            'account_name' => $xeroAccount['Name'],
                            'status' => 'created',
                            'message' => 'Account imported from Xero',
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error('Error processing chart of account from Xero', [
                        'company_id' => $currentCompany->id,
                        'account' => $xeroAccount,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    
                    $results[] = [
                        'account_name' => $xeroAccount['Name'] ?? 'Unknown',
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];
                }
            }
            
            Log::info('Chart of accounts sync - Processing complete', [
                'company_id' => $currentCompany->id,
                'total_accounts' => count($xeroAccounts),
                'processed' => $processedCount,
                'skipped' => $skippedCount,
                'results_count' => count($results),
            ]);

            // Second pass: set parent relationships
            foreach ($accountMap as $xeroAccountId => $account) {
                // Find the original Xero account data
                $xeroAccount = null;
                foreach ($xeroAccounts as $xa) {
                    if (($xa['AccountID'] ?? '') === $xeroAccountId) {
                        $xeroAccount = $xa;
                        break;
                    }
                }
                
                if (!$xeroAccount || empty($xeroAccount['ParentAccountID'])) {
                    continue;
                }

                $parentAccount = $accountMap[$xeroAccount['ParentAccountID']] ?? null;

                if ($parentAccount) {
                    if ((int) $account->parent_account_id !== (int) $parentAccount->id) {
                        $account->update(['parent_account_id' => $parentAccount->id]);
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to sync chart of accounts from Xero', [
                'company_id' => $currentCompany->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return ['skipped' => true, 'message' => 'Failed to sync chart of accounts from Xero: ' . $e->getMessage()];
        }

        $this->recordSyncDatetimeForModule(self::SYNC_MODULE_CHART_OF_ACCOUNTS, $syncStartedAt);
        
        if (empty($results)) {
            $existingCount = ChartOfAccount::where('company_id', $currentCompany->id)
                ->whereNotNull('xero_account_id')
                ->count();

            if ($existingCount > 0) {
                return ['skipped' => true, 'message' => 'Chart of accounts are up to date - no changes detected in Xero.'];
            }

            Log::warning('Chart of accounts sync returned no results', [
                'company_id' => $currentCompany->id,
                'xero_accounts_count' => count($xeroAccounts ?? []),
            ]);
            
            return ['skipped' => true, 'message' => 'No chart of accounts found in Xero to import. Make sure you have accounts set up in Xero (excluding bank accounts which are imported separately).'];
        }

        return $results;
    }

    // ========================================================================
    // Credit Note Sync Methods
    // ========================================================================

    public function syncCreditNotesToXero(Company $company = null): array
    {
        if (!$this->settings->sync_credit_notes_to_xero) {
            return ['skipped' => true, 'message' => 'Credit note sync to Xero is disabled'];
        }

        $currentCompany = $this->getCompany();
        $results = [];

        $creditNotes = CreditNote::where('company_id', $currentCompany->id)
            ->where(function ($query) {
                $query->whereNull('xero_credit_note_id')
                    ->orWhereNull('xero_updated_at')
                    ->orWhereColumn('updated_at', '>', 'xero_updated_at');
            })
            ->orderByDesc('updated_at')
            ->with(['customer', 'lineItems.product', 'invoice'])
            ->get();

        Log::info('Starting credit note sync to Xero', [
            'company_id' => $currentCompany->id,
            'credit_note_count' => $creditNotes->count(),
            'filter_applied' => 'updated_at_gt_xero_updated_at_or_unsynced',
        ]);

        foreach ($creditNotes as $index => $creditNote) {
            try {
                if ($index > 0) {
                    sleep(1);
                }

                if ($creditNote->invoice_id && in_array($creditNote->status, ['draft', 'submitted'], true)) {
                    $creditNote->update(['status' => 'authorised']);
                    $creditNote->refresh();
                }

                if (!in_array($creditNote->status, ['authorised', 'paid', 'voided'], true)) {
                    $results[] = [
                        'credit_note_id' => $creditNote->id,
                        'credit_note_number' => $creditNote->credit_note_number,
                        'status' => 'skipped',
                        'message' => "Skipped outbound sync: status '{$creditNote->status}' is not eligible",
                    ];
                    continue;
                }

                if ($creditNote->status === 'voided' && !$creditNote->xero_credit_note_id) {
                    $results[] = [
                        'credit_note_id' => $creditNote->id,
                        'credit_note_number' => $creditNote->credit_note_number,
                        'status' => 'skipped',
                        'message' => 'Skipped outbound sync: cannot void in Xero before initial sync',
                    ];
                    continue;
                }

                if (!$creditNote->customer || !$creditNote->customer->xero_contact_id) {
                    $results[] = [
                        'credit_note_id' => $creditNote->id,
                        'credit_note_number' => $creditNote->credit_note_number,
                        'status' => 'error',
                        'error' => 'Customer not synced to Xero. Sync customers first.',
                    ];
                    continue;
                }

                if ($creditNote->xero_credit_note_id) {
                    $xeroCN = $this->getXeroCreditNote($creditNote->xero_credit_note_id);
                    if ($xeroCN) {
                        if ($this->isXeroRecordNewerThanLocal($creditNote, $xeroCN)) {
                            $this->updateCreditNoteFromXeroData($creditNote, $xeroCN);
                            $results[] = [
                                'credit_note_id' => $creditNote->id,
                                'credit_note_number' => $creditNote->credit_note_number,
                                'status' => 'updated_from_xero',
                                'message' => 'Credit note updated from Xero (Xero was newer)',
                            ];
                            continue;
                        }
                    }
                }

                $xeroData = $this->createOrUpdateCreditNoteInXero($creditNote);

                if (!empty($xeroData['_sync_skipped_reason'])) {
                    $this->updateCreditNoteFromXeroData($creditNote, $xeroData);
                    $results[] = [
                        'credit_note_id' => $creditNote->id,
                        'credit_note_number' => $creditNote->credit_note_number,
                        'status' => 'skipped',
                        'message' => $xeroData['_sync_skipped_reason'],
                    ];
                    continue;
                }

                if (isset($xeroData['CreditNoteID'])) {
                    $updateData = $this->getXeroTimestamps($xeroData);
                    if (!$creditNote->xero_credit_note_id) {
                        $updateData['xero_credit_note_id'] = $xeroData['CreditNoteID'];
                    }
                    $creditNote->update($updateData);
                    $this->alignLocalUpdatedAtWithXero($creditNote);
                }

                $results[] = [
                    'credit_note_id' => $creditNote->id,
                    'credit_note_number' => $creditNote->credit_note_number,
                    'status' => 'success',
                    'xero_credit_note_id' => $xeroData['CreditNoteID'] ?? null,
                ];

                if ($creditNote->invoice_id && $creditNote->xero_credit_note_id && $creditNote->status === 'authorised') {
                    try {
                        $this->syncCreditNoteAllocationToXero($creditNote);
                    } catch (\Exception $e) {
                        Log::warning('Failed to allocate credit note to invoice in Xero', [
                            'credit_note_id' => $creditNote->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if ($creditNote->xero_credit_note_id) {
                    try {
                        $this->syncCreditNotePaymentsToXero($creditNote);
                    } catch (\Exception $e) {
                        Log::warning('Failed to sync credit note refund payments to Xero', [
                            'credit_note_id' => $creditNote->id,
                            'credit_note_number' => $creditNote->credit_note_number,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

            } catch (\Exception $e) {
                Log::error('Credit note sync to Xero failed', [
                    'credit_note_id' => $creditNote->id,
                    'credit_note_number' => $creditNote->credit_note_number,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $results[] = [
                    'credit_note_id' => $creditNote->id,
                    'credit_note_number' => $creditNote->credit_note_number,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function syncCreditNotesFromXero(Company $company = null): array
    {
        if (!$this->settings->sync_credit_notes_from_xero) {
            return ['skipped' => true, 'message' => 'Credit note sync from Xero is disabled'];
        }

        $currentCompany = $this->getCompany();
        $results = [];
        $syncStartedAt = now();

        try {
            $lastSync = $this->getLastSyncDatetimeForModule(self::SYNC_MODULE_CREDIT_NOTES);
            $ifModifiedSince = $this->buildIfModifiedSinceHeader($lastSync);

            $page = 1;
            $pageSize = 100;
            $totalProcessed = 0;

            do {
                Log::info('Fetching credit note page from Xero', [
                    'company_id' => $currentCompany->id,
                    'page' => $page,
                    'page_size' => $pageSize,
                ]);

                $response = $this->makeXeroRequest(
                    'get',
                    $this->baseUrl . '/api.xro/2.0/CreditNotes?page=' . $page . '&pageSize=' . $pageSize . '&summaryOnly=false',
                    [],
                    2,
                    $ifModifiedSince
                );

                if (!$response->successful()) {
                    $errorBody = $response->body();
                    $statusCode = $response->status();

                    if ($statusCode === 403 && str_contains($errorBody, 'AuthenticationUnsuccessful')) {
                        Log::error('Xero authentication failed during credit note sync', [
                            'company_id' => $currentCompany->id,
                            'status' => $statusCode,
                        ]);
                        $this->clearInvalidTokens();
                        return ['skipped' => true, 'message' => 'Xero authentication failed. Please re-authorize your Xero connection.'];
                    }

                    throw new \Exception('Failed to fetch credit notes from Xero: ' . $errorBody);
                }

                $responseData = $response->json();
                $xeroNotes = $responseData['CreditNotes'] ?? [];
                $hasMorePages = count($xeroNotes) >= $pageSize;

                Log::info('Fetched credit note page from Xero', [
                    'company_id' => $currentCompany->id,
                    'page' => $page,
                    'count' => count($xeroNotes),
                    'has_more_pages' => $hasMorePages,
                ]);

                foreach ($xeroNotes as $xeroNote) {
                    try {
                        if (($xeroNote['Type'] ?? '') !== 'ACCRECCREDIT') {
                            continue;
                        }

                        if (empty($xeroNote['CreditNoteNumber']) && empty($xeroNote['CreditNoteID'])) {
                            continue;
                        }

                        $existing = CreditNote::where('company_id', $currentCompany->id)
                            ->where('xero_credit_note_id', $xeroNote['CreditNoteID'])
                            ->first();

                        if (!$existing && !empty($xeroNote['CreditNoteNumber'])) {
                            $existing = CreditNote::where('company_id', $currentCompany->id)
                                ->where('credit_note_number', $xeroNote['CreditNoteNumber'])
                                ->first();
                        }

                        if ($existing) {
                            $allocationCount = isset($xeroNote['Allocations']) && is_array($xeroNote['Allocations'])
                                ? count($xeroNote['Allocations'])
                                : 0;
                            $needsAllocationBackfill = empty($existing->invoice_id) && $allocationCount > 0;

                            if (!$this->xeroUpdatedAtChanged($existing, $xeroNote) && !$needsAllocationBackfill) {
                                $results[] = [
                                    'credit_note_id' => $existing->id,
                                    'credit_note_number' => $existing->credit_note_number,
                                    'status' => 'skipped',
                                    'message' => 'Credit note unchanged in Xero',
                                ];
                                $totalProcessed++;
                                continue;
                            }

                            if ($needsAllocationBackfill) {
                                Log::info('Forcing credit note update to backfill invoice allocation', [
                                    'company_id' => $currentCompany->id,
                                    'credit_note_id' => $existing->id,
                                    'credit_note_number' => $existing->credit_note_number,
                                    'allocation_count' => $allocationCount,
                                ]);
                            }

                            $this->updateCreditNoteFromXeroData($existing, $xeroNote);
                            if (!$existing->xero_credit_note_id) {
                                $existing->update(['xero_credit_note_id' => $xeroNote['CreditNoteID']]);
                                $this->alignLocalUpdatedAtWithXero($existing);
                            }
                            $results[] = [
                                'credit_note_id' => $existing->id,
                                'credit_note_number' => $existing->credit_note_number,
                                'status' => 'updated',
                                'message' => 'Credit note updated from Xero',
                            ];
                        } else {
                            $cn = $this->createCreditNoteFromXero($xeroNote, $currentCompany);
                            $results[] = [
                                'credit_note_id' => $cn->id,
                                'credit_note_number' => $cn->credit_note_number,
                                'status' => 'created',
                                'message' => 'Credit note imported from Xero',
                            ];
                        }

                        $totalProcessed++;
                    } catch (\Exception $e) {
                        Log::error('Failed to process credit note from Xero', [
                            'company_id' => $currentCompany->id,
                            'credit_note_id' => $xeroNote['CreditNoteID'] ?? 'Unknown',
                            'credit_note_number' => $xeroNote['CreditNoteNumber'] ?? 'Unknown',
                            'error_class' => get_class($e),
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        $results[] = [
                            'credit_note_number' => $xeroNote['CreditNoteNumber'] ?? 'Unknown',
                            'status' => 'error',
                            'error' => $e->getMessage(),
                        ];
                    }
                }

                $page++;
                if ($hasMorePages) {
                    sleep(1);
                }
            } while ($hasMorePages);

            Log::info('Finished processing all credit notes from Xero', [
                'company_id' => $currentCompany->id,
                'total_processed' => $totalProcessed,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to sync credit notes from Xero', [
                'company_id' => $currentCompany->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['skipped' => true, 'message' => 'Failed to sync credit notes from Xero: ' . $e->getMessage()];
        }

        $this->recordSyncDatetimeForModule(self::SYNC_MODULE_CREDIT_NOTES, $syncStartedAt);

        return $results;
    }

    public function handleCreditNoteWebhook(array $webhookData): void
    {
        if (!$this->settings->sync_credit_notes_from_xero) {
            return;
        }

        foreach ($webhookData as $event) {
            if (($event['EventType'] ?? '') === 'UPDATE' && ($event['EventCategory'] ?? '') === 'CREDIT_NOTE') {
                $this->updateCreditNoteFromXero($event['ResourceId']);
            }
        }
    }

    private function updateCreditNoteFromXero(string $xeroCreditNoteId): void
    {
        try {
            $response = $this->makeXeroRequest('get', $this->baseUrl . '/api.xro/2.0/CreditNotes/' . $xeroCreditNoteId);

            if (!$response->successful()) {
                Log::error('Failed to fetch credit note from Xero: ' . $response->body());
                return;
            }

            $xeroNote = $response->json()['CreditNotes'][0] ?? null;
            if (!$xeroNote) {
                return;
            }

            $currentCompany = $this->getCompany();
            $creditNote = CreditNote::where('company_id', $currentCompany->id)
                ->where('xero_credit_note_id', $xeroCreditNoteId)
                ->first();

            if (!$creditNote && !empty($xeroNote['CreditNoteNumber'])) {
                $creditNote = CreditNote::where('company_id', $currentCompany->id)
                    ->where('credit_note_number', $xeroNote['CreditNoteNumber'])
                    ->first();
            }

            if ($creditNote) {
                $this->updateCreditNoteFromXeroData($creditNote, $xeroNote);
                Log::info("Updated credit note {$creditNote->id} from Xero webhook");
            }
        } catch (\Exception $e) {
            Log::error('Error updating credit note from Xero webhook: ' . $e->getMessage());
        }
    }

    private function syncCreditNoteAllocationToXero(CreditNote $creditNote): void
    {
        if (!$creditNote->xero_credit_note_id || !$creditNote->invoice_id) {
            return;
        }

        $invoice = Invoice::find($creditNote->invoice_id);
        if (!$invoice || !$invoice->xero_invoice_id) {
            return;
        }

        $existingCN = $this->makeXeroRequest('get',
            $this->baseUrl . '/api.xro/2.0/CreditNotes/' . $creditNote->xero_credit_note_id
        );

        if ($existingCN->successful()) {
            $xeroData = $existingCN->json()['CreditNotes'][0] ?? [];
            $allocations = $xeroData['Allocations'] ?? [];

            foreach ($allocations as $alloc) {
                if (($alloc['Invoice']['InvoiceID'] ?? '') === $invoice->xero_invoice_id) {
                    return;
                }
            }
        }

        $totalPaid = (float) $invoice->payments()->sum('amount');
        $otherCredits = (float) $invoice->creditNotes()
            ->where('id', '!=', $creditNote->id)
            ->where('status', '!=', 'voided')
            ->sum('total');
        $invoiceOwing = max(0, (float) $invoice->total - $totalPaid - $otherCredits);
        $allocationAmount = min((float) $creditNote->total, $invoiceOwing);
        if ($allocationAmount <= 0) {
            return;
        }

        $response = $this->makeXeroRequest('put',
            $this->baseUrl . '/api.xro/2.0/CreditNotes/' . $creditNote->xero_credit_note_id . '/Allocations',
            [
                'Allocations' => [[
                    'Invoice' => ['InvoiceID' => $invoice->xero_invoice_id],
                    'Amount' => round($allocationAmount, 2),
                    'Date' => $creditNote->credit_note_date->format('Y-m-d'),
                ]],
            ]
        );

        if (!$response->successful()) {
            throw new \Exception('Failed to allocate credit note in Xero: ' . $response->body());
        }

        Log::info('Credit note allocated to invoice in Xero', [
            'credit_note_id' => $creditNote->id,
            'invoice_id' => $invoice->id,
            'amount' => $allocationAmount,
        ]);
    }

    public function syncCreditNotePaymentsToXero(CreditNote $creditNote): array
    {
        if (!$this->settings->sync_credit_notes_to_xero) {
            return ['skipped' => true, 'message' => 'Credit note sync to Xero is disabled'];
        }

        if (!$creditNote->xero_credit_note_id) {
            return [['status' => 'skipped', 'message' => 'Credit note has not been synced to Xero yet']];
        }

        $payments = $creditNote->payments()
            ->where(function ($query) {
                $query->whereNull('xero_payment_id')
                    ->orWhereNull('xero_synced_at')
                    ->orWhereColumn('payments.updated_at', '>', 'payments.xero_synced_at');
            })
            ->orderBy('id')
            ->get()
            ->filter(fn (Payment $payment) => $this->hasSignificantLocalSyncDrift($payment->updated_at, $payment->xero_synced_at))
            ->sortBy(fn (Payment $payment) => empty($payment->xero_payment_id) ? 0 : 1)
            ->values();

        if ($payments->isEmpty()) {
            return [[
                'credit_note_id' => $creditNote->id,
                'status' => 'skipped',
                'message' => 'No unsynced credit note refund payments found',
            ]];
        }

        $results = [];
        foreach ($payments as $payment) {
            try {
                $results[] = $this->createCreditNotePaymentInXero($creditNote, $payment);
            } catch (\Exception $e) {
                Log::error('Credit note payment sync failed', [
                    'credit_note_id' => $creditNote->id,
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
                $results[] = [
                    'payment_id' => $payment->id,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function createCreditNotePaymentInXero(CreditNote $creditNote, Payment $payment): array
    {
        if (!empty($payment->xero_payment_id) && !empty($payment->xero_synced_at) && !$this->hasSignificantLocalSyncDrift($payment->updated_at, $payment->xero_synced_at)) {
            return [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'status' => 'skipped',
                'message' => 'Credit note payment already synced to Xero',
            ];
        }

        $currentCompany = $this->getCompany();
        $defaultBankAccount = BankAccount::getDefaultForCompany($currentCompany->id);
        if (!$defaultBankAccount || !$defaultBankAccount->xero_account_id) {
            throw new \Exception("No default bank account configured with Xero account ID. Please set a default bank account with Xero integration in Bank Accounts settings.");
        }

        $paymentData = [
            'CreditNote' => [
                'CreditNoteID' => $creditNote->xero_credit_note_id,
            ],
            'Account' => [
                'AccountID' => $defaultBankAccount->xero_account_id,
            ],
            'Date' => $payment->payment_date->format('Y-m-d'),
            'Amount' => (float) $payment->amount,
            'Reference' => 'Refund for ' . $creditNote->credit_note_number . ($payment->notes ? (' - ' . $payment->notes) : ''),
        ];

        $response = $this->makeXeroRequest('post', $this->baseUrl . '/api.xro/2.0/Payments', [
            'Payments' => [$paymentData],
        ]);

        if (!$response->successful()) {
            $errorBody = $response->body();
            throw new \Exception('Failed to create credit note payment in Xero: ' . $errorBody);
        }

        $result = $response->json();
        $xeroPayment = $result['Payments'][0] ?? [];
        $syncStamp = !empty($xeroPayment['UpdatedDateUTC']) ? $this->parseXeroDate($xeroPayment['UpdatedDateUTC']) : now();

        if (!empty($xeroPayment['PaymentID'])) {
            $payment->update([
                'xero_payment_id' => $xeroPayment['PaymentID'],
                'xero_synced_at' => $syncStamp,
            ]);
        }

        return [
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'status' => 'success',
            'xero_payment_id' => $xeroPayment['PaymentID'] ?? null,
        ];
    }

    private function createOrUpdateCreditNoteInXero(CreditNote $creditNote): array
    {
        if (!$creditNote->relationLoaded('lineItems')) {
            $creditNote->load(['lineItems.product', 'lineItems.account']);
        }

        if (!$creditNote->relationLoaded('customer')) {
            $creditNote->load('customer');
        }

        if ($creditNote->lineItems->isEmpty()) {
            throw new \Exception("Credit note '{$creditNote->credit_note_number}' has no line items. Cannot sync to Xero.");
        }

        $currentCompany = $this->getCompany();
        $defaultTaxRate = TaxRate::getDefaultSalesForCompany($currentCompany->id);
        $defaultTaxCode = $defaultTaxRate && $defaultTaxRate->xero_tax_rate_id
            ? $defaultTaxRate->xero_tax_rate_id
            : ($defaultTaxRate && $defaultTaxRate->code ? $defaultTaxRate->code : 'TAX002');

        $fallbackSalesAccountCode = ChartOfAccount::where('company_id', $currentCompany->id)
            ->where('is_active', true)
            ->where('account_code', '1000')
            ->value('account_code')
            ?? ChartOfAccount::getDefaultSalesForCompany($currentCompany->id)?->account_code
            ?? '1000';

        $lineItems = [];
        foreach ($creditNote->lineItems as $lineItem) {
            $accountCode = $fallbackSalesAccountCode;
            if ($lineItem->account_id && $lineItem->account && !empty($lineItem->account->account_code)) {
                $accountCode = $lineItem->account->account_code;
            }

            $lineAmount = (float) $lineItem->total;
            $discountAmount = (float) ($lineItem->discount_amount ?? 0);
            $discountPercentage = (float) ($lineItem->discount_percentage ?? 0);

            $expectedSubtotal = $lineItem->quantity * $lineItem->unit_price;
            $calculatedLineAmount = $expectedSubtotal;

            if ($discountPercentage > 0) {
                $calculatedLineAmount = $expectedSubtotal * (1 - ($discountPercentage / 100));
            } elseif ($discountAmount > 0) {
                $calculatedLineAmount = $expectedSubtotal - $discountAmount;
            }

            if (abs($calculatedLineAmount - $lineAmount) > 0.01 && ($discountPercentage > 0 || $discountAmount > 0)) {
                $lineAmount = round($calculatedLineAmount, 2);
            }

            $taxTypeCode = $defaultTaxCode;
            if ($lineItem->tax_rate_id) {
                $lineTaxRate = TaxRate::find($lineItem->tax_rate_id);
                if ($lineTaxRate) {
                    $taxTypeCode = $lineTaxRate->xero_tax_rate_id ?? $lineTaxRate->code ?? $defaultTaxCode;
                }
            }

            $lineItemData = [
                'Description' => $lineItem->description ?? 'Item',
                'Quantity' => $lineItem->quantity,
                // Xero validates line totals as Quantity * UnitAmount when no Discount* is provided.
                // For discounted lines, send net UnitAmount so Xero expected totals match local totals.
                'UnitAmount' => round(
                    ($discountPercentage > 0 || $discountAmount > 0)
                        ? ($lineItem->quantity > 0 ? ((float) $lineAmount / (float) $lineItem->quantity) : (float) $lineAmount)
                        : (float) $lineItem->unit_price,
                    4
                ),
                'AccountCode' => ($lineItem->account_id && $lineItem->account ? $lineItem->account->account_code : null) ?? $lineItem->account_code ?? $accountCode,
                'TaxType' => $taxTypeCode,
            ];

            $itemCode = $this->resolveXeroItemCodeForLineItem($lineItem);
            if (!empty($itemCode)) {
                $lineItemData['ItemCode'] = $itemCode;
            }

            if ($discountPercentage <= 0 && $discountAmount <= 0) {
                $lineItemData['LineAmount'] = round($lineAmount, 2);
            }

            $lineItems[] = $lineItemData;
        }

        $data = [
            'Type' => 'ACCRECCREDIT',
            'Contact' => ['ContactID' => $creditNote->customer->xero_contact_id],
            'Date' => $creditNote->credit_note_date->format('Y-m-d'),
            'LineItems' => $lineItems,
            'LineAmountTypes' => 'Exclusive',
            'Status' => $this->mapCreditNoteStatus($creditNote->status),
            'Reference' => $creditNote->reference ?? $creditNote->credit_note_number,
            'CreditNoteNumber' => $creditNote->credit_note_number,
        ];

        if ($creditNote->xero_credit_note_id) {
            if ($creditNote->status === 'voided') {
                // Void transitions should be status-only updates.
                $data = [
                    'CreditNoteID' => $creditNote->xero_credit_note_id,
                    'Status' => 'VOIDED',
                ];
            } else {
                $data['CreditNoteID'] = $creditNote->xero_credit_note_id;
            }

            // Credit notes with allocations/applied amounts are often non-editable in Xero.
            // Skip outbound mutation to avoid repeated validation failures.
            $existingXeroCreditNote = $this->getXeroCreditNote($creditNote->xero_credit_note_id);
            if ($existingXeroCreditNote) {
                $allocationCount = isset($existingXeroCreditNote['Allocations']) && is_array($existingXeroCreditNote['Allocations'])
                    ? count($existingXeroCreditNote['Allocations'])
                    : 0;
                $paymentCount = isset($existingXeroCreditNote['Payments']) && is_array($existingXeroCreditNote['Payments'])
                    ? count($existingXeroCreditNote['Payments'])
                    : 0;
                $xeroTotal = (float) ($existingXeroCreditNote['Total'] ?? $creditNote->total ?? 0);
                $remainingCredit = (float) ($existingXeroCreditNote['RemainingCredit'] ?? $creditNote->remaining_credit ?? 0);
                $appliedAmount = max(0, $xeroTotal - $remainingCredit);
                $xeroStatus = (string) ($existingXeroCreditNote['Status'] ?? '');

                $isLockedForEdit = $allocationCount > 0
                    || $paymentCount > 0
                    || $appliedAmount > 0.01
                    || in_array($xeroStatus, ['PAID', 'VOIDED'], true);
                $isVoidingTransition = $creditNote->status === 'voided' && $xeroStatus !== 'VOIDED';

                if ($isLockedForEdit && !$isVoidingTransition) {
                    Log::info('Skipping outbound credit note update because Xero credit note is non-editable', [
                        'credit_note_id' => $creditNote->id,
                        'credit_note_number' => $creditNote->credit_note_number,
                        'xero_credit_note_id' => $creditNote->xero_credit_note_id,
                        'xero_status' => $xeroStatus,
                        'allocation_count' => $allocationCount,
                        'payment_count' => $paymentCount,
                        'applied_amount' => $appliedAmount,
                        'remaining_credit' => $remainingCredit,
                    ]);

                    $existingXeroCreditNote['_sync_skipped_reason'] = 'Skipped outbound update: credit note is allocated/applied and non-editable in Xero';
                    return $existingXeroCreditNote;
                }
            }
        }

        Log::info('Creating/updating credit note in Xero', [
            'credit_note_id' => $creditNote->id,
            'credit_note_number' => $creditNote->credit_note_number,
            'xero_credit_note_id' => $creditNote->xero_credit_note_id,
            'line_items_count' => count($lineItems),
            'total' => $creditNote->total,
        ]);

        $response = $this->makeXeroRequest('post', $this->baseUrl . '/api.xro/2.0/CreditNotes', [
            'CreditNotes' => [$data],
        ]);

        if (!$response->successful()) {
            $errorBody = $response->body();
            Log::error('Failed to create/update credit note in Xero', [
                'credit_note_id' => $creditNote->id,
                'status' => $response->status(),
                'response' => $errorBody,
            ]);
            throw new \Exception('Failed to create/update credit note in Xero: ' . $errorBody);
        }

        return $response->json()['CreditNotes'][0] ?? [];
    }

    private function createCreditNoteFromXero(array $xeroNote, Company $company): CreditNote
    {
        $customer = Customer::where('company_id', $company->id)
            ->where('xero_contact_id', $xeroNote['Contact']['ContactID'] ?? null)
            ->first();

        if (!$customer && !empty($xeroNote['Contact']['ContactID'])) {
            $contactResponse = $this->makeXeroRequest(
                'get',
                $this->baseUrl . '/api.xro/2.0/Contacts/' . $xeroNote['Contact']['ContactID']
            );
            if ($contactResponse->successful()) {
                $contactData = $contactResponse->json()['Contacts'][0] ?? null;
                if ($contactData) {
                    $customer = $this->createCustomerFromXero($contactData, $company);
                }
            }
        }

        if (!$customer) {
            throw new \Exception('Could not resolve customer for credit note ' . ($xeroNote['CreditNoteNumber'] ?? $xeroNote['CreditNoteID']));
        }

        $invoiceId = $this->resolveCreditNoteInvoiceId($xeroNote, $company);

        $creditNote = CreditNote::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoiceId,
            'xero_credit_note_id' => $xeroNote['CreditNoteID'],
            'credit_note_number' => $xeroNote['CreditNoteNumber'] ?? CreditNote::generateCreditNoteNumber($company->id),
            'title' => $xeroNote['Reference'] ?? null,
            'status' => $this->mapXeroCreditNoteStatusToLocal($xeroNote['Status'] ?? 'DRAFT'),
            'credit_note_date' => isset($xeroNote['Date']) ? $this->parseXeroDate($xeroNote['Date']) : now(),
            'subtotal' => (float) ($xeroNote['SubTotal'] ?? 0),
            'tax_amount' => (float) ($xeroNote['TotalTax'] ?? 0),
            'total' => (float) ($xeroNote['Total'] ?? 0),
            'remaining_credit' => (float) ($xeroNote['RemainingCredit'] ?? 0),
            'reference' => $xeroNote['Reference'] ?? null,
            ...$this->getXeroTimestamps($xeroNote),
        ]);
        $this->alignLocalUpdatedAtWithXero($creditNote);

        $this->createCreditNoteLineItemsFromXero($creditNote, $xeroNote['LineItems'] ?? [], $company);
        $this->syncInvoiceStatusAfterCreditNoteImport($invoiceId, $company->id);

        return $creditNote;
    }

    private function updateCreditNoteFromXeroData(CreditNote $creditNote, array $xeroNote): void
    {
        $invoiceId = $this->resolveCreditNoteInvoiceId($xeroNote, Company::find($creditNote->company_id)) ?? $creditNote->invoice_id;

        $updated = $this->updateModelIfChanged($creditNote, [
            'status' => $this->mapXeroCreditNoteStatusToLocal($xeroNote['Status'] ?? 'DRAFT'),
            'total' => (float) ($xeroNote['Total'] ?? $creditNote->total),
            'subtotal' => (float) ($xeroNote['SubTotal'] ?? $creditNote->subtotal),
            'tax_amount' => (float) ($xeroNote['TotalTax'] ?? $creditNote->tax_amount),
            'remaining_credit' => (float) ($xeroNote['RemainingCredit'] ?? $creditNote->remaining_credit),
            'invoice_id' => $invoiceId,
            'reference' => $xeroNote['Reference'] ?? $creditNote->reference,
            'credit_note_date' => isset($xeroNote['Date']) ? $this->parseXeroDate($xeroNote['Date']) : $creditNote->credit_note_date,
            ...$this->getXeroTimestamps($xeroNote),
        ], 'credit_note', [
            'credit_note_id' => $creditNote->id,
            'xero_credit_note_id' => $xeroNote['CreditNoteID'] ?? $creditNote->xero_credit_note_id,
        ]);
        if ($updated) {
            $this->alignLocalUpdatedAtWithXero($creditNote);
        }

        if (isset($xeroNote['LineItems']) && is_array($xeroNote['LineItems']) && count($xeroNote['LineItems']) > 0) {
            $creditNote->lineItems()->delete();
            $this->createCreditNoteLineItemsFromXero($creditNote, $xeroNote['LineItems'], Company::find($creditNote->company_id));
        } elseif (isset($xeroNote['LineItems']) && is_array($xeroNote['LineItems'])) {
            Log::info('Skipping credit note line item replacement due to empty Xero LineItems payload', [
                'credit_note_id' => $creditNote->id,
                'credit_note_number' => $creditNote->credit_note_number,
                'xero_credit_note_id' => $xeroNote['CreditNoteID'] ?? $creditNote->xero_credit_note_id,
                'decision_reason' => 'skip_noop',
            ]);
        }

        $this->syncInvoiceStatusAfterCreditNoteImport($invoiceId, $creditNote->company_id);
    }

    private function resolveAccountId(?string $accountCode, int $companyId): ?int
    {
        if (!$accountCode) {
            return null;
        }

        $account = ChartOfAccount::where('company_id', $companyId)
            ->where('account_code', $accountCode)
            ->first();

        return $account?->id;
    }

    private function resolveTaxRateId(?string $taxType, int $companyId): ?int
    {
        if (!$taxType) {
            return null;
        }

        $taxRate = TaxRate::where('company_id', $companyId)
            ->where(function ($query) use ($taxType) {
                $query->where('code', $taxType)
                    ->orWhere('xero_tax_rate_id', $taxType);
            })
            ->first();

        return $taxRate?->id;
    }

    private function resolveCreditNoteInvoiceId(array $xeroNote, ?Company $company): ?int
    {
        if (!$company || empty($xeroNote['Allocations'])) {
            return null;
        }

        foreach ($xeroNote['Allocations'] as $allocation) {
            $xeroInvoiceId = $allocation['Invoice']['InvoiceID'] ?? $allocation['InvoiceID'] ?? null;
            if (!empty($xeroInvoiceId)) {
                $localInvoice = Invoice::where('company_id', $company->id)
                    ->where('xero_invoice_id', $xeroInvoiceId)
                    ->first();
                if ($localInvoice) {
                    return $localInvoice->id;
                }
            }

            $invoiceNumber = $allocation['Invoice']['InvoiceNumber'] ?? $allocation['InvoiceNumber'] ?? null;
            if (!empty($invoiceNumber)) {
                $localInvoice = Invoice::where('company_id', $company->id)
                    ->where('invoice_number', $invoiceNumber)
                    ->first();
                if ($localInvoice) {
                    return $localInvoice->id;
                }
            }
        }

        return null;
    }

    private function syncInvoiceStatusAfterCreditNoteImport(?int $invoiceId, int $companyId): void
    {
        if (!$invoiceId) {
            return;
        }

        $invoice = Invoice::where('company_id', $companyId)
            ->whereKey($invoiceId)
            ->first();
        if (!$invoice) {
            return;
        }

        $invoice->refresh();

        if ($invoice->isFullyPaid() && $invoice->status !== 'paid') {
            $invoice->update(['status' => 'paid']);
            Log::info('Marked invoice as paid after credit note import from Xero', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'decision_reason' => 'credit_note_allocation_settled_invoice',
            ]);
        }
    }

    private function createCreditNoteLineItemsFromXero(CreditNote $creditNote, array $xeroLineItems, Company $company): void
    {
        $sortOrder = 0;
        foreach ($xeroLineItems as $xeroLineItem) {
            $product = null;
            if (!empty($xeroLineItem['ItemCode'])) {
                $product = Product::where('company_id', $company->id)
                    ->where('sku', $xeroLineItem['ItemCode'])
                    ->first();
            }
            if (!$product && !empty($xeroLineItem['ItemCode'])) {
                $product = Product::where('company_id', $company->id)
                    ->where('name', $xeroLineItem['ItemCode'])
                    ->first();
            }

            $taxRateId = null;
            if (!empty($xeroLineItem['TaxType'])) {
                $taxRate = TaxRate::where('company_id', $company->id)
                    ->where(function ($q) use ($xeroLineItem) {
                        $q->where('code', $xeroLineItem['TaxType'])
                          ->orWhere('xero_tax_rate_id', $xeroLineItem['TaxType']);
                    })->first();
                $taxRateId = $taxRate?->id;
            }

            CreditNoteLineItem::create([
                'credit_note_id' => $creditNote->id,
                'product_id' => $product?->id,
                'tax_rate_id' => $taxRateId,
                'description' => $xeroLineItem['Description'] ?? 'Item',
                'quantity' => (int) ($xeroLineItem['Quantity'] ?? 1),
                'unit_price' => (float) ($xeroLineItem['UnitAmount'] ?? 0),
                'discount_amount' => (float) ($xeroLineItem['DiscountAmount'] ?? 0),
                'discount_percentage' => (float) ($xeroLineItem['DiscountRate'] ?? 0),
                'tax_amount' => (float) ($xeroLineItem['TaxAmount'] ?? 0),
                'total' => (float) ($xeroLineItem['LineAmount'] ?? 0),
                'account_code' => $xeroLineItem['AccountCode'] ?? null,
                'account_id' => $this->resolveAccountId($xeroLineItem['AccountCode'] ?? null, $company->id),
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    private function mapCreditNoteStatus(string $status): string
    {
        return match ($status) {
            'draft' => 'DRAFT',
            'submitted' => 'SUBMITTED',
            'authorised' => 'AUTHORISED',
            'paid' => 'AUTHORISED',
            'voided' => 'VOIDED',
            default => 'DRAFT',
        };
    }

    private function mapXeroCreditNoteStatusToLocal(string $xeroStatus): string
    {
        return match ($xeroStatus) {
            'DRAFT' => 'draft',
            'SUBMITTED' => 'submitted',
            'AUTHORISED' => 'authorised',
            'PAID' => 'paid',
            'VOIDED' => 'voided',
            default => 'draft',
        };
    }

    // ========================================================================
    // Purchase Order Sync Methods
    // ========================================================================

    public function syncPurchaseOrdersToXero(Company $company = null): array
    {
        if (!$this->settings->sync_purchase_orders_to_xero) {
            return ['skipped' => true, 'message' => 'Purchase order sync to Xero is disabled'];
        }

        $currentCompany = $this->getCompany();
        $results = [];

        $purchaseOrders = PurchaseOrder::where('company_id', $currentCompany->id)
            ->where(function ($query) {
                $query->whereNull('xero_purchase_order_id')
                    ->orWhereNull('xero_updated_at')
                    ->orWhereColumn('updated_at', '>', 'xero_updated_at');
            })
            ->orderByDesc('updated_at')
            ->with(['supplier', 'items.product'])
            ->get();

        foreach ($purchaseOrders as $po) {
            try {
                if (!$po->supplier || !$po->supplier->xero_contact_id) {
                    $results[] = [
                        'po_id' => $po->id,
                        'po_number' => $po->po_number,
                        'status' => 'error',
                        'error' => 'Supplier not synced to Xero',
                    ];
                    continue;
                }

                if ($po->xero_purchase_order_id) {
                    $xeroPO = $this->getXeroPurchaseOrder($po->xero_purchase_order_id);
                    if ($xeroPO && $this->isXeroRecordNewerThanLocal($po, $xeroPO)) {
                        $this->updatePurchaseOrderFromXeroData($po, $xeroPO);
                        $results[] = [
                            'po_id' => $po->id,
                            'po_number' => $po->po_number,
                            'status' => 'updated_from_xero',
                            'message' => 'Purchase order updated from Xero (Xero was newer)',
                        ];
                        continue;
                    }
                }

                $xeroData = $this->createOrUpdatePurchaseOrderInXero($po);

                if (!$po->xero_purchase_order_id && isset($xeroData['PurchaseOrderID'])) {
                    $po->update([
                        'xero_purchase_order_id' => $xeroData['PurchaseOrderID'],
                        ...$this->getXeroTimestamps($xeroData),
                    ]);
                } else {
                    $po->update($this->getXeroTimestamps($xeroData));
                }
                $this->alignLocalUpdatedAtWithXero($po);

                $results[] = [
                    'po_id' => $po->id,
                    'po_number' => $po->po_number,
                    'status' => 'success',
                ];
            } catch (\Exception $e) {
                Log::error('Purchase order sync to Xero failed', [
                    'po_id' => $po->id,
                    'error' => $e->getMessage(),
                ]);
                $results[] = [
                    'po_id' => $po->id,
                    'po_number' => $po->po_number,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function syncPurchaseOrdersFromXero(Company $company = null): array
    {
        if (!$this->settings->sync_purchase_orders_from_xero) {
            return ['skipped' => true, 'message' => 'Purchase order sync from Xero is disabled'];
        }

        $currentCompany = $this->getCompany();
        $results = [];
        $syncStartedAt = now();
        $syncFullyCompleted = false;

        try {
            $hasAnyLocalPurchaseOrders = PurchaseOrder::where('company_id', $currentCompany->id)->exists();
            $localPurchaseOrderCount = PurchaseOrder::where('company_id', $currentCompany->id)->count();
            $fullSyncCompletedKey = self::getInitialSyncCompletedCacheKey($currentCompany->id, 'purchase_order');
            $cursorKey = self::getInitialSyncCursorCacheKey($currentCompany->id, 'purchase_order');
            $paginationKey = self::getInitialSyncPaginationCacheKey($currentCompany->id, 'purchase_order');
            $lastSync = $this->getLastSyncDatetimeForModule(self::SYNC_MODULE_PURCHASE_ORDERS);
            $fullSyncCompleted = (bool) Cache::get($fullSyncCompletedKey, false);
            if (!$fullSyncCompleted && $hasAnyLocalPurchaseOrders && !empty($lastSync)) {
                $fullSyncCompleted = true;
                Cache::put($fullSyncCompletedKey, true, now()->addDays(365));
                Log::info('Rehydrated purchase order full-sync completion state from xero_sync_states', [
                    'company_id' => $currentCompany->id,
                    'last_sync' => $lastSync,
                ]);
            }
            $isBackfillMode = !$hasAnyLocalPurchaseOrders || !$fullSyncCompleted;
            $cursor = Cache::get($cursorKey, ['page' => 1]);

            // Self-heal edge case where completed flag was set previously but historical data
            // was never fully imported (observed as only 0-1 local purchase orders).
            if (!$isBackfillMode && $localPurchaseOrderCount <= 1) {
                Log::warning('Purchase order full-sync flag appears stale; forcing backfill restart', [
                    'company_id' => $currentCompany->id,
                    'local_purchase_orders' => $localPurchaseOrderCount,
                ]);
                $isBackfillMode = true;
                Cache::forget($fullSyncCompletedKey);
                Cache::put($cursorKey, ['page' => 1], now()->addDays(7));
                $cursor = ['page' => 1];
            }

            // During backfill mode we intentionally avoid If-Modified-Since so older pages are not skipped.
            $ifModifiedSince = $isBackfillMode ? [] : $this->buildIfModifiedSinceHeader($lastSync);

            $page = (int) ($isBackfillMode ? ($cursor['page'] ?? 1) : 1);
            if ($page < 1) {
                $page = 1;
            }
            $pageSize = max(1, min((int) config('services.xero.purchase_order_import_page_size', 50), 100));
            $maxPagesPerRun = max(1, (int) config('services.xero.purchase_order_import_max_pages_per_run', 3));
            $maxPurchaseOrdersPerRun = max(1, (int) config('services.xero.purchase_order_import_max_pos_per_run', 150));
            $maxSecondsPerRun = max(5, (int) config('services.xero.purchase_order_import_max_seconds_per_run', 35));
            $pageDelayMs = max(0, (int) config('services.xero.purchase_order_import_page_delay_ms', 300));
            $hasMorePages = false;
            $pagesProcessed = 0;
            $processedThisRun = 0;
            $startedAt = microtime(true);
            $stopReason = null;

            Log::info('Starting purchase order import from Xero', [
                'company_id' => $currentCompany->id,
                'mode' => $isBackfillMode ? 'backfill' : 'incremental',
                'start_page' => $page,
                'page_size' => $pageSize,
            ]);

            do {
                if (!$isBackfillMode && $processedThisRun >= $maxPurchaseOrdersPerRun) {
                    $stopReason = 'max_purchase_orders_per_run_reached';
                    break;
                }
                if (!$isBackfillMode && (microtime(true) - $startedAt) >= $maxSecondsPerRun) {
                    $stopReason = 'max_seconds_per_run_reached';
                    break;
                }

                $response = $this->makeXeroRequest(
                    'get',
                    $this->baseUrl . '/api.xro/2.0/PurchaseOrders?page=' . $page . '&pageSize=' . $pageSize . '&summaryOnly=false',
                    [],
                    2,
                    $ifModifiedSince
                );

                if (!$response->successful()) {
                    throw new \Exception('Failed to fetch purchase orders from Xero: ' . $response->body());
                }

                $responseData = $response->json();
                $xeroPOs = $responseData['PurchaseOrders'] ?? [];
                $pagination = $responseData['Pagination'] ?? null;
                $currentPage = $pagination['Page'] ?? $page;
                $pageCount = $pagination['PageCount'] ?? 1;
                $poCountOnPage = count($xeroPOs);
                $hasPaginationPageCount = is_array($pagination) && isset($pagination['PageCount']) && is_numeric($pagination['PageCount']);
                $normalizedPageCount = is_numeric($pageCount) ? (int) $pageCount : null;
                if ($isBackfillMode && $hasPaginationPageCount && $normalizedPageCount !== null && $normalizedPageCount >= 1 && $page > $normalizedPageCount) {
                    Log::warning('Purchase order backfill cursor exceeded available pages; resetting to page 1', [
                        'company_id' => $currentCompany->id,
                        'requested_page' => $page,
                        'reported_current_page' => $currentPage,
                        'reported_page_count' => $normalizedPageCount,
                    ]);
                    $page = 1;
                    Cache::put($cursorKey, ['page' => $page], now()->addDays(7));
                    $hasMorePages = true;
                    continue;
                }
                if ($hasPaginationPageCount) {
                    $hasMorePages = $currentPage < $pageCount;
                } else {
                    $hasMorePages = $poCountOnPage >= $pageSize;
                }
                Cache::put($paginationKey, [
                    'page' => is_numeric($currentPage) ? (int) $currentPage : $page,
                    'page_count' => is_numeric($pageCount) ? (int) $pageCount : null,
                    'item_count' => is_numeric($pagination['ItemCount'] ?? null) ? (int) $pagination['ItemCount'] : null,
                    'page_size' => $pageSize,
                    'captured_at' => now()->toIso8601String(),
                ], now()->addDays(7));

                foreach ($xeroPOs as $xeroPO) {
                    if (!$isBackfillMode && $processedThisRun >= $maxPurchaseOrdersPerRun) {
                        $stopReason = 'max_purchase_orders_per_run_reached';
                        break;
                    }
                    if (!$isBackfillMode && (microtime(true) - $startedAt) >= $maxSecondsPerRun) {
                        $stopReason = 'max_seconds_per_run_reached';
                        break;
                    }

                    try {
                        // Xero list endpoints may omit LineItems on page payloads even with summaryOnly=false.
                        // Hydrate PO details when needed so local items are consistently imported.
                        $xeroPODetails = $this->hydratePurchaseOrderDetails($xeroPO);

                        $existing = PurchaseOrder::where('company_id', $currentCompany->id)
                            ->where('xero_purchase_order_id', $xeroPODetails['PurchaseOrderID'] ?? null)
                            ->first();

                        if (!$existing && !empty($xeroPODetails['PurchaseOrderNumber'])) {
                            $existing = PurchaseOrder::where('company_id', $currentCompany->id)
                                ->where('po_number', $xeroPODetails['PurchaseOrderNumber'])
                                ->first();
                        }

                        if ($existing) {
                            $localLineItemCount = $existing->items()->count();
                            $xeroLineItemCount = isset($xeroPODetails['LineItems']) && is_array($xeroPODetails['LineItems'])
                                ? count($xeroPODetails['LineItems'])
                                : 0;
                            $needsLineItemBackfill = $localLineItemCount === 0 && $xeroLineItemCount > 0;

                            if (!$this->xeroUpdatedAtChanged($existing, $xeroPODetails) && !$needsLineItemBackfill) {
                                $results[] = [
                                    'po_number' => $existing->po_number,
                                    'status' => 'skipped',
                                ];
                                $processedThisRun++;
                                continue;
                            }
                            $this->updatePurchaseOrderFromXeroData($existing, $xeroPODetails);
                            if (!$existing->xero_purchase_order_id) {
                                $existing->update(['xero_purchase_order_id' => $xeroPODetails['PurchaseOrderID']]);
                                $this->alignLocalUpdatedAtWithXero($existing);
                            }
                            $results[] = [
                                'po_number' => $existing->po_number,
                                'status' => 'updated',
                            ];
                            $processedThisRun++;
                        } else {
                            $po = $this->createPurchaseOrderFromXero($xeroPODetails, $currentCompany);
                            $results[] = [
                                'po_number' => $po->po_number,
                                'status' => 'created',
                            ];
                            $processedThisRun++;
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to process purchase order from Xero', [
                            'po_id' => $xeroPO['PurchaseOrderID'] ?? 'Unknown',
                            'error' => $e->getMessage(),
                        ]);
                        $results[] = [
                            'po_number' => $xeroPO['PurchaseOrderNumber'] ?? 'Unknown',
                            'status' => 'error',
                            'error' => $e->getMessage(),
                        ];
                        $processedThisRun++;
                    }
                }

                $page++;
                $pagesProcessed++;
                if (!$isBackfillMode && $pagesProcessed >= $maxPagesPerRun) {
                    $stopReason = 'max_pages_per_run_reached';
                }
                if ($isBackfillMode) {
                    Cache::put($cursorKey, ['page' => $page], now()->addDays(7));
                }
                if ($hasMorePages && !$stopReason && $pageDelayMs > 0) {
                    usleep($pageDelayMs * 1000);
                }
            } while ($hasMorePages && !$stopReason);

            if ($isBackfillMode && !$hasMorePages && !$stopReason) {
                Cache::put($fullSyncCompletedKey, true, now()->addDays(365));
                Cache::forget($cursorKey);
            }

            Log::info('Finished processing purchase orders from Xero', [
                'company_id' => $currentCompany->id,
                'mode' => $isBackfillMode ? 'backfill' : 'incremental',
                'pages_processed' => $pagesProcessed,
                'processed_this_run' => $processedThisRun,
                'stop_reason' => $stopReason,
            ]);
            $syncFullyCompleted = !$stopReason && !$hasMorePages;
        } catch (\Exception $e) {
            Log::error('Failed to sync purchase orders from Xero', [
                'error' => $e->getMessage(),
            ]);
            return ['skipped' => true, 'message' => 'Failed: ' . $e->getMessage()];
        }

        if ($syncFullyCompleted) {
            $this->recordSyncDatetimeForModule(self::SYNC_MODULE_PURCHASE_ORDERS, $syncStartedAt);
        }

        return $results;
    }

    private function createOrUpdatePurchaseOrderInXero(PurchaseOrder $po): array
    {
        $po->load(['items.product', 'items.account', 'supplier']);

        $currentCompany = $this->getCompany();
        $defaultTaxRate = TaxRate::getDefaultPurchasingForCompany($currentCompany->id);
        $defaultTaxCode = $defaultTaxRate && $defaultTaxRate->xero_tax_rate_id
            ? $defaultTaxRate->xero_tax_rate_id
            : ($defaultTaxRate && $defaultTaxRate->code ? $defaultTaxRate->code : 'TAX002');

        $lineItems = [];
        foreach ($po->items as $item) {
            $accountCode = '300';
            if ($item->account_id && $item->account) {
                $accountCode = $item->account->account_code ?? '300';
            } elseif ($item->product_id && $item->product) {
                $accountCode = $item->product->purchase_account_code ?? '300';
            } else {
                $defaultAccount = ChartOfAccount::getDefaultPurchasingForCompany($currentCompany->id);
                if ($defaultAccount) {
                    $accountCode = $defaultAccount->account_code;
                }
            }

            $lineItemData = [
                'Description' => $item->description ?? ($item->product ? $item->product->name : 'Item'),
                'Quantity' => $item->quantity,
                'UnitAmount' => $item->unit_cost,
                'LineAmount' => (float) $item->total,
                'AccountCode' => $accountCode,
                'TaxType' => $defaultTaxCode,
            ];

            $itemCode = $this->resolveXeroItemCodeForLineItem($item);
            if (!empty($itemCode)) {
                $lineItemData['ItemCode'] = $itemCode;
            }

            $lineItems[] = $lineItemData;
        }

        $data = [
            'Contact' => ['ContactID' => $po->supplier->xero_contact_id],
            'Date' => $po->order_date->format('Y-m-d'),
            'LineItems' => $lineItems,
            'Status' => $this->mapPurchaseOrderStatus($po->status),
            'PurchaseOrderNumber' => $po->po_number,
            'Reference' => $po->po_number,
        ];

        if ($po->expected_delivery_date) {
            $data['DeliveryDate'] = $po->expected_delivery_date->format('Y-m-d');
        }

        if ($po->xero_purchase_order_id) {
            $data['PurchaseOrderID'] = $po->xero_purchase_order_id;

            // Xero can reject status transitions during PO updates depending on
            // current remote state (e.g. billed/authorised). Keep status immutable on updates.
            unset($data['Status']);
        }

        $response = $this->makeXeroRequest('post', $this->baseUrl . '/api.xro/2.0/PurchaseOrders', [
            'PurchaseOrders' => [$data],
        ]);

        if (!$response->successful()) {
            $errorBody = $response->body();
            $retryData = $data;
            $shouldRetry = false;

            // Xero can reject status in update-like scenarios, even when we expected a create.
            if (
                str_contains($errorBody, 'PurchaseOrder status change is invalid')
                || str_contains($errorBody, 'Please provide a valid Status Code')
            ) {
                unset($retryData['Status']);
                $shouldRetry = true;
            }

            // Xero may reject account codes for PurchaseOrders depending on org setup.
            if (str_contains($errorBody, 'is not a valid code for this document')) {
                $retryData['LineItems'] = array_map(function (array $line) {
                    unset($line['AccountCode']);
                    return $line;
                }, $retryData['LineItems'] ?? []);
                $shouldRetry = true;
            }

            if ($shouldRetry) {
                $retryResponse = $this->makeXeroRequest('post', $this->baseUrl . '/api.xro/2.0/PurchaseOrders', [
                    'PurchaseOrders' => [$retryData],
                ]);

                if ($retryResponse->successful()) {
                    Log::warning('Purchase order sync succeeded after fallback payload retry', [
                        'purchase_order_id' => $po->id,
                        'po_number' => $po->po_number,
                        'xero_purchase_order_id' => $po->xero_purchase_order_id,
                        'fallback_removed_status' => !isset($retryData['Status']) && isset($data['Status']),
                        'fallback_removed_account_codes' => collect($data['LineItems'] ?? [])
                            ->contains(fn ($line) => array_key_exists('AccountCode', $line))
                            && collect($retryData['LineItems'] ?? [])
                                ->every(fn ($line) => !array_key_exists('AccountCode', $line)),
                    ]);
                    return $retryResponse->json()['PurchaseOrders'][0] ?? [];
                }

                $errorBody = $retryResponse->body();
            }

            throw new \Exception('Failed to create/update purchase order in Xero: ' . $errorBody);
        }

        return $response->json()['PurchaseOrders'][0] ?? [];
    }

    private function hydratePurchaseOrderDetails(array $xeroPO): array
    {
        if (
            empty($xeroPO['PurchaseOrderID']) ||
            (!empty($xeroPO['LineItems']) && !empty($xeroPO['PurchaseOrderNumber']))
        ) {
            return $xeroPO;
        }

        $response = $this->makeXeroRequest(
            'get',
            $this->baseUrl . '/api.xro/2.0/PurchaseOrders/' . $xeroPO['PurchaseOrderID']
        );

        if (!$response->successful()) {
            Log::warning('Could not hydrate purchase order details from Xero', [
                'purchase_order_id' => $xeroPO['PurchaseOrderID'],
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return $xeroPO;
        }

        $detailed = $response->json()['PurchaseOrders'][0] ?? [];
        if (!is_array($detailed) || empty($detailed)) {
            return $xeroPO;
        }

        // Keep the detailed payload authoritative while preserving any fallback keys
        // from the list response that might not be present in the details payload.
        return array_replace($xeroPO, $detailed);
    }

    private function createPurchaseOrderFromXero(array $xeroPO, Company $company): PurchaseOrder
    {
        $supplier = Supplier::where('company_id', $company->id)
            ->where('xero_contact_id', $xeroPO['Contact']['ContactID'] ?? null)
            ->first();

        if (!$supplier && !empty($xeroPO['Contact']['ContactID'])) {
            $contactResponse = $this->makeXeroRequest(
                'get',
                $this->baseUrl . '/api.xro/2.0/Contacts/' . $xeroPO['Contact']['ContactID']
            );
            if ($contactResponse->successful()) {
                $contactData = $contactResponse->json()['Contacts'][0] ?? null;
                if ($contactData) {
                    $supplier = $this->createSupplierFromXero($contactData, $company);
                }
            }
        }

        if (!$supplier) {
            throw new \Exception('Could not resolve supplier for purchase order');
        }

        return DB::transaction(function () use ($xeroPO, $company, $supplier) {
            $xeroPurchaseOrderNumber = trim((string) ($xeroPO['PurchaseOrderNumber'] ?? ''));

            $po = PurchaseOrder::create([
                'company_id' => $company->id,
                'supplier_id' => $supplier->id,
                'xero_purchase_order_id' => $xeroPO['PurchaseOrderID'],
                'po_number' => $xeroPurchaseOrderNumber !== '' ? $xeroPurchaseOrderNumber : PurchaseOrder::generatePONumber($company->id),
                'order_date' => isset($xeroPO['Date']) ? $this->parseXeroDate($xeroPO['Date']) : now(),
                'expected_delivery_date' => isset($xeroPO['DeliveryDate']) ? $this->parseXeroDate($xeroPO['DeliveryDate']) : null,
                'status' => $this->mapXeroPOStatusToLocal($xeroPO['Status'] ?? 'DRAFT'),
                'subtotal' => (float) ($xeroPO['SubTotal'] ?? 0),
                'tax_amount' => (float) ($xeroPO['TotalTax'] ?? 0),
                'total' => (float) ($xeroPO['Total'] ?? 0),
                'notes' => $xeroPO['Reference'] ?? null,
                ...$this->getXeroTimestamps($xeroPO),
            ]);
            $this->alignLocalUpdatedAtWithXero($po);

            $this->syncPurchaseOrderLineItemsFromXero($po, $xeroPO['LineItems'] ?? [], $company->id);

            return $po;
        });
    }

    private function updatePurchaseOrderFromXeroData(PurchaseOrder $po, array $xeroPO): void
    {
        DB::transaction(function () use ($po, $xeroPO) {
            $xeroPurchaseOrderNumber = trim((string) ($xeroPO['PurchaseOrderNumber'] ?? ''));

            $updated = $this->updateModelIfChanged($po, [
                'po_number' => $xeroPurchaseOrderNumber !== '' ? $xeroPurchaseOrderNumber : $po->po_number,
                'status' => $this->mapXeroPOStatusToLocal($xeroPO['Status'] ?? $po->status),
                'subtotal' => (float) ($xeroPO['SubTotal'] ?? $po->subtotal),
                'tax_amount' => (float) ($xeroPO['TotalTax'] ?? $po->tax_amount),
                'total' => (float) ($xeroPO['Total'] ?? $po->total),
                'expected_delivery_date' => isset($xeroPO['DeliveryDate']) ? $this->parseXeroDate($xeroPO['DeliveryDate']) : $po->expected_delivery_date,
                ...$this->getXeroTimestamps($xeroPO),
            ], 'purchase_order', [
                'purchase_order_id' => $po->id,
                'xero_purchase_order_id' => $xeroPO['PurchaseOrderID'] ?? $po->xero_purchase_order_id,
            ]);
            if ($updated) {
                $this->alignLocalUpdatedAtWithXero($po);
            }

            if (!empty($xeroPO['LineItems'])) {
                $po->items()->delete();
                $this->syncPurchaseOrderLineItemsFromXero($po, $xeroPO['LineItems'], $po->company_id);
            } elseif (isset($xeroPO['LineItems']) && is_array($xeroPO['LineItems'])) {
                Log::info('Skipping purchase order line item replacement due to empty Xero LineItems payload', [
                    'purchase_order_id' => $po->id,
                    'po_number' => $po->po_number,
                    'xero_purchase_order_id' => $xeroPO['PurchaseOrderID'] ?? $po->xero_purchase_order_id,
                    'decision_reason' => 'skip_noop',
                ]);
            }
        });
    }

    private function syncPurchaseOrderLineItemsFromXero(PurchaseOrder $po, array $xeroLineItems, int $companyId): void
    {
        foreach ($xeroLineItems as $xeroLineItem) {
            $product = $this->resolveOrCreateProductForPurchaseOrderLine($xeroLineItem, $companyId);

            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'product_id' => $product->id,
                'tax_rate_id' => $this->resolveTaxRateIdForPurchaseOrderLine($xeroLineItem, $companyId),
                'description' => $xeroLineItem['Description'] ?? 'Item',
                'quantity' => (int) ($xeroLineItem['Quantity'] ?? 1),
                'unit_cost' => (float) ($xeroLineItem['UnitAmount'] ?? 0),
                'tax_amount' => (float) ($xeroLineItem['TaxAmount'] ?? 0),
                'total' => (float) ($xeroLineItem['LineAmount'] ?? 0),
                'account_id' => $this->resolveAccountId($xeroLineItem['AccountCode'] ?? null, $po->company_id),
                'quantity_received' => 0,
            ]);
        }
    }

    private function resolveOrCreateProductForPurchaseOrderLine(array $xeroLineItem, int $companyId): Product
    {
        $itemCode = trim((string) ($xeroLineItem['ItemCode'] ?? ($xeroLineItem['Item']['Code'] ?? '')));
        $itemId = trim((string) ($xeroLineItem['ItemID'] ?? ($xeroLineItem['Item']['ItemID'] ?? '')));
        $description = trim((string) ($xeroLineItem['Description'] ?? ''));

        if ($itemId !== '') {
            $existingByXeroId = Product::where('company_id', $companyId)
                ->where('xero_item_id', $itemId)
                ->first();
            if ($existingByXeroId) {
                return $existingByXeroId;
            }
        }

        if ($itemCode !== '') {
            $existingBySku = Product::where('company_id', $companyId)
                ->where('sku', $itemCode)
                ->first();
            if ($existingBySku) {
                return $existingBySku;
            }
        }

        if ($description !== '') {
            $existingByName = Product::where('company_id', $companyId)
                ->where('name', $description)
                ->first();
            if ($existingByName) {
                return $existingByName;
            }
        }

        $quantity = (float) ($xeroLineItem['Quantity'] ?? 1);
        if ($quantity <= 0) {
            $quantity = 1.0;
        }
        $unitAmount = (float) ($xeroLineItem['UnitAmount'] ?? 0);
        $lineAmount = (float) ($xeroLineItem['LineAmount'] ?? 0);
        $price = $unitAmount !== 0.0 ? $unitAmount : ($lineAmount / $quantity);

        $name = $description !== '' ? $description : ($itemCode !== '' ? $itemCode : 'Xero Purchase Item');

        return Product::create([
            'company_id' => $companyId,
            'name' => mb_substr($name, 0, 255),
            'description' => $description !== '' ? $description : null,
            'type' => 'product',
            'sku' => $itemCode !== '' ? $itemCode : null,
            'price' => $price,
            'cost' => $unitAmount,
            'track_stock' => false,
            'is_active' => true,
            'xero_item_id' => $itemId !== '' ? $itemId : null,
        ]);
    }

    private function resolveTaxRateIdForPurchaseOrderLine(array $xeroLineItem, int $companyId): ?int
    {
        if (empty($xeroLineItem['TaxType'])) {
            return null;
        }

        $taxRate = TaxRate::where('company_id', $companyId)
            ->where(function ($q) use ($xeroLineItem) {
                $q->where('code', $xeroLineItem['TaxType'])
                    ->orWhere('xero_tax_rate_id', $xeroLineItem['TaxType']);
            })->first();

        return $taxRate?->id;
    }

    private function mapPurchaseOrderStatus(string $status): string
    {
        return match ($status) {
            'draft' => 'DRAFT',
            'sent' => 'SUBMITTED',
            'received' => 'AUTHORISED',
            'cancelled' => 'DELETED',
            default => 'DRAFT',
        };
    }

    private function mapXeroPOStatusToLocal(string $xeroStatus): string
    {
        return match ($xeroStatus) {
            'DRAFT' => 'draft',
            'SUBMITTED' => 'sent',
            'AUTHORISED' => 'sent',
            'BILLED' => 'received',
            'DELETED' => 'cancelled',
            default => 'draft',
        };
    }

}