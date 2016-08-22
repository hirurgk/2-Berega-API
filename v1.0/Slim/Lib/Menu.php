<?
/**
 * Created by PhpStorm.
 * User: v.kravtsov
 * Date: 16.03.2015
 * Time: 13:50
 */
namespace Slim\Lib;

class Menu extends \Slim\Helper\Lib
{
    /**
     * Получение списка категорий для меню
     * Выбираются только разделы с галочкой UF_SHOW_MOBILE_APP
     * В свойтсве UF_LINK_FILTER задаётся привязка к фильтру
     *
     * @return array
     */
    public static function getList()
    {
    	global $DB;
        \CModule::IncludeModule('iblock');

        $arSort   = array('LEFT_MARGIN' => 'ASC');
        $arFilter = array('IBLOCK_ID' => self::PRODUCTS, '!UF_SHOW_MOBILE_APP'=>false);
        $arSelect = array('ID', 'ACTIVE', 'TIMESTAMP_X', 'NAME', 'DEPTH_LEVEL', 'SORT', 'PICTURE', 'UF_SHOW_MOBILE_APP', 'UF_LINK_FILTER', 'UF_IOS_PICTURE');
        
		//$timestamp = self::getTimestamp();
        $arFilter = ( $timestamp )
	        ? array_merge($arFilter, array('>TIMESTAMP_X'=>date($DB->DateFormatToPHP(\CLang::GetDateFormat()), $timestamp)))
	        : array_merge($arFilter, array('ACTIVE'=>'Y'));

        $arRes = \CIBlockSection::GetList($arSort, $arFilter, false, $arSelect);

        $arMenu = array();
        while($res = $arRes->Fetch())
        {
            $arInMenu = array();

            if($res['DEPTH_LEVEL'] == 1 )
				//$parenId = \Slim\Helper\OldSectionsId::getOld( $res['ID'] );
                $parenId = $res['ID'];

            $picture = \CFile::ResizeImageGet( $res['PICTURE'], array('width' => 344, 'height' => 238), BX_RESIZE_IMAGE_EXACT, false);
            $picture = ( strlen($picture['src'])>0 ) ? self::getApp()->request->getUrl() . $picture['src'] : '';
            
            $picture_ios = \CFile::ResizeImageGet( $res['UF_IOS_PICTURE'], array(), BX_RESIZE_IMAGE_EXACT, false);
            $picture_ios = ( strlen($picture_ios['src'])>0 ) ? self::getApp()->request->getUrl() . $picture_ios['src'] : '';


			//$arInMenu['ID']             = (int)\Slim\Helper\OldSectionsId::getOld( $res['ID'] );
            $arInMenu['ID']     = (int)$res['ID'];

            $arInMenu['ACTIVE']         = ($res['ACTIVE'] == 'Y') ? true : false;
            $arInMenu['DEPTH_LEVEL']    = (int)$res['DEPTH_LEVEL'];
            $arInMenu['PARENT_ID']      = (int)$parenId;
            $arInMenu['NAME']           = $res['NAME'];
            $arInMenu['SORT']           = (int)$res['SORT'];

            if(strlen($picture)>0)
                $arInMenu['PICTURE']    = $picture;
            
            if(strlen($picture_ios)>0)
                $arInMenu['PICTURE_IOS'] = $picture_ios;
            else
                $arInMenu['PICTURE_IOS'] = 'http://'.$_SERVER['SERVER_NAME'].'/upload/picture_ios_default.png';

            if($res['UF_LINK_FILTER'])
                $arInMenu['FILTER_SECTION_ID']     = (int)$res['UF_LINK_FILTER'];

            $arMenu[] = $arInMenu;
        }

        if (count($arMenu)==0 && $timestamp)
        	\Slim\Helper\ErrorHandler::put('MENU_ITEM_NOT_FOUND', 304);
        elseif(count($arMenu)==0)
            \Slim\Helper\ErrorHandler::put('MENU_ITEM_NOT_FOUND');

		$arMenu = array_merge(self::getPsevdoArray(), $arMenu);
        
        return $arMenu;
    }
	
	/**
	* TODO(y.alekseev 04.02.2016): ВРЕМЕННАЯ ФУНКЦИЯ! ID'шники со старого сайта категорий, которых нет на новом сайте!
	*/
	private function getPsevdoArray() {
		$ar = array(
			array('ID' => 53),
			array('ID' => 68),
			array('ID' => 69),
			array('ID' => 72),
			array('ID' => 67),
			array('ID' => 62),
			array('ID' => 64),
			array('ID' => 61),
			array('ID' => 92),
			array('ID' => 56),
			array('ID' => 117),
			array('ID' => 229),
			array('ID' => 249),
			array('ID' => 66),
			array('ID' => 57),
			array('ID' => 71),
			array('ID' => 115),
			array('ID' => 93),
			array('ID' => 101),
			array('ID' => 106),
			array('ID' => 104),
			array('ID' => 183)
		);
		
		foreach ($ar as &$a) {
			$a['ACTIVE'] = false;
			$a['DEPTH_LEVEL'] = 9;
			$a['PARENT_ID'] = 999999;
			$a['NAME'] = "";
			$a['SORT'] = 999999;
			$a['PICTURE'] = '/';
		}

		return $ar;
	}
}
