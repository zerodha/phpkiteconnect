<?php

/**
Kite Connect API client for PHP -- [kite.trade](https://kite.trade) | [Download from Github](https://github.com/rainmattertech/phpkiteconnect)

Rainmatter (c) 2016. Version 1.2

License
-------
Kite Connect PHP library is licensed under the MIT License.

The library
-----------
Kite Connect is a set of REST-like APIs that expose
many capabilities required to build a complete
investment and trading platform. Execute orders in
real time, manage user portfolio, stream live market
data (WebSockets), and more, with the simple HTTP API collection.

This module provides an easy to use abstraction over the HTTP APIs.
The HTTP calls have been converted to methods and their JSON responses
are returned as native PHP structures, for example, dicts, lists, bools etc.
See the **[Kite Connect API documentation](https://kite.trade/docs/connect/v1/)**
for the complete list of APIs, supported parameters and values, and response formats.

Getting started
---------------
<pre>
<?php
	include dirname(__FILE__)."/kiteconnect.php";

	// Initialise.
	$kite = new KiteConnect("your_api_key");

	// Assuming you have obtained the `request_token`
	// after the auth flow redirect by redirecting the
	// user to $kite->login_url()
	try {
		$user = $kite->requestAccessToken("request_token_obtained", "your_api_secret");

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
	print_r($kite->positions());

	// Place order.
	$order_id = $kite->orderPlace([
		"tradingsymbol" => "INFY",
		"exchange" => "NSE",
		"quantity" => 1,
		"transaction_type" => "BUY",
		"order_type" => "MARKET",
		"product" => "NRML"
	], "regular");

	echo "Order id is ".$order_id;
</pre>

A typical web application
-------------------------
In a typical web application where a new instance of
views, controllers etc. are created per incoming HTTP
request, you will need to initialise a new instance of
Kite client per request as well. This is because each
individual instance represents a single user that's
authenticated, unlike an **admin** API where you may
use one instance to manage many users.

Hence, in your web application, typically:

- You will initialise an instance of the Kite client
- Redirect the user to the `login_url()`
- At the redirect url endpoint, obtain the
`request_token` from the query parameters
- Initialise a new instance of Kite client,
use `request_access_token()` to obtain the `access_token`
along with authenticated user data
- Store this response in a session and use the
stored `access_token` and initialise instances
of Kite client for subsequent API calls.

Exceptions
----------
Kite Connect client saves you the hassle of detecting API errors
by looking at HTTP codes or JSON error responses. Instead,
it raises aptly named **[exceptions](exceptions.m.html)** that you can catch.
*/

class KiteConnect {
	// Default root API endpoint. It's possible to
	// override this by passing the `root` parameter during initialisation.
	private $_root = "https://api.kite.trade";
	private static $_login = "https://kite.trade/connect/login";

	// API route map.
	private $_routes = [
		"parameters" => "/parameters",
		"api.validate" => "/session/token",
		"api.invalidate" => "/session/token",
		"user.margins" => "/user/margins/{segment}",

		"orders" => "/orders",
		"trades" => "/trades",
		"orders.info" => "/orders/{order_id}",

		"orders.place" => "/orders/{variety}",
		"orders.modify" => "/orders/{variety}/{order_id}",
		"orders.cancel" => "/orders/{variety}/{order_id}",
		"orders.trades" => "/orders/{order_id}/trades",

		"portfolio.positions" => "/portfolio/positions",
		"portfolio.holdings" => "/portfolio/holdings",
		"portfolio.positions.modify" => "/portfolio/positions",

		"market.instruments.all" => "/instruments",
		"market.instruments" => "/instruments/{exchange}",
		"market.quote" => "/instruments/{exchange}/{tradingsymbol}",
		"market.trigger_range" => "/instruments/{exchange}/{tradingsymbol}/trigger_range"
	];


	// Instance variables.
	public $timeout = 7;
	public $api_key = null;
	public $access_token = null;
	public $debug = null;
	public $session_hook = null;
	public $micro_cache = true;


