<?php

namespace App\Http\Controllers;

use AltAutoTrader\ExchangeRates\ExchangeRateRepositoryEloquent;
use AltAutoTrader\Lib\KrakenApi;
use AltAutoTrader\Lib\KrakenApiException;
use App\Exchange;
use App\ExchangeRate;
use App\ExchangeRateLog;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ExchangeRateController extends Controller
{
    protected $exchangeRateRepository;

    public function __construct(ExchangeRateRepositoryEloquent $exchangeRateRepository)
    {
        $this->exchangeRateRepository = $exchangeRateRepository;
    }

    public function store(Exchange $exchange)
    {
        $api = $exchange->getProvider();

        $rates = $api->getExchangeRatesFromExchange();

        foreach ($rates as $rate) {
            $exists = ExchangeRate::where('exchange_id', $exchange->id)->where('name', $rate->name)->first();
            if (!$exists) {
                $rate->exchange_id = $exchange->id;
                $rate->save();
            }
        }
    }

    public function update(Exchange $exchange, string $rate)
    {
        if ($rate !== 'all') {
            return response('Only updating "all" is currently supported', 501);
        }

        /** @var Collection $exchangeRates */
        $exchangeRates = ExchangeRate::where('exchange_id', $exchange->id)->get();

        $api = $exchange->getProvider();

        $exchangeRates = $api->getExchangeRatesTicker($exchangeRates);

        $exchangeRates->each(function ($exchangeRate) {
            $exchangeRate->save();

            if($exchangeRate->logHistory) {
                (new ExchangeRateLog([
                    'exchange_rate_id' => $exchangeRate->id,
                    'ask_rate' => $exchangeRate->ask_rate,
                    'bid_rate' => $exchangeRate->bid_rate,
                ]))->save();
            }
        });

        return $exchangeRates;
    }

    public function trackExchangeRates()
    {
        /** @var Collection $exchangeRates */
        $exchangeRates = (new ExchangeRate())
            ->where('counter_iso', 'ZUSD')
            ->get();

        $best = [
            'name' => null,
            'change' => 0,
        ];

        foreach($exchangeRates as $rate) {
            $change = $this->exchangeRateRepository->trackTrend($rate);

            if ($change > $best['change']) {
                $best['name'] = $rate->name;
                $best['change'] = $change;
            }
        }

        var_dump($best);

        if ($best['name']) { //trade to this

        } else { //trade to USD
            
        }

    }
}
