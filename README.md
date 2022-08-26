![main workflow](https://github.com/XRPLWin/XRPL-Orderbook-Reader/actions/workflows/main.yml/badge.svg)
[![GitHub license](https://img.shields.io/github/license/XRPLWin/XRPL-Orderbook-Reader)](https://github.com/XRPLWin/XRPL-Orderbook-Reader/blob/main/LICENSE)
[![Total Downloads](https://img.shields.io/packagist/dt/xrplwin/xrpl-orderbook-reader.svg?style=flat)](https://packagist.org/packages/xrplwin/xrpl-orderbook-reader)

# XRPL Orderbook Reader for PHP

This is PHP port of https://github.com/XRPL-Labs/XRPL-Orderbook-Reader by [Wietse Wind](https://github.com/WietseWind) ([@XRPL Labs](https://github.com/XRPL-Labs))

This repository takes XRPL Orderbook (`book_offers`) datasets and requested volume to
exchange and calculates the effective exchange rates based on the requested and available liquidity.

Optionally certain checks can be specified (eg. `book_offers` on the other side of the book)
to warn for limited (percentage) liquidity on the requested side, and possibly other side
of the order book.

# Note

This package is provided as is, please test it yourself first.  
Found a bug? [Report issue here](https://github.com/XRPLWin/XRPL-Orderbook-Reader/issues/new)

## Requirements
- PHP 8.1 or higher
- [Composer](https://getcomposer.org/)

## Installation
To install run

```
composer require xrplwin/xrpl-orderbook-reader
```

## Usage
```PHP
use \XRPLWin\XRPL\Client;

$xrplwinapiclient = new Client([]);
$lc = new LiquidityCheck([
    # Trade:
    'from' => [
        'currency' => 'XRP'
    ],
    'to' => [
        'currency' => 'USD',
        'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'
    ],
    'amount' => 10,
    'limit' => 100
],
[
    # Options:
    //'rates' => 'to',
    //'maxSpreadPercentage' => 4
    //'maxSlippagePercentage' => 3
    //'maxSlippagePercentageReverse' => 3
    'includeBookData' => true //default false
], $xrplwinapiclient);

try {
    $Liquidity = $lc->get();
} catch (\Exception) {
    //Unable to connect to provided XRPL server...
    $Liquidity = [
        'rate' => null,
        'safe' => false,
        'errors' => ['CONNECT_ERROR']
    ];
}

print_r($Liquidity); //['rate' => NUMBER, 'safe' => BOOLEAN, 'errors' => ARRAY, 'books' => ARRAY]
```
## Running tests
Run all tests in "tests" directory.
```
composer test
```
or
```
./vendor/bin/phpunit --testdox
```

### Sample

See [dev.php](dev.php) for sample.
