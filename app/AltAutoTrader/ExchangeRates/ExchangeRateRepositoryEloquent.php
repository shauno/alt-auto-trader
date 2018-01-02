<?php

namespace AltAutoTrader\ExchangeRates;

use App\ExchangeRate;
use App\ExchangeRateLog;

class ExchangeRateRepositoryEloquent
{
    /**
     * Calculate the currency trend as percent change for this asset
     *
     * @param ExchangeRate $exchangeRate
     * @param int $minutesBack
     * @return float
     */
    public function trackTrend(ExchangeRate $exchangeRate, $minutesBack = 120) : float
    {
        $list = (new ExchangeRateLog())
            ->where('exchange_rate_id', $exchangeRate->id)
            ->where('created_at', '>', date('Y-m-d H:i:s', strtotime('-'.$minutesBack.' minutes')))
            ->orderBy('created_at', 'asc')
            ->get();

        return ($list->last()->bid_rate - $list->first()->bid_rate) / $list->first()->bid_rate;
    }
}