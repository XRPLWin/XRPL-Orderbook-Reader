<?php declare(strict_types=1);

namespace XRPLWin\XRPLOrderbookReader\Tests;

use PHPUnit\Framework\TestCase;
use \XRPLWin\XRPL\Client as XRPLWinApiClient;
use \XRPLWin\XRPLOrderbookReader\LiquidityCheck;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use XRPLWin\XRPL\Client\Guzzle\HttpClient;
use GuzzleHttp\Promise as P;

final class PromiseTest extends TestCase
{
  public function testAsynchronousRequest()
  {
    $container = [];
    $history = Middleware::history($container);

    $mock = new MockHandler([
        //Mock next five responses as 503: https://github.com/XRPLF/rippled/blob/e32bc674aa2a035ea0f05fe43d2f301b203f1827/src/ripple/server/impl/JSONRPCUtil.cpp#L116
        new Response(503, [], 'Server is overloaded'."\r\n"),
        new Response(503, [], 'Server is overloaded'."\r\n"),
      ]);
    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push($history);

    $httpClient = new HttpClient(['handler' => $handlerStack]);

    $client = new XRPLWinApiClient([
        'endpoint_reporting_uri' => 'https://xrplcluster.com'
    ],$httpClient);

    $lc = new LiquidityCheck([
        # Trade:
        'from' => [
            'currency' => 'XRP'
        ],
        'amount' => 20,
        'to' => [
            'currency' => 'USD',
            'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'
        ]
    ],
    [],$client);

    $orderbook = $lc->fetchBook();
    $orderbookReverse = $lc->fetchBook();

    $promises = [
      $orderbook->requestAsync(),
      $orderbookReverse->requestAsync()
    ];

    $promiseResults = P\Utils::all($promises)->wait();//$each->promise()->wait(); //see unwrap


    echo 'start';
    foreach ($container as $transaction) {
      //echo $transaction['request']->getMethod();
      //> GET, HEAD
      if ($transaction['response']) {
          echo $transaction['response']->getBody()."\r\n";
          //> 200, 200
      } elseif ($transaction['error']) {
          //echo $transaction['error'];
          //> exception
      }
      //var_dump($transaction['response']);exit;
      //> dumps the request options of the sent request.
    }



  }

}