<?php
/**
 * Created by PhpStorm.
 * User: v.kravtsov
 * Date: 25.09.2015
 * Time: 15:04
 */


namespace Slim\Helper;

/**
 * Class PaymentSystem
 *
 * Класс для работы с платёжной системой в API Mobile
 *
 * Активная платежная система берется из модуля "Настройки сайта"
 * Настрока Идентификатор ПС МП - в которой указан ID использвемой ПС
 *
 * @package Slim\Helper
 */
class PaymentSystem extends \Slim\Helper\Lib {

    /**
     * Создание ссылки для оплаты картой
     *
     * @param $orderId      - номер заказа
     * @param $amount       - стоимость заказа
     * @param $paySystemId  - Id платежной системы
     *
     * @return string
     */
    public static function getUrl($orderId, $amount, $paySystemCode)
    {

		\CModule::IncludeModule('sale');
		$card_active = \CSalePaySystem::GetList(array(), array('ID' => 8), false, false, array('ID', 'ACTIVE'))->Fetch();
		if ($card_active['ACTIVE'] == 'N') {
			return 'http://2-berega.ru/payment/no_card.php';
		}

		if ($paySystemCode == 'online_dengi')
			$paySystemId = PAYSYSTEM_MOBILE;
		elseif ($paySystemCode == 'pskb')
			$paySystemId = PAYSYSTEM_MOBILE_2;

        # получим данные по платежой системе
        $arPaymentSystemInfo = self::getInfo( $paySystemId );

        # сформируем url
        switch ($paySystemId) {
            case PAYSYSTEM_MOBILE:
                $paySystemUrl = self::onlineDengi( $arPaymentSystemInfo, $orderId, $amount );
                break;
            case 999:
                $paySystemUrl = self::payOnline( $arPaymentSystemInfo, $orderId, $amount );
                break;
            case PAYSYSTEM_MOBILE_2:
                $paySystemUrl = self::oosPscb( $arPaymentSystemInfo, $orderId, $amount );
                break;
        }

        return $paySystemUrl;
    }


    /**
     * Получим данные по активной платежной системе
     * @param $paySystemId - Id платежой сисетмы
     *
     * @return array
     */
    public static function getInfo( $paySystemId )
    {
        \CModule::IncludeModule("sale");
        $arPaySystem = \CSalePaySystem::GetByID($paySystemId, self::PERSON_TYPE_ID);

        # вернем массив с настройки ПС
        return unserialize( $arPaySystem['PSA_PARAMS'] );
    }



    /**
     * метод для Деньги Онлайн
     *
     * @param $arPaymentSystemInfo
     * @param $orderId
     * @param int $amount
     *
     * @return string
     */
    private function onlineDengi( $arPaymentSystemInfo, $orderId, $amount = 0 )
    {
        $vars = array(
            'project'  			 => $arPaymentSystemInfo['ONLINEDENGI_PROJECT']['VALUE'],

            'nickname' 			 => $orderId,
            'order_id' 			 => $orderId,

            'amount'   			 => $amount,
			'paymentCurrency'    => 'RUB',
            'mode_type'			 => 624,
            'xml'       		 => 1,
            'return_url_success' =>  'http://2-berega.ru/payment/success',
            'return_url_fail'	 => 'http://2-berega.ru/payment/failure'
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


    /**
     * метод для Оплата через PayOnline System
     *
     * @param $arPaymentSystemInfo
     * @param $orderId
     * @param int $amount
     *
     * @return string
     */
    private function payOnline( $arPaymentSystemInfo, $orderId, $amount = 0 )
    {
        $merchantId           = $arPaymentSystemInfo['MERCHANT_ID']['VALUE'];
        $privateSecurityKey   = $arPaymentSystemInfo['PRIVATE_SECURITY_KEY']['VALUE'];

        $amount = "$amount.00";

        $paymentUrl   = "https://secure.payonlinesystem.com/ru/payment/";
        $returnUrl    = 'http://2-berega.ru/payment/success';
        $failUrl      = 'http://2-berega.ru/payment/failure';

        # соберем параметры для $PrivateSecurityKey
        $params	 = 'MerchantId='. $merchantId;
        $params .= '&OrderId='.$orderId;
        $params .= '&Amount='.$amount;
        $params .= '&Currency=RUB';
        $params .= '&PrivateSecurityKey='.$privateSecurityKey;

        # сформируем секретный ключ
        $securityKey=md5($params);

        # соберем параметры для Url
        $url_query= "?MerchantId=".$merchantId."&OrderId=".urlencode($orderId)."&Amount=".$amount."&Currency=RUB";

        $url_query .= "&ReturnUrl=".urlencode($returnUrl);
        $url_query .= "&FailUrl=".urlencode($failUrl);
        $url_query .= "&SecurityKey=".$securityKey;

        return $paymentUrl.$url_query;
    }


    /**
     * медот для ПСПБ Банк
     *
     * @param $arPaymentSystemInfo
     * @param $orderId
     * @param $amount
     *
     * @return string
     */
    private function oosPscb( $arPaymentSystemInfo, $orderId, $amount = 0 )
    {
        $paymentUrl   = $arPaymentSystemInfo['PAYMENT_PAGE']['VALUE'];
        $message = array(
            "amount"            => $amount,
            "details"           => $arPaymentSystemInfo['ORDER_DESCR']['VALUE'],
            "customerRating"    => "5",
            "customerAccount"   => $orderId,
            "orderId"           => $orderId,

            "successUrl"        => 'http://2-berega.ru/payment/success',
            "failUrl"           => 'http://2-berega.ru/payment/failure',

			"paymentMethod"     => "ac",    //$arPaymentSystemInfo['PAYMENT_METHOD']['VALUE']
            "customerPhone"     => "",
            "customerEmail"     => "",
            "customerComment"   => "",
        );

        $messageText = json_encode($message);

        $http_params = '?marketPlace=' . $arPaymentSystemInfo['MARKET_PLACE_ID']['VALUE'];
        $http_params .= '&message=' . base64_encode($messageText);
        $http_params .= '&signature=' . hash('sha256', $messageText . $arPaymentSystemInfo['MerchantKey']['VALUE']);

        return $paymentUrl.$http_params;
    }

}
