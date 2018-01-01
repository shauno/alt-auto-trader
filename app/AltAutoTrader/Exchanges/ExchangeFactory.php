<?php

namespace AltAutoTrader\Exchanges;

class ExchangeFactory
{
    public static function api(string $driver)
    {
        $className = '\\AltAutoTrader\\Exchanges\Providers\\'.ucfirst($driver).'ExchangeProvider';

        return new $className;
    }
}