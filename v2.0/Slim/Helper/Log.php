<?
/**
 * Created by y.alekseev
 */

namespace Slim\Helper;

/**
 * Class Log
 * @package Slim\Helper
 */
class Log
{
    /**
     * Количество дней хранения логов
     */
    const COUNT_DAYS = 7;

    
    /**
	* Сохраняет логи в папку /api/logs
    *
    * @param string $additional_message
    */
    public static function add($additional_message = '', $folder)
    {
        //Путь до папки с логами
        $PATH_LOGS = $_SERVER['DOCUMENT_ROOT']."/api/v2.0/logs/";

        $time = time();

        //Соберём все данные
        global $USER;
        $arData = array();
        $arData['PATH'] = \Slim\Helper\Lib::getApp()->request->getPath();	//URL
        $arData['DATE'] = date('d.m.Y H:i:s', $time);	//Дата и время обращения к скрипту
        $arData['USER'] = "{$USER->GetID()} ({$USER->GetLogin()})";	//Пользователь
        $headers = (array) \Slim\Helper\Lib::getApp()->request->headers;
        sort($headers);
        $arData['HEADERS'] = $headers[0];	//Заголовки
        $arData['GET'] = $_GET;	//Заголовки
        $arData['POST'] = $_POST;	//Заголовки
        $arData['BODY'] = json_decode(\Slim\Helper\Lib::getApp()->request->getBody(), true);	//Тело


        $message  = "================================" . PHP_EOL;

        //URL, дата и юзер
        $message .= $arData['PATH'] . PHP_EOL;
        $message .= $arData['DATE'] . PHP_EOL;
        $message .= "User: {$arData['USER']}" . PHP_EOL;
        $message .= "JSON_FULL_DATA: " . json_encode($arData) . PHP_EOL;
        $message .= "--------------------------------" . PHP_EOL;

        $message .= print_r($arData, true);

        //Дополнительно
        if ($additional_message) {
            $message .= "ADDITIONAL" . PHP_EOL;
            $message .= $additional_message . PHP_EOL;
        }

        $message .= "================================" . PHP_EOL . PHP_EOL;
        
        
        //Составляем путь до файла и записываем
        $folder_name = $folder.'/';
        mkdir($PATH_LOGS.$folder_name, 0775);
                
        $file_name = date('d.m.Y', $time).'.log';

        file_put_contents($PATH_LOGS.$folder_name.$file_name, $message, FILE_APPEND);
        
        self::clean();
    }
    
    
    /**
	* Метод затирает старые логи
    */
    private static function clean()
    {
        $unixdate = strtotime(date('d.m.Y', $time));
        $start = $unixdate - (self::COUNT_DAYS * 86400);	//Отнимаем нужное количество дней. От этой даты оставляем файлы в папке
        $dir = opendir($PATH_LOGS);
        while ($file = readdir($dir)) {
            if ($file == '.' || $file == '..')
                continue;
            
            if (is_dir($PATH_LOGS.$file)) {
            	$dir_log = opendir($PATH_LOGS.$file);
            	while ($file_log = readdir($dir_log)) {
            		if ($file_log == '.' || $file_log == '..')
            			continue;
            		
            		if (strpos($file_log, '.log')) {
            			$file_date = strtotime(substr($file_log, 0, -4));
            			if ($file_date <= $start)
            				unlink($PATH_LOGS.$file.'/'.$file_log);
            		}
            	}
            }
        }
    }

}