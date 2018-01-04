<?php

namespace AltAutoTrader\Exchanges\Providers;

use App\Exchange;
use App\ExchangeRate;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;

class LivecoinExchangeProvider extends ExchangeProvider implements ExchangeProviderInterface
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
            $exchangeRate->volume_24 = $keyedRates[$exchangeRate->name]->volume;
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
            if($asset->type === 'available' && $asset->value > $max['balance']) {
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
        $heldAsset = $this->getHeldAsset();

        if($heldAsset['asset'] === $wantedIso) { //we already hold it
            return;
        }

        //First try a normal buy order with what we already have
        $rate = ExchangeRate::where('exchange_id', $exchange->id)
            ->where('base_iso', $wantedIso)
            ->where('counter_iso', $heldAsset['asset'])
            ->first();

        if ($rate) {
            return $this->placeOrder($rate, 'buy', $heldAsset['balance']);
        } else { //try invert it
            $rate = ExchangeRate::where('exchange_id', $exchange->id)
                ->where('base_iso', $heldAsset['asset'])
                ->where('counter_iso', $wantedIso)
                ->first();

            if($rate) {
                return $this->placeOrder($rate, 'sell', $heldAsset['balance']);
            }
        }

        if(!$rate) {
            $rate = ExchangeRate::where('exchange_id', $exchange->id)
                ->where('base_iso', $heldAsset['asset'])
                ->where('counter_iso', $this->getUsdIso())
                ->first();

            if(!$rate) {
                throw new \Exception('Unable to find rate for '.$heldAsset['asset'].':'.$this->getUsdIso().'');
            }

            return $this->placeOrder($rate, 'sell', $heldAsset['balance']);
        }
    }

    public function placeOrder(ExchangeRate $rate, $type, $amount)
    {
        $api = new Client([
            'base_uri' => 'https://api.livecoin.net',
        ]);

        $params = [
            'currencyPair' => $rate->name,
            'quantity' => $amount,
        ];
        $signature = strtoupper(hash_hmac('sha256', http_build_query($params, '', '&'), env('LIVECOIN_API_SECRET')));

        try {
            $options = [
                'headers' => [
                    'Api-Key' => env('LIVECOIN_API_KEY'),
                    'Sign' => $signature,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => $params,
            ];
            $order = $api->post('/exchange/'.$type.'market', $options);
            $order = json_decode($order->getBody()->getContents());
            return $order;
        }catch (\Exception $e) {
            throw $e;
        }
    }
}