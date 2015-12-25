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

	function find_max_trades($first_pair_array, $second_pair_array, $third_pair_array, $one, $two, $three) {
			$max_trades = 10000000;

			if($third_pair_array[$three] < $max_trades) {
				$max_trades = $third_pair_array[$three];
			}
			if($second_pair_array[$two] < $max_trades) {
				$max_trades = $second_pair_array[$two];
			}
			if($tfirst_pair_array[$one] < $max_trades) {
				$max_trades = $first_pair_array[$one];
			}
			return $max_trades;
	}

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

			$max_trades = find_max_trades($first_pair_array, $second_pair_array, $third_pair_array, 1, 3, 3);

			//echos for testing purposes
			echo "<p>1 BTC = $first $third_pair_sub ($third_pair_array[2])</p>";
			echo "<p>= $second $second_pair_sub ($second_pair_array[2])</p>";
			echo "<p>= $third BTC ($first_pair_array[0])</p>";

			//percent gain
			$gain = ($third - 1) * 100;
			$rounded_gain = number_format((float)$gain, 2, '.', '');
			echo "<p>Percent gain: $rounded_gain";
			echo "<p>You can make up to $max_trades trades at these prices</p>";

			echo "<p>Testing arbitrage from BTC to $second_pair_sub to $third_pair_sub to BTC</p>";

			$first = 1 / $first_pair_array[2];
			$second = $first / $second_pair_array[0];
			$third = $second * $third_pair_array[0];

			$max_trades = find_max_trades($first_pair_array, $second_pair_array, $third_pair_array, 3, 1, 1);

			//echos for testing purposes
			echo "<p>1 BTC = $first $second_currency_sub ($first_pair_array[2])</p>";
			echo "<p>= $second $third_currency_sub ($second_pair_array[0])</p>";
			echo "<p>= $third BTC ($third_pair_array[0])</p>";

			//percent gain
			$gain = ($third - 1) * 100;
			$rounded_gain = number_format((float)$gain, 2, '.', '');
			echo "<p>Percent gain: $rounded_gain";
			echo "<p>You can make up to $max_trades trades at these prices</p>";
		}
	}

	search_arbitrage('BTC_DASH', 'XMR_DASH', 'BTC_XMR', 'EGNLC8SU-OXMKD4MV-3YGWKWH7-39AXHKCL', 'a49c400a00269220e895bfba6a48eb57bb8a1398ca80022969d91a27e480de0316d47aa8aac2148a02cf0dc14314142aa1701ed0dbf85692e85417a45be18ad1');
?>