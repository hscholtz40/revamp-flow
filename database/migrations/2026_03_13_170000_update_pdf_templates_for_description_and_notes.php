<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('pdf_templates')
            ->whereIn('module', ['invoice', 'quote', 'proforma-invoice'])
            ->orderBy('id')
            ->get()
            ->each(function ($template): void {
                $html = (string) $template->html_template;
                $updatedHtml = $html;

                if ($template->module === 'invoice') {
                    if (!str_contains($updatedHtml, '{{invoice.description}}')) {
                        if (str_contains($updatedHtml, '<div class="terms-section">')) {
                            $updatedHtml = str_replace(
                                '<div class="terms-section">' . "\n" . '    <h4>Notes</h4>',
                                '<div class="terms-section">' . "\n" . '    <h4>Description</h4>' . "\n" . '    <p>{{invoice.description}}</p>' . "\n" . '    <h4>Notes</h4>',
                                $updatedHtml
                            );
                        } else {
                            $updatedHtml = str_replace(
                                '<div class="signature-section">',
                                '<div class="terms-section">' . "\n" .
                                '    <h4>Description</h4>' . "\n" .
                                '    <p>{{invoice.description}}</p>' . "\n" .
                                '    <h4>Notes</h4>' . "\n" .
                                '    <p>{{invoice.notes}}</p>' . "\n" .
                                '    <h4>Terms & Conditions</h4>' . "\n" .
                                '    <p>{{invoice.terms}}</p>' . "\n" .
                                '</div>' . "\n" .
                                '<div class="signature-section">',
                                $updatedHtml
                            );
                        }
                    }
                }

                if (in_array($template->module, ['quote', 'proforma-invoice'], true)) {
                    if (!str_contains($updatedHtml, '{{quote.description}}')) {
                        $updatedHtml = str_replace(
                            '<div class="terms-section">' . "\n" . '    <h4>Notes</h4>',
                            '<div class="terms-section">' . "\n" . '    <h4>Description</h4>' . "\n" . '    <p>{{quote.description}}</p>' . "\n" . '    <h4>Notes</h4>',
                            $updatedHtml
                        );
                    }

                    $updatedHtml = str_replace('{{quote.terms}}', '{{quote.terms_conditions}}', $updatedHtml);
                }

                if ($updatedHtml !== $html) {
                    DB::table('pdf_templates')
                        ->where('id', $template->id)
                        ->update([
                            'html_template' => $updatedHtml,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Existing templates may have been manually edited after this migration,
        // so we intentionally do not try to revert the HTML changes.
    }
};
