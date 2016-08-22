<?
/**
 * Created by y.alekseev
 */

namespace Slim\Lib;

class SMS extends \Slim\Helper\Lib
{
    public static function sendSMSLock()
    {
        $phone = self::getApp()->request->params('PHONE');
        $hash = self::getApp()->request->params('HASH');
        
        if (!\C2B::S('ORDER_WITHOUT_CALL_NOAUTH')) {
            \Slim\Helper\ErrorHandler::put('SMS_LOCK_DISABLED', 404);
            return array();
        }
        
        //Если хеш не совпадает, останавливаем приложение
        if (strtolower(md5($phone.'2b_secret_sms_lock')) != strtolower($hash)) {
            \Slim\Helper\ErrorHandler::addMessageAndPut('SMS_LOCK_HASH_INCORRECT', array('CODE' => 800000, 'MESSAGE' => "Некорректная строка проверки"), $status=404);
            return array();
        }
        
        $sms = \SMSLock::send($phone);
        
        if (!$sms['SUCCESS']) {
            if ($sms['ERROR'] == 501) {
                \Slim\Helper\ErrorHandler::put('SMS_ALREADY_SEND', 404);
                return array();
            }
            
            \Slim\Helper\ErrorHandler::put('SMS_NOT_SEND', 404);
            return array();
        }
        
        return array('CODE' => md5($sms['CODE'].'2b_secret_sms_lock_code'));
    }
}
?>