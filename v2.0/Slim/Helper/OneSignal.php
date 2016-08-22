<?
/**
 * Created by y.alekseev
 */

 
namespace Slim\Helper;


# Документация:
# https://documentation.onesignal.com/docs/notifications-create-notification

class OneSignal
{
    /**
     * OneSignal App ID
     */
    const APP_ID = 'e1534b6c-6700-40e8-86f3-c9d5dd1712f9';
    
    /**
     * REST API Key
     */
    const API_KEY = 'MjNlYThiOWQtMWI3NC00NzVmLTg1OGUtNzg0MzFlMmY3ZDJh';
    
    
    /**
     * Формирует REST-запрос к API OneSignal для отправки PUSH-уведомления на устройства
     *
     * @param string $message - сообщение
     * @param array $appIDs - массив внутренних ID приложений в OneSignal
     * @param array $data - массив данных (ключ => значение)
     * @param int $sendAfter - время в UNIX-формате, после которого отправить пуш (откуда отправляется)
     * @param int $timeToLive - время жизни пуша в секундах
     */
    public static function sendPush($message = '', $appIDs = array(), $data = array(), $sendAfter = 0, $timeToLive = 0)
    {
        $URL = 'https://onesignal.com/api/v1/notifications';
        
        $fields = array(
            'app_id' => self::APP_ID,
            'include_player_ids' => $appIDs,
            'data' => json_encode($data),
            'contents' => array('en' => $message)
        );
        
        if ($timeToLive)
            $fields['ttl'] = $timeToLive;
        
        if ($sendAfter)
            $fields['send_after'] = date('Y-m-d H:i:s', $sendAfter) . ' GMT+0300';
        
        
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Basic '.self::API_KEY
        );

        $fields = json_encode($fields);
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $URL);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($curl);
        curl_close($curl);
        
        return json_decode($response, true);
    }

}
?>