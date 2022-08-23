<?php 

require __DIR__ . '/vendor/autoload.php';


use \XRPLWin\XRPL\Client;
use \XRPLWin\XRPLOrderbookReader\LiquidityCheck;
use Amp\Parallel\Worker;
use Amp\Promise;

function doRequest(string $url): string
{
    return 'test';
}

$trade = []; $options = [];
$client = new Client([
  'endpoint_reporting_uri' => 'https://xrplcluster.com'
]);

$promises = [
  //Worker\enqueueCallable( 'Amp\Serialization\encodeUnprintableChars', 'asd'),
  Worker\enqueueCallable( 'XRPLWin\XRPLOrderbookReader\fetchBook',false,$trade,$options,$client),
  //Worker\enqueueCallable( 'XRPLWin\XRPLOrderbookReader\BookFetcher::fetchBook',false,$trade,$options,$client),
  //Worker\enqueueCallable( '\\XRPLWin\\XRPLOrderbookReader\\BookFetcher::fetchBook',true, $trade, $options, $client),
  //Worker\enqueueCallable('\XRPLWin\XRPLOrderbookReader\BookFetcher::fetchBook', false, $this->trade, $this->options, $this->client),
];


$responses = Promise\wait(Promise\all($promises));
dump($responses);

return;



$client = new Client([
  'endpoint_reporting_uri' => 'https://xrplcluster.com'
]);

$lc = new LiquidityCheck([
  # Trade:
  'from' => [
      'currency' => 'XRP'
  ],
  'amount' => 100000,
  'to' => [
      'currency' => 'USD',
      'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'
  ],
],
[
  # Options:
  'rates' => 'to',
  'maxSpreadPercentage' => 0.0001,
  'maxSlippagePercentage' => 0.0001,
  'maxSlippagePercentageReverse' => 0.0001,
  'includeBookData' => false,
  'maxBookLines' => 10
],
$client);

$Liquidity = $lc->get();


dump($Liquidity);