<?php

namespace AltAutoTrader\ExchangeRates;

use App\Exchange;
use App\ExchangeRate;
use App\ExchangeRateLog;
use Illuminate\Support\Collection;

class EloquentExchangeRateRepository implements ExchangeRateRepositoryInterface
{
    /**
     * @inheritdoc
     */
    public function getExchangeRates(Exchange $exchange): Collection
    {
        return $exchange->exchangeRates()->get();
    }

    /**
     * @inheritdoc
     */
    public function getExchangeRateByName(Exchange $exchange, string $name) : ?ExchangeRate
    {
        return $exchange->exchangeRates()->where('exchange_rates.name', $name)->first();
    }

    /**
     * @inheritdoc
     */
    public function trackTrend(ExchangeRate $exchangeRate, $minutesBack = 120) : float
    {
        /** @var Collection $list */
        $list = (new ExchangeRateLog())
            ->where('exchange_rate_id', $exchangeRate->id)
            ->where('created_at', '>', date('Y-m-d H:i:s', strtotime('-'.$minutesBack.' minutes')))
            ->orderBy('created_at', 'asc')
            ->get();

        //Should be false (null)?
        if (!$list->count()) {
            return 0;
        }

        return ($list->last()->bid_rate - $list->first()->bid_rate) / $list->first()->bid_rate;
    }
}