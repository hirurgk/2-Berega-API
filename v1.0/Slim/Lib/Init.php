<?
/**
 * Created by y.alekseev
*/

namespace Slim\Lib;

class Init extends \Slim\Helper\Lib
{
	private static $INIT = array();

    /**
     * Объединяем все необходимые данные при старте приложения и возвращаем в массиве
     * @return array
     */
	public static function get()
	{
        $site_id_info = Site::getSiteIdFromIP();
        self::setSiteId( $site_id_info['SITE_ID']);

		self::$INIT['SITES']        = Site::getList();
		self::$INIT['PHONES']       = Site::getPhoneList();

        # Определение сайта(региона) по IP
        if($site_id_info['SITE_ID'])
            self::$INIT['SITE_FROM_IP'] = $site_id_info;

        self::$INIT['SETTINGS']     = Params::getList();        //Настройки сайта
		self::$INIT['MENU']         = Menu::getList();          //Разделы меню
		self::$INIT['FILTERS']      = Filter::getList();        //Фильтры
		self::$INIT['TOPPINGS']     = Topping::getList();       //Топпинги
        
        $serverName = self::getServerURI($site_id_info['SITE_ID']);
        //Ссылки на сайта
        self::$INIT['LINKS'] = array(
            'SALES' => 'http://' . $serverName . '/o-nas/conditions/bonus_programs/',
            'DELIVERY' => 'http://' . $serverName . '/o-nas/conditions/delivery/',
            'PAYMENT' => 'http://' . $serverName . '/o-nas/conditions/payment/',
            'PROMO' => 'http://m.' . $serverName . '/akcii/darim-300-rubley/?landing_disallowed=Y',
            'FB' => 'http://www.facebook.com/2berega',
            'VK' => 'http://vk.com/2berega',
            'TW' => 'https://twitter.com/2_berega',
            'OK' => 'http://www.odnoklassniki.ru/group/52070244221019',
        );
        
		//Доставка
		//self::$INIT['DELIVERY'] = Delivery::getDelivery();

        \Slim\Helper\ErrorHandler::inZero();

		return self::$INIT;
	}
}
?>