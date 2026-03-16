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
        $signatureBlock = '<div class="signature-section">
    <div class="signature-row">
        <div class="signature-item">
            <div>Received by</div>
            <div class="signature-line"></div>
        </div>
        <div class="signature-item">
            <div>Date</div>
            <div class="signature-line"></div>
        </div>
        <div class="signature-item">
            <div>Signature</div>
            <div class="signature-line"></div>
        </div>
    </div>
</div>
';

        $signatureCss = '
.signature-section {
    clear: both;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #000;
}
.signature-row {
    display: table;
    width: 100%;
    margin-bottom: 15px;
}
.signature-item {
    display: table-cell;
    width: 33.33%;
    text-align: left;
    font-size: 11px;
}
.signature-line {
    border-bottom: 1px dashed #000;
    height: 20px;
    margin-top: 5px;
}
';

        DB::table('pdf_templates')
            ->whereIn('module', ['quote', 'proforma-invoice'])
            ->orderBy('id')
            ->get(['id', 'html_template', 'css_styles'])
            ->each(function ($template) use ($signatureBlock, $signatureCss): void {
                $html = (string) ($template->html_template ?? '');
                $css = (string) ($template->css_styles ?? '');
                $updatedHtml = $html;
                $updatedCss = $css;

                if (!str_contains($updatedHtml, 'signature-section')) {
                    $updatedHtml = str_replace(
                        '<div class="footer">',
                        $signatureBlock . '<div class="footer">',
                        $updatedHtml
                    );
                }

                if (!str_contains($updatedCss, 'signature-section') && $updatedHtml !== $html) {
                    $updatedCss = $css . $signatureCss;
                }

                if ($updatedHtml !== $html || $updatedCss !== $css) {
                    DB::table('pdf_templates')
                        ->where('id', $template->id)
                        ->update([
                            'html_template' => $updatedHtml,
                            'css_styles' => $updatedCss,
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
