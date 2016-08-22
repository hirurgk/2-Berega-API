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

        $group_id	= self::getCatalogGroupId();                                # тип цены с учетом города
        $data       = json_decode(self::getApp()->request->getBody(), true);    # данные с заказом
        $siteId     = self::getSiteId();

        # в методе CheckBasketFull проверяется скидка пользователя, для этого авторизуемся если есть данные
        \Slim\Helper\Authentication::auth();

        # Соберем массив для добавления в корзину товаров и привязки к заказу
        $arCart = self::setItemsForBasket($data, $group_id, $siteId);

        # Приведем к булевому значение день рождение
        $hb = ($data['BIRTHDAY']) ? true : false;

        $checkBasket = CheckBasketFull($arCart, $siteId, true, $hb, true, $data['COUPON']);

        return self::setItemsForApp( $checkBasket );
    }


    /**
     * Метод для создания массива идентичный тому который возвращает CSaleBasket
     * Необходим для Функция проверки актуальности товаров в корзине и применения скидки
     *
     * @param $data         данные с заказом
     * @param $group_id     тип цены
     * @param $siteId       привязка к региону
     *
     * @return array
     */
    public static function setItemsForBasket( $data, $group_id, $siteId )
    {
        # Соберём ID- шники товаров
        $PRODUCTS_DATA = $PRODUCTS_DATA_IDS = array();

        foreach ($data['PRODUCTS'] as $product) {
            if ($product['ID'] > 900000) {
				$PRODUCTS_IDS[]     = (int)$product['ID'] - 900000;
				$product['ID']     = (int)$product['ID'] - 900000;
			} else {
				$PRODUCTS_IDS[]     = $product['ID'];
			}
			$PRODUCTS_DATA[]    = $product;
        }

        # Уберем дубли
        $PRODUCTS_IDS = array_unique($PRODUCTS_IDS);

        # Выберем из базы активные товары и составим массив с продуктами по их ID- шникам
        $products   = array();
        if( sizeof($PRODUCTS_IDS) )
        {
            $pFilter    = array("IBLOCK_ID"=>IBLOCK_CATALOG_ID, 'ACTIVE'=>'Y', "ACTIVE_DATE"=>"Y", 'ID' => $PRODUCTS_IDS, '!CATALOG_PRICE_'.$group_id => false);
            $pSelect    = array('ID', 'IBLOCK_ID', 'NAME', 'ACTIVE', 'DETAIL_PAGE_URL', 'CODE', 'DATE_ACTIVE_FROM', 'DATE_ACTIVE_TO', 'PROPERTY_BUY_DISABLED', 'PROPERTY_LANCH_START', 'PROPERTY_LANCH_END', 'PROPERTY_ACTIVITY_CALENDAR', 'CATALOG_GROUP_'.$group_id);

            $dbProducts = \CIBlockElement::GetList(array(), $pFilter, false, false, $pSelect);
            while ($arProduct = $dbProducts->Fetch())
                $products[$arProduct['ID']] = $arProduct;
        }


        $arCart = array();  // массив с товарами заказа
        foreach($PRODUCTS_DATA as $prodKey => $prodValue)
        {
            # Удаление неактуальных товаров из списка корзины (по времени) // Заменено на "ACTIVE_DATE"=>"Y" в фильтре
           /* if( ($products[$prodValue['ID']]["PROPERTY_BUY_DISABLED_ENUM_ID"]==ITEM_PROPERTY_DISABLED_ENUM) || ((strtotime($products[$prodValue['ID']]["DATE_ACTIVE_TO"]) > 0) && (strtotime($product[$prodValue['ID']]["DATE_ACTIVE_TO"]) < ShowTimeNow($siteId))) )
            {
                $arCart[] = null;
                continue;
            } */

            #  Удаление неактуальных товаров из списка корзины (Ланч)
			if($products[$prodValue['ID']]["PROPERTY_LANCH_START_VALUE"] && $products[$prodValue['ID']]["PROPERTY_LANCH_END_VALUE"])
            {
                $date = date("w");
                $lanchStart = strtotime(date("d.m.Y", time())." ".$products[$prodValue['ID']]["PROPERTY_LANCH_START_VALUE"]);   //Время начала ланча
                $lanchEnd = strtotime(date("d.m.Y", time())." ".$products[$prodValue['ID']]["PROPERTY_LANCH_END_VALUE"]);       //Время конца ланча

                if(ShowTimeNow($siteId) >= $lanchStart && ShowTimeNow($siteId) <= $lanchEnd && $date != 0 && $date != 6)
                    $timeFlag = true; //Время ланча
                else
                {
                    $arCart[] = null;
                    continue;
                }
			}

            #  Удаление неактуальных товаров из корзины по календарю активности
			$AC = \ActivityCalendar::isActive($products[$prodValue['ID']]["PROPERTY_ACTIVITY_CALENDAR_VALUE"], self::getSiteId());

            if(!$AC['ACTIVE'])
            {
                $arCart[] = null;
                continue;
            }

            # Удаление неактивных товаров
            if( !array_key_exists( $prodValue['ID'], $products))
            {
                $arCart[] = null;
                continue;
            }

            /*--  ОБРАБОТКА ТОППИНГОВ --*/
            $topping_ids        = array();      // Массив с id топингов
            $props              = array();      // Массив для свойств заказа
            $toppings_string    = '';	        // Строка вида "id,quantity;id,quantity;"


            # соберем ID топингов
            foreach ($prodValue['TOPPINGS'] as $topping)
                $topping_ids[] = $topping['ID'];


            # пройдемся по топингам
            if( count($topping_ids)>0 )
            {
                # Запрос на получение активных топингов
                $dbToppings = \CIBlockElement::GetList(
                    array(),
                    array('IBLOCK_ID'=>self::INGREDIENTS, 'ID'=>$topping_ids, 'ACTIVE'=>'Y'),
                    false,
                    false,
                    array('ID', 'NAME','CATALOG_GROUP_'.$group_id)
                );

                # Соберём в массив ID и Названия топпингов
                $arToppings = array();
                while ($arTopping = $dbToppings->Fetch())
                    $arToppings[$arTopping['ID']] = $arTopping;

                # Если не пустой, а это значит что есть активные топинги
                if( count($arToppings)>0 )
                {
                    foreach ($prodValue['TOPPINGS'] as $kTopping => $vTopping)
                    {
                        # Если топинг не активен, запишем место него null
                        if (!array_key_exists($vTopping['ID'], $arToppings)) {
                            $toppings_string .= 'null;';
                            unset($prodValue['TOPPINGS'][$kTopping]);
                            continue;
                        }

                        # Заполним строку с топпингами
                        $toppings_string .= $vTopping['ID'] . ',' . $vTopping['QUANTITY'] . ',' .$arToppings[$vTopping['ID']]['CATALOG_PRICE_'.$group_id] .  ';';
                    }


                    # Доп.ингридиенты к пицце
                    if (strlen($toppings_string) > 0)
                        $props[] = array("NAME" => "Доп.ингридиенты к пицце", "CODE" => "NABORLIST_PIZZA", "VALUE" => $toppings_string, "SORT" => "100");

                    # Соус
                    if ($prodValue['SAUCE'])
                        $props[] = array("NAME" => "соус", "CODE" => "SOUS", "VALUE" => $prodValue['SAUCE'], "SORT" => "100");

                    # Строка вида "Королева моря на томатном соусе [Зелень (укроп) x2, Маринованные огурцы]"
                    $endName = $products[$prodValue['ID']]['NAME'];


                    if ($prodValue['SAUCE'] == 'SL_TOMATO')
                        $endName .= ' на томатном соусе ';
                    elseif ($prodValue['SAUCE'] == 'SL_CREAMY')
                        $endName .= ' на сливочном соусе ';


                    # Проставим названия топпингов и их кол-во
                    $endName .= '[';
                    $c = count($prodValue['TOPPINGS']);
                    $i = 0;
                    foreach ($prodValue['TOPPINGS'] as $topping) {
                        $i++;
                        $endName .= $arToppings[$topping['ID']]['NAME'];
                        if ($topping['QUANTITY'] > 1) $endName .= ' x' . $topping['QUANTITY'];
                        if ($i < $c) $endName .= ', ';
                    }
                    $endName .= ']';


                    $props[] = array("NAME" => "Список ингредиентов (не заполняется)", "CODE" => "DOPLIST", "VALUE" => $endName, "SORT" => "100");
                }
            }
            //---

            $detail_page = str_replace('#SITE_DIR#', '', $products[$prodValue['ID']]['DETAIL_PAGE_URL']);
            $detail_page = str_replace('#ELEMENT_CODE#', $products[$prodValue['ID']]['CODE'], $detail_page);

            $arCart[] = array(
				'IBLOCK_ID'         => $products[$prodValue['ID']]['IBLOCK_ID'],
                'PRODUCT_ID'        => $products[$prodValue['ID']]['ID'],
                'LID'               => $siteId,
                'NAME'              => $products[$prodValue['ID']]['NAME'],
                'DETAIL_PAGE_URL'   => self::getApp()->request->getUrl() . $detail_page,
                'QUANTITY'          => $prodValue['QUANTITY'],
                'PRICE'             => $products[$prodValue['ID']]['CATALOG_PRICE_'.$group_id],
                'CURRENCY'          => 'RUB',
                'CAN_BUY'           => 'Y',
                'PROPS'             => $props,
                'MODULE'            => 'catalog',
            );
        }

        return $arCart;
    }


    /**
     * Создание массива для ответа приложению
     * содержит только товары, топинги и итоговую стоимость с расчетом скидки
     *
     * @param $data
     *
     * @return array
     */
    public static function setItemsForApp( $data )
    {
		\CModule::IncludeModule('iblock');
		global $USER;
    	$arUser = \CUser::GetByID($USER->GetID())->Fetch();
		
        $arOrder = array();
        foreach( $data['PRODUCTS'] as $key=>$value)
        {
            # Если товар удален
            if($value == null)
            {
                $arOrder['PRODUCTS'][] = null;
                continue;
            }

            # соберем Топинги
            $toppings = array();
            $arToppings = explode(';' , $value['PROPS'][0]['VALUE']);
            foreach( $arToppings as $keyProp => $valueProp)
            {
                $t = explode(',', $valueProp);

                if( $t[0] == 'null')        # Если топинг удален
                    $toppings[] = null;
                elseif( $t[0] != '' )
                    $toppings[] = array('ID'=>(int)$t[0], 'QUANTITY'=>(int)$t[1], 'PRICE'=>(int)$t[2] );
            }

            # сформируем элемент блюда
            $items = array(
                'ID'        => (int)$value['PRODUCT_ID'] + 900000,
                'PRICE'     => ($value['GOODS_PRICE']) ? (int)$value['GOODS_PRICE'] : (int)$value['PRICE'],
                'QUANTITY'  => (int)$value['QUANTITY'],
                'TOPPINGS'  => $toppings
            );

            # запишем в общий массив
            $arOrder['PRODUCTS'][] = $items;
        }

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
		
        return $arOrder;
    }
}
?>