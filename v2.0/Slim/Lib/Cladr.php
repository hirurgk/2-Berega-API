<?php
/**
 * Created by PhpStorm.
 * User: v.kravtsov
 * Date: 18.06.2015
 * Time: 15:26
 */

namespace Slim\Lib;

class Cladr extends \Slim\Helper\Lib
{
    /**
     * Получение улиц с их кодами
     * @param $name
     * @return array
     */
    public static function getStreet( $name )
    {
        global $DB;

        mysql_query("SET NAMES 'utf8'");
        mysql_query("SET CHARACTER SET 'utf8'");

        $siteId = self::getSiteId();

        $sql="SELECT `CODE`, `STREET` FROM  `U_STREET_LIST` WHERE `CITY`='".$siteId."' AND `STREET` LIKE  '%".addslashes($name)."%'";
        $results = $DB->Query($sql);

        $arResult = array();
        while ($row = $results->Fetch()){
            array_push($arResult, $row);
        }

        return $arResult;
    }

    /**
     * Получение номеров домов для улицы по ее коду
     * @param $codeStreet
     * @return array
     */
    public static function getNumbersHouseForStreet( $codeStreet )
    {
        global $DB;

        mysql_query("SET NAMES 'utf8'");
        mysql_query("SET CHARACTER SET 'utf8'");

        $sql="SELECT `HOUSE` FROM `U_HOUSE_LIST` WHERE `STREET_CODE`='".addslashes($codeStreet)."' GROUP BY `HOUSE` ORDER BY `HOUSE` ASC";
        $results = $DB->Query($sql);

        $arResult = array();
        while ($row = $results->Fetch()){
            array_push($arResult, $row);
        }

        return $arResult;
    }

}