<?
/**
 * Created by y.alekseev
 */

namespace Slim\Lib;

class Basket extends \Slim\Helper\Lib
{
    /**
     * Проверяет содержимое корзины и возвращает эти же продукты
     * с актуальными данными и итоговой суммой со скидками

     * @return array
     */
    public static function check()
    {
        \CModule::IncludeModule('iblock');
        \CModule::IncludeModule('catalog');

        $groupId	= self::getCatalogGroupId();                                # тип цены с учетом города
        $data       = json_decode(self::getApp()->request->getBody(), true);    # данные с заказом
        $siteId     = self::getSiteId();

        # в методе CheckBasketFull проверяется скидка пользователя, для этого авторизуемся если есть данные
        \Slim\Helper\Authentication::auth();

        # Соберем массив для добавления в корзину товаров и привязки к заказу
        $arCart = \Order::setItemsForBasket($data['PRODUCTS'], $groupId, $siteId);

        # Приведем к булевому значение день рождение
        $hb = ($data['BIRTHDAY']) ? true : false;

        $checkBasket = CheckBasketFull($arCart, $siteId, true, $hb, true, $data['COUPON']);
        
        //Проверяем, изменились ли товары
        $changed = false;
        if (count($data['PRODUCTS']) > count($checkBasket['PRODUCTS']))
            $changed = true;
        
        //Проверяем, изменились ли топпинги
        $changed_toppings = false;
        $count_toppings_start = 0;
        $count_toppings_end = 0;
		
        foreach ($data['PRODUCTS'] as $product) {
            $count_toppings_start += count($product['TOPPINGS']);
        }
		
        foreach ($checkBasket['PRODUCTS'] as $product) {
            foreach ($product['PROPS'] as $prop) {
                if ($prop['CODE'] != 'NABORLIST_PIZZA')
                    continue;
                
                $arToppings = explode(';' , $prop['VALUE']);
                $count_toppings_end += count($arToppings);
            }
        }
        if ($count_toppings_start > $count_toppings_end)
            $changed_toppings = true;
        
		if($data["ADD_SALES"]["PASS"]){
			$checkBasket["ADD_SALES_PASS"] = $data["ADD_SALES"]["PASS"];
		}
		
        return self::setItemsForApp( $checkBasket, $changed, $changed_toppings );
    }

    
    /**
     * Создание массива для ответа приложению
     * содержит только товары, топинги и итоговую стоимость с расчетом скидки
     *
     * @param $data
     * @param changed - изменились ли товары
     * @param changed_toppings - изменились ли топпинги
     *
     * @return array
     */
    public static function setItemsForApp( $data, $changed = false, $changed_toppings = false )
    {
		\CModule::IncludeModule('iblock');
		global $USER;
    	$arUser = \CUser::GetByID($USER->GetID())->Fetch();
		
        $productIDs = array();
        
        $arOrder = array();
        foreach( $data['PRODUCTS'] as $key=>$value)
        {
            # Соберём ID товаров
            $productIDs[] = (int) $value['PRODUCT_ID'];
            

            # Соус
            $sauce = '';
            foreach ($value['PROPS'] as $prop) {
                if ($prop['CODE'] != 'SOUS')
                    continue;
                
                $sauce = $prop['VALUE'];
                break;
            }
            
            # соберем Топинги
            $toppings = array();
            foreach ($value['PROPS'] as $prop) {
                if ($prop['CODE'] != 'NABORLIST_PIZZA')
                    continue;
                
                $arToppings = explode(';' , $prop['VALUE']);
                foreach ($arToppings as $keyProp => $valueProp)
                {
                    $t = explode(',', $valueProp);

                    if( $t[0] != '' )
                        $toppings[] = array('ID'=>(int)$t[0], 'QUANTITY'=>(int)$t[1], 'PRICE'=>(int)$t[2] );
                }
            }

            # сформируем элемент блюда
            $items = array(
                'ID'        => (int) $value['PRODUCT_ID'],
                'PRICE'     => ($value['GOODS_PRICE']) ? (int)$value['GOODS_PRICE'] : (int)$value['PRICE'],
                'QUANTITY'  => (int) $value['QUANTITY'],
                'TOPPINGS'  => $toppings
            );
            
            if ($sauce)
                $items['SAUCE'] = $sauce;

            # запишем в общий массив
            $arOrder['PRODUCTS'][] = $items;
        }
        
        //Вернём полную инфу о возвращаемых товарах
        $productsOriginal = \Slim\Lib\Catalog::getGoodsList(false, $productIDs, true);
        $arOrder['PRODUCTS_INFO'] = $productsOriginal;
        
        
        $arOrder['CHANGED'] = $changed;
        $arOrder['CHANGED_TOPPINGS'] = $changed_toppings;
        
        if ($arOrder['CHANGED'] || $arOrder['CHANGED_TOPPINGS'])
            $arOrder['CHANGED_MESSAGE'] = "Состав заказа был изменён";

        # запишем общюю стоимость
        $arOrder['TOTAL_PRICE'] = (int) $data['ORDER_PRICE'];
        
        # текст купона
        if (strlen($data['COUPON_STATUS']) > 0)
            $arOrder['COUPON_STATUS'] = (string) $data['COUPON_STATUS'];
		
		# Добавим текст активированного подарочного купона, если есть
		if (!empty($arUser['UF_PROMO_PRESENT'])) {
			//Получим разовый промокод
			$arCode = \Slim\Lib\Promo::getSinglePromo($arUser['UF_PROMO_PRESENT']);

			$arOrder['BASKET_TEXT'] = $arCode['PROPERTY_BASKET_TEXT_VALUE'];
		}
		
		#пароль для акций
		if($data["ADD_SALES_PASS"]){
			$arOrder["ADD_SALES"]["PASS"] = $data["ADD_SALES_PASS"];
		}
		
        return $arOrder;
    }
	
	
	private static function select_akciya($arSelect, $arFilter){

		$arSel = array_merge($arSelect, array(
			"ID",
			"CODE",
			"IBLOCK_ID",
			"ACTIVE_FROM",
			"DETAIL_PAGE_URL",
			"NAME",
			"DATE_ACTIVE_FROM",
			"PREVIEW_PICTURE",
			"DETAIL_PICTURE",
			"PROPERTY_DOLJNO_BUT_MORE",
			"PROPERTY_ERROR"
		));

		$arFilter = array_merge($arFilter, array(
			"IBLOCK_CODE" => "MEHANIZM_AKCII",
			"ACTIVE_DATE" => "Y",
			"ACTIVE" => "Y",
		));

		$res = \CIBlockElement::GetList(Array("SORT" => "ASC"), $arFilter, false, false, $arSel);
		if($ob = $res->fetch()){

			$db_props = \CIBlockElement::GetProperty($ob["IBLOCK_ID"], $ob["ID"], array("sort" => "asc"), Array("CODE" => "DOLJNO_BUT_EL"));
			while($ar_props = $db_props->Fetch()){
				$ob["DOLJNO_BUT_EL"][] = $ar_props["VALUE"];
			};

			$db_props = \CIBlockElement::GetProperty($ob["IBLOCK_ID"], $ob["ID"], array("sort" => "asc"), Array("CODE" => "DOLJNO_BUT_CAT"));
			while($ar_props = $db_props->Fetch()){
				$ob["DOLJNO_BUT_CAT"][] = $ar_props["VALUE"];
			};

			$db_props = \CIBlockElement::GetProperty($ob["IBLOCK_ID"], $ob["ID"], array("sort" => "asc"), Array("CODE" => "SYTI"));
			while($ar_props = $db_props->Fetch()){
				$ob["SYTI"][] = $ar_props["VALUE"];
			};

			$ob["PREVIEW_PICTURE"] = array("SRC" => \CFile::GetPath($ob['PREVIEW_PICTURE']));
			$ob["DETAIL_PICTURE"] = array("SRC" => \CFile::GetPath($ob['DETAIL_PICTURE']));
			$arRes = $ob;
		}

		return $arRes;
	}


