<!doctype html>
<html lang="">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <title></title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script src="/js/jquery.min.js"></script>
    <script src="/js/jquery.flot.min.js"></script>
    <script src="/js/jquery.flot.time.min.js"></script>
    <script src="/js/jquery.flot.selection.min.js"></script>
    <script src="/js/regression.min.js"></script>
    <script src="/js/graphs.js"></script>
</head>
<body>

<input type="hidden" id="selectedExchange" value="{{$selectedExchange->slug}}" />
<input type="hidden" id="selectedCounterIso" value="{{$selectedCounterIso}}" />

<form method="get">
    <label>Exchange</label>
    <select name="exchange">
        <option>Select</option>
        @foreach($exchanges as $exchange)
            <option value="{{$exchange->slug}}" @if($exchange->slug == $selectedExchange->slug) selected="selected" @endif>{{$exchange->name}}</option>
        @endforeach
    </select>
    <br />

    @if($counterIsos)
        <label>Counter ISO</label>

        <select name="counter_iso">
            <option>Select</option>
            @foreach($counterIsos as $iso)
                <option value="{{$iso->counter_iso}}" @if($selectedCounterIso == $iso->counter_iso) selected="selected" @endif>{{$iso->counter_iso}}</option>
            @endforeach
        </select>
        <br />
    @endif

    <input type="submit" value="Change" />
</form>
<hr />

<div id="graph-container"></div>


</body>
</html>
