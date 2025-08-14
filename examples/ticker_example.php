<?php

require_once 'vendor/autoload.php';

use KiteConnect\KiteConnect;
use KiteConnect\KiteTicker;

// Example usage of KiteTicker with the official KiteConnect client

$apiKey = 'your_api_key_here';
$accessToken = 'your_access_token_here';

// Create ticker instance with proper configuration
$ticker = new KiteTicker(
    $apiKey,
    $accessToken,
    30,    // timeout
    true,  // auto-reconnect
    true   // debug mode
);

// Connection event - fires when WebSocket connects successfully
$ticker->on('connect', function() use ($ticker) {
    echo "Connected to KiteTicker WebSocket!\n";

    // Subscribe to instruments after connection
    $ticker->subscribe([738561], KiteTicker::MODE_FULL);
    echo "Subscribed to instrument 738561 (RELIANCE) in FULL mode\n";
});

// Market data event - receives real-time ticks
$ticker->on('ticks', function(array $ticks) {
    foreach ($ticks as $tick) {
        $token = $tick['instrument_token'];
        $price = $tick['last_price'];
        $volume = $tick['volume_traded'];
        $mode = $tick['mode'];
        
        echo "Token: {$token} | Price: {$price} | Volume: {$volume} | Mode: {$mode}\n";
    }
});

// Error event - handles connection and data errors
$ticker->on('error', function(Exception $error) {
    echo "WebSocket Error: " . $error->getMessage() . "\n";
});

// Disconnection event - fires when connection is lost
$ticker->on('disconnect', function(int $code, string $reason) {
    echo "WebSocket Disconnected: [{$code}] {$reason}\n";
});

// Connection close event - fires when connection closes gracefully
$ticker->on('close', function(int $code, string $reason) {
    echo "WebSocket Connection Closed: [{$code}] {$reason}\n";
});

// Reconnection attempt event - fires during auto-reconnect
$ticker->on('reconnect', function(int $attempt) {
    echo "Attempting to reconnect... (Attempt #{$attempt})\n";
});

// Max reconnection attempts reached
$ticker->on('noreconnect', function() {
    echo "Maximum reconnection attempts reached. Connection abandoned.\n";
});

// Raw message event - receives all WebSocket messages
$ticker->on('message', function(string $payload, bool $isBinary) {
    if ($isBinary) {
        echo "Binary message received (" . strlen($payload) . " bytes)\n";
    } else {
        echo "Text message received: " . $payload . "...\n";
    }
});

// Order update event - receives order status updates
$ticker->on('order_update', function(array $orderUpdate) {
    echo "Order Update Received:\n";
    echo "Order ID: {$orderUpdate['order_id']} | Status: {$orderUpdate['status']} | Price: {$orderUpdate['price']} | Quantity: {$orderUpdate['quantity']}\n";
});

echo "Starting KiteTicker WebSocket connection...\n";

// Start the WebSocket connection
$ticker->connect();
