<?php

namespace KiteConnect;

use DateTime;
use DateTimeZone;
use Exception;
use Closure;
use React\EventLoop\Loop;
use React\Socket\Connector;
use Ratchet\Client\Connector as WsConnector;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;

/**
 * KiteTicker - WebSocket client for streaming live market data
 */
class KiteTicker
{
    // Streaming modes
    public const MODE_LTP = "ltp";
    public const MODE_QUOTE = "quote";
    public const MODE_FULL = "full";

    // Exchange segment constants
    public const EXCHANGE_MAP = [
        "nse" => 1,
        "nfo" => 2,
        "cds" => 3,
        "bse" => 4,
        "bfo" => 5,
        "bcd" => 6,
        "mcx" => 7,
        "mcxsx" => 8,
        "indices" => 9,
    ];

    // WebSocket connection states
    public const STATE_DISCONNECTED = 0;
    public const STATE_CONNECTING = 1;
    public const STATE_CONNECTED = 2;
    public const STATE_RECONNECTING = 3;

    private string $apiKey;
    private string $accessToken;
    private string $wsUrl;
    private int $timeout;
    private int $connectionState = self::STATE_DISCONNECTED;
    private ?WebSocket $connection = null;
    private array $subscribedTokens = [];
    private array $tokenModes = [];
    private bool $autoReconnect = true;
    private int $reconnectAttempts = 0;
    private int $maxReconnectAttempts = 5;
    private int $reconnectDelay = 3;
    private bool $debug = false;

    // Event callbacks
    private array $callbacks = [
        'connect' => [],
        'disconnect' => [],
        'ticks' => [],
        'order_update' => [],
        'error' => [],
        'reconnect' => [],
        'close' => [],
        'message' => [],
        'noreconnect' => []
    ];

    /**
     * Initialize KiteTicker instance
     *
     * @param string $apiKey Kite Connect API key
     * @param string $accessToken Access token obtained after login
     * @param int $timeout Connection timeout in seconds
     * @param bool $autoReconnect Enable automatic reconnection
     * @param bool $debug Enable debug mode
     * @param string $root WebSocket root URL
     */
    public function __construct(
        string $apiKey,
        string $accessToken,
        int $timeout = 30,
        bool $autoReconnect = true,
        bool $debug = false,
        string $root = "wss://ws.kite.trade/"
    ) {
        $this->apiKey = $apiKey;
        $this->accessToken = $accessToken;
        $this->timeout = $timeout;
        $this->autoReconnect = $autoReconnect;
        $this->debug = $debug;
        
        $uid = (string) (time() * 1000); // Millisecond timestamp for uniqueness
        $this->wsUrl = $root . "?api_key={$this->apiKey}&access_token={$this->accessToken}&uid={$uid}";
    }

    /**
     * Bind callback to an event
     *
     * @param string $event Event name (connect, disconnect, ticks, error, etc.)
     * @param callable $callback Callback function (Closure or string function name)
     * @return void
     */
    public function on(string $event, callable $callback): void
    {
        if (isset($this->callbacks[$event])) {
            $this->callbacks[$event][] = $callback;
        }
    }

    /**
     * Trigger event callbacks
     *
     * @param string $event Event name
     * @param array $args Arguments to pass to callbacks
     * @return void
     */
    private function trigger(string $event, array $args = []): void
    {
        if (isset($this->callbacks[$event])) {
            foreach ($this->callbacks[$event] as $callback) {
                try {
                    call_user_func_array($callback, $args);
                } catch (Exception $e) {
                    $this->debugLog("Callback error for event '{$event}': " . $e->getMessage());
                }
            }
        }
    }



    /**
     * Enable or disable auto-reconnect
     *
     * @param bool $enabled Whether to enable auto-reconnect
     * @param int $maxAttempts Maximum reconnection attempts
     * @param int $delay Delay between attempts in seconds
     * @return void
     */
    public function setAutoReconnect(bool $enabled, int $maxAttempts = 5, int $delay = 3): void
    {
        $this->autoReconnect = $enabled;
        $this->maxReconnectAttempts = $maxAttempts;
        $this->reconnectDelay = $delay;
    }

