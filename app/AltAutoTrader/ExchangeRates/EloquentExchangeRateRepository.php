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
    public function trackTrend(ExchangeRate $exchangeRate, int $start, int $end) : float
    {
        $first = (new ExchangeRateLog())
            ->select('bid_rate')
            ->where('exchange_rate_id', $exchangeRate->id)
            ->where('created_at', '>=', date('Y-m-d H:i:s', $start))
            ->where('created_at', '<=', date('Y-m-d H:i:s', $end))
            ->orderBy('created_at', 'asc')
            ->limit(1)
            ->first();

        $last = (new ExchangeRateLog())
            ->select('bid_rate')
            ->where('exchange_rate_id', $exchangeRate->id)
            ->where('created_at', '>=', date('Y-m-d H:i:s', $start))
            ->where('created_at', '<=', date('Y-m-d H:i:s', $end))
            ->orderBy('created_at', 'desc')
            ->limit(1)
            ->first();

        if(!$first || !$last) {
            return 0;
        }

        return ($last->bid_rate - $first->bid_rate) / $first->bid_rate;
    }

    /**
     * @inheritdoc
     */
    public function saveExchangeRate(ExchangeRate $exchangeRate) : ExchangeRate
    {
        $exchangeRate->save();

        if ($exchangeRate->logHistory) {
            $this->saveExchangeRateHistory($exchangeRate);
        }

        return $exchangeRate;
    }

    private function saveExchangeRateHistory(ExchangeRate $exchangeRate)
    {
        $exchangeRateLog = (new ExchangeRateLog([
            'exchange_rate_id' => $exchangeRate->id,
            'ask_rate' => $exchangeRate->ask_rate,
            'bid_rate' => $exchangeRate->bid_rate,
        ]));

        $exchangeRateLog->save();

        return $exchangeRateLog;
    }
}