<?php

namespace AltAutoTrader\Exchanges\Providers;

use AltAutoTrader\Lib\KrakenApi;
use AltAutoTrader\Lib\KrakenApiException;
use App\Exchange;
use App\ExchangeRate;
use Illuminate\Support\Collection;

class KrakenExchangeProvider implements ExchangeProviderInterface
{
    /**
     * @inheritdoc
     */
    public function getUsdIso()
    {
        return 'ZUSD';
    }

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

    public function getHeldAsset() : array
    {
        $api = new KrakenApi(env('KRAKEN_API_KEY'), env('KRAKEN_API_SECRET'));

        $balances = $api->QueryPrivate('Balance');

        $max = [
            'asset' => null,
            'balance' => 0,
        ];
        foreach ($balances['result'] as $asset => $balance) {
            if($balance > $max['balance']) {
                $max = [
                    'asset' => $asset,
                    'balance' => $balance,
                ];
            }
        }

        return $max;
    }

    public function convertHoldings(Exchange $exchange, string $wantedIso)
    {
        $api = new KrakenApi(env('KRAKEN_API_KEY'), env('KRAKEN_API_SECRET'));

        //$heldAsset = 'BCH';
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



//        $order = $api->QueryPrivate('AddOrder', [
//            'pair' => $exchangeRate->name,
//            'type' => $type,
//        ]);
//
//        var_dump($order);
    }

    public function placeOrder(ExchangeRate $rate, $type, $amount)
    {
        $api = new KrakenApi(env('KRAKEN_API_KEY'), env('KRAKEN_API_SECRET'));

        $params = [
            'pair' => $rate->name,
            'type' => $type,
            'ordertype' => 'market',
            'volume' => $amount,
        ];

        if($type == 'buy') {
            $params['oflags'] = 'viqc';
        }

        return $api->QueryPrivate('AddOrder', $params);
    }

}