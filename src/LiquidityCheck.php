<?php declare(strict_types=1);

namespace XRPLWin\XRPLOrderbookReader;
use XRPLWin\XRPL\Client as XRPLWinClient;
use XRPLWin\XRPL\Exceptions\BadRequestException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use GuzzleHttp\Promise as P;


class LiquidityCheck
{
  const ERROR_REQUESTED_LIQUIDITY_NOT_AVAILABLE = 'REQUESTED_LIQUIDITY_NOT_AVAILABLE';
  const ERROR_REVERSE_LIQUIDITY_NOT_AVAILABLE = 'REVERSE_LIQUIDITY_NOT_AVAILABLE';
  const ERROR_MAX_SPREAD_EXCEEDED = 'MAX_SPREAD_EXCEEDED';
  const ERROR_MAX_SLIPPAGE_EXCEEDED = 'MAX_SLIPPAGE_EXCEEDED';
  const ERROR_MAX_REVERSE_SLIPPAGE_EXCEEDED = 'MAX_REVERSE_SLIPPAGE_EXCEEDED';

  const NATURAL_PRECISION = 17;
  const ROUNDING_MODE = RoundingMode::HALF_UP;

  protected XRPLWinClient $client;

  /**
   * @var array $trade
   * [
   *    'from' => ['currency' => 'USD', 'issuer' (optional if XRP) => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'],
   *    'to'   => ['currency' => 'EUR', 'issuer' (optional if XRP) => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'],
   *    'amount' => 500,
   * ]
   */
  private array $trade;

  private array $options_default = [
    'rates' => 'to',
    'maxSpreadPercentage' => 4, //4
    'maxSlippagePercentage' => 3, //3
    'maxSlippagePercentageReverse' => 3, //3
    'includeBookData' => false,
    'maxBookLines' => 500
  ];

  private array $options;
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

  
  /**
   * Fetches orderbook and reverse orderbook then calculates exchange rate, checks for errors.
   * @throws \Exception
   * @return array [rate,safe,errors]
   */
  public function get(): array
  {
    $orderbook = $this->fetchBook();
    $orderbookReverse = $this->fetchBook(true);

    $promises = [
      'fwd' => $orderbook->requestAsync(),
      'rev' => $orderbookReverse->requestAsync()
    ];

    $promiseResults = P\Utils::all($promises)->wait();

    try {
      $orderbook->fill($promiseResults['fwd']);
    } catch (BadRequestException) {
      //
    }

    try {
      $orderbookReverse->fill($promiseResults['rev']);
    } catch (BadRequestException) {
      //
    }
  
    if(!$orderbook->isSuccess()) {
      if($orderbook->getIsExecutedWithError())
        throw new \Exception('Http request failed with status code '.$orderbook->getExecutedWithErrorCode());
      else {
        if(isset($orderbook->result()->result->error_message))
          throw new \Exception($orderbook->result()->result->error_message);
        else
          throw new \Exception(\json_encode($orderbook->result()));
      }
    }

    if(!$orderbookReverse->isSuccess()) {
      if($orderbookReverse->getIsExecutedWithError())
        throw new \Exception('Http request failed with status code '.$orderbookReverse->getExecutedWithErrorCode());
      else {
        if(isset($orderbookReverse->result()->result->error_message))
          throw new \Exception($orderbookReverse->result()->result->error_message);
        else
          throw new \Exception(\json_encode($orderbookReverse->result()));
      }
    }

    $book = $orderbook->finalResult(); //array response from ledger
    $bookReverse = $orderbookReverse->finalResult(); //array response from ledger

    $book1 = LiquidityParser::parse($book,        $this->trade['from'], $this->trade['to'], $this->trade['amount'], $this->options['rates']);
    $book2 = LiquidityParser::parse($bookReverse, $this->trade['from'], $this->trade['to'], $this->trade['amount'], ($this->options['rates'] == 'to' ? 'from':'to'));
    
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
    return $this;
  }

  /**
   * Returns Promise to query book_offers
   * @return XRPLWin\XRPL\Api\Methods\BookOffers
   */
  public function fetchBook(bool $reverse = false)
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

    $orderbook = $this->client->api('book_offers')->params([
      'taker_gets' => $to,
      'taker_pays' => $from,
      'limit' => $this->options['maxBookLines']
    ]);

    return $orderbook;
  }

  /**
   * Detects errors
   * @param array $book
   * @param array $bookReversed
   * @return array of errors
   */
  private function detectErrors(array $book, array $bookReversed): array
  {
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
    if(!$bookAmount->isEqualTo($amount)) {
      $errors[] = self::ERROR_REQUESTED_LIQUIDITY_NOT_AVAILABLE;
    }
    
    if(!$bookReversedAmount->isEqualTo($amount)) {
      $errors[] = self::ERROR_REVERSE_LIQUIDITY_NOT_AVAILABLE;
    }

    if($this->options['maxSpreadPercentage']) {
      $spread = BigDecimal::one()->minus(  $startRate->dividedBy($startRateReverse,self::NATURAL_PRECISION,self::ROUNDING_MODE) )->multipliedBy(100)->abs();

      //todo: log

      if($spread->isGreaterThan($this->options['maxSpreadPercentage']))
        $errors[] = self::ERROR_MAX_SPREAD_EXCEEDED;
    }

    if($this->options['maxSlippagePercentage']) {
      $slippage = BigDecimal::one()->minus(  $startRate->dividedBy($finalRate,self::NATURAL_PRECISION,self::ROUNDING_MODE) )->multipliedBy(100)->abs();

      //todo: log

      if($slippage->isGreaterThan($this->options['maxSlippagePercentage']))
        $errors[] = self::ERROR_MAX_SLIPPAGE_EXCEEDED;
    }

    if($this->options['maxSlippagePercentageReverse']) {
      $slippage = BigDecimal::one()->minus(  $startRateReverse->dividedBy($finalRateReverse,self::NATURAL_PRECISION,self::ROUNDING_MODE) )->multipliedBy(100)->abs();

      //todo: log

      if($slippage->isGreaterThan($this->options['maxSlippagePercentageReverse']))
        $errors[] = self::ERROR_MAX_REVERSE_SLIPPAGE_EXCEEDED;
    }
    return $errors;
  }
}
