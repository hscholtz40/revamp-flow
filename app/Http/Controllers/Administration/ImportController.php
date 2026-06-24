<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    private const IMPORT_DISK = 'local';

    public function index(): Response
    {
        return Inertia::render('administration/Import', [
            'entityDefinitions' => $this->entityDefinitions(),
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity' => ['required', 'in:customers,suppliers,products'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $entity = $validated['entity'];
        $path = $request->file('file')->store('imports/tmp', self::IMPORT_DISK);
        $absolutePath = $this->resolveImportPath($path);

        $handle = $absolutePath ? fopen($absolutePath, 'r') : false;
        if ($handle === false) {
            return response()->json(['message' => 'Unable to read uploaded file.'], 422);
        }

        $delimiter = $this->detectDelimiter($handle);
        $headers = fgetcsv($handle, 0, $delimiter) ?: [];
        $headers = $this->normalizeCsvHeaders($headers);

        if (count($headers) === 0 || count(array_filter($headers, fn ($h) => $h !== '')) === 0) {
            fclose($handle);

            return response()->json(['message' => 'CSV header row is required.'], 422);
        }

        $sampleRows = [];
        $rowsRead = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false && $rowsRead < 5) {
            $assoc = [];
            foreach ($headers as $idx => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = (string) ($row[$idx] ?? '');
            }
            $sampleRows[] = $assoc;
            $rowsRead++;
        }
        fclose($handle);

        $companyId = (int) (auth()->user()->getCurrentCompany()?->id ?? 0);
        if ($companyId <= 0) {
            return response()->json(['message' => 'Invalid company context for import.'], 422);
        }

        $token = $this->createImportToken([
            'entity' => $entity,
            'path' => $path,
            'disk' => self::IMPORT_DISK,
            'delimiter' => $delimiter,
            'headers' => $headers,
            'uploaded_by' => (int) auth()->id(),
            'company_id' => $companyId,
        ]);

        return response()->json([
            'token' => $token,
            'headers' => $headers,
            'sampleRows' => $sampleRows,
            'fields' => $this->entityDefinitions()[$entity]['fields'],
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'entity' => ['required', 'in:customers,suppliers,products'],
            'mapping' => ['required', 'array'],
            'defaults' => ['nullable', 'array'],
        ]);

        $currentCompany = auth()->user()->getCurrentCompany();
        $companyId = (int) ($currentCompany?->id ?? 0);
        if ($companyId <= 0) {
            return response()->json(['message' => 'Invalid company context for import.'], 422);
        }

        $cached = $this->resolveImportToken($validated['token'], (int) auth()->id(), $companyId);
        if ($cached === null) {
            return response()->json(['message' => 'Import session expired. Please upload CSV again.'], 422);
        }
        if (($cached['entity'] ?? null) !== $validated['entity']) {
            return response()->json(['message' => 'Import entity mismatch. Please restart import.'], 422);
        }

        $definitions = $this->entityDefinitions();
        $entity = $validated['entity'];
        $fields = $definitions[$entity]['fields'];
        $requiredFieldKeys = collect($fields)->where('required', true)->pluck('key')->values()->all();
        $mapping = $validated['mapping'];
        $defaults = is_array($validated['defaults'] ?? null) ? $validated['defaults'] : [];

        $missingRequired = collect($requiredFieldKeys)
            ->filter(function ($fieldKey) use ($mapping, $defaults) {
                $hasMapping = ! empty($mapping[$fieldKey]) && $mapping[$fieldKey] !== '__skip';
                $hasDefault = $this->hasUsableDefault($defaults[$fieldKey] ?? null);

                return ! $hasMapping && ! $hasDefault;
            })
            ->values()
            ->all();
        if ($missingRequired !== []) {
            return response()->json([
                'message' => 'Required fields must be mapped before import.',
                'missing_required' => $missingRequired,
            ], 422);
        }

        $disk = is_string($cached['disk'] ?? null) ? $cached['disk'] : self::IMPORT_DISK;
        $absolutePath = $this->resolveImportPath((string) $cached['path'], $disk);
        $handle = $absolutePath ? fopen($absolutePath, 'r') : false;
        if ($handle === false) {
            return response()->json(['message' => 'Uploaded CSV file could not be found. Please upload it again.'], 422);
        }

        $delimiter = is_string($cached['delimiter'] ?? null) && $cached['delimiter'] !== ''
            ? $cached['delimiter']
            : $this->detectDelimiter($handle);

        $parsedHeaders = fgetcsv($handle, 0, $delimiter) ?: [];
        $parsedHeaders = $this->normalizeCsvHeaders($parsedHeaders);
        $headers = is_array($cached['headers'] ?? null) && $cached['headers'] !== []
            ? $this->normalizeCsvHeaders($cached['headers'])
            : $parsedHeaders;

        $created = 0;
        $skipped = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;
            $mapped = [];
            foreach ($mapping as $fieldKey => $columnName) {
                if (! is_string($columnName) || $columnName === '' || $columnName === '__skip') {
                    continue;
                }
                $idx = $this->resolveColumnIndex($headers, $columnName);
                if ($idx === false) {
                    continue;
                }
                $mapped[$fieldKey] = (string) ($row[$idx] ?? '');
            }
            $mapped = $this->applyDefaultsToMappedData($mapped, $defaults);

            try {
                $ok = $this->importRow($entity, $mapped, $companyId);
                if ($ok) {
                    $created++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => $e->getMessage(),
                ];
                if (count($errors) >= 20) {
                    break;
                }
            }
        }
        fclose($handle);

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    private function createImportToken(array $payload): string
    {
        $payload['expires_at'] = now()->addHour()->timestamp;

        return Crypt::encryptString(json_encode($payload));
    }

    private function resolveImportToken(string $token, int $userId, int $companyId): ?array
    {
        try {
            $cached = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($cached)) {
            return null;
        }

        if ((int) ($cached['expires_at'] ?? 0) < now()->timestamp) {
            return null;
        }

        if ((int) ($cached['uploaded_by'] ?? 0) !== $userId) {
            return null;
        }

        if ((int) ($cached['company_id'] ?? 0) !== $companyId) {
            return null;
        }

        return $cached;
    }

    private function importRow(string $entity, array $mapped, int $companyId): bool
    {
        return match ($entity) {
            'customers' => $this->importCustomerRow($mapped, $companyId),
            'suppliers' => $this->importSupplierRow($mapped, $companyId),
            'products' => $this->importProductRow($mapped, $companyId),
            default => false,
        };
    }

    private function importCustomerRow(array $mapped, int $companyId): bool
    {
        $payload = [
            'name' => trim((string) ($mapped['name'] ?? '')),
            'company_id' => $companyId,
        ];
        $this->assignNullableStringIfProvided($payload, 'email', $mapped);
        $this->assignNullableStringIfProvided($payload, 'phone', $mapped);
        $this->assignNullableStringIfProvided($payload, 'address', $mapped);
        $this->assignNullableStringIfProvided($payload, 'city', $mapped);
        $this->assignNullableStringIfProvided($payload, 'state', $mapped);
        $this->assignNullableStringIfProvided($payload, 'postal_code', $mapped);
        $this->assignNullableStringIfProvided($payload, 'country', $mapped);
        $this->assignNullableStringIfProvided($payload, 'vat_number', $mapped);
        $this->assignNullableStringIfProvided($payload, 'account_code', $mapped);
        $this->assignNullableStringIfProvided($payload, 'terms', $mapped);
        $this->assignNullableStringIfProvided($payload, 'notes', $mapped);
        $this->assignBooleanIfProvided($payload, 'is_active', $mapped, true);
        $this->assignBooleanIfProvided($payload, 'is_default_sales', $mapped, false);

        $validator = Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'account_code' => ['nullable', 'string', 'max:255'],
            'terms' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'is_default_sales' => ['boolean'],
        ]);
        $validator->validate();

        Customer::create($payload);

        return true;
    }

    private function importSupplierRow(array $mapped, int $companyId): bool
    {
        $payload = [
            'name' => trim((string) ($mapped['name'] ?? '')),
            'company_id' => $companyId,
        ];
        $this->assignNullableStringIfProvided($payload, 'email', $mapped);
        $this->assignNullableStringIfProvided($payload, 'phone', $mapped);
        $this->assignNullableStringIfProvided($payload, 'address', $mapped);
        $this->assignNullableStringIfProvided($payload, 'city', $mapped);
        $this->assignNullableStringIfProvided($payload, 'state', $mapped);
        $this->assignNullableStringIfProvided($payload, 'postal_code', $mapped);
        $this->assignNullableStringIfProvided($payload, 'country', $mapped);
        $this->assignNullableStringIfProvided($payload, 'vat_number', $mapped);
        $this->assignNullableStringIfProvided($payload, 'notes', $mapped);
        $this->assignBooleanIfProvided($payload, 'is_active', $mapped, true);

        $validator = Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);
        $validator->validate();

        Supplier::create($payload);

        return true;
    }

    private function importProductRow(array $mapped, int $companyId): bool
    {
        $type = strtolower(trim((string) ($mapped['type'] ?? Product::make()->type ?? 'product')));
        if (! in_array($type, ['product', 'service'], true)) {
            $type = 'product';
        }

        $payload = [
            'name' => trim((string) ($mapped['name'] ?? '')),
            'type' => $type,
            'price' => (float) ($mapped['price'] ?? 0),
            'unit' => trim((string) ($mapped['unit'] ?? Product::make()->unit ?? 'each')) ?: 'each',
            'company_id' => $companyId,
        ];
        $this->assignNullableStringIfProvided($payload, 'description', $mapped);
        $this->assignNullableStringIfProvided($payload, 'sku', $mapped);
        $this->assignNullableStringIfProvided($payload, 'barcode', $mapped);
        $this->assignNullableFloatIfProvided($payload, 'cost', $mapped);
        $this->assignIntegerIfProvided($payload, 'stock_quantity', $mapped);
        $this->assignIntegerIfProvided($payload, 'min_stock_level', $mapped);
        $this->assignBooleanIfProvided($payload, 'track_stock', $mapped, true);
        $this->assignBooleanIfProvided($payload, 'track_batches', $mapped, false);
        $this->assignBooleanIfProvided($payload, 'track_serial_numbers', $mapped, false);
        $this->assignNullableStringIfProvided($payload, 'valuation_method', $mapped);
        $this->assignBooleanIfProvided($payload, 'is_active', $mapped, true);
        $this->assignNullableStringIfProvided($payload, 'category', $mapped);
        $this->assignNullableStringIfProvided($payload, 'notes', $mapped);

        if ($payload['type'] === 'service') {
            $payload['track_stock'] = false;
            $payload['stock_quantity'] = 0;
        }

        $validator = Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:product,service'],
            'price' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            'valuation_method' => ['nullable', 'in:fifo,lifo,average_cost'],
            'is_active' => ['boolean'],
            'track_stock' => ['boolean'],
            'track_batches' => ['boolean'],
            'track_serial_numbers' => ['boolean'],
        ]);
        $validator->validate();

        Product::create($payload);

        return true;
    }

    private function entityDefinitions(): array
    {
        return [
            'customers' => [
                'label' => 'Customers',
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'required' => true],
                    ['key' => 'email', 'label' => 'Email', 'required' => false],
                    ['key' => 'phone', 'label' => 'Phone', 'required' => false],
                    ['key' => 'address', 'label' => 'Address', 'required' => false],
                    ['key' => 'city', 'label' => 'City', 'required' => false],
                    ['key' => 'state', 'label' => 'State', 'required' => false],
                    ['key' => 'postal_code', 'label' => 'Postal Code', 'required' => false],
                    ['key' => 'country', 'label' => 'Country', 'required' => false],
                    ['key' => 'vat_number', 'label' => 'VAT Number', 'required' => false],
                    ['key' => 'account_code', 'label' => 'Account Code', 'required' => false],
                    ['key' => 'terms', 'label' => 'Terms', 'required' => false],
                    ['key' => 'notes', 'label' => 'Notes', 'required' => false],
                    ['key' => 'is_active', 'label' => 'Is Active', 'required' => false, 'default_type' => 'select', 'default_options' => ['true' => 'True', 'false' => 'False']],
                    ['key' => 'is_default_sales', 'label' => 'Default Sales Customer', 'required' => false, 'default_type' => 'select', 'default_options' => ['true' => 'True', 'false' => 'False']],
                ],
            ],
            'suppliers' => [
                'label' => 'Suppliers',
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'required' => true],
                    ['key' => 'email', 'label' => 'Email', 'required' => false],
                    ['key' => 'phone', 'label' => 'Phone', 'required' => false],
                    ['key' => 'address', 'label' => 'Address', 'required' => false],
                    ['key' => 'city', 'label' => 'City', 'required' => false],
                    ['key' => 'state', 'label' => 'State', 'required' => false],
                    ['key' => 'postal_code', 'label' => 'Postal Code', 'required' => false],
                    ['key' => 'country', 'label' => 'Country', 'required' => false],
                    ['key' => 'vat_number', 'label' => 'VAT Number', 'required' => false],
                    ['key' => 'notes', 'label' => 'Notes', 'required' => false],
                    ['key' => 'is_active', 'label' => 'Is Active', 'required' => false, 'default_type' => 'select', 'default_options' => ['true' => 'True', 'false' => 'False']],
                ],
            ],
            'products' => [
                'label' => 'Products',
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'required' => true],
                    ['key' => 'description', 'label' => 'Description', 'required' => false],
                    ['key' => 'type', 'label' => 'Type (product/service)', 'required' => true, 'default_type' => 'select', 'default_options' => ['product' => 'Product', 'service' => 'Service']],
                    ['key' => 'sku', 'label' => 'SKU', 'required' => false],
                    ['key' => 'barcode', 'label' => 'Barcode', 'required' => false],
                    ['key' => 'price', 'label' => 'Price', 'required' => true],
                    ['key' => 'cost', 'label' => 'Cost', 'required' => false],
                    ['key' => 'unit', 'label' => 'Unit', 'required' => true, 'default_type' => 'text', 'default_placeholder' => 'each'],
                    ['key' => 'stock_quantity', 'label' => 'Stock Quantity', 'required' => false],
                    ['key' => 'min_stock_level', 'label' => 'Min Stock Level', 'required' => false],
                    ['key' => 'track_stock', 'label' => 'Track Stock', 'required' => false, 'default_type' => 'select', 'default_options' => ['true' => 'True', 'false' => 'False']],
                    ['key' => 'track_batches', 'label' => 'Track Batches', 'required' => false, 'default_type' => 'select', 'default_options' => ['true' => 'True', 'false' => 'False']],
                    ['key' => 'track_serial_numbers', 'label' => 'Track Serial Numbers', 'required' => false, 'default_type' => 'select', 'default_options' => ['true' => 'True', 'false' => 'False']],
                    ['key' => 'valuation_method', 'label' => 'Valuation Method', 'required' => false],
                    ['key' => 'is_active', 'label' => 'Is Active', 'required' => false, 'default_type' => 'select', 'default_options' => ['true' => 'True', 'false' => 'False']],
                    ['key' => 'category', 'label' => 'Category', 'required' => false],
                    ['key' => 'notes', 'label' => 'Notes', 'required' => false],
                ],
            ],
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }

    private function toBoolean(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes', 'y'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'n'], true)) {
            return false;
        }

        return $default;
    }

    private function toNullableFloat(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (float) $value;
    }

    private function hasUsableDefault(mixed $value): bool
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return true;
        }

        return is_string($value) && trim($value) !== '';
    }

    private function applyDefaultsToMappedData(array $mapped, array $defaults): array
    {
        foreach ($defaults as $fieldKey => $defaultValue) {
            if (! is_string($fieldKey) || ! $this->hasUsableDefault($defaultValue)) {
                continue;
            }

            $currentValue = $mapped[$fieldKey] ?? null;
            if (! $this->hasUsableDefault($currentValue)) {
                $mapped[$fieldKey] = is_string($defaultValue) ? trim($defaultValue) : $defaultValue;
            }
        }

        return $mapped;
    }

    private function resolveImportPath(string $path, string $disk = self::IMPORT_DISK): ?string
    {
        if ($path === '') {
            return null;
        }

        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->path($path);
        }

        $legacyPath = storage_path('app/'.$path);
        if (is_file($legacyPath)) {
            return $legacyPath;
        }

        return null;
    }

    private function detectDelimiter($handle): string
    {
        $firstLine = fgets($handle);
        if (! is_string($firstLine)) {
            rewind($handle);

            return ',';
        }

        rewind($handle);

        $candidates = [',', ';', "\t", '|'];
        $best = ',';
        $maxCount = -1;
        foreach ($candidates as $candidate) {
            $count = substr_count($firstLine, $candidate);
            if ($count > $maxCount) {
                $maxCount = $count;
                $best = $candidate;
            }
        }

        return $best;
    }

    private function normalizeCsvHeaders(array $headers): array
    {
        return array_map(function ($value) {
            $header = trim((string) $value);
            // Strip UTF-8 BOM from first/header values if present.
            $header = preg_replace('/^\xEF\xBB\xBF/u', '', $header) ?? $header;

            return $header;
        }, $headers);
    }

    private function findHeaderIndex(array $headers, string $columnName): int|false
    {
        $needle = strtolower(trim($columnName));
        foreach ($headers as $idx => $header) {
            if (strtolower(trim((string) $header)) === $needle) {
                return $idx;
            }
        }

        return false;
    }

    private function resolveColumnIndex(array $headers, string $columnReference): int|false
    {
        if (str_starts_with($columnReference, '__idx:')) {
            $indexText = substr($columnReference, 6);
            if ($indexText !== '' && ctype_digit($indexText)) {
                $index = (int) $indexText;
                if (array_key_exists($index, $headers)) {
                    return $index;
                }
            }
        }

        // Backward-compatible fallback for older mapping payloads that send header text.
        return $this->findHeaderIndex($headers, $columnReference);
    }

    private function assignNullableStringIfProvided(array &$payload, string $key, array $mapped): void
    {
        if (! array_key_exists($key, $mapped)) {
            return;
        }

        $payload[$key] = $this->nullableString($mapped[$key]);
    }

    private function assignBooleanIfProvided(array &$payload, string $key, array $mapped, bool $default): void
    {
        if (! array_key_exists($key, $mapped)) {
            return;
        }

        $payload[$key] = $this->toBoolean($mapped[$key], $default);
    }

    private function assignNullableFloatIfProvided(array &$payload, string $key, array $mapped): void
    {
        if (! array_key_exists($key, $mapped)) {
            return;
        }

        $payload[$key] = $this->toNullableFloat($mapped[$key]);
    }

    private function assignIntegerIfProvided(array &$payload, string $key, array $mapped): void
    {
        if (! array_key_exists($key, $mapped)) {
            return;
        }

        $payload[$key] = (int) ($mapped[$key] ?? 0);
    }
}

