<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ExchangeRateLog extends Model
{
    protected $fillable = [
        'exchange_rate_id',
        'ask_rate',
        'bid_rate',
    ];
}
