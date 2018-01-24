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

        foreach($exchangeRates as $rate) {
            $change = [
                '3_2_hours' => $this->exchangeRateRepository->trackTrend($rate, time()-(3*60*60), time()-(2*60*60)),
                '2_1_hours' => $this->exchangeRateRepository->trackTrend($rate, time()-(2*60*60), time()-(1*60*60)),
                '1_0_hours' => $this->exchangeRateRepository->trackTrend($rate, time()-(1*60*60), time()),
                '3_0_hours' => $this->exchangeRateRepository->trackTrend($rate, time()-(3*60*60), time()),
                '5_0_min' => $this->exchangeRateRepository->trackTrend($rate, time()-(5*60), time()),
            ];

            //magic thumb suck algorithm for spotting a climber*
            //* citation needed
            if(
                $change['3_2_hours'] > 0
                && $change['2_1_hours'] > 0
                && $change['1_0_hours'] > 0
                && $change['3_0_hours'] > 0
                && $change['5_0_min'] > 0
            ) {
                if($change['3_0_hours'] > $best['change']) {
                    $best['pair'] = $rate;
                    $best['change'] = $change[3];
                }
            }

            var_dump($rate->name);
            var_dump($change);
            echo '<hr />';
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
        /** @var Collection $data */
        $data = ExchangeRateLog::where('exchange_rate_id', $exchangeRate->id)
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-'.$minBack.' minutes')))
            ->orderBy('created_at', 'asc')
            ->get();


        return [
            'rates' => $data,
            'extra' => [
                '3_2_hours' => $this->exchangeRateRepository->trackTrend($exchangeRate, time()-(3*60*60), time()-(2*60*60)),
                '2_1_hours' => $this->exchangeRateRepository->trackTrend($exchangeRate, time()-(2*60*60), time()-(1*60*60)),
                '1_0_hours' => $this->exchangeRateRepository->trackTrend($exchangeRate, time()-(1*60*60), time()),
                '3_0_hours' => $this->exchangeRateRepository->trackTrend($exchangeRate, time()-(3*60*60), time()),
                '5_0_min' => $this->exchangeRateRepository->trackTrend($exchangeRate, time()-(5*60), time()),
            ],

        ];
    }
}
