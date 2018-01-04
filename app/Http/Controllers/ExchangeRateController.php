<?php

namespace App\Http\Controllers;

use AltAutoTrader\ExchangeRates\ExchangeRateRepositoryEloquent;
use AltAutoTrader\ExchangeRates\ExchangeRateRepositoryInterface;
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

    public function __construct(ExchangeRateRepositoryInterface $exchangeRateRepository)
    {
        $this->exchangeRateRepository = $exchangeRateRepository;
    }

    public function store(Exchange $exchange)
    {
        $api = $exchange->getProvider();

        $rates = $api->getExchangeRatesFromExchange();

        $return = [];
        foreach ($rates as $rate) {
            if (!$this->exchangeRateRepository->getExchangeRateByName($exchange, $rate->name)) {
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
        $exchangeRates = $this->exchangeRateRepository->getExchangeRates($exchange);

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

    public function track(Exchange $exchange, bool $convert = false)
    {
        $provider = $exchange->getProvider();

        /** @var Collection $exchangeRates */
        $exchangeRates = ExchangeRate::where('exchange_id', $exchange->id)
            ->where('counter_iso', $provider->getUsdIso())
            ->whereRaw('volume_24 * bid_rate > 100000')
            ->get();

        $best = [
            'pair' => null,
            'change' => 0,
        ];

        foreach($exchangeRates as $rate) {
            $change = $this->exchangeRateRepository->trackTrend($rate, 240);
            $min5change = $this->exchangeRateRepository->trackTrend($rate, 5);

            //magic thumb suck algorithm for spotting a climber*
            //* citation needed
            if ($change > $best['change'] && $min5change > 0.01) {
                $best['pair'] = $rate;
                $best['change'] = $change;
            }
        }

        //TODO remove hard coded thing here
        $best['pair'] = ExchangeRate::where('name', 'CVC/USD')->first();

        if($convert) {
            $order = $provider->convertHoldings($exchange, $best['pair']->base_iso);
            var_dump($order);
        }

        var_dump($best);
    }
}
