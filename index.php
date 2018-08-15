<?php 

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

    $servername = getenv('DB_HOST');
    $username = getenv('BD_USER');
    $password = getenv('BD_PASSWORD');
    $database = getenv('BD_DATABASE');

    // Create connection
    $conn = new mysqli($servername, $username, $password, $database);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    } 
    
    if($query = $conn->query("SELECT *, DATE_FORMAT(data, '%d-%m %H:%i') data FROM total_balance")){
        if($query->num_rows){
            $i=0;
            while ($result = $query->fetch_assoc()) {
                $data[$i] = $result;
                $i++;
            }
            $query->close();
        }
    }
    
    if($query = $conn->query("SELECT *, DATE_FORMAT(FROM_UNIXTIME(bitfinex_timestamp), '%Y-%m-%d %H:%i') entrada, (last_price*100/price_in-100) saldo, CONCAT(FLOOR(HOUR(TIMEDIFF(last_update, FROM_UNIXTIME(bitfinex_timestamp))) / 24), 'd ',MOD(HOUR(TIMEDIFF(last_update, FROM_UNIXTIME(bitfinex_timestamp))), 24), 'h ',MINUTE(TIMEDIFF(last_update, FROM_UNIXTIME(bitfinex_timestamp))), 'm') tempo FROM operacoes ORDER BY closed, saldo")){
        if($query->num_rows){
            $i=0;
            while ($result = $query->fetch_assoc()) {
                $operacoes[$i] = $result;
                $i++;
            }
            $query->close();
        }
    }
    
    if($query = $conn->query("SELECT *, SUM((amount*last_price)-(amount*price_in)) total FROM operacoes GROUP BY symbol ORDER BY total")){
        if($query->num_rows){
            $i=0;
            while ($result = $query->fetch_assoc()) {
                $moedas[$i] = $result;
                $i++;
            }
            $query->close();
        }
    }

    $conn->close();

    function colorCell($val){
        if($val < -5){
            return ' style="background: red; color: white;"';
        }else if($val < 0){
            return ' style="background: yellow;"';
        }else if($val < 2){
            return ' style="background: #4ebf4e; color: white;"';
        }else if($val < 5){
            return ' style="background: green; color: white;"';
        }else{
            return ' style="background: blue; color: white;"';
        }
    }
