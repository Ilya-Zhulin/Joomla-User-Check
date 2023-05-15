<?php

/**
 * @package  User checker Plugin
 * @author zhulinia.ru
 * @email ilya.zhulin@hotmail.com
 * @Copyright (C) 2023 Ilya A.Zhulin. All rights reserved.
 * @license    GNU/GPL v2 or later
 * @version 1.0.0
 */
// no direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class plgSystemUserCheck extends JPlugin {

	public function onUserBeforeSave($user, $isnew, $new) {
		$lang = Factory::getLanguage();
		$lang->load('plg_system_usercheck', JPATH_ADMINISTRATOR);
		if ($isnew) {
			$app		 = Factory::getApplication();
			$bad_words	 = explode(',', $this->params->get('bad_words'));
			$spaces		 = (int) $this->params->get('spaces', 5);
			$block_reg	 = (int) $this->params->get('block_reg', 1);
			$mailto		 = (int) $this->params->get('mailto', 1);
			$bad_word	 = '';
			if (substr_count($new['name'], ' ') <= $spaces) {
				foreach ($bad_words as $bw) {
					if (stripos(mb_strtolower($new['name']), mb_strtolower(trim($bw))) !== FALSE) {
						$bad_word = $bw;
					}
				}
			} else {
				$bad_word = Text::_('PLG_SYSTEM_USERCHECK_ALERT_TOO_MANY_SPACES');
			}
			if (mb_strlen($bad_word) > 0) {
				$count = $this->_returnFalse($bad_word, $new['name']);
				if ($mailto > 0) {
					$this->_sendEmail($bad_word, $new['name'], $count);
				}
				if ($block_reg > 0) {
					$app->enqueueMessage(Text::_('PLG_SYSTEM_USERCHECK_ALERT_TO_USER'), 'error');
					return FALSE;
				}
			}
		}
	}

	private function _returnFalse($bw, $string) {
		$db					 = Factory::getDbo();
		$ip					 = getenv('REMOTE_ADDR');
		$time				 = date("Y-m-d H:i:s", strtotime("+1 hour"));
		$now				 = date("Y-m-d H:i:s");
		$block_user			 = (int) $this->params->get('block_user', 1);
		$block_user_tmp		 = (int) $this->params->get('block_user_tmp', 1);
		$block_user_const	 = (int) $this->params->get('block_user_const', 3);
		$db->setQuery("SHOW TABLES LIKE '%_admintools_log'");
		$at_installed		 = $db->loadResult();
		$count				 = 0;
		if ($block_user > 0 and strlen(trim($at_installed)) > 0) {
			$db->setQuery("select count(*) from #__admintools_log where ip='$ip'");
			$count = $db->loadResult();
			$count++;
			if ($count > $block_user_const) {
				$db->setQuery('INSERT INTO `#__admintools_ipblock` (`ip`, `description`) VALUES ( "' . $ip . '", "AntiSpam by ComeOn for badword [' . $bw . '] in username [' . $string . '] during registrarion in ' . $time . '")');
				$db->execute();
				$db->setQuery('INSERT INTO `#__admintools_log` (`logdate`, `ip`, `url`,`reason`,`extradata`) VALUES ( "' . $now . '", "' . $ip . '", "/register","antispam", "AntiSpam with [' . $bw . '] in ' . $time . '")');
				$db->execute();
			} elseif ($count > $block_user_tmp) {
				$db->setQuery('INSERT INTO `#__admintools_ipautoban` (`ip`, `reason`,`until`) VALUES ( "' . $ip . '", "AntiSpam by ComeOn", "' . $time . '")');
				$db->execute();
				$db->setQuery('INSERT INTO `#__admintools_log` (`logdate`, `ip`, `url`,`reason`,`extradata`) VALUES ( "' . $now . '", "' . $ip . '", "/register","antispam", "AntiSpam with [' . $bw . '] in ' . $time . '")');
				$db->execute();
			} else {
				$db->setQuery('INSERT INTO `#__admintools_log` (`logdate`, `ip`, `url`,`reason`,`extradata`) VALUES ( "' . $now . '", "' . $ip . '", "/register","antispam", "AntiSpam with [' . $bw . '] in ' . $time . '")');
				$db->execute();
			}
		}
		return $count;
	}

	private function _sendEmail($bad_word, $name, $count = 0) {
		$server_mailfrom	 = Factory::getConfig()['mailfrom'];
		$fromname			 = Factory::getConfig()['fromname'];
		$sitename			 = Factory::getConfig()['sitename'];
		$mailto_email		 = $this->params->get('mailto_email');
		$mailto_subj		 = $this->params->get('mailto_subj', 'PLG_SYSTEM_USERCHECK_MAILTO_SUBJ_TEXT');
		$mailto_subj		 = (stripos($mailto_subj, 'PLG_SYSTEM_') === 0) ? Text::_($mailto_subj) : $mailto_subj;
		$block_user			 = (int) $this->params->get('block_user', 1);
		$block_user_tmp		 = (int) $this->params->get('block_user_tmp', 1);
		$block_user_const	 = (int) $this->params->get('block_user_const', 3);
		$mailto_logo_path	 = $this->params->get('mailto_logo');
		$body				 = '';
		$ip					 = getenv('REMOTE_ADDR');
		$time				 = date("Y-m-d H:i:s", strtotime("+1 hour"));
		$now				 = date("Y-m-d H:i:s");
		$mailto				 = [];
		if (strlen($mailto_email) > 0) {
			$mailto1 = explode(',', $mailto_email);
			foreach ($mailto1 as $mail1) {
				$mailto2 = explode(' ', trim($mail1));
				foreach ($mailto2 as $mail2) {
					$mailto3 = explode(PHP_EOL, $mail2);
					foreach ($mailto3 as $mail3) {
						$mailto[] = $mail3;
					}
				}
			}
		}
		if (strlen($mailto_logo_path) > 0 && $this->UR_exists($mailto_logo_path)) {
			$body .= '<table style="width: 100%; text-align: center;"><tr>
<td><img src="' . $mailto_logo_path . '" alt="' . $sitename . ' logo" width="299" height="102" /></td>
</tr>
</table>';
		}
		$body	 .= '<p>' . Text::_('PLG_SYSTEM_USERCHECK_MAILTO_BODY_TEXT_1') . '</p>';
		$body	 .= '<p>' . Text::_('PLG_SYSTEM_USERCHECK_MAILTO_BODY_TEXT_2') . $time . '</p>';
		$body	 .= '<p>' . Text::_('PLG_SYSTEM_USERCHECK_MAILTO_BODY_TEXT_3') . '[' . $bad_word . ']</p>';
		$body	 .= '<p>' . Text::_('PLG_SYSTEM_USERCHECK_MAILTO_BODY_TEXT_4') . '[' . $name . ']</p>';
		$body	 .= '<p>' . Text::_('PLG_SYSTEM_USERCHECK_MAILTO_BODY_TEXT_5') . $ip . '</p>';
		$body	 .= '<p>' . Text::_('PLG_SYSTEM_USERCHECK_MAILTO_BODY_TEXT_6') . $now . '</p>';
		if ($block_user > 0) {
			$body .= '<p>' . Text::_('PLG_SYSTEM_USERCHECK_MAILTO_BODY_TEXT_7') . $count . Text::_('PLG_SYSTEM_USERCHECK_MAILTO_BODY_TEXT_8') . '</p>';
			if ($count > $block_user_tmp && $count <= $block_user_const) {
				$body .= '<p>' . Text::_('PLG_SYSTEM_USERCHECK_MAILTO_BODY_TEXT_9') . '</p>';
			}
			if ($count > $block_user_const) {
				$body .= '<p>' . Text::_('PLG_SYSTEM_USERCHECK_MAILTO_BODY_TEXT_10') . '</p>';
			}
		}
		if (count($mailto) > 0) {
			Factory::getMailer()->sendMail($server_mailfrom, $fromname, $mailto, $mailto_subj, $body, 'html');
		}
	}

	private function UR_exists($url) {
		$headers = get_headers($url);
		return (stripos($headers[0], "200") !== FALSE) ? true : false;
	}

}
