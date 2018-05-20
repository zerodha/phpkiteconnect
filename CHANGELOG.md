New features
=============
- method: `getProfile`
- method: `getOHLC`
- method: `getLTP`
- method: `getMFOrders`
- method: `getMFHoldings`
- method: `placeMFOrder`
- method: `cancelMFOrder`
- method: `getMFSIPS`
- method: `placeMFSIP`
- method: `modifyMFSIP`
- method: `cancelMFSIP`
- method: `getMFInstruments`
- method: `exitOrder`
- method: `renewAccessToken`
- method: `invalidateRefreshToken`

API method name changes
=======================

| v2  						| v3 						|
| -------------------------	| -------------------------	|
| requestAccessToken		| generateSession			|
| invalidateToken			| invalidateAccessToken		|
| setSessionHook 			| setSessionExpiryHook		|
| loginUrl					| getLoginURL				|
| margins					| getMargins				|
| orderPlace				| placeOrder				|
| orderModify				| modifyOrder				|
| orderCancel 				| cancelOrder				|
| orders 					| getOrders 				|
| orders(order_id) 			| getOrderHistory			|
| trades 					| getTrades 				|
| trades(order_id) 			| getOrderTrades 			|
| holdings					| getHoldings 				|
| positions					| getPositions 				|
| productModify 			| convertPosition 			|
| instruments				| getInstruments 			|
| historical				| getHistoricalData 		|
| triggerRange 				| getTriggerRange 			|

Params and other changes
========================
- `convertPosition` method takes all the params as array
- `getHistoricalData` method takes all the params as array
- [Changes in `generateSession` response structure](https://kite.trade/docs/connect/v3/user/#response-attributes)
- [Changes in `getPositions` response structure](https://kite.trade/docs/connect/v3/portfolio/#response-attributes_1)
- [Changes in `getQuote` response structure](https://kite.trade/docs/connect/v3/market-quotes/#retrieving-full-market-quotes)
- [Changes in `placeOrder` params](https://kite.trade/docs/connect/v3/orders/#bracket-order-bo-parameters)
- Changes in `getHistoricalData` params
- All datetime string fields has been converted to `Date` object.
	- `getOrders`, `getOrderHistory`, `getTrades`, `getOrderTrades`, `getMFOrders` responses fields `order_timestamp`, `exchange_timestamp`, `fill_timestamp`
	- `getMFSIPS` fields `created`, `last_instalment`
	- `generateSession` field `login_time`
	- `getQuote` fields `timestamp`, `last_trade_time`
	- `getInstruments` field `expiry`
	- `getMFInstruments` field `last_price_date`
