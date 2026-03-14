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
            $css = $template->css_styles ?? '';
            
            // Fix logo path references
            $html = str_replace('{{company.getLogoPathForPdf}}', '{{company.logo_path_for_pdf}}', $html);
            $html = str_replace('{{#if company.logo_path}}', '{{#if company.logo_path_for_pdf}}', $html);

            // Increase the default logo size slightly for stored purchase order templates.
            $css = str_replace('max-width: 200px;', 'max-width: 220px;', $css);
            $css = str_replace('max-height: 100px;', 'max-height: 110px;', $css);
            
            $template->html_template = $html;
            $template->css_styles = $css;
            $template->save();
            
            $this->info("Updated template: {$template->name} (ID: {$template->id})");
        }
        
        $this->info("Completed!");
        return Command::SUCCESS;
    }
}

