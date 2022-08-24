<?php declare(strict_types=1);

namespace XRPLWin\XRPLOrderbookReader\Tests;

use PHPUnit\Framework\TestCase;
use \XRPLWin\XRPL\Client;
use \XRPLWin\XRPLOrderbookReader\LiquidityCheck;

final class ReaderTest extends TestCase
{
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

    public function testShouldExceedAbsurdLimitsForXrpToGatehubUsd()
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
            'maxSpreadPercentage' => 0.0001,
            'maxSlippagePercentage' => 0.0001,
            'maxSlippagePercentageReverse' => 0.0001,
            'includeBookData' => false,
            'maxBookLines' => 100
        ],
        $client);

        $Liquidity = $lc->get();
        //print_r($Liquidity['errors']);
        $this->assertEquals([
            //'REQUESTED_LIQUIDITY_NOT_AVAILABLE',
            //'REVERSE_LIQUIDITY_NOT_AVAILABLE',
            'MAX_SPREAD_EXCEEDED',
            'MAX_SLIPPAGE_EXCEEDED',
            'MAX_REVERSE_SLIPPAGE_EXCEEDED'
        ],$Liquidity['errors']);
    }

    public function testShouldErrorOutWithInsufficientLiquidity()
    {
        $client = new Client([
            'endpoint_reporting_uri' => 'https://xrplcluster.com'
        ]);

        $lc = new LiquidityCheck([
            # Trade:
            'from' => [
                'currency' => 'XRP'
            ],
            'amount' => 1000,
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
            'maxBookLines' => 100
        ],
        $client);

        $Liquidity = $lc->get();

        $this->assertEquals([
            'REQUESTED_LIQUIDITY_NOT_AVAILABLE',
            'REVERSE_LIQUIDITY_NOT_AVAILABLE',
            'MAX_SPREAD_EXCEEDED'
        ],$Liquidity['errors']);
    }
}