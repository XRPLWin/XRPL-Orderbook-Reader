<?php 
header("Content-Type: text/plain");
require __DIR__ . '/vendor/autoload.php';

use \XRPLWin\XRPL\Client;
use \XRPLWin\XRPLOrderbookReader\LiquidityCheck;

//https://sologenic.org/trade?market=XRP%2F534F4C4F00000000000000000000000000000000%2BrsoLo2S1kiGeCcn6hCUXVrCpGMWLrRrLZz&network=mainnet
//https://api.onthedex.live/public/v1/ticker/XRP:534F4C4F00000000000000000000000000000000.rsoLo2S1kiGeCcn6hCUXVrCpGMWLrRrLZz
//https://xrpl.win/api/ticker/rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq/USD

$client = new Client([
  'endpoint_reporting_uri' => 'https://xrplcluster.com'
]);

$lc = new LiquidityCheck([
  # Trade:
  'to' => [
      'currency' => 'XRP'
  ],
  'amount' => 100,
  'from' => [
      'currency' => '534F4C4F00000000000000000000000000000000',
      'issuer' => 'rsoLo2S1kiGeCcn6hCUXVrCpGMWLrRrLZz'
  ],
],
[
  # Options:
  'rates' => 'from',
  'maxSpreadPercentage' => 0.0001,
  'maxSlippagePercentage' => 0.0001,
  'maxSlippagePercentageReverse' => 0.0001,
  'includeBookData' => true,
  'maxBookLines' => 100
],
$client);

$Liquidity = $lc->get();

$calculatorTest = new \XRPLWin\XRPLOrderbookReader\Tests\CalculatorTest();
$calculatorTest->renderTable($Liquidity);
echo 'Rate: '.$Liquidity['rate'].' Safe: '.(int)$Liquidity['safe'];
echo ' | '.\implode(' | ', $Liquidity['errors']);
