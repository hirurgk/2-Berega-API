<?
/**
 * created by y.alekseev
 */
namespace Slim\Lib;

class User extends \Slim\Helper\Lib
{
    /**
     * @method Получение данных пользователя
     * @param $newToken - для переавторизации и создания нового токена
     * @return array
     */
    public static function get( $newToken = false )
    {
        \CModule::IncludeModule('iblock');

        global $USER;

        if (!$USER->GetID()) {
            \Slim\Helper\ErrorHandler::put('NOT_VALID_USERNAME_OR_PASSWORD', 401);
            \Slim\Helper\Answer::json();
            $app->stop();
        }

        $arUser = \CUser::GetByID($USER->GetID())->Fetch();

        $siteId = self::getSiteId();
        
        $serverName = self::getServerURI($siteId);
        
        $discount_types = array(
            0 => "Накопительная скидка",

            1 => array(
                "NAME" => "Я в Игре!",
                "URL" => "http://m.{$serverName}/o-nas/game/?landing_disallowed=Y"
            ),
            2 => array(
                "NAME" => "Голодный офис",
                "URL" => "http://m.{$serverName}/o-nas/office/?landing_disallowed=Y"
            ),
            3 => array(
                "NAME" => "Постоянная скидка",
                "URL" => ""
            ),
            4 => array(
                "NAME" => "Дай 5!",
                "URL" => "http://m.{$serverName}/o-nas/day_five/?landing_disallowed=Y"
            )
        );

        $need_fields = array('ID', 'LOGIN', 'NAME', 'LAST_NAME', 'EMAIL', 'PERSONAL_BIRTHDAY', 'PERSONAL_GENDER', 'PERSONAL_PHONE', 'UF_DISCOUNT_TYPE', 'UF_DISCOUNT', 'UF_PROMO_PRESENT');
        foreach ($arUser as $key => $field):
    		if (!in_array($key, $need_fields) || empty($field) )
                unset($arUser[$key]);
    	endforeach;

        $arUser['ID'] = (int)$arUser['ID'];

        # Скидочная программа
        if ((int)$arUser['UF_DISCOUNT'] > 0) {
            $arUser['DISCOUNT'] = (int)$arUser['UF_DISCOUNT'];
            unset($arUser['UF_DISCOUNT']);

            $arUser['DISCOUNT_TYPE'] = (int)$arUser['UF_DISCOUNT_TYPE'];
            $arUser['DISCOUNT_NAME'] = $discount_types[(int)$arUser['UF_DISCOUNT_TYPE']]['NAME'];
            $arUser['DISCOUNT_URL'] = $discount_types[(int)$arUser['UF_DISCOUNT_TYPE']]['URL'];
            unset($arUser['UF_DISCOUNT_TYPE']);
        }
        unset($arUser['UF_DISCOUNT']);


		# промокод
        $userPromo = \Slim\Lib\Promo::getCode();
        if (strlen($userPromo['UF_CODE']) > 0 ) {
            $arUser['PROMO'] = array(
                'CODE' => $userPromo['UF_CODE'],
                'ACTIVE' => (bool) $userPromo['UF_ACTIVE'],
				'SHARE_TEXT' => $userPromo['SHARE_TEXT']
            );
        }
        
        # купоны
        $arUser['COUPONS'] = \Slim\Lib\Promo::getCoupons();
        
        # текст подарка за промокод
        if (strlen($arUser['UF_PROMO_PRESENT']) > 0) {
			//Получим разовый промокод
			$arCode = \Slim\Lib\Promo::getSinglePromo($arUser['UF_PROMO_PRESENT']);
            
            $arUser['BASKET_TEXT'] = $arCode['PROPERTY_BASKET_TEXT_VALUE'];
		}
        unset($arUser['UF_PROMO_PRESENT']);

        
    	# Дата в UNIX-формате
    	if ($arUser['PERSONAL_BIRTHDAY'])
    		$arUser['PERSONAL_BIRTHDAY'] = strtotime($arUser['PERSONAL_BIRTHDAY']);
    	else
    		unset($arUser['PERSONAL_BIRTHDAY']);


    	# Достанем адреса
    	$dbAddress = \CIBlockElement::GetList(
    			array(),
    			array('IBLOCK_ID'=>self::USER_ADDRESS, 'ACTIVE'=>'Y', 'NAME'=>$USER->GetID(), 'PROPERTY_CITY'=> $siteId),
    			false,
    			false,
    			array('PROPERTY_CITY', 'PROPERTY_STREET_ID', 'PROPERTY_STREET', 'PROPERTY_HOUSE', 'PROPERTY_APARTMENT', 'PROPERTY_GENERAL_PHONES', 'PROPERTY_PREVIEW_TEXT')
    	);

        $arUser['ADDRESS'] = array();
    	while ($arAddress = $dbAddress->Fetch())
        {
            $oneAddress = array();

            if( strlen($arAddress['PROPERTY_CITY_VALUE'])>0)
                $oneAddress['SITE_ID'] = $arAddress['PROPERTY_CITY_VALUE'];

            if( strlen($arAddress['PROPERTY_STREET_ID_VALUE'])>0)
                $oneAddress['STREET_ID'] = $arAddress['PROPERTY_STREET_ID_VALUE'];

            if(strlen($arAddress['PROPERTY_STREET_VALUE'])>0)
                $oneAddress['STREET_NAME'] = $arAddress['PROPERTY_STREET_VALUE'];

            if(strlen($arAddress['PROPERTY_HOUSE_VALUE'])>0)
                $oneAddress['HOUSE'] = $arAddress['PROPERTY_HOUSE_VALUE'];

            if(strlen($arAddress['PROPERTY_APARTMENT_VALUE'])>0)
                $oneAddress['APARTMENT'] = (int)$arAddress['PROPERTY_APARTMENT_VALUE'];

            if(strlen($arAddress['PROPERTY_GENERAL_PHONES_VALUE'])>0) {
                //$oneAddress['PHONES'] = explode(',', $arAddress['PROPERTY_GENERAL_PHONES_VALUE']);
                $arUser['PHONES'] = explode(',', $arAddress['PROPERTY_GENERAL_PHONES_VALUE']);
				
				$arPhones = array();
				
				foreach ($arUser['PHONES'] as $phone) {
					$phone = explode("-",$phone);
					$phone = "+7(".$phone[1].")".$phone[2].$phone[3].$phone[4];
					
					if ($phone != $arUser['PERSONAL_PHONE'])
						$arPhones[] = $phone;
				}
				
				$arUser['PHONES'] = $arPhones;
            }

            $arUser['ADDRESS'][] = $oneAddress;
        }

        if( count($arUser['ADDRESS'])== 0)
            unset($arUser['ADDRESS']);
        
        
        //Переавторизуем пользователя
        if ($newToken) {
        	$USER->Authorize($arUser['ID'], true);
        	$arUser['TOKEN'] = $_SESSION['SESS_AUTH']['SESSION_HASH'];
        }


		//КОСТЫЛЬ С ДОП. ТЕЛЕФОНАМИ (06.06.2016)
		if (!$arUser['PERSONAL_PHONE'])
			unset($arUser['PHONES']);
        

    	return $arUser;
    }


