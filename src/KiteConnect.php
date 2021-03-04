<?php

declare(strict_types=1);

namespace KiteConnect;

use Closure;
use DateTime;
use DateTimeZone;
use Exception;
use KiteConnect\Exception\DataException;
use KiteConnect\Exception\GeneralException;
use KiteConnect\Exception\InputException;
use KiteConnect\Exception\NetworkException;
use KiteConnect\Exception\OrderException;
use KiteConnect\Exception\PermissionException;
use KiteConnect\Exception\TokenException;
use phpDocumentor\Reflection\Types\Mixed_;
use stdClass;

/**
 * Kite Connect API client for PHP -- [kite.trade](https://kite.trade) | [Download from Github](https://github.com/zerodhatech/phpkiteconnect)
 * Zerodha Technology (c) 2018. Version 3.0.2b
 * License
 * -------git
 * Kite Connect PHP library is licensed under the MIT License.
 * The library
 * -----------
 * Kite Connect is a set of REST-like APIs that expose
 * many capabilities required to build a complete
 * investment and trading platform. Execute orders in
 * real time, manage user portfolio, stream live market
 * data (WebSockets), and more, with the simple HTTP API collection.
 * This module provides an easy to use abstraction over the HTTP APIs.
 * The HTTP calls have been converted to methods and their JSON responses
 * are returned as native PHP structures, for example, dicts, lists, bools etc.
 * See the **[Kite Connect API documentation](https://kite.trade/docs/connect/v3/)**
 * for the complete list of APIs, supported parameters and values, and response formats.
 * Getting started
 * ---------------
 * <pre>
 * <?php
 * include dirname(__FILE__)."/kiteconnect.php";
 * // Initialise.
 * $kite = new KiteConnect("your_api_key");
 * // Assuming you have obtained the `request_token`
 * // after the auth flow redirect by redirecting the
 * // user to $kite->login_url()
 * try {
 * $user = $kite->generateSession("request_token_obtained", "your_api_secret");
 * echo "Authentication successful. \n";
 * print_r($user);
 * $kite->setAccessToken($user->access_token);
 * } catch(Exception $e) {
 * echo "Authentication failed: ".$e->getMessage();
 * throw $e;
 * }
 * echo $user->user_id." has logged in";
 * // Get the list of positions.
 * echo "Positions: \n";
 * print_r($kite->getPositions());
 * // Place order.
 * $o = $kite->placeOrder("regular", [
 * "tradingsymbol" => "INFY",
 * "exchange" => "NSE",
 * "quantity" => 1,
 * "transaction_type" => "BUY",
 * "order_type" => "MARKET",
 * "product" => "NRML"
 * ]);
 * echo "Order id is ".$o->order_id;
 * ?>
 * </pre>
 * A typical web application
 * -------------------------
 * In a typical web application where a new instance of
 * views, controllers etc. are created per incoming HTTP
 * request, you will need to initialise a new instance of
 * Kite client per request as well. This is because each
 * individual instance represents a single user that's
 * authenticated, unlike an **admin** API where you may
 * use one instance to manage many users.
 * Hence, in your web application, typically:
 * - You will initialise an instance of the Kite client
 * - Redirect the user to the `login_url()`
 * - At the redirect url endpoint, obtain the
 * `request_token` from the query parameters
 * - Initialise a new instance of Kite client,
 * use `request_access_token()` to obtain the `access_token`
 * along with authenticated user data
 * - Store this response in a session and use the
 * stored `access_token` and initialise instances
 * of Kite client for subsequent API calls.
 * Exceptions
 * ----------
 * Kite Connect client saves you the hassle of detecting API errors
 * by looking at HTTP codes or JSON error responses. Instead,
 * it raises aptly named **[exceptions](exceptions.m.html)** that you can catch.
 */
class KiteConnect
{
    // Constants
    // Products
    public const PRODUCT_MIS = "MIS";
    public const PRODUCT_CNC = "CNC";
    public const PRODUCT_NRML = "NRML";
    public const PRODUCT_CO = "CO";
    public const PRODUCT_BO = "BO";

    // Order types
    public const ORDER_TYPE_MARKET = "MARKET";
    public const ORDER_TYPE_LIMIT = "LIMIT";
    public const ORDER_TYPE_SLM = "SL-M";
    public const ORDER_TYPE_SL = "SL";

    // Varieties
    public const VARIETY_REGULAR = "regular";
    public const VARIETY_BO = "bo";
    public const VARIETY_CO = "co";
    public const VARIETY_AMO = "amo";

    // Transaction type
    public const TRANSACTION_TYPE_BUY = "BUY";
    public const TRANSACTION_TYPE_SELL = "SELL";

    // Validity
    public const VALIDITY_DAY = "DAY";
    public const VALIDITY_IOC = "IOC";

    // Margins segments
    public const MARGIN_EQUITY = "equity";
    public const MARGIN_COMMODITY = "commodity";

    public const STATUS_CANCELLED = "CANCELLED";
    public const STATUS_REJECTED = "REJECTED";
    public const STATUS_COMPLETE = "COMPLETE";

    // GTT Types
    public const GTT_TYPE_OCO = "two-leg";
    public const GTT_TYPE_SINGLE = "single";

    // GTT Statuses
    public const GTT_STATUS_ACTIVE = "active";
    public const GTT_STATUS_TRIGGERED = "triggered";
    public const GTT_STATUS_DISABLED = "disabled";
    public const GTT_STATUS_EXPIRED = "expired";
    public const GTT_STATUS_CANCELLED = "cancelled";
    public const GTT_STATUS_REJECTED = "rejected";
    public const GTT_STATUS_DELETED = "deleted";

    public const _version = "3.2.0";

    // Default root API endpoint. It's possible to
    // override this by passing the `root` parameter during initialisation.
    /** @var String */
    private $_root = "https://api.kite.trade";

    /** @var String */
    private $_login = "https://kite.trade/connect/login";

    /** @var array */
    private static $_date_fields = ["order_timestamp", "exchange_timestamp", "created", "last_instalment", "fill_timestamp", "timestamp", "last_trade_time"];

