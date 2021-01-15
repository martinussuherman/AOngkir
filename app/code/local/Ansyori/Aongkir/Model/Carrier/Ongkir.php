<?php

/**
 * Indonesia Shipping Carriers
 * @copyright   Copyright (c) 2015 Ansyori B.
 * @email		ansyori@gmail.com / ansyori@kemanaservices.com
 * @build_date  March 2015   
 * @license     http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
class Ansyori_Aongkir_Model_Carrier_Ongkir
extends Mage_Shipping_Model_Carrier_Abstract
implements Mage_Shipping_Model_Carrier_Interface
{
	protected $_code = 'ongkir';

	public function collectRates(Mage_Shipping_Model_Rate_Request $request)
	{
		if (!$this->getConfigFlag('active')) {
			return false;
		}

		$rate_list = $this->getListRates($request);
		$result = Mage::getModel('shipping/rate_result');
		$count = 0;

		foreach ($rate_list as $rate) {
			$count++;
			//getMethodRates($code,$title,$name,$rates)
			$result->append($this->getMethodRates('_' . $count, '', $rate['text'], $rate['cost']));
		}

		return $result;
	}

	public function helper($type = 'aongkir')
	{
		return Mage::helper($type);
	}

	public function getCityId()
	{
		$string_city = Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress()->getCity();
		$sql = "select * from " . Mage::getConfig()->getTablePrefix() .
			"daftar_alamat where concat(type,' ',city_name) = '$string_city' limit 0,1 ";
		$res =  $this->helper()->fetchSql($sql);

		return $res[0]['city_id'];
	}

	public function getListRates(Mage_Shipping_Model_Rate_Request $request)
	{
		$weight = $this->getBeratTotal($request);

		$rates_by_kurir = $this->helper()->getSavedRates(
			$this->getOriginId(), 
			$this->getCityId(), 
			$weight, 
			$this->getActiveCarriersListPostData($this->getActiveCarriers()));
		$rate_list = array();

		foreach ($rates_by_kurir as $final_list) {
			$rate_list[] = array(
				'text' => $final_list['text'] . "($weight Kg)", 
				'cost' => $this->changePrice($final_list['cost']) * $weight);
		}

		$this->helper()->setLog('Final rate ' . print_r($rate_list, true));
		return $rate_list;
	}

	private function getActiveCarriers()
	{
		return explode(',', strtolower($this->helper()->config('kurir')));
	}
	private function getActiveCarriersListPostData(array $activeCarriersList)
	{
		return implode(":", $activeCarriersList);
	}

	public function changePrice($price)
	{
		$set = $this->helper()->config('changeprice');

		if (!$set) {
			return $price;
		}

		/*if (strpos($a,'are') !== false) {
				echo 'true';
			}*/

		$found_persen = false;

		if (strpos($set, '%') !== false) {
			//echo 'true';
			$found_persen = true;
			$set = str_replace('%', '', $set);
		}

		$found_minus = false;

		if (strpos($set, '-') !== false) {
			//echo 'true';
			$found_minus = true;
			$set = str_replace('-', '', $set);
		}

		$found_plus = false;

		if (strpos($set, '+') !== false) {
			//echo 'true';
			$found_plus = true;
			$set = str_replace('+', '', $set);
		}

		$final_set = $set;
		$changed_price = 0;

		if ($found_persen) {
			$changed_price = ($price * $set) / 100;
		} else {
			$changed_price = abs($set);
		}

		if ($found_minus) {
			return $price - $changed_price;
		}

		if ($found_plus) {
			return $price + $changed_price;
		}

		//$final_price
		return $price;
	}

	public function getOriginId()
	{
		return $this->helper()->config('origin');
	}

	public function getDisabledServices()
	{
		return $this->helper()->config('disablesvr');
	}

	private function getBeratTotal(Mage_Shipping_Model_Rate_Request $request)
	{
		$totalWeight = $request->getPackageWeight();

		if ($totalWeight < 1) {
			$totalWeight = 1;
		}

		return $totalWeight;
	}

	public function getMethodRates($code, $title, $name, $rates)
	{
		$method = Mage::getModel('shipping/rate_result_method');
		$method->setCarrier($this->_code);
		$method->setCarrierTitle($title);
		$method->setMethod($this->_code . $code);
		$method->setMethodTitle($name);
		$method->setPrice($rates);

		return $method;
	}

	/**
	 * Get allowed shipping methods
	 *
	 * @return array
	 */
	public function getAllowedMethods()
	{
		return array($this->_code => $this->getConfigData('name'));
	}

	public function isTrackingAvailable()
	{
		return true;
	}
}
