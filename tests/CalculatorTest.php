<?php declare(strict_types=1);

namespace XRPLWin\XRPLOrderbookReader\Tests;

use PHPUnit\Framework\TestCase;
use XRPLWin\XRPL\Client;
use XRPLWin\XRPL\Client\Guzzle\HttpClient;
use XRPLWin\XRPLOrderbookReader\LiquidityCheck;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7;

use function PHPSTORM_META\map;

#use GuzzleHttp\Middleware;


final class CalculatorTest extends TestCase
{
  public function testCalculatedTradesShouldBeInEquilibrium()
  {
    $from = ['currency' => 'EUR', 'issuer' => 'rb'];
    $to =  [ 'currency' => 'USD', 'issuer' => 'ra'];
    $offersFromTo = [
      //with 22 xrp buy 333 usd
      ['22','333'],
      ['22','333'],
      ['22','333'],
      ['22','333'],
    ];
    $offersToFrom = [
      //with 88 usd buy 22 xrp
      ['333','22'],
      ['333','22'],
      ['333','22'],
      ['333','22'],
    ];
    $result = $this->calculate('800',$from, $to, $offersFromTo, $offersToFrom);

    # Debug
    $this->renderTable($result);
    echo 'Rate: '.$result['rate'].' Safe: '.(int)$result['safe'];
    echo ' | '.\implode(' | ', $result['errors']);
    # End Debug
    exit;

    $this->assertEquals(10,$result['rate']);
  }

  /**
   * Calculates mock data
   * @return array [rate,safe,errors]
   */
  public function calculate(string $amount, array $from, array $to, array $offersFromTo, array $offersToFrom): array
  {
    dump($this->buildBookOffersJson([$to,$from],$offersFromTo));
    dump($this->buildBookOffersJson([$from,$to],$offersToFrom));
    
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
      'includeBookData' => true,
    ],
    $client);

    //dump($lc->get());exit;
    return $lc->get();
  }

  /**
   * Returns Offer minimum compatible mock response
   * @return string JSON
   */
  public function buildBookOffersJson(array $from_to, array $offer_data): string
  {
    $offers = [];
    foreach($offer_data as $v)
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
   * Renders book data to console
   * @return void
   */
  private function renderTable(array $data): void
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