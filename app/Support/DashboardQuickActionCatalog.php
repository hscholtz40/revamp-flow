<?php

namespace App\Support;

class DashboardQuickActionCatalog
{
    /**
     * @return array<string, array{label: string, group: string, default: bool}>
     */
    public static function definitions(): array
    {
        return [
            'quote' => [
                'label' => 'New Quote',
                'group' => 'documents',
                'default' => true,
            ],
            'invoice' => [
                'label' => 'New Invoice',
                'group' => 'documents',
                'default' => true,
            ],
            'pos' => [
                'label' => 'POS',
                'group' => 'documents',
                'default' => true,
            ],
            'jobcard' => [
                'label' => 'New Jobcard',
                'group' => 'documents',
                'default' => true,
            ],
            'customer' => [
                'label' => 'Quick Create Customer',
                'group' => 'catalog',
                'default' => true,
            ],
            'supplier' => [
                'label' => 'Quick Create Supplier',
                'group' => 'catalog',
                'default' => true,
            ],
            'category' => [
                'label' => 'Quick Create Category',
                'group' => 'catalog',
                'default' => true,
            ],
            'product' => [
                'label' => 'Quick Create Product',
                'group' => 'catalog',
                'default' => true,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        return array_map(
            static fn (array $definition): bool => $definition['default'],
            self::definitions(),
        );
    }

    /**
     * @return array<string, bool>
     */
    public static function resolve(?array $stored): array
    {
        $resolved = self::defaults();

        if ($stored === null) {
            return $resolved;
        }

        foreach (array_keys($resolved) as $key) {
            if (array_key_exists($key, $stored)) {
                $resolved[$key] = (bool) $stored[$key];
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, bool>|null  $input
     * @return array<string, bool>|null
     */
    public static function normalizeForStorage(?array $input): ?array
    {
        if ($input === null) {
            return null;
        }

        $resolved = self::resolve($input);

        return $resolved === self::defaults() ? null : $resolved;
    }

    /**
     * @return list<array{key: string, label: string, group: string}>
     */
    public static function optionsForUi(): array
    {
        return array_map(
            static fn (string $key, array $definition): array => [
                'key' => $key,
                'label' => $definition['label'],
                'group' => $definition['group'],
            ],
            array_keys(self::definitions()),
            self::definitions(),
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function validationRules(string $prefix = 'dashboard_quick_actions'): array
    {
        $rules = [
            $prefix => ['nullable', 'array'],
        ];

        foreach (self::keys() as $key) {
            $rules["{$prefix}.{$key}"] = ['boolean'];
        }

        return $rules;
    }
}
