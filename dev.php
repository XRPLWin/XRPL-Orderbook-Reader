<?php 

require __DIR__ . '/vendor/autoload.php';


use \XRPLWin\XRPL\Client;
use \XRPLWin\XRPLOrderbookReader\LiquidityCheck;



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