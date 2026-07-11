<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LineItem extends Model
{
    use HasFactory;

    protected $table = 'line_item';

    protected $fillable = [
        'invoiceId',
        'price',
        'units',
        'quantity',
        'description',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $appends = [
        'customerId',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoiceId');
    }

    public function getCustomerIdAttribute()
    {
        return $this->invoice?->customerId ?? 0;
    }
}
