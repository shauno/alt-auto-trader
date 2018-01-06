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
    public function getExchangeRatesTicker() : Collection
    {
        $api = new Client([
            'base_uri' => 'https://api.livecoin.net',
        ]);

        try {
            $assets = $api->get('/exchange/ticker');
            $newRates = json_decode($assets->getBody()->getContents());
        }catch (\Exception $e) {
            return response('API call failed', 500);
        }

        $exchangeRates = [];
        foreach ($newRates as $exchangeRate) {
            list($baseIso, $counterIso) = explode('/', $exchangeRate->symbol);
            $model = new ExchangeRate([
                'exchange_id' => $this->exchange->id,
                'name' => $exchangeRate->symbol,
                'base_iso' => $baseIso,
                'counter_iso' => $counterIso,
                'ask_rate' => $exchangeRate->best_ask,
                'bid_rate' => $exchangeRate->best_bid,
                'volume_24' => $exchangeRate->volume,
            ]);
            if ($model->counter_iso === $this->getUsdIso()) {
                $model->logHistory = true;
            }

            $exchangeRates[] = $model;
        }

        return new Collection($exchangeRates);
    }

    /**
     * @inheritdoc
     */
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

    /**
     * @inheritdoc
     */
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

    /**
     * @param ExchangeRate $rate
     * @param string $type "buy" or "sell"
     * @param float $amount
     * @return array|\stdClass
     * @throws \Exception
     */
    public function placeOrder(ExchangeRate $rate, $type, float $amount)
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