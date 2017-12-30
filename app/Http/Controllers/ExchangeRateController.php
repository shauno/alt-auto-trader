<?php

namespace App\Http\Controllers;

use AltAutoTrader\Lib\KrakenApi;
use AltAutoTrader\Lib\KrakenApiException;
use App\ExchangeRate;
use App\ExchangeRateLog;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ExchangeRateController extends Controller
{
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
                if ($details['quote'] === 'ZUSD' && substr($asset, -2) != '.d') {
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

                if ($rate) {
                    //update the rate with the latest data
                    $rate->ask_rate = $details['a'][0];
                    $rate->bid_rate = $details['b'][0];
                    $rate->save();

                    //create new historic entry
                    (new ExchangeRateLog())->fill([
                        'exchange_rate_id' => $rate->id,
                        'ask_rate' => $details['a'][0],
                        'bid_rate' => $details['b'][0],
                    ])->save();
                }
            }
        }
    }
}
