<?php declare(strict_types=1);

namespace XRPLWin\XRPLOrderbookReader\Tests;

use PHPUnit\Framework\TestCase;
use XRPLWin\XRPL\Client;
use XRPLWin\XRPL\Client\Guzzle\HttpClient;
use XRPLWin\XRPLOrderbookReader\LiquidityCheck;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\HandlerStack;
#use GuzzleHttp\Middleware;


final class ReaderTest extends TestCase
{
    /**
     * First promise should follow 301 redirect to 404 response.
     * Next promise should reach 503 error response.
     */
    public function testExceptThisRequestToFail(): void
    {
        //$container = [];
        //$history = Middleware::history($container);

        $mock = new MockHandler([
            new Response(301, ['Location' => 'https://otherurl.test'], 'Moved Permanently'),
            new Response(404, [], 'Not found'."\r\n"),
            new Response(503, [], 'Server is overloaded'."\r\n"),
        ]);

        $handlerStack = HandlerStack::create($mock);
        
        //$handlerStack->push($history);

        $httpClient = new HttpClient(['handler' => $handlerStack]);

        $client = new Client([],$httpClient);

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

        $this->expectException(\Exception::class);
        $lc->get();
    }

    public function testShouldGetRateForXrpFromGatehubUsd(): void
    {
        $client = new Client([
            'endpoint_reporting_uri' => 'https://xrplcluster.com'
        ]);

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
        [
            # Options:
            //'rates' => 'to',
            //'maxSpreadPercentage' => 4,
            //'maxSlippagePercentage' => 3,
            //'maxSlippagePercentageReverse' => 3,
            'includeBookData' => true
        ],
        $client);

        $Liquidity = $lc->get();

        $this->assertIsArray($Liquidity);
        $this->assertEquals(4,count($Liquidity));
        $this->assertArrayHasKey('rate',$Liquidity);
        $this->assertIsNumeric($Liquidity['rate']);

        $this->assertArrayHasKey('safe',$Liquidity);
        $this->assertIsBool($Liquidity['safe']);

        $this->assertArrayHasKey('errors',$Liquidity);
        $this->assertIsArray($Liquidity['errors']);

        $this->assertArrayHasKey('books',$Liquidity);
        $this->assertIsArray($Liquidity['books']);
        $this->assertEquals(2,count($Liquidity['books']));
        
        $this->assertGreaterThan(0,$Liquidity['rate']);
    }

    public function testShouldExceedAbsurdLimitsForXrpToGatehubUsd(): void
    {
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
            ]
        ],
        [
            # Options:
            'rates' => 'to',
            'maxSpreadPercentage' => 0.000001,
            'maxSlippagePercentage' => 0.000001,
            'maxSlippagePercentageReverse' => 0.000001,
            'includeBookData' => false,
            'maxBookLines' => 500
        ],
        $client);

        $Liquidity = $lc->get();
        $this->assertContains('MAX_SPREAD_EXCEEDED',$Liquidity['errors']);
        $this->assertContains('MAX_SLIPPAGE_EXCEEDED',$Liquidity['errors']);
        $this->assertContains('MAX_REVERSE_SLIPPAGE_EXCEEDED',$Liquidity['errors']);
    }

    public function testShouldErrorOutWithInsufficientLiquidity(): void
    {
        $client = new Client([
            'endpoint_reporting_uri' => 'https://xrplcluster.com'
        ]);

        $lc = new LiquidityCheck([
            # Trade:
            'from' => [
                'currency' => 'XRP'
            ],
            'amount' => 1000000,
            'to' => [
                'currency' => 'USD',
                'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'
            ]
        ],
        [
            # Options:
            'rates' => 'to',
            'maxSpreadPercentage' => 2,
            'maxSlippagePercentage' => 2,
            'maxSlippagePercentageReverse' => 2,
            'includeBookData' => false,
            'maxBookLines' => 1
        ],
        $client);

        $Liquidity = $lc->get();
        $this->assertContains('REQUESTED_LIQUIDITY_NOT_AVAILABLE',$Liquidity['errors']);
        $this->assertContains('REVERSE_LIQUIDITY_NOT_AVAILABLE',$Liquidity['errors']);

    }
}