<?
/**
 * Created by y.alekseev
 */

namespace Slim\Lib;

class Banners extends \Slim\Helper\Lib
{
	/**
	 * Возвращает список всех баннеров с привязкой к сайтам(регионам)
	 * @return array
	 */
	public static function getList() {
		\CModule::IncludeModule('iblock');

		$dbBanners = \CIBlockElement::GetList(
				array(),
				array('IBLOCK_ID'=>self::BANNER_MOBILE, 'ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y')
		);

        $banners = self::getCollection($dbBanners);
		return $banners;
	}
	
	/**
	 * Возвращает баннеры по запрошенному региону
	 * @return array
	 */
	public static function getListFromSiteId() {
		\CModule::IncludeModule('iblock');
		
		$siteId = self::getSiteId();
		$props = array();

		$dbProps = \CIBlockPropertyEnum::GetList(array(), array('PROPERTY_ID' => self::BANNER_TARGETING));
		while ($arProp = $dbProps->Fetch())
            { $props[$arProp['XML_ID']] = $arProp['ID']; }

		$dbBanners = \CIBlockElement::GetList(
				array(),
				array('IBLOCK_ID'=>self::BANNER_MOBILE, 'ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y', 'PROPERTY_TARGETING' => $props[$siteId])
		);

        $banners = self::getCollection($dbBanners);
		return $banners;
	}



    /**
     * Формирование массива значений для банера(ов)
     * @param $dbBanners
     *
     * @return array
     */
    private static function getCollection( $dbBanners )
    {
        $banners = array();

        while ($arBanner = $dbBanners->GetNextElement())
        {
            $banner = array();

            $arFields = $arBanner->GetFields();
            $arProps  = $arBanner->GetProperties();

            $banner['ID'] = (int)$arFields['ID'];

            if ($arProps['LINK_TYPE']['VALUE_XML_ID'] == 'MENU') {
                $banner['LINK_TYPE'] = 'MENU';
                $banner['LINK'] = (int)$arProps['LINK_MENU']['VALUE'];
            } else {
                $banner['LINK_TYPE'] = 'OTHER';

                # при увеличении списка "Прочее расположение" в инфоблоке, строчку раскоментить
                # $banner['LINK'] = $arProps['LINK_OTHER']['VALUE_XML_ID'];
            }

            $banner['POSITION']    = $arProps['POSITION']['VALUE_XML_ID'];
            $banner['SORT']        = (int)$arFields['SORT'];
            $banner['SITE_ID']     = $arProps['TARGETING']['VALUE_XML_ID'];

            if (strlen($arFields['PREVIEW_TEXT'])>0)
                $banner['DESCRIPTION'] = $arFields['PREVIEW_TEXT'];


            $pic = \CFile::ResizeImageGet($arFields['PREVIEW_PICTURE'], array('width' => 600, 'height' => 238), BX_RESIZE_IMAGE_EXACT, false);
            if ($pic['src'])
                $banner['PICTURE'] = self::getApp()->request->getUrl() . $pic['src'];


            switch ($arProps['URL_TYPE']['VALUE_XML_ID'])
            {
                case 'SALE':
                    $banner['URL'] = '2berega://sale/' . $arProps['URL_SALE']['VALUE'];
                    break;
                case 'MENU':
                    $banner['URL'] = '2berega://menu/' . $arProps['URL_MENU']['VALUE'];
                    break;
                case 'PRODUCT':
                    $banner['URL'] = '2berega://product/' . $arProps['URL_PRODUCT']['VALUE'];
                    break;
                case 'ADDRESS':
                    $banner['URL'] = $arProps['URL_ADDRESS']['VALUE'];
                    break;
                default:
                    break;
            }

            $banners[] = $banner;
        }
        return $banners;
    }

}
?>