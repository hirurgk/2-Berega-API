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

        self::$INIT['SETTINGS']     = Params::getList();             //Настройки сайта
		self::$INIT['MENU']         = Menu::getList();               //Разделы меню
		self::$INIT['FILTERS']      = Filter::getList();             //Фильтры
		self::$INIT['TOPPINGS']     = Topping::getList();            //Топпинги
        
        self::$INIT = array_merge(self::$INIT, Links::getList());    //Ссылки
        
		//Доставка
		//self::$INIT['DELIVERY'] = Delivery::getDelivery();

        \Slim\Helper\ErrorHandler::inZero();

		return self::$INIT;
	}
}
?>