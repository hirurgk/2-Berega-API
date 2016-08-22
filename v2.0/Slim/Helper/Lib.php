<?
/**
 * Created by y.alekseev
 */

namespace Slim\Helper;

/**
 * Class Lib
 * @package Slim\Helper
 */
class Lib
{
    /**
     * Параметры соединения с 1С
     */
	//const SOAP_1C_URL = "http://81.23.120.202:8070/1test/ws/ws1.1cws?wsdl";
	//const SOAP_1C_LOGIN = "Service";
	//const SOAP_1C_PASSWORD = "test";
	const SOAP_1C_URL = "http://81.23.120.202:8070/1cexpress/ws/ws1.1cws?wsdl";
	const SOAP_1C_LOGIN = "Service";
	const SOAP_1C_PASSWORD = "Hnd65ruf";


    /**
     * Id инфоблока с 'Каталог с продукцией'
     * @type int
     */
    const PRODUCTS = 1;


    /**
     * Id инфоблока 'Дополнительные ингредиенты'
     * @type int
     */
    const INGREDIENTS = 7;


    /**
     * Id инфоблока 'Специальные предложения'
     * @type int
     */
    const PROMOTIONS = 10;


    /**
     * Id инфоблока 'Телефоны клиентов'
     * @type int
     */
    const PHONES = 13;


    /**
     * Id инфоблока 'Адреса клиентов'
     * @type int
     */
    const USER_ADDRESS = 8;


    /**
     * Id инфоблока 'Фильтры'
     * @type int
     */
    const IBLOCK_FILTERS_ID = 15;


    /**
     * Id инфоблока 'Баннерв для моб. приложения'
     * @var int
     */
    const BANNER_MOBILE = 16;


    /**
     * Id свойства таргентинка в инфоблоке с баннерами для моб. приложения
     * @var int
     */
    const BANNER_TARGETING = 106;


    /**
     * Id раздела со всеми ингредиентами
     * @type int
     */
    const SECTION_ALL_INGREDIENTS = 19;


    /**
     * Тип платильшика используемого при оформлении заказа
     * @type int
     */
    const PERSON_TYPE_ID = 1;


    /**
     * ID Пользователя "Гость"
     * @var int
     */
    const USER_GUEST = 143050;
	
	
	/**
     * ID группы зарегистрированных пользователей
     * @var int
     */
    const REGISTERED_USER_GROUP = 3;


	/**
     * ID инфоблока с алертами
     * @var int
     */
    const IBLOCK_ALERTS_ID = 6;
    
    
    /**
     * ID highload-блока с промокодами
     * @var int
     */
    const HIGHLOADBLOCK_PROMO = 1;
    
	
	/**
     * ID инфоблока с моментальными промокодами
     * @var int
     */
    const PROMO__IBLOCK__ID = 21;
    
    
	/**
     * ID инфоблока контроля качества
     * @var int
     */
    const IBLOCK_FEEDBACK_ID = 35;


    private static $app;
    private static $siteId;

    /**
     * Возвращает объект Slim
     * @return \Slim\Slim
     */
    public static function getApp()
    {
        if (!is_object($app)) self::$app = \Slim\Slim::getInstance();
        return self::$app;
    }


    /**
     * Возвращает ID сайта(региона) из запроса
     * @return string
     */
    public static function getSiteId()
    {
        if (self::$siteId)
            return self::$siteId;
        else
            return self::getApp()->request->headers('Site-id');
    }


    /**
     * Принудительно устанавливает ID сайта
     * @param $siteId
     */
    public static function setSiteId($siteId)
    {
        self::$siteId = $siteId;
    }


    /**
     * Получение id групп цен
     * @return mixed
     */
    public static function getCatalogGroupId()
    {
        \CModule::IncludeModule('catalog');
        $CATALOG_GROUP = \CCatalogGroup::GetList(array(), array('XML_ID'=>self::getSiteId()))->Fetch();

        return $CATALOG_GROUP['ID'];
    }


    /**
     * Возвращает timestamp
     * @return array|bool|mixed|null
     */
    public static function getTimestamp()
    {
        $timestamp = \Slim\Slim::getInstance()->request->params('timestamp');
        return ( $timestamp !== null ) ? $timestamp : false;
    }


