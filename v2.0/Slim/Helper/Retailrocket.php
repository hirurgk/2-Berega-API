<?php
/**
 * Created by PhpStorm.
 * User: v.kravtsov
 * Date: 17.03.2015
 * Time: 15:01
 */
namespace Slim\Helper;

class Retailrocket
{
    private $siteId;
    private $productIds;
    private $key;

	//public $url = 'http://api.retailrocket.ru/api/1.0/Recomendation/CrossSellItemToItems';
	public $url = 'http://api.retailrocket.ru/api/1.0/Recomendation/RelatedItems/';

	public function __construct( $siteId = 's1', $productIds, $count, $full_array )
    {
        $this->getKey();
        $this->siteId       = $siteId;
        $this->productIds   = $productIds;
		$this->count        = $count;
		
		if(isset($full_array)){
		    $this->full_array   = $full_array;
	    }else{
		    $this->full_array   = false;
	    }
    }


    /**
     * получение массива id через Api
     * @return array
     */
    public function getArrayIdRetailrocket()
    {
	    $arResult = array(); //собираю массив для возврата
	    $arRes = array(); //вспомогательный массив

	    $arRes["URL"] = $this->url .'/'. $this->key .'/'. implode(",", $this->productIds); //формируем url
	    $arRes["ARRECOMMENDS"] = file_get_contents($arRes["URL"]); //получаем все рекомендованные товары
	    preg_match_all("/[\\d]+/", $arRes["ARRECOMMENDS"], $arRes["RETAILITEMS"]); //парсим строку в итоге в $arRes["RETAILITEMS"] = полученный массив IDS

	    if($this->count > 0){
		    $arRes["FULL_ARRAY_GOODS"] = \Slim\Lib\Catalog::getGoodsList('', array_slice($arRes["RETAILITEMS"][0], 0, $this->count), true); //получаю полный массив
	    }else{
		    $arRes["FULL_ARRAY_GOODS"] = \Slim\Lib\Catalog::getGoodsList('', $arRes["RETAILITEMS"][0], true);
	    }

	    if($this->full_array){
		    $arResult = $arRes["FULL_ARRAY_GOODS"];
	    }else{
		    foreach($arRes["FULL_ARRAY_GOODS"] as $k => $v){
			    $arResult[] = $v['ID'];
		    }
	    }

        return $arResult;
    }


    /**
     * получение ключа сервиса по SITE_ID
     * @return $this
    */
    public function getKey()
    {
        $this->key = \Helper_Site_RetailRocket::getCode( $this->siteId );

        return $this->key;
    }
}