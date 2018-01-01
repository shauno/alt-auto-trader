<?php

namespace AltAutoTrader\Exchanges\Providers;

use AltAutoTrader\Lib\KrakenApi;
use AltAutoTrader\Lib\KrakenApiException;
use App\ExchangeRate;
use Illuminate\Support\Collection;

class KrakenExchangeProvider implements ExchangeProviderInterface
{
    /**
     * @inheritdoc
     */
    public function getExchangeRatesFromExchange() : Collection
    {
        $api = new KrakenApi(env('KRAKEN_API_KEY'), env('KRAKEN_API_SECRET'));

        try {
            $assets = $api->QueryPublic('AssetPairs');
        }catch (KrakenApiException $e) {
            return response('API call failed', 500);
        }

        $return = [];
        if (!$assets['error']) {
            foreach ($assets['result'] as $asset => $details) {
                if (substr($asset, -2) != '.d') { //no idea what these pairs are, but they seem like duplicates
                    $exchangeRate = new ExchangeRate();
                    $exchangeRate->fill([
                        'name' => $asset,
                        'base_iso' => $details['base'],
                        'counter_iso' => $details['quote'],
                        'ask_rate' => 0,
                        'bid_rate' => 0,
                    ]);

                    $return[] = $exchangeRate;
                }
            }
        }

        return new Collection($return);
    }

    /**
     * @inheritdoc
     */
    public function getExchangeRatesTicker(Collection $exchangeRates) : Collection
    {
        $api = new KrakenApi(env('KRAKEN_API_KEY'), env('KRAKEN_API_SECRET'));

        $pairs = $exchangeRates->map(function($item) {
            return $item->name;
        })->toArray();

        try {
            $rates = $api->QueryPublic('Ticker', ['pair' => implode(',', $pairs)]);
        }catch (KrakenApiException $e) {
            return response('API call failed', 500);
        }

        if (!$rates['error']) {
            foreach ($exchangeRates as $exchangeRate) {
                $exchangeRate->ask_rate = $rates['result'][$exchangeRate->name]['a'][0];
                $exchangeRate->bid_rate = $rates['result'][$exchangeRate->name]['b'][0];
                if ($exchangeRate->counter_iso === 'ZUSD') {
                    $exchangeRate->logHistory = true;
                }
            }
        }

        return $exchangeRates;
    }
}