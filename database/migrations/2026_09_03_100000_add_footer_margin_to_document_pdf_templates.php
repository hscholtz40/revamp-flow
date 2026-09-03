<?php

use App\Models\PdfTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        PdfTemplate::query()
            ->whereIn('module', ['invoice', 'quote', 'jobcard', 'proforma-invoice'])
            ->get(['id', 'css_styles'])
            ->each(function (PdfTemplate $template): void {
                $css = (string) $template->css_styles;
                if (preg_match('/@page\s*\{[^}]*margin-bottom\s*:/i', $css)) {
                    return;
                }

                $template->update([
                    'css_styles' => rtrim($css)."\n@page { margin-bottom: 70px; }\n",
                ]);
            });
    }

    public function down(): void
    {
        // Keep the reserved footer margin; removing it would hide company footers again.
    }
};