    /**
     * Формирование массива с ошибками от битрикса
     * @param array $err
     * @return array
     */
    public static function arBxError( $err=array() )
    {
        return array_filter(explode("<br>", $err), function($element) {
            return !empty($element);
        });
    }


    /**
     * Получение IP пользователя
     * @return string
     */
    public static function getUserIP()
    {
        return self::getApp()->request->getIp();
    }
    
    
    /**
     * Получение uri сервера с регионом по ID региона
     * @return string
     */
    public static function getServerURI($siteId)
    {
        $site = \CSite::GetList($by = "sort", $order = "desc", Array("ID" => $siteId))->Fetch();
        
        return $site['SERVER_NAME'];
    }


    /**
     * Возвращает массив ID'шников из адресной строки /?ids=1,2,3,4,5
     * @return array
     */
    public static function getIds()
    {
        $ids = self::getApp()->request->params('ids');
        $ids = explode(',', $ids);

        $true_ids = array();

        foreach ($ids as $id) {
            if (is_numeric(trim($id)))
                $true_ids[] = $id;
        }

        return $true_ids;
    }


    /**
     * Преобразование времени формата 01:15:00 в минуты
     * @param string $time
     *
     * @return int
     */
    public static function convertInMinutes( $time )
    {
        $arTime = explode(':', $time);
        $min = ( (int)$arTime[0]*60 ) + (int)$arTime[1];

        return $min;
    }


    /**     
     * Получим данные по активной платежной системе
	 *
     * @return array
     */
    public static function getPaymentSystem($paySystemId)
    {
        \CModule::IncludeModule("sale");
        $arPaySystem = \CSalePaySystem::GetByID($paySystemId, self::PERSON_TYPE_ID);

        # вернем массив с настройки ПС
        return unserialize( $arPaySystem['PSA_PARAMS'] );
    }


    /**
     * Метод возвращает необходимую временную зону по SITE_ID
     * @param string $site_id
     *
     * @return mixed
     */
    public static function getTimeZone($site_id = 's1') {
        $timeZone = array(
            's1' => 'Europe/Moscow', // санкт-петербург
            'kz' => 'Europe/Moscow', // казань
            'rs' => 'Europe/Moscow', // ростов

            'ji' => 'Asia/Yekaterinburg', // уфа
            'ek' => 'Asia/Yekaterinburg', // екатеринбург
            'ch' => 'Asia/Yekaterinburg', // челябинск

            'sa' => 'Europe/Samara', // самара

            'kg' => 'Europe/Kaliningrad' // калининград
        );
        
        return $timeZone[$site_id];
    }
    
    
    /**
     * Метод возвращает ID OneSignal, который присылает приложение в заголовках
     *
     * @return string
     */
    public static function getAppID() {
        return self::getApp()->request->headers('Token-App');
    }
    
    
    /**
     * Метод создаёт нового покупателя в базе на основе токена приложения, если его ещё не существует
     *
     * @return ID
     */
    public static function getFUserID() {
        global $DB, $USER;
        
        \CModule::IncludeModule('sale');
        
        $token = self::getAppID();
        
        //Если гость, выдаём user_id на основе токена приложения
        if ($USER->GetID() == self::USER_GUEST && $token) {
            //Если уже существует такой, отдадим его fuser_id
            if ($saleUser = \CSaleUser::GetList(array('CODE' => $token))) {
                return $saleUser['ID'];
            } else {        //Иначе создадим нового
                $arFields = array(
                    "=DATE_INSERT" => $DB->GetNowFunction(),
                    "=DATE_UPDATE" => $DB->GetNowFunction(),
                    "USER_ID" => self::USER_GUEST,
                    "CODE" => $token,
                );

                $ID = \CSaleUser::_Add($arFields);
                $ID = IntVal($ID);
                
                $_SESSION["SALE_USER_ID"] = $ID;

                return $ID;
            }
        } else {        //Если не гость, отдаём обычный fuser_id
            return \CSaleBasket::GetBasketUserID(true);
        }
    }

}