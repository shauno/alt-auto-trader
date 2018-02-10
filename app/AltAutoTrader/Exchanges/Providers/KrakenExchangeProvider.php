<?php

namespace AltAutoTrader\Exchanges\Providers;

use AltAutoTrader\Lib\KrakenApi;
use AltAutoTrader\Lib\KrakenApiException;
use App\Exchange;
use App\ExchangeRate;
use Illuminate\Support\Collection;

class KrakenExchangeProvider extends ExchangeProvider implements ExchangeProviderInterface
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
    public function getExchangeRates() : Collection
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
                if (substr($asset, -2) != '.d' && $asset != 'USDTZUSD') { //no idea what these pairs are, but they seem like duplicates
                    $exchangeRate = new ExchangeRate();
                    $exchangeRate->fill([
                        'exchange_id' => $this->exchange->id,
                        'name' => $asset,
                        'base_iso' => $details['base'],
                        'counter_iso' => $details['quote'],
                        'ask_rate' => 0,
                        'bid_rate' => 0,
                        'volume' => 0,
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
                $exchangeRate->volume_24 = $rates['result'][$exchangeRate->name]['v'][1];
                $exchangeRate->logHistory = true;
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
        $api = new KrakenApi(env('KRAKEN_API_KEY'), env('KRAKEN_API_SECRET'));

        $params = [
            'pair' => $rate->name,
            'type' => $type,
            'ordertype' => 'market',
        ];

        if($type == 'buy') {
            /* Calculate how much volume (required field) we can buy with our currency asset.
             * For some reason Kraken stopped supporting the viqc flag which would have this unnecessary
             * Obviously the order book can change during the calls latency, so who knows how well this will work :|
             */

            $orderBook = $api->QueryPublic('Depth', [
                'pair' => $rate->name,
            ]);

            $price = 0;
            $totalAmount = 0;

            foreach($orderBook['result'][$rate->name]['asks'] as $ask) {
                $totalAmount += $ask[0] * $ask[1];
                if($totalAmount >= $amount) {
                    $price = $ask[0];
                    break;
                }
            }

            $params['volume'] = $amount / $price;

        } else {
            $params['volume'] = $amount;
        }

        return $api->QueryPrivate('AddOrder', $params);
    }

}