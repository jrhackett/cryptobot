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
	//TODO fix this up... not working properly yet
	function find_max_trades($first_pair_array, $second_pair_array, $third_pair_array, $direction, $volume) {
		//first to second to third to first
		if($direction === 0) {
			$max_one = ($volume < ($first_pair_array[3] * $first_pair_array[2])) ? $volume : ($first_pair_array[3] * $first_pair_array[2]);
			$max_two = (($max_one * $second_pair_array[0]) < $second_pair_array[1]) ? ($max_one * $second_pair_array[0]) : $second_pair_array[1];
			$max_three = (($max_two * $third_pair_array[0]) < $third_pair_array[1]) ? ($max_two * $third_pair_array[0]) : $third_pair_array[1];

			echo "<p>$volume $max_one $max_two $max_three</p>";

			return array($max_one, $max_two, $max_three);
		}
		//first to third to second to first
		else {
			$max_one = ($volume < ($third_pair_array[3] / $third_pair_array[2])) ? $volume : ($third_pair_array[3] * $third_pair_array[2]);
			$max_two = (($max_one / $second_pair_array[2]) < $second_pair_array[3]) ? ($max_one * $second_pair_array[2]) : $second_pair_array[3];
			$max_three = (($max_two / $first_pair_array[0]) < $first_pair_array[1]) ? ($max_two * $first_pair_array[0]) : $first_pair_array[1];

			echo "<p>$volume $max_one $max_two $max_three</p>";

			return array($max_one, $max_two, $max_three);
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
			echo "<p>1 BTC = $first $third_pair_sub ($third_pair_array[2])</p>";
			echo "<p>= $second $second_pair_sub ($second_pair_array[2])</p>";
			echo "<p>= $third BTC ($first_pair_array[0])</p>";

			//percent gain
			$first_gain = ($third - 1) * 100;
			$first_rounded_gain = number_format((float)$first_gain, 2, '.', '');
			echo "<p>Percent gain: $first_rounded_gain";

			echo "<p>Testing arbitrage from BTC to $second_pair_sub to $third_pair_sub to BTC</p>";

			$first = 1 / $first_pair_array[2];
			$second = $first / $second_pair_array[0];
			$third = $second * $third_pair_array[0];

			//echos for testing purposes
			echo "<p>1 BTC = $first $second_currency_sub ($first_pair_array[2])</p>";
			echo "<p>= $second $third_currency_sub ($second_pair_array[0])</p>";
			echo "<p>= $third BTC ($third_pair_array[0])</p>";

			$max_trades_array = find_max_trades($first_pair_array, $second_pair_array, $third_pair_array, 0, 1);
			$max_trades_array2 = find_max_trades($first_pair_array, $second_pair_array, $third_pair_array, 1, 1);

			//percent gain
			$second_gain = ($third - 1) * 100;
			$second_rounded_gain = number_format((float)$second_gain, 2, '.', '');
			echo "<p>Percent gain: $second_rounded_gain";

			echo "<p><br>Trade BTC for $max_trades_array[0] DASH. Sell $max_trades_array[1] DASH for XMR. Sell $max_trades_array[2] XMR for BTC.</p>";
			echo "<p><br>Trade BTC for $max_trades_array2[0] XMR. Buy $max_trades_array2[1] DASH with XMR. Sell $max_trades_array2[2] DASH for BTC.</p>";
			
			echo "<p>$first_pair_array[0] $first_pair_array[1] $first_pair_array[2] $first_pair_array[3] </p>";
			echo "<p>$second_pair_array[0] $second_pair_array[1] $second_pair_array[2] $second_pair_array[3] </p>";
			echo "<p>$third_pair_array[0] $third_pair_array[1] $third_pair_array[2] $third_pair_array[3] </p>";

			if($first_gain > $second_gain && $first_gain > 0.3) {
				return array(1, 0, 'work in progress');
			}
			else if($second_gain > $first_gain && $second_gain > 0.3) {
				return array(0, 1, 'work in progress');
			}
			else {
				return array(0, 0, 0);
			}
		}
	}

	function handle_arbitrage() {
		$array = search_arbitrage('BTC_DASH', 'XMR_DASH', 'BTC_XMR', 'EGNLC8SU-OXMKD4MV-3YGWKWH7-39AXHKCL', 'a49c400a00269220e895bfba6a48eb57bb8a1398ca80022969d91a27e480de0316d47aa8aac2148a02cf0dc14314142aa1701ed0dbf85692e85417a45be18ad1');

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