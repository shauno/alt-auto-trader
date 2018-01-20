jQuery(document).ready(function() {
    jQuery.ajax({
        method: 'get',
        url: '/api/v1/exchange/kraken/exchange-rates?counter_iso=ZUSD&volume=0',
        success: function(data) {
            for(i in data) {
                drawLog('kraken', data[i].name);
            }
        }
    });
});

var graphs = {};
function drawLog(exchange, name) {
    jQuery.ajax({
        method: 'get',
        url: '/api/v1/exchange/'+exchange+'/exchange-rates/history/'+name+'?min-back=480',
        success: function(returnedData) {
            var data = returnedData.data;
            var graphdata = [];
            for(i in data) {
                givenDate = new Date(data[i].created_at);
                correctedDate = new Date(Date.UTC(
                    givenDate.getFullYear(),
                    givenDate.getMonth(),
                    givenDate.getDate(),
                    givenDate.getHours(),
                    givenDate.getMinutes() - givenDate.getTimezoneOffset(),
                    givenDate.getSeconds()
                ));
                graphdata.push([correctedDate, parseFloat(data[i].bid_rate)]);
            }

            graphdata = [
                {
                    label: name,
                    data: graphdata
                }
            ];

            name = name.replace('/', '-').toLowerCase();

            jQuery('#graph-container').append('<div id="log-'+name+'" style="width: 600px; height: 100px; display: inline-block;"></div>');

            graphs[name] = jQuery("#log-"+name).plot(
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