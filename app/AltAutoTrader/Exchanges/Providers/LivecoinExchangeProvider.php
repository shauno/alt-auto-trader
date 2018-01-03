<?php

namespace AltAutoTrader\Exchanges\Providers;

use App\Exchange;
use App\ExchangeRate;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;

class LivecoinExchangeProvider implements ExchangeProviderInterface
{
    /**
     * @inheritdoc
     */
    public function getUsdIso()
    {
        return 'USD';
    }

    /**
     * @inheritdoc
     */
    public function getExchangeRatesFromExchange() : Collection
    {
        $api = new Client([
            'base_uri' => 'https://api.livecoin.net',
        ]);

        try {
            $assets = $api->get('/exchange/ticker');
            $assets = json_decode($assets->getBody()->getContents());
        }catch (\Exception $e) {
            return response('API call failed', 500);
        }

        $return = [];
        foreach ($assets as $details) {
            list($baseIso, $counterIso) = explode('/', $details->symbol);
            $exchangeRate = new ExchangeRate();
            $exchangeRate->fill([
                'name' => $details->symbol,
                'base_iso' => $baseIso,
                'counter_iso' => $counterIso,
                'ask_rate' => $details->best_ask,
                'bid_rate' => $details->best_bid,
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
            'base_uri' => 'https://api.livecoin.net',
        ]);

        try {
            $assets = $api->get('/exchange/ticker');
            $rates = json_decode($assets->getBody()->getContents());
        }catch (\Exception $e) {
            return response('API call failed', 500);
        }

        $keyedRates = [];
        foreach ($rates as $rate) {
            $keyedRates[$rate->symbol] = $rate;
        }

        foreach ($exchangeRates as $exchangeRate) {
            $exchangeRate->ask_rate = $keyedRates[$exchangeRate->name]->best_ask;
            $exchangeRate->bid_rate = $keyedRates[$exchangeRate->name]->best_bid;
            if ($exchangeRate->counter_iso === $this->getUsdIso()) {
                $exchangeRate->logHistory = true;
            }
        }

        return $exchangeRates;
    }

    public function getHeldAsset() : array
    {
        $api = new Client([
            'base_uri' => 'https://api.livecoin.net',
        ]);

        $signature = strtoupper(hash_hmac('sha256', '', env('LIVECOIN_API_SECRET')));

        try {
            $options = [
                'headers' => [
                    'Api-Key' => env('LIVECOIN_API_KEY'),
                    'Sign' => $signature,
                ],
            ];
            $balances = $api->get('/payment/balances', $options);
            $balances = json_decode($balances->getBody()->getContents());
        }catch (\Exception $e) {
            throw $e;
        }

        $max = [
            'asset' => null,
            'balance' => 0,
        ];

        foreach ($balances as $asset) {
            if($asset->type === 'trade' && $asset->value > $max['balance']) {
                $max = [
                    'asset' => $asset->currency,
                    'balance' => $asset->value,
                ];
            }
        }

        return $max;
    }

    public function convertHoldings(Exchange $exchange, string $wantedIso)
    {
        throw new \Exception('Still needs to be implemented for this provider');
    }
}