    /**
     * Connect to WebSocket server
     *
     * @return void
     * @throws Exception
     */
    public function connect(): void
    {
        if ($this->connectionState === self::STATE_CONNECTED) {
            $this->debugLog("Already connected");
            return;
        }

        $this->connectionState = self::STATE_CONNECTING;
        $this->debugLog("Connecting to WebSocket...");

        $this->performConnection();
        
        // Run the event loop
        $loop = Loop::get();
        $loop->run();
    }

    /**
     * Perform the actual WebSocket connection
     *
     * @return void
     */
    private function performConnection(): void
    {
        $reactConnector = new Connector([
            'timeout' => $this->timeout
        ]);
        
        $loop = Loop::get();
        $connector = new WsConnector($loop, $reactConnector);

        $connector($this->wsUrl)->then(
            function (WebSocket $conn) {
                $this->handleConnection($conn);
            },
            function (Exception $e) {
                $this->handleConnectionError($e);
            }
        );
    }

    /**
     * Disconnect from WebSocket server
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->autoReconnect = false;
        $this->connectionState = self::STATE_DISCONNECTED;
        
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }

        $this->debugLog("Disconnected from WebSocket");
    }

    /**
     * Subscribe to instruments
     *
     * @param array $tokens List of instrument tokens
     * @param string $mode Streaming mode (ltp, quote, full)
     * @return void
     * @throws Exception
     */
    public function subscribe(array $tokens, string $mode = self::MODE_QUOTE): void
    {
        if (!in_array($mode, [self::MODE_LTP, self::MODE_QUOTE, self::MODE_FULL])) {
            throw new Exception("Invalid mode. Use MODE_LTP, MODE_QUOTE, or MODE_FULL");
        }

        foreach ($tokens as $token) {
            $this->subscribedTokens[] = $token;
            $this->tokenModes[$token] = $mode;
        }

        if ($this->connectionState === self::STATE_CONNECTED) {
            $this->sendSubscription($tokens, $mode);
        }
    }

    /**
     * Unsubscribe from instruments
     *
     * @param array $tokens List of instrument tokens to unsubscribe
     * @return void
     */
    public function unsubscribe(array $tokens): void
    {
        foreach ($tokens as $token) {
            $key = array_search($token, $this->subscribedTokens);
            if ($key !== false) {
                unset($this->subscribedTokens[$key]);
                unset($this->tokenModes[$token]);
            }
        }

        if ($this->connectionState === self::STATE_CONNECTED) {
            $this->sendUnsubscription($tokens);
        }
    }

    /**
     * Set mode for subscribed instruments
     *
     * @param string $mode New streaming mode
     * @param array $tokens List of instrument tokens (empty for all)
     * @return void
     * @throws Exception
     */
    public function setMode(string $mode, array $tokens = []): void
    {
        if (!in_array($mode, [self::MODE_LTP, self::MODE_QUOTE, self::MODE_FULL])) {
            throw new Exception("Invalid mode. Use MODE_LTP, MODE_QUOTE, or MODE_FULL");
        }

        $tokensToUpdate = empty($tokens) ? $this->subscribedTokens : $tokens;

        foreach ($tokensToUpdate as $token) {
            if (in_array($token, $this->subscribedTokens)) {
                $this->tokenModes[$token] = $mode;
            }
        }

        if ($this->connectionState === self::STATE_CONNECTED) {
            $this->sendModeChange($mode, $tokensToUpdate);
        }
    }

    /**
     * Get current connection state
     *
     * @return int Connection state constant
     */
    public function getConnectionState(): int
    {
        return $this->connectionState;
    }

    /**
     * Get subscribed tokens
     *
     * @return array List of subscribed instrument tokens
     */
    public function getSubscribedTokens(): array
    {
        return $this->subscribedTokens;
    }

    /**
     * Check if WebSocket connection is currently connected
     *
     * @return bool True if connected
     */
    public function isConnected(): bool
    {
        return $this->connection !== null && $this->connectionState === self::STATE_CONNECTED;
    }

