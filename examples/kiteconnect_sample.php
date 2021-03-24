<?php

require_once __DIR__ . '/vendor/autoload.php';

use KiteConnect\KiteConnect;

// Initialise.
$kite = new KiteConnect("api_key");

// Assuming you have obtained the `request_token`
// after the auth flow redirect by redirecting the
// user to $kite->login_url()
try {
    $user = $kite->generateSession("request_token", "secret_key");

    echo "Authentication successful. \n";
    print_r($user);

    $kite->setAccessToken($user->access_token);
} catch (Exception $e) {
    echo "Authentication failed: " . $e->getMessage();

    throw $e;
}

echo $user->user_id . " has logged in";

// Get the list of positions.
echo "Positions: \n";
print_r($kite->getPositions());

// Get the list of holdings.
echo "Holdings: \n";
print_r($kite->getHoldings());

// Retrieve quote and market depth for list of instruments.
echo "Quote: \n";
print_r($kite->getQuote(["NSE:INFY", "NSE:SBIN"]));

// Place order.
$order = $kite->placeOrder("regular", [
    "tradingsymbol" => "INFY",
    "exchange" => "NSE",
    "quantity" => 1,
    "transaction_type" => "BUY",
    "order_type" => "MARKET",
    "product" => "CNC",
]);

echo "Order id is " . $order->order_id;

// fetch order margin
$order_param = [["exchange" => "NSE",
    "tradingsymbol" => "INFY",
    "transaction_type" => $kite::TRANSACTION_TYPE_BUY,
    "variety" => $kite::VARIETY_REGULAR,
    "product" => $kite::PRODUCT_CNC,
    "order_type" => $kite::ORDER_TYPE_MARKET,
    "quantity" => 1,
],];

print_r($kite->orderMargins($order_param));

$place_GTT = $kite->placeGTT([
    "trigger_type" => $kite::GTT_TYPE_SINGLE,
    "tradingsymbol" => "TATAMOTORS",
    "exchange" => "NSE",
    "trigger_values" => array(310),
    "last_price" => 315,
    "orders" => array([
            "transaction_type"   => $kite::TRANSACTION_TYPE_BUY,
            "quantity"   => 1,
            "product"    => $kite::PRODUCT_CNC,
            "order_type" => $kite::ORDER_TYPE_LIMIT,
            "price" => 314
            ])
]);
echo "Trigger id is ".$place_GTT->trigger_id;

?>
