<?php declare(strict_types=1);

namespace XRPLWin\XRPLOrderbookReader\Tests;

use PHPUnit\Framework\TestCase;
use XRPLWin\XRPL\Client;
use XRPLWin\XRPL\Client\Guzzle\HttpClient;
use XRPLWin\XRPLOrderbookReader\LiquidityCheck;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\HandlerStack;


final class CalculatorTest extends TestCase
{
  /**
   * Enable this to show debug output
   */
  const DEBUG = false;

  public function testCalculateMulti()
  {
    $amount = 1000;
    $from = ['currency' => 'EUR', 'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'];
    $to =  [ 'currency' => 'USD', 'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'];
    $offersToFrom = [
      ['1000','2500'],
    ];
    $offersFromTo = [
      ['25','10'],
      ['20','31'],
    ];
    $result = $this->calculate($amount,$from, $to, $offersFromTo, $offersToFrom);
    # Debug
    if(self::DEBUG) {
      $this->renderTable($result);
      echo 'Rate: '.$result['rate'].' Safe: '.(int)$result['safe'];
      echo ' | '.\implode(' | ', $result['errors']);
    }
    # End Debug

    $this->assertEquals('0.91111111111111110909',$result['rate']);
    $this->assertFalse($result['safe']);
    $this->assertEquals([
      'REQUESTED_LIQUIDITY_NOT_AVAILABLE',
      'REVERSE_LIQUIDITY_NOT_AVAILABLE',
      'MAX_SLIPPAGE_EXCEEDED'
    ],$result['errors']);
    

  }

  public function testCalculateRequestedLiquidityShouldNotBeAvailable()
  {
    $amount = 50;
    $from = ['currency' => 'EUR', 'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'];
    $to =  [ 'currency' => 'USD', 'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'];
    $offersToFrom = [
      ['10','15'],
      ['20','31'],
    ];
    $offersFromTo = [
      
    ];
    $result = $this->calculate($amount,$from, $to, $offersFromTo, $offersToFrom);
    # Debug
    if(self::DEBUG) {
      $this->renderTable($result);
      echo 'Rate: '.$result['rate'].' Safe: '.(int)$result['safe'];
      echo ' | '.\implode(' | ', $result['errors']);
    }
    # End Debug

    $this->assertEquals(0,$result['rate']);
    $this->assertFalse($result['safe']);
    $this->assertEquals([
      'REQUESTED_LIQUIDITY_NOT_AVAILABLE',
    ],$result['errors']);

  }

  public function testCalculateReverseLiquidityShouldNotBeAvailable()
  {
    $amount = 50;
    $from = ['currency' => 'EUR', 'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'];
    $to =  [ 'currency' => 'USD', 'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'];
    $offersToFrom = [
      
    ];
    $offersFromTo = [
      ['10','15'],
      ['20','31'],
    ];
    $result = $this->calculate($amount,$from, $to, $offersFromTo, $offersToFrom);
    # Debug
    if(self::DEBUG) {
      $this->renderTable($result);
      echo 'Rate: '.$result['rate'].' Safe: '.(int)$result['safe'];
      echo ' | '.\implode(' | ', $result['errors']);
    }
    # End Debug

    $this->assertEquals('1.53333333333333333538',$result['rate']);
    $this->assertFalse($result['safe']);
    $this->assertEquals([
      'REVERSE_LIQUIDITY_NOT_AVAILABLE',
    ],$result['errors']);

  }

  /**
   * Out of range test
   * due to LiquidityParser::PRECISION = 20
   * @see https://xrpl.org/serialization.html#token-amount-format
   * As per documentation XRPL value store ranges up to 16 decimal digits.
   */
  public function testExponentialAmountWithVerySmallOutOfRangeValueShouldThrowDivisionByZeroError()
  {
    $amount = 1;
    $from = ['currency' => 'EUR', 'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'];
    $to =  [ 'currency' => 'USD', 'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'];
    $offersToFrom = [
      ['999e-24','10'],
    ];
    $offersFromTo = [
      ['10','100'],
    ];
    $this->expectException(\Brick\Math\Exception\DivisionByZeroException::class);
    $this->calculate($amount,$from, $to, $offersFromTo, $offersToFrom);
  }

