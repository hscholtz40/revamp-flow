<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SMSActivity extends Model
{
    use HasFactory;

    protected $table = 'sms_activities';

    protected $fillable = [
        'customer_id',
        'user_id',
        'company_id',
        'phone_number',
        'message',
        'status',
        'error_message',
        'bulksms_reference',
        'bulksms_response',
    ];

    protected $casts = [
        'bulksms_response' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
