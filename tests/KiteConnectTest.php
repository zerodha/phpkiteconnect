<?php

namespace KiteConnect\Tests;

use GuzzleHttp\Client;
use KiteConnect\KiteConnect;
use PHPUnit\Framework\TestCase;

/** @package KiteConnect\Tests */
class KiteConnectTest extends TestCase
{
    /** 
    * Setup objects to be used through out the tests 
    */ 
    protected function setUp(): void
    {
        parent::setUp();
    }
    /**
     * @test
     * @return void
     */
    public function test_login_url_is_generated_correctly(): void
    {
        $kiteConnect = new KiteConnect('token');
        $actualLoginUrl = $kiteConnect->getLoginURL();
        $expectedLoginUrl = 'https://kite.trade/connect/login?api_key=token&v=3';
        $this->assertEquals($expectedLoginUrl, $actualLoginUrl);
    }

    /**
     * @test
     * @return void
     */
    public function test_login_url_is_string(): void
    {
        $kiteConnect = new KiteConnect('token');
        $loginUrl = $kiteConnect->getLoginURL();
        $this->assertIsString($loginUrl);
    }

    /**
     * @test
     * @return void
     */
    public function it_can_be_instantiated_with_token(): void
    {
        $kiteConnect = new KiteConnect('token');
        $this->assertInstanceOf(KiteConnect::class, $kiteConnect);
    }

    /**
     * @test
     * @return void
     */
    public function it_can_be_instantiated_with_token_and_access_token(): void
    {
        $kiteConnect = new KiteConnect('token', 'access_token');
        $this->assertInstanceOf(KiteConnect::class, $kiteConnect);
    }

    /**
     * @test Mock intialization
     */
    public function initializeMock()
    {
        $timeout = 7;
        $mock_data = new MockJson();
        // Create a mock and queue required responses.
        $client = new Client(['handler' => $mock_data->generateMock()]);
        // Inject guzzleClient
        $kiteConnect = new KiteConnect('api_key', 'access_token', NULL, false, $timeout, $client);
        $this->assertNotNull($kiteConnect); 
        
        return $kiteConnect;
    }

    /** 
     * @depends initializeMock
     * @test getProfile 
    */
    public function getProfileTest($kiteConnect): void
    {
        $response = $kiteConnect->getProfile();
        
        $this->assertObjectHasProperty('user_id',$response);
        $this->assertObjectHasProperty('user_name',$response);
        $this->assertObjectHasProperty('exchanges',$response);
        $this->assertObjectHasProperty('meta',$response);
    }

    /** 
     * @depends initializeMock
     * @test getMargins 
    */
    public function getMarginsTest($kiteConnect): void
    {
        $response = $kiteConnect->getMargins();
        
        $this->assertObjectHasProperty('equity',$response);
        $this->assertObjectHasProperty('commodity',$response);
    }

    /** 
     * @depends initializeMock
     * @test getQuote 
    */
    public function getQuoteTest($kiteConnect): void
    {
        $response = $kiteConnect->getQuote(['NSE:INFY']);

        $this->assertArrayHasKey('NSE:INFY', $response);

        foreach ($response as $values) {
            $this->assertObjectHasProperty('instrument_token',$values);
            $this->assertObjectHasProperty('ohlc',$values);
            $this->assertObjectHasProperty('depth',$values);
        }
    }

    /** 
     * @depends initializeMock
     * @test getOHLC 
    */
    public function getOHLCTest($kiteConnect): void
    {
        $response = $kiteConnect->getOHLC(['NSE:INFY']);

        $this->assertObjectHasProperty('NSE:INFY', $response);
        
        foreach ($response as $values) {
            $this->assertObjectHasProperty('instrument_token',$values);
            $this->assertObjectHasProperty('ohlc',$values);
            $this->assertObjectHasProperty('last_price',$values);
        }
    }

    /** 
     * @depends initializeMock
     * @test getLTP 
    */
    public function getLTPTest($kiteConnect): void
    {
        $response = $kiteConnect->getLTP(['NSE:INFY']);

        $this->assertObjectHasProperty('NSE:INFY', $response);
        
        foreach ($response as $values) {
            $this->assertObjectHasProperty('instrument_token',$values);
            $this->assertObjectHasProperty('last_price',$values);
        }
    }