    // API route map.
    /** @var array */
    private $_routes = [
        "api.token" => "/session/token",
        "api.token.invalidate" => "/session/token",
        "api.token.renew" => "/session/refresh_token",
        "user.profile" => "/user/profile",
        "user.margins" => "/user/margins",
        "user.margins.segment" => "/user/margins/{segment}",

        "orders" => "/orders",
        "trades" => "/trades",

        "order.info" => "/orders/{order_id}",
        "order.place" => "/orders/{variety}",
        "order.modify" => "/orders/{variety}/{order_id}",
        "order.cancel" => "/orders/{variety}/{order_id}",
        "order.trades" => "/orders/{order_id}/trades",
        "order.margins" => "/margins/orders",

        "portfolio.positions" => "/portfolio/positions",
        "portfolio.holdings" => "/portfolio/holdings",
        "portfolio.positions.convert" => "/portfolio/positions",

        # MF api endpoints
        "mf.orders" => "/mf/orders",
        "mf.order.info" => "/mf/orders/{order_id}",
        "mf.order.place" => "/mf/orders",
        "mf.order.cancel" => "/mf/orders/{order_id}",

        "mf.sips" => "/mf/sips",
        "mf.sip.info" => "/mf/sips/{sip_id}",
        "mf.sip.place" => "/mf/sips",
        "mf.sip.modify" => "/mf/sips/{sip_id}",
        "mf.sip.cancel" => "/mf/sips/{sip_id}",

        "mf.holdings" => "/mf/holdings",
        "mf.instruments" => "/mf/instruments",

        "market.instruments.all" => "/instruments",
        "market.instruments" => "/instruments/{exchange}",
        "market.margins" => "/margins/{segment}",
        "market.historical" => "/instruments/historical/{instrument_token}/{interval}",
        "market.trigger_range" => "/instruments/trigger_range/{transaction_type}",

        "market.quote" => "/quote",
        "market.quote.ohlc" => "/quote/ohlc",
        "market.quote.ltp" => "/quote/ltp",

        "gtt.triggers" => "/gtt/triggers",
        "gtt.trigger_info" => "/gtt/triggers/{trigger_id}",
        "gtt.place" => "/gtt/triggers",
        "gtt.modify" => "/gtt/triggers/{trigger_id}",
        "gtt.delete" => "/gtt/triggers/{trigger_id}",
    ];

    // Instance variables
    /** @var int */
    private $timeout;

    /** @var mixed */
    private $api_key;

    /** @var mixed */
    private $access_token;

    /** @var mixed */
    private $debug;

    /** @var Closure */
    private $session_hook;

    /**
     * Initialise a new Kite Connect client instance.
     *
     * @param string $api_key The Kite Connect API key issued to you.
     * @param string|null $access_token The token obtained after the login flow in exchange for the `request_token`.
     *                                Pre-login, this will default to None,
     *                                but once you have obtained it, you should
     *                                persist it in a database or session to pass
     *                                to the Kite Connect class initialisation for subsequent requests
     * @param string|null $root The Kite Connect API end point root. Unless you explicitly
     *                                want to send API requests to a non-default endpoint, this
     *                                should be left as null.
     * @param bool $debug If set to True, requests and responses will be `echo`ed.
     * @param int $timeout The the time (seconds) for which the API client will wait for
     *                                a request to complete before it fails.
     * @return void
     */
    public function __construct(
        string $api_key,
        string $access_token = null,
        string $root = null,
        bool $debug = false,
        int $timeout = 7
    ) {
        $this->api_key = $api_key;
        $this->access_token = $access_token;
        $this->debug = $debug;
        $this->session_hook = null;
        $this->timeout = $timeout;

        if ($root) {
            $this->_root = $root;
        }
    }

    /**
     * Set a callback hook for session (TokenError -- timeout, expiry etc.) errors.
     *
     * An `access_token` (login session) can become invalid for a number of
     * reasons, but it doesn't make sense for the client to
     * try and catch it during every API call.
     *
     * A callback method that handles session errors
     * can be set here and when the client encounters
     * a token error at any point, it'll be called.
     * This callback, for instance, can log the user out of the UI,
     * clear session cookies, or initiate a fresh login.
     *
     * @param Closure $method The callback function that should be called in case of a TokenError error.
     * @return void
     */
    public function setSessionExpiryHook(Closure $method): void
    {
        $this->session_hook = $method;
    }

    /**
     * Set the `access_token` received after a successful authentication.
     *
     * @param string $access_token The `access_token` received after a successful authentication token exchange.
     * @return void
     */
    public function setAccessToken(string $access_token): void
    {
        $this->access_token = $access_token;
    }

    /**
     * Get the remote login url to which a user should be redirected to initiate the login flow.
     *
     * @return string
     */
    public function getLoginURL(): string
    {
        return "{$this->_login}?api_key={$this->api_key}&v=3";
    }

    /**
     * Do the token exchange with the `request_token` obtained after the login flow,
     * and retrieve the `access_token` required for all subsequent requests. The
     * response contains not just the `access_token`, but metadata for
     * the user who has authenticated.
     *
     * @param string $request_token Token obtained from the GET params after a successful login redirect
     * @param string $api_secret The API secret issued with the API key.
     * @return mixed
     * @throws Exception
     */
    public function generateSession(string $request_token, string $api_secret)
    {
        $checksum = hash("sha256", $this->api_key . $request_token . $api_secret);

        $response = $this->_post("api.token", [
            "api_key" => $this->api_key,
            "request_token" => $request_token,
            "checksum" => $checksum,
        ]);

        if ($response->access_token) {
            $this->setAccessToken($response->access_token);
        }

        if ($response->login_time) {
            $response->login_time = new DateTime($response->login_time, new DateTimeZone("Asia/Kolkata"));
        }

        return $response;
    }

