<?php

namespace AltAutoTrader\Exchanges\Providers;

use App\Exchange;
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
    public function getExchangeRates() : Collection;

    /**
     * @param Collection|ExchangeRate[] $exchangeRates
     * @return Collection|ExchangeRate[]
     */
    public function getExchangeRatesTicker(Collection $exchangeRates) : Collection;

    /**
     * @return array
     */
    public function getHeldAsset() : array;

    /**
     * @param Exchange $exchange
     * @param string $wantedIso
     * @return mixed
     */
    public function convertHoldings(Exchange $exchange, string $wantedIso);
}
