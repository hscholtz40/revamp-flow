<?php

namespace App\Services;

use App\Models\PdfTemplate;
use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;

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
        if (!$templateId) {
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
        $fullHtml .= $css . "\n";
        $fullHtml .= "    </style>\n";
        $fullHtml .= "</head>\n";
        $fullHtml .= "<body>\n";
        $fullHtml .= $html . "\n";
        $fullHtml .= "</body>\n";
        $fullHtml .= "</html>\n";
        
        // Generate PDF directly from HTML
        return Pdf::loadHTML($fullHtml);
    }
    
    /**
     * Convert relative image paths to absolute URLs or base64 for dompdf
     */
    protected function convertImagePathsToAbsolute(string $html): string
    {
        // Convert img src attributes from relative paths to base64 data URIs for better PDF compatibility
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
                    $storagePath = 'public/' . $pathMatches[1];
                    $fullPath = storage_path('app/' . $storagePath);
                    
                    if (file_exists($fullPath)) {
                        $filePath = $fullPath;
                    }
                } elseif (strpos($src, '/storage/') === 0) {
                    // Direct /storage/ path
                    $storagePath = 'public' . substr($src, 8); // Remove '/storage'
                    $fullPath = storage_path('app/' . $storagePath);
                    
                    if (file_exists($fullPath)) {
                        $filePath = $fullPath;
                    }
                } elseif (strpos($src, '/') === 0 && !preg_match('/^https?:\/\//i', $src)) {
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
                    $dataUri = 'data:' . $mimeType . ';base64,' . $base64;
                    
                    return '<img' . $before . ' src="' . htmlspecialchars($dataUri, ENT_QUOTES, 'UTF-8') . '"' . $after . '>';
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
                    $absoluteUrl = url('/' . ltrim($src, '/'));
                }
                
                return '<img' . $before . ' src="' . htmlspecialchars($absoluteUrl, ENT_QUOTES, 'UTF-8') . '"' . $after . '>';
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
        ];
        
        $view = $viewMap[$module] ?? 'pdf.invoice';
        
        return Pdf::loadView($view, $data);
    }
    
    /**
     * Process Handlebars template syntax
     */
    protected function processHandlebarsTemplate(string $html, array $data): string
    {
        // Convert data to array format for easier processing
        $processedData = $this->prepareDataForHandlebars($data);
        
        // Process {{#each}} loops
        $html = preg_replace_callback(
            '/\{\{#each\s+([^}]+)\}\}([\s\S]*?)\{\{\/each\}\}/',
            function ($matches) use ($processedData) {
                $path = trim($matches[1]);
                $content = $matches[2];
                
                $items = $this->getNestedValue($processedData, $path);
                
                // Handle null or empty values
                if (empty($items)) {
                    return '';
                }
                
                // Ensure items is an array
                if (!is_array($items)) {
                    return '';
                }
                
                // Handle numeric arrays (lists) vs associative arrays
                // If it's a numeric array, use it directly
                // If it's associative with numeric keys, use it directly
                $isNumericArray = array_keys($items) === range(0, count($items) - 1);
                
                if (!$isNumericArray) {
                    // If it's an associative array but we want to iterate, convert to list
                    $items = array_values($items);
                }
                
                $result = '';
                foreach ($items as $item) {
                    // Ensure item is an array for getNestedValue
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
                    
                    if (!is_array($item)) {
                        continue;
                    }
                    
                    $itemHtml = $content;
                    // Replace {{this.property}} with item values
                    $itemHtml = preg_replace_callback(
                        '/\{\{this\.([^}]+)\}\}/',
                        function ($m) use ($item) {
                            $prop = $m[1];
                            $value = $this->getNestedValue($item, $prop);
                            return $value !== null ? (string) $value : '';
                        },
                        $itemHtml
                    );
                    $result .= $itemHtml;
                }
                
                return $result;
            },
            $html
        );
        
        // Process {{variable}} and {{variable.property}}
        $html = preg_replace_callback(
            '/\{\{([^#\/][^}]*)\}\}/',
            function ($matches) use ($processedData) {
                $path = trim($matches[1]);
                $value = $this->getNestedValue($processedData, $path);
                return $value ?? '';
            },
            $html
        );
        
        return $html;
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
            if (!isset($result['code']) && !empty($result['sku'])) {
                $result['code'] = $result['sku'];
            }

            if (!isset($result['terms']) && !empty($result['terms_conditions'])) {
                $result['terms'] = $result['terms_conditions'];
            }

            if (!isset($result['work_notes']) && !empty($result['notes'])) {
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
                }
                else {
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