    /**
     * Kill the session by invalidating the access token.
     *
     * @param string|null $access_token (Optional) `access_token` to invalidate. Default is the active `access_token`.
     * @return mixed
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function invalidateAccessToken($access_token = null)
    {
        if (! $access_token) {
            $access_token = $this->access_token;
        }

        return $this->_delete("api.token.invalidate", [
            "access_token" => $access_token,
            "api_key" => $this->api_key,
        ]);
    }

    /**
     * Renew access token by active refresh token.
     * Renewed access token is implicitly set.
     *
     * @param string $refresh_token Token obtained from previous successful login.
     * @param string $api_secret The API secret issued with the API key.
     * @return array
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function renewAccessToken(string $refresh_token, string $api_secret): array
    {
        $checksum = hash("sha256", $this->api_key . $refresh_token . $api_secret);

        $resp = $this->_post("api.token.renew", [
            "api_key" => $this->api_key,
            "refresh_token" => $refresh_token,
            "checksum" => $checksum,
        ]);

        if (! empty($resp->access_token)) {
            $this->setAccessToken($resp->access_token);
        }

        return $resp;
    }

    /**
     * Invalidate refresh token.
     *
     * @param string $refresh_token Refresh token to invalidate.
     * @return mixed
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function invalidateRefreshToken(string $refresh_token): Mixed_
    {
        return $this->_delete("api.token.invalidate", [
            "refresh_token" => $refresh_token,
            "api_key" => $this->api_key,
        ]);
    }

    /**
     * Get user profile.
     *
     * @return array
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function getProfile(): array
    {
        return $this->_get("user.profile");
    }

    /**
     * Get account balance and cash margin details for a particular segment.
     *
     * @param string|null $segment (Optional) trading segment (eg: equity or commodity)
     * @return array
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function getMargins(?string $segment = null): array
    {
        if (! $segment) {
            return $this->_get("user.margins");
        }

        return $this->_get("user.margins.segment", ["segment" => $segment]);
    }

    /**
     * Place an order.
     *
     * @param string $variety "variety"  Order variety (ex. bo, co, amo, regular).
     * @param array $params [Order parameters](https://kite.trade/docs/connect/v3/orders/#regular-order-parameters)
     *                $params string        "exchange" Exchange in which instrument is listed (NSE, BSE, NFO, BFO, CDS, MCX).
     *                $params string        "tradingsymbol" Tradingsymbol of the instrument (ex. RELIANCE, INFY).
     *                $params string        "transaction_type" Transaction type (BUY or SELL).
     *                $params string        "product" Product code (NRML, MIS, CNC).
     *                $params string        "order_type" Order type (SL, SL-M, LIMIT, MARKET).
     *                $params int            "quantity" Order quantity
     *                $params int|null    "disclosed_quantity" (Optional) Disclosed quantity
     *                $params float|null  "price" (Optional) Order Price
     *                $params float|null  "trigger_price" (Optional) Trigger price
     *                $params float|null  "squareoff" (Mandatory only for bracker orders) Square off value
     *                $params float|null  "stoploss" (Mandatory only for bracker orders) Stoploss value
     *                $params float|null  "trailing_stoploss" (Optional) Trailing stoploss value (only for bracket orders)
     *                $params float|null  "tag" (Optional) Order tag
     *                $params string|null "validity" (Optional) Order validity (DAY, IOC).
     * @return mixed|null
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function placeOrder(string $variety, array $params)
    {
        $params["variety"] = $variety;

        return $this->_post("order.place", $params);
    }

    /**
     * Modify an open order.
     *
     * @param string $variety "variety"  Order variety (ex. bo, co, amo, regular).
     * @param string $order_id "order_id" Order id.
     * @param array $params [Order modify parameters](https://kite.trade/docs/connect/v3/orders/#regular-order-parameters_1).
     *                $params string        "parent_order_id" (Optional) Parent order id if its a multi legged order.
     *                $params string        "order_type" (Optional) Order type (SL, SL-M, MARKET)
     *                $params int            "quantity" (Optional) Order quantity
     *                $params int|null    "disclosed_quantity" (Optional) Disclosed quantity
     *                $params float|null  "price" (Optional) Order Price
     *                $params float|null  "trigger_price" (Optional) Trigger price
     *                $params string|null "validity" (Optional) Order validity (DAY, IOC).
     *
     * @return mixed
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function modifyOrder(string $variety, string $order_id, array $params): Mixed_
    {
        $params["variety"] = $variety;
        $params["order_id"] = $order_id;

        return $this->_put("order.modify", $params);
    }

    /**
     * Cancel an open order.
     *
     * @param string $variety "variety"  Order variety (ex. bo, co, amo, regular).
     * @param string $order_id "order_id" Order id.
     * @param array|null $params [Order cancel parameters](https://kite.trade/docs/connect/v3/orders/#cancelling-orders)
     *                $params string        "parent_order_id" (Optional) Parent order id if its a multi legged order.
     *
     * @return mixed
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function cancelOrder(string $variety, string $order_id, array $params = null)
    {
        if (! $params) {
            $params = [];
        }

        $params["variety"] = $variety;
        $params["order_id"] = $order_id;

        return $this->_delete("order.cancel", $params);
    }

    /**
     * Exit a BO or CO.
     *
     * @param string $variety "variety"  Order variety (ex. bo, co, amo, regular).
     * @param string $order_id "order_id" Order id.
     * @param array $params [Order cancel parameters](https://kite.trade/docs/connect/v3/orders/#cancelling-orders)
     *                $params string        "parent_order_id" (Optional) Parent order id if its a multi legged order.
     *
     * @return mixed
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function exitOrder(string $variety, string $order_id, array $params)
    {
        return $this->cancelOrder($variety, $order_id, $params);
    }

    /**
     * Get the list of all orders placed for the day.
     *
     * @return array
     * @throws DataException
     * @throws Exception
     */
    public function getOrders(): array
    {
        return $this->_format_response_array($this->_get("orders"));
    }

    /**
     * Get history of the individual order.
     * @param string $order_id ID of the order (optional) whose trades
     *                            are to be retrieved. If no `order_id` is
     *                            specified, all trades for the day are returned.
     * @return array
     * @throws DataException
     * @throws Exception
     */
    public function getOrderHistory(string $order_id): array
    {
        return $this->_format_response_array($this->_get("order.info", ["order_id" => $order_id]));
    }

