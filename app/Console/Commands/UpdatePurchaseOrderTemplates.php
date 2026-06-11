<?php

namespace App\Console\Commands;

use App\Models\PdfTemplate;
use Illuminate\Console\Command;

class UpdatePurchaseOrderTemplates extends Command
{
    protected $signature = 'templates:update-purchase-order';

    protected $description = 'Update existing purchase order templates with correct syntax';

    public function handle()
    {
        $templates = PdfTemplate::where('module', 'purchase-order')
            ->where('is_default', true)
            ->get();

        $this->info("Updating {$templates->count()} purchase order templates...");

        foreach ($templates as $template) {
            /** @var \App\Models\PdfTemplate $template */
            $html = $template->html_template;
            $css = $template->css_styles ?? '';

            // Fix logo path references
            $html = str_replace('{{company.getLogoPathForPdf}}', '{{company.logo_path_for_pdf}}', $html);
            $html = str_replace('{{#if company.logo_path}}', '{{#if company.logo_path_for_pdf}}', $html);

            // Keep PO defaults aligned with invoice-like line item styling.
            $css = str_replace('max-width: 200px;', 'max-width: 240px;', $css);
            $css = str_replace('max-width: 220px;', 'max-width: 240px;', $css);
            $css = str_replace('max-height: 100px;', 'max-height: 120px;', $css);
            $css = str_replace('max-height: 110px;', 'max-height: 120px;', $css);
            $css = str_replace('font-size: 28px;', 'font-size: 24px;', $css);

            $css = str_replace(
                ".line-items-table {\n    width: 100%;\n    border-collapse: collapse;\n    margin-top: 20px;\n    border: 1px solid #000;\n}\n\n.line-items-table th {\n    background-color: #f0f0f0;\n    padding: 8px;\n    text-align: left;\n    border-bottom: 2px solid #000;\n    font-size: 11px;\n    font-weight: bold;\n}\n\n.line-items-table td {\n    padding: 8px;\n    border-bottom: 1px solid #ccc;\n    font-size: 11px;\n}",
                ".line-items-table {\n    width: 100%;\n    border-collapse: collapse;\n    margin-bottom: 20px;\n}\n\n.line-items-table th,\n.line-items-table td {\n    border-bottom: 1px solid #000;\n    padding: 6px;\n    font-size: 11px;\n}\n\n.line-items-table th {\n    font-weight: bold;\n    text-align: left;\n    border-bottom: 2px solid #000;\n}\n\n.line-items-table td {\n    text-align: left;\n}",
                $css
            );

            // Upgrade default purchase-order template line columns to include tax and company email.
            $html = str_replace(
                '<p>Telephone {{company.phone}}</p>',
                "<p>Telephone {{company.phone}}</p>\n            <p>Email {{company.email}}</p>",
                $html
            );
            $html = str_replace(
                '<th>Description</th>
            <th class="text-right">Quantity</th>
            <th class="text-right">Unit Cost</th>
            <th class="text-right">Total</th>',
                "<th>Item Code</th>\n            <th>Item Description</th>\n            <th class=\"text-right\">QTY</th>\n            <th class=\"text-right\">Price (Ex)</th>\n            <th class=\"text-right\">Tax</th>\n            <th class=\"text-right\">Total (Excl)</th>",
                $html
            );
            $html = str_replace(
                '<p>{{purchaseOrder.notes}}</p>',
                '<p>{{{purchaseOrder.notes}}}</p>',
                $html
            );
            $html = str_replace(
                '<p>{{purchaseOrder.terms}}</p>',
                '<p>{{{purchaseOrder.terms}}}</p>',
                $html
            );

            $html = str_replace(
                '<td>
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
            <td class="text-right">R {{this.total}}</td>',
                "<td>{{this.product.sku}}</td>\n            <td>{{this.description}}</td>\n            <td class=\"text-right\">{{this.quantity}}</td>\n            <td class=\"text-right\">R{{this.unit_cost}}</td>\n            <td class=\"text-right\">{{#if this.taxRate}}R{{this.tax_amount}}{{else}}—{{/if}}</td>\n            <td class=\"text-right\">R{{this.total}}</td>",
                $html
            );

            $template->html_template = $html;
            $template->css_styles = $css;
            $template->save();

            $this->info("Updated template: {$template->name} (ID: {$template->id})");
        }

        $this->info('Completed!');

        return Command::SUCCESS;
    }
}
