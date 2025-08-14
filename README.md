# The Kite Connect API PHP client - v3

The Official PHP client for communicating with the [Kite Connect API](https://kite.trade).<br />

Note: For PHP version < 8.0. You can refer to our [previous version](https://github.com/zerodha/phpkiteconnect/releases/tag/v3.0.0).  
Kite Connect is a set of REST-like APIs that expose many capabilities required to build a complete investment and trading platform. Execute orders in real time, manage user portfolio and more, with the simple HTTP API collection.

[Zerodha Technology](http://zerodha.com) (c) 2025. Licensed under the MIT License.

## Documentation

- [PHP client documentation](https://kite.trade/docs/phpkiteconnect/v3)
- [Kite Connect HTTP API documentation](https://kite.trade/docs/connect/v3)

## Installing

### Requirements

1. Install [PHP](https://www.php.net/manual/en/install.php) version 8.0 or higher.
2. Install [Composer](https://getcomposer.org/download/), which is used to install PHP packages.

You can install the package via composer:

```bash
composer require zerodha/phpkiteconnect
```

Note: You can refer to our previous version [here](https://github.com/zerodha/phpkiteconnect/releases/tag/v3.0.0) for PHP version < 8.0.

## Usage

```php
<?php
    require_once __DIR__ . '/vendor/autoload.php';

    use KiteConnect\KiteConnect;

    // Initialise.
    $kite = new KiteConnect("your_api_key");

    // Assuming you have obtained the `request_token`
    // after the auth flow redirect by redirecting the
    // user to $kite->getLoginURL()
    try {
        $user = $kite->generateSession("request_token_obtained", "your_api_secret");
        echo "Authentication successful. \n";
        print_r($user);
        $kite->setAccessToken($user->access_token);
    } catch(Exception $e) {
        echo "Authentication failed: ".$e->getMessage();
        throw $e;
    }

    echo $user->user_id." has logged in";

    // Get the list of positions.
    echo "Positions: \n";
    print_r($kite->getPositions());

    // Place order.
    $order = $kite->placeOrder("regular", [
        "tradingsymbol" => "INFY",
        "exchange" => "NSE",
        "quantity" => 1,
        "transaction_type" => "BUY",
        "order_type" => "MARKET",
        "product" => "NRML"
    ]);

    echo "Order id is ".$order->order_id;
?>
```

## Examples

Check [examples folder](https://github.com/zerodha/phpkiteconnect/tree/master/examples) for more examples.

Refer to the [PHP client documentation](https://kite.trade/docs/phpkiteconnect/v3) for the complete list of supported methods.

## Run unit tests

```
composer test
```

## Generate documentation

```
$ apt-get install wget
$ wget https://phpdoc.org/phpDocumentor.phar
$ chmod +x phpDocumentor.phar
$ ./phpDocumentor.phar run -d ./src/ -t ./doc/
```

## Changelog

[Check CHANGELOG.md](CHANGELOG.md)