    /**
     * Handle successful WebSocket connection
     *
     * @param WebSocket $conn WebSocket connection instance
     * @return void
     */
    private function handleConnection(WebSocket $conn): void
    {
        $this->connection = $conn;
        $this->connectionState = self::STATE_CONNECTED;
        $this->reconnectAttempts = 0;

        $this->debugLog("WebSocket connected successfully");

        // Set up event handlers
        $conn->on('message', function (MessageInterface $msg) {
            $this->handleMessage($msg);
        });

        $conn->on('close', function ($code = null, $reason = null) {
            $this->trigger('close', [$code, $reason]);
            $this->handleDisconnection($code, $reason);
        });

        $conn->on('error', function (Exception $e) {
            $this->handleError($e);
        });

        // Trigger onConnect callback
        $this->trigger('connect');

        // Resubscribe to tokens if any
        if (!empty($this->subscribedTokens)) {
            $this->resubscribeAll();
        }
    }

    /**
     * Handle connection errors
     *
     * @param Exception $e Connection error
     * @return void
     */
    private function handleConnectionError(Exception $e): void
    {
        $this->connectionState = self::STATE_DISCONNECTED;
        $this->debugLog("Connection failed: " . $e->getMessage());

        $this->trigger('error', [$e]);

        if ($this->autoReconnect) {
            $this->attemptReconnect();
        }
    }

    /**
     * Handle WebSocket disconnection
     *
     * @param int|null $code Disconnect code
     * @param string|null $reason Disconnect reason
     * @return void
     */
    private function handleDisconnection(?int $code = null, ?string $reason = null): void
    {
        $this->connectionState = self::STATE_DISCONNECTED;
        $this->connection = null;

        $this->debugLog("WebSocket disconnected ({$code} - {$reason})");

        // Trigger onDisconnect callback
        $this->trigger('disconnect', [$code, $reason]);

        // Attempt reconnection for various disconnect reasons
        // 1000 = Normal closure (don't reconnect)
        // 1001 = Going away (server shutdown, might want to reconnect)  
        // 1006 = Abnormal closure (network issues, definitely reconnect)
        // null/undefined = Connection lost (network issues, definitely reconnect)
        $shouldReconnect = $this->autoReconnect && 
                          $code !== 1000 && // Not normal closure
                          $this->reconnectAttempts < $this->maxReconnectAttempts;

        if ($shouldReconnect) {
            $this->debugLog("Disconnect reason: {$code} - {$reason}. Will attempt reconnection.");
            $this->attemptReconnect();
        } else if (!$this->autoReconnect) {
            $this->debugLog("Auto-reconnect is disabled. Not attempting reconnection.");
        } else if ($this->reconnectAttempts >= $this->maxReconnectAttempts) {
            $this->debugLog("Max reconnection attempts ({$this->maxReconnectAttempts}) reached.");
        }
    }

    /**
     * Handle WebSocket errors
     *
     * @param Exception $e Error exception
     * @return void
     */
    private function handleError(Exception $e): void
    {
        $this->debugLog("WebSocket error: " . $e->getMessage());

        $this->trigger('error', [$e]);
    }

    /**
     * Attempt to reconnect to WebSocket
     *
     * @return void
     */
    private function attemptReconnect(): void
    {
        if ($this->reconnectAttempts >= $this->maxReconnectAttempts) {
            $this->debugLog("Max reconnection attempts reached. Giving up.");
            $this->trigger('noreconnect');
            return;
        }

        $this->reconnectAttempts++;
        $this->connectionState = self::STATE_RECONNECTING;

        $this->debugLog("Attempting reconnection #{$this->reconnectAttempts} in {$this->reconnectDelay} seconds...");

        $this->trigger('reconnect', [$this->reconnectAttempts]);

        // Wait before reconnecting
        $loop = Loop::get();
        $loop->addTimer($this->reconnectDelay, function () {
            $this->performConnection();
        });
    }

    /**
     * Handle incoming WebSocket messages
     *
     * @param MessageInterface $msg Incoming message
     * @return void
     */
    private function handleMessage(MessageInterface $msg): void
    {
        $payload = $msg->getPayload();
        $isBinary = $this->isBinaryData($payload);
        
        // Always trigger message event for raw data
        $this->trigger('message', [$payload, $isBinary]);
        
        // Handle binary data (market ticks)
        if ($isBinary && strlen($payload) > 4) {
            $ticks = $this->parseBinary($payload);
            if (!empty($ticks)) {
                $this->trigger('ticks', [$ticks]);
            }
        } else {
            // Handle text messages (order updates, errors, etc.)
            $this->parseTextMessage($payload);
        }
    }

