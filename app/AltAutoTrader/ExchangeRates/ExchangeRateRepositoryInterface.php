<?php

namespace AltAutoTrader\ExchangeRates;

use App\Exchange;
use App\ExchangeRate;
use Illuminate\Support\Collection;

interface ExchangeRateRepositoryInterface
{
    /**
     * @param Exchange $exchange
     * @return Collection
     */
    public function getExchangeRates(Exchange $exchange) : Collection;

    /**
     * @param Exchange $exchange
     * @param string $name
     * @return ExchangeRate|null
     */
    public function getExchangeRateByName(Exchange $exchange, string $name) : ?ExchangeRate;

    /**
     * Calculate the currency trend as percent change for this asset
     *
     * @param ExchangeRate $exchangeRate
     * @param int $minutesBack
     * @return float
     */
    public function trackTrend(ExchangeRate $exchangeRate, $minutesBack = 120) : float;

    /**
     * @param ExchangeRate $exchangeRate
     * @return ExchangeRate
     */
    public function saveExchangeRate(ExchangeRate $exchangeRate) : ExchangeRate;
}