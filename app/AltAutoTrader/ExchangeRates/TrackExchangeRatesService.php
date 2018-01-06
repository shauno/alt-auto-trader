<?php

namespace AltAutoTrader\ExchangeRates;

use App\Exchange;
use App\ExchangeRate;
use Illuminate\Support\Collection;

class TrackExchangeRatesService
{
    /** @var ExchangeRateRepositoryInterface */
    protected $exchangeRateRepository;

    /**
     * @param ExchangeRateRepositoryInterface $exchangeRateRepository
     */
    public function __construct(ExchangeRateRepositoryInterface $exchangeRateRepository)
    {
        $this->exchangeRateRepository = $exchangeRateRepository;
    }

    /**
     * @param Exchange $exchange
     * @return ExchangeRate[]|Collection
     */
    public function trackExchangeRates(Exchange $exchange)
    {
        $provider = $exchange->getProvider();

        $exchangeRates = $provider->getExchangeRatesTicker();

        $exchangeRates = $exchangeRates->map(function (ExchangeRate $exchangeRate) use($exchange, $provider) {
            //If the exchange rate exists, update it instead of creating it
            if($exists = $this->exchangeRateRepository->getExchangeRateByName($exchange, $exchangeRate->name)) {
                $logHistory = $exchangeRate->logHistory;
                $exists->fill($exchangeRate->toArray());
                $exchangeRate = $exists;
                $exchangeRate->logHistory = $logHistory;
            }

            $this->exchangeRateRepository->saveExchangeRate($exchangeRate);

            return $exchangeRate;
        });

        return $exchangeRates;
    }
}