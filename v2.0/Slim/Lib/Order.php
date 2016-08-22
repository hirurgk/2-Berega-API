<?
/**
 * Created by y.alekseev
 */

namespace Slim\Lib;

class Order extends \Slim\Helper\Lib
{
    /**
     * Время в минутах, через которое будет отправлен пуш с просьбой оценить заказ без учёта времени на доставку
     */
    const TIME_TO_PUSH_AFTER_DELIVERY = 15;
    
    
    /**
     * Получение списка заказов пользователя из HL-блока
     *
     * @return array
     */
    public static function getOrderList()
    {
        global $USER;
        \CModule::IncludeModule('iblock');
        
        $timestamp = self::getTimestamp();
        $orders = \Order::getHistory($USER->GetID(), $timestamp);
        
        
        //Достанем все нужные разделы товаров
        array_unique($sectionIds);
        $sections = array();
        $dbSections = \CIBlockSection::GetList(array(), array('ID' => $sectionIds));
        while ($arSection = $dbSections->Fetch()) {
            $sections[$arSection['ID']] = $arSection;
        }
        
        
        //Отформатируем структуру данных для приложения
        foreach ($orders as &$order) {
            $arOrder = array();
            
            //Внутренний ID
            $arOrder['ID'] = (int) $order['UF_ORDER_ID_1C'];
            
            //ID интернет-заказа для отображения пользователю
            if ($order['UF_ORDER_ID'])
                $arOrder['ID_FOR_USER'] = $order['UF_ORDER_ID'];
            
            $arOrder['DATE_INSERT'] = strtotime((string) $order['UF_DATE']);
            
            $arOrder['BUYER'] = $order['UF_BUYER'];
            $arOrder['ADDRESS'] = $order['UF_ADDRESS'];
            $arOrder['PHONE'] = $order['UF_PHONE'];
            $arOrder['PAYSYSTEM'] = $order['UF_PAYSYSTEM'];
            
            //TODO(y.alekseev): переделать на статус из нашей базы
            $arOrder['STATUS'] = 'Принят';
            
            $arOrder['PRICE'] = (int) $order['UF_SUM'];
            
            $arOrder['CAN_RATE'] = \Order::isDateForRate($arOrder['DATE_INSERT']);
            
            if ($order['UF_RATING']) {
                $arOrder['RATING'] = (int) $order['UF_RATING'];
                $arOrder['CAN_RATE'] = false;
            }
            
            //Товары
            foreach ($order['UF_GOODS'] as $good) {
                $product = array();
                
                if ($good['ID']) {
                    $product['PRODUCT_ID'] = (int) $good['ID'];
                    $product['SECTION_NAME'] = $sections[$good['IBLOCK_SECTION_ID']]['NAME'];
                }
                
                $product['NAME'] = $good['NAME'];
                $product['QUANTITY'] = $good['QUANTITY'];
                
                $toppings = count($good['TOPPINGS']);
                if ($toppings)
                    $product['TOPPINGS'] = $toppings;
                
                $arOrder['PRODUCTS'][] = $product;
            }
            
            $order = $arOrder;
        }
        
        return $orders;
    }
    
