<?php

namespace AltAutoTrader\Exchanges;

use App\Exchange;

class ExchangeFactory
{
    public static function api(Exchange $exchange)
    {
        $className = '\\AltAutoTrader\\Exchanges\Providers\\'.ucfirst($exchange->driver).'ExchangeProvider';

        return new $className($exchange);
    }
}