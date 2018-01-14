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
     * Fetches the list of possible exchange rates from the exchange provider
     *
     * @param Exchange $exchange
     * @return ExchangeRate[]|Collection
     */
    public function fetchExchangeRates(Exchange $exchange)
    {
        $provider = $exchange->getProvider();

        $exchangeRates = $provider->getExchangeRates();

        $exchangeRates = $exchangeRates->map(function (ExchangeRate $exchangeRate) use($exchange, $provider) {
            //If the exchange rate exists, update it instead of creating it
            if($exists = $this->exchangeRateRepository->getExchangeRateByName($exchange, $exchangeRate->name)) {
                $exists->fill($exchangeRate->toArray());
                $exchangeRate = $exists;
            }

            $this->exchangeRateRepository->saveExchangeRate($exchangeRate);

            return $exchangeRate;
        });

        return $exchangeRates;
    }

    /**
     * @param Exchange $exchange
     * @return ExchangeRate[]|Collection
     */
    public function trackExchangeRates(Exchange $exchange)
    {
        $provider = $exchange->getProvider();

        $exchangeRates = $this->exchangeRateRepository->getExchangeRates($exchange);

        $exchangeRates = $provider->getExchangeRatesTicker($exchangeRates);

        $exchangeRates = $exchangeRates->each(function (ExchangeRate $exchangeRate) {
            $this->exchangeRateRepository->saveExchangeRate($exchangeRate);
        });

        return $exchangeRates;
    }
}