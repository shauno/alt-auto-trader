<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/assets', function() {
    $api = new \AltAutoTrader\Lib\KrakenApi(env('KRAKEN_API_KEY'), env('KRAKEN_API_SECRET'));

    $assets = $api->QueryPublic('AssetPairs');

    if (!$assets['error']) {
        foreach ($assets['result'] as $asset => $details) {
            if ($details['quote'] === 'ZUSD' && substr($asset, -2) != '.d') {
                $exchangeRate = new \App\ExchangeRate();
                $exchangeRate->fill([
                    'name' => $asset,
                    'base_iso' => $details['base'],
                    'counter_iso' => $details['quote'],
                    'rate' => 0,
                ]);

                try {
                    $exchangeRate->save();
                } catch (\Illuminate\Database\QueryException $e) {
                    //ignore it, just an integrity constraint
                }
            }
        }
    }
});
