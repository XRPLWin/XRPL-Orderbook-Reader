<?php 

require __DIR__ . '/vendor/autoload.php';


use \XRPLWin\XRPL\Client;
use \XRPLWin\XRPLOrderbookReader\LiquidityCheck;

//https://api.onthedex.live/public/v1/ticker/XRP:USD.rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq
//https://xrpl.win/api/ticker/rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq/USD

$client = new Client([
  'endpoint_reporting_uri' => 'https://xrplcluster.com'
]);

$lc = new LiquidityCheck([
  # Trade:
  'from' => [
      'currency' => 'XRP'
  ],
  'amount' => 100,
  'to' => [
      'currency' => '534F4C4F00000000000000000000000000000000',
      'issuer' => 'rsoLo2S1kiGeCcn6hCUXVrCpGMWLrRrLZz'
  ],
],
[
  # Options:
  'rates' => 'to',
  'maxSpreadPercentage' => 0.0001,
  'maxSlippagePercentage' => 0.0001,
  'maxSlippagePercentageReverse' => 0.0001,
  'includeBookData' => true,
  'maxBookLines' => 1
],
$client);

$Liquidity = $lc->get();


dump($Liquidity);