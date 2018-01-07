jQuery(document).ready(function() {
    jQuery.ajax({
        method: 'get',
        url: '/api/v1/exchange/livecoin/exchange-rates?counter_iso=USD&volume=100000',
        success: function(data) {
            for(i in data) {
                drawLog('livecoin', data[i].name);
            }
        }
    });
});

function drawLog(exchange, name) {
    jQuery.ajax({
        method: 'get',
        url: '/api/v1/exchange/'+exchange+'/exchange-rates/history/'+name+'?min-back=480',
        success: function(data) {
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
            jQuery("#log-"+name).plot(
                graphdata,
                {
                    xaxis: { mode: "time"},
                    legend: {position: 'nw'}
                }
            ).data("plot");

        }
    });
}