    /**
     * Fetch order margin
     *
     * @param array $params Order params to fetch margin detail
     *                $params string       "exchange" Name of the exchange(eg. NSE, BSE, NFO, CDS, MCX)
     *              $params string       "tradingsymbol" Trading symbol of the instrument
     *              $params string       "transaction_type" eg. BUY, SELL
     *              $params string       "variety" Order variety (regular, amo, bo, co etc.)
     *              $params string       "product" Margin product to use for the order
     *              $params string       "order_type" Order type (MARKET, LIMIT etc.)
     *              $params int       "quantity" Quantity of the order
     *              $params float|null "price" Price at which the order is going to be placed (LIMIT orders)
     *              $params float|null "trigger_price" Trigger price (for SL, SL-M, CO orders)
     * @return array
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function orderMargins(array $params): array
    {
        return $this->_post("order.margins", (array)json_encode($params), 'Content-type: application/json');
    }

    /**
     * Retrieve the list of trades executed.
     * @return array
     * @throws DataException
     * @throws Exception
     */
    public function getTrades(): array
    {
        return $this->_format_response_array($this->_get("trades"));
    }

    /**
     * Retrieve the list of trades executed for a particular order.
     *
     * An order can be executed in tranches based on market conditions.
     * These trades are individually recorded under an order.
     *
     * @param string $order_id ID of the order (optional) whose trades
     *                            are to be retrieved. If no `order_id` is
     *                            specified, all trades for the day are returned.
     * @return array
     * @throws DataException
     * @throws Exception
     */
    public function getOrderTrades(string $order_id): array
    {
        return $this->_format_response_array($this->_get("order.trades", ["order_id" => $order_id]));
    }

    /**
     * Retrieve the list of positions
     *
     * @return array
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function getPositions(): array
    {
        return $this->_get("portfolio.positions");
    }

    /**
     * Retrieve the list of equity holdings.
     *
     * @return array
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function getHoldings(): array
    {
        return $this->_get("portfolio.holdings");
    }

    /**
     * Modify an open position's product type.
     * @param array $params [Parameters](https://kite.trade/docs/connect/v3/portfolio/#position-conversion) describing the open position to be modified.
     *               $param string "exchange" Exchange in which instrument is listed (NSE, BSE, NFO, BFO, CDS, MCX).
     *               $param string "tradingsymbol" Tradingsymbol of the instrument  (ex. RELIANCE, INFY).
     *               $param string "transaction_type" Transaction type (BUY or SELL).
     *               $param string "position_type" Position type (overnight, day).
     *               $param string "quantity" Position quantity
     *               $param string "old_product" Current product code (NRML, MIS, CNC).
     *               $param string "new_product" New Product code (NRML, MIS, CNC).
     * @return bool
     * @throws DataException
     */
    public function convertPosition(array $params): bool
    {
        return $this->_put("portfolio.positions.convert", $params);
    }

    /**
     * Retrieve the list of market instruments available to trade.
     *
     * Note that the results could be large, several hundred KBs in size,
     * with tens of thousands of entries in the array. The actual response
     * from the API is in the CSV format, but this function parses the CSV
     * into an array of Objects where an individual object looks like:
     * <pre>Class Object
     *    (
     *        [instrument_token] => 128031748
     *        [exchange_token] => 500124
     *        [tradingsymbol] => DRREDDY*
     *        [name] => DR.REDDYS LABORATORIES
     *        [last_price] => 0
     *        [expiry] =>
     *        [strike] => 0
     *        [tick_size] => 0.05
     *        [lot_size] => 1
     *        [instrument_type] => EQ
     *        [segment] => BSE
     *        [exchange] => BSE
     *    )
     * </pre>
     *
     * @param string|null $exchange (Optional) Exchange.
     * @return array
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function getInstruments(?string $exchange = null): array
    {
        if ($exchange) {
            $params = ["exchange" => $exchange];

            return $this->_parseInstrumentsCSV($this->_get("market.instruments", $params));
        } else {
            return $this->_parseInstrumentsCSV($this->_get("market.instruments.all"));
        }
    }

    /**
     * Retrieve quote and market depth for list of instruments.
     *
     * @param array $instruments instruments is a list of instruments, Instrument are in the format of `tradingsymbol:exchange`.
     *    For example NSE:INFY
     * @return array
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function getQuote(array $instruments): array
    {
        return $this->_format_response_array($this->_get("market.quote", ["i" => $instruments]));
    }

    /**
     * Retrieve OHLC for list of instruments.
     *
     * @param array $instruments instruments is a list of instruments, Instrument are in the format of `tradingsymbol:exchange`.
     *    For example NSE:INFY
     * @return array
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function getOHLC(array $instruments): array
    {
        return $this->_get("market.quote.ohlc", ["i" => $instruments]);
    }

    /**
     * Retrieve LTP for list of instruments.
     *
     * @param array $instruments instruments is a list of instruments, Instrument are in the format of `tradingsymbol:exchange`.
     *    For example NSE:INFY
     * @return array
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function getLTP(array $instruments): array
    {
        return $this->_get("market.quote.ltp", ["i" => $instruments]);
    }

    /**
     * Retrieve historical data (candles) for an instrument.
     *
     * Although the actual response JSON from the API does not have field
     * names such has 'open', 'high' etc., this functin call structures
     * the data into an array of objects with field names. For example:
     * <pre>stdClass Object
     *    (
     *        [date] => 2016-05-02T09:15:00+0530
     *        [open] => 1442
     *        [high] => 1446.45
     *        [low] => 1416.15
     *        [close] => 1420.55
     *        [volume] => 205976
     *    )
     * </pre>
     *
     *
     * @param string $instrument_token "instrument_token" Instrument identifier (retrieved from the instruments()) call.
     * @param string $interval "interval" candle interval (minute, day, 5 minute etc.)
     * @param string|DateTime $from "from" From date (String in format of 'yyyy-mm-dd HH:MM:SS' or Date object).
     * @param string|DateTime $to "to" To date (String in format of 'yyyy-mm-dd HH:MM:SS' or Date object).
     * @param bool $continuous "continuous" is a bool flag to get continuous data for futures and options instruments. Defaults to false.
     * @param bool $oi
     * @return array
     * @throws Exception
     */
    public function getHistoricalData(
        string $instrument_token,
        string $interval,
        $from,
        $to,
        bool $continuous = false,
        bool $oi = false
    ): array {
        $params = [
            "instrument_token" => $instrument_token,
            "interval" => $interval,
            "from" => $from,
            "to" => $to,
            "continuous" => $continuous,
            "oi" => $oi,
        ];

        if ($from instanceof DateTime) {
            $params["from"] = $from->format("Y-m-d H:i:s");
        }

        if ($to instanceof DateTime) {
            $params["to"] = $to->format("Y-m-d H:i:s");
        }

        if ($params["continuous"] == false) {
            $params["continuous"] = 0;
        } else {
            $params["continuous"] = 1;
        }

        if ($params["oi"] == false) {
            $params["oi"] = 0;
        } else {
            $params["oi"] = 1;
        }

        $data = $this->_get("market.historical", $params);

        $records = [];
        foreach ($data->candles as $j) {
            $r = new stdclass;
            $r->date = new DateTime($j[0], new DateTimeZone("Asia/Kolkata"));
            $r->open = $j[1];
            $r->high = $j[2];
            $r->low = $j[3];
            $r->close = $j[4];
            $r->volume = $j[5];
            if (! empty($j[6])) {
                $r->oi = $j[6];
            }

            $records[] = $r;
        }

        return $records;
    }

