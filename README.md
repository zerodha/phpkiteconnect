# The Kite Connect API PHP client - v3
The official PHP client for communicating with the [Kite Connect API](https://kite.trade).

Kite Connect is a set of REST-like APIs that expose many capabilities required to build a complete investment and trading platform. Execute orders in real time, manage user portfolio and more, with the simple HTTP API collection.

[Zerodha Technology](http://zerodha.com) (c) 2018. Licensed under the MIT License.

## Documentation
- [PHP client documentation](https://kite.trade/docs/phpkiteconnect/v3)
- [Kite Connect HTTP API documentation](https://kite.trade/docs/connect/v3)

## Installing
Download `kiteconnect.php` and `include()` it in your application.

## Usage
```php
<?php
	include dirname(__FILE__)."/kiteconnect.php";

	// Initialise.
	$kite = new KiteConnect("your_api_key");

	// Assuming you have obtained the `request_token`
	// after the auth flow redirect by redirecting the
	// user to $kite->login_url()
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
	$order_id = $kite->placeOrder("regular", [
		"tradingsymbol" => "INFY",
		"exchange" => "NSE",
		"quantity" => 1,
		"transaction_type" => "BUY",
		"order_type" => "MARKET",
		"product" => "NRML"
	])["order_id"];

	echo "Order id is ".$order_id;
?>
```

Refer to the [PHP client documentation](https://kite.trade/docs/phpkiteconnect/v3) for the complete list of supported methods.

## Changelog
[Check CHANGELOG.md](CHANGELOG.md)

