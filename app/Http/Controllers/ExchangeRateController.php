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

        $return = [];
        foreach ($rates as $rate) {
            $exists = ExchangeRate::where('exchange_id', $exchange->id)->where('name', $rate->name)->first();
            if (!$exists) {
                $rate->exchange_id = $exchange->id;
                $rate->save();
                $return[] = $rate;
            }
        }

        return $return;
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

    public function track(Exchange $exchange)
    {
        $provider = $exchange->getProvider();

        /** @var Collection $exchangeRates */
        $exchangeRates = ExchangeRate::where('exchange_id', $exchange->id)
            ->where('counter_iso', $provider->getUsdIso())
            ->get();

        $best = [
            'pair' => null,
            'change' => 0,
        ];

        foreach($exchangeRates as $rate) {
            $change = $this->exchangeRateRepository->trackTrend($rate);
            $min5change = $this->exchangeRateRepository->trackTrend($rate, 5);

            //We want the best climber that isn't losing ground over the last 5 min
            if ($change > $best['change'] && $min5change > 0) {
                $best['pair'] = $rate;
                $best['change'] = $change;
            }
        }

        //TODO remove hard coded thing here
        //$best['pair'] = ExchangeRate::where('name', 'XETHZUSD')->first();

        //$order = $provider->convertHoldings($exchange, $best['pair']->base_iso);
        //var_dump($order);

        var_dump($best);
    }

    protected function findPathToAsset(Exchange $exchange, $wantedIso, $heldIso)
    {
        $steps = [];

        //First try a normal buy order with what we already have
        $tradeRate = ExchangeRate::where('exchange_id', $exchange->id)
            ->where('base_iso', $wantedIso)
            ->where('counter_iso', $heldIso)
            ->first();

        if($tradeRate) {
            $step[] = [
                'rate' => $tradeRate,
                'type' => 'buy'
            ];
        } else { //Is the inverse available, then we can sell
            $tradeRate = ExchangeRate::where('exchange_id', $exchange->id)
                ->where('base_iso', $heldIso)
                ->where('counter_iso', $wantedIso)
                ->first();

            if($tradeRate) {
                $step[] = [
                    'rate' => $tradeRate,
                    'type' => 'sell'
                ];
            }
        }

        //no path, we're going to need to go through USD
        if (!$steps) {
            $tradeRate = ExchangeRate::where('exchange_id', $exchange->id)
                ->where('base_iso', $heldIso)
                ->where('counter_iso', $exchange->getProvider()->getUsdIso())
                ->first();

            $steps[] = [
                'rate' => $tradeRate,
                'type' => 'sell'
            ];

//            $tradeRate = ExchangeRate::where('exchange_id', $exchange->id)
//                ->where('base_iso', $wantedIso)
//                ->where('counter_iso', $exchange->getProvider()->getUsdIso())
//                ->first();
//
//            $steps[] = [
//                'rate' => $tradeRate,
//                'type' => 'buy'
//            ];
        }

        return $steps;
    }
}
