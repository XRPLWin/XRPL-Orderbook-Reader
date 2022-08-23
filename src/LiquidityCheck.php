<?php declare(strict_types=1);

namespace XRPLWin\XRPLOrderbookReader;
use XRPLWin\XRPL\Client as XRPLWinClient;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
#use GuzzleHttp\Promise as P;
#use GuzzleHttp\Promise\Promise;
#use GuzzleHttp\Promise\EachPromise;
use Amp\Parallel\Worker;
use Amp\Promise;
use function Amp\ParallelFunctions\parallelMap;
use function Amp\Promise\wait;
use Amp\Parallel\Worker\DefaultPool;

class LiquidityCheck
{
  const ERROR_REQUESTED_LIQUIDITY_NOT_AVAILABLE = 'REQUESTED_LIQUIDITY_NOT_AVAILABLE';
  const ERROR_REVERSE_LIQUIDITY_NOT_AVAILABLE = 'REVERSE_LIQUIDITY_NOT_AVAILABLE';
  const ERROR_MAX_SPREAD_EXCEEDED = 'MAX_SPREAD_EXCEEDED';
  const ERROR_MAX_SLIPPAGE_EXCEEDED = 'MAX_SLIPPAGE_EXCEEDED';
  const ERROR_MAX_REVERSE_SLIPPAGE_EXCEEDED = 'MAX_REVERSE_SLIPPAGE_EXCEEDED';

  const PRECISION = 20;
  /**
   * PRECISION_CAPPED:
   * Due to divisions, calculated values must be rounded to PRECISION, 
   * sum of fields _I_Spend_Capped and _I_Get_Capped can be very close approximation of 
   * TradeAmount. To create correct comparison of TradeAmount === _I_Spend_Capped or _I_Get_Capped,
   * those fields are rounded to ROUNDING_MODE of PRECISION_CAPPED precision.
   */
  const PRECISION_CAPPED = 10;
  const ROUNDING_MODE = RoundingMode::HALF_UP;

  protected XRPLWinClient $client;

  /**
   * @var array $trade
   * [
   *    'from' => ['currency' => 'USD', 'issuer' (optional if XRP) => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'],
   *    'to'   => ['currency' => 'EUR', 'issuer' (optional if XRP) => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'],
   *    'amount' => 500,
   *    'limit' => 200
   * ]
   */
  private array $trade;

  private array $options_default = [
    'rates' => 'to',
    'maxSpreadPercentage' => 4, //4
    'maxSlippagePercentage' => 3, //3
    'maxSlippagePercentageReverse' => 3, //3
    'includeBookData' => false
  ];

  private array $options;
  private array $book;
  private array $bookReverse;
  private bool $bookExecuted = false;
  private bool $bookReverseExecuted = false;
  
  public function __construct(array $trade, array $options, XRPLWinClient $client)
  {
    $this->client = $client;
    $this->trade = $trade;
    
    //Check $trade array
    if(count($this->trade) != 3)
      throw new \Exception('Invalid trade parameters');
    if(!isset($this->trade['from']) || !isset($this->trade['to']) || !isset($this->trade['amount']))
      throw new \Exception('Invalid trade parameters required parameters are from, to, amount and (int)limit');
    if(!is_array($this->trade['from']) || !is_array($this->trade['to']))
      throw new \Exception('Invalid trade parameters from and to must be array');
    if(!isset($this->trade['from']['currency']) || !isset($this->trade['to']['currency']))
      throw new \Exception('Invalid trade parameters from and to must have currency defined');
    if($this->trade['from']['currency'] != 'XRP' && !isset($this->trade['from']['issuer']))
      throw new \Exception('Invalid trade parameters from.issuer is not defined');
    if($this->trade['to']['currency'] != 'XRP' && !isset($this->trade['to']['issuer']))
      throw new \Exception('Invalid trade parameters to.issuer is not defined');
    if($this->trade['from'] === $this->trade['to'])
      throw new \Exception('Invalid trade parameters they can not be the same');

    \ksort($this->trade['from']);
    \ksort($this->trade['to']);


    $options = array_merge($this->options_default,$options);
    if($options['rates'] != 'from' && $options['rates'] != 'to')
      throw new \Exception('Options rates can be from or to only');
    $this->options = $options;
  }

