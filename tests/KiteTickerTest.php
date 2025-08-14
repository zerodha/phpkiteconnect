<?php

namespace KiteConnect\Tests;

use PHPUnit\Framework\TestCase;
use KiteConnect\KiteTicker;
use Exception;

class KiteTickerTest extends TestCase
{
    private $apiKey = "api_key";
    private $accessToken = "access_token";

    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test LTP mode tick data
     */
    public function testLtpModeTick()
    {
        $ticker = new KiteTicker($this->apiKey, $this->accessToken);
        $reflection = new \ReflectionClass($ticker);
        $parseBinaryMethod = $reflection->getMethod('parseBinary');
        $parseBinaryMethod->setAccessible(true);
        
        $tickData = $parseBinaryMethod->invoke($ticker, $this->toArrayBuffer('ltpMode_binary.packet'));
        
        $this->assertIsArray($tickData);
        $this->assertEquals('ltp', $tickData[0]['mode']);
        $this->assertArrayHasKey('instrument_token', $tickData[0]);
        $this->assertArrayHasKey('last_price', $tickData[0]);
    }

    /**
     * Test Quote mode tick data
     */
    public function testQuoteModeTick()
    {
        $ticker = new KiteTicker($this->apiKey, $this->accessToken);
        $reflection = new \ReflectionClass($ticker);
        $parseBinaryMethod = $reflection->getMethod('parseBinary');
        $parseBinaryMethod->setAccessible(true);
        
        $tickData = $parseBinaryMethod->invoke($ticker, $this->toArrayBuffer('quoteMode_binary.packet'));
        
        $this->assertIsArray($tickData);
        $this->assertEquals('quote', $tickData[0]['mode']);
        $this->assertArrayHasKey('instrument_token', $tickData[0]);
        $this->assertArrayHasKey('ohlc', $tickData[0]);
        $this->assertArrayHasKey('volume_traded', $tickData[0]);
    }

    /**
     * Test Full mode tick data
     */
    public function testFullModeTick()
    {
        $ticker = new KiteTicker($this->apiKey, $this->accessToken);
        $reflection = new \ReflectionClass($ticker);
        $parseBinaryMethod = $reflection->getMethod('parseBinary');
        $parseBinaryMethod->setAccessible(true);
        
        $tickData = $parseBinaryMethod->invoke($ticker, $this->toArrayBuffer('fullMode_binary.packet'));
        
        $this->assertIsArray($tickData);
        $this->assertEquals('full', $tickData[0]['mode']);
        $this->assertArrayHasKey('exchange_timestamp', $tickData[0]);
        $this->assertArrayHasKey('last_trade_time', $tickData[0]);
        $this->assertArrayHasKey('depth', $tickData[0]);
    }

    /**
     * Read binary packets
     */
    private function readBufferPacket(string $fileName): string
    {
        return file_get_contents(__DIR__ . '/' . $fileName);
    }

    /**
     * Convert buffer to binary buffer array
     */
    private function toArrayBuffer(string $tickerMode): string
    {
        return $this->readBufferPacket($tickerMode);
    }
}