?>
<!DOCTYPE html>
<html>
<head>
    <title>BITFINEX EXCHANGE USD BALANCE</title><head>
    <meta charset="utf-8">
    <meta id="viewport" name="viewport" content="width=device-width, initial-scale=1, user-scalable=0">
    <style>
    
        * {
          -webkit-box-sizing: border-box;
                  box-sizing: border-box;
            line-height:14px;
            margin:0;
            padding:0;
            list-style:none;
            text-decoration:none;
            border:none;
            line-height:inherit;
            outline:none;
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            font-size:12px;
            font-weight: 300;
        }

        h1 { 
            margin: 20px 0;
            font-size: 26px;
            font-weight: bold;
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            text-align: center;
        }

        #chartdiv {
            width   : 90%;
            height  : 500px;
            margin: 10px auto;
        }
        table {
          border: 3px solid #000000;
          width: 90%;
          text-align: left;
          border-collapse: collapse;
          margin: 10px auto;
        }
        table td, table th {
          border: 1px solid #000000;
          padding: 5px 4px;
          text-align: right;
        }
        table tbody td {
          font-size: 13px;
        }
        table thead {
          background: #CFCFCF;
          background: -moz-linear-gradient(top, #dbdbdb 0%, #d3d3d3 66%, #CFCFCF 100%);
          background: -webkit-linear-gradient(top, #dbdbdb 0%, #d3d3d3 66%, #CFCFCF 100%);
          background: linear-gradient(to bottom, #dbdbdb 0%, #d3d3d3 66%, #CFCFCF 100%);
          border-bottom: 3px solid #000000;
        }
        table thead th {
          font-size: 15px;
          font-weight: bold;
          color: #000000;
        }
        table tfoot {
          font-size: 14px;
          font-weight: bold;
          color: #000000;
          border-top: 3px solid #000000;
        }
        table tfoot td {
          font-size: 14px;
        }

    </style>
</head>
<body>
    <h1>Operações Abertas</h1>
    <table width="100%">
        <thead>
            <tr>
                <th>Data Entrada</th>
                <th>Última Atualização</th>
                <th>Tempo</th>
                <th>Moeda</th>
                <th>Quantidade</th>
                <th>Preço de Entrada</th>
                <th>Último preço</th>
                <th>Investimento</th>
                <th>Porcentagem</th>
                <th>Perda/Lucro</th>
            </tr>
        </thead>
        <tbody>
            <?php 
                $total_balance = 0;
                foreach($operacoes as $operacao){
                    $balance = ($operacao['last_price'] * $operacao['amount']) - ($operacao['price_in'] * $operacao['amount']);
                    $investimento = ($operacao['price_in'] * $operacao['amount']);
                    if($operacao['closed'] == ''){
                        $total_balance = $total_balance + $balance;
            ?>
                        <tr>
                            <td><?php echo $operacao['entrada'] ?></td>
                            <td><?php echo $operacao['last_update'] ?></td>
                            <td><?php echo $operacao['tempo'] ?></td>
                            <td style="text-transform: uppercase;"><?php echo $operacao['symbol'] ?></td>
                            <td><?php echo $operacao['amount'] ?></td>
                            <td>U$ <?php echo number_format($operacao['price_in'], 2, ',', '.'); ?></td>
                            <td>U$ <?php echo number_format($operacao['last_price'], 2, ',', '.'); ?></td>
                            <td>U$ <?php echo number_format($investimento, 2, ',', '.'); ?></td>
                            <td <?php echo colorCell($operacao['saldo']) ?>><?php echo number_format($operacao['saldo'], 2, ',', '.'); ?>%</td>
                            <td <?php echo colorCell($operacao['saldo']) ?>>U$ <?php echo number_format($balance, 2, ',', '.'); ?></td>
                        </tr>
            <?php
                    }
                }
            ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="9"></td>
                <td><?php echo number_format($total_balance, 2, ',', '.'); ?></td>
            </tr>
        </tfoot>
    </table>

    <h1>Saldo por Moedas</h1>

    <table width="100%">
        <thead>
            <tr>
                <?php 
                    foreach($moedas as $moeda){
                ?>
                        <th><?php echo strtoupper($moeda['symbol']) ?></th>
                <?php
                    }
                ?>
                <td>Total</td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <?php 
                    $total_balance = 0;
                    foreach($moedas as $moeda){
                        $total_balance = $moeda['total'] + $total_balance;
                ?>
                        <td>U$ <?php echo number_format($moeda['total'], 2, ',', '.'); ?></td>
                <?php
                    }
                ?>
                <td><?php echo number_format($total_balance, 2, ',', '.'); ?></td>
            </tr>
        </tbody>
    </table>

    <h1>Gráfico do Investimento Total</h1>
    <div id="chartdiv"></div>

    <h1>Operações Concluídas</h1>

    <table width="100%">
        <thead>
            <tr>
                <th>Data Entrada</th>
                <th>Data Saída</th>
                <th>Tempo</th>
                <th>Moeda</th>
                <th>Quantidade</th>
                <th>Preço de Entrada</th>
                <th>Último preço</th>
                <th>Porcentagem</th>
                <th>Perda/Lucro</th>
            </tr>
        </thead>
        <tbody>
            <?php 
                $total_balance = 0;
                foreach($operacoes as $operacao){
                    $balance = ($operacao['last_price'] * $operacao['amount']) - ($operacao['price_in'] * $operacao['amount']);
                    if($operacao['closed'] != ''){
                        $total_balance = $total_balance + $balance;
            ?>
                        <tr>
                            <td><?php echo $operacao['entrada'] ?></td>
                            <td><?php echo $operacao['last_update'] ?></td>
                            <td><?php echo $operacao['tempo'] ?></td>
                            <td style="text-transform: uppercase;"><?php echo $operacao['symbol'] ?></td>
                            <td><?php echo $operacao['amount'] ?></td>
                            <td>U$ <?php echo number_format($operacao['price_in'], 2, ',', '.'); ?></td>
                            <td>U$ <?php echo number_format($operacao['last_price'], 2, ',', '.'); ?></td>
                            <td <?php echo colorCell($operacao['saldo']) ?>><?php echo number_format($operacao['saldo'], 2, ',', '.'); ?>%</td>
                            <td <?php echo colorCell($operacao['saldo']) ?>>U$ <?php echo number_format($balance, 2, ',', '.'); ?></td>
                        </tr>
            <?php
                    }
                }
            ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="8"></td>
                <td><?php echo number_format($total_balance, 2, ',', '.'); ?></td>
            </tr>
        </tfoot>
    </table>

<script src="https://www.amcharts.com/lib/3/amcharts.js"></script>
<script src="https://www.amcharts.com/lib/3/serial.js"></script>
<script src="https://www.amcharts.com/lib/3/plugins/export/export.min.js"></script>
<link rel="stylesheet" href="https://www.amcharts.com/lib/3/plugins/export/export.css" type="text/css" media="all" />
<script src="https://www.amcharts.com/lib/3/themes/light.js"></script>
<script>
    var chart = AmCharts.makeChart("chartdiv", {
    "type": "serial",
    "theme": "light",
    "marginRight": 40,
    "marginLeft": 40,
    "autoMarginOffset": 20,
    "mouseWheelZoomEnabled":true,
    "dataDateFormat": "YYYY-MM-DD JJ:NN:SS",
    "valueAxes": [{
        "id": "v1",
        "axisAlpha": 0,
        "position": "left",
        "ignoreAxisWidth":true
    }],
    "balloon": {
        "borderThickness": 1,
        "shadowAlpha": 0
    },
    "graphs": [{
        "id": "g1",
        "balloon":{
          "drop":true,
          "adjustBorderColor":false,
          "color":"#ffffff"
        },
        "bullet": "round",
        "bulletBorderAlpha": 1,
        "bulletColor": "#FFFFFF",
        "bulletSize": 5,
        "hideBulletsCount": 50,
        "lineThickness": 2,
        "title": "red line",
        "useLineColorForBulletBorder": true,
        "valueField": "value",
        "balloonText": "<span style='font-size:18px;'>[[value]]</span>"
    }],
    "chartScrollbar": {
        "graph": "g1",
        "oppositeAxis":false,
        "offset":30,
        "scrollbarHeight": 80,
        "backgroundAlpha": 0,
        "selectedBackgroundAlpha": 0.1,
        "selectedBackgroundColor": "#888888",
        "graphFillAlpha": 0,
        "graphLineAlpha": 0.5,
        "selectedGraphFillAlpha": 0,
        "selectedGraphLineAlpha": 1,
        "autoGridCount":true,
        "color":"#AAAAAA"
    },
    "chartCursor": {
        "pan": true,
        "valueLineEnabled": true,
        "valueLineBalloonEnabled": true,
        "cursorAlpha":1,
        "cursorColor":"#258cbb",
        "limitToGraph":"g1",
        "valueLineAlpha":0.2,
        "valueZoomable":true
    },
    "valueScrollbar":{
      "oppositeAxis":false,
      "offset":50,
      "scrollbarHeight":10
    },
    "categoryField": "date",
    "categoryAxis": {
        "parseDates": false,
        "dashLength": 1,
        "minorGridEnabled": true
    },
    "export": {
        "enabled": true
    },
    "dataProvider": [
    <?php
        $rows = '';
        foreach($data as $row){
            $rows .= '{"date":"'.$row['data'].'","value":'.(int)$row['balance'].'},';
        }
        $rows .= "|}";
        $rows = str_replace(',|}','',$rows);
        echo $rows;

    ?>
    ]
});

chart.addListener("rendered", zoomChart);

zoomChart();

function zoomChart() {
    chart.zoomToIndexes(chart.dataProvider.length - 40, chart.dataProvider.length - 1);
}
</script>                                 
</body>
</html>