    /** 
     * @depends initializeMock
     * @test getHoldings 
    */
    public function getHoldingsTest($kiteConnect): void
    {
        $response = $kiteConnect->getHoldings();

        foreach ($response as $values) {
            $this->assertObjectHasProperty('tradingsymbol',$values);
            $this->assertObjectHasProperty('exchange',$values);
            $this->assertObjectHasProperty('pnl',$values);
        }
    }

    /** 
     * @depends initializeMock
     * @test getPositions 
    */
    public function getPositionsTest($kiteConnect): void
    {
        $response = $kiteConnect->getPositions();
        $this->assertObjectHasProperty('net',$response);

        foreach ($response as $values) {
            foreach ($values as $value2){
                $this->assertObjectHasProperty('tradingsymbol',$value2);
                $this->assertObjectHasProperty('exchange',$value2);
                $this->assertObjectHasProperty('average_price',$value2);
            }
        }

    }

    /** 
     * @depends initializeMock
     * @test placeOrder 
    */
    public function placeOrderTest($kiteConnect): void
    {
        $response = $kiteConnect->placeOrder("regular", [
            "tradingsymbol" => "INFY",
            "exchange" => "NSE",
            "quantity" => 1,
            "transaction_type" => "BUY",
            "order_type" => "MARKET",
            "product" => "NRML"
        ]);

        $this->assertObjectHasProperty('order_id',$response);

    }

    /** 
     * @depends initializeMock
     * @test getOrders 
    */
    public function getOrdersTest($kiteConnect): void
    {
        $response = $kiteConnect->getOrders();

        foreach ($response as $values) {
            $this->assertObjectHasProperty('order_id',$values);
            $this->assertObjectHasProperty('exchange_timestamp',$values);
            $this->assertObjectHasProperty('status',$values);
            $this->assertObjectHasProperty('order_id',$values);
        }

    }

    /** 
     * @depends initializeMock
     * @test getOrderHistory 
    */
    public function getOrderHistoryTest($kiteConnect): void
    {
        $response = $kiteConnect->getOrderHistory('123456789');

        foreach ($response as $values) {
            $this->assertObjectHasProperty('order_id',$values);
            $this->assertObjectHasProperty('exchange_timestamp',$values);
            $this->assertObjectHasProperty('status',$values);
        }

    }

    /** 
     * @depends initializeMock
     * @test getOrderTrades 
    */
    public function getOrderTradesTest($kiteConnect): void
    {
        $response = $kiteConnect->getOrderTrades('123456789');

        foreach ($response as $values) {
            $this->assertObjectHasProperty('average_price',$values);
            $this->assertObjectHasProperty('transaction_type',$values);
            $this->assertObjectHasProperty('order_timestamp',$values);
            $this->assertObjectHasProperty('order_id',$values);
        }

    }

    /** 
     * @depends initializeMock
     * @test getTrades 
    */
    public function getTradesTest($kiteConnect): void
    {
        $response = $kiteConnect->getTrades();

        foreach ($response as $values) {
            $this->assertObjectHasProperty('trade_id',$values);
            $this->assertObjectHasProperty('exchange_order_id',$values);
            $this->assertObjectHasProperty('order_id',$values);
            $this->assertObjectHasProperty('instrument_token',$values);
        }

    }

    /** 
     * @depends initializeMock
     * @test placeGTT 
    */
    public function placeGTTTest($kiteConnect): void
    {
        $response = $kiteConnect->placeGTT([
            "trigger_type" => $kiteConnect::GTT_TYPE_SINGLE,
            "tradingsymbol" => "TATAMOTORS",
            "exchange" => "NSE",
            "trigger_values" => array(310),
            "last_price" => 315,
            "orders" => array([
                "transaction_type" => $kiteConnect::TRANSACTION_TYPE_SELL,
                "quantity" => 1,
                "product" => $kiteConnect::PRODUCT_CNC,
                "order_type" => $kiteConnect::ORDER_TYPE_LIMIT,
                "price" => 300], 
                [
                "transaction_type" => $kiteConnect::TRANSACTION_TYPE_SELL,
                "quantity" => 1,
                "product" => $kiteConnect::PRODUCT_CNC,
                "order_type" => $kiteConnect::ORDER_TYPE_LIMIT,
                "price" => 400
                ])]);

        $this->assertObjectHasProperty('trigger_id',$response);
    }

