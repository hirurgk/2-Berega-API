<?php
/**
 * Created by PhpStorm.
 * User: V.Kravtsov
 * Date: 17.06.2015
 * Time: 16:54
 */

namespace Slim\Lib;

class Delivery extends \Slim\Helper\Lib
{
    /**
     * Получение времени доставки
     *
     * @param $street
     * @param $house
     * @param $order_sum
     * @param string $timestamp
     * @param bool $new_address
     *
     * @return array
     */
    public static function GetTime( $street, $house, $order_sum, $timestamp = '', $new_address = false )
    {
        # Преобразуем метку времени $timestamp в формат необходимый 1С
        if( !empty($timestamp) ) {
            if (is_numeric($timestamp)) {
                $datetime = new \DateTime();
                $datetime->setTimestamp($timestamp);
            }else {
                $datetime = new \DateTime($timestamp);
                $datetime->getTimestamp();
            }
            $format_date = $datetime->format('YmdHis');
        }
        else
            $format_date = '';


        # Инстализируем параметры
        $param = new \stdClass();

        $param->Street       = $street;
        $param->HouseNumber  = $house;
        $param->Corpus       = '';
        $param->Sum          = $order_sum;
        $param->DeliveryTime = $format_date;

        # обратимся к веб сервису
        ini_set("soap.wsdl_cache_enabled", "0");
		$client = new \SoapClient(self::SOAP_1C_URL, array('login' => self::SOAP_1C_LOGIN, 'password' => self::SOAP_1C_PASSWORD));
        $response = $client->GetTime($param);

        $arData = $response->return->massiv;

        # соберём массив для отправки
        $arReturnInfo = array();

        foreach( $arData as $key=>$value) {
            switch($key) {
                case 0: 
					$arReturnInfo['DELIVERY_TIME'] = self::convertInMinutes( $value);
					$arReturnInfo['MESSAGE'] = \Tools1C::parseDeliveryTime($value, $format_date);
                break;
                case 1: $arReturnInfo['WITHOUT_OPERATOR_CALL'] = ($value == 1 && !$new_address) ? true: false;
                    break;
                case 2: $arReturnInfo['TERMINAL_PAYMENT'] = ($value == 1) ? true: false;
                    break;
                case 3: // цех пропускаем
                    break;
                case 4:
                    if ($value)
                        $arReturnInfo['MIN_ORDER_SUM'] = (int)$value;
                    break;
                case 5:
                    if ($value)
                        $arReturnInfo['COST_OF_DELIVERY'] = (int)$value;
                    break;
            }
        }

		if ($arReturnInfo['MIN_ORDER_SUM'] < $order_sum) {  $arReturnInfo['COST_OF_DELIVERY'] = 0; }

        return $arReturnInfo;

    }

}
