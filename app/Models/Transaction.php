<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'payment_system',
        'amount',
        'currency',
        'status',
        'response',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Establecer un valor predeterminado para la columna pay_method
        $this->attributes['pay_method'] = 'default_payment_system';
    }
}