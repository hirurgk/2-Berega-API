<?
/**
 * Created by y.alekseev
 */

namespace Slim\Lib;

class Order extends \Slim\Helper\Lib
{
    /**
     * Получение списка заказов(или одного заказа, если указан ID) пользователя
     * $payment_url - ссылка на страницу оплаты, указывается при создании заказа
     *
     * @param bool $id
     * @param bool $payment_url
     *
     * @return array
     */
	public static function getOrderList($id = false, $payment_url = false)
	{
		global $USER, $DB;

		\CModule::IncludeModule('iblock');
		\CModule::IncludeModule('sale');
		
		$orders = $order_ids = array();
		
		$timestamp = self::getTimestamp();

        # сформируем $arFilter
		$arFilter = array('USER_ID' => $USER->GetID(), 'LID' => self::getSiteId());

        if ($id)
			$arFilter = array_merge($arFilter, array('ID' => $id));

		if ($timestamp)
			$arFilter = array_merge($arFilter, array('>DATE_UPDATE' => date($DB->DateFormatToPHP(\CLang::GetDateFormat()), $timestamp)));
		
		
		//---Сначала соберём все заказы пользователя
		$dbOrders = \CSaleOrder::GetList(
				array(),
				$arFilter,
				false,
				false,
				array('ID', 'USER_ID', 'PAYED', 'STATUS_ID', 'PRICE', 'DISCOUNT_VALUE', 'DATE_INSERT')
		);
		while ($arOrder = $dbOrders->Fetch()) {
            $itemOrder = array();
            $itemOrder['ID']             = (int)$arOrder['ID'];
            $itemOrder['USER_ID']        = (int)$arOrder['USER_ID'];
            $itemOrder['PAYED']          = $arOrder['PAYED'];
            $itemOrder['STATUS_ID']      = $arOrder['STATUS_ID'];
            $itemOrder['PRICE']          = (int)$arOrder['PRICE'];
            $itemOrder['DISCOUNT_VALUE'] = (int)$arOrder['DISCOUNT_VALUE'];
            $itemOrder['DATE_INSERT']    = $arOrder['DATE_INSERT'];

            $orders[]    = $itemOrder;
			$order_ids[] = (int)$arOrder['ID'];
		}
		//---
		
		# Если заказы не найдены - вернем пустой массив
		if (!sizeof($orders))
			return array();
		
		
		//---Получим статусы заказов
		$statuses = array();
		$dbStatuses = \CSaleStatus::GetList();
		while ($arStatus = $dbStatuses->Fetch()) {
			$statuses[$arStatus['ID']] = $arStatus['NAME'];
		}
		foreach ($orders as $key => $order) {
			$orders[$key]['STATUS'] = $statuses[$order['STATUS_ID']];
			unset($orders[$key]['STATUS_ID']);
		}
		//---

		$goods      = array();
		$good_ids   = array();
		$basket_ids = array();
		$basket_props = array();
		$products   = array();
		$sections   = array();
		$section_ids = array();


        //---Соберём все продукты заказов
		$dbProducts = \CSaleBasket::GetList(
				array(),
				array('ORDER_ID' => $order_ids),
				false,
				false,
				array('ID', 'PRODUCT_ID', 'ORDER_ID', 'PRICE', 'QUANTITY', 'NAME', 'DISCOUNT_PRICE')
		);
		while ($arProduct = $dbProducts->Fetch()) {
            $itemProduct = array();
            $itemProduct['ID']              = (int)$arProduct['ID'];
            $itemProduct['PRODUCT_ID']      = (int)$arProduct['PRODUCT_ID'];
            $itemProduct['ORDER_ID']        = (int)$arProduct['ORDER_ID'];
            $itemProduct['PRICE']           = (int)$arProduct['PRICE'];
            $itemProduct['QUANTITY']        = (int)$arProduct['QUANTITY'];
            $itemProduct['DISCOUNT_PRICE']  = (int)$arProduct['DISCOUNT_PRICE'];


			$goods[$arProduct['ORDER_ID']][] = $itemProduct;
			
			$good_ids[]     = (int)$arProduct['PRODUCT_ID'];
			$basket_ids[]   = (int)$arProduct['ID'];
		}
		//---
		
        # Если товары найдены...
		if (sizeof($goods))	{
			//---Соберём в массив оригиналы товаров
	        $dbProducts = \CIBlockElement::GetList(
	        		array(),
	        		array('IBLOCK_ID' => self::PRODUCTS, 'ID' => $good_ids),
	        		false,
	        		false,
	        		array('ID', 'IBLOCK_SECTION_ID', 'NAME', 'ACTIVE', 'DATE_ACTIVE_FROM', 'DATE_ACTIVE_TO')
	        );
	        while ($arProduct = $dbProducts->Fetch()) {
	        	$products[$arProduct['ID']] = $arProduct;
	        	$section_ids[] = $arProduct['IBLOCK_SECTION_ID'];
	        }
        	//---
        	
        	
	        //---Получим свойства товаров в заказе
	        $dbBp = \CSaleBasket::GetPropsList(array(), array('BASKET_ID' => $basket_ids));
	        while ($arBp = $dbBp->Fetch()) {
	        	$basket_props[$arBp['BASKET_ID']][$arBp['CODE']] = $arBp;
	        }
	        //---
	        
	        
	        //---Получим разделы
	        $dbSections = \CIBlockSection::GetList(array(), array('ID' => $section_ids));
	        while ($arSection = $dbSections->Fetch()) {
	        	$sections[$arSection['ID']] = $arSection;
	        }
	        //---
	        
			
	        //Отсортируем товары по их заказам, дополнительно произведём необходимые действия с полями
			foreach ($orders as &$order) {
				$order['PAYED'] = $order['PAYED'] == 'Y' ? true : false;
				$order['DATE_INSERT'] = strtotime($order['DATE_INSERT']);
				
				foreach ($goods[$order['ID']] as &$good) {
					//$product - оригинал продукта bp ,fps
					//$good - товар(из корзины заказа) для вывода
					$product = $products[$good['PRODUCT_ID']];
					
					
					//---Проставим актуальную активность товарам
					$active = true;
					$time = time();
						
					if ($product['DATE_ACTIVE_FROM'])
					    if (strtotime($product['DATE_ACTIVE_FROM']) > $time)
                            $active = false;
						
					if ($product['DATE_ACTIVE_TO'])
					    if (strtotime($product['DATE_ACTIVE_TO']) < $time)
                            $active = false;
						
					if ($product['ACTIVE'] == 'N')
                        $active = false;
						
					$good['ACTIVE'] = $active;
					//---
					
					
					//Названия товаров по умолчанию
					$good['NAME'] = $product['NAME'];
					
					
					//Название категории
					$good['SECTION_NAME'] = $sections[$product['IBLOCK_SECTION_ID']]['NAME'];
					
					
					//---Топпинги
					$toppings = explode(';', $basket_props[$good['ID']]['NABORLIST_PIZZA']['VALUE']);
                    foreach ($toppings as $k => $topping) {
                        if (!$topping['ID'] || $topping == 'null' )
                            unset($toppings[$k]);    //Удалим пустые
                    }


					if (sizeof($toppings)) {
						foreach ($toppings as &$topping) {
							$arTopping = explode(',', $topping);
							$topping = array('ID' => (int)$arTopping[0], 'QUANTITY' => (int)$arTopping[1]);
						}
						unset($topping);
						$good['TOPPINGS'] = $toppings;
					}
					//---
					
					
					//Удалим лишнее
					unset($good['ID']);
					unset($good['ORDER_ID']);
				}

				
				//Привяжем товары к их заказам
				$order['PRODUCTS'] = $goods[$order['ID']];

                if($payment_url)
                    $order['PAYMENT_URL'] = $payment_url;
			}

			//Удалим ссылки
			unset($order);
			unset($good);
		}

		return $orders;
	}


    
	/**
	 * Создание заказа
	 *
		$_POST['NAME'] = 'Тестоедов Тестоед Тестоедович';
		$_POST['PHONE'] = '964-999-88-77';
		$_POST['STREET'] = '000000162';
		$_POST['HOUSE'] = '111';
		$_POST['FLAT'] = '777';
		$_POST['COMMENT_ADDRESS'] = 'Адрес коммент';
		$_POST['COMMENT_ORDER'] = 'Коммент к заказу =)';
		$_POST['NEW_ADDRESS'] = 'Y';
		$_POST['NEW_PHONE'] = 'Y';
		$_POST['AUTO_ORDER'] = 'Y';
		$_POST['DELETE_PHONES'] = '905-962-65-55;965-041-22-42';
		$_POST['DELETE_ADDRESS'] = '1656;1657';
		$_POST['CHOPSTICKS'] = '2';
		$_POST['CHANGE'] = '1200';
		$_POST['PAY_SYSTEM_ID'] = '4';
		$_POST['PAYEE'] = 'Тестоедов Тестоед Тестоедович';
		$_POST['DISCOUNT'] = 'HB';
		$_POST['BIRTHDATE'] = '03.04.1989';
		$_POST['DELIVERY_DATE'] = '12.04.2015';
		$_POST['DELIVERY_HOURS'] = '13';
		$_POST['DELIVERY_MINUTES'] = '40';
		$_POST['IP'] = '127.0.0.1';
		$_POST['PRODUCTS'] = array(
				array(
						'ID' => 625,
						'QUANTITY' => 2,
						'SAUCE' => 'SL_TOMATO',
						'TOPPINGS' => array(
								array(
										'ID' => 267,
										'QUANTITY' => 2,
								),
								array(
										'ID' => 257,
										'QUANTITY' => 1,
								),
						),
				),
				array(
						'ID' => 737,
						'QUANTITY' => 1,
						'SAUCE' => 'SL_CREAMY',
						'TOPPINGS' => array(),
				),
		);
	 *
	 * @return array
	 */
	public static function create()
	{
        //Проверяем доступность 1С
        if (!\Tools1C::isAvailable()) {
            \Slim\Helper\ErrorHandler::put('ORDER_CREATE_ERROR');
            return array();
        }
        
		\CModule::IncludeModule('iblock');
		\CModule::IncludeModule('sale');
		\CModule::IncludeModule('catalog');

		global $USER;

		$siteId     = self::getSiteId();
		$group_id   = self::getCatalogGroupId();                                // ID Типа плательщиков
        $data       = json_decode(self::getApp()->request->getBody(), true);    // Данные с информацией по заказу
        //$paySystemId  = \C2B::S('PAYMENT_ID');                                  // Получим данные платежной системы
        $paySystemCode  = \C2B::S('MOBILE_PAY_SYSTEM');


        //Отредактируем необходимые поля
        $data['NEW_ADDRESS'] = $data['NEW_ADDRESS'] ? 'Y' : 'N';
        $data['NEW_PHONE']   = $data['NEW_PHONE'] ? 'Y' : 'N';
        $data['AUTO_ORDER']  = $data['AUTO_ORDER'] ? 'Y' : 'N';


        //Отложенная доставка
        if( $data['DELAYED_DELIVERY'] ) {
            // Ввиду появления проблем со временем т.к. на девайсах могут стоять не верные часовые пояса,
            // принято решение присылать DELAYED_DELIVERY строкой в формате d.m.Y H:i
            // далее конвертим уже в корректный timestamp
            // в данном случае зоны не используем т.к время уже с учетом региона
            if (is_string($data['DELAYED_DELIVERY'])) {
                $dateTime = new \DateTime($data['DELAYED_DELIVERY']);
                $data['DELAYED_DELIVERY'] = $dateTime->getTimestamp();
            }else {
                $dateTime = new \DateTime();
                $dateTime->setTimestamp($data['DELAYED_DELIVERY']);
                $dateTime->setTimezone( new \DateTimeZone( self::getTimeZone($siteId) ));
                $data['DELAYED_DELIVERY'] = $dateTime->getTimestamp();
            }

            $data['BRINGTOTIME']        = 'Y';  // Флаг отложенной доставки
            $data['DELIVERY_DATE']      = $dateTime->format('d.m.Y');
            $data['DELIVERY_HOURS']     = $dateTime->format('H');
            $data['DELIVERY_MINUTES']   = $dateTime->format('i');
        }


        //ДР
        if( isset($data['BIRTHDATE']) ) {
            if($data['BIRTHDATE'] > 0)
                $data['BIRTHDATE'] = date('d.m.Y', $data['BIRTHDATE']);
            else
                unset($data['BIRTHDATE']);
        }

        // Получатель
        if( !isset($data['PAYEE']) )
            $data['PAYEE'] = 'no';

        //Сдача
        if( $data['CHANGE'] == 0 )
            unset( $data['CHANGE'] );


        # Соберем массив для добавления в корзину товаров и привязки к заказу
        $arBasketItems = \Slim\Lib\Basket::setItemsForBasket($data, $group_id, $siteId);


        # Актуализируем
        # Учитывается скидка пользователя и скидка на ДР
        $hb = ($data['BIRTHDATE'] && $data['DISCOUNT'] == 'HB') ? true : false;
        $arResult = CheckBasketFull($arBasketItems, $siteId, true, $hb, true, $data['COUPON']);


        # Если после актуализации Стоимость заказа меньше минимальной стоимости
        if( $arResult["ORDER_PRICE"] < \C2B::S('MIN_SUM_ORDER') ) {
            \Slim\Helper\ErrorHandler::put('PRODUCTS_NOT_FOUND');
            return array();
        }

		# Удаляем существующую корзину на сайте, чтобы избежать их скрещивания
		$FUser = \CSaleBasket::GetBasketUserID(true);
		if (!empty($FUser)) {
			\CSaleBasket::DeleteAll($FUser);
		}

        # Добавим товары в корзину
		foreach ($arResult['PRODUCTS'] as $product) {
			\CSaleBasket::Add( $product );
		}


        # Сформируем массив для заказа
        $arFields['USER_ID']            = $USER->GetID();
        $arFields['PRICE']              = $arResult['ORDER_PRICE'];
        $arFields['LID']                = $siteId;
        $arFields['CURRENCY']           = 'RUB';
        $arFields['PERSON_TYPE_ID']     = self::PERSON_TYPE_ID;
        $arFields['USER_DESCRIPTION']   = $data['COMMENT_ORDER'];
        
        $ps_id = PAYSYSTEM_MOBILE;
        if ($paySystemCode == 'online_dengi')
            $ps_id = PAYSYSTEM_MOBILE;
        elseif ($paySystemCode == 'pskb')
            $ps_id = PAYSYSTEM_MOBILE_2;
		//if ($USER->GetLogin() == 'degal' || $USER->GetLogin() == 'y.alekseev' || $USER->GetLogin() == 'byss') {
        	$arFields['PAY_SYSTEM_ID']      = ($data['PAY_SYSTEM_ID'] == PAYSYSTEM_MOBILE) ? $ps_id : $data['PAY_SYSTEM_ID'];
		//} else {
		//	$arFields['PAY_SYSTEM_ID']      = ($data['PAY_SYSTEM_ID'] == 8) ? 8 : $data['PAY_SYSTEM_ID'];
		//}

        # Создадим заказ
        $order_id = \CSaleOrder::Add($arFields);

        if (!$order_id) {
            \Slim\Helper\ErrorHandler::put('ORDER_CREATE_ERROR');
            return array();
        }

        # получим информацию по пользователю
        $userInfo =  \CUser::GetByID($USER->GetID())->Fetch();
        
        
        # если НЕТ флага ДР и пользователь делает первый заказ из приложения,
        # сохраним эти данные в заказе и учетке пользователя
        /* Акция не активна
		if ($userInfo['UF_APP_DISCOUNT'] !== 'Y' && !$hb) {
            $data['FIRST_ORDER_DISCOUNT'] = 'Y';

            $upUser = new \CUser;
            $upFieldsUser = array('UF_APP_DISCOUNT' => 'Y');
            $upUser->Update($USER->GetID(), $upFieldsUser);
        }
		*/


        # Привяжем товары к заказу
        \CSaleBasket::OrderBasket($order_id, \CSaleBasket::GetBasketUserID(), $siteId);


        # Получим свойства заказа из базы
        $propsValue = array();
        $dbOrderProps = \CSaleOrderProps::GetList(array(), array('PERSON_TYPE_ID'=>self::PERSON_TYPE_ID));
        while( $arOrderProps = $dbOrderProps->Fetch())
            $propsValue[ $arOrderProps['CODE'] ] = array( 'ID'=>$arOrderProps['ID'], 'NAME'=>$arOrderProps['NAME'] );

        
        # Установим ID купона
        if (strlen($data['COUPON']) > 0) {
            \CSaleOrderPropsValue::Add(
                array(
                    "ORDER_ID"          => $order_id,
                    "ORDER_PROPS_ID"    => $propsValue['COUPON']['ID'],
                    "NAME"              => "Купон",
                    "CODE"              => "COUPON",
                    "VALUE"             => $data['COUPON']
                )
            );
        }
        
        
        # Установим подарок по купону
        if (strlen($userInfo['UF_PROMO_PRESENT']) > 0) {
            \CSaleOrderPropsValue::Add(
                array(
                    "ORDER_ID"          => $order_id,
                    "ORDER_PROPS_ID"    => $propsValue['PROMO_PRESENT']['ID'],
                    "NAME"              => "Подарок за купон",
                    "CODE"              => "PROMO_PRESENT",
                    "VALUE"             => $userInfo['UF_PROMO_PRESENT']
                )
            );
        }
        

        # Добавляем свойства к заказу
        foreach ($data as $key => $value) {
            $siteCode = self::matchCodeOrder( $key );
            if( $siteCode ) {
                \CSaleOrderPropsValue::Add(array(
                    "ORDER_ID"          => $order_id,
                    "ORDER_PROPS_ID"    => $propsValue[$siteCode]['ID'],
                    "NAME"              => $propsValue[$siteCode]['NAME'],
                    "CODE"              => $siteCode,
                    "VALUE"             => $value
                ));
            }
        }


        # Установим источник заказа
		\CSaleOrderPropsValue::Add(
			array(
				"ORDER_ID"          => $order_id,
				"ORDER_PROPS_ID"    => $propsValue['ORDER_SOURCE']['ID'],
				"NAME"              => "Источник заказа",
				"CODE"              => "ORDER_SOURCE",
				"VALUE"             => "SV5"
			)
		);


        // Посчитаем сумму доставки, и сохраним если она есть
        $arDelivery = \Slim\Lib\Delivery::GetTime( $data['STREET'], $data['HOUSE'], $arResult['ORDER_PRICE'], $data['DELAYED_DELIVERY'] );
        if( isset( $arDelivery['COST_OF_DELIVERY']) ){
			$flag= \CSaleOrderPropsValue::Add(
                array(
                    "ORDER_ID"          => $order_id,
                    "ORDER_PROPS_ID"    => $propsValue['DELIVERY_AMOUNT']['ID'],
                    "NAME"              => "Сумма доставки",
                    "CODE"              => "DELIVERY_AMOUNT",
                    "VALUE"             => $arDelivery['COST_OF_DELIVERY']
                )
            );

            // Изменим итоговую сумму с учетом стоимости доставки
			if($flag) {
                $arResult['ORDER_PRICE'] += $arDelivery['COST_OF_DELIVERY'];
				\CSaleOrder::Update($order_id, array('PRICE'=>$arResult['ORDER_PRICE']) );
			}
		} else {
			\CSaleOrder::Update($order_id, array('PRICE'=>$arResult['ORDER_PRICE']) );
		}


        # Если выбрана оплата картой, сформируем URL для получения формы
		//if ($USER->GetLogin() == 'degal' || $USER->GetLogin() == 'y.alekseev' || $USER->GetLogin() == 'byss') {
			if ($data['PAY_SYSTEM_ID'] == 8)
				$payment_url = \Slim\Helper\PaymentSystem::getUrl($order_id, $arResult['ORDER_PRICE'], $paySystemCode);
		//} else {
		//	if ($data['PAY_SYSTEM_ID'] == 8)
		//		$payment_url = \Slim\Helper\PaymentSystem::getUrl($order_id, $arResult['ORDER_PRICE'], 'online_dengi');
		//}

        return self::getOrderList( (int)$order_id, $payment_url);
	}
	


