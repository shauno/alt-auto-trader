<?php

namespace App\Http\Controllers;

use App\Exchange;
use App\ExchangeRate;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index(Exchange $exchange, Request $request)
    {
        $params = [];
        if ($request->has('exchange')) {
            $params = ['exchange' => $request->get('exchange')];

            $params['counter_iso'] = $request->has('counter_iso')
                ? $request->get('counter_iso')
                : null;

            return redirect(route('home', $params));
        }

        $counterIsos = [];
        if ($exchange->id) {
            $counterIsos = ExchangeRate::select('counter_iso')
                ->where('exchange_id', $exchange->id)
                ->groupBy('counter_iso')
                ->get();
        }

        $exchangeList = Exchange::all();

        return view('home')
            ->with([
                'exchanges' => $exchangeList,
                'selectedExchange' => $exchange,
                'counterIsos' => $counterIsos,
                'selectedCounterIso' => $request->get('counter_iso'),
            ]);
    }
}