  public function fetchBookPromise($reverse = false)
  {
    $trade = $this->trade;
    $options = $this->options;
    $client = $this->client;
    //($reverse = false, array $trade, array $options, $client)
    $promise = new Promise(
      function() use (&$promise,$reverse,$trade,$options,$client) {
        
        #$response = self::fetchBook($reverse,$trade,$options,$client);
        #$promise->resolve($response);
        sleep(5);
        $promise->resolve(\date('Y-m-d H:i:s'));
      }//,
      //function (&$promise) {
        // do something that will cancel the promise computation (e.g., close
        // a socket, cancel a database query, etc...)
        //$promise->reject(null); // just to know which index has failed or rejected.
      //}
    );
    return $promise;
  }

  
  /**
   * Fetches orderbook and reverse orderbook then calculates exchange rate, checks for errors.
   * @return array [rate,safe,errors]
   */
  public function get(): array
  {
    //https://github.com/amphp/parallel/issues/130#issuecomment-813536102
    /*for ($i = 0; $i < 30; $i++) {
        $pool = new DefaultPool();

        $promises = parallelMap(range(1, 50), function () {
            return 2;
        }, $pool);

        $result = wait($promises);
    }
    exit;*/

    $urls = [
        'https://secure.php.net',
        'https://amphp.org',
        'https://github.com',
    ];

    $trade = $this->trade;
    $options = $this->options;
    $client = $this->client;

    /*$a =  new \Opis\Closure\SerializableClosure(function($trade, $options, $client){
      return \XRPLWin\XRPLOrderbookReader\BookFetcher::fetchBook(false, $trade, $options, $client);
    });*/
    /*\Laravel\SerializableClosure\SerializableClosure::setSecretKey('secret');
    $a = new \Laravel\SerializableClosure\SerializableClosure(function($trade, $options, $client){
      return \XRPLWin\XRPLOrderbookReader\BookFetcher::fetchBook(false, $trade, $options, $client);
    });*/

    $promises = [
      Worker\enqueueCallable( '\\XRPLWin\\XRPLOrderbookReader\\BookFetcher::fetchBook',false, $trade, $options, $client),
      Worker\enqueueCallable( '\\XRPLWin\\XRPLOrderbookReader\\BookFetcher::fetchBook',true, $trade, $options, $client),
      //Worker\enqueueCallable('\XRPLWin\XRPLOrderbookReader\BookFetcher::fetchBook', false, $this->trade, $this->options, $this->client),
    ];

    $responses = Promise\wait(Promise\all($promises));
    dump($responses);
    exit;
    foreach ($responses as $url => $response) {
        \printf("Read %d bytes from %s\n", \strlen($response), $url);
    }
    exit;
    ################### END

    //READ: https://medium.com/@ardanirohman/how-to-handle-async-request-concurrency-with-promise-in-guzzle-6-cac10d76220e


    $promiseList = [
      $this->fetchBookPromise(),
      $this->fetchBookPromise(true)
    ];
    $promises = (function () use ($promiseList) {
      foreach ($promiseList as $p) {
        // don't forget using generator
        yield $p;//$this->getAsync('https://api.demo.com/v1/users?username=' . $user);		
      }
    })();
    
    
    $eachPromise = new EachPromise($promises, [
      // how many concurrency we are use
      'concurrency' => 2,
      'fulfilled' => function (string $response) {
          dump($response);
        },
      'rejected' => function ($reason) {
        // handle promise rejected here
      }
    ]);
    
    $eachPromise->promise()->wait();













    ###################### 
    exit;
    //$called = 0;
    //$rejected = 0;

    $then = microtime(true);

    $promises = [
      $this->fetchBookPromise(),
      $this->fetchBookPromise(true)
    ];
   
    $each = new EachPromise($promises, [
      //'concurrency' => 5,
      'fulfilled' => function ($response,$index) use (&$called) { $called++; dump($response); },
      'rejected' => function($reason) use (&$rejected) {$rejected++;}
    ]);
    //$results = P\Utils::inspectAll( $promises );
    //dump($results);exit;
    $promiseResults = P\Utils::all($promises)->wait();//$each->promise()->wait(); //see unwrap
    //$promiseResults = $each->promise()->wait();
    echo 'Took: ' . (microtime(true) - $then);
    dump($promiseResults);exit;
    foreach ($promiseResults as $k => $v) {
      dump($v['value']);exit;
      //$response = value['value']->getBody()->getContents();
      //$header = value['value']->getHeader();
  }


    dump($called,$rejected, $p);exit;
    dump($called);



    $promises = [];
    $promise1 = new Promise();
    $promise2 = new Promise();
    $called = [];
    $each = new \GuzzleHttp\Promise\EachPromise($promises, [
      'fulfilled' => function ($value) use (&$called) {
          $called[] = $value;
      }
  ]);
  die('35');

    $this->fetchBook();
    $this->fetchBook(true);
    $book1 = LiquidityParser::parse($this->book,        $this->trade['from'], $this->trade['to'], $this->trade['amount'], $this->options['rates']);
    $book2 = LiquidityParser::parse($this->bookReverse, $this->trade['from'], $this->trade['to'], $this->trade['amount'], ($this->options['rates'] == 'to' ? 'from':'to'));
    dump($book1,$book2);
    $errors = $this->detectErrors($book1,$book2);
    $finalBookLine = (count($book1)) ? \end($book1) : null;

    if($finalBookLine === null)
      $rate = 0;
    else
      $rate = ($finalBookLine['_CumulativeRate_Cap']) ? $finalBookLine['_CumulativeRate_Cap'] : $finalBookLine['_CumulativeRate'];

    $r = [
      'rate' => (string)$rate,
      'safe' => (count($errors) == 0),
      'errors' => $errors,
    ];

    if($this->options['includeBookData'])
      $r['books'] = [$book1,$book2];

    return $r;
  }

