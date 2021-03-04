<?php

namespace KiteConnect\Tests;

use KiteConnect\KiteConnect;
use PHPUnit\Framework\TestCase;

/** @package KiteConnect\Tests */
class KiteConnectTest extends TestCase
{
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
}
