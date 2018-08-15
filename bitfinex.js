/*

    THIS FILES IS SUPPOSED TO BE EXECUTED BY CRONTAB EVERY 10 MIN

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

const url = '/v1/balances';
const nonce = Date.now().toString();
const completeURL = baseUrl + url;
const body = {
    request: url,
    nonce
};
const payload = new Buffer(JSON.stringify(body))
    .toString('base64');

const signature = crypto
    .createHmac('sha384', apiSecret)
    .update(payload)
    .digest('hex');

const options = {
    url: completeURL,
    headers: {
        'X-BFX-APIKEY': apiKey,
        'X-BFX-PAYLOAD': payload,
        'X-BFX-SIGNATURE': signature
    },
    body: JSON.stringify(body)
};

var mywallets = [];
var wallets = [];
var totalBalance = 0;
var wallets_count = 0;
var complete = 1;
var verifyCount = 0;
var insertBalanceCount = 0;

return request.post(
    options,
    function(error, response, body) {
        //console.log('response:', JSON.stringify(body, 0, 2))
        mywallets = JSON.parse(body);
        mywallets.forEach(function(res, i){
            if(parseFloat(res.amount) > 0 && res.currency != 'usd'){
                wallets_count++;
                wallets.push(res);
            }else if(res.currency == 'usd'){
                res.usd_balance = parseFloat(res.amount);
                wallets.push(res);
            }
        });
        getPrice();
    }
)

function getPrice(symbol){
    wallets.forEach(function(res, i){
        if(res.currency != 'usd'){
            request.get('https://api.bitfinex.com/v1/pubticker/'+res.currency+'usd', function(error, response, body) {
                var obj = JSON.parse(body);
                res.usd_price = parseFloat(obj.last_price);
                res.usd_balance = parseFloat(obj.last_price) * parseFloat(parseFloat(res.amount));
                complete++;
                if(wallets.length == complete) showTotalBalance();
            });
        }
    });
}

function showTotalBalance(){
    console.log(' \n --- SHOWING TOTALS --- ');
    totalBalance = 0;
    wallets.forEach(function(res){
        console.log(JSON.stringify(res));
        if(typeof res.usd_balance !== 'undefined')
            totalBalance = totalBalance + parseFloat(res.usd_balance);
    });

    console.log(' ---  Total: U$ '+totalBalance+' --- ');
    saveInDataBase();
}

function saveInDataBase(){
    mysql_conn = mysql.createConnection(mysql_conf);
    mysql_conn.connect();
    dbInsertTotalBalance();
    verifyCount = 0;
    dbVerifyCurrency();
}

function dbInsertTotalBalance(){

    var last_percentage = 0;
    var sum_percentage = 0;
    mysql_conn.query('SELECT * FROM total_balance ORDER BY id DESC LIMIT 1', function(err, rows, fields) {
        if(rows.length > 0){
            last_percentage = totalBalance * 100 / rows[0].balance - 100;
            sum_percentage = rows[0].sum_percentage + last_percentage;
        }

        mysql_conn.query('INSERT INTO total_balance (balance, last_percentage, sum_percentage) VALUES ('+totalBalance+', '+last_percentage+', '+sum_percentage+') ');
    });
}

function dbVerifyCurrency(){
    mysql_conn.query('SELECT * FROM wallets WHERE currency = "'+wallets[verifyCount].currency+'"', function(err, rows, fields) {
        if(rows.length == 0){
            dbInsertCurrency(wallets[verifyCount]);
        }else{
            dbInsertBalance(rows[0].id, wallets[verifyCount]);
        }
        verifyCount++;
        if(verifyCount < wallets.length){
            dbVerifyCurrency();
        }
    });
}

function dbInsertCurrency(wallet){
    mysql_conn.query('INSERT INTO wallets (currency) VALUES ("'+wallet.currency+'") ', function(err, rows, fields) {
        dbInsertBalance(rows.insertId, wallet);
    });
}

function dbInsertBalance(currency, wallet){
    var last_percentage = 0;
    var sum_percentage = 0;
    mysql_conn.query('SELECT * FROM balances WHERE id_wallet = '+currency+' ORDER BY id DESC LIMIT 1', function(err, rows, fields) {
        
        if(rows.length > 0){
            if(rows[0].amount == wallet.amount){
                last_percentage = parseFloat(wallet.usd_balance) * 100 / rows[0].balance - 100;
                sum_percentage = rows[0].sum_percentage + last_percentage;
            }
        }

        mysql_conn.query('INSERT INTO balances (id_wallet, amount, balance, last_percentage, sum_percentage) VALUES ('+currency+', '+wallet.amount+', '+wallet.usd_balance+', '+last_percentage+', '+sum_percentage+') ');
        insertBalanceCount++;
        if(insertBalanceCount >= wallets.length){
            mysql_conn.end();
            console.log('fechou conexão');
        }
    });
}

