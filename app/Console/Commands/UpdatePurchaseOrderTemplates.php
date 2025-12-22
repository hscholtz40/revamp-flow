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
        $templates = PdfTemplate::where('module', 'purchase-order')->get();
        
        $this->info("Updating {$templates->count()} purchase order templates...");
        
        foreach ($templates as $template) {
            $html = $template->html_template;
            
            // Fix logo path references
            $html = str_replace('{{company.getLogoPathForPdf}}', '{{company.logo_path_for_pdf}}', $html);
            $html = str_replace('{{#if company.logo_path}}', '{{#if company.logo_path_for_pdf}}', $html);
            
            $template->html_template = $html;
            $template->save();
            
            $this->info("Updated template: {$template->name} (ID: {$template->id})");
        }
        
        $this->info("Completed!");
        return Command::SUCCESS;
    }
}

