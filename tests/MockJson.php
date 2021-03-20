<?php

namespace KiteConnect\Tests;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class MockJson
{

    public function generateMock()
    {
        $status_code = 200;
        $header_content = ['Content-Type' => 'application/json'];
        $mock_files = [
            "profile.json",
            "margins.json",
            "quote.json",
            "ohlc.json",
            "ltp.json",
            "holdings.json",
            "positions.json",
            "order_response.json",
            "orders.json",
            "order_info.json",
            "order_trades.json",
            "trades.json",
            "gtt_place_order.json",
            "gtt_get_orders.json",
            "gtt_get_order.json",
            "gtt_modify_order.json",
            "gtt_delete_order.json",
            "historical_minute.json",
            "mf_orders.json",
            "mf_orders_info.json",
            "mf_sips.json",
            "mf_sip_info.json",
            "mf_holdings.json"
        ]; 
        $response_array = array();
        foreach ($mock_files as $values) {
            $response_array[] = new Response($status_code, $header_content, $this->fetchMock($values));
        }
        // add all text/csv header based content-type response
        $response_array[] = new Response($status_code, ['Content-Type' => 'text/csv'], $this->fetchMock("instruments_all.csv"));
        $response_array[] = new Response($status_code, ['Content-Type' => 'text/csv'], $this->fetchMock("instruments_nse.csv"));
        $response_array[] = new Response($status_code, ['Content-Type' => 'text/csv'], $this->fetchMock("mf_instruments.csv"));
        
        $mock = new MockHandler($response_array);
        $handlerStack = HandlerStack::create($mock);

        return $handlerStack;
    }

    public function fetchMock(string $route)
    {
        // root mock response file location
        $file_root = "./tests/mock_responses/";
        $fetch_mock = file_get_contents($file_root. $route);
        return $fetch_mock;
    }
}

?>