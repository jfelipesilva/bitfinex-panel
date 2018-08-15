const request = require('request')
const url = 'https://api.bitfinex.com/v1'

request.get(url + '/pubticker/neousd', function(error, response, body) {
    obj = JSON.parse(body)
    console.log(obj)
})