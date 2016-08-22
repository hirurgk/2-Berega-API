<?
/**
 * Created by PhpStorm.
 * User: v.kravtsov
 * Date: 16.03.2015
 * Time: 11:02
 */
namespace Slim\Helper;


class Answer
{
    /**
     * Метод формирует ответ в json формате в случае наличия ошибок заполнит их
     * Перед отправкой данные сжимаются
     *
     * @param array $data
     * @param bool $string
     */
    public static function json( $data=array(), $string=false, $log=false )
    {
        $app = \Slim\Slim::getInstance();
        $arErrors = \Slim\Helper\ErrorHandler::getList();

        $date =  new \DateTime();
		$date->modify('-1 hours');

        header("Content-Encoding: gzip");
        $app->response->setStatus( $arErrors['status'] );

        if( \Slim\Helper\ErrorHandler::isError() )
        	$answer = json_encode( array('ERRORS'=>$arErrors['err'], 'ANSWER'=>array(), 'TIMESTAMP'=>$date->getTimestamp()) );
        else
        	$answer = json_encode( array('ERRORS'=>array(), 'ANSWER'=>$data, 'TIMESTAMP'=>$date->getTimestamp()) );

        //Добавим логи
        if ($log)
           \Slim\Helper\Lib::addLog($answer, $log);
         
        echo gzencode($answer, 9);
    }

}
?>