    /**
     * Retrieve the buy/sell trigger range for Cover Orders.
     *
     * @param string $transaction_type Transaction type
     * @param mixed $instruments
     * @return array
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function getTriggerRange(string $transaction_type, $instruments): array
    {
        return $this->_get(
            "market.trigger_range",
            ["i" => $instruments, "transaction_type" => strtolower($transaction_type)]
        );
    }

    /**
     * Get the list of MF orders / order info for individual order.
     * @param string|null $order_id (Optional) Order id.
     * @return array
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function getMFOrders(?string $order_id = null): array
    {
        if ($order_id) {
            return $this->_format_response($this->_get("mf.order.info", ["order_id" => $order_id]));
        }

        return $this->_format_response_array($this->_get("mf.orders"));
    }

    /**
     * Get the list of MF holdings.
     * @return array
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function getMFHoldings(): array
    {
        return $this->_get("mf.holdings");
    }

    /**
     * Place an mutual fund order.
     *
     * @param array $params [Order parameters](https://kite.trade/docs/connect/v3/mf/#orders)
     *                $param string        "tradingsymbol" Tradingsymbol (ISIN) of the fund.
     *                $param string        "transaction_type" Transaction type (BUY or SELL).
     *                $param int|null    "quantity" Quantity to SELL. Not applicable on BUYs.
     *                $param float|null    "amount" (Optional) Amount worth of units to purchase. Not applicable on SELLs
     *                $param string|null    "tag" (Optional) An optional tag to apply to an order to identify it (alphanumeric, max 8 chars)
     * @return string
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function placeMFOrder(array $params): string
    {
        return $this->_post("mf.order.place", $params);
    }

    /**
     * Cancel an mutual fund order.
     *
     * @param string $order_id Order id.
     * @return string
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function cancelMFOrder(string $order_id): string
    {
        return $this->_delete("mf.order.cancel", ["order_id" => $order_id]);
    }

    /**
     * Get the list of mutual fund SIP's or individual SIP info.
     * @param string|null $sip_id (Optional) SIP id.
     * @return array
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function getMFSIPS(?string $sip_id = null): array
    {
        if ($sip_id) {
            return $this->_format_response($this->_get("mf.sip.info", ["sip_id" => $sip_id]));
        }

        return $this->_format_response_array($this->_get("mf.sips"));
    }

    /**
     * Place an mutual fund order.
     *
     * @param array $params [Mutual fund SIP parameters](https://kite.trade/docs/connect/v3/mf/#sip-orders)
     *                $param string        "tradingsymbol" Tradingsymbol (ISIN) of the fund.
     *                $param float        "amount" Amount worth of units to purchase. Not applicable on SELLs
     *                $param int            "instalments" Number of instalments to trigger. If set to -1, instalments are triggered at fixed intervals until the SIP is cancelled
     *                $param string        "frequency" Order frequency. weekly, monthly, or quarterly.
     *                $param float|null    "initial_amount" (Optional) Amount worth of units to purchase before the SIP starts.
     *                $param int|null    "instalment_day" (Optional) If frequency is monthly, the day of the month (1, 5, 10, 15, 20, 25) to trigger the order on.
     *                $param string|null    "tag" An optional (Optional) tag to apply to an order to identify it (alphanumeric, max 8 chars)
     * @return string
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function placeMFSIP(array $params): string
    {
        return $this->_post("mf.sip.place", $params);
    }

    /**
     * Place an mutual fund order.
     *
     * @param string $sip_id Mutual fund SIP ID.
     * @param array $params [Mutual fund SIP modify parameters](https://kite.trade/docs/connect/v1/#orders30)
     *                $param float        "amount" Amount worth of units to purchase. Not applicable on SELLs
     *                $param int|null    "instalments" (Optional) Number of instalments to trigger. If set to -1, instalments are triggered at fixed intervals until the SIP is cancelled
     *                $param string|null    "frequency" (Optional) Order frequency. weekly, monthly, or quarterly.
     *                $param int|null    "instalment_day" (Optional) If frequency is monthly, the day of the month (1, 5, 10, 15, 20, 25) to trigger the order on.
     *                $param string|null    "status" (Optional) Pause or unpause an SIP (active or paused).
     * @return string
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function modifyMFSIP(string $sip_id, array $params): string
    {
        $params["sip_id"] = $sip_id;

        return $this->_put("mf.sip.modify", $params);
    }

    /**
     * Cancel an mutual fund order.
     *
     * @param string $sip_id SIP id.
     * @return string
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function cancelMFSIP(string $sip_id): string
    {
        return $this->_delete("mf.sip.cancel", ["sip_id" => $sip_id]);
    }

    /**
     * Get list of mutual fund instruments.
     *
     * @return array
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function getMFInstruments(): array
    {
        return $this->_parseMFInstrumentsCSV($this->_get("mf.instruments"));
    }

    /**
     * Get the list of all orders placed for the day.
     * @return array
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function getGTTs(): array
    {
        return $this->_format_response_array($this->_get("gtt.triggers"));
    }

    /**
     * Get detail of individual GTT order.
     * @param string $trigger_id "trigger_id" Trigger ID
     * @return array
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function getGTT(string $trigger_id): array
    {
        return $this->_format_response($this->_get("gtt.trigger_info", ["trigger_id" => $trigger_id]));
    }

    /**
     * Delete an GTT order
     * @param string $trigger_id "trigger_id" Trigger ID
     * @return mixed
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function deleteGTT(string $trigger_id)
    {
        return $this->_delete("gtt.delete", ["trigger_id" => $trigger_id]);
    }

    /**
     * @param mixed $params
     * @return array
     * @throws DataException
     */
    private function getGTTPayload($params): array
    {
        if ($params["trigger_type"] == self::GTT_TYPE_OCO && count($params["trigger_values"]) != 2) {
            throw new DataException("Invalid `trigger_values` for `OCO` order type");
        }
        if ($params["trigger_type"] == self::GTT_TYPE_SINGLE && count($params["trigger_values"]) != 1) {
            throw new DataException("Invalid `trigger_values` for `single` order type");
        }
        $condition = [
            "exchange" => $params["exchange"],
            "tradingsymbol" => $params["tradingsymbol"],
            "trigger_values" => $params["trigger_values"],
            "last_price" => (float)$params["last_price"],
        ];
        $orders = [];
        foreach ($params["orders"] as &$o) {
            array_push($orders, [
                "transaction_type" => $o["transaction_type"],
                "order_type" => $o["order_type"],
                "product" => $o["product"],
                "quantity" => (int)$o["quantity"],
                "price" => (float)($o["price"]),
                "exchange" => $params["exchange"],
                "tradingsymbol" => $params["tradingsymbol"],
            ]);
        }

        return [
            "condition" => $condition,
            "orders" => $orders,
        ];
    }