    /**
     * Parse text message (order updates, errors, etc.)
     *
     * @param string $payload Text message payload
     * @return void
     */
    private function parseTextMessage(string $payload): void
    {
        // Decode payload if it's bytes (though PHP handles this automatically)
        if (empty($payload)) {
            return;
        }

        // Try to parse as JSON
        $data = json_decode($payload, true);
        if (!$data) {
            return; // Invalid JSON, ignore
        }

        // Handle order updates
        if (isset($data['type']) && $data['type'] === 'order' && isset($data['data'])) {
            $this->trigger('order_update', [$data['data']]);
        }

        // Handle error messages
        if (isset($data['type']) && $data['type'] === 'error') {
            $errorData = $data['data'] ?? 'WebSocket error occurred';
            $error = new Exception($errorData);
            $this->trigger('error', [$error]);
        }
    }

    /**
     * Send subscription message
     *
     * @param array $tokens Instrument tokens
     * @param string $mode Streaming mode
     * @return void
     */
    private function sendSubscription(array $tokens, string $mode): void
    {
        if (!$this->connection || $this->connectionState !== self::STATE_CONNECTED) {
            $this->debugLog("Cannot send subscription - not connected");
            return;
        }

        // For quote mode, use subscribe action
        if ($mode === self::MODE_QUOTE) {
            $message = json_encode(['a' => 'subscribe', 'v' => $tokens]);
        } else {
            // For LTP and Full modes, use mode action
            $message = json_encode(['a' => 'mode', 'v' => [$mode, $tokens]]);
        }

        $this->connection->send($message);
        $this->debugLog("Sent subscription: " . $message);
    }

    /**
     * Send unsubscription message
     *
     * @param array $tokens Instrument tokens
     * @return void
     */
    private function sendUnsubscription(array $tokens): void
    {
        $message = json_encode(['a' => 'unsubscribe', 'v' => $tokens]);
        $this->connection->send($message);
        $this->debugLog("Sent unsubscription: " . $message);
    }

    /**
     * Send mode change message
     *
     * @param string $mode New streaming mode
     * @param array $tokens Instrument tokens
     * @return void
     */
    private function sendModeChange(string $mode, array $tokens): void
    {
        $message = json_encode(['a' => 'mode', 'v' => [$mode, $tokens]]);
        $this->connection->send($message);
        $this->debugLog("Sent mode change: " . $message);
    }

    /**
     * Resubscribe to all previously subscribed tokens
     *
     * @return void
     */
    private function resubscribeAll(): void
    {
        $modeGroups = [];
        foreach ($this->tokenModes as $token => $mode) {
            if (!isset($modeGroups[$mode])) {
                $modeGroups[$mode] = [];
            }
            $modeGroups[$mode][] = $token;
        }

        foreach ($modeGroups as $mode => $tokens) {
            $this->sendSubscription($tokens, $mode);
        }
    }

    /**
     * Check if payload is binary market data
     *
     * @param string $payload Message payload
     * @return bool True if binary data
     */
    private function isBinaryData(string $payload): bool
    {
        // Empty or very short payloads are not binary market data
        if (strlen($payload) <= 2) {
            return false;
        }

        // Check for binary characteristics - look for control characters
        // Binary market data typically starts with packet count (2 bytes) followed by binary data
        for ($i = 0; $i < min(8, strlen($payload)); $i++) {
            $byte = ord($payload[$i]);
            // If we find control characters (except common whitespace), it's likely binary
            if ($byte < 32 && !in_array($byte, [9, 10, 13])) { // Not tab, newline, carriage return
                return true;
            }
        }

        return false;
    }

    /**
     * Parse binary market data
     *
     * @param string $msg Binary message
     * @return array Parsed tick data
     */
    private function parseBinary(string $msg): array
    {
        $packets = $this->splitPackets($msg);
        $ticks = [];

        foreach ($packets as $packet) {
            $tick = $this->parsePacket($packet);
            if ($tick) {
                $ticks[] = $tick;
            }
        }

        return $ticks;
    }

