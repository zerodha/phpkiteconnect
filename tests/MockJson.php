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
        
        $response_array = array();
        
        // Provide responses in the exact order that tests will consume them
        // Based on test execution order and dependencies
        
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("profile.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("margins.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("quote.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("ohlc.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("ltp.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("holdings.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("positions.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("order_response.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("orders.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("order_info.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("order_trades.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("trades.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("gtt_place_order.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("gtt_get_orders.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("gtt_get_order.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("gtt_modify_order.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("gtt_delete_order.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("historical_minute.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("mf_orders.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("mf_orders_info.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("mf_sips.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("mf_sip_info.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("mf_holdings.json"));
        $response_array[] = new Response($status_code, $header_content, $this->fetchMock("virtual_contract_note.json"));
        
        // Add CSV responses
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