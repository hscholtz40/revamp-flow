<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\PdfTemplate;
use Illuminate\Console\Command;

class CreateDefaultPurchaseOrderTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'templates:create-purchase-order-defaults';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create default purchase order PDF templates for all companies';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $companies = Company::where('is_active', true)->get();
        
        $this->info("Creating default purchase order templates for {$companies->count()} companies...");
        
        $htmlTemplate = $this->getDefaultHtmlTemplate();
        $cssStyles = $this->getDefaultCssStyles();
        
        $created = 0;
        $skipped = 0;
        
        foreach ($companies as $company) {
            // Check if default template already exists
            $existing = PdfTemplate::where('company_id', $company->id)
                ->where('module', 'purchase-order')
                ->where('is_default', true)
                ->first();
            
            if ($existing) {
                $this->warn("Company '{$company->name}' already has a default purchase order template. Skipping...");
                $skipped++;
                continue;
            }
            
            // Unset any existing defaults for this module
            PdfTemplate::where('company_id', $company->id)
                ->where('module', 'purchase-order')
                ->update(['is_default' => false]);
            
            // Create default template
            PdfTemplate::create([
                'company_id' => $company->id,
                'module' => 'purchase-order',
                'name' => 'Default Purchase Order Template',
                'html_template' => $htmlTemplate,
                'css_styles' => $cssStyles,
                'is_active' => true,
                'is_default' => true,
            ]);
            
            $created++;
            $this->info("Created default template for company '{$company->name}'");
        }
        
        $this->info("\nCompleted! Created {$created} templates, skipped {$skipped}.");
        
        return Command::SUCCESS;
    }
    
    /**
     * Get the default HTML template for purchase orders
     */
    public function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
<div class="page-header">Page 1 of 1</div>

<div class="document-title">Purchase Order</div>

