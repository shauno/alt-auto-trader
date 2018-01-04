<?php

namespace AltAutoTrader\Exchanges\Providers;

use App\Exchange;

abstract class ExchangeProvider
{
    /** @var Exchange */
    protected $exchange;

    public function __construct(Exchange $exchange)
    {
        $this->exchange = $exchange;
    }
}