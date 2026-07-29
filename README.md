# Kite Connect PHP Client

[![Packagist Version](https://img.shields.io/packagist/v/zerodha/phpkiteconnect.svg)](https://packagist.org/packages/zerodha/phpkiteconnect)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-777bb4.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)

The official PHP client for the [Kite Connect API](https://kite.trade) — Zerodha's trading and investment platform API.

Kite Connect is a set of REST-like APIs that expose the capabilities needed to build a complete investment and trading platform. Execute orders in real time, manage user portfolios, stream live market data over WebSockets, and more — all through a simple, unified HTTP and WebSocket API collection. This library wraps those APIs in idiomatic PHP so you can focus on your application logic instead of raw HTTP calls.

> **PHP version note:** This version requires **PHP 8.0+**. If you're on an older PHP version, use the [v3.0.0 release](https://github.com/zerodha/phpkiteconnect/releases/tag/v3.0.0) instead.

&copy; [Zerodha Technology](http://zerodha.com), 2025. Licensed under the [MIT License](LICENSE.md).

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Usage Guide](#usage-guide)
  - [Authentication](#authentication)
  - [Placing and Managing Orders](#placing-and-managing-orders)
  - [Portfolio and Market Data](#portfolio-and-market-data)
  - [Real-time Streaming with KiteTicker](#real-time-streaming-with-kiteticker)
- [Error Handling](#error-handling)
- [API Reference](#api-reference)
- [Testing](#testing)
- [Generating Documentation](#generating-documentation)
- [Versioning and Changelog](#versioning-and-changelog)
- [Support](#support)
- [License](#license)

---

## Features

- Full coverage of the Kite Connect v3 HTTP API: orders, portfolio, margins, mutual funds, GTT, historical data, and instruments.
- **`KiteTicker`** — a WebSocket client for streaming live market data (LTP, quote, and full tick modes) with automatic reconnection.
- Typed, documented methods returning native PHP structures (arrays, `stdClass` objects, `DateTime` instances) instead of raw JSON.
- A dedicated exception hierarchy (see [Error Handling](#error-handling)) so you can catch specific failure modes instead of parsing HTTP status codes.
- Built on [Guzzle](https://docs.guzzlephp.org/), with first-class support for dependency injection in tests.

## Requirements

| Requirement | Version |
|---|---|
| [PHP](https://www.php.net/manual/en/install.php) | >= 8.0 |
| [Composer](https://getcomposer.org/download/) | Latest |
| PHP extensions | `curl`, `json`, `zlib` |

## Installation

Install via [Composer](https://getcomposer.org/):

```bash
composer require zerodha/phpkiteconnect
```

## Quick Start

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use KiteConnect\KiteConnect;

// 1. Initialise with your API key.
$kite = new KiteConnect('your_api_key');

// 2. Redirect the user to $kite->getLoginURL() to complete the login flow.
//    On successful login, Kite redirects back with a `request_token` query param.

// 3. Exchange the request_token for an access_token.
try {
    $user = $kite->generateSession('request_token_obtained', 'your_api_secret');
    $kite->setAccessToken($user->access_token);
    echo "Authentication successful. {$user->user_id} has logged in.\n";
} catch (Exception $e) {
    echo 'Authentication failed: ' . $e->getMessage();
    throw $e;
}

// 4. Persist $user->access_token (e.g. in a session or database) and reuse it
//    for subsequent requests without repeating the login flow:
//    $kite = new KiteConnect('your_api_key', 'stored_access_token');

// 5. Make authenticated API calls.
print_r($kite->getPositions());

$order = $kite->placeOrder(KiteConnect::VARIETY_REGULAR, [
    'tradingsymbol'    => 'INFY',
    'exchange'         => 'NSE',
    'quantity'         => 1,
    'transaction_type' => KiteConnect::TRANSACTION_TYPE_BUY,
    'order_type'       => KiteConnect::ORDER_TYPE_MARKET,
    'product'          => KiteConnect::PRODUCT_NRML,
]);

echo "Order placed. Order ID: {$order->order_id}";
```

> **A note on client lifecycle:** In a typical web application, a new instance of your views/controllers is created per incoming HTTP request — so you should initialise a new `KiteConnect` instance per request too. Each instance represents a single authenticated user, unlike an admin API where one instance manages many users. Store the `access_token` in your session or database after login, and re-initialise `KiteConnect` with it on each subsequent request.

## Usage Guide

### Authentication

```php
$kite = new KiteConnect('your_api_key');

// Step 1: send the user here to log in.
$loginUrl = $kite->getLoginURL();

// Step 2: after login, Kite redirects to your registered redirect URL with
// a `request_token` query parameter. Exchange it for a session:
$user = $kite->generateSession($_GET['request_token'], 'your_api_secret');
$kite->setAccessToken($user->access_token);

// Optional: register a callback for session-expiry / token errors so you
// can log the user out, clear cookies, or trigger a fresh login automatically.
$kite->setSessionExpiryHook(function () {
    // e.g. redirect to login page
});
```

### Placing and Managing Orders

```php
// Place an order.
$order = $kite->placeOrder(KiteConnect::VARIETY_REGULAR, [
    'tradingsymbol'      => 'INFY',
    'exchange'           => 'NSE',
    'transaction_type'   => KiteConnect::TRANSACTION_TYPE_BUY,
    'order_type'         => KiteConnect::ORDER_TYPE_LIMIT,
    'quantity'           => 1,
    'product'            => KiteConnect::PRODUCT_CNC,
    'price'              => 1500,
    'validity'           => KiteConnect::VALIDITY_DAY,
]);

// Modify an open order.
$kite->modifyOrder(KiteConnect::VARIETY_REGULAR, $order->order_id, [
    'quantity' => 2,
    'price'    => 1490,
]);

// Cancel an order.
$kite->cancelOrder(KiteConnect::VARIETY_REGULAR, $order->order_id);

// Fetch orders and trades.
$orders = $kite->getOrders();
$history = $kite->getOrderHistory($order->order_id);
$trades = $kite->getTrades();
```

`MARKET` orders should also pass `market_protection` — use `KiteConnect::MARKET_PROTECTION_AUTO` (`-1`) for the system default, or a percentage value (0–100) to cap slippage.

### Portfolio and Market Data

```php
// Portfolio.
$positions = $kite->getPositions();
$holdings  = $kite->getHoldings();
$margins   = $kite->getMargins(KiteConnect::MARGIN_EQUITY);

// Market quotes.
$quote = $kite->getQuote(['NSE:INFY', 'NSE:SBIN']);
$ltp   = $kite->getLTP(['NSE:INFY']);
$ohlc  = $kite->getOHLC(['NSE:INFY']);

// Historical candles.
$candles = $kite->getHistoricalData(
    '408065',            // instrument_token
    'day',                // interval
    '2024-01-01 09:15:00',
    '2024-01-31 15:30:00'
);

// Instrument dumps (large CSV responses, parsed into objects for you).
$instruments = $kite->getInstruments('NSE');
```

### Real-time Streaming with KiteTicker

`KiteTicker` streams live market data over a WebSocket connection, with automatic reconnection on network interruptions.

```php
use KiteConnect\KiteTicker;

$ticker = new KiteTicker(
    'your_api_key',
    'your_access_token',
    timeout: 30,
    autoReconnect: true,
    debug: false
);

$ticker->on('connect', function () use ($ticker) {
    // Subscribe once connected.
    $ticker->subscribe([738561], KiteTicker::MODE_FULL); // 738561 = RELIANCE
});

$ticker->on('ticks', function (array $ticks) {
    foreach ($ticks as $tick) {
        echo "{$tick['instrument_token']}: {$tick['last_price']}\n";
    }
});

$ticker->on('order_update', function (array $order) {
    // Postback-style order update pushed over the socket.
});

$ticker->on('close', function ($code, $reason) {
    echo "Connection closed: {$code} {$reason}\n";
});

$ticker->on('error', function (Exception $e) {
    echo 'Ticker error: ' . $e->getMessage() . "\n";
});

$ticker->connect(); // blocks and runs the event loop
```

Streaming modes, from lightest to heaviest payload: `MODE_LTP`, `MODE_QUOTE`, `MODE_FULL`. See [`examples/ticker_example.php`](examples/ticker_example.php) for a complete, runnable example including subscribe/unsubscribe/mode-switching.

## Error Handling

Kite Connect saves you the hassle of detecting API errors by inspecting HTTP status codes or parsing JSON error bodies yourself. Instead, it raises aptly named exceptions — all under the `KiteConnect\Exception` namespace and extending a common base — that you can catch individually or collectively:

| Exception | Default HTTP-like code | Raised when |
|---|---|---|
| `KiteException` | — | Base class for all library exceptions. Catch this to handle any Kite-specific error generically. |
| `InputException` | 400 | Missing or invalid request parameters. |
| `TokenException` | 403 | The `access_token` is invalid, expired, or missing. |
| `PermissionException` | 403 | The API key/user doesn't have permission for the requested action. |
| `OrderException` | 500 | An order placement, modification, or cancellation failed. |
| `DataException` | 502 | A malformed or unexpected response was received from the backend. |
| `NetworkException` | 503 | A network-level failure occurred — connection timeout, DNS failure, or connection refused — before any response was received. |
| `GeneralException` | 500 | Any other unclassified error. |

```php
use KiteConnect\Exception\TokenException;
use KiteConnect\Exception\NetworkException;
use KiteConnect\Exception\KiteException;

try {
    $kite->placeOrder(KiteConnect::VARIETY_REGULAR, $params);
} catch (TokenException $e) {
    // Access token expired — re-authenticate.
} catch (NetworkException $e) {
    // Transient network issue — safe to retry with backoff.
} catch (KiteException $e) {
    // Catch-all for any other Kite-specific error.
    echo $e->getMessage();
}
```

For session-expiry errors specifically, you can also register a hook once instead of catching `TokenException` on every call:

```php
$kite->setSessionExpiryHook(function () {
    // Clear the stored session and redirect to login.
});
```

## API Reference

The client exposes typed constants for every enumerable API parameter, so you don't have to remember magic strings:

| Group | Constants |
|---|---|
| Products | `PRODUCT_MIS`, `PRODUCT_CNC`, `PRODUCT_NRML`, `PRODUCT_CO`, `PRODUCT_BO` |
| Order types | `ORDER_TYPE_MARKET`, `ORDER_TYPE_LIMIT`, `ORDER_TYPE_SLM`, `ORDER_TYPE_SL` |
| Varieties | `VARIETY_REGULAR`, `VARIETY_BO`, `VARIETY_CO`, `VARIETY_AMO`, `VARIETY_ICEBERG`, `VARIETY_AUCTION` |
| Transaction type | `TRANSACTION_TYPE_BUY`, `TRANSACTION_TYPE_SELL` |
| Validity | `VALIDITY_DAY`, `VALIDITY_IOC`, `VALIDITY_TTL` |
| Margins segment | `MARGIN_EQUITY`, `MARGIN_COMMODITY` |
| GTT type | `GTT_TYPE_OCO`, `GTT_TYPE_SINGLE` |
| GTT status | `GTT_STATUS_ACTIVE`, `GTT_STATUS_TRIGGERED`, `GTT_STATUS_DISABLED`, `GTT_STATUS_EXPIRED`, `GTT_STATUS_CANCELLED`, `GTT_STATUS_REJECTED`, `GTT_STATUS_DELETED` |
| Position type | `POSITION_TYPE_DAY`, `POSITION_TYPE_OVERNIGHT` |
| Market protection | `MARKET_PROTECTION_AUTO` (`-1`, system default) |
| Ticker modes | `KiteTicker::MODE_LTP`, `KiteTicker::MODE_QUOTE`, `KiteTicker::MODE_FULL` |

For the complete list of methods, parameters, and response shapes, refer to:

- [PHP client documentation](https://kite.trade/docs/phpkiteconnect/v3)
- [Kite Connect HTTP API documentation](https://kite.trade/docs/connect/v3)
- [Examples folder](https://github.com/zerodha/phpkiteconnect/tree/master/examples) — runnable, end-to-end scripts for both `KiteConnect` and `KiteTicker`.

## Testing

Run the unit test suite with:

```bash
composer test
```

Static analysis (Psalm) and code style checks are also available for contributors:

```bash
vendor/bin/psalm       # static analysis
composer format        # apply code style fixes (php-cs-fixer)
```

## Generating Documentation

API documentation can be generated locally with [phpDocumentor](https://phpdoc.org/):

```bash
apt-get install wget
wget https://phpdoc.org/phpDocumentor.phar
chmod +x phpDocumentor.phar
./phpDocumentor.phar run -d ./src/ -t ./doc/
```

## Versioning and Changelog

This project follows [Semantic Versioning](https://semver.org/). See [CHANGELOG.md](CHANGELOG.md) for the full history of releases and notable changes.

## Support

- **Issues and bugs:** [GitHub Issues](https://github.com/zerodha/phpkiteconnect/issues)
- **API questions:** [Kite Connect developer forum](https://kite.trade/forum/)
- **General API documentation:** [kite.trade/docs](https://kite.trade/docs/connect/v3/)

## License

[MIT](LICENSE.md) &copy; [Zerodha Technology](http://zerodha.com)