    /**
     * Оценка заказа
     *
     * $id - внутренний ID заказа (от 1С)
     * $rating - оценка от 1 до 5
     * $message - комментарий к оценке
     *
     * @return array
     */
    public static function rate()
    {
        $id = self::getApp()->request->params('ORDER_ID');
        $rating = self::getApp()->request->params('RATING');
        $message = htmlspecialchars(self::getApp()->request->params('COMMENT'));

        if (preg_match("/^[0-9]+$/", $id) && preg_match("/^[1-5]{1}$/", $rating))
            \Order::rate($id, $rating, $message);
        
        return array();
    }
    
    
    /**
     * Повтор заказа
     *
     * @param $id - внутренний ID заказа (от 1С)
     *
     * @return array
     */
    public static function repeat($id)
    {
        global $USER;
        
        $siteId     = self::getSiteId();
		$groupId   = self::getCatalogGroupId();
        
        $basket = \Order::getBasketForRepeat($USER->GetID(), $id, $groupId, $siteId);
        
        if (!$basket) {
            \Slim\Helper\ErrorHandler::put('ORDER_REPEAT_ERROR');
            return array();
        }
        
        
        //Составим ответ приложению
        $productIDs = array();
        
        $arResult = array();
        foreach ($basket['ITEMS'] as $item) {
            # Соберём ID товаров
            $productIDs[] = (int) $item['PRODUCT_ID'];
            
            
            $product = array(
                'ID' => (int) $item['PRODUCT_ID'],
                'QUANTITY' => (int) $item['QUANTITY'],
            );
            
            //соус
            foreach ($item['PROPS'] as $prop) {
                if ($prop['CODE'] != 'SOUS')
                    continue;
                
                $product['SAUCE'] = $prop['VALUE'];
                
                break;
            }
            
            //топпинги
            foreach ($item['PROPS'] as $prop) {
                if ($prop['CODE'] != 'NABORLIST_PIZZA')
                    continue;
                
                $toppings = explode(';', $prop['VALUE']);
                
                foreach ($toppings as $topping) {
                    $topping = explode(',', $topping);
                    
                    $product['TOPPINGS'][] = array(
                        'ID' => (int) $topping[0],
                        'QUANTITY' => (int) $topping[1],
                    );
                }
                
                break;
            }
            
            
            $arResult['PRODUCTS'][] = $product;
        }
        
        //Вернём полную инфу о возвращаемых товарах
        $productsOriginal = \Slim\Lib\Catalog::getGoodsList(false, $productIDs, true);
        $arResult['PRODUCTS_INFO'] = $productsOriginal;
        
        
        $arResult['CHANGED'] = $basket['CHANGED'] == 'Y' ? true : false;
        $arResult['CHANGED_TOPPINGS'] = $basket['CHANGED_TOPPINGS'] == 'Y' ? true : false;
        
        if ($arResult['CHANGED'] || $arResult['CHANGED_TOPPINGS'])
            $arResult['CHANGED_MESSAGE'] = "Состав заказа был изменён";
        
        return array($arResult);
    }
    
    
    /**
     * Получение статуса оплаты заказа
     *
     * $id - ID заказа
     *
     * @return array
     */
    public static function getPaymentStatus($id)
    {
        $status = \Slim\Helper\PaymentSystem::getPaymentStatusPscb($id);
        
        return $status;
    }
    
    
    /**
     * Смена статуса заказа по запросу из 1С
     * + отправка отложенного пуша в приложение с просьбой оценить заказ
     *
     * @return array
     */
    public static function changeStatusFrom1C()
    {
        \CModule::IncludeModule('sale');
        
        $timeToDelivery = self::getApp()->request->params('delivery_time');
        $orderID = self::getApp()->request->params('order_id');
        $status = self::getApp()->request->params('status');
        $hash = self::getApp()->request->params('hash');

        //Если хеш не совпадает, останавливаем приложение
        if (strtolower(md5($timeToDelivery.$orderID.$status.'2b_secret_order_status')) != strtolower($hash)) {
            echo 'failed';
            die();
        }
        
        
        //Достанем токен приложения из заказа
        $token = '';
        $dbProp = \CSaleOrderPropsValue::GetList(array(), array("ORDER_ID" => $orderID));
        while ($arProp = $dbProp->Fetch())
            if ($arProp['CODE'] == 'TOKEN_APP')
                $token = $arProp['VALUE'];
        
        if ($token) {
            //Время, через которое будет отправлен пуш с просьбой оценки в секундах
            $timeToPush = ($timeToDelivery + self::TIME_TO_PUSH_AFTER_DELIVERY) * 60;
            
            //Отправка пуша со статусом "Передан курьеру"
            \Slim\Helper\OneSignal::sendPush('Ваш заказ передан курьеру', array($token), array('ORDER_ID' => $orderID, 'ORDER_TIMER' => $timeToDelivery));
            
            //Отправка пуша с просьбой оценить заказ
            \Slim\Helper\OneSignal::sendPush('Оцените, пожалуйста, заказ!', array($token), array("link" => "2berega://order/". $orderID), time()+$timeToPush, 0);
        }
        
        
        echo 'ok';
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
        $arBasketItems = \Order::setItemsForBasket($data['PRODUCTS'], $group_id, $siteId);


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
		$FUser = self::getFUserID();
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

		
		# Добавляем подарок по акционной механике
		if(is_array($data['PROMOTIONAL'])){
			$arFields['PROMOTIONAL_ID']      = $data['PROMOTIONAL']['PROMOTIONAL_ID'];
			$arFields['PROMOTIONAL_GIFT_ID'] = $data['PROMOTIONAL']['PROMOTIONAL_GIFT_ID'];
		}
        
		
        $ps_id = PAYSYSTEM_MOBILE;
        if ($paySystemCode == 'online_dengi')
            $ps_id = PAYSYSTEM_MOBILE;
        elseif ($paySystemCode == 'pskb')
            $ps_id = PAYSYSTEM_MOBILE_2;

        $arFields['PAY_SYSTEM_ID']      = ($data['PAY_SYSTEM_ID'] == PAYSYSTEM_MOBILE) ? $ps_id : $data['PAY_SYSTEM_ID'];
        

        # Создадим заказ
        $order_id = \CSaleOrder::Add($arFields);
        $timeOrderCreate = time();

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
        \CSaleBasket::OrderBasket($order_id, $FUser, $siteId);


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
        
        
        # Установим Токен приложения
        $token = self::getAppID();
        if (strlen($token) > 0) {
            \CSaleOrderPropsValue::Add(
                array(
                    "ORDER_ID"          => $order_id,
                    "ORDER_PROPS_ID"    => $propsValue['TOKEN_APP']['ID'],
                    "NAME"              => "Токен мобильного приложения",
                    "CODE"              => "TOKEN_APP",
                    "VALUE"             => $token
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
			

        $orderData = array(
            'ID_FOR_USER' => (int)$order_id,
            'DATE_INSERT' => $timeOrderCreate,
            'PRICE' => $arResult['ORDER_PRICE'],
        );
        
        # Если выбрана оплата картой, сформируем URL для получения формы
        # TODO(y.alekseev): 8 - захардкодено в моб. приложении (оплата картой)
        if ($data['PAY_SYSTEM_ID'] == 8) {
            $payment_url = \Slim\Helper\PaymentSystem::getUrl($order_id, $arResult['ORDER_PRICE'], $paySystemCode);
            
            $orderData['PAYMENT_URL'] = $payment_url;
            $orderData['SUCCESS_PAYMENT'] = 'http://2-berega.ru/payment/success';
            $orderData['FAILURE_PAYMENT'] = 'http://2-berega.ru/payment/failure';
        }
        
        return $orderData;
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