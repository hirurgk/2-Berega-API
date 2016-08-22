<?
/**
 * Created by y.alekseev
 */

namespace Slim\Lib;

class Links extends \Slim\Helper\Lib
{
    /**
     * Возвращает ссылки для приложения
     * @return array
     */
	public static function getList()
	{
        $links = array();
        
		$serverName = self::getServerURI($site_id_info['SITE_ID']);
        
        //Ссылки в блоке информации
        $links['LINKS_INFO'] = array(
            array(
                'NAME' => 'Условия доставки',
				'LINK' => 'http://' . $serverName . '/o-nas/conditions/delivery/',
                'ICON' => 'http://' . $serverName . '/upload/mobile_app/ic_delivery.png',				
            ),
            array(
                'NAME' => 'Бонусные программы',
                'LINK' => 'http://' . $serverName . '/o-nas/conditions/bonus_programs/',
                'ICON' => 'http://' . $serverName . '/upload/mobile_app/ic_bonus.png',
            ),
            array(
                'NAME' => 'Оплата',
                'LINK' => 'http://' . $serverName . '/o-nas/conditions/payment/',
                'ICON' => 'http://' . $serverName . '/upload/mobile_app/ic_payment.png',
            ),
        );
        
        //Ссылки
        $links['LINKS'] = array(
            'PROMO' => 'http://m.' . $serverName . '/akcii/darim-300-rubley/?landing_disallowed=Y',
            //'VIDEO_COOKING' => 'https://www.youtube.com/watch?v=au3dAGhQezM',
            'FB' => 'http://www.facebook.com/2berega',
            'VK' => 'http://vk.com/2berega',
            'TW' => 'https://twitter.com/2_berega',
            'OK' => 'http://www.odnoklassniki.ru/group/52070244221019',
            'INST' => 'http://www.instagram.com/2berega/',
        );
        
        //Рассказать друзьям
        $links['SHARE'] = array(
            'MESSAGE' => 'Спешу с тобой поделиться - здесь самая вкусная пицца!',
            'LINK' => 'http://app.2-berega.ru',
        );
        
        return $links;
    }

}
?>