<div class="company-section">
    <div class="company-left">
        {{#if company.logo_path_for_pdf}}
        <img src="{{company.logo_path_for_pdf}}" alt="Company Logo" class="company-logo">
        {{/if}}
        <div class="company-name">{{company.name}}</div>
        <div class="company-tagline">{{company.description}}</div>
        
        <div class="company-details">
            <p><strong>{{company.name}}</strong></p>
            {{#if company.address}}
            <p>{{company.address}}</p>
            {{/if}}
            {{#if company.city}}
            <p>{{company.city}}</p>
            {{/if}}
            {{#if company.vat_number}}
            <p>VAT Number {{company.vat_number}}</p>
            {{/if}}
            {{#if company.phone}}
            <p>Telephone {{company.phone}}</p>
            {{/if}}
        </div>
    </div>
    
    <div class="company-right">
        <div class="po-info">
            <h4>Purchase Order Details</h4>
            <p><strong>PO Number:</strong> {{purchaseOrder.po_number}}</p>
            <p><strong>Order Date:</strong> {{purchaseOrder.order_date}}</p>
            {{#if purchaseOrder.expected_delivery_date}}
            <p><strong>Expected Delivery:</strong> {{purchaseOrder.expected_delivery_date}}</p>
            {{/if}}
            <p><strong>Status:</strong> {{purchaseOrder.status}}</p>
        </div>
    </div>
</div>

<div class="supplier-section">
    <div class="supplier-left">
        <div class="supplier-info">
            <h4>Supplier:</h4>
            <p><strong>{{purchaseOrder.supplier.name}}</strong></p>
            {{#if purchaseOrder.supplier.address}}
            <p>{{purchaseOrder.supplier.address}}</p>
            {{/if}}
            {{#if purchaseOrder.supplier.city}}
            <p>{{purchaseOrder.supplier.city}}</p>
            {{/if}}
            {{#if purchaseOrder.supplier.postal_code}}
            <p>{{purchaseOrder.supplier.postal_code}}</p>
            {{/if}}
            {{#if purchaseOrder.supplier.phone}}
            <p>Phone: {{purchaseOrder.supplier.phone}}</p>
            {{/if}}
            {{#if purchaseOrder.supplier.email}}
            <p>Email: {{purchaseOrder.supplier.email}}</p>
            {{/if}}
        </div>
    </div>
    
    <div class="supplier-right">
        <div class="po-info">
            <h4>Delivery Information</h4>
            {{#if purchaseOrder.expected_delivery_date}}
            <p><strong>Expected:</strong> {{purchaseOrder.expected_delivery_date}}</p>
            {{/if}}
            {{#if purchaseOrder.received_date}}
            <p><strong>Received:</strong> {{purchaseOrder.received_date}}</p>
            {{/if}}
        </div>
    </div>
</div>

<table class="line-items-table">
    <thead>
        <tr>
            <th>Description</th>
            <th class="text-right">Quantity</th>
            <th class="text-right">Unit Cost</th>
            <th class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        {{#each purchaseOrder.items}}
        <tr>
            <td>
                <strong>{{this.product.name}}</strong>
                {{#if this.product.sku}}
                <br><small>SKU: {{this.product.sku}}</small>
                {{/if}}
                {{#if this.description}}
                <br><small>{{this.description}}</small>
                {{/if}}
            </td>
            <td class="text-right">{{this.quantity}}</td>
            <td class="text-right">R {{this.unit_cost}}</td>
            <td class="text-right">R {{this.total}}</td>
        </tr>
        {{/each}}
    </tbody>
</table>

<div class="totals-section">
    <div class="total-row">
        <span>Subtotal:</span>
        <span>R {{purchaseOrder.subtotal}}</span>
    </div>
    {{#if purchaseOrder.tax_amount}}
    <div class="total-row">
        <span>Tax:</span>
        <span>R {{purchaseOrder.tax_amount}}</span>
    </div>
    {{/if}}
    <div class="total-row final">
        <span>Total:</span>
        <span>R {{purchaseOrder.total}}</span>
    </div>
</div>

{{#if purchaseOrder.notes}}
<div class="terms-section">
    <h4>Notes:</h4>
    <p>{{purchaseOrder.notes}}</p>
</div>
{{/if}}

{{#if purchaseOrder.terms}}
<div class="terms-section">
    <h4>Terms & Conditions:</h4>
    <p>{{purchaseOrder.terms}}</p>
</div>
{{/if}}

<div class="footer">
    <div class="footer-left">
        Generated on {{date}}
    </div>
    <div class="footer-right">
        {{company.name}}
    </div>
</div>
HTML;
    }
    
    /**
     * Get the default CSS styles for purchase orders
     */
    public function getDefaultCssStyles(): string
    {
        return <<<'CSS'
body {
    font-family: Arial, sans-serif;
    line-height: 1.4;
    color: #000;
    margin: 0;
    padding: 20px;
    font-size: 12px;
    background: white;
}

.page-header {
    text-align: right;
    font-size: 10px;
    color: #666;
    margin-bottom: 10px;
}

.document-title {
    text-align: center;
    font-size: 28px;
    font-weight: bold;
    color: #000;
    margin: 20px 0;
    text-transform: uppercase;
}

.company-section {
    display: table;
    width: 100%;
    margin-bottom: 20px;
    border: 1px solid #000;
    padding: 15px;
}

.company-left {
    display: table-cell;
    width: 60%;
    vertical-align: top;
}

.company-right {
    display: table-cell;
    width: 40%;
    vertical-align: top;
    text-align: left;
}

.company-logo {
    max-width: 220px;
    max-height: 110px;
    margin-bottom: 10px;
}

.company-name {
    font-size: 16px;
    font-weight: bold;
    color: #000;
    margin-bottom: 5px;
}

.company-tagline {
    font-size: 11px;
    color: #666;
    margin-bottom: 15px;
}

.company-details {
    font-size: 11px;
    line-height: 1.3;
}

.company-details p {
    margin: 2px 0;
}

.supplier-section {
    display: table;
    width: 100%;
    margin-bottom: 20px;
    border: 1px solid #000;
    padding: 15px;
}

.supplier-left {
    display: table-cell;
    width: 60%;
    vertical-align: top;
}

.supplier-right {
    display: table-cell;
    width: 40%;
    vertical-align: top;
    text-align: left;
}

.supplier-info h4 {
    margin: 0 0 8px 0;
    font-size: 12px;
    font-weight: bold;
    color: #000;
}

.supplier-info p {
    margin: 2px 0;
    font-size: 11px;
}

.po-info {
    text-align: left;
    font-size: 11px;
}

.po-info h4 {
    margin: 0 0 8px 0;
    font-size: 12px;
    font-weight: bold;
    color: #000;
}

.po-info p {
    margin: 2px 0;
}

.line-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    border: 1px solid #000;
}

.line-items-table th {
    background-color: #f0f0f0;
    padding: 8px;
    text-align: left;
    border-bottom: 2px solid #000;
    font-size: 11px;
    font-weight: bold;
}

.line-items-table td {
    padding: 8px;
    border-bottom: 1px solid #ccc;
    font-size: 11px;
}

.line-items-table .text-right {
    text-align: right;
}

.totals-section {
    float: right;
    width: 300px;
    margin-top: 20px;
}

.total-row {
    display: table;
    width: 100%;
    margin-bottom: 5px;
}

.total-row span {
    display: table-cell;
    font-size: 11px;
    padding: 2px 0;
}

.total-row span:first-child {
    text-align: left;
}

.total-row span:last-child {
    text-align: right;
}

.total-row.final {
    font-weight: bold;
    font-size: 12px;
    border-top: 1px solid #000;
    padding-top: 8px;
    margin-top: 8px;
}

.terms-section {
    clear: both;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #000;
    font-size: 11px;
    text-align: left;
}

.terms-section h4 {
    margin: 0 0 10px 0;
    font-size: 12px;
    font-weight: bold;
    color: #000;
}

.terms-section p {
    margin: 0 0 8px 0;
    font-size: 11px;
    line-height: 1.4;
}

.footer {
    position: fixed;
    bottom: 20px;
    left: 20px;
    right: 20px;
    font-size: 9px;
    color: #666;
    display: table;
    width: 100%;
}

.footer-left {
    display: table-cell;
    text-align: left;
}

.footer-right {
    display: table-cell;
    text-align: right;
}
CSS;
    }
}

