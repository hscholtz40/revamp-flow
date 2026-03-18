<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'subject',
        'html_template',
        'css_styles',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public static function variableGroups(): array
    {
        return [
            'Company' => [
                '{{company.name}}',
                '{{company.legal_name}}',
                '{{company.email}}',
                '{{company.phone}}',
                '{{company.website}}',
                '{{company.address}}',
                '{{company.city}}',
                '{{company.country}}',
                '{{company.vat_number}}',
            ],
            'Customer' => [
                '{{customer.name}}',
                '{{customer.email}}',
                '{{customer.phone}}',
                '{{customer.account_code}}',
                '{{customer.address}}',
                '{{customer.city}}',
                '{{customer.country}}',
                '{{customer.vat_number}}',
                '{{customer.terms}}',
            ],
            'Contact' => [
                '{{contact.name}}',
                '{{contact.email}}',
                '{{contact.phone}}',
                '{{contact.position}}',
                '{{contact.is_primary}}',
            ],
            'User' => [
                '{{user.name}}',
                '{{user.email}}',
            ],
            'Dates' => [
                '{{date.today}}',
                '{{date.now}}',
            ],
        ];
    }
}