    /**
     * Place a GTT. Check [GTT documentation](https://kite.trade/docs/connect/v3/gtt/#placing-orders) for details.
     * <code>
     * $params = [
     *        // GTT type, its either `$kite::GTT_TYPE_OCO` or `$kite::GTT_TYPE_SINGLE`.
     *        "trigger_type" => $kite::GTT_TYPE_OCO,
     *        // Tradingsymbol of the instrument (ex. RELIANCE, INFY).
     *        "tradingsymbol" => "SBIN",
     *        // Exchange in which instrument is listed (NSE, BSE, NFO, BFO, CDS, MCX).
     *        "exchange" => "NSE",
     *        // List of trigger values, number of items depends on trigger type.
     *        "trigger_values" => array(300, 400),
     *        // Price at which trigger is created. This is usually the last price of the instrument.
     *        "last_price" => 318,
     *        // List of orders. Check [order params](https://kite.trade/docs/connect/v3/orders/#regular-order-parameters) for all available params.
     *        "orders" => array([
     *            "transaction_type" => $kite::TRANSACTION_TYPE_SELL,
     *            "quantity" => 1,
     *            "product" => $kite::PRODUCT_CNC,
     *            "order_type" => $kite::ORDER_TYPE_LIMIT,
     *            "price" => 300
     *        ], [
     *            "transaction_type" => $kite::TRANSACTION_TYPE_SELL,
     *            "quantity" => 1,
     *            "product" => $kite::PRODUCT_CNC,
     *            "order_type" => $kite::ORDER_TYPE_LIMIT,
     *            "price" => 400
     *        ])
     *    ]
     * </code>
     *
     * @param array $params GTT Params. Check above for required fields.
     * @return mixed
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    public function placeGTT(array $params)
    {
        $payload = $this->getGTTPayload($params);

        return $this->_post("gtt.place", [
            "condition" => json_encode($payload["condition"]),
            "orders" => json_encode($payload["orders"]),
            "type" => $params["trigger_type"],
        ]);
    }

    /**
     * Modify GTT. Check [GTT documentation](https://kite.trade/docs/connect/v3/gtt/#modify-order) for details.
     * <code>
     * $params = [
     *        // GTT type, its either `$kite::GTT_TYPE_OCO` or `$kite::GTT_TYPE_SINGLE`.
     *        "trigger_type" => $kite::GTT_TYPE_OCO,
     *        // Tradingsymbol of the instrument (ex. RELIANCE, INFY).
     *        "tradingsymbol" => "SBIN",
     *        // Exchange in which instrument is listed (NSE, BSE, NFO, BFO, CDS, MCX).
     *        "exchange" => "NSE",
     *        // List of trigger values, number of items depends on trigger type.
     *        "trigger_values" => array(300, 400),
     *        // Price at which trigger is created. This is usually the last price of the instrument.
     *        "last_price" => 318,
     *        // List of orders. Check [order params](https://kite.trade/docs/connect/v3/orders/#regular-order-parameters) for all available params.
     *        "orders" => array([
     *            "transaction_type" => $kite::TRANSACTION_TYPE_SELL,
     *            "quantity" => 1,
     *            "product" => $kite::PRODUCT_CNC,
     *            "order_type" => $kite::ORDER_TYPE_LIMIT,
     *            "price" => 300
     *        ], [
     *            "transaction_type" => $kite::TRANSACTION_TYPE_SELL,
     *            "quantity" => 1,
     *            "product" => $kite::PRODUCT_CNC,
     *            "order_type" => $kite::ORDER_TYPE_LIMIT,
     *            "price" => 400
     *        ])
     *    ]
     * </code>
     * @param int $trigger_id GTT Trigger ID
     * @param array $params GTT Params. Check above for required fields.
     * @return mixed
     * @throws DataException
     */
    public function modifyGTT(int $trigger_id, array $params)
    {
        $payload = $this->getGTTPayload($params);

        return $this->_put("gtt.modify", [
            "condition" => json_encode($payload["condition"]),
            "orders" => json_encode($payload["orders"]),
            "type" => $params["trigger_type"],
            "trigger_id" => $trigger_id,
        ]);
    }

