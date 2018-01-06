<?php

namespace App\Http\Controllers;

use AltAutoTrader\ExchangeRates\ExchangeRateRepositoryEloquent;
use AltAutoTrader\ExchangeRates\ExchangeRateRepositoryInterface;
use AltAutoTrader\ExchangeRates\TrackExchangeRatesService;
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

    public function update(Exchange $exchange, string $rate, TrackExchangeRatesService $trackExchangeRatesService)
    {
        if ($rate !== 'all') {
            return response('Only updating "all" is currently supported', 501);
        }

        return $trackExchangeRatesService->trackExchangeRates($exchange);
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
