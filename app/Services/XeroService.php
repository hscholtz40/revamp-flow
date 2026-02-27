<?php

namespace App\Services;

use App\Models\XeroSettings;
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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XeroService
{
    private $settings;
    private $baseUrl = 'https://api.xero.com';
    private ?array $cachedXeroContacts = null;
    private ?array $cachedXeroAccounts = null;

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
        
        while ($attempt <= $maxRetries) {
            try {
                $headers = array_merge($this->getHeaders(), $additionalHeaders);
                $request = Http::withHeaders($headers);
                
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
                
                // If successful or not a rate limit error, return response
                if ($response->successful() || $response->status() !== 429) {
                    return $response;
                }
                
                // Handle rate limiting (429)
                if ($response->status() === 429) {
                    $retryAfter = (int) ($response->header('Retry-After') ?? 60);
                    
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

        $xeroContactsMap = [];
        try {
            $xeroContacts = $this->fetchXeroContacts();
            foreach ($xeroContacts as $xeroContact) {
                $xeroContactsMap[$xeroContact['ContactID']] = $xeroContact;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch Xero contacts for comparison', ['error' => $e->getMessage()]);
        }

        $customersToSync = [];
        $batchSize = 100;

        Customer::where('company_id', $currentCompany->id)->chunk(200, function ($customers) use (&$results, &$customersToSync, $xeroContactsMap, $batchSize) {
            foreach ($customers as $customer) {
                try {
                    if ($customer->xero_contact_id && isset($xeroContactsMap[$customer->xero_contact_id])) {
                        $xeroCustomer = $xeroContactsMap[$customer->xero_contact_id];
                        
                        if ($this->xeroUpdatedAtChanged($customer, $xeroCustomer)) {
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
        });

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
    public function syncCustomersFromXero(Company $company = null): array
    {
        if (!$this->settings->sync_customers_from_xero) {
            return ['skipped' => true, 'message' => 'Customer sync from Xero is disabled'];
        }

        $currentCompany = $this->getCompany();
        $results = [];

        try {
            $xeroContacts = $this->fetchXeroContacts();
            
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
                            $results[] = [
                                'customer_id' => $existingCustomer->id,
                                'customer_name' => $xeroContact['Name'],
                                'status' => 'skipped',
                                'message' => 'Customer unchanged in Xero',
                            ];
                            continue;
                        }
                        $this->updateCustomerFromXero($existingCustomer, $xeroContact);
                        $results[] = [
                            'customer_id' => $existingCustomer->id,
                            'customer_name' => $xeroContact['Name'],
                            'status' => 'updated',
                            'message' => 'Customer updated from Xero and linked to existing customer',
                        ];
                    } else {
                        // Create new customer
                        $customer = $this->createCustomerFromXero($xeroContact, $currentCompany);
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

        $xeroItemsMap = [];
        try {
            $response = $this->makeXeroRequest('get', $this->baseUrl . '/api.xro/2.0/Items');
            if ($response->successful()) {
                foreach ($response->json()['Items'] ?? [] as $xeroItem) {
                    $xeroItemsMap[$xeroItem['ItemID']] = $xeroItem;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch Xero items for comparison', ['error' => $e->getMessage()]);
        }

        $productsToSync = [];
        $batchSize = 100;

        Product::where('company_id', $currentCompany->id)->chunk(200, function ($products) use (&$results, &$productsToSync, $xeroItemsMap, $batchSize) {
            foreach ($products as $product) {
                try {
                    if ($product->xero_item_id && isset($xeroItemsMap[$product->xero_item_id])) {
                        $xeroItem = $xeroItemsMap[$product->xero_item_id];
                        
                        if ($this->xeroUpdatedAtChanged($product, $xeroItem)) {
                            $this->updateProductFromXero($product, $xeroItem);
                            $results[] = [
                                'product_id' => $product->id,
                                'product_name' => $product->name,
                                'status' => 'updated_from_xero',
                                'message' => 'Product updated from Xero (Xero was newer)',
                            ];
                            continue;
                        }
                    }
                    
                    $itemData = [
                        'Code' => $product->sku,
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
        });

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

        try {
            $lastSync = Product::where('company_id', $currentCompany->id)
                ->whereNotNull('xero_updated_at')->max('xero_updated_at');

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
            'Code' => $product->sku,
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

    /**
     * Sync invoices to Xero
     */
    public function syncInvoicesToXero(Company $company = null): array
    {
        if (!$this->settings->sync_invoices_to_xero) {
            return ['skipped' => true, 'message' => 'Invoice sync to Xero is disabled'];
        }

        $currentCompany = $this->getCompany();
        
        $oneHourAgo = now()->subHour();
        $invoices = Invoice::where('company_id', $currentCompany->id)
            ->where('updated_at', '>=', $oneHourAgo)
            ->with(['customer', 'lineItems'])
            ->get();
        $results = [];
        
        Log::info('Starting invoice sync to Xero', [
            'company_id' => $currentCompany->id,
            'invoice_count' => $invoices->count(),
            'filter_applied' => true,
            'modified_since' => $oneHourAgo->toIso8601String(),
        ]);

        // Pre-fetch all Xero invoices once to avoid N+1 individual GET requests
        $xeroInvoicesMap = [];
        try {
            $response = $this->makeXeroRequest(
                'get',
                $this->baseUrl . '/api.xro/2.0/Invoices',
                [],
                2,
                $this->buildIfModifiedSinceHeader($oneHourAgo->toIso8601String())
            );
            if ($response->successful()) {
                foreach ($response->json()['Invoices'] ?? [] as $xi) {
                    $xeroInvoicesMap[$xi['InvoiceID']] = $xi;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to pre-fetch Xero invoices for comparison', ['error' => $e->getMessage()]);
        }

        foreach ($invoices as $index => $invoice) {
            try {
                if ($index > 0) {
                    sleep(1);
                }
                
                if ($invoice->xero_invoice_id) {
                    $xeroInvoice = $xeroInvoicesMap[$invoice->xero_invoice_id] ?? null;
                    if ($xeroInvoice) {
                        // Check payment status in both systems
                        $isPaidInXero = ($xeroInvoice['AmountDue'] ?? $xeroInvoice['AmountOwing'] ?? $xeroInvoice['Total'] ?? 0) <= 0.01;
                        $isPaidLocally = $invoice->isFullyPaid();
                        
                        // If paid in both systems, skip updating
                        if ($isPaidInXero && $isPaidLocally) {
                            Log::info('Invoice is fully paid in both systems, skipping update', [
                                'invoice_id' => $invoice->id,
                                'invoice_number' => $invoice->invoice_number,
                                'xero_invoice_id' => $invoice->xero_invoice_id,
                            ]);
                            $results[] = [
                                'invoice_id' => $invoice->id,
                                'invoice_number' => $invoice->invoice_number,
                                'status' => 'skipped',
                                'message' => 'Invoice is fully paid in both systems',
                            ];
                            continue;
                        }
                        
                        // If paid in Xero but not locally, import payments from Xero
                        if ($isPaidInXero && !$isPaidLocally) {
                            Log::info('Invoice is paid in Xero but not locally, importing payments', [
                                'invoice_id' => $invoice->id,
                                'invoice_number' => $invoice->invoice_number,
                                'xero_invoice_id' => $invoice->xero_invoice_id,
                            ]);
                            
                            try {
                                // Fetch payments for this invoice from Xero
                                $this->syncPaymentsForInvoiceFromXero($invoice, $xeroInvoice);
                                
                                // Refresh invoice to get updated payment status
                                $invoice->refresh();
                                
                                $results[] = [
                                    'invoice_id' => $invoice->id,
                                    'invoice_number' => $invoice->invoice_number,
                                    'status' => 'payments_imported',
                                    'message' => 'Payments imported from Xero',
                                ];
                            } catch (\Exception $e) {
                                Log::error('Failed to import payments from Xero for invoice', [
                                    'invoice_id' => $invoice->id,
                                    'invoice_number' => $invoice->invoice_number,
                                    'error' => $e->getMessage(),
                                ]);
                                // Continue with normal sync if payment import fails
                            }
                        }
                        
                        // If paid locally but not in Xero, sync payments to Xero
                        if (!$isPaidInXero && $isPaidLocally) {
                            Log::info('Invoice is paid locally but not in Xero, syncing payments to Xero', [
                                'invoice_id' => $invoice->id,
                                'invoice_number' => $invoice->invoice_number,
                                'xero_invoice_id' => $invoice->xero_invoice_id,
                            ]);
                            
                            try {
                                $paymentResults = $this->syncPaymentsToXero($invoice);
                                $successCount = collect($paymentResults)->where('status', 'success')->count();
                                
                                if ($successCount > 0) {
                                    $results[] = [
                                        'invoice_id' => $invoice->id,
                                        'invoice_number' => $invoice->invoice_number,
                                        'status' => 'payments_synced',
                                        'message' => "Synced {$successCount} payment(s) to Xero",
                                    ];
                                    continue; // Skip invoice update since payments were synced
                                }
                            } catch (\Exception $e) {
                                Log::error('Failed to sync payments to Xero for invoice', [
                                    'invoice_id' => $invoice->id,
                                    'invoice_number' => $invoice->invoice_number,
                                    'error' => $e->getMessage(),
                                ]);
                                // Continue with normal sync if payment sync fails
                            }
                        }
                        
                        if ($this->xeroUpdatedAtChanged($invoice, $xeroInvoice)) {
                            $this->updateInvoiceFromXeroData($invoice, $xeroInvoice);
                            $results[] = [
                                'invoice_id' => $invoice->id,
                                'invoice_number' => $invoice->invoice_number,
                                'status' => 'updated_from_xero',
                                'message' => 'Invoice updated from Xero (Xero was newer)',
                            ];
                            continue;
                        }
                    }
                }
                
                $xeroInvoice = $this->createOrUpdateInvoiceInXero($invoice);
                if (isset($xeroInvoice['InvoiceID'])) {
                    $invoice->update([
                        'xero_invoice_id' => $xeroInvoice['InvoiceID'],
                        ...$this->getXeroTimestamps($xeroInvoice),
                    ]);
                }
                $results[] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'status' => 'success',
                    'xero_invoice_id' => $xeroInvoice['InvoiceID'] ?? null,
                ];
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

        try {
            $lastSync = Invoice::where('company_id', $currentCompany->id)
                ->whereNotNull('xero_updated_at')->max('xero_updated_at');
            $ifModifiedSince = $this->buildIfModifiedSinceHeader($lastSync);

            $page = 1;
            $pageSize = 100;
            $totalProcessed = 0;
            
            do {
                Log::info('Fetching invoice page from Xero', [
                    'company_id' => $currentCompany->id,
                    'page' => $page,
                    'page_size' => $pageSize,
                ]);

                $response = $this->makeXeroRequest(
                    'get',
                    $this->baseUrl . '/api.xro/2.0/Invoices?page=' . $page . '&pageSize=' . $pageSize,
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
                $hasMorePages = ($invoicesOnPage >= $pageSize) || ($currentPage < $pageCount);
                
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

                // Process invoices from this page immediately
                foreach ($xeroInvoices as $xeroInvoice) {
                    try {
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
                                    ->whereNull('xero_invoice_id')
                                    ->first();
                            }
                        }

                        if ($existingInvoice) {
                            if (!$this->xeroUpdatedAtChanged($existingInvoice, $xeroInvoice)) {
                                $results[] = [
                                    'invoice_id' => $existingInvoice->id,
                                    'invoice_number' => $existingInvoice->invoice_number,
                                    'status' => 'skipped',
                                    'message' => 'Invoice unchanged in Xero',
                                ];
                                $totalProcessed++;
                                continue;
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
                        } else {
                            // Create new invoice
                            $invoice = $this->createInvoiceFromXero($xeroInvoice, $currentCompany);
                            $results[] = [
                                'invoice_id' => $invoice->id,
                                'invoice_number' => $invoice->invoice_number,
                                'status' => 'created',
                                'message' => 'Invoice imported from Xero',
                            ];
                        }
                        
                        $totalProcessed++;
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
                    }
                }

                Log::info('Completed processing invoice page', [
                    'company_id' => $currentCompany->id,
                    'page' => $page,
                    'invoices_processed_on_page' => $invoicesOnPage,
                    'total_processed_so_far' => $totalProcessed,
                ]);
                
                $page++;
                
                // Add delay before fetching next page to avoid rate limiting
                if ($hasMorePages) {
                    sleep(1);
                }
            } while ($hasMorePages);

            Log::info('Finished processing all invoices from Xero', [
                'company_id' => $currentCompany->id,
                'total_invoices_processed' => $totalProcessed,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to sync invoices from Xero', [
                'company_id' => $currentCompany->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
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
        
        $invoice->load(['lineItems.product', 'lineItems.account']);
        
        // Ensure customer is loaded
        if (!$invoice->relationLoaded('customer')) {
            $invoice->load('customer');
        }
        
        if ($invoice->lineItems->isEmpty()) {
            throw new \Exception("Invoice '{$invoice->invoice_number}' has no line items. Cannot sync to Xero.");
        }
        
        $currentCompany = $this->getCompany();
        $defaultTaxRate = TaxRate::getDefaultSalesForCompany($currentCompany->id);
        $defaultTaxCode = $defaultTaxRate && $defaultTaxRate->xero_tax_rate_id 
            ? $defaultTaxRate->xero_tax_rate_id 
            : ($defaultTaxRate && $defaultTaxRate->code 
                ? $defaultTaxRate->code 
                : 'TAX002');
        
        $lineItems = [];
        foreach ($invoice->lineItems as $lineItem) {
            // Use the line item's total field which already includes discount
            // This ensures consistency with what's stored in the database
            $lineAmount = (float) $lineItem->total;
            
            // Log discount information for debugging
            $discountAmount = (float) ($lineItem->discount_amount ?? 0);
            $discountPercentage = (float) ($lineItem->discount_percentage ?? 0);
            
            $accountCode = '200';
            if ($lineItem->account_id && $lineItem->account) {
                $accountCode = $lineItem->account->account_code ?? '200';
            } elseif ($lineItem->product_id && $lineItem->product) {
                $accountCode = $lineItem->product->sales_account_code ?? '200';
            } else {
                $defaultAccount = ChartOfAccount::getDefaultSalesForCompany($currentCompany->id);
                if ($defaultAccount) {
                    $accountCode = $defaultAccount->account_code;
                }
            }
            
            // Calculate expected subtotal (Quantity * UnitAmount)
            $expectedSubtotal = $lineItem->quantity * $lineItem->unit_price;
            
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
            
            Log::debug('Processing invoice line item for Xero', [
                'invoice_id' => $invoice->id,
                'line_item_id' => $lineItem->id,
                'product_id' => $lineItem->product_id,
                'account_code' => $accountCode,
                'quantity' => $lineItem->quantity,
                'unit_price' => $lineItem->unit_price,
                'discount_amount' => $discountAmount,
                'discount_percentage' => $discountPercentage,
                'line_item_total' => $lineAmount,
                'expected_subtotal' => $expectedSubtotal,
                'calculated_line_amount' => $calculatedLineAmount,
            ]);
            
            $lineItemData = [
                'Description' => $lineItem->description ?? 'Item',
                'Quantity' => $lineItem->quantity,
                'UnitAmount' => $lineItem->unit_price,
                'LineAmount' => $lineAmount,
                'AccountCode' => $accountCode,
                'TaxType' => $defaultTaxCode,
            ];
            
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

        // If invoice already has a Xero ID, update it; otherwise create new
        if ($invoice->xero_invoice_id) {
            $invoiceData['InvoiceID'] = $invoice->xero_invoice_id;
            
            // For paid invoices, we need to fetch existing line items and include LineItemIDs
            $xeroInvoice = $this->getXeroInvoice($invoice->xero_invoice_id);
            if ($xeroInvoice && $xeroInvoice['Status'] === 'PAID') {
                // Map local line items to Xero line items by matching description and amount
                $xeroLineItems = $xeroInvoice['LineItems'] ?? [];
                
                foreach ($lineItems as $index => &$lineItem) {
                    // Try to find matching Xero line item
                    $matchedXeroItem = null;
                    foreach ($xeroLineItems as $xeroItem) {
                        // Match by description and line amount (within small tolerance for rounding)
                        $descriptionMatch = ($lineItem['Description'] === ($xeroItem['Description'] ?? ''));
                        $amountMatch = abs($lineItem['LineAmount'] - ($xeroItem['LineAmount'] ?? 0)) < 0.01;
                        
                        if ($descriptionMatch && $amountMatch && isset($xeroItem['LineItemID'])) {
                            $matchedXeroItem = $xeroItem;
                            break;
                        }
                    }
                    
                    // If we found a match, include the LineItemID
                    if ($matchedXeroItem && isset($matchedXeroItem['LineItemID'])) {
                        $lineItem['LineItemID'] = $matchedXeroItem['LineItemID'];
                    } elseif (isset($xeroLineItems[$index]['LineItemID'])) {
                        // Fallback: match by index if available
                        $lineItem['LineItemID'] = $xeroLineItems[$index]['LineItemID'];
                    }
                }
                unset($lineItem); // Break reference
                
                $invoiceData['LineItems'] = $lineItems;
            }
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
            $response = $this->makeXeroRequest('get', $this->baseUrl . '/api.xro/2.0/Invoices/' . $xeroInvoiceId);

            if (!$response->successful()) {
                Log::error('Failed to fetch invoice from Xero: ' . $response->body());
                return;
            }

            $xeroInvoice = $response->json()['Invoices'][0];
            
            // Find local invoice by Xero invoice ID or reference
            $invoice = Invoice::where('invoice_number', $xeroInvoice['Reference'])
                ->orWhere('xero_invoice_id', $xeroInvoiceId)
                ->first();

            if ($invoice) {
                $status = $this->mapXeroStatusToLocal($xeroInvoice['Status']);
                $invoice->update([
                    'status' => $status,
                    ...$this->getXeroTimestamps($xeroInvoice),
                ]);
                
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

    private function buildIfModifiedSinceHeader(?string $lastSync): array
    {
        if (!$lastSync) {
            return [];
        }

        return ['If-Modified-Since' => \Carbon\Carbon::parse($lastSync)->format('D, d M Y H:i:s \G\M\T')];
    }

    /**
     * Fetch contacts from Xero with caching and If-Modified-Since.
     * Shared by customer and supplier syncs to avoid duplicate API calls.
     */
    private function fetchXeroContacts(): array
    {
        if ($this->cachedXeroContacts !== null) {
            return $this->cachedXeroContacts;
        }

        $companyId = $this->getCompany()->id;
        $lastCustomerSync = Customer::where('company_id', $companyId)->whereNotNull('xero_updated_at')->max('xero_updated_at');
        $lastSupplierSync = Supplier::where('company_id', $companyId)->whereNotNull('xero_updated_at')->max('xero_updated_at');
        $lastSync = collect([$lastCustomerSync, $lastSupplierSync])->filter()->min();

        $response = $this->makeXeroRequest(
            'get',
            $this->baseUrl . '/api.xro/2.0/Contacts',
            [],
            2,
            $this->buildIfModifiedSinceHeader($lastSync)
        );

        if (!$response->successful()) {
            $errorBody = $response->body();
            $statusCode = $response->status();

            if ($statusCode === 403 && str_contains($errorBody, 'AuthenticationUnsuccessful')) {
                $this->clearInvalidTokens();
                throw new \Exception('Xero authentication failed. Please re-authorize your Xero connection in the settings.');
            }

            throw new \Exception('Failed to fetch contacts from Xero: ' . $errorBody);
        }

        $this->cachedXeroContacts = $response->json()['Contacts'] ?? [];
        return $this->cachedXeroContacts;
    }

    /**
     * Fetch accounts from Xero with caching and If-Modified-Since.
     * Shared by bank account and chart of accounts syncs.
     */
    private function fetchXeroAccounts(bool $forceFullFetch = false): array
    {
        if (!$forceFullFetch && $this->cachedXeroAccounts !== null) {
            return $this->cachedXeroAccounts;
        }

        $headers = [];
        if (!$forceFullFetch) {
            $companyId = $this->getCompany()->id;
            $lastBankSync = BankAccount::where('company_id', $companyId)->whereNotNull('xero_updated_at')->max('xero_updated_at');
            $lastChartSync = ChartOfAccount::where('company_id', $companyId)->whereNotNull('xero_updated_at')->max('xero_updated_at');
            $lastSync = collect([$lastBankSync, $lastChartSync])->filter()->min();
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

        $this->cachedXeroAccounts = $response->json()['Accounts'] ?? [];
        return $this->cachedXeroAccounts;
    }

    /**
     * Update customer from Xero data
     */
    private function updateCustomerFromXero(Customer $customer, array $xeroCustomer): void
    {
        $updateData = [
            'name' => $xeroCustomer['Name'] ?? $customer->name,
            'email' => $xeroCustomer['EmailAddress'] ?? $customer->email,
            'xero_contact_id' => $xeroCustomer['ContactID'] ?? $customer->xero_contact_id,
            ...$this->getXeroTimestamps($xeroCustomer),
        ];

        // Update account_code from Xero AccountNumber if available
        if (isset($xeroCustomer['AccountNumber']) && !empty($xeroCustomer['AccountNumber'])) {
            $updateData['account_code'] = $xeroCustomer['AccountNumber'];
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

        $customer->update($updateData);
    }

    /**
     * Create customer from Xero contact data
     */
    private function createCustomerFromXero(array $xeroContact, Company $company): Customer
    {
        $customerData = [
            'company_id' => $company->id,
            'name' => $xeroContact['Name'],
            'email' => $xeroContact['EmailAddress'] ?? null,
            'xero_contact_id' => $xeroContact['ContactID'],
            ...$this->getXeroTimestamps($xeroContact),
        ];

        // Add account_code from Xero AccountNumber if available
        if (isset($xeroContact['AccountNumber']) && !empty($xeroContact['AccountNumber'])) {
            $customerData['account_code'] = $xeroContact['AccountNumber'];
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

        return Customer::create($customerData);
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

        $xeroContactsMap = [];
        try {
            $xeroContacts = $this->fetchXeroContacts();
            foreach ($xeroContacts as $xeroContact) {
                $xeroContactsMap[$xeroContact['ContactID']] = $xeroContact;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch Xero contacts for supplier comparison', ['error' => $e->getMessage()]);
        }

        Supplier::where('company_id', $currentCompany->id)->chunk(100, function ($suppliers) use (&$results, $xeroContactsMap) {
            foreach ($suppliers as $supplier) {
                try {
                    if ($supplier->xero_contact_id && isset($xeroContactsMap[$supplier->xero_contact_id])) {
                        $xeroSupplier = $xeroContactsMap[$supplier->xero_contact_id];
                        if ($this->xeroUpdatedAtChanged($supplier, $xeroSupplier)) {
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
        });

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

        try {
            $xeroContacts = $this->fetchXeroContacts();
            
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
                    $existingSupplier = Supplier::where('company_id', $currentCompany->id)
                        ->where('xero_contact_id', $xeroContact['ContactID'])
                        ->first();

                    // If not found by Xero ID, check by name (case-insensitive)
                    if (!$existingSupplier) {
                        $existingSupplier = Supplier::where('company_id', $currentCompany->id)
                            ->whereRaw('LOWER(name) = ?', [strtolower($xeroContact['Name'])])
                            ->whereNull('xero_contact_id') // Only match suppliers that don't have a Xero ID yet
                            ->first();
                    }

                    if ($existingSupplier) {
                        if (!$this->xeroUpdatedAtChanged($existingSupplier, $xeroContact)) {
                            $results[] = [
                                'supplier_id' => $existingSupplier->id,
                                'supplier_name' => $xeroContact['Name'],
                                'status' => 'skipped',
                                'message' => 'Supplier unchanged in Xero',
                            ];
                            continue;
                        }
                        $this->updateSupplierFromXero($existingSupplier, $xeroContact);
                        $results[] = [
                            'supplier_id' => $existingSupplier->id,
                            'supplier_name' => $xeroContact['Name'],
                            'status' => 'updated',
                            'message' => 'Supplier updated from Xero and linked to existing supplier',
                        ];
                    } else {
                        // Create new supplier
                        $supplier = $this->createSupplierFromXero($xeroContact, $currentCompany);
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

        $supplier->update($updateData);
    }

    /**
     * Create supplier from Xero contact data
     */
    private function createSupplierFromXero(array $xeroContact, Company $company): Supplier
    {
        $supplierData = [
            'company_id' => $company->id,
            'name' => $xeroContact['Name'],
            'email' => $xeroContact['EmailAddress'] ?? null,
            'xero_contact_id' => $xeroContact['ContactID'],
            ...$this->getXeroTimestamps($xeroContact),
        ];

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

        return Supplier::create($supplierData);
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
        $quotes = Quote::where('company_id', $currentCompany->id)
            ->with(['customer', 'lineItems'])
            ->get();
        $results = [];
        
        Log::info('Starting quote sync to Xero', [
            'company_id' => $currentCompany->id,
            'quote_count' => $quotes->count(),
        ]);

        // Pre-fetch all Xero quotes once to avoid N+1 individual GET requests
        $xeroQuotesMap = [];
        try {
            $response = $this->makeXeroRequest('get', $this->baseUrl . '/api.xro/2.0/Quotes');
            if ($response->successful()) {
                foreach ($response->json()['Quotes'] ?? [] as $xq) {
                    $xeroQuotesMap[$xq['QuoteID']] = $xq;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to pre-fetch Xero quotes for comparison', ['error' => $e->getMessage()]);
        }

        foreach ($quotes as $index => $quote) {
            try {
                if ($index > 0) {
                    sleep(1);
                }
                
                if ($quote->xero_quote_id) {
                    $xeroQuote = $xeroQuotesMap[$quote->xero_quote_id] ?? null;
                    if ($xeroQuote && $this->xeroUpdatedAtChanged($quote, $xeroQuote)) {
                        $this->updateQuoteFromXeroData($quote, $xeroQuote);
                        $results[] = [
                            'quote_id' => $quote->id,
                            'quote_number' => $quote->quote_number,
                            'status' => 'updated_from_xero',
                            'message' => 'Quote updated from Xero (Xero was newer)',
                        ];
                        continue;
                    }
                }
                
                $xeroQuote = $this->createOrUpdateQuoteInXero($quote);
                if (isset($xeroQuote['QuoteID'])) {
                    $quote->update([
                        'xero_quote_id' => $xeroQuote['QuoteID'],
                        ...$this->getXeroTimestamps($xeroQuote),
                    ]);
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

        try {
            $lastSync = Quote::where('company_id', $currentCompany->id)
                ->whereNotNull('xero_updated_at')->max('xero_updated_at');
            $ifModifiedSince = $this->buildIfModifiedSinceHeader($lastSync);

            $page = 1;
            $pageSize = 100;
            $totalProcessed = 0;
            
            do {
                $response = $this->makeXeroRequest(
                    'get',
                    $this->baseUrl . '/api.xro/2.0/Quotes?page=' . $page . '&pageSize=' . $pageSize,
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
                $hasMorePages = ($quotesOnPage >= $pageSize) || ($currentPage < $pageCount);
                
                Log::info('Fetched quote page from Xero, processing now', [
                    'company_id' => $currentCompany->id,
                    'page' => $page,
                    'quotes_on_page' => $quotesOnPage,
                    'has_more_pages' => $hasMorePages,
                ]);

                foreach ($xeroQuotes as $xeroQuote) {
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
                                ->whereNull('xero_quote_id')
                                ->first();
                        }

                        if ($existingQuote) {
                            if (!$this->xeroUpdatedAtChanged($existingQuote, $xeroQuote)) {
                                $results[] = [
                                    'quote_id' => $existingQuote->id,
                                    'quote_number' => $xeroQuote['QuoteNumber'],
                                    'status' => 'skipped',
                                    'message' => 'Quote unchanged in Xero',
                                ];
                                $totalProcessed++;
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
                    }
                }

                $page++;
                
                if ($hasMorePages) {
                    sleep(1);
                }
            } while ($hasMorePages);

            Log::info('Finished processing all quotes from Xero', [
                'company_id' => $currentCompany->id,
                'total_quotes_processed' => $totalProcessed,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to sync quotes from Xero', [
                'company_id' => $currentCompany->id,
                'error' => $e->getMessage(),
            ]);
            
            return ['skipped' => true, 'message' => 'Failed to sync quotes from Xero: ' . $e->getMessage()];
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
        
        $quote->load(['lineItems.product', 'lineItems.account']);
        
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
        
        $currentCompany = $this->getCompany();
        $defaultTaxRate = TaxRate::getDefaultSalesForCompany($currentCompany->id);
        $defaultTaxCode = $defaultTaxRate && $defaultTaxRate->xero_tax_rate_id 
            ? $defaultTaxRate->xero_tax_rate_id 
            : ($defaultTaxRate && $defaultTaxRate->code 
                ? $defaultTaxRate->code 
                : 'OUTPUT3');
        
        $lineItems = [];
        foreach ($quote->lineItems as $lineItem) {
            $accountCode = '200';
            if ($lineItem->account_id && $lineItem->account) {
                $accountCode = $lineItem->account->account_code ?? '200';
            } elseif ($lineItem->product_id && $lineItem->product) {
                $accountCode = $lineItem->product->sales_account_code ?? '200';
            } else {
                $defaultAccount = ChartOfAccount::getDefaultSalesForCompany($currentCompany->id);
                if ($defaultAccount) {
                    $accountCode = $defaultAccount->account_code;
                }
            }
            
            $lineItems[] = [
                'Description' => $lineItem->description,
                'Quantity' => $lineItem->quantity,
                'UnitAmount' => $lineItem->unit_price,
                'LineAmount' => $lineItem->total,
                'AccountCode' => $accountCode,
                'TaxType' => $defaultTaxCode,
            ];
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
        }
        
        $response = $this->makeXeroRequest('post', $this->baseUrl . '/api.xro/2.0/Quotes', [
            'Quotes' => [$quoteData]
        ]);

        if (!$response->successful()) {
            $errorBody = $response->body();
            $statusCode = $response->status();
            
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
            'expired' => 'EXPIRED',
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

        $quote->update($updateData);
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

        // Create line items
        if (isset($xeroQuote['LineItems']) && is_array($xeroQuote['LineItems'])) {
            foreach ($xeroQuote['LineItems'] as $index => $xeroLineItem) {
                \App\Models\QuoteLineItem::create([
                    'quote_id' => $quote->id,
                    'description' => $xeroLineItem['Description'] ?? '',
                    'quantity' => $xeroLineItem['Quantity'] ?? 1,
                    'unit_price' => $xeroLineItem['UnitAmount'] ?? 0,
                    'total' => $xeroLineItem['LineAmount'] ?? 0,
                    'account_id' => $this->resolveAccountId($xeroLineItem['AccountCode'] ?? null, $currentCompany->id),
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
                    'account_id' => $this->resolveAccountId($xeroLineItem['AccountCode'] ?? null, $currentCompany->id),
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
     * Get Xero invoice by ID
     */
    private function getXeroInvoice(string $invoiceId): ?array
    {
        try {
            $response = $this->makeXeroRequest('get', $this->baseUrl . '/api.xro/2.0/Invoices/' . $invoiceId);

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

        $invoice->update($updateData);
        
        // Update line items if provided (optional - only if line items exist in Xero data)
        if (isset($xeroInvoice['LineItems']) && is_array($xeroInvoice['LineItems'])) {
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
                    'account_id' => $this->resolveAccountId($xeroLineItem['AccountCode'] ?? null, $currentCompany->id),
                    'sort_order' => $index,
                ]);
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

        $product->update($updateData);
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
            'sales_account_code' => $xeroItem['SalesDetails']['AccountCode'] ?? 200,
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

        return Product::create($productData);
    }

    /**
     * Sync payments to Xero for a specific invoice
     */
    public function syncPaymentsToXero(Invoice $invoice): array
    {
        if (!$this->settings->sync_invoices_to_xero) {
            return ['skipped' => true, 'message' => 'Invoice sync to Xero is disabled'];
        }

        if (!$invoice->xero_invoice_id) {
            return ['error' => 'Invoice has not been synced to Xero yet'];
        }

        $results = [];

        foreach ($invoice->payments as $payment) {
            try {
                $result = $this->createPaymentInXero($invoice, $payment);
                
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
    private function createPaymentInXero(Invoice $invoice, Payment $payment): array
    {
        // Check if invoice is already fully paid in Xero
        $xeroInvoice = $this->getXeroInvoice($invoice->xero_invoice_id);
        if ($xeroInvoice && isset($xeroInvoice['AmountDue']) && $xeroInvoice['AmountDue'] <= 0) {
            Log::info('Skipping payment creation - invoice already fully paid in Xero', [
                'invoice_id' => $invoice->id,
                'xero_invoice_id' => $invoice->xero_invoice_id,
                'amount_due' => $xeroInvoice['AmountDue'],
                'payment_amount' => $payment->amount,
            ]);
            
            return [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'status' => 'skipped',
                'message' => 'Invoice is already fully paid in Xero (Amount Due: ' . ($xeroInvoice['AmountDue'] ?? 'N/A') . ')',
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
            'amount' => $payment->amount,
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
            'Amount' => $payment->amount,
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
            if (isset($errorData['Elements'][0]['ValidationErrors'][0]['Message'])) {
                $specificError = $errorData['Elements'][0]['ValidationErrors'][0]['Message'];
                throw new \Exception("Failed to create payment in Xero: {$specificError}. The default bank account may not be valid for payments in Xero.");
            }
            
            throw new \Exception('Failed to create payment in Xero: ' . $errorBody);
        }

        $result = $response->json();
        return $result['Payments'][0];
    }


    /**
     * Sync payments for a specific invoice FROM Xero
     */
    private function syncPaymentsForInvoiceFromXero(Invoice $invoice, array $xeroInvoice = null): void
    {
        // If xeroInvoice not provided, fetch it
        if (!$xeroInvoice && $invoice->xero_invoice_id) {
            $xeroInvoice = $this->getXeroInvoice($invoice->xero_invoice_id);
        }
        
        if (!$xeroInvoice) {
            Log::warning('Cannot sync payments - Xero invoice not found', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'xero_invoice_id' => $invoice->xero_invoice_id,
            ]);
            return;
        }

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

                // Check if payment already exists (match by invoice, amount, and date)
                $existingPayment = Payment::where('invoice_id', $invoice->id)
                    ->where('amount', $amount)
                    ->whereDate('payment_date', $paymentDate->format('Y-m-d'))
                    ->first();

                if ($existingPayment) {
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
        $invoices = Invoice::where('company_id', $currentCompany->id)
            ->whereNotNull('xero_invoice_id')
            ->whereHas('payments')
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

        try {
            // Calculate date one hour ago for filtering payments
            $oneHourAgo = now()->subHour();
            $ifModifiedSince = $oneHourAgo->format('D, d M Y H:i:s \G\M\T');
            
            Log::info('Starting payment sync from Xero with date filter', [
                'company_id' => $currentCompany->id,
                'if_modified_since' => $ifModifiedSince,
                'one_hour_ago' => $oneHourAgo->toIso8601String(),
            ]);

            // Fetch payments from Xero created in the last hour
            // Note: Xero Payments API doesn't directly support IfModifiedSince, so we'll fetch and filter
            $response = $this->makeXeroRequest(
                'get',
                $this->baseUrl . '/api.xro/2.0/Payments',
                [],
                2,
                ['If-Modified-Since' => $ifModifiedSince]
            );

            $statusCode = $response->status();
            
            // Handle 304 Not Modified - no payments have been modified since the specified date
            if ($statusCode === 304) {
                Log::info('No payments modified since last hour', [
                    'company_id' => $currentCompany->id,
                    'if_modified_since' => $ifModifiedSince,
                ]);
                return [
                    'skipped' => true,
                    'created' => 0,
                    'skipped_count' => 0,
                    'errors' => 0,
                    'message' => 'No payments modified since last hour',
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
                    // Check if payment was updated/created in the last hour
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

                    // Only process payments created/updated in the last hour
                    if ($paymentCreatedDate->lt($oneHourAgo)) {
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

                    // Get invoice ID from payment
                    $xeroInvoiceId = $xeroPayment['Invoice']['InvoiceID'] ?? null;
                    if (!$xeroInvoiceId) {
                        Log::warning('Payment from Xero has no invoice ID', [
                            'payment_id' => $xeroPayment['PaymentID'] ?? 'Unknown',
                        ]);
                        continue;
                    }

                    // Find local invoice by Xero invoice ID
                    $invoice = Invoice::where('company_id', $currentCompany->id)
                        ->where('xero_invoice_id', $xeroInvoiceId)
                        ->first();

                    if (!$invoice) {
                        Log::info('Invoice not found locally for Xero payment', [
                            'company_id' => $currentCompany->id,
                            'xero_invoice_id' => $xeroInvoiceId,
                            'payment_id' => $xeroPayment['PaymentID'] ?? 'Unknown',
                        ]);
                        $skippedCount++;
                        continue;
                    }

                    // Check if payment already exists (match by invoice, amount, and date)
                    $amount = $xeroPayment['Amount'] ?? 0;
                    $existingPayment = Payment::where('invoice_id', $invoice->id)
                        ->where('amount', $amount)
                        ->whereDate('payment_date', $paymentDate->format('Y-m-d'))
                        ->first();

                    if ($existingPayment) {
                        Log::info('Payment already exists locally', [
                            'payment_id' => $existingPayment->id,
                            'invoice_id' => $invoice->id,
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
                        'invoice_id' => $invoice->id,
                        'company_id' => $currentCompany->id,
                        'amount' => $amount,
                        'payment_method' => $paymentMethod,
                        'payment_date' => $paymentDate->format('Y-m-d'),
                        'notes' => $notes,
                    ]);

                    $createdCount++;
                    
                    Log::info('Created payment from Xero', [
                        'payment_id' => $payment->id,
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'amount' => $amount,
                        'date' => $paymentDate->format('Y-m-d'),
                    ]);

                    // Update invoice status if fully paid
                    $invoice->refresh();
                    if ($invoice->isFullyPaid()) {
                        $invoice->update(['status' => 'paid']);
                    }

                    $results[] = [
                        'payment_id' => $payment->id,
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
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
                        'xero_invoice_id' => $xeroPayment['Invoice']['InvoiceID'] ?? 'Unknown',
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

        try {
            $lastSync = TaxRate::where('company_id', $currentCompany->id)
                ->whereNotNull('xero_updated_at')->max('xero_updated_at');

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
                        $existingTaxRate->update([
                            'xero_tax_rate_id' => $xeroTaxRate['TaxType'],
                            'name' => $xeroTaxRate['Name'],
                            'code' => $xeroTaxRate['TaxType'] ?? null,
                            'rate' => $rate,
                            'description' => $xeroTaxRate['ReportTaxType'] ?? null,
                            'is_active' => true,
                            ...$this->getXeroTimestamps($xeroTaxRate),
                        ]);
                        
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

        try {
            $hasExistingBankAccounts = BankAccount::where('company_id', $currentCompany->id)
                ->whereNotNull('xero_account_id')
                ->exists();

            $allAccounts = $this->fetchXeroAccounts(!$hasExistingBankAccounts);
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
                        $existingBankAccount->update($accountData);
                        
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

        try {
            $hasExistingCoa = ChartOfAccount::where('company_id', $currentCompany->id)
                ->whereNotNull('xero_account_id')
                ->exists();

            $allAccounts = $this->fetchXeroAccounts(!$hasExistingCoa);
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
                            'EQUITY' => 'Equity',
                            'EXPENSE' => 'Expense',
                            'LIABILITY' => 'Liability',
                            'REVENUE' => 'Revenue',
                        ];
                        $accountType = $typeMap[$xeroAccount['Type']] ?? 'Expense';
                    }

                    // Check if account already exists in app by Xero ID
                    $existingAccount = ChartOfAccount::where('company_id', $currentCompany->id)
                        ->where('xero_account_id', $xeroAccount['AccountID'])
                        ->first();

                    // If not found by Xero ID, check by account code
                    if (!$existingAccount && !empty($accountCode)) {
                        $existingAccount = ChartOfAccount::where('company_id', $currentCompany->id)
                            ->where('account_code', $accountCode)
                            ->whereNull('xero_account_id')
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
                        $existingAccount->update($accountData);
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
                    $account->update(['parent_account_id' => $parentAccount->id]);
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

        $oneHourAgo = now()->subHour();
        $creditNotes = CreditNote::where('company_id', $currentCompany->id)
            ->where('updated_at', '>=', $oneHourAgo)
            ->with(['customer', 'lineItems.product', 'invoice'])
            ->get();

        Log::info('Starting credit note sync to Xero', [
            'company_id' => $currentCompany->id,
            'credit_note_count' => $creditNotes->count(),
            'modified_since' => $oneHourAgo->toIso8601String(),
        ]);

        $xeroCreditNotesMap = [];
        try {
            $response = $this->makeXeroRequest(
                'get',
                $this->baseUrl . '/api.xro/2.0/CreditNotes',
                [],
                2,
                $this->buildIfModifiedSinceHeader($oneHourAgo->toIso8601String())
            );
            if ($response->successful()) {
                foreach ($response->json()['CreditNotes'] ?? [] as $xcn) {
                    $xeroCreditNotesMap[$xcn['CreditNoteID']] = $xcn;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to pre-fetch Xero credit notes for comparison', ['error' => $e->getMessage()]);
        }

        foreach ($creditNotes as $index => $creditNote) {
            try {
                if ($index > 0) {
                    sleep(1);
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
                    $xeroCN = $xeroCreditNotesMap[$creditNote->xero_credit_note_id] ?? null;
                    if ($xeroCN) {
                        if ($this->xeroUpdatedAtChanged($creditNote, $xeroCN)) {
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

                if (isset($xeroData['CreditNoteID'])) {
                    $updateData = $this->getXeroTimestamps($xeroData);
                    if (!$creditNote->xero_credit_note_id) {
                        $updateData['xero_credit_note_id'] = $xeroData['CreditNoteID'];
                    }
                    $creditNote->update($updateData);
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

        try {
            $lastSync = CreditNote::where('company_id', $currentCompany->id)
                ->whereNotNull('xero_updated_at')->max('xero_updated_at');
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
                    $this->baseUrl . '/api.xro/2.0/CreditNotes?page=' . $page . '&pageSize=' . $pageSize,
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
                                ->whereNull('xero_credit_note_id')
                                ->first();
                        }

                        if ($existing) {
                            if (!$this->xeroUpdatedAtChanged($existing, $xeroNote)) {
                                $results[] = [
                                    'credit_note_id' => $existing->id,
                                    'credit_note_number' => $existing->credit_note_number,
                                    'status' => 'skipped',
                                    'message' => 'Credit note unchanged in Xero',
                                ];
                                $totalProcessed++;
                                continue;
                            }
                            $this->updateCreditNoteFromXeroData($existing, $xeroNote);
                            if (!$existing->xero_credit_note_id) {
                                $existing->update(['xero_credit_note_id' => $xeroNote['CreditNoteID']]);
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

        $lineItems = [];
        foreach ($creditNote->lineItems as $lineItem) {
            $accountCode = '200';
            if ($lineItem->account_id && $lineItem->account) {
                $accountCode = $lineItem->account->account_code ?? '200';
            } elseif ($lineItem->product_id && $lineItem->product) {
                $accountCode = $lineItem->product->sales_account_code ?? '200';
            } else {
                $defaultAccount = ChartOfAccount::getDefaultSalesForCompany($currentCompany->id);
                if ($defaultAccount) {
                    $accountCode = $defaultAccount->account_code;
                }
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
                'UnitAmount' => $lineItem->unit_price,
                'LineAmount' => $lineAmount,
                'AccountCode' => ($lineItem->account_id && $lineItem->account ? $lineItem->account->account_code : null) ?? $lineItem->account_code ?? $accountCode,
                'TaxType' => $taxTypeCode,
            ];

            if ($discountPercentage > 0) {
                $lineItemData['DiscountRate'] = round($discountPercentage, 2);
            } elseif ($discountAmount > 0) {
                $lineItemData['DiscountAmount'] = round($discountAmount, 2);
            }

            $lineItems[] = $lineItemData;
        }

        $data = [
            'Type' => 'ACCRECCREDIT',
            'Contact' => ['ContactID' => $creditNote->customer->xero_contact_id],
            'Date' => $creditNote->credit_note_date->format('Y-m-d'),
            'LineItems' => $lineItems,
            'Status' => $this->mapCreditNoteStatus($creditNote->status),
            'Reference' => $creditNote->reference ?? $creditNote->credit_note_number,
            'CreditNoteNumber' => $creditNote->credit_note_number,
        ];

        if ($creditNote->xero_credit_note_id) {
            $data['CreditNoteID'] = $creditNote->xero_credit_note_id;
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
            'credit_note_number' => $xeroNote['CreditNoteNumber'] ?? CreditNote::generateCreditNoteNumber(),
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

        $this->createCreditNoteLineItemsFromXero($creditNote, $xeroNote['LineItems'] ?? [], $company);

        return $creditNote;
    }

    private function updateCreditNoteFromXeroData(CreditNote $creditNote, array $xeroNote): void
    {
        $invoiceId = $this->resolveCreditNoteInvoiceId($xeroNote, Company::find($creditNote->company_id)) ?? $creditNote->invoice_id;

        $creditNote->update([
            'status' => $this->mapXeroCreditNoteStatusToLocal($xeroNote['Status'] ?? 'DRAFT'),
            'total' => (float) ($xeroNote['Total'] ?? $creditNote->total),
            'subtotal' => (float) ($xeroNote['SubTotal'] ?? $creditNote->subtotal),
            'tax_amount' => (float) ($xeroNote['TotalTax'] ?? $creditNote->tax_amount),
            'remaining_credit' => (float) ($xeroNote['RemainingCredit'] ?? $creditNote->remaining_credit),
            'invoice_id' => $invoiceId,
            'reference' => $xeroNote['Reference'] ?? $creditNote->reference,
            'credit_note_date' => isset($xeroNote['Date']) ? $this->parseXeroDate($xeroNote['Date']) : $creditNote->credit_note_date,
            ...$this->getXeroTimestamps($xeroNote),
        ]);

        if (!empty($xeroNote['LineItems'])) {
            $creditNote->lineItems()->delete();
            $this->createCreditNoteLineItemsFromXero($creditNote, $xeroNote['LineItems'], Company::find($creditNote->company_id));
        }
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

    private function resolveCreditNoteInvoiceId(array $xeroNote, ?Company $company): ?int
    {
        if (!$company || empty($xeroNote['Allocations'])) {
            return null;
        }

        foreach ($xeroNote['Allocations'] as $allocation) {
            if (!empty($allocation['Invoice']['InvoiceID'])) {
                $localInvoice = Invoice::where('company_id', $company->id)
                    ->where('xero_invoice_id', $allocation['Invoice']['InvoiceID'])
                    ->first();
                if ($localInvoice) {
                    return $localInvoice->id;
                }
            }
        }

        return null;
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
            ->where('updated_at', '>=', now()->subHour())
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

                $xeroData = $this->createOrUpdatePurchaseOrderInXero($po);

                if (!$po->xero_purchase_order_id && isset($xeroData['PurchaseOrderID'])) {
                    $po->update([
                        'xero_purchase_order_id' => $xeroData['PurchaseOrderID'],
                        ...$this->getXeroTimestamps($xeroData),
                    ]);
                } else {
                    $po->update($this->getXeroTimestamps($xeroData));
                }

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

        try {
            $lastSync = PurchaseOrder::where('company_id', $currentCompany->id)
                ->whereNotNull('xero_updated_at')->max('xero_updated_at');
            $ifModifiedSince = $this->buildIfModifiedSinceHeader($lastSync);

            $page = 1;
            $pageSize = 100;

            do {
                $response = $this->makeXeroRequest(
                    'get',
                    $this->baseUrl . '/api.xro/2.0/PurchaseOrders?page=' . $page . '&pageSize=' . $pageSize,
                    [],
                    2,
                    $ifModifiedSince
                );

                if (!$response->successful()) {
                    throw new \Exception('Failed to fetch purchase orders from Xero: ' . $response->body());
                }

                $xeroPOs = $response->json()['PurchaseOrders'] ?? [];
                $hasMorePages = count($xeroPOs) >= $pageSize;

                foreach ($xeroPOs as $xeroPO) {
                    try {
                        $existing = PurchaseOrder::where('company_id', $currentCompany->id)
                            ->where('xero_purchase_order_id', $xeroPO['PurchaseOrderID'])
                            ->first();

                        if (!$existing && !empty($xeroPO['PurchaseOrderNumber'])) {
                            $existing = PurchaseOrder::where('company_id', $currentCompany->id)
                                ->where('po_number', $xeroPO['PurchaseOrderNumber'])
                                ->whereNull('xero_purchase_order_id')
                                ->first();
                        }

                        if ($existing) {
                            if (!$this->xeroUpdatedAtChanged($existing, $xeroPO)) {
                                $results[] = [
                                    'po_number' => $existing->po_number,
                                    'status' => 'skipped',
                                ];
                                continue;
                            }
                            $this->updatePurchaseOrderFromXeroData($existing, $xeroPO);
                            if (!$existing->xero_purchase_order_id) {
                                $existing->update(['xero_purchase_order_id' => $xeroPO['PurchaseOrderID']]);
                            }
                            $results[] = [
                                'po_number' => $existing->po_number,
                                'status' => 'updated',
                            ];
                        } else {
                            $po = $this->createPurchaseOrderFromXero($xeroPO, $currentCompany);
                            $results[] = [
                                'po_number' => $po->po_number,
                                'status' => 'created',
                            ];
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
                    }
                }

                $page++;
                if ($hasMorePages) {
                    sleep(1);
                }
            } while ($hasMorePages);
        } catch (\Exception $e) {
            Log::error('Failed to sync purchase orders from Xero', [
                'error' => $e->getMessage(),
            ]);
            return ['skipped' => true, 'message' => 'Failed: ' . $e->getMessage()];
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

            $lineItems[] = [
                'Description' => $item->description ?? ($item->product ? $item->product->name : 'Item'),
                'Quantity' => $item->quantity,
                'UnitAmount' => $item->unit_cost,
                'LineAmount' => (float) $item->total,
                'AccountCode' => $accountCode,
                'TaxType' => $defaultTaxCode,
            ];
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
        }

        $response = $this->makeXeroRequest('post', $this->baseUrl . '/api.xro/2.0/PurchaseOrders', [
            'PurchaseOrders' => [$data],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create/update purchase order in Xero: ' . $response->body());
        }

        return $response->json()['PurchaseOrders'][0] ?? [];
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
                    $supplier = Supplier::create([
                        'company_id' => $company->id,
                        'xero_contact_id' => $contactData['ContactID'],
                        'name' => $contactData['Name'],
                        'email' => $contactData['EmailAddress'] ?? null,
                        'phone' => $contactData['Phones'][0]['PhoneNumber'] ?? null,
                        'is_active' => true,
                        ...$this->getXeroTimestamps($contactData),
                    ]);
                }
            }
        }

        if (!$supplier) {
            throw new \Exception('Could not resolve supplier for purchase order');
        }

        $po = PurchaseOrder::create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'xero_purchase_order_id' => $xeroPO['PurchaseOrderID'],
            'po_number' => $xeroPO['PurchaseOrderNumber'] ?? PurchaseOrder::generatePONumber($company->id),
            'order_date' => isset($xeroPO['Date']) ? $this->parseXeroDate($xeroPO['Date']) : now(),
            'expected_delivery_date' => isset($xeroPO['DeliveryDate']) ? $this->parseXeroDate($xeroPO['DeliveryDate']) : null,
            'status' => $this->mapXeroPOStatusToLocal($xeroPO['Status'] ?? 'DRAFT'),
            'subtotal' => (float) ($xeroPO['SubTotal'] ?? 0),
            'tax_amount' => (float) ($xeroPO['TotalTax'] ?? 0),
            'total' => (float) ($xeroPO['Total'] ?? 0),
            'notes' => $xeroPO['Reference'] ?? null,
            ...$this->getXeroTimestamps($xeroPO),
        ]);

        if (!empty($xeroPO['LineItems'])) {
            foreach ($xeroPO['LineItems'] as $xeroLineItem) {
                $product = null;
                if (!empty($xeroLineItem['ItemCode'])) {
                    $product = Product::where('company_id', $company->id)
                        ->where('sku', $xeroLineItem['ItemCode'])
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

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $product?->id,
                    'tax_rate_id' => $taxRateId,
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

        return $po;
    }

    private function updatePurchaseOrderFromXeroData(PurchaseOrder $po, array $xeroPO): void
    {
        $po->update([
            'status' => $this->mapXeroPOStatusToLocal($xeroPO['Status'] ?? $po->status),
            'subtotal' => (float) ($xeroPO['SubTotal'] ?? $po->subtotal),
            'tax_amount' => (float) ($xeroPO['TotalTax'] ?? $po->tax_amount),
            'total' => (float) ($xeroPO['Total'] ?? $po->total),
            'expected_delivery_date' => isset($xeroPO['DeliveryDate']) ? $this->parseXeroDate($xeroPO['DeliveryDate']) : $po->expected_delivery_date,
            ...$this->getXeroTimestamps($xeroPO),
        ]);

        if (!empty($xeroPO['LineItems'])) {
            $po->items()->delete();
            foreach ($xeroPO['LineItems'] as $xeroLineItem) {
                $product = null;
                if (!empty($xeroLineItem['ItemCode'])) {
                    $product = Product::where('company_id', $po->company_id)
                        ->where('sku', $xeroLineItem['ItemCode'])
                        ->first();
                }

                $taxRateId = null;
                if (!empty($xeroLineItem['TaxType'])) {
                    $taxRate = TaxRate::where('company_id', $po->company_id)
                        ->where(function ($q) use ($xeroLineItem) {
                            $q->where('code', $xeroLineItem['TaxType'])
                              ->orWhere('xero_tax_rate_id', $xeroLineItem['TaxType']);
                        })->first();
                    $taxRateId = $taxRate?->id;
                }

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $product?->id,
                    'tax_rate_id' => $taxRateId,
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