<?php

namespace Database\Seeders;

use App\Models\ReportTemplate;
use Illuminate\Database\Seeder;

class DefaultReportTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Invoice Report Templates
        ReportTemplate::create([
            'company_id' => null, // Default templates are available to all companies
            'name' => 'Invoice Summary Report',
            'description' => 'Summary of all invoices with key details',
            'entity_type' => 'invoice',
            'config' => [
                'columns' => [
                    'invoice_number',
                    'customer.name',
                    'formatted_date',
                    'due_date',
                    'status',
                    'formatted_total',
                ],
                'group_by' => null,
                'sort_by' => 'invoice_date',
                'sort_direction' => 'desc',
                'show_totals' => true,
            ],
            'is_default' => true,
            'created_by' => null,
        ]);

        ReportTemplate::create([
            'company_id' => 1,
            'name' => 'Invoice Detailed Report',
            'description' => 'Detailed invoice report with all financial information',
            'entity_type' => 'invoice',
            'config' => [
                'columns' => [
                    'invoice_number',
                    'customer.name',
                    'salesperson.name',
                    'formatted_date',
                    'due_date',
                    'status',
                    'subtotal',
                    'tax_amount',
                    'discount_amount',
                    'formatted_total',
                ],
                'group_by' => null,
                'sort_by' => 'invoice_date',
                'sort_direction' => 'desc',
                'show_totals' => true,
            ],
            'is_default' => false,
            'created_by' => null,
        ]);

        ReportTemplate::create([
            'company_id' => 1,
            'name' => 'Invoice by Status',
            'description' => 'Invoices grouped by status',
            'entity_type' => 'invoice',
            'config' => [
                'columns' => [
                    'invoice_number',
                    'customer.name',
                    'formatted_date',
                    'formatted_total',
                ],
                'group_by' => 'status',
                'sort_by' => 'invoice_date',
                'sort_direction' => 'desc',
                'show_totals' => true,
            ],
            'is_default' => false,
            'created_by' => null,
        ]);

        // Quote Report Templates
        ReportTemplate::create([
            'company_id' => 1,
            'name' => 'Quote Summary Report',
            'description' => 'Summary of all quotes with key details',
            'entity_type' => 'quote',
            'config' => [
                'columns' => [
                    'quote_number',
                    'customer.name',
                    'formatted_date',
                    'expiry_date',
                    'status',
                    'formatted_total',
                ],
                'group_by' => null,
                'sort_by' => 'created_at',
                'sort_direction' => 'desc',
                'show_totals' => true,
            ],
            'is_default' => true,
            'created_by' => null,
        ]);

        ReportTemplate::create([
            'company_id' => 1,
            'name' => 'Quote Detailed Report',
            'description' => 'Detailed quote report with all financial information',
            'entity_type' => 'quote',
            'config' => [
                'columns' => [
                    'quote_number',
                    'customer.name',
                    'formatted_date',
                    'expiry_date',
                    'status',
                    'subtotal',
                    'tax_amount',
                    'discount_amount',
                    'formatted_total',
                ],
                'group_by' => null,
                'sort_by' => 'created_at',
                'sort_direction' => 'desc',
                'show_totals' => true,
            ],
            'is_default' => false,
            'created_by' => null,
        ]);

        ReportTemplate::create([
            'company_id' => 1,
            'name' => 'Quote by Status',
            'description' => 'Quotes grouped by status',
            'entity_type' => 'quote',
            'config' => [
                'columns' => [
                    'quote_number',
                    'customer.name',
                    'formatted_date',
                    'formatted_total',
                ],
                'group_by' => 'status',
                'sort_by' => 'created_at',
                'sort_direction' => 'desc',
                'show_totals' => true,
            ],
            'is_default' => false,
            'created_by' => null,
        ]);

        // Job Card Report Templates
        ReportTemplate::create([
            'company_id' => 1,
            'name' => 'Job Card Summary Report',
            'description' => 'Summary of all job cards with key details',
            'entity_type' => 'jobcard',
            'config' => [
                'columns' => [
                    'job_number',
                    'customer.name',
                    'formatted_date',
                    'due_date',
                    'completed_date',
                    'status',
                    'formatted_total',
                ],
                'group_by' => null,
                'sort_by' => 'start_date',
                'sort_direction' => 'desc',
                'show_totals' => true,
            ],
            'is_default' => true,
            'created_by' => null,
        ]);

        ReportTemplate::create([
            'company_id' => 1,
            'name' => 'Job Card Detailed Report',
            'description' => 'Detailed job card report with all financial information',
            'entity_type' => 'jobcard',
            'config' => [
                'columns' => [
                    'job_number',
                    'customer.name',
                    'formatted_date',
                    'due_date',
                    'completed_date',
                    'status',
                    'subtotal',
                    'tax_amount',
                    'discount_amount',
                    'formatted_total',
                ],
                'group_by' => null,
                'sort_by' => 'start_date',
                'sort_direction' => 'desc',
                'show_totals' => true,
            ],
            'is_default' => false,
            'created_by' => null,
        ]);

        ReportTemplate::create([
            'company_id' => 1,
            'name' => 'Job Card by Status',
            'description' => 'Job cards grouped by status',
            'entity_type' => 'jobcard',
            'config' => [
                'columns' => [
                    'job_number',
                    'customer.name',
                    'formatted_date',
                    'formatted_total',
                ],
                'group_by' => 'status',
                'sort_by' => 'start_date',
                'sort_direction' => 'desc',
                'show_totals' => true,
            ],
            'is_default' => false,
            'created_by' => null,
        ]);
    }
}
