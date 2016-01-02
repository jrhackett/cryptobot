<?php
	//Used as part of a bot to facilitate automatic crypto currency trading on Poloniex.com
	//written by Jacob Hackett

	require 'poloniex_wrapper.php';

	//returns array holding best asking price, the quantity for that price, best bid price, and best quantity for that price
	function find_best_prices($pair, $key, $secret) {
		$object = new poloniex($key, $secret);
		$orders = $object->get_order_book($pair);
		
		//bid is buy order
		//ask is a sell order

		$best_bid = 0.0;
		$best_bid_quantity = 0;
		$best_ask = 10000000.0;
		$best_ask_quantity = 0;
		$check = false;

		//TODO handle the isFrozen variable to assure that we are not trading with frozen markets
		foreach($orders as $one => $two) {
			//$one denotes asks vs bids
			foreach($two as $three => $four) {
				foreach($four as $five => $six) {
					//$five denotes price(0) or quantity(1), $six is value
					//beware that index 0 does not have anything meaningful
					if($one === 'asks') {
						//if we just found the best price, update the quantity
						if($check === true && $five === 1) {
							$best_ask_quantity = $six;
							$check = false;
						}
						//if this is the best price, update it
						if($five === 0 && $six < $best_ask) {	
							$best_ask = $six;
							$check = true;
						}
					}
					else if ($one === 'bids') {
						//if we just found the best price, update the quantity
						if($check === true && $five === 1) {
							$best_bid_quantity = $six;
							$check = false;
						}
						//if this is the best price, update it
						if($five === 0 && $six > $best_bid) {
							$best_bid = $six;
							$check = true;
						}
					}
				}
			}
		}

		return array($best_ask, $best_ask_quantity, $best_bid, $best_bid_quantity);
	}

	//calculates the amount of the each trade to make
	function find_max_trades($first_pair_array, $second_pair_array, $third_pair_array, $direction, $volume) {
		//first to second to third to first
		if($direction === 0) {
			//the most we can buy of the first currency is either our volume limit or the limit of the ask order -- whichever is less
			$max_one = ($first_pair_array[1] < ($volume / $first_pair_array[0])) ? $first_pair_array[1] : ($volume / $first_pair_array[0]);	

			//checking if max_one is bounded by the amount we can buy in the second and third
			if($max_one * $second_pair_array[2] > $second_pair_array[3]) {
				$max_one = $second_pair_array[3] / $second_pair_array[2];
			}

			//the most we can sell of the second currency is either the limit by how much we bought of the first or by the bid order -- whichever is less
			$max_two = (($max_one * $second_pair_array[2]) < $second_pair_array[3]) ? ($max_one * $second_pair_array[2]) : $second_pair_array[3];

			//checking if max_two is bounded by the amount we can buy in the third
			if($max_two * $third_pair_array[2] > $third_pair_array[3]) {
				$max_two = $third_pair_array[3] / $third_pair_array[2];
				$max_one = $max_two / $second_pair_array[2];				//TODO verify this line works (hard to find real data for it)
			}

			//the most we can sell of the third currency is either the limit by how much we have of the second or by the bid order -- whichever is elss
			$max_three = (($max_two * $third_pair_array[2]) < $third_pair_array[3]) ? ($max_two * $third_pair_array[2]) : $third_pair_array[3];

			//some variables to calculate profit
			$starting = $max_one * $first_pair_array[0];
			$profit = ($max_three - $starting) / $starting;

			echo "<p>Profit: $profit from $starting BTC</p>";

			return array($max_one, $max_two, $max_three, $profit, $starting);
		}
		//first to third to second to first
		else {
			$max_one = ($third_pair_array[1] < ($volume * $third_pair_array[0])) ? $third_pair_array[1] : ($volume * $third_pair_array[0]);

			//checking if max_one is bounded by the amount we can buy in the second and third
			if($max_one * $second_pair_array[0] > $second_pair_array[1]) {
				$max_one = $second_pair_array[1] * $second_pair_array[0];
			}

			//the most we can sell of the second currency is either the limit by how much we bought of the first or by the bid order -- whichever is less
			$max_two = (($second_pair_array[0] * $max_one) < $second_pair_array[1]) ? ($max_one * $second_pair_array[0]) : $second_pair_array[1];

			//checking if max_two is bounded by the amount we can buy in the third
			if($max_two * $first_pair_array[2] > $first_pair_array[3]) {
				$max_two = $first_pair_array[3] / $first_pair_array[2];
				$max_one = $max_two / $second_pair_array[2];				//TODO verify this line works (hard to find real data for it)
			}

			//the most we can sell of the third currency is either the limit by how much we have of the second or by the bid order -- whichever is elss
			$max_three = (($max_two * $first_pair_array[2]) < $first_pair_array[3]) ? ($max_two * $first_pair_array[2]) : $first_pair_array[3];

			//some variables to calculate profit
			$starting = $max_one * $third_pair_array[0];
			$profit = ($max_three - $starting) / $starting;

			echo "<p>Profit: $profit from $starting BTC</p>";

			return array($max_one, $max_two, $max_three, $profit, $starting);
		}
	}

	//returns an array containing either a 1 or 0 in the first 2 indices to indicate which direction to trade in
	//this array will also contain info about how many trades to make
	function search_arbitrage($first_pair, $second_pair, $third_pair, $key, $secret) {
		$first_pair_array = find_best_prices($first_pair, $key, $secret);
		$second_pair_array = find_best_prices($second_pair, $key, $secret);
		$third_pair_array = find_best_prices($third_pair, $key, $secret);

		$first_pair_sub = substr($first_pair, 4);
		$second_pair_sub = substr($second_pair, 4);
		$third_pair_sub = substr($third_pair, 4);

		$d = $third_pair_array[2];			//third pair's best bid price
		$e = 1 / $second_pair_array[0];		//second pair's best ask price
		$f = $first_pair_array[2];			//first pair's best bid price

		if($d === $e * $f) {
			echo '<p>No arbitrage here</p>';
		}
		else {
			echo "<p>Testing arbitrage from BTC to $third_pair_sub to $second_pair_sub to BTC</p>";

			$first = 1 / $third_pair_array[2];
			$second = $first / $second_pair_array[2];
			$third = $second * $first_pair_array[0];

			//echos for testing purposes
			// echo "<p>1 BTC = $first $third_pair_sub ($third_pair_array[2])</p>";
			// echo "<p>= $second $second_pair_sub ($second_pair_array[2])</p>";
			// echo "<p>= $third BTC ($first_pair_array[0])</p>";

			//percent gain
			$first_gain = ($third - 1) * 100;
			$first_rounded_gain = number_format((float)$first_gain, 2, '.', '');
			echo "<p>Ideal percent gain: $first_rounded_gain";

			echo "<p>Testing arbitrage from BTC to $second_pair_sub to $third_pair_sub to BTC</p>";

			$first = 1 / $first_pair_array[2];
			$second = $first / $second_pair_array[0];
			$third = $second * $third_pair_array[0];

			//echos for testing purposes
			// echo "<p>1 BTC = $first $second_currency_sub ($first_pair_array[2])</p>";
			// echo "<p>= $second $third_currency_sub ($second_pair_array[0])</p>";
			// echo "<p>= $third BTC ($third_pair_array[0])</p>";

			$max_trades_array = find_max_trades($first_pair_array, $second_pair_array, $third_pair_array, 0, 100000);
			$max_trades_array2 = find_max_trades($first_pair_array, $second_pair_array, $third_pair_array, 1, 100000);

			//percent gain
			$second_gain = ($third - 1) * 100;
			$second_rounded_gain = number_format((float)$second_gain, 2, '.', '');
			echo "<p>Ideal percent gain: $second_rounded_gain";

			echo "<p><br>Trade $max_trade_array[4] BTC for $max_trades_array[0] $first_pair_sub. Sell $first_pair_sub for $max_trades_array[1] $third_pair_sub. Sell $second_pair_sub for $max_trades_array[2] BTC. Profit = $max_trades_array[3]%</p>";
			echo "<p><br>Trade $max_trade_array2[4] BTC for $max_trades_array2[0] $third_pair_sub. Buy $max_trades_array2[1] $first_pair_sub with $third_pair_sub. Sell $first_pair_sub for $max_trades_array2[2] BTC. Profit = $max_trades_array2[3]%</p>";
			
			//echos for prices for testing purposes
			echo "<p>$first_pair $first_pair_array[0] $first_pair_array[1] $first_pair_array[2] $first_pair_array[3] </p>";
			echo "<p>$second_pair $second_pair_array[0] $second_pair_array[1] $second_pair_array[2] $second_pair_array[3] </p>";
			echo "<p>$third_pair $third_pair_array[0] $third_pair_array[1] $third_pair_array[2] $third_pair_array[3] </p>";

			//TODO add better returns to be handled in handle_arbitrage
		}
	}

	function handle_arbitrage() {
		$array = search_arbitrage('BTC_DASH', 'XMR_DASH', 'BTC_XMR', 'EGNLC8SU-OXMKD4MV-3YGWKWH7-39AXHKCL', 'a49c400a00269220e895bfba6a48eb57bb8a1398ca80022969d91a27e480de0316d47aa8aac2148a02cf0dc14314142aa1701ed0dbf85692e85417a45be18ad1');
		echo '<hr>';
		$array2 = search_arbitrage('BTC_LTC', 'XMR_LTC', 'BTC_XMR', 'EGNLC8SU-OXMKD4MV-3YGWKWH7-39AXHKCL', 'a49c400a00269220e895bfba6a48eb57bb8a1398ca80022969d91a27e480de0316d47aa8aac2148a02cf0dc14314142aa1701ed0dbf85692e85417a45be18ad1');
		echo '<hr>';
		$array3 = search_arbitrage('BTC_BLK', 'XMR_BLK', 'BTC_XMR', 'EGNLC8SU-OXMKD4MV-3YGWKWH7-39AXHKCL', 'a49c400a00269220e895bfba6a48eb57bb8a1398ca80022969d91a27e480de0316d47aa8aac2148a02cf0dc14314142aa1701ed0dbf85692e85417a45be18ad1');
		echo '<hr>';
		$array4 = search_arbitrage('BTC_BBR', 'XMR_BBR', 'BTC_XMR', 'EGNLC8SU-OXMKD4MV-3YGWKWH7-39AXHKCL', 'a49c400a00269220e895bfba6a48eb57bb8a1398ca80022969d91a27e480de0316d47aa8aac2148a02cf0dc14314142aa1701ed0dbf85692e85417a45be18ad1');
		echo '<hr>';
		$array5 = search_arbitrage('BTC_DIEM', 'XMR_DIEM', 'BTC_XMR', 'EGNLC8SU-OXMKD4MV-3YGWKWH7-39AXHKCL', 'a49c400a00269220e895bfba6a48eb57bb8a1398ca80022969d91a27e480de0316d47aa8aac2148a02cf0dc14314142aa1701ed0dbf85692e85417a45be18ad1');
		echo '<hr>';
		$array6 = search_arbitrage('BTC_QORA', 'XMR_QORA', 'BTC_XMR', 'EGNLC8SU-OXMKD4MV-3YGWKWH7-39AXHKCL', 'a49c400a00269220e895bfba6a48eb57bb8a1398ca80022969d91a27e480de0316d47aa8aac2148a02cf0dc14314142aa1701ed0dbf85692e85417a45be18ad1');
		echo '<hr>';
		$array7 = search_arbitrage('BTC_XDN', 'XMR_XDN', 'BTC_XMR', 'EGNLC8SU-OXMKD4MV-3YGWKWH7-39AXHKCL', 'a49c400a00269220e895bfba6a48eb57bb8a1398ca80022969d91a27e480de0316d47aa8aac2148a02cf0dc14314142aa1701ed0dbf85692e85417a45be18ad1');

		//make trades below
		/*if($array[0] === 1 && $array[1] === 0) {
			echo "Make $array[2] trades from BTC to XMR to DASH to BTC";
		}
		else if($array[1] === 1 && $array[0] === 0) {
			echo "Make $array[2] trades from BTC to DASH to XMR to BTC";
		}
		else {
			echo '<p>There are no profitable trades between these currencies at the moment</p>';
		}*/
	}

	handle_arbitrage();
?>