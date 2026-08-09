<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $table = 'customer';

    protected $fillable = [
        'user_id',
        'name',
        'bAddress1',
        'bAddress2',
        'bCity',
        'bState',
        'bZip',
        'sAddress1',
        'sAddress2',
        'sCity',
        'sState',
        'sZip',
        'phoneMain',
        'fax',
        'billingContact',
        'billingEmail',
        'billingPhone',
        'primaryContact',
        'primaryEmail',
        'primaryPhone',
        'taxable',
        'archive',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
