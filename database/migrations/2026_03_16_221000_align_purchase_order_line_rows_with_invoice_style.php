<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $templates = DB::table('pdf_templates')
            ->where('module', 'purchase-order')
            ->where('is_default', true)
            ->get(['id', 'html_template']);

        foreach ($templates as $template) {
            $html = (string) ($template->html_template ?? '');

            $html = str_replace(
                '<th>Description</th>
            <th class="text-right">Quantity</th>
            <th class="text-right">Unit Cost</th>
            <th class="text-right">Tax</th>
            <th class="text-right">Total</th>',
                "<th>Item Code</th>\n            <th>Item Description</th>\n            <th class=\"text-right\">QTY</th>\n            <th class=\"text-right\">Price (Ex)</th>\n            <th class=\"text-right\">Tax</th>\n            <th class=\"text-right\">Total (Excl)</th>",
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
            <td class="text-right">{{#if this.taxRate}}R {{this.tax_amount}}{{else}}—{{/if}}</td>
            <td class="text-right">R {{this.total}}</td>',
                "<td>{{this.product.sku}}</td>\n            <td>{{this.description}}</td>\n            <td class=\"text-right\">{{this.quantity}}</td>\n            <td class=\"text-right\">R{{this.unit_cost}}</td>\n            <td class=\"text-right\">{{#if this.taxRate}}R{{this.tax_amount}}{{else}}—{{/if}}</td>\n            <td class=\"text-right\">R{{this.total}}</td>",
                $html
            );

            DB::table('pdf_templates')
                ->where('id', $template->id)
                ->update([
                    'html_template' => $html,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Intentionally non-reversible content migration.
    }
};
