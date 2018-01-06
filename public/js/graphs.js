jQuery(document).ready(function() {
    jQuery.ajax({
        method: 'get',
        url: '/api/v1/exchange/livecoin/exchange-rates?counter_iso=USD',
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
        url: '/api/v1/exchange/'+exchange+'/exchange-rates/history/'+name+'?min-back=240',
        success: function(data) {
            var graphdata = [];
            for(i in data) {
                graphdata.push([new Date(data[i].created_at), parseFloat(data[i].bid_rate)]);
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
                    xaxis: { mode: "time"}
                }
            ).data("plot");

        }
    });
}