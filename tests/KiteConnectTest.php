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
        
        $this->assertObjectHasAttribute('user_id',$response);
        $this->assertObjectHasAttribute('user_name',$response);
        $this->assertObjectHasAttribute('exchanges',$response);
        $this->assertObjectHasAttribute('meta',$response);
    }

    /** 
     * @depends initializeMock
     * @test getMargins 
    */
    public function getMarginsTest($kiteConnect): void
    {
        $response = $kiteConnect->getMargins();
        
        $this->assertObjectHasAttribute('equity',$response);
        $this->assertObjectHasAttribute('commodity',$response);
    }

    /** 
     * @depends initializeMock
     * @test getQuote 
    */
    public function getQuoteTest($kiteConnect): void
    {
        $response = $kiteConnect->getQuote(['NSE:INFY', 'NSE:SBIN']);

        $this->assertArrayHasKey('NSE:INFY', $response);
        $this->assertArrayHasKey('NSE:SBIN', $response);

        foreach ($response as $values) {
            $this->assertObjectHasAttribute('instrument_token',$values);
            $this->assertObjectHasAttribute('ohlc',$values);
            $this->assertObjectHasAttribute('depth',$values);
        }
    }

    /** 
     * @depends initializeMock
     * @test getOHLC 
    */
    public function getOHLCTest($kiteConnect): void
    {
        $response = $kiteConnect->getOHLC(['NSE:INFY', 'NSE:SBIN']);

        $this->assertObjectHasAttribute('NSE:INFY', $response);
        $this->assertObjectHasAttribute('NSE:SBIN', $response);
        
        foreach ($response as $values) {
            $this->assertObjectHasAttribute('instrument_token',$values);
            $this->assertObjectHasAttribute('ohlc',$values);
            $this->assertObjectHasAttribute('last_price',$values);
        }
    }

    /** 
     * @depends initializeMock
     * @test getLTP 
    */
    public function getLTPTest($kiteConnect): void
    {
        $response = $kiteConnect->getLTP(['NSE:INFY', 'NSE:SBIN']);

        $this->assertObjectHasAttribute('NSE:INFY', $response);
        $this->assertObjectHasAttribute('NSE:SBIN', $response);
        
        foreach ($response as $values) {
            $this->assertObjectHasAttribute('instrument_token',$values);
            $this->assertObjectHasAttribute('last_price',$values);
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
            $this->assertObjectHasAttribute('tradingsymbol',$values);
            $this->assertObjectHasAttribute('exchange',$values);
            $this->assertObjectHasAttribute('pnl',$values);
        }
    }

    /** 
     * @depends initializeMock
     * @test getPositions 
    */
    public function getPositionsTest($kiteConnect): void
    {
        $response = $kiteConnect->getPositions();
        $this->assertObjectHasAttribute('net',$response);

        foreach ($response as $values) {
            foreach ($values as $value2){
                $this->assertObjectHasAttribute('tradingsymbol',$value2);
                $this->assertObjectHasAttribute('exchange',$value2);
                $this->assertObjectHasAttribute('average_price',$value2);
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

        $this->assertObjectHasAttribute('order_id',$response);

    }

    /** 
     * @depends initializeMock
     * @test getOrders 
    */
    public function getOrdersTest($kiteConnect): void
    {
        $response = $kiteConnect->getOrders();

        foreach ($response as $values) {
            $this->assertObjectHasAttribute('order_id',$values);
            $this->assertObjectHasAttribute('exchange_timestamp',$values);
            $this->assertObjectHasAttribute('status',$values);
            $this->assertObjectHasAttribute('order_id',$values);
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
            $this->assertObjectHasAttribute('order_id',$values);
            $this->assertObjectHasAttribute('exchange_timestamp',$values);
            $this->assertObjectHasAttribute('status',$values);
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
            $this->assertObjectHasAttribute('average_price',$values);
            $this->assertObjectHasAttribute('transaction_type',$values);
            $this->assertObjectHasAttribute('order_timestamp',$values);
            $this->assertObjectHasAttribute('order_id',$values);
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
            $this->assertObjectHasAttribute('trade_id',$values);
            $this->assertObjectHasAttribute('exchange_order_id',$values);
            $this->assertObjectHasAttribute('order_id',$values);
            $this->assertObjectHasAttribute('instrument_token',$values);
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

        $this->assertObjectHasAttribute('trigger_id',$response);
    }

    /** 
     * @depends initializeMock
     * @test getGTTs 
    */
    public function getGTTsTest($kiteConnect): void
    {
        $response = $kiteConnect->getGTTs();

        foreach ($response as $values) {
            $this->assertObjectHasAttribute('id',$values);
            $this->assertObjectHasAttribute('created_at',$values);
            $this->assertObjectHasAttribute('status',$values);
            $this->assertObjectHasAttribute('condition',$values);
        }

    }

    /** 
     * @depends initializeMock
     * @test getGTT 
    */
    public function getGTTTest($kiteConnect): void
    {
        $response = $kiteConnect->getGTT('123');

        $this->assertObjectHasAttribute('id',$response);
        $this->assertObjectHasAttribute('user_id',$response);
        $this->assertObjectHasAttribute('orders',$response);
        $this->assertObjectHasAttribute('condition',$response);

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

        $this->assertObjectHasAttribute('trigger_id',$response);
    }

    /** 
     * @depends initializeMock
     * @test deleteGTT 
    */
    public function deleteGTTTest($kiteConnect): void
    {
        $response = $kiteConnect->deleteGTT('123');

        $this->assertObjectHasAttribute('trigger_id',$response);

    }

    /** 
     * @depends initializeMock
     * @test getHistoricalData 
    */
    public function getHistoricalDataTest($kiteConnect): void
    {
        $response = $kiteConnect->getHistoricalData(15495682, 'minute', '2021-01-20', '2021-01-25');

        foreach ($response as $values) {
            $this->assertObjectHasAttribute('date',$values);
            $this->assertObjectHasAttribute('open',$values);
            $this->assertObjectHasAttribute('high',$values);
            $this->assertObjectHasAttribute('low',$values);
            $this->assertObjectHasAttribute('close',$values);
            $this->assertObjectHasAttribute('volume',$values);
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
            $this->assertObjectHasAttribute('order_id',$values);
            $this->assertObjectHasAttribute('tradingsymbol',$values);
            $this->assertObjectHasAttribute('purchase_type',$values);
            $this->assertObjectHasAttribute('fund',$values);
        }

    }

    /** 
     * @depends initializeMock
     * @test getMFOrders 
    */
    public function getMFOrderindTest($kiteConnect): void
    {
        $response = $kiteConnect->getMFOrders('123456789');

        $this->assertObjectHasAttribute('order_id',$response);
        $this->assertObjectHasAttribute('fund',$response);
        $this->assertObjectHasAttribute('order_timestamp',$response);
        $this->assertObjectHasAttribute('amount',$response);
    }

    /** 
     * @depends initializeMock
     * @test getMFSIPS 
    */
    public function getMFSIPSTest($kiteConnect): void
    {
        $response = $kiteConnect->getMFSIPS();

        foreach ($response as $values) {
            $this->assertObjectHasAttribute('sip_id',$values);
            $this->assertObjectHasAttribute('fund',$values);
            $this->assertObjectHasAttribute('instalment_amount',$values);
            $this->assertObjectHasAttribute('dividend_type',$values);
        }
    }

    /** 
     * @depends initializeMock
     * @test getMFSIPS 
    */
    public function getMFSIPSindvTest($kiteConnect): void
    {
        $response = $kiteConnect->getMFSIPS('123456789');

        $this->assertObjectHasAttribute('sip_id',$response);
        $this->assertObjectHasAttribute('last_instalment',$response);
        $this->assertObjectHasAttribute('pending_instalments',$response);
        $this->assertObjectHasAttribute('instalment_date',$response);
    }

    /** 
     * @depends initializeMock
     * @test getMFHoldings 
    */
    public function getMFHoldingsTest($kiteConnect): void
    {
        $response = $kiteConnect->getMFHoldings();

        foreach ($response as $values) {
            $this->assertObjectHasAttribute('folio',$values);
            $this->assertObjectHasAttribute('fund',$values);
            $this->assertObjectHasAttribute('tradingsymbol',$values);
            $this->assertObjectHasAttribute('pnl',$values);
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
            $this->assertObjectHasAttribute('instrument_token',$values);
            $this->assertObjectHasAttribute('exchange_token',$values);
            $this->assertObjectHasAttribute('tradingsymbol',$values);
            $this->assertObjectHasAttribute('name',$values);
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
            $this->assertObjectHasAttribute('instrument_token',$values);
            $this->assertObjectHasAttribute('exchange_token',$values);
            $this->assertObjectHasAttribute('tradingsymbol',$values);
            $this->assertObjectHasAttribute('name',$values);
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
            $this->assertObjectHasAttribute('tradingsymbol',$values);
            $this->assertObjectHasAttribute('amc',$values);
            $this->assertObjectHasAttribute('scheme_type',$values);
            $this->assertObjectHasAttribute('redemption_allowed',$values);
        }
    }
    
}
