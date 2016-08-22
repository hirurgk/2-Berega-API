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
	public $url = 'http://api.retailrocket.ru/api/1.0/Recomendation/RelatedItems';


    public function __construct( $siteId='s1', $productIds )
    {
        $this->getKey();

        $this->siteId       = $siteId;
        $this->productIds   = $productIds;
    }


    /**
     * получение массива id через Api
     * @return array
     */
    public function getArrayIdRetailrocket()
    {
    	$arRecommends = array();
    	foreach ($this->productIds as $productId) {

    		$urlCollect = $this->url .'/'. $this->key .'/'. $productId;
    		$arRecommends = array_merge(array_unique(json_decode(file_get_contents($urlCollect))), $arRecommends);
    	}

        $arGoodsIds = array();
        if( count($arRecommends)>0 ) {
            # Получим доступные для покупки товары
            $arGoods = \Slim\Lib\Catalog::getGoodsList('', array_unique($arRecommends));

            foreach ($arGoods as $k => $v)
                $arGoodsIds[] = $v['ID'];
        }

        return $arGoodsIds;
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