	/* функция отправляет корзину в ритайл*/
	private static function send_info_to_retail($BASKET_IDS, $FULL_ARRAY){
		$obj = new \Slim\Helper\Retailrocket(self::getSiteId(), $BASKET_IDS, "6", $FULL_ARRAY);
		$arrId = $obj->getArrayIdRetailrocket();
		return $arrId;
	}


	private static function get_bonus_tovar($arSelect, $arFilter_for_bonus){

		$arResult = array(); //собираю массив для возврата
		$arRes = array(); //вспомогательный массив

		$arRes["ARSELECT_BONUS"] = array_merge($arSelect, array(
			"ID",
			"IBLOCK_ID",
			"NAME",
			"CODE",
			"PROPERTY_ARTNUMBER"
		));

		$arRes["ARFILTER_BONUS"] = array_merge($arFilter_for_bonus, array(
			"IBLOCK_CODE" => "PRODUCTS",
			"ACTIVE_DATE" => "Y",
			"ACTIVE" => "Y",
		));

		$res = \CIBlockElement::GetList(array("SORT" => "ASC"), $arRes["ARFILTER_BONUS"], false, false, $arRes["ARSELECT_BONUS"]);
		while($ob = $res->fetch()){
			$arResult[] = array(
				"ID" => (int) $ob["ID"],
				"NAME" => $ob["NAME"],
				"ART_NUMBER" => $ob["PROPERTY_ARTNUMBER_VALUE"],
			);
		}

		return $arResult;
	}

	
	/**
	входящий массив для setItemsForDopProd
	{
		"PRODUCTS": [
			{
				"ID": 679,
				"TOPPINGS": [],
				"QUANTITY": 1
			},
			{
				"ID": 1155,
				"TOPPINGS": [
					{
						"ID": 55,
						"QUANTITY": 1
					}
				],
				"QUANTITY": 2
			}
		],
		"COUPON": "",
		"ADD_SALES" :
		{
			"PASS" : "classic"
		}
	}
	*/

