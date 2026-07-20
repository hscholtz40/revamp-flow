<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\PdfTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class PdfGenerationService
{
    /**
     * Generate PDF using a template or fallback to default view
     */
    public function generatePdf(string $module, array $data, Company $company, ?int $templateId = null): \Barryvdh\DomPDF\PDF
    {
        $template = null;

        // If template ID is provided, try to load it
        if ($templateId) {
            $template = PdfTemplate::where('company_id', $company->id)
                ->where('module', $module)
                ->where('id', $templateId)
                ->where('is_active', true)
                ->first();

            // If template found, use it
            if ($template) {
                return $this->generateFromTemplate($template, $data, $company);
            }
        }

        // If no template ID provided or template not found, try to get default template
        if (! $templateId) {
            $template = PdfTemplate::where('company_id', $company->id)
                ->where('module', $module)
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();

            // If default template exists, use it
            if ($template) {
                return $this->generateFromTemplate($template, $data, $company);
            }
        }

        // Fallback to default Blade view
        return $this->generateFromBladeTemplate($module, $data, $company);
    }

    /**
     * Generate PDF from a template
     */
    protected function generateFromTemplate(PdfTemplate $template, array $data, Company $company): \Barryvdh\DomPDF\PDF
    {
        // Process Handlebars syntax in template
        $html = $this->processHandlebarsTemplate($template->html_template, $data);
        $html = $this->sanitizeRenderedTemplateHtml($html);
        $html = $this->injectCompanyFooter($html, $template->module, $company);
        $css = $template->css_styles ?? '';

        // Convert relative image paths to absolute URLs for dompdf
        $html = $this->convertImagePathsToAbsolute($html);

        // Create full HTML document with CSS
        $fullHtml = "<!DOCTYPE html>\n";
        $fullHtml .= "<html>\n";
        $fullHtml .= "<head>\n";
        $fullHtml .= "    <meta charset=\"utf-8\">\n";
        $fullHtml .= "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
        $fullHtml .= "    <style>\n";
        $fullHtml .= $css."\n";
        $fullHtml .= "    </style>\n";
        $fullHtml .= "</head>\n";
        $fullHtml .= "<body>\n";
        $fullHtml .= $html."\n";
        $fullHtml .= "</body>\n";
        $fullHtml .= "</html>\n";

        // Generate PDF directly from HTML
        return Pdf::loadHTML($fullHtml);
    }

    /**
     * Remove obvious executable HTML from rendered template output.
     */
    protected function sanitizeRenderedTemplateHtml(string $html): string
    {
        // Script tags are never needed for static PDF output.
        $html = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html) ?? $html;

        // Remove inline JS event handlers (onclick, onerror, etc.).
        $html = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html) ?? $html;

        // Remove javascript: URLs from href/src attributes.
        $html = preg_replace('/\s+(href|src)\s*=\s*("|\')\s*javascript:[\s\S]*?\2/i', '', $html) ?? $html;

        return $html;
    }

    /**
     * Convert relative image paths to absolute URLs or base64 for dompdf
     */
    protected function convertImagePathsToAbsolute(string $html): string
    {
        $html = preg_replace_callback(
            '/<img([^>]*)\s+src=["\']([^"\']+)["\']([^>]*)>/i',
            function ($matches) {
                $before = $matches[1];
                $src = $matches[2];
                $after = $matches[3];

                // Skip if already data URI
                if (preg_match('/^data:/i', $src)) {
                    return $matches[0];
                }

                // Convert to file path if it's a storage URL
                $filePath = null;
                if (preg_match('/\/storage\/(.+)$/i', $src, $pathMatches)) {
                    // Extract the storage path
                    $storagePath = 'public/'.$pathMatches[1];
                    $fullPath = storage_path('app/'.$storagePath);

                    if (file_exists($fullPath)) {
                        $filePath = $fullPath;
                    }
                } elseif (strpos($src, '/storage/') === 0) {
                    // Direct /storage/ path
                    $storagePath = 'public'.substr($src, 8); // Remove '/storage'
                    $fullPath = storage_path('app/'.$storagePath);

                    if (file_exists($fullPath)) {
                        $filePath = $fullPath;
                    }
                } elseif (strpos($src, '/') === 0 && ! preg_match('/^https?:\/\//i', $src)) {
                    // Local path starting with /
                    $publicPath = public_path($src);
                    if (file_exists($publicPath)) {
                        $filePath = $publicPath;
                    }
                }

                // If we found a file, convert to base64
                if ($filePath && file_exists($filePath)) {
                    $imageData = file_get_contents($filePath);
                    $imageInfo = getimagesize($filePath);
                    $mimeType = $imageInfo['mime'] ?? 'image/png';
                    $base64 = base64_encode($imageData);
                    $dataUri = 'data:'.$mimeType.';base64,'.$base64;

                    return '<img'.$before.' src="'.htmlspecialchars($dataUri, ENT_QUOTES, 'UTF-8').'"'.$after.'>';
                }

                // If it's already an absolute URL (http/https), keep it as is
                if (preg_match('/^https?:\/\//i', $src)) {
                    return $matches[0];
                }

                // Otherwise, convert to absolute URL as fallback
                if (strpos($src, '/storage/') === 0) {
                    $absoluteUrl = asset($src);
                } elseif (strpos($src, '/') === 0) {
                    $absoluteUrl = url($src);
                } else {
                    $absoluteUrl = url('/'.ltrim($src, '/'));
                }

                return '<img'.$before.' src="'.htmlspecialchars($absoluteUrl, ENT_QUOTES, 'UTF-8').'"'.$after.'>';
            },
            $html
        );

        return $html;
    }

    /**
     * Generate PDF from default Blade template
     */
    protected function generateFromBladeTemplate(string $module, array $data, Company $company): \Barryvdh\DomPDF\PDF
    {
        $viewMap = [
            'invoice' => 'pdf.invoice',
            'quote' => 'pdf.quote',
            'jobcard' => 'pdf.jobcard',
            'proforma-invoice' => 'pdf.proforma-invoice',
            'purchase-order' => 'pdf.purchase-order',
            'delivery-note' => 'pdf.delivery-note',
        ];

        $view = $viewMap[$module] ?? 'pdf.invoice';

        $html = View::make($view, $data)->render();
        $html = $this->convertImagePathsToAbsolute($html);
        $html = $this->injectCompanyFooter($html, $module, $company);

        return Pdf::loadHTML($html);
    }

    /**
     * Inject company-configured PDF footer text when set in company settings.
     */
    protected function injectCompanyFooter(string $html, string $module, Company $company): string
    {
        $footerText = match ($module) {
            'invoice' => $company->invoice_footer,
            'quote', 'proforma-invoice' => $company->quote_footer,
            'jobcard' => $company->jobcard_footer,
            default => null,
        };

        $footerText = trim((string) $footerText);
        if ($footerText === '') {
            return $html;
        }

        $formattedFooter = nl2br(e($footerText), false);

        if (preg_match('/(<div\s+class="footer-left"[^>]*>)([\s\S]*?)(<\/div>)/i', $html)) {
            return preg_replace(
                '/(<div\s+class="footer-left"[^>]*>)([\s\S]*?)(<\/div>)/i',
                '$1'.$formattedFooter.'$3',
                $html,
                1
            ) ?? $html;
        }

        $footerBlock = '<div class="footer"><div class="footer-left">'.$formattedFooter.'</div></div>';

        if (stripos($html, '</body>') !== false) {
            return str_ireplace('</body>', $footerBlock.'</body>', $html);
        }

        return $html.$footerBlock;
    }

    /**
     * Process Handlebars-style placeholders in custom PDF HTML.
     *
     * - {@code {{path}}} and {@code {{this.path}}} inside {@code #each}: HTML-escaped (safe default).
     * - {@code {{{path}}}} and {@code {{{this.path}}}}: raw HTML (use only for trusted rich-text fields).
     */
    protected function processHandlebarsTemplate(string $html, array $data): string
    {
        $processedData = $this->prepareDataForHandlebars($data);

        $html = preg_replace_callback(
            '/\{\{#each\s+([^}]+)\}\}([\s\S]*?)\{\{\/each\}\}/',
            function ($matches) use ($processedData) {
                $path = trim($matches[1]);
                $content = $matches[2];

                $items = $this->getNestedValue($processedData, $path);

                if (empty($items) || ! is_array($items)) {
                    return '';
                }

                $isNumericArray = array_keys($items) === range(0, count($items) - 1);
                if (! $isNumericArray) {
                    $items = array_values($items);
                }

                $result = '';
                foreach ($items as $item) {
                    if (is_object($item)) {
                        if (method_exists($item, 'toArray')) {
                            $item = $this->objectToArray($item->toArray());
                        } elseif ($item instanceof \Illuminate\Support\Collection) {
                            $item = $this->objectToArray($item->toArray());
                        } else {
                            $item = $this->objectToArray((array) $item);
                        }
                    } elseif (is_array($item)) {
                        $item = $this->objectToArray($item);
                    }

                    if (! is_array($item)) {
                        continue;
                    }

                    $itemCode = null;
                    if (! empty($item['product'])) {
                        $product = is_array($item['product']) ? $item['product'] : [];
                        $itemCode = $product['sku'] ?? $product['barcode'] ?? null;
                    }
                    $item['description_with_code'] = ($item['description'] ?? '').($itemCode ? " ({$itemCode})" : '');

                    $itemHtml = $this->interpolateThisRawBlocks($content, $item);
                    $itemHtml = $this->interpolateThisEscapedBlocks($itemHtml, $item);
                    $result .= $itemHtml;
                }

                return $result;
            },
            $html
        ) ?? $html;

        $html = $this->interpolateRawBlocks($html, $processedData);
        $html = $this->interpolateEscapedBlocks($html, $processedData);

        return $html;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function interpolateThisRawBlocks(string $html, array $row): string
    {
        return preg_replace_callback(
            '/\{\{\{\s*this\.([^}]+?)\s*\}\}\}/',
            function (array $m) use ($row) {
                $path = trim($m[1]);
                $value = $this->getNestedValue($row, $path);

                return $this->formatRawHtmlInterpolation($value);
            },
            $html
        ) ?? $html;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function interpolateThisEscapedBlocks(string $html, array $row): string
    {
        return preg_replace_callback(
            '/\{\{\s*this\.([^}]+?)\s*\}\}/',
            function (array $m) use ($row) {
                $path = trim($m[1]);
                $value = $this->getNestedValue($row, $path);

                return $this->escapeHtmlInterpolation($value);
            },
            $html
        ) ?? $html;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function interpolateRawBlocks(string $html, array $data): string
    {
        return preg_replace_callback(
            '/\{\{\{\s*([^}]+?)\s*\}\}\}/',
            function (array $m) use ($data) {
                $path = trim($m[1]);
                if ($path === '' || str_starts_with($path, '#') || str_starts_with($path, '/')) {
                    return $m[0];
                }
                $value = $this->getNestedValue($data, $path);

                return $this->formatRawHtmlInterpolation($value);
            },
            $html
        ) ?? $html;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function interpolateEscapedBlocks(string $html, array $data): string
    {
        return preg_replace_callback(
            '/\{\{\s*([^#\/][^}]*?)\s*\}\}/',
            function (array $m) use ($data) {
                $path = trim($m[1]);
                if ($path === '' || str_starts_with($path, '#') || str_starts_with($path, '/')) {
                    return $m[0];
                }
                $value = $this->getNestedValue($data, $path);

                return $this->escapeHtmlInterpolation($value);
            },
            $html
        ) ?? $html;
    }

    protected function escapeHtmlInterpolation(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '';
        }
        if (is_int($value) || is_float($value)) {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (is_string($value)) {
            return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        try {
            return htmlspecialchars(json_encode($value, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } catch (\JsonException) {
            return '';
        }
    }

    protected function formatRawHtmlInterpolation(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * Prepare data for Handlebars processing
     */
    protected function prepareDataForHandlebars(array $data): array
    {
        $prepared = [];
        foreach ($data as $key => $value) {
            if (is_object($value)) {
                // For Eloquent models, use toArray() to properly convert relationships
                if (method_exists($value, 'toArray')) {
                    $prepared[$key] = $this->objectToArray($value->toArray());

                    // Add logo path for PDF if it's a Company model
                    if ($key === 'company' && method_exists($value, 'getLogoPathForPdf')) {
                        $logoPath = $value->getLogoPathForPdf();
                        if ($logoPath) {
                            $prepared[$key]['logo_path_for_pdf'] = $logoPath;
                            $prepared[$key]['getLogoPathForPdf'] = $logoPath;
                        }
                    }

                    if ($key === 'invoice' && $value instanceof Invoice) {
                        $prepared[$key]['document_title'] = $value->getPdfDocumentTitle();
                    }
                } else {
                    $prepared[$key] = $this->objectToArray($value);
                }
            } elseif (is_array($value)) {
                $prepared[$key] = $this->objectToArray($value);
            } else {
                $prepared[$key] = $value;
            }
        }

        return $prepared;
    }

    /**
     * Convert object to array recursively
     * Returns array for objects/arrays, mixed for scalars
     */
    protected function objectToArray($object)
    {
        // Handle Eloquent models
        if (is_object($object) && method_exists($object, 'toArray')) {
            $object = $object->toArray();
        } elseif (is_object($object)) {
            // For non-Eloquent objects, try to convert to array
            if ($object instanceof \Illuminate\Support\Collection) {
                $object = $object->toArray();
            } else {
                $object = (array) $object;
            }
        }

        if (is_array($object)) {
            $result = [];
            foreach ($object as $key => $value) {
                if (is_object($value) || is_array($value)) {
                    $result[$key] = $this->objectToArray($value);
                } else {
                    $result[$key] = $value;
                }
            }

            // Backwards-compatible aliases for older PDF templates.
            if (! isset($result['code']) && ! empty($result['sku'])) {
                $result['code'] = $result['sku'];
            }

            if (! isset($result['terms']) && ! empty($result['terms_conditions'])) {
                $result['terms'] = $result['terms_conditions'];
            }

            if (! isset($result['work_notes']) && ! empty($result['notes'])) {
                $result['work_notes'] = $result['notes'];
            }

            return $result;
        }

        // This should only be reached if a scalar is passed directly
        // Return as-is (but this case shouldn't happen in normal usage)
        return $object;
    }

    /**
     * Get nested value from array using dot notation
     */
    protected function getNestedValue(array $data, string $path)
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value)) {
                // Try exact key first
                if (isset($value[$key])) {
                    $value = $value[$key];
                }
                // Try snake_case version (Laravel converts camelCase to snake_case in toArray())
                elseif (isset($value[\Illuminate\Support\Str::snake($key)])) {
                    $value = $value[\Illuminate\Support\Str::snake($key)];
                }
                // Try camelCase version
                elseif (isset($value[\Illuminate\Support\Str::camel($key)])) {
                    $value = $value[\Illuminate\Support\Str::camel($key)];
                } else {
                    return null;
                }
            } elseif (is_object($value)) {
                if (isset($value->$key)) {
                    $value = $value->$key;
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }

        return $value;
    }
}
