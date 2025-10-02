<?php

namespace App\Services;

use App\Models\XeroSettings;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Invoice;
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
        if (!$this->settings->isTokenExpired()) {
            return true;
        }

        // If no refresh token, we can't refresh
        if (!$this->settings->refresh_token) {
            Log::warning('Cannot refresh Xero token: no refresh token available');
            return false;
        }

        try {
            $response = Http::asForm()->post('https://identity.xero.com/connect/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $this->settings->client_id,
                'client_secret' => $this->settings->client_secret,
                'refresh_token' => $this->settings->refresh_token,
            ]);

            if (!$response->successful()) {
                Log::error('Failed to refresh Xero token: ' . $response->body());
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

            return true;
        } catch (\Exception $e) {
            Log::error('Error refreshing Xero token: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get headers for Xero API requests
     */
    private function getHeaders(): array
    {
        if (!$this->refreshTokenIfNeeded()) {
            throw new \Exception('Failed to refresh Xero token');
        }

        return [
            'Authorization' => 'Bearer ' . $this->settings->access_token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Xero-tenant-id' => $this->settings->tenant_id,
        ];
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
     * Create or update product in Xero
     */
    private function createOrUpdateProductInXero(Product $product): array
    {
        $itemData = [
            'Code' => 'PROD-' . $product->id,
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
            'paid' => 'PAID',
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
}