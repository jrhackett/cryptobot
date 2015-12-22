<?php
	function api_query($method, array $req = array()) {
	        // API settings
	        $key = 'a715756228b7afba20e239391a97cef50628c409'; // your API-key
	        $secret = 'e821c5bbc65414ace7b4818aead07de1e22f21ca2ffd37d156cc0b5589be34f15b3c0094bf9af915'; // your Secret-key
	 
	        $req['method'] = $method;
	        $mt = explode(' ', microtime());
	        $req['nonce'] = $mt[1];
	       
	        // generate the POST data string
	        $post_data = http_build_query($req, '', '&');

	        $sign = hash_hmac("sha512", $post_data, $secret);
	 
	        // generate the extra headers
	        $headers = array(
	                'Sign: '.$sign,
	                'Key: '.$key,
	        );
	 
	        // our curl handle (initialize if required)
	        static $ch = null;
	        if (is_null($ch)) {
	                $ch = curl_init();
	                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Cryptsy API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	        }
	        curl_setopt($ch, CURLOPT_URL, 'https://api.cryptsy.com/api');
	        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	 
	        // run the query
	        $res = curl_exec($ch);
	        if ($res === false) throw new Exception('Could not get reply: '.curl_error($ch));
	        $dec = json_decode($res, true);
	        if (!$dec) throw new Exception('Invalid data received, please make sure connection is working and requested API exists');
	        return $dec;
	}
 
 	//these are all the example api_queries we can perform and how to perform them

	//$result = api_query("getinfo");

	//$result = api_query("getmarkets");

	//$result = api_query("mytransactions");

	//$result = api_query("markettrades", array("marketid" => 26));

	//$result = api_query("marketorders", array("marketid" => 26));

	//$result = api_query("mytrades", array("marketid" => 26, "limit" => 1000));

	//$result = api_query("allmytrades");

	//$result = api_query("myorders", array("marketid" => 26));

	//$result = api_query("allmyorders");

	//$result = api_query("createorder", array("marketid" => 26, "ordertype" => "Sell", "quantity" => 1000, "price" => 0.00031000));

	//$result = api_query("cancelorder", array("orderid" => 139567));
	 
	//$result = api_query("calculatefees", array("ordertype" => 'Buy', 'quantity' => 1000, 'price' => '0.005'));

	//echo "<pre>".print_r($result, true)."</pre>";



	//returns an array holding the lowest sell price in the first index and the highest buy price in the second index
	function find_market_prices($marketid) {
		$result = api_query("marketorders", array("marketid" => $marketid));

		$lowestsellprice = 10000000.0;
		$highestbuyprice = 0.0;

		foreach($result as $x => $y) {
			foreach($y as $z => $a) {
				foreach($a as $b => $c) {
					foreach($c as $d => $e) {
						if($d === 'sellprice') {
							if($e < $lowestsellprice) {
								$lowestsellprice = $e;
							}
						}
						if($d === 'buyprice') {
							if($e > $highestbuyprice) {
								$highestbuyprice = $e;
							}
						}
					}
				}
			}
		}

		return array($lowestsellprice, $highestbuyprice);
	}

	//returns best buy price for given market
	function find_market_buy($marketid) {
		foreach(find_market_prices($marketid) as $x => $y) {
			if($x === 1) {
				return $y;
			}
		}
	}

	//returns best sell price for given market
	function find_market_sell($marketid) {
		foreach(find_market_prices($marketid) as $x => $y) {
			if($x === 0) {
				return $y;
			}
		}
	}

	//searches for arbitrage opportunities going from USD -> BTC -> DOGE -> USD
	function search_arbitrage($first_currency, $second_currency, $third_currency) {
		//This is the flow of money we are looking for, either clockwise or counterclockwise
		//		first currency
		//		/			\
		//	 second -------third

		//variables
		$markets = api_query("getmarkets");
		$current_market_id = 0;
		$first_buy = 0;
		$first_sell = 0;
		$second_buy = 0;
		$second_sell = 0;
		$third_buy = 0;
		$third_sell = 0;
		$first_currency_sub = substr($first_currency, 0, -4); 
		$second_currency_sub = substr($second_currency, 0, -4);
		$third_currency_sub = substr($third_currency, 0, -4);

		//grab prices for the labels we're looking at
		foreach($markets as $x => $y) {
			foreach($y as $z => $a) {
				foreach($a as $b => $c) {
					if($b === 'marketid') {
						$current_market_id = $c;
					}
					if($b === 'label') {
						switch($c) {
							case $first_currency:
								$first_buy = find_market_buy($current_market_id);
								$first_sell = find_market_sell($current_market_id);
								break;
							case $second_currency:
								$second_buy = find_market_buy($current_market_id);
								$second_sell = find_market_sell($current_market_id);
								break;
							case $third_currency:
								$third_buy = find_market_buy($current_market_id);
								$third_sell = find_market_sell($current_market_id);
								break;
						}
					}
				}
			}
		}

		//calculates if arbitrage exists
		$d = $third_buy;
		$e = 1 / $second_sell;
		$f = $first_buy;

		//if d = e * f, there is perfect balance and therefore no arbitrage opportunity
		if($d == $e * $f) {
			echo '<p>No arbitrage here</p>';
		}
		else {
			//test first direction
			echo "<p>BTC to $third_currency_sub to $second_currency_sub to BTC</p>";

			//a little math happens here to calculate the resulting BTC value after the exchanges
			$first = 1 / $third_buy;
			$second = $first / $second_buy;
			$third = $second * $first_sell;

			//echos for testing purposes
			echo "<p>1 BTC = $first $third_currency_sub ($third_buy)</p>";
			echo "<p>= $second $second_currency_sub ($second_buy)</p>";
			echo "<p>= $third BTC ($first_sell)</p>";

			//percent gain
			$gain = ($third - 1) * 100;
			$rounded_gain = number_format((float)$gain, 2, '.', '');
			echo "<p>Percent gain: $rounded_gain";

			//test other direction
			echo "<p>BTC to $second_currency_sub to $third_currency_sub to BTC</p>";

			//a little math happens here to calculate the resulting BTC value after the exchanges
			$first = 1 / $first_buy;
			$second = $first * $second_sell;
			$third = $second * $third_sell;

			//echos for testing purposes
			echo "<p>1 BTC = $first $second_currency_sub ($first_buy)</p>";
			echo "<p>= $second $third_currency_sub ($second_sell)</p>";
			echo "<p>= $third BTC ($third_sell)</p>";

			//percent gain
			$gain = ($third - 1) * 100;
			$rounded_gain = number_format((float)$gain, 2, '.', '');
		}

		echo "<p>Percent gain: $rounded_gain";

		//uncomment this and change the value to get feedback on whether or not to make the trade
		// if($gain > .1) {
		// 	echo '<p>Make the trade!</p>';
		// }
		// else {
		// 	echo '<p>Do not make the trade!</p>';
		// }
	}

	search_arbitrage('DOGE/BTC', 'DOGE/LTC', 'LTC/BTC');
	echo '<hr>';
	search_arbitrage('DASH/BTC', 'DASH/LTC', 'LTC/BTC');
	echo '<hr>';
	search_arbitrage('ETH/BTC', 'ETH/LTC', 'LTC/BTC');
	echo '<hr>';
	// search_arbitrage('NBT/BTC', 'NBT/LTC', 'LTC/BTC');
	// echo '<hr>';
	// search_arbitrage('XRP/BTC', 'XRP/LTC', 'LTC/BTC');
	// echo '<hr>';
	search_arbitrage('PPC/BTC', 'PPC/LTC', 'LTC/BTC');
	echo '<hr>';
	// search_arbitrage('ZRC/BTC', 'ZRC/LTC', 'LTC/BTC');
	// echo '<hr>';
	search_arbitrage('NXT/BTC', 'NXT/LTC', 'LTC/BTC');
	echo '<hr>';
	// search_arbitrage('XPY/BTC', 'XPY/LTC', 'LTC/BTC');
	// echo '<hr>';
?>