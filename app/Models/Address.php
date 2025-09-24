<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Address extends Model
{
    protected $fillable = [
        'street',
        'city',
        'state',
        'zip_code',
    ];
}