    /**
     * Split binary message into individual packets
     *
     * @param string $msg Binary message
     * @return array Array of packets
     */
    private function splitPackets(string $msg): array
    {
        // Ignore heartbeat data
        if (strlen($msg) < 2) {
            return [];
        }

        // Number of packets in the message
        $packetCount = $this->unpackBinary($msg, "n", 0, 2);
        $packets = [];
        $offset = 2;

        for ($i = 0; $i < $packetCount; $i++) {
            $packetLen = $this->unpackBinary($msg, "n", $offset, $offset + 2);
            $packets[] = [
                'tick_data' => substr($msg, $offset + 2, $packetLen),
                'tick_len' => $packetLen
            ];
            $offset += 2 + $packetLen;
        }

        return $packets;
    }

    /**
     * Parse individual tick packet
     *
     * @param array $packet Packet data
     * @return array|null Parsed tick data
     */
    private function parsePacket(array $packet): ?array
    {
        $instrumentToken = $this->unpackBinary($packet['tick_data'], "N", 0, 4);
        $segment = $instrumentToken & 0xff;

        // Price divisor based on segment
        $divisor = match ($segment) {
            self::EXCHANGE_MAP["cds"] => 10000000.0,
            self::EXCHANGE_MAP["bcd"] => 10000.0,
            default => 100.0
        };

        // Tradable status - indices are non-tradable
        $tradable = $segment !== self::EXCHANGE_MAP["indices"];

        $tick = [
            "tradable" => $tradable,
            "instrument_token" => $instrumentToken
        ];

        // Parse based on packet length
        switch ($packet['tick_len']) {
            case 8: // LTP mode
                $tick = array_merge($tick, [
                    "mode" => self::MODE_LTP,
                    "last_price" => $this->unpackBinary($packet['tick_data'], "N", 4, 8) / $divisor
                ]);
                break;

            case 28: // Indices quote mode
            case 32: // Indices full mode
                $tick = array_merge($tick, $this->parseIndicesData($packet, $divisor));
                break;

            case 44: // Quote mode
            case 184: // Full mode
                $tick = array_merge($tick, $this->parseTickData($packet, $divisor));
                break;
        }

        return $tick;
    }

    /**
     * Parse indices data
     *
     * @param array $packet Packet data
     * @param float $divisor Price divisor
     * @return array Parsed data
     */
    private function parseIndicesData(array $packet, float $divisor): array
    {
        $lastPrice = $this->unpackBinary($packet['tick_data'], "N", 4, 8) / $divisor;
        $closePrice = $this->unpackBinary($packet['tick_data'], "N", 20, 24) / $divisor;

        $data = [
            "mode" => $packet['tick_len'] === 28 ? self::MODE_QUOTE : self::MODE_FULL,
            "last_price" => $lastPrice,
            "ohlc" => [
                "high" => $this->unpackBinary($packet['tick_data'], "N", 8, 12) / $divisor,
                "low" => $this->unpackBinary($packet['tick_data'], "N", 12, 16) / $divisor,
                "open" => $this->unpackBinary($packet['tick_data'], "N", 16, 20) / $divisor,
                "close" => $closePrice
            ],
            "price_change" => $lastPrice - $closePrice
        ];

        // Add exchange timestamp for full mode
        if ($packet['tick_len'] === 32) {
            $exchangeTimestamp = $this->unpackBinary($packet['tick_data'], "N", 28, 32);
            $data['exchange_timestamp'] = $this->convertTimestamp($exchangeTimestamp);
        }

        return $data;
    }

