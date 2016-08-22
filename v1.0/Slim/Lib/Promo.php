<?
/**
 * Created by y.alekseev
 */

namespace Slim\Lib;

use Bitrix\Highloadblock as HL;
use Bitrix\Main\Entity;

class Promo extends \Slim\Helper\Lib
{
    /**
    * Кол-во попыток сгенерировать уникальный код
    */
    public static $tryGen = 10;
    
    /**
    * Валидация пользовательского промокода
    */
    public static $matchCode = '/^[a-z0-9]{4,10}$/i';
    
    
    //Сущность highload-блока
    private static $entity;
    
    
    /**
    * Возвращает промокод пользователя
    *
    * @return array
    */
    public static function get()
    {
        //Если промокоды выключены...
        $promo_enabled = \C2B::S('PROMO');
        if ($promo_enabled[0] != 'Y')
            return array();
        
        
        //Получим промокод с созданием нового в случае его отсутствия
        $userPromo = self::getCode(true);
        
		return array(
            'CODE' => $userPromo['UF_CODE'],
            'ACTIVE' => (bool) $userPromo['UF_ACTIVE'],
            'SHARE_TEXT' => $userPromo['SHARE_TEXT']
        );
    }
    
    
    /**
    * Устанавливает пользовательский промокод
    *
    * @return array
    */
    public static function set()
    {
        //Если промокоды выключены...
        $promo_enabled = \C2B::S('PROMO');
        if ($promo_enabled[0] != 'Y')
            return array();
        
        
        global $USER;
        
        $userPromo = self::getCode();
        $userCode = self::getApp()->request->params('CODE');
        
        //Если код уже создан, возвращаем ошибку
        if (strlen($userPromo['UF_CODE']) > 0) {
            \Slim\Helper\ErrorHandler::put('PROMO_ALREADY_EXIST', 404);
            return array();
        }
        
        //Валидация промокода
        if (!preg_match(self::$matchCode, $userCode)) {
            \Slim\Helper\ErrorHandler::put('PROMO_NOT_VALID', 404);
            return array();
        }
        
        //Проверим на уникальность. Иначе возвращаем ошибку
        if (!self::isUnique($userCode)) {
            \Slim\Helper\ErrorHandler::put('PROMO_NOT_UNIQUE', 404);
            return array();
        }
        
        //Сохраним промокод
        $HL = self::getEntityHL();
        $date = date('d.m.Y H:i:s');

        $HL::add(array(
            'UF_USER_ID' => $USER->GetID(),
            'UF_CODE' => $userCode,
            'UF_CUSTOM' => 1,
            'UF_DATE_INSERT' => $date,
            'UF_DATE_UPDATE' => $date,
        ));
        
        //Отправляем промокод в 1С
        if (self::sendCodeTo1C($userCode) == 'success')
            $userPromo['UF_ACTIVE'] = 1;
        
        $userPromo['UF_CODE'] = $userCode;
        $userPromo['UF_CUSTOM'] = 1;
        
		return array(
            'CODE' => $userPromo['UF_CODE'],
            'ACTIVE' => (bool) $userPromo['UF_ACTIVE'],
			'SHARE_TEXT' => $userPromo['SHARE_TEXT']
        );
    }
    
    
    /**
    * Выбирает из базы промокод или создаёт новый
    * $newCode - создавать ли новый промокод в случае его отсутствия в базе
    *
    * @return array
    */
    public static function getCode($newCode = false)
    {
        global $USER;

        $HL = self::getEntityHL();
        
        //Вытаскиваем промокод. Если его нет, создаём новый
        $userPromo = $HL::getList(array(
            'filter' => array('UF_USER_ID' => $USER->GetID()),
        ))->Fetch();
        
        if (strlen($userPromo['UF_CODE']) == 0) {   //Создаём новый
			if ($newCode) {
                $userPromo['UF_CODE'] = self::getNewCode($USER->GetID());
                
                //Отправляем промокод в 1С
                if (self::sendCodeTo1C($userPromo['UF_CODE']) == 'success')
                    $userPromo['UF_ACTIVE'] = true;
			} else {
                $userPromo['UF_CODE'] = '';
            }
        }
        
        $promo_bonus = \C2B::S('PROMO_REGISTRATION_BONUS');
        $userPromo['SHARE_TEXT'] = "Подарок от меня! Скидка ".$promo_bonus." рублей к любому заказу в 2 Берега. Воспользуйтесь моим кодом ".$userPromo['UF_CODE']." при регистрации в приложении и получите скидку! http://2b.ru/akcii/darim-300-rubley/";
        
		return $userPromo;
    }
    
    
    /**
    * Возвращает ID промокода
    *
    * @return int
    */
    public static function getIdByCode($code)
    {
        $HL = self::getEntityHL();
        
        //Вытаскиваем промокод. Если его нет, возвращаем 0
        $userPromo = $HL::getList(array(
            'filter' => array('UF_CODE' => $code),
        ))->Fetch();
        
        if (strlen($userPromo['UF_CODE']) == 0)
            return 0;
        else
            return $userPromo['ID'];
    }
    
    
    /**
    * Возвращает список купонов пользователя
    *
    * @return array
    */
    public static function getCoupons()
    {
        global $USER;
        
        //Получим все купоны из 1С
		$arCoupons = json_decode(self::getCouponsFrom1C($USER->GetID()), true);
		
		// Сортируем по сроку годности по возрастанию
		$sortArray = array();
		
		foreach($arCoupons as $coupon){ 
			foreach($coupon as $key=>$value){ 
				if(!isset($sortArray[$key])){ 
					$sortArray[$key] = array(); 
				} 
				$sortArray[$key][] = $value; 
			} 
		} 

		$orderby = "DATE";

		array_multisort($sortArray[$orderby],SORT_ASC,$arCoupons); 
        
        //Приведём к виду для МП
        $coupons = array();
        foreach ($arCoupons as $key=>$arCoupon) {
            $coupons[] = array(
                'ID' => (string) $key,
                'VALUE' => (string) $arCoupon['VALUE'],
                'DATE' => $arCoupon['DATE'] < 0 ? '' : date('d.m.Y H:i:s', $arCoupon['DATE'])
            );
        }

		return $coupons;
    }

    
	/**
	 * Создаёт новый уникальный промокод и записывает его в базу
     *
	 * @return string
	 */
	private static function getNewCode($userID)
	{
        $HL = self::getEntityHL();
        
        //Генерируем новый уникальный(!) промокод
        $genSuccess = false;
        for ($i = 0; $i < self::$tryGen; $i++) {
            $newCode = self::generateNewCode();
            if (self::isUnique($newCode)) {
                $genSuccess = true;
                break;
            }
        }
        //Если промокод по каким-то причинам не сгенерировался уникальный, выдаём ошибку
        if (!$genSuccess) {
            \Slim\Helper\ErrorHandler::put('PROMO_NOT_GEN', 404);
            return array();
        }
        
        $date = date('d.m.Y H:i:s');
        $result = $HL::add(array(
            'UF_USER_ID' => $userID,
            'UF_CODE' => $newCode,
            'UF_DATE_INSERT' => $date,
            'UF_DATE_UPDATE' => $date,
            'UF_ACTIVE' => 0,   //по умолчанию неактивен
            'UF_CUSTOM' => 0
        ));
        
        return $newCode;
	}
    
    
    /**
    * Генерирует новый промокод
    *
    * @return string
    */
    private static function generateNewCode()
    {
        $HL = self::getEntityHL();
        
        //Вытащим последний сгенерированный промокод
        $lastPromo = $HL::getList(array(
            'select' => array('UF_CODE'),
            'order' => array('ID' => 'DESC'),
            'filter' => array('UF_CUSTOM' => 0),
        ))->Fetch();
        $lastCode = $lastPromo['UF_CODE'] ? $lastPromo['UF_CODE'] : '0000';
        
        # Отсекаем 3 последних случайных символа,
        # переводим в 10-ю систему,
        # увеличиваем на 1
        # и обратно в 16-ю систему с рандомными символами в конце
        $newCode = hexdec(substr($lastCode, 0, -3));
        $newCode++;
        $newCode = dechex($newCode)
                   .dechex(rand(0, 15))
                   .dechex(rand(0, 15))
                   .dechex(rand(0, 15));
        
        return $newCode;
    }
    
    
    /**
    * Проверяет промокод на уникальность
    *
    * @return string
    */
    public static function isUnique($code)
    {
        global $USER;
        
        $HL = self::getEntityHL();
        
        $userPromo = $HL::getList(array(
            'filter' => array('UF_CODE' => $code),
        ))->Fetch();
        
        if ($userPromo['ID'] && $USER->GetID() != $userPromo['UF_USER_ID'])
            return false;
        else
            return true;
    }
    
    
    /**
    * Отправляет новый промокод в 1С
    * $id - ID промокода в таблице
    * $code - промокод
    *
    * @return string
    */
    public static function sendCodeTo1C($code)
    {
        global $USER;
        $userData = \CUser::GetByID($USER->GetID())->Fetch();
        
        $param = new \stdClass();
        $param->ID           = $userData['ID'];
        $param->PromoCode    = $code;
        $param->Phone        = $userData['PERSONAL_PHONE'];
        $param->Name         = strlen($userData['LAST_NAME']) > 0 ? $userData['LAST_NAME'] . ' ' . $userData['NAME'] : $userData['NAME'];
        $param->Main         = 1;   //1 - создание нового промокода
        
        ini_set("soap.wsdl_cache_enabled", "0");
		$client = new \SoapClient(self::SOAP_1C_URL, array('login' => self::SOAP_1C_LOGIN, 'password' => self::SOAP_1C_PASSWORD));
        $response = $client->promocoderegistration($param);
        
        # $result->result: результат (success/operator)
        $result = $response->return;
        
        //Активируем промокод, если 1С ответила успехом
        if ($result->result == 'success') {
            $id = self::getIdByCode($code);
            
            $HL = self::getEntityHL();
            $HL::update($id, array(
                'UF_ACTIVE' => 1
            ));
        }
        
        return $result->result;
    }
    
    
    /**
    * Метод для 1С: Активирует промокод пользователя на запрос от 1С
    *
    * @return array
    */
    public static function confirmCodeFrom1C()
    {
        global $USER;
        
        $userID = self::getApp()->request->params('id_user');
        $hash = self::getApp()->request->params('hash');

        //Если хеш не совпадает, останавливаем приложение
        if (strtolower(md5($userID.'2b_secret_promo')) != strtolower($hash)) {
            echo 'failed';
            die();
        }
        
        $USER->Authorize($userID);
        
        $userPromo = self::getCode();
        
        //Активируем промокод, если он ещё не активирован
        if (!$userPromo['ACTIVE']) {
            $HL = self::getEntityHL();
            $result = $HL::update($userPromo['ID'], array(
                'UF_ACTIVE' => 1
            ));
            
            $userPromo['UF_ACTIVE'] = 1;
        }
        
        echo 'success';
    }
    
    
    /**
    * Метод для 1С: Очищает активный подарок клиента на запрос от 1С
    *
    * @return array
    */
    public static function clearPromoPresentFrom1C()
    {
        global $USER;
        
        $userID = self::getApp()->request->params('id_user');
        $hash = self::getApp()->request->params('hash');

        //Если хеш не совпадает, останавливаем приложение
        if (strtolower(md5($userID.'2b_present_promo')) != strtolower($hash)) {
            echo 'failed';
            die();
        }
        
        $user = new \CUser;
        $user->Update($userID, array('UF_PROMO_PRESENT' => ''));
        
        echo 'success';
    }
    
    
    /**
    * Отправляет регистрацию "друга" по промокоду в 1С
    * $code - промокод
    *
    * @return string
    */
    public static function sendRegisterTo1C($code)
    {
        global $USER;
        $userData = \CUser::GetByID($USER->GetID())->Fetch();
        
        $param = new \stdClass();
        $param->ID           = $userData['ID'];
        $param->PromoCode    = $code;
        $param->Phone        = $userData['PERSONAL_PHONE'];
        $param->Name         = strlen($userData['LAST_NAME']) > 0 ? $userData['LAST_NAME'] . ' ' . $userData['NAME'] : $userData['NAME'];
        $param->Main         = 0;   //0 - регистрация по существующему промокоду
        
        ini_set("soap.wsdl_cache_enabled", "0");
		$client = new \SoapClient(self::SOAP_1C_URL, array('login' => self::SOAP_1C_LOGIN, 'password' => self::SOAP_1C_PASSWORD));
        $response = $client->promocoderegistration($param);
        
        # $result->result: результат (success/operator)
        # $result->accrued: начислен купон или нет (1/0)
        # $result->id: ID купона
        # $result->value: номинал купона
        # $result->date: дата действия купона (0001-01-01T00:00:00)
        $result = $response->return;

        //Зачисляем купон, если 1С его вернула
        /*if ($result->result == 'success') {
            $newCoupon = array('VALUE' => (string) $result->value, 'DATE' => strtotime($result->date));
            $arCoupons = json_decode($arUser['UF_PROMO_COUPONS'], true);
            $arCoupons[$result->id] = $newCoupon;

            $user = new \CUser;
            $user->Update($userData['ID'], array('UF_PROMO_COUPONS' => json_encode($arCoupons)));
        }*/

        return $result->result == 'success' ? true : false;
    }
    
    
    /**
    * Создаёт купон в 1С
    * $value - номинал купона в рублях
    * $date - дата действия в unix-формате
    *
    * @return string
    */
    public static function createCouponIn1C($value, $date)
    {
        global $USER;
        
        $param = new \stdClass();
        $param->ID = $USER->GetID();
        $param->Sum = $value;
        if ($date)
            $param->Date = date('Y-m-d\TH:i:s', $date);
        
        ini_set("soap.wsdl_cache_enabled", "0");
		$client = new \SoapClient(self::SOAP_1C_URL, array('login' => self::SOAP_1C_LOGIN, 'password' => self::SOAP_1C_PASSWORD));
        $response = $client->addBonusCoupon($param);
        
        return $response->return;
    }
    
    
    /**
    * Принимает купоны по запросу ОТ 1С (Не используется)
    *
    */
    /*public static function setCouponsFrom1C()
    {
        $userID = self::getApp()->request->params('id_user');
        $hash = self::getApp()->request->params('hash');
        
        //Если хеш не совпадает, останавливаем приложение
        if (strtolower(md5($userID.'2b_secret_coupon')) != strtolower($hash)) {
            echo 'failed';
            die();
        }
        
        $XML = self::getApp()->request->getBody();

        //Сохраним купоны пользователю
        self::saveCoupons($userID, $XML);
    }*/
    
    
    /**
    * Получает купоны пользователя по запросу В 1С
    * 
    * @return array
    */
    public static function getCouponsFrom1C($userID)
    {
        $param = new \stdClass();
        $param->ID = $userID;

        ini_set("soap.wsdl_cache_enabled", "0");
        $client = new \SoapClient(self::SOAP_1C_URL, array('login' => self::SOAP_1C_LOGIN, 'password' => self::SOAP_1C_PASSWORD));
        $response = $client->getBonusCoupons($param);
        
        //Сохраним купоны пользователю
        return self::saveCouponsFromXML($userID, $response->return);
    }
    
    
    /**
    * Сохраняет купоны пользователю из XML
    *
    */
    private static function saveCouponsFromXML($userID, $XML)
    {
        $data = simplexml_load_string($XML);
        
        $coupons = array();
        
        foreach ($data as $coupon) {
            $coupons[(string) $coupon->Ид] = array(
                'VALUE' => (string) $coupon->Номинал,
                'DATE' => (string) strtotime($coupon->ДатаДействия)
            );
        }
        
        $user = new \CUser;
        $JC = json_encode($coupons);
        $user->Update($userID, array('UF_PROMO_COUPONS' => $JC));
        
        return $JC;
    }
    
    
    /**
    * Возвращает данные разового купона по его имени
    * 
    * @return array
    */
    public static function getSinglePromo($name)
    {
        if (strlen($name) > 0) {
            $pFilter    = array("IBLOCK_ID" => self::PROMO__IBLOCK__ID, 'ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y', 'NAME' => $name);
            $pSelect    = array('ID', 'IBLOCK_ID', 'NAME', 'ACTIVE', 'PROPERTY_COUPON_VALUE', 'PROPERTY_LANCH_START', 'PROPERTY_COUPON_DATE', 'PROPERTY_BASKET_TEXT');
            // Нужно добавить таргетинг по регионам

            $dbCodes = \CIBlockElement::GetList(array(), $pFilter, false, false, $pSelect);
            $arCode = $dbCodes->Fetch();
            
            if (is_array($arCode))
                return $arCode;
        }
        
        return false;
    }
    
    
    /**
    * Активирует разовый промокод
    *
    * @return array
    */
    public static function activateSingleCode()
    {
        //Если промокоды выключены...
        $promo_enabled = \C2B::S('PROMO');
        if ($promo_enabled[0] != 'Y')
            return array();
        
		\CModule::IncludeModule('iblock');
		
        $arErrors = array();
		$siteId	= self::getSiteId();
		$codeText = "";

		global $USER;
    	$arUser = \CUser::GetByID($USER->GetID())->Fetch();

        //Получим промокод, который нужно активировать
        $userCode = self::getApp()->request->params('code');

		// Ищем промокод в инфоблоке
		if (!empty($userCode)) {
            //Получим разовый промокод
			$arCode = self::getSinglePromo($userCode);
			
			//Если кода не существует или он неактивен
			if (empty($arCode) || count($arCode) == 0) {
				\Slim\Helper\ErrorHandler::put('PROMO_NOT_FOUND', 404);
				return array();
			}
			
			//Если код уже активирован, возвращаем ошибку
			if (in_array($arCode['NAME'], explode(",",$arUser['UF_ACTIVATED_CODES']))) {
				\Slim\Helper\ErrorHandler::put('PROMO_ALREADY_USED', 404);
				return array();
			}

			if (!empty($arCode['PROPERTY_COUPON_VALUE_VALUE'])) {       // Если код на добавление купона
                //Создадим купон в 1С
                self::createCouponIn1C($arCode['PROPERTY_COUPON_VALUE_VALUE'], strtotime($arCode['PROPERTY_COUPON_DATE_VALUE']));
                
                $user = new \CUser;
				$activated = (!empty($arUser['UF_ACTIVATED_CODES'])) ? $arUser['UF_ACTIVATED_CODES'].",".$arCode['NAME'] : $arCode['NAME'];
				$user->Update($arUser['ID'], array('UF_ACTIVATED_CODES' => $activated));
                
				$codeText = "Вы получили купон на скидку!";
			} else {        // Если код на подарок
				$user = new \CUser;
				$activated = (!empty($arUser['UF_ACTIVATED_CODES'])) ? $arUser['UF_ACTIVATED_CODES'].",".$arCode['NAME'] : $arCode['NAME'];
				$user->Update($arUser['ID'], array('UF_PROMO_PRESENT' => $arCode['NAME'], 'UF_ACTIVATED_CODES' => $activated));
				
				$codeText = $arCode['PROPERTY_BASKET_TEXT_VALUE'];
			}
		}
        
		return array(
            'TEXT' => $codeText
        );
    }
    
    
    /**
    * Возвращает сущность highload-блока
    *
    * @return object
    */
    private static function getEntityHL()
    {
        if (!self::$entity) {
            \CModule::IncludeModule("highloadblock");
            
            $hlblock = HL\HighloadBlockTable::getById(self::HIGHLOADBLOCK_PROMO)->fetch();
            $entity = HL\HighloadBlockTable::compileEntity($hlblock);
            $entity_data_class = $entity->getDataClass();
            
            self::$entity = $entity_data_class;
        }
        
        return self::$entity;
    }

}