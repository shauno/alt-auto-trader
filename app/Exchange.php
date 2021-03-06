<?php

namespace App;

use AltAutoTrader\Exchanges\ExchangeFactory;
use AltAutoTrader\Exchanges\Providers\ExchangeProviderInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int id
 * @property string name
 * @property string slug
 * @property string api_base_url
 * @property string driver
 */
class Exchange extends Model
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function exchangeRates()
    {
        return $this->hasMany(ExchangeRate::class);
    }

    /**
     * @return ExchangeProviderInterface
     */
    public function getProvider()
    {
        return ExchangeFactory::api($this);
    }
}
