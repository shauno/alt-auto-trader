<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int id
 * @property int exchange_id
 * @property string name
 * @property string base_iso
 * @property string counter_iso
 * @property float ask_rate
 * @property float bid_rate
 */
class ExchangeRate extends Model
{
    protected $fillable = [
        'exchange_id',
        'name',
        'base_iso',
        'counter_iso',
        'ask_rate',
        'bid_rate',
    ];

    /**
     * If true an exchange_rate_log record will be written on update of this ExchangeRate
     * @var bool
     */
    public $logHistory = false;

    public function exchange()
    {
        return $this->belongsTo(Exchange::class);
    }
}
