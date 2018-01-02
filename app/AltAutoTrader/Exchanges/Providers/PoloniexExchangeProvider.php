<?php

namespace AltAutoTrader\Exchanges\Providers;

use App\ExchangeRate;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;

class PoloniexExchangeProvider implements ExchangeProviderInterface
{
    /**
     * @inheritdoc
     */
    public function getExchangeRatesFromExchange() : Collection
    {
        $api = new Client([
            'base_uri' => 'https://poloniex.com',
        ]);

        try {
            $assets = $api->get('public?command=returnTicker');
            $assets = json_decode($assets->getBody()->getContents());
        }catch (\Exception $e) {
            return response('API call failed', 500);
        }

        $return = [];
        foreach ($assets as $asset => $details) {
            list($counterIso, $baseIso) = explode('_', $asset);
            $exchangeRate = new ExchangeRate();
            $exchangeRate->fill([
                'name' => $asset,
                'base_iso' => $baseIso,
                'counter_iso' => $counterIso,
                'ask_rate' => $details->lowestAsk,
                'bid_rate' => $details->highestBid,
            ]);

            $return[] = $exchangeRate;
        }

        return new Collection($return);
    }

    /**
     * @inheritdoc
     */
    public function getExchangeRatesTicker(Collection $exchangeRates) : Collection
    {
        $api = new Client([
            'base_uri' => 'https://poloniex.com',
        ]);

        try {
            $assets = $api->get('public?command=returnTicker');
            $rates = json_decode($assets->getBody()->getContents());
        }catch (\Exception $e) {
            return response('API call failed', 500);
        }

        foreach ($exchangeRates as $exchangeRate) {
            $exchangeRate->ask_rate = $rates->{$exchangeRate->name}->lowestAsk;
            $exchangeRate->bid_rate = $rates->{$exchangeRate->name}->highestBid;
            if ($exchangeRate->counter_iso === 'USDT') {
                $exchangeRate->logHistory = true;
            }
        }

        return $exchangeRates;
    }
}