  /**
   * Clears results and resets instance.
   * @return self
   */
  public function reset()
  {
    $this->book = [];
    $this->bookReverse = [];
    $this->bookExecuted = false;
    $this->bookReverseExecuted = false;
    return $this;
  }

  /**
   * Queries XRPL and gets results of book_offers
   * Note that book_offers does not have pagination built in.
   * Fills $this->book or $this->bookReverse (if $reverse = true)
   * @throws \XRPLWin\XRPL\Exceptions\XWException
   * @return void
   */
  public static function fetchBook($reverse = false, array $trade, array $options, $client)
  {
    if($trade['from'] === $trade['to'])
      return;

    if(!$reverse) {
      $from = $trade['from'];
      $to = $trade['to'];
    } else {
      $from = $trade['to'];
      $to = $trade['from'];
    }
    

    /** @var \XRPLWin\XRPL\Methods\BookOffers */
    $orderbook = $client->api('book_offers')->params([
      'taker_gets' => $to,
      'taker_pays' => $from,
      'limit' => $options['maxBookLines']
    ]);

    try {
      $orderbook->send();
    } catch (\XRPLWin\XRPL\Exceptions\XWException $e) {
        // Handle errors
        throw $e;
    }

    if(!$orderbook->isSuccess()) {
      //XRPL response is returned but field result.status did not return 'success'

      if(isset($orderbook->result()->result->error_message))
        throw new \Exception($orderbook->result()->result->error_message);
      else
        throw new \Exception(\json_encode($orderbook->result()));
      return;
    }
    return $orderbook->finalResult(); //array response from ledger 
  }

  /**
   * Queries XRPL and gets results of book_offers
   * Note that book_offers does not have pagination built in.
   * Fills $this->book or $this->bookReverse (if $reverse = true)
   * @throws \XRPLWin\XRPL\Exceptions\XWException
   * @return void
   */
  /*private function fetchBook($reverse = false)
  {
    if($this->trade['from'] === $this->trade['to'])
      return;

    //prevent re-querying
    if(!$reverse && $this->bookExecuted) 
      return;
    else if($this->bookReverseExecuted)
      return;

    if(!$reverse) {
      $from = $this->trade['from'];
      $to = $this->trade['to'];
    } else {
      $from = $this->trade['to'];
      $to = $this->trade['from'];
    }
    

    // @var \XRPLWin\XRPL\Methods\BookOffers 
    $orderbook = $this->client->api('book_offers')->params([
      'taker_gets' => $to,
      'taker_pays' => $from,
      'limit' => $this->options['maxBookLines']
    ]);

    try {
      $orderbook->send();
    } catch (\XRPLWin\XRPL\Exceptions\XWException $e) {
        // Handle errors
        throw $e;
    }

    if(!$orderbook->isSuccess()) {
      //XRPL response is returned but field result.status did not return 'success'

      if(isset($orderbook->result()->result->error_message))
        throw new \Exception($orderbook->result()->result->error_message);
      else
        throw new \Exception(\json_encode($orderbook->result()));
      return;
    }

    if(!$reverse) {
      $this->book = $orderbook->finalResult(); //array response from ledger
      $this->bookExecuted = true;
      
    } else {
      $this->bookReverse = $orderbook->finalResult(); //array response from ledger
      $this->bookReverseExecuted = true;
    }
  }*/