    /**
     * @method Обновление данных пользователя
     * @return array
     */
    public static function update()
    {
        global $USER;
        $app = \Slim\Slim::getInstance();
        
        $newToken = false;

        $arFields = array();
        # обработаем переданные параметры
        foreach( $app->request()->params() as $key => $value )
        {
            # исключаем некоторые параметры
            if ($key == 'GROUP_ID')
                continue;

            if ($key == 'PASSWORD') {
                $arFields['CONFIRM_PASSWORD'] = $value;
                $newToken = true;
            }

            $arFields[$key] = $value;
        }
        
        # Дата ДР из unix в date
        if ($arFields['PERSONAL_BIRTHDAY'])
        	$arFields['PERSONAL_BIRTHDAY'] = date('d.m.Y', $arFields['PERSONAL_BIRTHDAY']);
        elseif ($arFields['PERSONAL_BIRTHDAY'] == null)
            $arFields['PERSONAL_BIRTHDAY'] = '';
        else
        	unset($arFields['PERSONAL_BIRTHDAY']);
        

        $update_user = new \Slim\Helper\BUser;
		if( $update_user->Update($USER->GetID(), $arFields) )
			return self::get($newToken);
        elseif( sizeof($update_user->ERROR_IDS) )
			foreach ($update_user->ERROR_IDS as $error_id)
				\Slim\Helper\ErrorHandler::put($error_id, 404);
        else
        	\Slim\Helper\ErrorHandler::put('NOT_VALID_USERNAME_OR_PASSWORD', 404);
    }


    /**
     * @method Авторизация пользователя
     *
     * @return array
     * @throws \Slim\Exception\Stop
     */
    public static function login()
    {
        $app = \Slim\Slim::getInstance();

        $login      = $app->request()->params('LOGIN');
        $password   = $app->request()->params('PASSWORD');

        # если есть логин и пароль, пытаемся авторизовать
        if( $login != null && $password != null )
        {
            $login      =iconv( 'CP1251', 'UTF-8',  $login);
            $password   =iconv( 'CP1251', 'UTF-8',  $password);

            global $USER;
            $resAuth = $USER->Login($login, $password, 'Y');

            # если автризация не прошла
            if( $resAuth["TYPE"] == 'ERROR' )
            {
                \Slim\Helper\ErrorHandler::put('NOT_VALID_USERNAME_OR_PASSWORD', 401);
                \Slim\Helper\Answer::json();
                $app->stop();
            }
            # вернем в ответе Token
            else
            {
            	$userInfo = self::get();
                return array_merge($userInfo, array('TOKEN' => $_SESSION["SESS_AUTH"]["SESSION_HASH"]));
            }
        }
        else
        {
            \Slim\Helper\ErrorHandler::put('UNKNOWN_USERNAME_OR_PASSWORD', 401);
            \Slim\Helper\Answer::json();
            $app->stop();
        }
    }


