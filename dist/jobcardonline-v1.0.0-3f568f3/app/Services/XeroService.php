<?php

namespace App\Services;

use App\Models\XeroSettings;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Company;
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

        foreach ($customers as $customer) {
            try {
                // Check if customer exists in Xero and compare dates
                if ($customer->xero_contact_id) {
                    $xeroCustomer = $this->getXeroContact($customer->xero_contact_id);
                    if ($xeroCustomer) {
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
                }
                
                $xeroCustomer = $this->createOrUpdateCustomerInXero($customer);
                
                // Store the Xero contact ID in the database
                if (isset($xeroCustomer['ContactID'])) {
                    $customer->update(['xero_contact_id' => $xeroCustomer['ContactID']]);
                }
                
                $results[] = [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'status' => 'success',
                    'xero_contact_id' => $xeroCustomer['ContactID'] ?? null,
                ];
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
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . '/api.xro/2.0/Contacts');

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
     * Create or update customer in Xero
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

        $response = Http::withHeaders($this->getHeaders())
            ->post($this->baseUrl . '/api.xro/2.0/Contacts', [
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

        foreach ($products as $product) {
            try {
                // Check if product exists in Xero and compare dates
                if ($product->xero_item_id) {
                    $xeroItem = $this->getXeroItem($product->xero_item_id);
                    if ($xeroItem) {
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
                }
                
                $xeroItem = $this->createOrUpdateProductInXero($product);
                
                // Store the Xero item ID in the database
                if (isset($xeroItem['ItemID'])) {
                    $product->update(['xero_item_id' => $xeroItem['ItemID']]);
                }
                
                $results[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'status' => 'success',
                    'xero_item_id' => $xeroItem['ItemID'] ?? null,
                ];
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
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . '/api.xro/2.0/Items');

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

        $response = Http::withHeaders($this->getHeaders())
            ->post($this->baseUrl . '/api.xro/2.0/Items', [
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
            ->get();
        $results = [];

        foreach ($invoices as $invoice) {
            try {
                // Check if invoice exists in Xero and compare dates
                if ($invoice->xero_invoice_id) {
                    $xeroInvoice = $this->getXeroInvoice($invoice->xero_invoice_id);
                    if ($xeroInvoice) {
                        // Skip updating if invoice is already paid in Xero
                        $invoice_paid = false;
                        if ($xeroInvoice['Status'] === 'PAID') {
                            $invoice_paid = true;
                            $results[] = [
                                'invoice_id' => $invoice->id,
                                'invoice_number' => $invoice->invoice_number,
                                'status' => 'skipped',
                                'message' => 'Invoice skipped - already paid in Xero',
                            ];
                            //continue;
                        }
                        
                        $appUpdated = $invoice->updated_at;
                        $xeroUpdated = $this->parseXeroDate($xeroInvoice['UpdatedDateUTC']);
                        
                        // If Xero is newer, sync from Xero instead
                        if ($xeroUpdated > $appUpdated || $invoice_paid) {
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
        $lineItems = [];
        foreach ($invoice->lineItems as $lineItem) {
            $lineItems[] = [
                'Description' => $lineItem->description,
                'Quantity' => $lineItem->quantity,
                'UnitAmount' => $lineItem->unit_price,
                'LineAmount' => $lineItem->total,
                'AccountCode' => '200', // Sales account code - this should be configurable
                'TaxType' => 'OUTPUT3', // Output tax for sales
            ];
        }

        $invoiceData = [
            'Type' => 'ACCREC',
            'Contact' => [
                'ContactID' => $this->getXeroContactId($invoice->customer),
            ],
            'Date' => $invoice->invoice_date,
            'DueDate' => $invoice->due_date,
            'LineItems' => $lineItems,
            'SubTotal' => $invoice->subtotal,
            'TotalTax' => $invoice->tax_amount,
            'Total' => $invoice->total,
            'Status' => $this->mapInvoiceStatus($invoice->status),
            'Reference' => $invoice->invoice_number,
        ];

        // If invoice already has a Xero ID, update it; otherwise create new
        if ($invoice->xero_invoice_id) {
            $invoiceData['InvoiceID'] = $invoice->xero_invoice_id;
            $response = Http::withHeaders($this->getHeaders())
                ->post($this->baseUrl . '/api.xro/2.0/Invoices', [
                    'Invoices' => [$invoiceData]
                ]);
        } else {
            $response = Http::withHeaders($this->getHeaders())
                ->post($this->baseUrl . '/api.xro/2.0/Invoices', [
                    'Invoices' => [$invoiceData]
                ]);
        }

        if (!$response->successful()) {
            throw new \Exception('Failed to create/update invoice in Xero: ' . $response->body());
        }

        $result = $response->json();
        $xeroInvoice = $result['Invoices'][0];
        
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
     * Get Xero contact by ID
     */
    private function getXeroContact(string $contactId): ?array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . '/api.xro/2.0/Contacts/' . $contactId);

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
    private function getXeroItem(string $itemId): ?array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . '/api.xro/2.0/Items/' . $itemId);

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

        $accountCode = $this->getPaymentAccountCode($payment->payment_method);
        
        // Validate the account before attempting payment
        if (!$this->validatePaymentAccount($accountCode)) {
            throw new \Exception("Account code '{$accountCode}' is not valid for payments in Xero. Please check your Xero account setup.");
        }
        
        // Log the payment details for debugging
        Log::info('Creating payment in Xero', [
            'payment_id' => $payment->id,
            'payment_method' => $payment->payment_method,
            'account_code' => $accountCode,
            'amount' => $payment->amount,
            'invoice_id' => $invoice->id,
            'xero_invoice_id' => $invoice->xero_invoice_id,
        ]);
        
        $paymentData = [
            'Invoice' => [
                'InvoiceID' => $invoice->xero_invoice_id,
            ],
            'Account' => [
                'Code' => $accountCode,
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
                'account_code' => $accountCode,
                'payment_method' => $payment->payment_method,
                'response' => $errorBody,
            ]);
            
            // Parse Xero error for more specific message
            $errorData = json_decode($errorBody, true);
            if (isset($errorData['Elements'][0]['ValidationErrors'][0]['Message'])) {
                $specificError = $errorData['Elements'][0]['ValidationErrors'][0]['Message'];
                throw new \Exception("Failed to create payment in Xero: {$specificError}. Account code '{$accountCode}' may not exist or be invalid for payments.");
            }
            
            throw new \Exception('Failed to create payment in Xero: ' . $errorBody);
        }

        $result = $response->json();
        return $result['Payments'][0];
    }

    /**
     * Get account code for payment method
     */
    private function getPaymentAccountCode(string $paymentMethod): string
    {
        $accountCode = match($paymentMethod) {
            'cash' => $this->settings->payment_account_cash,
            'card' => $this->settings->payment_account_card,
            'eft' => $this->settings->payment_account_eft,
            default => $this->settings->payment_account_cash,
        };

        // If no account code is configured, throw an error
        if (empty($accountCode)) {
            throw new \Exception("Payment account code for '{$paymentMethod}' is not configured in Xero settings. Please configure payment account codes in Xero integration settings.");
        }

        return $accountCode;
    }

    /**
     * Validate that an account code exists and is valid for payments in Xero
     */
    private function validatePaymentAccount(string $accountCode): bool
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . '/api.xro/2.0/Accounts/' . $accountCode);

            if (!$response->successful()) {
                Log::warning("Account code '{$accountCode}' not found in Xero");
                return false;
            }

            $account = $response->json()['Accounts'][0] ?? null;
            if (!$account) {
                return false;
            }

            // Check if account type is suitable for payments
            $suitableTypes = ['BANK', 'CURRENT', 'CASH'];
            $accountType = $account['Type'] ?? '';
            
            if (!in_array($accountType, $suitableTypes)) {
                Log::warning("Account '{$accountCode}' type '{$accountType}' is not suitable for payments");
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Error validating account '{$accountCode}': " . $e->getMessage());
            return false;
        }
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
}