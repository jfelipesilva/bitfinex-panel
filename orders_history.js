/*

    THIS FILES IS SUPPOSED TO BE EXECUTED BY CRONTAB EVERY 5 MIN

*/

require('dotenv').config();
var mysql = require('mysql');
var mysql_conf = {  
  host     : process.env.BD_HOST,
  user     : process.env.BD_USER,
  password : process.env.BD_PASSWORD,
  database : process.env.BD_DATABASE
}
var mysql_conn = "";

const request = require('request');
const crypto = require('crypto');

const apiKey = process.env.BITFINEX_APIKEY;
const apiSecret = process.env.BITFINEX_APISECRET;
const baseUrl = process.env.BITFINEX_BASEURL;


var mywallets = [];
var wallets = [];
getWalletsBalance();
function getWalletsBalance(){
    var url = '/v1/balances';
    var nonce = Date.now().toString();
    var completeURL = baseUrl + url;
    var body = {
        request: url,
        nonce
    };
    var payload = new Buffer(JSON.stringify(body))
        .toString('base64');

    var signature = crypto
        .createHmac('sha384', apiSecret)
        .update(payload)
        .digest('hex');

    var options = {
        url: completeURL,
        headers: {
            'X-BFX-APIKEY': apiKey,
            'X-BFX-PAYLOAD': payload,
            'X-BFX-SIGNATURE': signature
        },
        body: JSON.stringify(body)
    };


    return request.post(
        options,
        function(error, response, body) {
            //console.log('response:', JSON.stringify(body, 0, 2))
            mywallets = JSON.parse(body);
            //console.log(mywallets);

            mywallets.forEach(function(res, i){
                if(parseFloat(res.amount) > 0 && res.currency != 'usd'){
                    wallets.push(res);
                }
            });
            connectDatabase();
            getPrice();
        }
    )
}

var wallet_price_count = 0;
function getPrice(){
    wallets.forEach(function(res, i){
        request.get('https://api.bitfinex.com/v1/pubticker/'+res.currency+'usd', function(error, response, body) {
            var obj = JSON.parse(body);
            res.usd_price = parseFloat(obj.last_price);
            mysql_conn.query('UPDATE operacoes SET last_price='+obj.last_price+', last_update=NOW() WHERE symbol ="'+res.currency+'" AND closed IS NULL');
            wallet_price_count++;
            if(wallets.length == wallet_price_count) getOrderHistory();
        });
    });
}


var orders = [];
var orders_process_count = 0;

function getOrderHistory(){
    var url = '/v1/orders/hist';
    var nonce = Date.now().toString();
    var completeURL = baseUrl + url;
    var body = {
        request: url,
        nonce
    };
    var payload = new Buffer(JSON.stringify(body))
        .toString('base64');

    var signature = crypto
        .createHmac('sha384', apiSecret)
        .update(payload)
        .digest('hex');

    var options = {
        url: completeURL,
        headers: {
            'X-BFX-APIKEY': apiKey,
            'X-BFX-PAYLOAD': payload,
            'X-BFX-SIGNATURE': signature
        },
        body: JSON.stringify(body)
    };



    //BUSCA POR HISTÓRICO DE ORDENS
    request(options, function (error, response, body) {
        if (error) throw new Error(error);
        //console.log(JSON.parse(body));

        orders = JSON.parse(body);
        orders_process_count = orders.length - 1;
        processOrders();
    });

}



function connectDatabase(){
    mysql_conn = mysql.createConnection(mysql_conf);
    mysql_conn.connect();
}

function disconnectDatabase(){
    mysql_conn.end();
}

//FUNÇÃO RECURSSIVA AO FINAL DE CADA 
function processOrders(){
    var order = orders[orders_process_count];
    console.log(orders_process_count+" - "+JSON.stringify(order));
    //if(order.is_live == false && order.is_cancelled == false){
    if(order.is_live == false && parseFloat(order.executed_amount) > 0){
        //SOMENTE ORDENS EXECUTADAS
        mysql_conn.query('SELECT * FROM buys_sells WHERE id_bitfinex = '+order.id, function(err, rows, fields) {
            if(rows.length == 0){
                var buy = 0;
                var sell = 0;
                var novaoperacao = 0;
                var wallet_amount = 0;
                var amount = order.executed_amount;
                var op_amount = amount;
                var symbol = order.symbol[0]+order.symbol[1]+order.symbol[2];
                var tipo = "";
                var wallet_balance = "";
                var last_price = 0;

                //VERIFICA SALDO DA CARTEIRA
                mywallets.forEach(function(res, i){
                    if(res.currency == symbol){
                        wallet_balance = parseFloat(res.amount)*parseFloat(order.price);
                        wallet_amount = parseFloat(res.amount);
                        last_price = res.usd_price;
                    }
                });
                //console.log(symbol+' - '+order.side+'  amount: '+amount+', remaining: '+order.remaining_amount+', price: '+order.price);

                if(order.side == 'buy'){
                    buy = 1;
                    novaoperacao = 1;
                    tipo = "comprou";
                    if(wallet_balance > 1){
                        tipo = "comprou mais";
                    }
                }else{
                    sell = 1;

                    //VERIFICA SE EXISTE MAIS DE 1 DOLAR NA CARTEIRA, SE SIM, INICIAR NOVA OPERAÇÃO
                    if(wallet_balance > 1){
                        novaoperacao = 1;
                        op_amount = wallet_amount;
                        tipo = "vendeu e restou saldo maior que 1 dolar";
                    }
                    //FECHAR TODAS OPERAÇÕES DESSA MOEDA A PARTIR DE UMA VENDA
                    mysql_conn.query('UPDATE operacoes SET closed=NOW(), last_price = '+order.avg_execution_price+' WHERE symbol ="'+symbol+'" AND closed IS NULL');
                }

                if(novaoperacao) mysql_conn.query('INSERT INTO operacoes (symbol, tipo, amount, price_in, last_price, last_update, bitfinex_timestamp) VALUES ("'+symbol+'", "'+tipo+'", '+op_amount+', "'+order.avg_execution_price+'", "'+last_price+'", NOW(), "'+order.timestamp+'")');

                mysql_conn.query('INSERT INTO buys_sells (id_bitfinex, symbol, amount, price, bitfinex_timestamp,sell,buy) VALUES ('+order.id+', "'+order.symbol+'", '+amount+', '+order.avg_execution_price+', "'+order.timestamp+'",'+sell+','+buy+')',function(){
                    processNext();
                });

            }else{
                processNext();
            }
        });
    }else{
        processNext();
    }
}


function processNext(){
    orders_process_count--;
    //console.log('processo '+orders_process_count+'/'+orders.length+' ok!');
    if(orders_process_count >= 0){
        processOrders();
    }else{
        finishProcessing();
    }
}

function finishProcessing(){
    console.log('fim de execução');
    disconnectDatabase();
}
