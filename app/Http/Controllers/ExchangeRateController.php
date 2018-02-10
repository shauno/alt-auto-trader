<?php

namespace App\Http\Controllers;

use AltAutoTrader\ExchangeRates\ExchangeRateRepositoryEloquent;
use AltAutoTrader\ExchangeRates\ExchangeRateRepositoryInterface;
use AltAutoTrader\ExchangeRates\TrackExchangeRatesService;
use App\Exchange;
use App\ExchangeRate;
use App\ExchangeRateLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use MathPHP\Statistics\Regression\Linear;

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

        $change = [];
        foreach($exchangeRates as $rate) {
            $change[$rate->name] = $this->exchangeRateRepository->trendData($rate);

            $positive = true;

            foreach($change[$rate->name] as $period) {
                if($period <= 0) {
                    $positive = false;
                    break;
                }
            }

            if($positive) {
                if(array_values($change[$rate->name])[0] > $best['change']) {
                    $best['pair'] = $rate;
                    $best['change'] = array_values($change[$rate->name])[0];
                }
            }

        }

        if($convert) {
            if($best['change'] >= 0.02) {
                $order = $provider->convertHoldings($exchange, $best['pair']->base_iso);
                var_dump($order);
            } else { //nothing is up well, should we retreat to USD?
                $heldAsset = $provider->getHeldAsset();

                //get the rate pair for what we have against USD
                $rate = ExchangeRate::where('base_iso', $heldAsset['asset'])
                    ->where('counter_iso', $provider->getUsdIso())
                    ->first();

                //if the asset we hold is down too much against USD, bitch out
                if($rate && array_pop($change[$rate->name]) < -0.02) {
                    $order = $provider->convertHoldings($exchange, $provider->getUsdIso());
                    var_dump($order);
                }
            }
        }

        echo '<pre>';
        var_dump($change);
        echo '<hr />';
        var_dump($best);
        echo '</pre>';
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
        /** @var Collection $data */
        $data = ExchangeRateLog::where('exchange_rate_id', $exchangeRate->id)
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-'.$minBack.' minutes')))
            ->orderBy('created_at', 'asc')
            ->get();


        return [
            'rates' => $data,
            'extra' => $this->exchangeRateRepository->trendData($exchangeRate),
        ];
    }
}
