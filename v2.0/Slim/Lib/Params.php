<?
/**
 * Created by y.alekseev
 */

namespace Slim\Lib;

class Params extends \Slim\Helper\Lib
{
    /**
     * Возвращает список настроек по ID сайта(региона)
     * @return array
     */
	public static function getList()
	{
		$params = \C2B::GetList(self::getSiteId());
		$params_filtered = array();

        //Ставим промокодам по умолчанию false (чтобы в приложение при отключенной настройке уходил именно false)
		$params_filtered['PROMO']['VALUE'] = false;

		foreach ($params as $key => $param)
        {
            $default = (empty($param['DEFAULT'])) ? '' : $param['DEFAULT'];
			$value = $param['VALUE'];

            // Для PROMO меням текстовый Y на true
			if ($param['CONST'] == "PROMO") {
				$value = (empty($param['VALUE'])) ? false : true;
			}

			// Для ORDER_WITHOUT_CALL_NOAUTH меням текстовый Y на true
			if ($param['CONST'] == "ORDER_WITHOUT_CALL_NOAUTH") {
				$value = (empty($param['VALUE'])) ? false : true;
			}
            
            // Для ORDER_POLLING_INTERVAL и ORDER_POLLING_TIMEOUT меням тип на целое число
			if ($param['CONST'] == "ORDER_POLLING_INTERVAL" || $param['CONST'] == "ORDER_POLLING_TIMEOUT") {
				$value = (int) $value;
                $default = (int) $default;
			}

            $params_filtered[$param['CONST']] = array(
                'VALUE'     => $value,
                'DEFAULT'   => $default,
            );
		}

        # добавим параметры соц сетей
        $params_filtered['SOCIAL_SETTINGS'] = self::getSocialNetworksParams();

        # добавим параметры платёжных систем
		$params_filtered['PAY_SYSTEMS'] = self::getPaySystems();

        return $params_filtered;

	}


	/**
	 * Получение списка активных платёжных систем
	 */
	private static function getPaySystems()
	{
		\CModule::IncludeModule('sale');

	    //Массив с ID платёжных систем, которые могут использоваться в моб. приложении
		$mobilePS = array(
	            'CASH' => array(
	                    'ID' => 1,
	            ),
	            'TERMINAL' => array(
	                    'ID' => 2,
	            ),
	            'CARD' => array(
	                    'ID' => 8,
	            ),
	            'QIWI' => array(
	                    'ID' => 15,
	                    'ACTIVE' => false      //Отключим принудительно
	            ),
	            'WEBMONEY' => array(
	                    'ID' => 14,
	                    'ACTIVE' => false      //Отключим принудительно
	            ),
	            'YANDEXMONEY' => array(
	                    'ID' => 16,
	                    'ACTIVE' => false      //Отключим принудительно
	            ),
	    );

	    
	   //Пройдёмся по массиву и проставим активность
		$ps_code_id = array();
		foreach ($mobilePS as $key => $ps) {
		    $ps_code_id[$ps['ID']] = $key;
		}
		$dbPS = \CSalePaySystem::GetList(array(), array('ID' => array_keys($ps_code_id)), false, false, array('ID', 'ACTIVE'));
		while ($arPS = $dbPS->Fetch()) {
			if (!isset($mobilePS[$ps_code_id[$arPS['ID']]]['ACTIVE']))
			   $mobilePS[$ps_code_id[$arPS['ID']]]['ACTIVE'] = $arPS['ACTIVE'] == 'Y' ? true : false;
		}

		return $mobilePS;
	}


    /* получение ID соц. приложений для авторизации */
    private static function getSocialNetworksParams()
    {
        \CModule::IncludeModule('socialservices');

        $arSocialNetworksInfo = array();

		$arSocialNetworksInfo['VKontakte']      = self::getOptionVk();
        $arSocialNetworksInfo['Facebook']       = self::getOptionFb();
        $arSocialNetworksInfo['Twitter']        = self::getOptionTwitter();
        $arSocialNetworksInfo['Odnoklassniki']  = self::getOptionOk();

        return $arSocialNetworksInfo;
    }

    /* получим ID приложения и секретный ключ для  VK */
    private static function getOptionVk()
    {
		$appID     = trim(\CSocServAuth::GetOption('vkontakte_appid'));
		$appSecret = trim(\CSocServAuth::GetOption('vkontakte_appsecret'));

        return array('ID'=>$appID, 'SECRET'=>$appSecret);
    }

    /* получим ID приложения и секретный ключ для FB */
    private static function getOptionFb()
    {
        $appID     = trim(\CSocServAuth::GetOption('facebook_appid'));
        $appSecret = trim(\CSocServAuth::GetOption('facebook_appsecret'));

        return array('ID'=>$appID, 'SECRET'=>$appSecret);
    }

    /* получим ID приложения и секретный ключ для Twitter */
    private static function getOptionTwitter()
    {
        $appID     = trim(\CSocServAuth::GetOption('twitter_key'));
        $appSecret = trim(\CSocServAuth::GetOption('twitter_secret'));

        return array('ID'=>$appID, 'SECRET'=>$appSecret);
    }

    /* получим ID приложения и секретный ключ для Одноклассники */
    private static function getOptionOk()
    {
        $appID     = trim(\CSocServAuth::GetOption('odnoklassniki_appid'));
        $appSecret = trim(\CSocServAuth::GetOption('odnoklassniki_appsecret'));
        $appKey    = trim(\CSocServAuth::GetOption("odnoklassniki_appkey"));

        return array('ID'=>$appID, 'SECRET'=>$appSecret, 'KEY'=>$appKey);
    }

}
?>