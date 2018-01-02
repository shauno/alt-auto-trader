<?php

namespace AltAutoTrader\Exchanges\Providers;

use App\ExchangeRate;
use Illuminate\Support\Collection;

interface ExchangeProviderInterface
{
    /**
     * Different exchanges used different ISOs to represent USD
     *
     * @return string
     */
    public function getUsdIso();

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