    /** 
     * @depends initializeMock
     * @test getGTTs 
    */
    public function getGTTsTest($kiteConnect): void
    {
        $response = $kiteConnect->getGTTs();

        foreach ($response as $values) {
            $this->assertObjectHasProperty('id',$values);
            $this->assertObjectHasProperty('created_at',$values);
            $this->assertObjectHasProperty('status',$values);
            $this->assertObjectHasProperty('condition',$values);
        }

    }

    /** 
     * @depends initializeMock
     * @test getGTT 
    */
    public function getGTTTest($kiteConnect): void
    {
        $response = $kiteConnect->getGTT('123');

        $this->assertObjectHasProperty('id',$response);
        $this->assertObjectHasProperty('user_id',$response);
        $this->assertObjectHasProperty('orders',$response);
        $this->assertObjectHasProperty('condition',$response);

    }

    /** 
     * @depends initializeMock
     * @test modifyGTT 
    */
    public function modifyGTTTest($kiteConnect): void
    {
        $response = $kiteConnect->modifyGTT(123, [
            "orders" => array([
                "transaction_type" => $kiteConnect::TRANSACTION_TYPE_SELL,
                "quantity" => 1,
                "product" => $kiteConnect::PRODUCT_CNC,
                "order_type" => $kiteConnect::ORDER_TYPE_LIMIT,
                "price" => 300], 
                [
                "transaction_type" => $kiteConnect::TRANSACTION_TYPE_SELL,
                "quantity" => 1,
                "product" => $kiteConnect::PRODUCT_CNC,
                "order_type" => $kiteConnect::ORDER_TYPE_LIMIT,
                "price" => 400
                ]),
                "tradingsymbol" => "TATAMOTORS",
                "exchange" => "NSE",
                "trigger_values" => array(310),
                "last_price" => 315,
                "trigger_type" => $kiteConnect::GTT_TYPE_SINGLE,
                "trigger_id" => 123]);

        $this->assertObjectHasProperty('trigger_id',$response);
    }

    /** 
     * @depends initializeMock
     * @test deleteGTT 
    */
    public function deleteGTTTest($kiteConnect): void
    {
        $response = $kiteConnect->deleteGTT('123');

        $this->assertObjectHasProperty('trigger_id',$response);

    }

    /** 
     * @depends initializeMock
     * @test getHistoricalData 
    */
    public function getHistoricalDataTest($kiteConnect): void
    {
        $response = $kiteConnect->getHistoricalData(15495682, 'minute', '2021-01-20', '2021-01-25');

        foreach ($response as $values) {
            $this->assertObjectHasProperty('date',$values);
            $this->assertObjectHasProperty('open',$values);
            $this->assertObjectHasProperty('high',$values);
            $this->assertObjectHasProperty('low',$values);
            $this->assertObjectHasProperty('close',$values);
            $this->assertObjectHasProperty('volume',$values);
        }

    }

    /** 
     * @depends initializeMock
     * @test getMFOrders 
    */
    public function getMFOrdersTest($kiteConnect): void
    {
        $response = $kiteConnect->getMFOrders();

        foreach ($response as $values) {
            $this->assertObjectHasProperty('order_id',$values);
            $this->assertObjectHasProperty('tradingsymbol',$values);
            $this->assertObjectHasProperty('purchase_type',$values);
            $this->assertObjectHasProperty('fund',$values);
        }

    }

    /** 
     * @depends initializeMock
     * @test getMFOrders 
    */
    public function getMFOrderindTest($kiteConnect): void
    {
        $response = $kiteConnect->getMFOrders('123456789');

        $this->assertObjectHasProperty('order_id',$response);
        $this->assertObjectHasProperty('fund',$response);
        $this->assertObjectHasProperty('order_timestamp',$response);
        $this->assertObjectHasProperty('amount',$response);
    }

    /** 
     * @depends initializeMock
     * @test getMFSIPS 
    */
    public function getMFSIPSTest($kiteConnect): void
    {
        $response = $kiteConnect->getMFSIPS();

        foreach ($response as $values) {
            $this->assertObjectHasProperty('sip_id',$values);
            $this->assertObjectHasProperty('fund',$values);
            $this->assertObjectHasProperty('instalment_amount',$values);
            $this->assertObjectHasProperty('dividend_type',$values);
        }
    }

