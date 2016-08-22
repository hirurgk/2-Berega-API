<?
/**
 * Created by y.alekseev
 */

namespace Slim\Lib;

class Site extends \Slim\Helper\Lib
{

    /**
     * Возвращает список телефонов по сайтам
     * @return array
     */
	public static function getPhoneList()
	{
		\CModule::IncludeModule('iblock');

        $sites = array();
        $dbPhones = \CIBlockElement::GetList(
            array(),
            array('IBLOCK_ID'=>self::PHONES, 'ACTIVE'=>'Y'),
            false,
            false,
            array()
        );

        while( $arPhone = $dbPhones->GetNextElement() )
        {
            $arFields = $arPhone->GetFields();
            $arProps  = $arPhone->GetProperties();


            switch ($arProps['PHONE_TYPE']["VALUE_XML_ID"]) {
                case 'tech':
                    $phoneType = 'SUPPORT';  break;
                case 'common':
                    $phoneType = 'COMMON';   break;
                case 'region':
                    $phoneType = 'OPERATOR'; break;
            }

            $sites[] = array(
			    'SITE_ID'    => $arProps["TARGETING"]['VALUE_XML_ID'],
			    'NAME'       => $arFields['NAME'],
                'PHONE_TYPE' => $phoneType,
                'VALUE'      => $arProps['PHONE_NUMBER']['VALUE'],
            );
		}

		return $sites;
	}


    /**
     * Возвращает список сайтов
     * @return array
     */
    public static function getList()
    {
        \CModule::IncludeModule('iblock');
        $site_ids = array();

        $dbSites = \CSite::GetList($by, $order, array());
        while ($arSite = $dbSites->Fetch())
        {    $site_ids[] = array(
                'SITE_ID'=>$arSite['LID'],
                'NAME' => $arSite['SITE_NAME']
            );
        }

        return $site_ids;
    }


    /**
     * Возвращает ID сайта по IP-адресу пользователя
     * @return array
     */
    public static function getSiteIdFromIP()
	{
		$sites = array();

        $ip = self::getUserIP();
        $client_site_id = self::getSiteId();

        $site_id = false;

		$dbSites = \CSite::GetList($by, $order, array());
		while ($arSite = $dbSites->Fetch())
			$sites[$arSite['LID']] = $arSite['SITE_NAME'];

        # Таймаут для file_get_contents
        $city = '';
		$ctx = stream_context_create(array('http' => array('timeout' => 2)));

        if ($client_site_id !== null && $sites[$client_site_id]) {
            $site_id = $client_site_id;
            $city = $sites[$site_id];
        }
        else {
            # xml с определённым городом
            $xml = file_get_contents("http://ipgeobase.ru:7020/geo?ip=" . $ip, 0, $ctx);
            if ($xml)
            {
                $data = new \SimpleXMLElement($xml);
                $city = (string)$data->ip->city;
            }

            if( $city )
                $site_id = array_search($city, $sites);
        }
		
		// С 16.03.2016, если не удалось определить SITE_ID, то отдаём Питер вместо пустого массива
		return ($site_id) ? array('SITE_ID' => $site_id, 'NAME' => $city) : array('SITE_ID' => "s1", 'NAME' => "Санкт-Петербург");
	}


	/**
	 * Возвращает список активных алертов
	 * @return array
	 */
	public static function getAlerts()
	{
	    \CModule::IncludeModule('iblock');
	    
	    $siteId = self::getSiteId();
	    $property_enum = \CIBlockPropertyEnum::GetList(array(), array("IBLOCK_ID" => self::IBLOCK_ALERTS_ID, "XML_ID" => $siteId))->Fetch();
	    
	    $alerts = array();
	    $dbAlerts = \CIBlockElement::GetList(
	            array(),
	            array(
	                    'IBLOCK_ID' => self::IBLOCK_ALERTS_ID,
	                    'ACTIVE' => 'Y',
	                    'ACTIVE_DATE' => 'Y',
	                    'PROPERTY_TARGETING' => $property_enum 
	            ),
	            false,
	            false,
	            array('PREVIEW_TEXT', 'PROPERTY_DELBTN')
	    );
	    while ($arAlert = $dbAlerts->Fetch()) {
	        $r_alert = array(
	                "TEXT" => $arAlert['PREVIEW_TEXT'],
	                "ORDER_DISABLED" => $arAlert['PROPERTY_DELBTN_VALUE'] == 'Y' ? true : false,
	        );
	        
	        $alerts[] = $r_alert;
	    }
	    
	    return $alerts;
	}

}