  /**
   * This test demonstrates handling of max 19 decimal digits before division by zero is reached
   * due to LiquidityParser::PRECISION = 20
   * @see https://xrpl.org/serialization.html#token-amount-format
   * As per documentation XRPL value store ranges up to 16 decimal digits.
   */
  public function testExponentialAmountWithVerySmallValueShouldBeCalculated()
  {
    $amount = 1;
    $from = ['currency' => 'EUR', 'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'];
    $to =  [ 'currency' => 'USD', 'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'];
    $offersToFrom = [
      ['999e-19','10'],
    ];
    $offersFromTo = [
      ['10','100'],
    ];
    $result = $this->calculate($amount,$from, $to, $offersFromTo, $offersToFrom);
    # Debug
    if(self::DEBUG) {
      $this->renderTable($result);
      echo 'Rate: '.$result['rate'].' Safe: '.(int)$result['safe'];
      echo ' | '.\implode(' | ', $result['errors']);
    }
    # End Debug

    $this->assertEquals(10,$result['rate']);
    $this->assertFalse($result['safe']);
    $this->assertEquals([
      'REQUESTED_LIQUIDITY_NOT_AVAILABLE',
      'REVERSE_LIQUIDITY_NOT_AVAILABLE',
      'MAX_SPREAD_EXCEEDED'
    ],$result['errors']);

    $this->assertEquals('0.00000000000000001',(string)$result['books'][1][0]['_CumulativeRate_Cap']);
    $this->assertEquals(0.00000000000000001,  (string)$result['books'][1][0]['_CumulativeRate_Cap']);
  }

  public function testCalculatedExponentialAmount()
  {
    $amount = '1.23e-6';
    $from = ['currency' => 'EUR', 'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'];
    $to =  [ 'currency' => 'USD', 'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'];
    $offersToFrom = [
      ['100','10'],
    ];
    $offersFromTo = [
      ['10','100'],
    ];
    $result = $this->calculate($amount,$from, $to, $offersFromTo, $offersToFrom);
    # Debug
    if(self::DEBUG) {
      $this->renderTable($result);
      echo 'Rate: '.$result['rate'].' Safe: '.(int)$result['safe'];
      echo ' | '.\implode(' | ', $result['errors']);
    }
    # End Debug

    $this->assertEquals(10,$result['rate']);
    $this->assertFalse($result['safe']);
    $this->assertEquals([
      'REQUESTED_LIQUIDITY_NOT_AVAILABLE',
      'REVERSE_LIQUIDITY_NOT_AVAILABLE',
    ],$result['errors']);

    $this->assertEquals('0.00000012300000000',  (string)$result['books'][1][0]['_I_Get_Capped']);
    $this->assertEquals( 0.000000123,           (string)$result['books'][1][0]['_I_Get_Capped']);
    $this->assertEquals((float)'1.23e-7',       (string)$result['books'][1][0]['_I_Get_Capped']);

    $this->assertEquals('0.00000123000000000',  (string)$result['books'][1][0]['_I_Spend_Capped']);
    $this->assertEquals( 0.00000123,            (string)$result['books'][1][0]['_I_Spend_Capped']);
    $this->assertEquals((float)'1.23e-6',       (string)$result['books'][1][0]['_I_Spend_Capped']);
  }

  public function testCalculatedTradesIGetCappedRoundedCorrectly()
  {
    $amount = 1;
    $from = ['currency' => 'EUR', 'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'];
    $to =  [ 'currency' => 'USD', 'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'];
    $offersToFrom = [
      ['222','10'],
    ];
    $offersFromTo = [
      ['10','100'],
    ];
    $result = $this->calculate($amount,$from, $to, $offersFromTo, $offersToFrom);
    # Debug
    if(self::DEBUG) {
      $this->renderTable($result);
      echo 'Rate: '.$result['rate'].' Safe: '.(int)$result['safe'];
      echo ' | '.\implode(' | ', $result['errors']);
    }
    # End Debug

    $this->assertEquals(10,$result['rate']);
    $this->assertFalse($result['safe']);
    $this->assertEquals([
      'REQUESTED_LIQUIDITY_NOT_AVAILABLE',
      'REVERSE_LIQUIDITY_NOT_AVAILABLE',
      'MAX_SPREAD_EXCEEDED'
    ],$result['errors']);

    // _I_Get_Capped === 0.04504504504504504500, rounded down to LiquidityParser::NATURAL_PRECISION equals to:
    $this->assertEquals('0.04504504504504505', (string)$result['books'][1][0]['_I_Get_Capped']);
  }

