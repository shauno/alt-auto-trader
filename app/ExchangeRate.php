<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'name',
        'base_iso',
        'counter_iso',
        'rate',
    ];
}