	/**
	 * Initialise a new Kite Connect client instance.
	 *
	 * @param string $api_key 		The Kite Connect API key issued to you.
	 * @param string $access_token 	The token obtained after the login flow in exchange for the `request_token`.
	 *								Pre-login, this will default to None,
	 *								but once you have obtained it, you should
	 *								persist it in a database or session to pass
	 *								to the Kite Connect class initialisation for subsequent requests
	 * @param string $root 			The Kite Connect API end point root. Unless you explicitly
	 *								want to send API requests to a non-default endpoint, this
	 *								should be left as null.
	 * @param int $debug 			If set to True, requests and responses will be `echo`ed.
	 * @param int $timeout 			The the time (seconds) for which the API client will wait for
	 *								a request to complete before it fails.
	 * @param bool $micro_cache		When set to True, will fetch the last cached
	 *								version of an API response if available. This saves time on
	 *								a roundtrip to the backend. Micro caches only live for several
	 *								seconds to prevent data from turning stale.
	 * @return void
	 */
	public function __construct($api_key, $access_token = null, $root = null, $debug = false, $timeout = 7, $micro_cache = true) {
		$this->api_key = $api_key;
		$this->access_token = $access_token;
		$this->debug = $debug;
		$this->session_hook = null;
		$this->timeout = $timeout;

		if($root) {
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
	 * @param function $method 	The callback function that should be
	 * 							called in case of a TokenError error.
	 * @return void
	 */
	public function setSessionHook($method) {
		$this->session_hook = $method;
	}

	/**
	 * Set the `access_token` received after a successful authentication.
	 * 
	 * @param string $access_token	The `access_token` received after a successful
	 * 								authentication token exchange.
	 * @return void
	 */
	public function setAccessToken($access_token) {
		$this->access_token = $access_token;
	}

	/**
	 * Get the remote login url to which a user should be redirected
	 * to initiate the login flow.
	 * 
	 * @return string Login url
	 */
	public function loginUrl() {
		return sprintf("%s?api_key=%s", self::$_login, $this->api_key);
	}

	/**
	 * Do the token exchange with the `request_token` obtained after the login flow,
	 * and retrieve the `access_token` required for all subsequent requests. The
	 * response contains not just the `access_token`, but metadata for
	 * the user who has authenticated.
	 * 
	 * @param string $request_token 	Token obtained from the GET paramers after a successful login redirect
	 * @param string $secret 			The API secret issued with the API key.
	 * @return array
	 */
	public function requestAccessToken($request_token, $secret) {
		$checksum = hash("sha256", $this->api_key.$request_token.$secret);

		$resp = $this->_post("api.validate", [
			"request_token" => $request_token,
			"checksum" => $checksum
		]);

		if(!empty($resp->access_token)) {
			$this->setAccessToken($resp->access_token);
		}

		return $resp;
	}

	/**
	 * Kill the session by invalidating the access token.
	 * 
	 * @param string|null $access_token Optional `access_token` to
	 * 									invalidate. Default is the
	 * 									active `access_token`.
	 * @return none
	 */
	public function invalidateToken($access_token = null) {
		$params = [];
		if($access_token) {
			$params = ["access_token" => $access_token];
		}

		return $this->_delete("api.invalidate", $params);
	}

	/**
	 * Get account balance and cash margin details for a particular segment.
	 * 
	 * @param string $segment		The trading segment (eg: equity or commodity)
	 * @return array
	 */
	public function margins($segment) {
		return $this->_get("user.margins", ["segment" => $segment]);
	}

	/**
	 * Place an order.
	 * 
	 * @param array $params			[Order parameters](https://kite.trade/docs/connect/v1/#placing-orders) (quantity, price etc.)
	 * @param string $variety		Order variety (regular, amo etc.).
	 * @return string
	 */
	public function orderPlace($params, $variety) {
		$params["variety"] = $variety;

		return $this->_post("orders.place", $params)->order_id;
	}

	/**
	 * Modify an open order.
	 * 
	 * @param type $order_id	ID of the open order to be modified.
	 * @param type $params 		Order parameters to be modified.
	 * @param type $variety 	Order variety of the order to be modified (regular, amo etc.).
	 * 
	 * @return void
	 */
	public function orderModify($order_id, $params, $variety) {
		$params["variety"] = $variety;

		$defaults = [
			"exchange" => null,
			"tradingsymbol" => null,
			"transaction_type" => null,
			"quantity" => null,
			"price" => null,
			"order_type" => null,
			"product" => null,
			"trigger_price" => 0,
			"validity" => "DAY",
			"disclosed_quantity" => 0
		];
		$params = array_merge($defaults, $params);

		if($variety == "BO") {
			return $this->_put("order_modify", [
				"order_id" => $order_id,
				"quantity" => $params["quantity"],
				"price" => $params["price"],
				"trigger_price" => $params["trigger_price"],
				"disclosed_quantity" => $params["disclosed_quantity"],
				"variety" => $variety
			])["order_id"];

		} else if($variety == "CO") {
			return $this->_put("order_modify", [
				"order_id" => $order_id,
				"trigger_price" => $params["trigger_price"],
				"variety" => $variety
			])["order_id"];

		} else {
			return $this->_put("orders.modify", $params)["order_id"];
		}
	}

	/**
	 * Cancel an open order.
	 * 
	 * @param type $order_id	ID of the open order to be cancelled.
	 * @param type $variety		Order variety of the order to be modified (regular, amo etc.).
	 * 
	 * @return void
	 */
	public function orderCancel($order_id, $variety) {
		return $this->_delete("orders.cancel",
				["order_id" => $order_id, "variety" => $variety])
				->order_id;
	}

	public function orders($order_id=null) {
		/* Get the collection of orders from the orderbook. */

		if($order_id) {
			return $this->_get("orders.info", ["order_id" => $order_id]);
		} else {
			return $this->_get("orders");
		}
	}

	/**
	 * Retreive the list of trades executed (all or ones under a particular order).
	 * 
	 * An order can be executed in tranches based on market conditions.
	 * These trades are individually recorded under an order.
	 * 
	 * @param type $order_id	ID of the order (optional) whose trades
	 * 							are to be retrieved. If no `order_id` is
	 * 							specified, all trades for the day are returned.
	 * @return array
	 */
	public function trades($order_id = null) {
		if($order_id) {
			return $this->_get("trades", ["order_id" => $order_id]);
		} else {
			return $this->_get("trades");
		}
	}

	/**
	 * Retrieve the list of positions
	 * 
	 * @return array
	 */
	public function positions() {
		return $this->_get("portfolio.positions");
	}

	/**
	 * Retrieve the list of equity holdings.
	 * 
	 * @return array
	 */
	public function holdings() {
		return $this->_get("portfolio.holdings");
	}

	/**
	 * Modify an open position's product type.
	 * @param type $params	[Parameters](https://kite.trade/docs/connect/v1/#position-conversion)
	 * 						describing the open position to be modified.
	 * @return bool
	 */
	public function productModify($params) {
		$defaults = [
			"exchange" => null,
			"tradingsymbol" => null,
			"transaction_type" => null,
			"position_type" => null,
			"quantity" => null,
			"old_product" => null,
			"new_product" => null
		];
		$params = array_merge($defaults, $params);

		return $this->_put("portfolio.positions.modify", $params);
	}

	/**
	 * Retrieve the list of market instruments available to trade.
	 * 
	 * Note that the results could be large, several hundred KBs in size,
	 * with tens of thousands of entries in the array. The actual response
	 * from the API is in the CSV format, but this function parses the CSV
	 * into an array of Objects where an individual object looks like:
	 * <pre>Class Object
	 *	(
	 *		[instrument_token] => 128031748
	 *		[exchange_token] => 500124
	 *		[tradingsymbol] => DRREDDY*
	 *		[name] => DR.REDDYS LABORATORIES
	 *		[last_price] => 0
	 *		[expiry] => 
	 *		[strike] => 0
	 *		[tick_size] => 0.05
	 *		[lot_size] => 1
	 *		[instrument_type] => EQ
	 *		[segment] => BSE
	 *		[exchange] => BSE
	 *	)
	 * </pre>
	 * 
	 * @param type|null $exchange	(Optional) Exchange.
	 * @return array
	 */
	public function instruments($exchange = null) {
		if($exchange) {
			$params = ["exchange" => $exchange];

			return $this->_get("market.instruments", $params);
		} else {
			return $this->_get("market.instruments.all");
		}
	}

	/**
	 * Retrieve market quote and depth (bids and offers) for an instrument.
	 * 
	 * @param type $exchange 
	 * @param type $tradingsymbol 
	 * @return type
	 */
	public function quote($exchange, $tradingsymbol) {
		return $this->_get("market.quote", ["exchange" => $exchange,
											"tradingsymbol" => $tradingsymbol]);
	}

	/**
	 * Retrieve the buy/sell trigger range for Cover Orders.
	 * @param type $exchange 
	 * @param type $tradingsymbol 
	 * @param type $transaction_type 
	 * @return type
	 */
	public function triggerRange($exchange, $tradingsymbol, $transaction_type) {
		return $this->_get("market.trigger_range",
							["exchange" => $exchange,
							"tradingsymbol" => $tradingsymbol,
							"transaction_type" => $transaction_type]);
	}

	/**
	 * Alias for sending a GET request.
	 * 
	 * @param type $route 			Route name mapped in self::$_routes.
	 * @param type|null $params		Request parameters.
	 * @return mixed				Array or object (deserialised JSON).
	 */
	private function _get($route, $params=null) {
		return $this->_request($route, "GET", $params);
	}

	/**
	 * Alias for sending a GET request.
	 * 
	 * @param type $route 			Route name mapped in self::$_routes.
	 * @param type|null $params		Request parameters.
	 * @return mixed				Array or object (deserialised JSON).
	 */
	private function _post($route, $params=null) {
		return $this->_request($route, "POST", $params);
	}

	/**
	 * Alias for sending a PUT request.
	 * 
	 * @param type $route 			Route name mapped in self::$_routes.
	 * @param type|null $params		Request parameters.
	 * @return mixed				Array or object (deserialised JSON).
	 */
	private function _put($route, $params=null) {
		return $this->_request($route, "PUT", $params);
	}

	/**
	 * Alias for sending a GET request.
	 * 
	 * @param type $route 			Route name mapped in self::$_routes.
	 * @param type|null $params		Request parameters.
	 * @return mixed				Array or object (deserialised JSON).
	 */
	private function _delete($route, $params=null) {
		return $this->_request($route, "DELETE", $params);
	}

	/**
	 * Make an HTTP request.
	 * 
	 * @param type $route 			Route name mapped in self::$_routes.
	 * @param type|null $params		Request parameters.
	 * @return mixed				Array or object (deserialised JSON).
	 */
	private function _request($route, $method, $params=null) {
		// Is there a token?
		if($this->access_token) {
			$params["access_token"] = $this->access_token;
		}

		// Override instance's API key if one is supplied in the params
		if(!isset($params["api_key"])) {
			$params["api_key"] = $this->api_key;
		}

		$uri = $this->_routes[$route];

		// 'RESTful' URLs.
		if(strpos($uri, "{") !== false) {
			foreach($params as $k=>$v) {
				$uri = str_replace("{" . $k . "}", $v, $uri);
			}
		}

		$url = $this->_root.$uri;

		if($this->debug) {
			print("Request: " . $url . "\n");
			var_dump($params);
		}

		// Prepare the payload.
		$options = [
			"method"  => $method,
			"content" => http_build_query($params),
			"ignore_errors" => true,

			"header" => "Accept-Language: en-US,en;q=0.8\r\n" .
						"Accept-Encoding: gzip, deflate\r\n" .
						"Accept-Charset:UTF-8,*;q=0.5\r\n" .
						"User-Agent: phpkiteconnect\r\n"
		];

		if($method != "GET") {
			$options["header"] .= "Content-type: application/x-www-form-urlencoded\r\n";
		}

		$context  = stream_context_create(["http" => $options]);
		$result = @file_get_contents($url, false, $context);

		// Request failed due to a network error.
		if(!$result) {
			$error = error_get_last();
			throw new ClientNetworkException($error["message"]);
		}

		if($this->debug) {
			print("Response :" . $result . "\n");
		}

		// Parse the request headers.
		$headers = $this->_parseHeaders($http_response_header);

		// Content is gzipped. Uncompress.
		if(isset($headers["Content-Encoding"]) && stristr($headers["Content-Encoding"], "gzip")) {
			$result = gzdecode($result);
		}

		if(empty($headers["Content-Type"])) {
			throw new DataException("Unknown Content-Type in response");
		} else if(strpos($headers["Content-Type"], "application/json") !== false) {
			$json = @json_decode($result);
			if(!$json) {
				throw new DataException("Couldn't parse JSON response");
			}

			// Token error.
			if($json->status == "error") {
				if($headers["status_code"] == 403) {
					if($this->session_hook) {
						$this->session_hook();
						return;				
					}
				}

				// Check if the exception class is defined.
				if(class_exists($json->error_type)) {
					throw new $json->error_type($json->message, $headers["status_code"]);
				} else {
					throw new GeneralException($json->message, $headers["status_code"]);
				}
			}

			return $json->data;
		} else if(strpos($headers["Content-Type"], "text/csv") !== false) {
			return $this->_parseCSV($result);
		} else {
			throw new DataException("Invalid response format");
		}
	}

	/**
	 * Parse a CSV dump into an array of objects.
	 * @param string $csv	Complete CSV dump.
	 * @return array
	 */
	private function _parseCSV($csv) {
		$lines = explode("\n", $csv);

		$records = [];
		$head = [];
		for($n=0; $n<count($lines); $n++) {
			if($cols = @str_getcsv($lines[$n])) {
				// First line is the header.
				if($n === 0) {
					$head = $cols;
					continue;
				}

				// Combine header columns + values to an associative array
				// and then to an object;
				$o = (object) array_combine($head, $cols);
				$o->last_price = floatval($o->last_price);
				$o->strike = floatval($o->strike);
				$o->tick_size = floatval($o->tick_size);
				$o->lot_size = floatval($o->lot_size);

				$records[] = $o;
			}
		}

		return $records;
	}

	/**
	 * Parse HTTP response headers to a list.
	 * @param string $headers	Header string from an HTTP request.
	 * @return array
	 */
	private function _parseHeaders($headers) {
		$head = ["status_code" => 200];

		foreach($headers as $k=>$v) {
			$h = explode(":", $v, 2);

			if(isset($h[1])) {
				$head[trim($h[0])] = trim( $h[1] );
			} else {
				$head[] = $v;
				if(preg_match("/HTTP\/[0-9\.]+\s+([0-9]+)/is", $v, $out)) {
					$head["status_code"] = intval($out[1]);
				}
			}
		}

		return $head;
	}
}

/**
 * Base exeception for client exceptions.
 */
class KiteException extends Exception {
	public function __construct($message, $code = 0, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}

	public function __toString() {
		return get_class($this) . " ({$this->code}) '{$this->message}'\n";
	}
}

/**
 * An unclassified, general error. Default code is 500.
 */
class GeneralException extends KiteException {
	public function __construct($message, $code = 500, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}

/**
 * Represents all token and authentication related errors. Default code is 403.
 */
class TokenException extends KiteException {
	public function __construct($message, $code = 403, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}

/**
 * An unclassified, general 500 error.
 */
class PermissionException extends KiteException {
	public function __construct($message, $code = 403, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}

/**
 * Exceptions pertaining to calls dealing with the logged in user's data.
 */
class UserException extends KiteException {
	public function __construct($message, $code = 500, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}

/**
 * Represents all order placement and manipulation errors.
 */
class OrderException extends KiteException {
	public function __construct($message, $code = 500, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}

/**
 * Represents user input errors such as missing and invalid	parameters.
 */
class InputException extends KiteException {
	public function __construct($message, $code = 500, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}

/**
 * Represents a bad response from the backend Order Management System (OMS).
 */
class DataException extends KiteException {
	public function __construct($message, $code = 502, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}

/**
 * Represents a network issue between Kite and the backend Order Management System (OMS).
 */
class NetworkException extends KiteException {
	public function __construct($message, $code = 503, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}

/**
 * Raised when Kite Client is unable to connect to the Kite Connect servers.
 */
class ClientNetworkException extends KiteException {
	public function __construct($message, $code = 504, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}