  public function testCalculatedTradesShouldBeInEquilibrium()
  {
    $amount = 1;
    $from = ['currency' => 'EUR', 'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'];
    $to =  [ 'currency' => 'USD', 'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'];
    $offersToFrom = [
      ['1','1'],
    ];
    $offersFromTo = [
      ['1','1'],
    ];
    $result = $this->calculate($amount,$from, $to, $offersFromTo, $offersToFrom);
    # Debug
    if(self::DEBUG) {
      $this->renderTable($result);
      echo 'Rate: '.$result['rate'].' Safe: '.(int)$result['safe'];
      echo ' | '.\implode(' | ', $result['errors']);
    }
    # End Debug

    $this->assertEquals(1,$result['rate']);
    $this->assertTrue($result['safe']);
    $this->assertEquals(0,count($result['errors']));
  }

  /**
   * Calculates mock data
   * @return array [rate,safe,errors,books]
   */
  public function calculate(string|float|int $amount, array $from, array $to, array $offersFromTo, array $offersToFrom): array
  {
    # Debug
    if(self::DEBUG) {
      //JS Vars (for node testing):
      $jsvars1 = \json_decode($this->buildBookOffersJson([$to,$from],$offersFromTo));
      $jsvars1 = \json_encode($jsvars1->result->offers);
      $jsvars2 = \json_decode($this->buildBookOffersJson([$from,$to],$offersToFrom));
      $jsvars2 = \json_encode($jsvars2->result->offers);
      echo 'let bookData2 = [';
      echo $jsvars1.','.PHP_EOL.$jsvars2;
      echo ']';
      echo PHP_EOL.PHP_EOL;
    }
    # End Debug
    
    $mock = new MockHandler([
      new Response(200, ['Content-Type' => 'application/json'], $this->buildBookOffersJson([$to,$from],$offersFromTo)),
      new Response(200, ['Content-Type' => 'application/json'], $this->buildBookOffersJson([$from,$to],$offersToFrom)),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $httpClient = new HttpClient(['handler' => $handlerStack]);
    $client = new Client([],$httpClient);
    $lc = new LiquidityCheck([
      # Trade:
      'from' => $from,
      'amount' => $amount,
      'to' => $to
    ],
    [
      'rates' => 'to',
      'includeBookData' => true,
    ],
    $client);
    return $lc->get();
  }

  /**
   * Returns Offer minimum compatible mock JSON response
   * @return string JSON
   */
  public function buildBookOffersJson(array $from_to, array $offer_data): string
  {
    $offers = [];
    foreach($offer_data as $k => $v)
    {
      $TakerGets = [];
      if(count($from_to[1]) === 1)
        $TakerGets = (string)($v[1]*1000000); //xrp to drops
      else 
        $TakerGets = ['currency' => $from_to[1]['currency'], 'issuer' => $from_to[1]['issuer'],'value' => $v[1]];

      $TakerPays = [];
      if(count($from_to[0]) === 1)
        $TakerPays = (string)($v[0]*1000000); //xrp to drops
      else
        $TakerPays = ['currency' => $from_to[0]['currency'], 'issuer' => $from_to[0]['issuer'], 'value' => $v[0]];

      $offers[] = [
        'BookDirectory' => "BD".$k.$v[1],
        'TakerGets' => $TakerGets,
        'TakerPays' => $TakerPays,
      ];
    }

    $r = [
      'result' => [
        'ledger_current_index' => 8696243,
        'offers' => $offers,
        'status' => 'success',
        'validated' => true
      ],
    ];
    return \json_encode($r);
  }

  /**
   * Renders book data to console for debugging
   * @return void
   */
  public function renderTable(array $data): void
  {
    foreach($data['books'] as $books)
    {
      $table = new \LucidFrame\Console\ConsoleTable();
      $i = 0;
      foreach($books as $book) {
        if($i == 0) {
          foreach(array_keys($book) as $title){
            $table->addHeader($title);
          }
        }
        $table->addRow();
        foreach($book as $v) {
          if($v instanceof \Brick\Math\BigDecimal || !is_object($v)) {
            if(is_bool($v))
              $table->addColumn(($v?'true':'false'));
            else
              $table->addColumn((string)$v);
            
          } else {
            $table->addColumn(
              'Value: '.$v->value
            );
          }
        }
        $i++;
      }
      $table->display();
    }
  }
}