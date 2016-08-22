<?

namespace Slim\Helper;

/**
 * Класс для работы с платежной сестемой PayOnline
 *
 * Class PaymentSystemPayOnline
 * @package Slim\Helper
 *
 */
Class PaymentSystemPayOnline extends \Slim\Helper\Lib {

    /**
     * Генерируем ссылку на оплату по схеме Standart
     * @param $OrderId
     * @param $Amount
     *
     * @return string
     */
	public static function GetPaymentURL($OrderId, $Amount)
	{
        $arPaymentSystemInfo = self::getPaymentSystem();

        $MerchantId           = $arPaymentSystemInfo['Payment_ID'];
        $PrivateSecurityKey   = $arPaymentSystemInfo['Payment_Key'];

        $Amount = "$Amount.00";

        $PaymentUrl   = "https://secure.payonlinesystem.com/ru/payment/";
        $ReturnUrl    = 'http://spb-test.2-berega.ru/payment/success';
        $FailUrl      = 'http://spb-test.2-berega.ru/payment/failure';

        # соберем параметры для $PrivateSecurityKey
        $params	 = 'MerchantId='. $MerchantId;
	    $params .= '&OrderId='.$OrderId;
	    $params .= '&Amount='.$Amount;
	    $params .= '&Currency=RUB';
	    $params .= '&PrivateSecurityKey='.$PrivateSecurityKey;

        # сформируем секретный ключ
	    $SecurityKey=md5($params);

        # соберем параметры для Url
	    $url_query= "?MerchantId=".$MerchantId."&OrderId=".urlencode($OrderId)."&Amount=".$Amount."&Currency=RUB";

        $url_query.= "&ReturnUrl=".urlencode($ReturnUrl);
        $url_query.= "&FailUrl=".urlencode($FailUrl);
        $url_query.="&SecurityKey=".$SecurityKey;

        return $PaymentUrl.$url_query;
	}

}


?>