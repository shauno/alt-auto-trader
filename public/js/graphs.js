jQuery(document).ready(function() {
    var exchange = jQuery('#selectedExchange').val();
    var counterIso = jQuery('#selectedCounterIso').val();

    if(!exchange || !counterIso) {
        return;
    }

    jQuery.ajax({
        method: 'get',
        url: '/api/v1/exchange/'+exchange+'/exchange-rates?counter_iso='+counterIso+'&volume=0',
        success: function(data) {
            for(i in data) {
                drawLog(exchange, data[i].name);
            }
        }
    });
});

var graphs = {};
function drawLog(exchange, name) {
    jQuery.ajax({
        method: 'get',
        url: '/api/v1/exchange/'+exchange+'/exchange-rates/history/'+name+'?min-back=480',
        success: function(data) {
            var graphdata = [];
            var trendData = [];
            for(i in data.rates) {
                givenDate = new Date(data.rates[i].created_at);
                correctedDate = new Date(Date.UTC(
                    givenDate.getFullYear(),
                    givenDate.getMonth(),
                    givenDate.getDate(),
                    givenDate.getHours(),
                    givenDate.getMinutes() - givenDate.getTimezoneOffset(),
                    givenDate.getSeconds()
                ));
                graphdata.push([correctedDate, parseFloat(data.rates[i].bid_rate)]);
            }
            trendData = data.extra;

            console.log(trendData);

            graphdata = [
                {
                    label: name,
                    data: graphdata
                }
            ];

            name = name.replace('/', '-').toLowerCase();

            var html = '' +
                '<div id="log-'+name+'" style="display: inline-block;">' +
                '   <div class="graph" style="width: 600px; height: 100px; display: block;"><img src="/assets/coin-loader.gif" /></div>' +
                '   <div class="data"><pre>'+JSON.stringify(trendData, null, 2)+'</pre></div>' +
                '</div>';
            jQuery('#graph-container').append(html);

            graphs[name] = jQuery("#log-"+name+" .graph").plot(
                graphdata,
                {
                    xaxis: { mode: "time"},
                    legend: { position: "nw" },
                    selection: { mode: "x" }
                }
            ).data("plot");

            jQuery("#log-"+name).bind("plotselected", function (event, ranges) {
                var allData = graphs[name].getData()[0].data;
                var selectedData = [];
                var normalize = null;
                for(var i = 0; i < allData.length; i++) {
                    if(allData[i][0].getTime() >= ranges.xaxis.from && allData[i][0].getTime() <= ranges.xaxis.to) {
                        if(!normalize) {
                            normalize = (1 / allData[i][1]);
                        }
                            selectedData.push([selectedData.length, allData[i][1]*normalize]);
                    }
                }

                var grad = regression.linear(selectedData, {precision: 8});
                console.log(selectedData);
                console.log(grad.equation[0].toFixed(8));
            });


        }
    });
}