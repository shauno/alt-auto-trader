<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function() {
    //Route::get('create', ['uses' => 'ExchangeController@store']);
    Route::resource('exchange.exchange-rates', 'ExchangeRateController', [
        'only' => ['index', 'store', 'update']
    ]);
    Route::get('exchange/{exchange}/exchange-rates/track/{convert?}', ['uses' => 'ExchangeRateController@track']);
    Route::get(
        'exchange/{exchange}/exchange-rates/history/{name}',
        ['uses' => 'ExchangeRateController@history'])
        ->where('name', '.*'); //Need this to allow forward slashes in the name (yes, even encoded slashes are not handled by default)
});
