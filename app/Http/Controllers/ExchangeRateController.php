<?php

namespace App\Http\Controllers;

use AltAutoTrader\ExchangeRates\ExchangeRateRepositoryEloquent;
use AltAutoTrader\Lib\KrakenApi;
use AltAutoTrader\Lib\KrakenApiException;
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

    public function createExchangeRates()
    {
        $api = new KrakenApi(env('KRAKEN_API_KEY'), env('KRAKEN_API_SECRET'));

        try {
            $assets = $api->QueryPublic('AssetPairs');
        }catch (KrakenApiException $e) {
            return response('API call failed', 500);
        }

        if (!$assets['error']) {
            foreach ($assets['result'] as $asset => $details) {
                if (substr($asset, -2) != '.d') { //no idea what these pairs are, but they seem like duplicates
                    $exchangeRate = new \App\ExchangeRate();
                    $exchangeRate->fill([
                        'name' => $asset,
                        'base_iso' => $details['base'],
                        'counter_iso' => $details['quote'],
                        'ask_rate' => 0,
                        'bid_rate' => 0,
                    ]);

                    try {
                        $exchangeRate->save();
                    } catch (QueryException $e) {
                        //ignore it, just an integrity constraint meaning we already have the pair
                    }
                }
            }
        }
    }

    public function updateExchangeRates()
    {
        /** @var Collection $exchangeRates */
        $exchangeRates = (new ExchangeRate())->get();

        $pairs = $exchangeRates->map(function($item) {
            return $item->name;
        })->toArray();

        $api = new KrakenApi(env('KRAKEN_API_KEY'), env('KRAKEN_API_SECRET'));

        try {
            $rates = $api->QueryPublic('Ticker', ['pair' => implode(',', $pairs)]);
        }catch (KrakenApiException $e) {
            return response('API call failed', 500);
        }


        if (!$rates['error']) {
            foreach ($rates['result'] as $rate => $details) {
                //find correct rate
                $rate = (new ExchangeRate())
                        ->where('name', $rate)
                        ->first();

                //update the rate with the latest data
                $rate->ask_rate = $details['a'][0];
                $rate->bid_rate = $details['b'][0];
                $rate->save();

                if ($rate->counter_iso === 'ZUSD') { //create new historic entry only for rates against USD
                    (new ExchangeRateLog())->fill([
                        'exchange_rate_id' => $rate->id,
                        'ask_rate' => $details['a'][0],
                        'bid_rate' => $details['b'][0],
                    ])->save();
                }

            }
        }
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
