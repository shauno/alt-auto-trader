<?php

namespace AltAutoTrader\Exchanges\Providers;

use App\ExchangeRate;
use Illuminate\Support\Collection;

interface ExchangeProviderInterface
{
    /**
     * @return Collection|ExchangeRate[]
     */
    public function getExchangeRatesFromExchange() : Collection;

    /**
     * @param Collection|ExchangeRate[] $exchangeRates
     * @return Collection|ExchangeRate[]
     */
    public function getExchangeRatesTicker(Collection $exchangeRates) : Collection;
}