    /** 
     * @depends initializeMock
     * @test getMFSIPS 
    */
    public function getMFSIPSindvTest($kiteConnect): void
    {
        $response = $kiteConnect->getMFSIPS('123456789');

        $this->assertObjectHasProperty('sip_id',$response);
        $this->assertObjectHasProperty('last_instalment',$response);
        $this->assertObjectHasProperty('pending_instalments',$response);
        $this->assertObjectHasProperty('next_instalment',$response);
    }

    /** 
     * @depends initializeMock
     * @test getMFHoldings 
    */
    public function getMFHoldingsTest($kiteConnect): void
    {
        $response = $kiteConnect->getMFHoldings();

        foreach ($response as $values) {
            $this->assertObjectHasProperty('folio',$values);
            $this->assertObjectHasProperty('fund',$values);
            $this->assertObjectHasProperty('tradingsymbol',$values);
            $this->assertObjectHasProperty('pnl',$values);
        }
    }

    /** 
     * @depends initializeMock
     * @test getVirtualContractNote
    */
    public function getVirtualContractNoteTest($kiteConnect): void
    {
        $orderParams = [[
            "order_id" => "111111111",
            "exchange" => "NSE",
            "tradingsymbol" => "SBIN",
            "transaction_type" => $kiteConnect::TRANSACTION_TYPE_BUY,
            "variety" => $kiteConnect::VARIETY_REGULAR,
            "product" => $kiteConnect::PRODUCT_CNC,
            "order_type" => $kiteConnect::ORDER_TYPE_MARKET,
            "quantity" => 1,
            "average_price" => 560
            ],
            [
            "order_id" => "2222222222",
            "exchange" => "MCX",
            "tradingsymbol" => "GOLDPETAL23JULFUT",
            "transaction_type" => $kiteConnect::TRANSACTION_TYPE_SELL,
            "variety" => $kiteConnect::VARIETY_REGULAR,
            "product" => $kiteConnect::PRODUCT_NRML,
            "order_type" => $kiteConnect::ORDER_TYPE_LIMIT,
            "quantity" => 1,
            "average_price" => 5862
            ],
            [
            "order_id" => "3333333333",
            "exchange" => "NFO",
            "tradingsymbol" => "NIFTY2371317900PE",
            "transaction_type" => $kiteConnect::TRANSACTION_TYPE_SELL,
            "variety" => $kiteConnect::VARIETY_REGULAR,
            "product" => $kiteConnect::PRODUCT_NRML,
            "order_type" => $kiteConnect::ORDER_TYPE_LIMIT,
            "quantity" => 100,
            "average_price" => 1.5
            ]
        ];
        $response = $kiteConnect->getVirtualContractNote($orderParams);

        foreach ($response as $values) {
            $this->assertObjectHasProperty('charges',$values);
            $this->assertObjectHasProperty('transaction_type',$values);
            $this->assertObjectHasProperty('tradingsymbol',$values);
        }
    }

    /** 
     * @depends initializeMock
     * @test getInstruments 
    */
    public function getInstrumentsTest($kiteConnect): void
    {
        $response = $kiteConnect->getInstruments();

        foreach ($response as $values) {
            $this->assertObjectHasProperty('instrument_token',$values);
            $this->assertObjectHasProperty('exchange_token',$values);
            $this->assertObjectHasProperty('tradingsymbol',$values);
            $this->assertObjectHasProperty('name',$values);
        }
    }

    /** 
     * @depends initializeMock
     * @test getInstruments with Exchange 
    */
    public function getInstrumentsExchangeTest($kiteConnect): void
    {
        $response = $kiteConnect->getInstruments('NSE');

        foreach ($response as $values) {
            $this->assertObjectHasProperty('instrument_token',$values);
            $this->assertObjectHasProperty('exchange_token',$values);
            $this->assertObjectHasProperty('tradingsymbol',$values);
            $this->assertObjectHasProperty('name',$values);
        }
    }

    /** 
     * @depends initializeMock
     * @test getMFInstruments
    */
    public function getMFInstrumentsTest($kiteConnect): void
    {
        $response = $kiteConnect->getMFInstruments();

        foreach ($response as $values) {
            $this->assertObjectHasProperty('tradingsymbol',$values);
            $this->assertObjectHasProperty('amc',$values);
            $this->assertObjectHasProperty('scheme_type',$values);
            $this->assertObjectHasProperty('redemption_allowed',$values);
        }
    }
}
