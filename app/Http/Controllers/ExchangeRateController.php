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

    public function index(Exchange $exchange, Request $request)
    {
        //TODO, implement a search in the repo
        $query = $exchange->exchangeRates();

        if($request->has('counter_iso')) {
            $query = $query->where('counter_iso', $request->get('counter_iso'));
        }

        if($request->has('volume')) {
            $query = $query->whereRaw('volume_24 * bid_rate > ?', $request->get('volume'));
        }

        return $query->get();
    }

    public function store(Exchange $exchange, TrackExchangeRatesService $trackExchangeRatesService)
    {
        return $trackExchangeRatesService->fetchExchangeRates($exchange);
    }

    public function update(Exchange $exchange, string $rate, TrackExchangeRatesService $trackExchangeRatesService)
    {
        if ($rate !== 'all') {
            return response('Only updating "all" is currently supported', 501);
        }

        return $trackExchangeRatesService->trackExchangeRates($exchange);
    }

    public function track(Exchange $exchange, bool $convert = false, Request $request)
    {
        $volumeLimit = $request->get('volume', 0);
        $provider = $exchange->getProvider();

        /** @var Collection $exchangeRates */
        $exchangeRates = ExchangeRate::where('exchange_id', $exchange->id)
            ->where('counter_iso', $provider->getUsdIso())
            ->whereRaw('volume_24 * bid_rate > ?', $volumeLimit)
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

        if($convert) {
            $order = $provider->convertHoldings($exchange, $best['pair']->base_iso);
            var_dump($order);
        }

        var_dump($best);
    }

    public function history(Exchange $exchange, string $name, Request $request)
    {
        if(!$exchangeRate = $this->exchangeRateRepository->getExchangeRateByName($exchange, $name)) {
            abort(404);
        }

        if (!$minBack = $request->get('min-back')) {
            $minBack = 60 * 24 * 7;
        }

        //TODO, implement this into the repo
        return ExchangeRateLog::where('exchange_rate_id', $exchangeRate->id)
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-'.$minBack.' minutes')))
            ->orderBy('created_at', 'asc')
            ->get();
    }
}