    /**
     * Авторизация пользователя через соцсети
     *
     * @return array|bool
     */
	public static function loginSocServiceAuth()
    {
        $app = \Slim\Slim::getInstance();
    	\CModule::IncludeModule('socialservices');

        $socServiceName = $app->request()->params('SOC_SERVICE_NAME');  # идентификатор соц. сети

        $token          = $app->request()->params('TOKEN');         # основной токен для авторизации
        $token_secret   = $app->request()->params('TOKEN_SECRET');  # доп. секретный токен для Twitter
        $refresh_token  = $app->request()->params('REFRESH_TOKEN'); # доп. токен для Однаклассников для обновления основного

        global $USER;
        $USER->logout();

        switch( $socServiceName ) {
            case 'VKontakte':
                $socServiceAuthInfo = \Slim\Helper\SocService::vkontakte($socServiceName, $token);
                break;
            case 'Facebook':
                $socServiceAuthInfo = \Slim\Helper\SocService::facebook($socServiceName, $token);
                break;
            case 'Twitter':
                $socServiceAuthInfo = \Slim\Helper\SocService::twitter($socServiceName, $token, $token_secret);
                break;
            case 'Odnoklassniki':
                $socServiceAuthInfo = \Slim\Helper\SocService::odnoklassniki($socServiceName, $token, $refresh_token);
                break;
        }

        $userInfo = $authInfo = '';

        # если авторизован через соц. сети, авторизуем по ID
        if (\CSocServAuth::AuthorizeUser( $socServiceAuthInfo ))
        {
            $USER->Authorize( $_SESSION['SESS_AUTH']['USER_ID'], true);
            $authInfo = array(
                'LOGIN' => $_SESSION["SESS_AUTH"]['LOGIN'],
                'TOKEN' => $_SESSION["SESS_AUTH"]["SESSION_HASH"]
            );

            $userInfo = self::get();
        }

        return array_merge($userInfo, $authInfo);
    }


    /**
     * @method Регистрирует пользователя
     * @return array
     */
    public static function register()
    {
    	$user = new \Slim\Helper\BUser;
        $app = \Slim\Slim::getInstance();

        $siteId = self::getSiteId();

    	$arFields = array(
	    	'LOGIN'          => $app->request()->params('LOGIN'),
	    	'PASSWORD'       => $app->request()->params('PASSWORD'),
            'EMAIL'          => $app->request()->params('EMAIL'),
            'NAME'           => $app->request()->params('NAME'),
            'LAST_NAME'      => $app->request()->params('LAST_NAME'),
	    	'PERSONAL_PHONE' => $app->request()->params('PERSONAL_PHONE'),
            'GROUP_ID'       => array(self::REGISTERED_USER_GROUP),
            'LID'            => $siteId,
    	);
    	$user_id = $user->Add($arFields);
    	
    	if( $user_id )
        {
            # если авторизация проходит
    		if( $user->Login( $arFields['LOGIN'], $arFields['PASSWORD'], 'Y') ) {
                $token = $_SESSION["SESS_AUTH"]["SESSION_HASH"];

                $userInfo = self::get();

                //Промокод. Если промокод существует в базе, запишем его пользователю и отправим в 1С
                $promoCode = $app->request()->params('PROMO');
                if (strlen($promoCode) > 0) {
                    $promoID = \Slim\Lib\Promo::getIdByCode($promoCode);
                    if ($promoID) {
                        $user->Update($user_id, array('UF_PROMO_INVITE' => $promoID));
                        $userInfo['COUPON_ACCRUED'] = \Slim\Lib\Promo::sendRegisterTo1C($promoCode) ? true : false;
                    }
                }

                return array_merge($userInfo, array(
                    'USER_ID' => $user_id,
                    'LOGIN' => $arFields['LOGIN'],
                    'TOKEN' => $token,
                ));
            }
            else
                \Slim\Helper\ErrorHandler::put('AUTHORIZATION_DATA_IS_NOT_VALID', 401);
    	}
        elseif( sizeof($user->ERROR_IDS) )
    		foreach ($user->ERROR_IDS as $error_id)
				\Slim\Helper\ErrorHandler::put($error_id, 404);
    	else
    		\Slim\Helper\ErrorHandler::put('USER_NOT_CREATE', 404);
    }


    /**
     * @method Функция отправки ключевой фразы для восстановления пароля
     *
     * @return array
     */
    public static function checkword()
    {
        $app = \Slim\Slim::getInstance();

        $login = $app->request->params('LOGIN');
        $email = $app->request->params('EMAIL');

        # Если есть логин или email, пробуем выслать строку
        if ( $login != null || $email != null )
        {
            global $USER;
            $arResult = $USER->SendPassword($login, $email);

    	    if ( $arResult["TYPE"] == 'OK')
			    return array();
    	    else
        		\Slim\Helper\ErrorHandler::put('RECOVERY_NOT_SENT');
        }
        else
        {
            \Slim\Helper\ErrorHandler::put('RECOVERY_NO_DATA');
            return array();
        }
    }

}