  /**
   * Detects errors
   * @param array $book
   * @param array $bookReversed
   * @return array of errors
   */
  private function detectErrors(array $book, array $bookReversed): array
  {
    //dump(count($this->book),count($bookReversed));
    # Check for orders existance
    $errors = [];
    if(!count($book)) {
      $errors[] = self::ERROR_REQUESTED_LIQUIDITY_NOT_AVAILABLE;
      return $errors;
    }
    if(!count($bookReversed)) {
      $errors[] = self::ERROR_REVERSE_LIQUIDITY_NOT_AVAILABLE;
      return $errors;
    }

    # Prepeare parameters
    $amount = $this->trade['amount'];

    $bookAmount = $book[count($book)-1]['_I_Spend_Capped'];
    $bookReversedAmount = $bookReversed[count($bookReversed)-1]['_I_Get_Capped'];
    //dump($book[count($book)-1], $bookReversed[count($bookReversed)-1]);
    $firstBookLine = $book[0];
    $finalBookLine = \end($book);

    /** @var \Brick\Math\BigDecimal */
    $startRate = ($firstBookLine['_CumulativeRate_Cap']) ? $firstBookLine['_CumulativeRate_Cap'] : $firstBookLine['_CumulativeRate'];
    /** @var \Brick\Math\BigDecimal */
    $finalRate = ($finalBookLine['_CumulativeRate_Cap']) ? $finalBookLine['_CumulativeRate_Cap'] : $finalBookLine['_CumulativeRate'];
    
    $firstBookLineReverse = $bookReversed[0];
    $finalBookLineReverse = \end($bookReversed);
    
    /** @var \Brick\Math\BigDecimal */
    $startRateReverse = ($firstBookLineReverse['_CumulativeRate_Cap']) ? $firstBookLineReverse['_CumulativeRate_Cap'] : $firstBookLineReverse['_CumulativeRate'];
    /** @var \Brick\Math\BigDecimal */
    $finalRateReverse = ($finalBookLineReverse['_CumulativeRate_Cap']) ? $finalBookLineReverse['_CumulativeRate_Cap'] : $finalBookLineReverse['_CumulativeRate'];
   
    # Check for errors
    if(!$bookAmount->toScale(self::PRECISION_CAPPED,self::ROUNDING_MODE)->isEqualTo($amount)) {
      $errors[] = self::ERROR_REQUESTED_LIQUIDITY_NOT_AVAILABLE;
    }
    
    //echo (string)$amount.' = '.((string)$bookAmount);
    //print_r(\end($bookReversed)['_I_Get_Capped']);
    //exit;
    if(!$bookReversedAmount->toScale(self::PRECISION_CAPPED,self::ROUNDING_MODE)->isEqualTo($amount)) {
      $errors[] = self::ERROR_REVERSE_LIQUIDITY_NOT_AVAILABLE;
    }

    if($this->options['maxSpreadPercentage']) {
      $spread = BigDecimal::one()->minus(  $startRate->dividedBy($startRateReverse,self::PRECISION,self::ROUNDING_MODE) )->multipliedBy(100)->abs();

      //todo: log

      if($spread->isGreaterThan($this->options['maxSpreadPercentage']))
        $errors[] = self::ERROR_MAX_SPREAD_EXCEEDED;
    }

    if($this->options['maxSlippagePercentage']) {
      $slippage = BigDecimal::one()->minus(  $startRate->dividedBy($finalRate,self::PRECISION,self::ROUNDING_MODE) )->multipliedBy(100)->abs();
      //die((string)$finalRate);
      //todo: log

      if($slippage->isGreaterThan($this->options['maxSlippagePercentage']))
        $errors[] = self::ERROR_MAX_SLIPPAGE_EXCEEDED;
    }

    if($this->options['maxSlippagePercentageReverse']) {
      $slippage = BigDecimal::one()->minus(  $startRateReverse->dividedBy($finalRateReverse,self::PRECISION,self::ROUNDING_MODE) )->multipliedBy(100)->abs();

      //todo: log

      if($slippage->isGreaterThan($this->options['maxSlippagePercentageReverse']))
        $errors[] = self::ERROR_MAX_REVERSE_SLIPPAGE_EXCEEDED;
    }
    return $errors;
  }
}
