<?
/**
 * Created by y.alekseev
 */

namespace Slim\Helper;

class BUser extends \CUser
{
	//Массив с ID'шниками ошибок. Список с ID'шниками ошибок в \Slim\Helper\ErrorHandler
	public $ERROR_IDS = array();
	
	
	/**
	 * Переопределённый метод проверки полей для регистрации/обновления с данными пользователя
	 * @see CAllUser::CheckFields()
	 */
	function CheckFields(&$arFields, $ID=false)
	{
		/**
		 * @global CMain $APPLICATION
		 * @global CUserTypeManager $USER_FIELD_MANAGER
		 */
		global $DB, $APPLICATION, $USER_FIELD_MANAGER;

		$this->LAST_ERROR = "";

		$bInternal = false;
		$bExternal = (is_set($arFields, "EXTERNAL_AUTH_ID") && trim($arFields["EXTERNAL_AUTH_ID"]) <> '');
		$oldEmail = "";
		if($ID > 0 && !$bExternal)
		{
			$strSql = "SELECT EXTERNAL_AUTH_ID, EMAIL FROM b_user WHERE ID=".intval($ID);
			$dbr = $DB->Query($strSql, false, "FILE: ".__FILE__."<br> LINE: ".__LINE__);
			if(($ar = $dbr->Fetch()))
			{
				$oldEmail = $ar['EMAIL'];
				if($ar['EXTERNAL_AUTH_ID'] == '')
					$bInternal = true;
			}

		}
		elseif(!$bExternal)
		{
			$bInternal = true;
		}


		if($bInternal)
		{
			if($ID === false)
			{
				if(!isset($arFields["LOGIN"])) {
					$this->LAST_ERROR .= GetMessage("user_login_not_set")."<br>";
					$this->ERROR_IDS[] = 'USER_LOGIN_NOT_SET';
				}

				if(!isset($arFields["PASSWORD"])) {
					$this->LAST_ERROR .= GetMessage("user_pass_not_set")."<br>";
					$this->ERROR_IDS[] = 'USER_PASS_NOT_SET';
				}

				if(!isset($arFields["EMAIL"])) {
					$this->LAST_ERROR .= GetMessage("user_email_not_set")."<br>";
					$this->ERROR_IDS[] = 'USER_EMAIL_NOT_SET';
				}
			}
			if(is_set($arFields, "LOGIN") && $arFields["LOGIN"]!=Trim($arFields["LOGIN"])) {
				$this->LAST_ERROR .= GetMessage("LOGIN_WHITESPACE")."<br>";
				$this->ERROR_IDS[] = 'USER_LOGIN_WHITESPACE';
			}

			if(is_set($arFields, "LOGIN") && strlen($arFields["LOGIN"])<3) {
				$this->LAST_ERROR .= GetMessage("MIN_LOGIN")."<br>";
				$this->ERROR_IDS[] = 'USER_MIN_LOGIN';
			}

			if(is_set($arFields, "PASSWORD"))
			{
				if(array_key_exists("GROUP_ID", $arFields))
				{
					$arGroups = array();
					if(is_array($arFields["GROUP_ID"]))
					{
						foreach($arFields["GROUP_ID"] as $arGroup)
						{
							if(is_array($arGroup))
								$arGroups[] = $arGroup["GROUP_ID"];
							else
								$arGroups[] = $arGroup;
						}
					}
					$arPolicy = $this->GetGroupPolicy($arGroups);
				}
				elseif($ID !== false)
				{
					$arPolicy = $this->GetGroupPolicy($ID);
				}
				else
				{
					$arPolicy = $this->GetGroupPolicy(array());
				}

				$password_min_length = intval($arPolicy["PASSWORD_LENGTH"]);
				if($password_min_length <= 0)
					$password_min_length = 6;
				if(strlen($arFields["PASSWORD"]) < $password_min_length) {
					$this->LAST_ERROR .= GetMessage("MAIN_FUNCTION_REGISTER_PASSWORD_LENGTH", array("#LENGTH#" => $arPolicy["PASSWORD_LENGTH"]))."<br>";
					$this->ERROR_IDS[] = 'USER_PASSWORD_LENGTH';
				}

				if(($arPolicy["PASSWORD_UPPERCASE"] === "Y") && !preg_match("/[A-Z]/", $arFields["PASSWORD"])) {
					$this->LAST_ERROR .= GetMessage("MAIN_FUNCTION_REGISTER_PASSWORD_UPPERCASE")."<br>";
					$this->ERROR_IDS[] = 'USER_PASSWORD_UPPERCASE';
				}

				if(($arPolicy["PASSWORD_LOWERCASE"] === "Y") && !preg_match("/[a-z]/", $arFields["PASSWORD"])) {
					$this->LAST_ERROR .= GetMessage("MAIN_FUNCTION_REGISTER_PASSWORD_LOWERCASE")."<br>";
					$this->ERROR_IDS[] = 'USER_PASSWORD_LOWERCASE';
				}

				if(($arPolicy["PASSWORD_DIGITS"] === "Y") && !preg_match("/[0-9]/", $arFields["PASSWORD"])) {
					$this->LAST_ERROR .= GetMessage("MAIN_FUNCTION_REGISTER_PASSWORD_DIGITS")."<br>";
					$this->ERROR_IDS[] = 'USER_PASSWORD_DIGITS';
				}

				if(($arPolicy["PASSWORD_PUNCTUATION"] === "Y") && !preg_match("/[,.<>\\/?;:'\"[\\]\\{\\}\\\\|`~!@#\$%^&*()_+=-]/", $arFields["PASSWORD"])) {
					$this->LAST_ERROR .= GetMessage("MAIN_FUNCTION_REGISTER_PASSWORD_PUNCTUATION")."<br>";
					$this->ERROR_IDS[] = 'USER_PASSWORD_PUNCTUATION';
				}
			}

			if(is_set($arFields, "EMAIL"))
			{
				if(strlen($arFields["EMAIL"])<3 || !check_email($arFields["EMAIL"], true))
				{
					$this->LAST_ERROR .= GetMessage("WRONG_EMAIL")."<br>";
					$this->ERROR_IDS[] = 'USER_WRONG_EMAIL';
				}
				elseif(\COption::GetOptionString("main", "new_user_email_uniq_check", "N") === "Y")
				{
					if($ID == false || $arFields["EMAIL"] <> $oldEmail)
					{
						$res = \CUser::GetList(($b=""), ($o=""), array("=EMAIL" => $arFields["EMAIL"], "EXTERNAL_AUTH_ID"=>''));
						if($res->Fetch()) {
							$this->LAST_ERROR .= GetMessage("USER_WITH_EMAIL_EXIST", array("#EMAIL#" => htmlspecialcharsbx($arFields["EMAIL"])))."<br>";
							$this->ERROR_IDS[] = 'USER_WITH_EMAIL_EXIST';
						}
					}
				}
			}

			if(is_set($arFields, "PASSWORD") && is_set($arFields, "CONFIRM_PASSWORD") && $arFields["PASSWORD"] !== $arFields["CONFIRM_PASSWORD"]) {
				$this->LAST_ERROR .= GetMessage("WRONG_CONFIRMATION")."<br>";
				$this->ERROR_IDS[] = 'USER_WRONG_CONFIRMATION';
			}

			if (is_array($arFields["GROUP_ID"]) && count($arFields["GROUP_ID"]) > 0)
			{
				if (is_array($arFields["GROUP_ID"][0]) && count($arFields["GROUP_ID"][0]) > 0)
				{
					foreach($arFields["GROUP_ID"] as $arGroup)
					{
						if($arGroup["DATE_ACTIVE_FROM"] <> '' && !CheckDateTime($arGroup["DATE_ACTIVE_FROM"]))
						{
							$error = str_replace("#GROUP_ID#", $arGroup["GROUP_ID"], GetMessage("WRONG_DATE_ACTIVE_FROM"));
							$this->LAST_ERROR .= $error."<br>";
							$this->ERROR_IDS[] = 'USER_WRONG_DATE_ACTIVE_FROM';
						}

						if($arGroup["DATE_ACTIVE_TO"] <> '' && !CheckDateTime($arGroup["DATE_ACTIVE_TO"]))
						{
							$error = str_replace("#GROUP_ID#", $arGroup["GROUP_ID"], GetMessage("WRONG_DATE_ACTIVE_TO"));
							$this->LAST_ERROR .= $error."<br>";
							$this->ERROR_IDS[] = 'USER_WRONG_DATE_ACTIVE_TO';
						}
					}
				}
			}
		}

		if(is_set($arFields, "PERSONAL_PHOTO") && $arFields["PERSONAL_PHOTO"]["name"] == '' && $arFields["PERSONAL_PHOTO"]["del"] == '')
			unset($arFields["PERSONAL_PHOTO"]);

		if(is_set($arFields, "PERSONAL_PHOTO"))
		{
			$res = \CFile::CheckImageFile($arFields["PERSONAL_PHOTO"]);
			if($res <> '') {
				$this->LAST_ERROR .= $res."<br>";
				$this->ERROR_IDS[] = 'USER_PERSONAL_PHOTO';
			}
		}

		if(is_set($arFields, "PERSONAL_BIRTHDAY") && $arFields["PERSONAL_BIRTHDAY"] <> '' && !CheckDateTime($arFields["PERSONAL_BIRTHDAY"])) {
			$this->LAST_ERROR .= GetMessage("WRONG_PERSONAL_BIRTHDAY")."<br>";
			$this->ERROR_IDS[] = 'USER_WRONG_PERSONAL_BIRTHDAY';
		}

		if(is_set($arFields, "WORK_LOGO") && $arFields["WORK_LOGO"]["name"] == '' && $arFields["WORK_LOGO"]["del"] == '')
			unset($arFields["WORK_LOGO"]);

		if(is_set($arFields, "WORK_LOGO"))
		{
			$res = \CFile::CheckImageFile($arFields["WORK_LOGO"]);
			if($res <> '') {
				$this->LAST_ERROR .= $res."<br>";
				$this->ERROR_IDS[] = 'USER_WORK_LOGO';
			}
		}

		if(is_set($arFields, "LOGIN"))
		{
			$res = $DB->Query(
				"SELECT 'x' ".
				"FROM b_user ".
				"WHERE LOGIN='".$DB->ForSql($arFields["LOGIN"], 50)."'	".
				"	".($ID===false ? "" : " AND ID<>".intval($ID)).
				"	".(!$bInternal ? "	AND EXTERNAL_AUTH_ID='".$DB->ForSql($arFields["EXTERNAL_AUTH_ID"])."' " : " AND (EXTERNAL_AUTH_ID IS NULL OR ".$DB->Length("EXTERNAL_AUTH_ID")."<=0)")
				);

			if($res->Fetch()) {
				$this->LAST_ERROR .= str_replace("#LOGIN#", htmlspecialcharsbx($arFields["LOGIN"]), GetMessage("USER_EXIST"))."<br>";
				$this->ERROR_IDS[] = 'USER_LOGIN_EXIST';
			}
		}

		if(is_object($APPLICATION))
		{
			$APPLICATION->ResetException();

			if($ID===false)
				$events = GetModuleEvents("main", "OnBeforeUserAdd", true);
			else
			{
				$arFields["ID"] = $ID;
				$events = GetModuleEvents("main", "OnBeforeUserUpdate", true);
			}

			foreach($events as $arEvent)
			{
				$bEventRes = ExecuteModuleEventEx($arEvent, array(&$arFields));
				if($bEventRes===false)
				{
					/*if($err = $APPLICATION->GetException())
						$this->LAST_ERROR .= $err->GetString()." ";
					else
					{
						$APPLICATION->ThrowException("Unknown error");
						$this->LAST_ERROR .= "Unknown error. ";
					}*/
					$this->LAST_ERROR .= "Unknown error. ";
					$this->ERROR_IDS[] = 'UNKNOWN_ERROR';
					break;
				}
			}
		}

		if(is_object($APPLICATION))
			$APPLICATION->ResetException();
		if (!$USER_FIELD_MANAGER->CheckFields("USER", $ID, $arFields))
		{
			if(is_object($APPLICATION) && $APPLICATION->GetException())
			{
				$e = $APPLICATION->GetException();
				$this->LAST_ERROR .= $e->GetString();
				$this->ERROR_IDS[] = 'UNKNOWN_ERROR';
				$APPLICATION->ResetException();
			}
			else
			{
				$this->LAST_ERROR .= "Unknown error. ";
				$this->ERROR_IDS[] = 'UNKNOWN_ERROR';
			}
		}

		if($this->LAST_ERROR <> '')
			return false;

		return true;
	}

}
?>