    /**
     * Format response array, For example datetime string to DateTime object
     * @param mixed $data
     * @return mixed
     * @throws Exception
     */
    private function _format_response($data)
    {
        foreach (self::$_date_fields as $field) {
            if (isset($data->$field) && strlen($data->$field) == 19) {
                $data->$field = new DateTime($data->$field, new DateTimeZone("Asia/Kolkata"));
            }
        }

        return $data;
    }

    /**
     * Format array of responses
     * @param mixed $data
     * @return array
     * @throws Exception
     */
    private function _format_response_array($data): array
    {
        $results = [];
        foreach ($data as $k => $item) {
            $results[$k] = $this->_format_response($item);
        }

        return $results;
    }

    /**
     * Alias for sending a GET request.
     *
     * @param string $route Route name mapped in self::$_routes.
     * @param array|null $params Request parameters.
     * @param string $header_content
     * @return mixed                    Array or object (deserialised JSON).
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    private function _get(string $route, $params = null, $header_content = '')
    {
        return $this->_request($route, "GET", $params, $header_content);
    }

    /**
     * Alias for sending a GET request.
     *
     * @param string $route Route name mapped in self::$_routes.
     * @param array|null $params Request parameters.
     * @param string $header_content
     * @return mixed                    Array or object (deserialised JSON).
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    private function _post($route, $params = null, $header_content = '')
    {
        return $this->_request($route, "POST", $params, $header_content);
    }

    /**
     * Alias for sending a PUT request.
     *
     * @param string $route Route name mapped in self::$_routes.
     * @param array|null $params Request parameters.
     * @param string $header_content
     * @return mixed                    Array or object (deserialised JSON).
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    private function _put($route, $params = null, $header_content = '')
    {
        return $this->_request($route, "PUT", $params, $header_content);
    }

    /**
     * Alias for sending a GET request.
     *
     * @param string $route Route name mapped in self::$_routes.
     * @param array|null $params Request parameters.
     * @param string $header_content
     * @return mixed  Array or object (deserialised JSON).
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    private function _delete(string $route, array $params = null, string $header_content = '')
    {
        return $this->_request($route, "DELETE", $params, $header_content);
    }

    /**
     * Make an HTTP request.
     *
     * @param string $route Route name mapped in self::$_routes.
     * @param string $method The HTTP method to send (GET, POST, PUT, DELETE).
     * @param array|null $params Request parameters.
     * @param string $header_content Header content
     * @return mixed Array or object (deserialised JSON).
     * @throws DataException
     * @throws GeneralException
     * @throws InputException
     * @throws NetworkException
     * @throws OrderException
     * @throws PermissionException
     * @throws TokenException
     */
    private function _request(string $route, string $method, ?array $params, string $header_content)
    {
        $uri = $this->_routes[$route];

        // 'RESTful' URLs.
        if (strpos($uri, "{") !== false) {
            foreach ($params as $key => $value) {
                $uri = str_replace("{" . $key . "}", $value, $uri);
            }
        }

        $url = $this->_root . $uri;

        if ($this->debug) {
            print("Request: " . $method . " " . $url . "\n");
            var_dump($params);
        }

        // Set the header content type, if not sent set it to default
        if ($header_content) {
            $content_type = $header_content;
        } else {
            // default header content type of urlencoded
            $content_type = "Content-type: application/x-www-form-urlencoded";
        }
        // Prepare the payload
        $request_headers = [
            $content_type,
            "User-Agent: phpkiteconnect/" . self::_version,
            "X-Kite-Version: 3",
        ];

        if ($this->api_key && $this->access_token) {
            $request_headers[] = "Authorization: token " . $this->api_key . ":" . $this->access_token;
        }
        // Make the HTTP request.
        if (function_exists("curl_init")) {
            $resp = $this->_curl($url, $method, $request_headers, $params);
        } else {
            trigger_error("The php curl module is not installed. Please isntall it for better performance.", E_USER_WARNING);
            $resp = $this->_http_socket($url, $method, $request_headers, $params);
        }
        $headers = $resp["headers"];
        $result = $resp["body"];

        if ($this->debug) {
            print("Response :" . $result . "\n");
        }
        if (empty($headers["content-type"])) {
            throw new DataException("Unknown content-type in response");
        } elseif (strpos($headers["content-type"], "application/json") !== false) {
            $json = json_decode($result);
            if (! $json) {
                throw new DataException("Couldn't parse JSON response");
            }

            // Token error.
            if ($json->status == "error") {
                if ($headers["status_code"] == 403) {
                    if ($this->session_hook) {
                        $this->session_hook->call($this);

                        return null;
                    }
                }
                $this->_throwSuitableException($headers, $json);
            }

            return $json->data;
        } elseif (strpos($headers["content-type"], "text/csv") !== false) {
            return $result;
        } else {
            throw new DataException("Invalid response: " . $result, $headers["status_code"]);
        }
    }

    /**
     * Make an HTTP request using the PHP socket functions.
     *
     * @param string $url The full URL to retrieve
     * @param string $method The HTTP method to send (GET, POST, PUT, DELETE).
     * @param array|null $headers Array of HTTP request headers to send.
     * @param array|null $params Array of key=>value request parameters.
     * @return array                    Returns an array with response "headers" and "body".
     * @throws NetworkException
     */
    private function _http_socket(string $url, string $method, ?array $headers, ?array $params = null): array
    {
        // Prepare the payload.
        $payload = http_build_query($params ? $params : []);
        $payload = preg_replace("/%5B(\d+?)%5D/", "", $payload);

        $request_headers = "";
        if ($headers && count($headers) > 0) {
            $request_headers = implode("\r\n", $headers);
        }

        $options = [
            "method" => $method,
            "content" => $payload,
            "ignore_errors" => true,
            "header" => $request_headers,
        ];

        if ($method == 'GET' || $method == 'DELETE') {
            $url .= "?" . $payload;
        }

        $context = stream_context_create(["http" => $options]);
        $result = @file_get_contents($url, false, $context);

        // Request failed due to a network error.
        if (! $result) {
            $error = error_get_last();
            /** @var string $error_message */
            $error_message = $error["message"];

            throw new NetworkException($error_message);
        }

        $response_headers = $this->_parseHeaders($http_response_header);

        // Content is gzipped. Uncompress.
        if (isset($response_headers["content-encoding"]) && stristr($response_headers["content-encoding"], "gzip")) {
            $result = gzdecode($result);
        }

        return ["headers" => $response_headers, "body" => $result];
    }

