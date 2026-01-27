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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XeroService
{
    private $settings;
    private $baseUrl = 'https://api.xero.com';

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
     * Make HTTP request with rate limiting handling
     * 
     * @param string $method HTTP method (get, post, put, patch, delete)
     * @param string $url Full URL to request
     * @param array $data Optional data for POST/PUT requests
     * @param int $maxRetries Maximum number of retries for rate limit errors
     * @return \Illuminate\Http\Client\Response
     * @throws \Exception
     */
    private function makeXeroRequest(string $method, string $url, array $data = [], int $maxRetries = 2): \Illuminate\Http\Client\Response
    {
        $attempt = 0;
        
        while ($attempt <= $maxRetries) {
            try {
                $request = Http::withHeaders($this->getHeaders());
                
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

        $currentCompany = $company ?? Company::getDefault();
        $customers = Customer::where('company_id', $currentCompany->id)->get();
        $results = [];

        // Fetch all Xero contacts once to avoid individual GET requests
        $xeroContactsMap = [];
        try {
            $response = $this->makeXeroRequest('get', $this->baseUrl . '/api.xro/2.0/Contacts');
            if ($response->successful()) {
                $xeroContacts = $response->json()['Contacts'] ?? [];
                foreach ($xeroContacts as $xeroContact) {
                    $xeroContactsMap[$xeroContact['ContactID']] = $xeroContact;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch Xero contacts for comparison, continuing with individual checks', [
                'error' => $e->getMessage(),
            ]);
        }

        // Prepare customers for batch processing
        $customersToSync = [];
        $batchSize = 100; // Xero allows up to 100 contacts per batch request

        foreach ($customers as $index => $customer) {
            try {
                // Check if customer exists in Xero using the fetched map
                $xeroCustomer = null;
                if ($customer->xero_contact_id && isset($xeroContactsMap[$customer->xero_contact_id])) {
                    $xeroCustomer = $xeroContactsMap[$customer->xero_contact_id];
                    
                    $appUpdated = $customer->updated_at;
                    $xeroUpdated = $this->parseXeroDate($xeroCustomer['UpdatedDateUTC']);
                    
                    // If Xero is newer, sync from Xero instead
                    if ($xeroUpdated > $appUpdated) {
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
                
                // Prepare contact data for batch
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
                
                // Add ContactID if customer already exists in Xero
                if ($customer->xero_contact_id) {
                    $contactData['ContactID'] = $customer->xero_contact_id;
                }
                
                $customersToSync[] = [
                    'customer' => $customer,
                    'contactData' => $contactData,
                ];
                
                // Process batch when we reach batch size or at the end
                if (count($customersToSync) >= $batchSize || $index === $customers->count() - 1) {
                    $batchResults = $this->batchCreateOrUpdateCustomersInXero($customersToSync);
                    $results = array_merge($results, $batchResults);
                    $customersToSync = [];
                    
                    // Add delay between batches to avoid rate limiting (except for last batch)
                    if ($index < $customers->count() - 1) {
                        sleep(1);
                    }
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
                    // Store the Xero contact ID in the database
                    $customer->update(['xero_contact_id' => $xeroContact['ContactID']]);
                    
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
                        $customer->update(['xero_contact_id' => $xeroCustomer['ContactID']]);
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

        $currentCompany = $company ?? Company::getDefault();
        $results = [];

        try {
            // Get all contacts from Xero
            $response = $this->makeXeroRequest('get', $this->baseUrl . '/api.xro/2.0/Contacts');

            if (!$response->successful()) {
                $errorBody = $response->body();
                $statusCode = $response->status();
                
                // Handle authentication errors specifically
                if ($statusCode === 403 && str_contains($errorBody, 'AuthenticationUnsuccessful')) {
                    Log::error('Xero authentication failed during customer sync', [
                        'company_id' => $currentCompany->id,
                        'status' => $statusCode,
                        'response' => $errorBody,
                    ]);
                    
                    $this->clearInvalidTokens();
                    
                    return ['skipped' => true, 'message' => 'Xero authentication failed. Please re-authorize your Xero connection in the settings.'];
                }
                
                throw new \Exception('Failed to fetch contacts from Xero: ' . $errorBody);
            }

            $xeroContacts = $response->json()['Contacts'] ?? [];
            
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
                        // Update existing customer and link it to Xero
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

        $currentCompany = $company ?? Company::getDefault();
        $products = Product::where('company_id', $currentCompany->id)->get();
        $results = [];

        // Fetch all Xero items once to avoid individual GET requests
        $xeroItemsMap = [];
        try {
            $response = $this->makeXeroRequest('get', $this->baseUrl . '/api.xro/2.0/Items');
            if ($response->successful()) {
                $xeroItems = $response->json()['Items'] ?? [];
                foreach ($xeroItems as $xeroItem) {
                    $xeroItemsMap[$xeroItem['ItemID']] = $xeroItem;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch Xero items for comparison, continuing with individual checks', [
                'error' => $e->getMessage(),
            ]);
        }

        // Prepare products for batch processing
        $productsToSync = [];
        $batchSize = 100; // Xero allows up to 100 items per batch request

        foreach ($products as $index => $product) {
            try {
                // Check if product exists in Xero using the fetched map
                $xeroItem = null;
                if ($product->xero_item_id && isset($xeroItemsMap[$product->xero_item_id])) {
                    $xeroItem = $xeroItemsMap[$product->xero_item_id];
                    
                    $appUpdated = $product->updated_at;
                    $xeroUpdated = $this->parseXeroDate($xeroItem['UpdatedDateUTC']);
                    
                    // If Xero is newer, sync from Xero instead
                    if ($xeroUpdated > $appUpdated) {
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
                
                // Prepare item data for batch
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
                
                $productsToSync[] = [
                    'product' => $product,
                    'itemData' => $itemData,
                ];
                
                // Process batch when we reach batch size or at the end
                if (count($productsToSync) >= $batchSize || $index === $products->count() - 1) {
                    $batchResults = $this->batchCreateOrUpdateProductsInXero($productsToSync);
                    $results = array_merge($results, $batchResults);
                    $productsToSync = [];
                    
                    // Add delay between batches to avoid rate limiting (except for last batch)
                    if ($index < $products->count() - 1) {
                        sleep(1);
                    }
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
                    // Store the Xero item ID in the database
                    $product->update(['xero_item_id' => $xeroItem['ItemID']]);
                    
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
                        $product->update(['xero_item_id' => $xeroItem['ItemID']]);
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

        $currentCompany = $company ?? Company::getDefault();
        $results = [];

        try {
            // Get all items from Xero
            $response = $this->makeXeroRequest('get', $this->baseUrl . '/api.xro/2.0/Items');

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
                        // Update existing product and link it to Xero
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

        $currentCompany = $company ?? Company::getDefault();
        $invoices = Invoice::where('company_id', $currentCompany->id)
            ->with(['customer', 'lineItems'])
            ->get();
        $results = [];
        
        Log::info('Starting invoice sync to Xero', [
            'company_id' => $currentCompany->id,
            'invoice_count' => $invoices->count(),
        ]);

        foreach ($invoices as $index => $invoice) {
            try {
                // Add delay between requests to avoid rate limiting (except for first request)
                if ($index > 0) {
                    // Xero allows 60 requests per minute, so add a 1 second delay between requests
                    sleep(1);
                }
                
                // Check if invoice exists in Xero and compare dates
                if ($invoice->xero_invoice_id) {
                    $xeroInvoice = $this->getXeroInvoice($invoice->xero_invoice_id);
                    if ($xeroInvoice) {
                        $appUpdated = $invoice->updated_at;
                        $xeroUpdated = $this->parseXeroDate($xeroInvoice['UpdatedDateUTC']);
                        
                        // If Xero is newer, sync from Xero instead
                        if ($xeroUpdated > $appUpdated) {
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
     * Create or update invoice in Xero
     */
    private function createOrUpdateInvoiceInXero(Invoice $invoice): array
    {
        // Ensure line items are loaded
        if (!$invoice->relationLoaded('lineItems')) {
            $invoice->load('lineItems');
        }
        
        // Ensure line items have products loaded
        $invoice->load('lineItems.product');
        
        // Ensure customer is loaded
        if (!$invoice->relationLoaded('customer')) {
            $invoice->load('customer');
        }
        
        if ($invoice->lineItems->isEmpty()) {
            throw new \Exception("Invoice '{$invoice->invoice_number}' has no line items. Cannot sync to Xero.");
        }
        
        // Get default tax code from TaxRate model, fallback to TAX002 if not set
        $currentCompany = $invoice->company ?? Company::getDefault();
        $defaultTaxRate = TaxRate::getDefaultForCompany($currentCompany->id);
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
            
            // Get account code from product if available, otherwise use default '200'
            $accountCode = '200'; // Default for custom products
            if ($lineItem->product_id && $lineItem->product) {
                $accountCode = $lineItem->product->sales_account_code ?? '200';
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
                'calculated_subtotal' => $lineItem->quantity * $lineItem->unit_price,
            ]);
            
            $lineItems[] = [
                'Description' => $lineItem->description ?? 'Item',
                'Quantity' => $lineItem->quantity,
                'UnitAmount' => $lineItem->unit_price,
                'LineAmount' => $lineAmount,
                'AccountCode' => $accountCode,
                'TaxType' => $defaultTaxCode,
            ];
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
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . '/api.xro/2.0/Invoices/' . $xeroInvoiceId);

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
                $invoice->update(['status' => $status]);
                
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

    /**
     * Update customer from Xero data
     */
    private function updateCustomerFromXero(Customer $customer, array $xeroCustomer): void
    {
        $updateData = [
            'name' => $xeroCustomer['Name'] ?? $customer->name,
            'email' => $xeroCustomer['EmailAddress'] ?? $customer->email,
            'xero_contact_id' => $xeroCustomer['ContactID'] ?? $customer->xero_contact_id,
        ];

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
        ];

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

        $currentCompany = $company ?? Company::getDefault();
        $suppliers = Supplier::where('company_id', $currentCompany->id)->get();
        $results = [];

        foreach ($suppliers as $supplier) {
            try {
                // Check if supplier exists in Xero and compare dates
                if ($supplier->xero_contact_id) {
                    $xeroSupplier = $this->getXeroContact($supplier->xero_contact_id);
                    if ($xeroSupplier) {
                        $appUpdated = $supplier->updated_at;
                        $xeroUpdated = $this->parseXeroDate($xeroSupplier['UpdatedDateUTC']);
                        
                        // If Xero is newer, sync from Xero instead
                        if ($xeroUpdated > $appUpdated) {
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
                }
                
                $xeroSupplier = $this->createOrUpdateSupplierInXero($supplier);
                
                // Store the Xero contact ID in the database
                if (isset($xeroSupplier['ContactID'])) {
                    $supplier->update(['xero_contact_id' => $xeroSupplier['ContactID']]);
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

        $currentCompany = $company ?? Company::getDefault();
        $results = [];

        try {
            // Get all contacts from Xero - we'll filter for suppliers in the response
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . '/api.xro/2.0/Contacts');

            if (!$response->successful()) {
                $errorBody = $response->body();
                $statusCode = $response->status();
                
                // Handle authentication errors specifically
                if ($statusCode === 403 && str_contains($errorBody, 'AuthenticationUnsuccessful')) {
                    Log::error('Xero authentication failed during supplier sync', [
                        'company_id' => $currentCompany->id,
                        'status' => $statusCode,
                        'response' => $errorBody,
                    ]);
                    
                    $this->clearInvalidTokens();
                    
                    return ['skipped' => true, 'message' => 'Xero authentication failed. Please re-authorize your Xero connection in the settings.'];
                }
                
                throw new \Exception('Failed to fetch suppliers from Xero: ' . $errorBody);
            }

            $xeroContacts = $response->json()['Contacts'] ?? [];
            
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
                        // Update existing supplier and link it to Xero
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

        // If supplier already has a Xero contact ID, update it
        if ($supplier->xero_contact_id) {
            $response = Http::withHeaders($this->getHeaders())
                ->post($this->baseUrl . '/api.xro/2.0/Contacts', [
                    'Contacts' => [array_merge($contactData, ['ContactID' => $supplier->xero_contact_id])]
                ]);
        } else {
            $response = Http::withHeaders($this->getHeaders())
                ->post($this->baseUrl . '/api.xro/2.0/Contacts', [
                    'Contacts' => [$contactData]
                ]);
        }

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

        $currentCompany = $company ?? Company::getDefault();
        $quotes = Quote::where('company_id', $currentCompany->id)
            ->get();
        $results = [];

        foreach ($quotes as $quote) {
            try {
                // Check if quote exists in Xero and compare dates
                if ($quote->xero_quote_id) {
                    $xeroQuote = $this->getXeroQuote($quote->xero_quote_id);
                    if ($xeroQuote) {
                        $appUpdated = $quote->updated_at;
                        $xeroUpdated = $this->parseXeroDate($xeroQuote['UpdatedDateUTC']);
                        
                        // If Xero is newer, sync from Xero instead
                        if ($xeroUpdated > $appUpdated) {
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
                }
                
                $xeroQuote = $this->createOrUpdateQuoteInXero($quote);
                $results[] = [
                    'quote_id' => $quote->id,
                    'quote_number' => $quote->quote_number,
                    'status' => 'success',
                    'xero_quote_id' => $xeroQuote['QuoteID'] ?? null,
                ];
            } catch (\Exception $e) {
                Log::error('Quote sync failed', [
                    'quote_id' => $quote->id,
                    'quote_number' => $quote->quote_number,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
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

        $currentCompany = $company ?? Company::getDefault();
        $results = [];

        try {
            // Get all quotes from Xero (quotes are invoices with Type='QUOTE')
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . '/api.xro/2.0/Quotes');

            if (!$response->successful()) {
                $errorBody = $response->body();
                $statusCode = $response->status();
                
                // Handle authentication errors specifically
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

            $xeroQuotes = $response->json()['Quotes'] ?? [];
            
            foreach ($xeroQuotes as $xeroQuote) {
                try {
                    // Skip if quote doesn't have required fields
                    if (empty($xeroQuote['QuoteNumber'])) {
                        continue;
                    }

                    // Check if quote already exists in app by Xero quote ID
                    $existingQuote = Quote::where('company_id', $currentCompany->id)
                        ->where('xero_quote_id', $xeroQuote['QuoteID'])
                        ->first();

                    // If not found by Xero ID, check by quote number
                    if (!$existingQuote) {
                        $existingQuote = Quote::where('company_id', $currentCompany->id)
                            ->where('quote_number', $xeroQuote['QuoteNumber'])
                            ->whereNull('xero_quote_id')
                            ->first();
                    }

                    if ($existingQuote) {
                        // Update existing quote and link it to Xero
                        $this->updateQuoteFromXeroData($existingQuote, $xeroQuote);
                        $results[] = [
                            'quote_id' => $existingQuote->id,
                            'quote_number' => $xeroQuote['QuoteNumber'],
                            'status' => 'updated',
                            'message' => 'Quote updated from Xero and linked to existing quote',
                        ];
                    } else {
                        // Create new quote
                        $quote = $this->createQuoteFromXero($xeroQuote, $currentCompany);
                        $results[] = [
                            'quote_id' => $quote->id,
                            'quote_number' => $xeroQuote['QuoteNumber'],
                            'status' => 'created',
                            'message' => 'Quote imported from Xero',
                        ];
                    }
                } catch (\Exception $e) {
                    $results[] = [
                        'quote_number' => $xeroQuote['QuoteNumber'] ?? 'Unknown',
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];
                }
            }

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
        
        // Ensure line items have products loaded
        $quote->load('lineItems.product');
        
        // Get default tax code from TaxRate model, fallback to OUTPUT3 if not set
        $currentCompany = $quote->company ?? Company::getDefault();
        $defaultTaxRate = TaxRate::getDefaultForCompany($currentCompany->id);
        $defaultTaxCode = $defaultTaxRate && $defaultTaxRate->xero_tax_rate_id 
            ? $defaultTaxRate->xero_tax_rate_id 
            : ($defaultTaxRate && $defaultTaxRate->code 
                ? $defaultTaxRate->code 
                : 'OUTPUT3');
        
        $lineItems = [];
        foreach ($quote->lineItems as $lineItem) {
            // Get account code from product if available, otherwise use default '200'
            $accountCode = '200'; // Default for custom products
            if ($lineItem->product_id && $lineItem->product) {
                $accountCode = $lineItem->product->sales_account_code ?? '200';
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

        // If quote already has a Xero ID, update it; otherwise create new
        if ($quote->xero_quote_id) {
            $quoteData['QuoteID'] = $quote->xero_quote_id;
            $response = Http::withHeaders($this->getHeaders())
                ->post($this->baseUrl . '/api.xro/2.0/Quotes', [
                    'Quotes' => [$quoteData]
                ]);
        } else {
            $response = Http::withHeaders($this->getHeaders())
                ->post($this->baseUrl . '/api.xro/2.0/Quotes', [
                    'Quotes' => [$quoteData]
                ]);
        }

        if (!$response->successful()) {
            throw new \Exception('Failed to create/update quote in Xero: ' . $response->body());
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

        if (!$customer) {
            throw new \Exception("Customer with Xero contact ID {$xeroQuote['Contact']['ContactID']} not found. Please sync customers first.");
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
     * Get Xero quote by ID
     */
    private function getXeroQuote(string $quoteId): ?array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . '/api.xro/2.0/Quotes/' . $quoteId);

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
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . '/api.xro/2.0/Invoices/' . $invoiceId);

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
        $updateData = [
            'status' => $this->mapXeroStatusToLocal($xeroInvoice['Status'] ?? $invoice->status),
            'total' => $xeroInvoice['Total'] ?? $invoice->total,
            'subtotal' => $xeroInvoice['SubTotal'] ?? $invoice->subtotal,
            'tax_amount' => $xeroInvoice['TotalTax'] ?? $invoice->tax_amount,
        ];

        // Update dates if available
        if (isset($xeroInvoice['DateString'])) {
            $updateData['invoice_date'] = \Carbon\Carbon::parse($xeroInvoice['DateString'])->format('Y-m-d');
        }
        if (isset($xeroInvoice['DueDateString'])) {
            $updateData['due_date'] = \Carbon\Carbon::parse($xeroInvoice['DueDateString'])->format('Y-m-d');
        }

        $invoice->update($updateData);
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
            'sales_account_code' => $xeroItem['SalesDetails']['AccountCode'] ?? null,
            'purchase_account_code' => $xeroItem['PurchaseDetails']['AccountCode'] ?? null,
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
        $currentCompany = $invoice->company ?? Company::getDefault();
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

        $response = Http::withHeaders($this->getHeaders())
            ->post($this->baseUrl . '/api.xro/2.0/Payments', [
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
     * Sync all payments for all invoices to Xero
     */
    public function syncAllPaymentsToXero(Company $company = null): array
    {
        if (!$this->settings->sync_invoices_to_xero) {
            return ['skipped' => true, 'message' => 'Invoice sync to Xero is disabled'];
        }

        $currentCompany = $company ?? Company::getDefault();
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
     * Sync tax rates from Xero (import existing Xero tax rates to app)
     */
    public function syncTaxRatesFromXero(Company $company = null): array
    {
        if (!$this->settings->sync_tax_rates_from_xero) {
            return ['skipped' => true, 'message' => 'Tax rate sync from Xero is disabled'];
        }

        $currentCompany = $company ?? Company::getDefault();
        $results = [];

        try {
            // Get all tax rates from Xero
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . '/api.xro/2.0/TaxRates');

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
                        // Update existing tax rate
                        $existingTaxRate->update([
                            'xero_tax_rate_id' => $xeroTaxRate['TaxType'],
                            'name' => $xeroTaxRate['Name'],
                            'code' => $xeroTaxRate['TaxType'] ?? null,
                            'rate' => $rate,
                            'description' => $xeroTaxRate['ReportTaxType'] ?? null,
                            'is_active' => true,
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

        $currentCompany = $company ?? Company::getDefault();
        $results = [];

        try {
            // Get all accounts from Xero, filter for bank accounts
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . '/api.xro/2.0/Accounts?where=Type=="BANK"');

            if (!$response->successful()) {
                $errorBody = $response->body();
                $statusCode = $response->status();
                
                if ($statusCode === 403 && str_contains($errorBody, 'AuthenticationUnsuccessful')) {
                    Log::error('Xero authentication failed during bank account sync', [
                        'company_id' => $currentCompany->id,
                        'status' => $statusCode,
                        'response' => $errorBody,
                    ]);
                    
                    $this->clearInvalidTokens();
                    
                    return ['skipped' => true, 'message' => 'Xero authentication failed. Please re-authorize your Xero connection in the settings.'];
                }
                
                throw new \Exception('Failed to fetch bank accounts from Xero: ' . $errorBody);
            }

            $xeroAccounts = $response->json()['Accounts'] ?? [];
            
            foreach ($xeroAccounts as $xeroAccount) {
                try {
                    // Skip if account doesn't have required fields
                    if (empty($xeroAccount['Name']) || empty($xeroAccount['Code'])) {
                        continue;
                    }

                    // Check if bank account already exists in app by Xero ID
                    $existingBankAccount = BankAccount::where('company_id', $currentCompany->id)
                        ->where('xero_account_id', $xeroAccount['AccountID'])
                        ->first();

                    // If not found by Xero ID, check by account number
                    if (!$existingBankAccount && !empty($xeroAccount['BankAccountNumber'])) {
                        $existingBankAccount = BankAccount::where('company_id', $currentCompany->id)
                            ->where('account_number', $xeroAccount['BankAccountNumber'])
                            ->whereNull('xero_account_id')
                            ->first();
                    }

                    // Map Xero account type to our account type
                    $accountType = 'Current';
                    if (isset($xeroAccount['BankAccountType'])) {
                        $typeMap = [
                            'CHECKING' => 'Current',
                            'SAVINGS' => 'Savings',
                            'CREDITCARD' => 'Credit Card',
                            'LOAN' => 'Loan',
                        ];
                        $accountType = $typeMap[$xeroAccount['BankAccountType']] ?? 'Current';
                    }

                    if ($existingBankAccount) {
                        // Update existing bank account
                        $existingBankAccount->update([
                            'xero_account_id' => $xeroAccount['AccountID'],
                            'account_name' => $xeroAccount['Name'],
                            'account_number' => $xeroAccount['BankAccountNumber'] ?? $xeroAccount['Code'],
                            'bank_name' => $xeroAccount['BankAccountNumber'] ? ($xeroAccount['Name'] ?? 'Unknown') : 'Unknown',
                            'branch_code' => $xeroAccount['BankAccountNumber'] ? null : null,
                            'account_type' => $accountType,
                            'currency' => $xeroAccount['CurrencyCode'] ?? 'ZAR',
                            'opening_balance' => (float) ($xeroAccount['Balance'] ?? 0),
                            'is_active' => $xeroAccount['Status'] === 'ACTIVE',
                        ]);
                        
                        $results[] = [
                            'bank_account_id' => $existingBankAccount->id,
                            'bank_account_name' => $xeroAccount['Name'],
                            'status' => 'updated',
                            'message' => 'Bank account updated from Xero',
                        ];
                    } else {
                        // Create new bank account
                        $bankAccount = BankAccount::create([
                            'company_id' => $currentCompany->id,
                            'xero_account_id' => $xeroAccount['AccountID'],
                            'account_name' => $xeroAccount['Name'],
                            'account_number' => $xeroAccount['BankAccountNumber'] ?? $xeroAccount['Code'],
                            'bank_name' => $xeroAccount['BankAccountNumber'] ? ($xeroAccount['Name'] ?? 'Unknown') : 'Unknown',
                            'branch_code' => null,
                            'account_type' => $accountType,
                            'currency' => $xeroAccount['CurrencyCode'] ?? 'ZAR',
                            'opening_balance' => (float) ($xeroAccount['Balance'] ?? 0),
                            'is_active' => $xeroAccount['Status'] === 'ACTIVE',
                        ]);
                        
                        $results[] = [
                            'bank_account_id' => $bankAccount->id,
                            'bank_account_name' => $xeroAccount['Name'],
                            'status' => 'created',
                            'message' => 'Bank account imported from Xero',
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

        $currentCompany = $company ?? Company::getDefault();
        $results = [];

        try {
            // Get all accounts from Xero, excluding bank accounts (those are handled separately)
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . '/api.xro/2.0/Accounts');

            if (!$response->successful()) {
                $errorBody = $response->body();
                $statusCode = $response->status();
                
                if ($statusCode === 403 && str_contains($errorBody, 'AuthenticationUnsuccessful')) {
                    Log::error('Xero authentication failed during chart of accounts sync', [
                        'company_id' => $currentCompany->id,
                        'status' => $statusCode,
                        'response' => $errorBody,
                    ]);
                    
                    $this->clearInvalidTokens();
                    
                    return ['skipped' => true, 'message' => 'Xero authentication failed. Please re-authorize your Xero connection in the settings.'];
                }
                
                throw new \Exception('Failed to fetch accounts from Xero: ' . $errorBody);
            }

            $responseData = $response->json();
            $allAccounts = $responseData['Accounts'] ?? [];
            
            Log::info('Chart of accounts sync - Xero API response', [
                'company_id' => $currentCompany->id,
                'accounts_count' => count($allAccounts),
                'response_keys' => array_keys($responseData),
                'first_account_sample' => $allAccounts[0] ?? null,
            ]);
            
            // Filter out bank accounts (Type == "BANK") as those are handled separately
            $xeroAccounts = array_values(array_filter($allAccounts, function($account) {
                return ($account['Type'] ?? '') !== 'BANK';
            }));
            
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
                    ];

                    if ($existingAccount) {
                        // Update existing account (parent will be set in second pass)
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
        
        // If no results and no errors, return a helpful message
        if (empty($results)) {
            Log::warning('Chart of accounts sync returned no results', [
                'company_id' => $currentCompany->id,
                'xero_accounts_count' => count($xeroAccounts ?? []),
            ]);
            
            return ['skipped' => true, 'message' => 'No chart of accounts found in Xero to import. Make sure you have accounts set up in Xero (excluding bank accounts which are imported separately).'];
        }

        return $results;
    }
}