	/**
	 * Возвращает массив с актуальной корзиной, выбранной акцией, и либо позициями по доп продаже, либо рекомендованные товары от ритайл
	 * @return array
	 *
	 */
	public static function setItemsForDopProd(){
		\CModule::IncludeModule('iblock');

		$arResult = array(); //собираю массив для возврата
		$arRes = array(); //вспомогательный массив

		//актуализирую полученные итемы
		$arRes["DATA_CHEKED"] = self::check();

		//собираю массив для возврата
		$arResult = $arRes["DATA_CHEKED"]; //собираю все актуальные итемы корзины

		//перебор полученного актуального массива и выбор нужных данных
		foreach($arRes["DATA_CHEKED"]["PRODUCTS"] as $PRODUCT){
			$arRes["BASKET_IDS"][] = $PRODUCT["ID"]; //выбираю IDS актуальных итемов
			//$arRes["BASKET_SUMM"] += $PRODUCT["PRICE"]*$PRODUCT["QUANTITY"]; //выбираю общую сумму
		}

		$arRes["BASKET_SUMM"] = $arResult['TOTAL_PRICE'];

		$arRes["ARSELECT_FILDS_AKCIYA"] = array(
			"ID",
			"NAME",
			"CODE",
			"PREVIEW_TEXT",
			"DETAIL_TEXT",
			"PROPERTY_AMOUNT_OF",
			"PROPERTY_AMOUNT_TO",
			"PROPERTY_COUNT_ITEMS",
			"PROPERTY_BONUS_TOVAR",
			"PROPERTY_MIN_SUMM_KORZINA",
			"PROPERTY_TIP_AKCII",
			"PROPERTY_KOD1C"
		);

		//по умолчанию фильтруем для доп продаж
		$arRes["ARFILTER_FILDS_AKCIYA"] = array(
			"IBLOCK_CODE" => "MEHANIZM_AKCII",
			"PROPERTY_TIP_AKCII" => AKCIYA_DOPPROD,
			"PROPERTY_CITY" => URL_ID,
			"<=PROPERTY_AMOUNT_OF" => $arRes["BASKET_SUMM"],
			">PROPERTY_AMOUNT_TO" => $arRes["BASKET_SUMM"]
		);

		//но если возвращается код/пароль то фильтруем по нему
		if(!empty($arRes["DATA_CHEKED"]["ADD_SALES"]["PASS"])){
			$arRes["ARFILTER_FILDS_AKCIYA"] = array(
				"IBLOCK_CODE" => "MEHANIZM_AKCII",
				"PROPERTY_CITY" => URL_ID,
				array(
					"LOGIC" => "OR",
					"PROPERTY_KOD1C" => $arRes["DATA_CHEKED"]["ADD_SALES"]["PASS"],
					"CODE" => $arRes["DATA_CHEKED"]["ADD_SALES"]["PASS"]
				)
			);
		}

		//выбор акции по нужному фильтру //по умолчанию идет выбор на доп продажи
		$arRes["AKCIYA"] = self::select_akciya($arRes["ARSELECT_FILDS_AKCIYA"], $arRes["ARFILTER_FILDS_AKCIYA"]);

		//сформирую массив с акцией //если есть массив с акцией, формирую его, если нет, вывожу просто рекомендованные товары от ритайл
		if($arRes["AKCIYA"]){
			$arResult["ADD_SALES"]["SALE"] = array(
				"CODE_1C" => $arRes["AKCIYA"]["PROPERTY_KOD1C_VALUE"],
				"PASS" => $arRes["AKCIYA"]["CODE"],
				"TYPE_SALE_ID" => (int) $arRes["AKCIYA"]["PROPERTY_TIP_AKCII_ENUM_ID"],
				"SALE_NAME" => $arRes["AKCIYA"]["NAME"],
				"SALE_CONDITIONS" => strip_tags($arRes["AKCIYA"]["PREVIEW_TEXT"]),
				"SALE_PICTURE" => "http://".self::getServerURI(self::getSiteId()).$arRes["AKCIYA"]["DETAIL_PICTURE"]["SRC"],
			);			
		}else{
			$arResult["SALE_ITEMS"] = self::send_info_to_retail($arRes["BASKET_IDS"], true); //рекомендованные товары от ритайл полный массив
		}

		//вывод итемов для доп продаж
		if($arRes["AKCIYA"]["PROPERTY_TIP_AKCII_ENUM_ID"] == self::AKCIYA_DOPPROD && $arRes["BASKET_SUMM"] <= $arRes["AKCIYA"]["PROPERTY_AMOUNT_TO_VALUE"]){
			$arResult["SALE_ITEMS"] = self::dopprod($arRes["BASKET_SUMM"], $arRes["BASKET_IDS"], $arRes["AKCIYA"]);
		}

		//акция по паролю и минимальной суммы в корзине
		if($arRes["AKCIYA"]["PROPERTY_TIP_AKCII_ENUM_ID"] == AKCIYA_PODAROKPAROL_SUMM){
			if($arRes["BASKET_SUMM"] > $arRes["AKCIYA"]["PROPERTY_AMOUNT_OF_VALUE"]){
				$arResult["ADD_SALES"]["SALE_GIFT"] = self::get_bonus_tovar(array(), array("ID" => $arRes["AKCIYA"]["PROPERTY_BONUS_TOVAR_VALUE"]));
			}else{
				$arRes["MESSAGA"][] = $arRes["AKCIYA"]["PROPERTY_ERROR_VALUE"]["TEXT"];
			}
		}

		//вывод
		foreach($arRes["MESSAGA"] as $MESSAGA){
			$arResult["ADD_SALES"]["ERROR_MESSAGE"][] = strip_tags($MESSAGA);
		}

		//$arResult["DEBUG"] = $arRes;
		return $arResult;
	}