	/**
	 * Создание заказа для неавторизованного пользователя
	 * @return array
	 */
	public static function createForGuest()
	{
		global $USER;
		$USER->Authorize(self::USER_GUEST);
		
		return self::create();
	}



    /**
     * соответсвие старых полей на сайте с поляпи поступающими от приложения
     * key - значение из $_POST
     * value - значение на сайте
     * @param $key
     * @return mixed
     */
    private static function matchCodeOrder($key)
    {
        $data = array(
            'PHONE'             => 'PHONE',
            'NAME'              => 'FIO',
            'STREET'            => 'STREET',
            'HOUSE'             => 'H_NUM',
            'SV4'               => 'ORDER_SOURCE',      # Источник заказа
            'FLAT'              => 'H_KV',              # Квартира / Офис
            'DISCOUNT'          => 'MYDISCOUNT',        # Скидка
            'BIRTHDATE'         => 'HB',                # День рождения
            'IP'                => 'IP',                # IP
            'NEW_PHONE'         => 'NEW_PHONE_FLAG',    # Флаг нового телефона
            'CHANGE'            => 'ODDMONEY',          # сдача
            'PAYEE'             => 'RECIEVER',          # получатель
            'DELETE_PHONES'     => 'DEL_PHONES',        # Удаление телефонов
            'DELETE_ADDRESS'    => 'DEL_ADDRESS',       # Удаление адресов
            'AUTO_ORDER'        => 'OPER_CALL',         # Флаг оформления без звонка оператора
            'CHOPSTICKS'        => 'FREE_CHOPSTICKS',   # Бесплатные пары палочек
            'BRINGTOTIME'       => 'BRINGTOTIME',       # отложенная доставка
            'DELIVERY_DATE'     => 'DLVDATE',           # дата доставки
            'DELIVERY_HOURS'    => 'DLVH',              # время, часы
            'DELIVERY_MINUTES'  => 'DLVM',              # время, минуты
            'COMMENT_ADDRESS'   => 'ADDRESCOMMENT',     # Примечания к адресу
            'NEW_ADDRESS'       => 'NEW_ADDRESS_FLAG',  # Флаг нового адреса
            'ORDER_SOURCE'      => 'TYPE_PLATFORM',     # Идентификатор приложения
            'APP_VERSION'       => 'APP_VERSION',       # Версия приложения
			'FIRST_ORDER_DISCOUNT' => 'APP_DISCOUNT',   # Идетнификатор первого заказа
        );

        return ($data[$key]) ? $data[$key] : false;
    }

}
?>