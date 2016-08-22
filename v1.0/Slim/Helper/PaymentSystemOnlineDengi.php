<?

namespace Slim\Helper;


Class PaymentSystemOnlineDengi extends \Slim\Helper\Lib {

	// генерируем ссылку на оплату
	public static function GetPaymentURL($OrderId, $Amount)
	{
        $arPaymentSystemInfo = self::getPaymentSystem();

        $vars = array(
            'project'   => $arPaymentSystemInfo['Payment_ID'],

            'nickname'  => $OrderId,
            'order_id'  => $OrderId,

            'amount'    => $Amount,
            'mode_type' => 624,
            'xml'       => 1,
            'return_url_success'=>  'http://2-berega.ru/payment/success',
            'return_url_fail' => 'http://2-berega.ru/payment/failure'
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.onlinedengi.ru/wmpaycheck.php');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $res = curl_exec($ch);

        $xml = simplexml_load_string($res, 'SimpleXMLElement', LIBXML_NOCDATA);

        # если нет ошибок, вернем url
        if(intval($xml->status) == 0){
            $url = trim($xml->iframeUrl);
            return $url;
        }
	}

}


?>