    /**
     * Make an HTTP request using the PHP curl library.
     *
     * @param string $url The full URL to retrieve
     * @param string $method The HTTP method to send (GET, POST, PUT, DELETE).
     * @param array|null $headers Array of HTTP request headers to send.
     * @param array|null $params Array of key=>value request parameters.
     * @return array                    Returns an array with response "headers" and "body".
     * @throws NetworkException
     */
    private function _curl(string $url, string $method, ?array $headers, $params = null): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($headers && count($headers) > 0) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // Prepare the payload.
        $payload = http_build_query($params && is_array($params) ? $params : []);
        if ($payload) {
            $payload = preg_replace("/%5B(\d+?)%5D/", "", $payload);
        } elseif (json_decode((string)$params)) {
            // send json param payload
            $payload = $params;
        }

        if ($method == "POST" || $method == "PUT") {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        } elseif ($method == "GET" || $method == "DELETE") {
            $url .= "?" . $payload;
        }

        // Request URL.
        curl_setopt($ch, CURLOPT_URL, $url);

        // Routine to collect the response headers.
        $response_headers = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($_curl, string $header) use (&$response_headers) {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) < 2) {
                return $len;
            }

            $name = strtolower(trim($header[0]));
            if (! array_key_exists($name, $response_headers)) {
                $response_headers[$name] = trim($header[1]);
            } else {
                $response_headers[$name] = trim($header[1]);
            }

            return $len;
        });

        $result = curl_exec($ch);

        // Request error.
        if ($error = curl_error($ch)) {
            throw new NetworkException($error);
        }

        $response_headers["status_code"] = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));

        return ["headers" => $response_headers, "body" => $result];
    }

    /**
     * Parse a CSV dump into an array of objects.
     *
     * @param string $csv Complete CSV dump.
     * @return array
     * @throws Exception
     */
    private function _parseInstrumentsCSV(string $csv): array
    {
        $lines = explode("\n", $csv);

        $records = [];
        $head = [];
        for ($count = 0; $count < count($lines); $count++) {
            $colums = str_getcsv($lines[$count]);
            if ($colums) {
                if (count($colums) < 5) {
                    //why this condition is necessary ?
                    continue;
                }

                // First line is the header.
                if ($count === 0) {
                    $head = $colums;
                    continue;
                }

                // Combine header columns + values to an associative array
                // and then to an object;
                $record = (object)array_combine($head, $colums);
                $record->last_price = floatval($record->last_price);
                $record->strike = floatval($record->strike);
                $record->tick_size = floatval($record->tick_size);
                $record->lot_size = floatval($record->lot_size);

                if (! empty($record->expiry) && strlen($record->expiry) == 10) {
                    $record->expiry = new DateTime($record->expiry, new DateTimeZone("Asia/Kolkata"));
                }

                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * Parse a CSV dump into an array of objects.
     *
     * @param string $csv Complete CSV dump.
     * @return array
     * @throws Exception
     */
    private function _parseMFInstrumentsCSV(string $csv): array
    {
        $lines = explode("\n", $csv);

        $records = [];
        $head = [];
        for ($n = 0; $n < count($lines); $n++) {
            if ($cols = @str_getcsv($lines[$n])) {
                if (count($cols) < 5) {
                    continue;
                }

                // First line is the header.
                if ($n === 0) {
                    $head = $cols;

                    continue;
                }

                // Combine header columns + values to an associative array
                // and then to an object;
                $o = (object)array_combine($head, $cols);
                $o->minimum_purchase_amount = floatval($o->minimum_purchase_amount);
                $o->purchase_amount_multiplier = floatval($o->purchase_amount_multiplier);
                $o->minimum_additional_purchase_amount = floatval($o->minimum_additional_purchase_amount);
                $o->minimum_redemption_quantity = floatval($o->minimum_redemption_quantity);
                $o->redemption_quantity_multiplier = floatval($o->redemption_quantity_multiplier);
                $o->last_price = floatval($o->last_price);
                $o->purchase_allowed = boolval(intval($o->purchase_allowed));
                $o->redemption_allowed = boolval(intval($o->redemption_allowed));

                if (! empty($o->last_price_date) && strlen($o->last_price_date) == 10) {
                    $o->last_price_date = new DateTime($o->last_price_date, new DateTimeZone("Asia/Kolkata"));
                }

                $records[] = $o;
            }
        }

        return $records;
    }

    /**
     * Parse HTTP response headers to a list.
     *
     * @param string $headers Header string from an HTTP request.
     * @return array
     */
    private function _parseHeaders(string $headers): array
    {
        $head = ["status_code" => 200];
        /** @var array $headers */
        foreach ($headers as $header) {
            $h = explode(":", $header, 2);

            if (isset($h[1])) {
                $head[strtolower(trim($h[0]))] = trim($h[1]);
            } else {
                $head[] = $header;
                if (preg_match("/HTTP\/[0-9.]+\s+([0-9]+)/is", $header, $out)) {
                    $head["status_code"] = intval($out[1]);
                }
            }
        }

        return $head;
    }

    /**
     * Throw Exception based on response
     *
     * @param array $headers
     * @param mixed $json
     * @return void
     * @throws DataException
     * @throws GeneralException
     * @throws OrderException
     * @throws PermissionException
     * @throws NetworkException
     * @throws InputException
     * @throws TokenException
     */
    private function _throwSuitableException(array $headers, $json): void
    {
        switch ($json->error_type) {
            case 'DataException':
                throw new DataException($json->message, $headers['status_code']);
            case 'InputException':
                throw new InputException($json->message, $headers['status_code']);
            case 'NetworkException':
                throw new NetworkException($json->message, $headers['status_code']);
            case 'OrderException':
                throw new OrderException($json->message, $headers['status_code']);
            case 'PermissionException':
                throw new PermissionException($json->message, $headers['status_code']);
            case 'TokenException':
                throw new TokenException($json->message, $headers['status_code']);
            default:
                throw new GeneralException($json->message, $headers['status_code']);
        }
    }
}