    /**
     * Parse tick data
     *
     * @param array $packet Packet data
     * @param float $divisor Price divisor
     * @return array Parsed data
     */
    private function parseTickData(array $packet, float $divisor): array
    {
        $data = [
            "mode" => $packet['tick_len'] === 44 ? self::MODE_QUOTE : self::MODE_FULL,
            "last_price" => $this->unpackBinary($packet['tick_data'], "N", 4, 8) / $divisor,
            "last_traded_quantity" => $this->unpackBinary($packet['tick_data'], "N", 8, 12),
            "average_traded_price" => $this->unpackBinary($packet['tick_data'], "N", 12, 16) / $divisor,
            "volume_traded" => $this->unpackBinary($packet['tick_data'], "N", 16, 20),
            "total_buy_quantity" => $this->unpackBinary($packet['tick_data'], "N", 20, 24),
            "total_sell_quantity" => $this->unpackBinary($packet['tick_data'], "N", 24, 28),
            "ohlc" => [
                "open" => $this->unpackBinary($packet['tick_data'], "N", 28, 32) / $divisor,
                "high" => $this->unpackBinary($packet['tick_data'], "N", 32, 36) / $divisor,
                "low" => $this->unpackBinary($packet['tick_data'], "N", 36, 40) / $divisor,
                "close" => $this->unpackBinary($packet['tick_data'], "N", 40, 44) / $divisor
            ]
        ];

        // Parse full mode additional data
        if ($packet['tick_len'] === 184) {
            $data = array_merge($data, $this->parseFullModeData($packet, $divisor));
        }

        return $data;
    }

    /**
     * Parse full mode additional data
     *
     * @param array $packet Packet data
     * @param float $divisor Price divisor
     * @return array Additional full mode data
     */
    private function parseFullModeData(array $packet, float $divisor): array
    {
        $lastTradeTimestamp = $this->unpackBinary($packet['tick_data'], "N", 44, 48);
        $exchangeTimestamp = $this->unpackBinary($packet['tick_data'], "N", 60, 64);

        $data = [
            "last_trade_time" => $this->convertTimestamp($lastTradeTimestamp),
            "oi" => $this->unpackBinary($packet['tick_data'], "N", 48, 52),
            "oi_day_high" => $this->unpackBinary($packet['tick_data'], "N", 52, 56),
            "oi_day_low" => $this->unpackBinary($packet['tick_data'], "N", 56, 60),
            "exchange_timestamp" => $this->convertTimestamp($exchangeTimestamp),
            "depth" => ["buy" => [], "sell" => []]
        ];

        // Parse market depth
        $buyOffset = 64;
        $sellOffset = 124;
        $depthLength = ($sellOffset - $buyOffset) / 12;

        for ($i = 0; $i < $depthLength; $i++) {
            // Buy depth
            $data['depth']['buy'][$i] = [
                'quantity' => $this->unpackBinary($packet['tick_data'], "N", $buyOffset, $buyOffset + 4),
                'price' => $this->unpackBinary($packet['tick_data'], "N", $buyOffset + 4, $buyOffset + 8) / $divisor,
                'orders' => $this->unpackBinary($packet['tick_data'], "n", $buyOffset + 8, $buyOffset + 12)
            ];

            // Sell depth
            $data['depth']['sell'][$i] = [
                'quantity' => $this->unpackBinary($packet['tick_data'], "N", $sellOffset, $sellOffset + 4),
                'price' => $this->unpackBinary($packet['tick_data'], "N", $sellOffset + 4, $sellOffset + 8) / $divisor,
                'orders' => $this->unpackBinary($packet['tick_data'], "n", $sellOffset + 8, $sellOffset + 12)
            ];

            $buyOffset += 12;
            $sellOffset += 12;
        }

        return $data;
    }

    /**
     * Unpack binary data
     *
     * @param string $msg Binary message
     * @param string $format Unpack format
     * @param int $start Start position
     * @param int $end End position
     * @return int Unpacked value
     */
    private function unpackBinary(string $msg, string $format, int $start, int $end): int
    {
        $data = unpack($format, substr($msg, $start, $end - $start));
        return $data[1];
    }

    /**
     * Convert Unix timestamp to local time
     *
     * @param int $timestamp Unix timestamp
     * @return string Formatted local time
     */
    private function convertTimestamp(int $timestamp): string
    {
        $dt = new DateTime();
        $dt->setTimestamp($timestamp);
        $dt->setTimezone(new DateTimeZone("Asia/Kolkata"));
        return $dt->format('Y-m-d H:i:s');
    }

    /**
     * Log debug messages
     *
     * @param string $message Debug message
     * @return void
     */
    private function debugLog(string $message): void
    {
        if ($this->debug) {
            echo "[" . date('Y-m-d H:i:s') . "] KiteTicker: " . $message . "\n";
        }
    }
}