	/**
	 * @param $BASKET_SUMM
	 * @param $BASKET_IDS
	 * @param $AKCIYA
	 *
	 * Возвращает позиции по доп продажам, необходимо передать массив выбранной акции.
	 *
	 *
	 * @return array
	 */
	public static function dopprod($BASKET_SUMM, $BASKET_IDS, $AKCIYA){
		\CModule::IncludeModule('iblock');

		$arResult = array(); //собираю массив для возврата
		$arRes = array(); //вспомогательный массив

		$arRes["FROM_RITAIL"] = self::send_info_to_retail($BASKET_IDS, false);

		$arRes["COUNT_ITEMS_TO_SHOW"] = intVal($AKCIYA['PROPERTY_COUNT_ITEMS_VALUE']); //Количество товаров для вывода
		$arRes["COUNT_ITEMS_FROM_RITAIL"] = count($arRes["FROM_RITAIL"]); //Количество товаров полученных от ритайл
		$arRes["RAZNIZA"] = $AKCIYA["PROPERTY_AMOUNT_TO_VALUE"] - $BASKET_SUMM; //разница между суммой корзины и
		$arRes["ARPRICE_TYPE_ID"] = self::getCatalogGroupId(); //тип цены

		$arRes["ARSELECT_CAT"] = Array(
			"ID",
			"IBLOCK_ID",
			"IBLOCK_CODE",
			"NAME",
			"CODE",
			"DATE_ACTIVE_FROM",
			"CATALOG_GROUP_".$arRes["ARPRICE_TYPE_ID"],
			"CATALOG_PRICE_ID_".$arRes["ARPRICE_TYPE_ID"],
		);

		$arRes["ARFILTER_CAT"] = array(
			"IBLOCK_CODE" => "PRODUCTS",
			"ACTIVE_DATE" => "Y",
			"ACTIVE" => "Y",
		);

		$arRes["ARFILTER_CAT"]["!SECTION_ID"] = array(
			self::SECTION_ID_ALCOHOL,
			self::SECTION_WOK_BUILDER,
			self::SECTION_WOK_BASE,
			self::SECTION_WOK_FILLING,
			self::SECTION_WOK_SAUCE,
			self::SECTION_WOK_TOPPING,
			self::SECTION_GIFT_CERT
		);

		//либо по цене либо с ритайла
		$arRes["ARFILTER_CAT"][] = array(
			"LOGIC" => "OR",
			"ID" => $arRes["FROM_RITAIL"],
			">CATALOG_PRICE_" . $arRes["ARPRICE_TYPE_ID"] => (int) $arRes["RAZNIZA"],
		);

		$arRes["ARRESITEMS_RITAIL"] = array();
		$arRes["ARRES_ITEMS"] = array();

		$arMass_catalog = \CIBlockElement::GetList(array("CATALOG_PRICE_".$arRes["ARPRICE_TYPE_ID"] => "ASC"), $arRes["ARFILTER_CAT"], false, array("nTopCount" => 100), $arRes["ARSELECT_CAT"]);
		while($ob = $arMass_catalog->Fetch()){

			$ob['CATALOG_GROUP_ID'] = $arRes["ARPRICE_TYPE_ID"];
			$ob['CATALOG_PRICE_FORMATED'] = number_format($ob['CATALOG_PRICE_'.$arRes["ARPRICE_TYPE_ID"]], 0, ".", "");

			if(in_array($ob["ID"], $arRes["FROM_RITAIL"])){
				$arRes["ARRESITEMS_RITAIL"][$ob['CATALOG_PRICE_FORMATED']] = $ob;
			}else{
				$arRes["ARRES_ITEMS"][$ob['CATALOG_PRICE_FORMATED']] = $ob;
			}
		}

		$arRes["ARALL_ITEMS"] = $arRes["ARRESITEMS_RITAIL"] + $arRes["ARRES_ITEMS"];

		foreach($arRes["ARALL_ITEMS"] as $key => $ITEM){
			if($ITEM['CATALOG_PRICE_FORMATED'] > $arRes["RAZNIZA"]){
				$FULL_ITEM = \Slim\Lib\Catalog::getGoodsList(false, $ITEM["ID"], true);
				$arResult[] = $FULL_ITEM[0];
				if(count($arResult) == $arRes["COUNT_ITEMS_TO_SHOW"]){break;}
			}
		}

		return $arResult;
	}

}
?>