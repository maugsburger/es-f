<?php
/**
 * Module RSS plugin
 *
 * @ingroup    Plugin
 * @ingroup    Module-RSS
 * @author     Knut Kohl <knutkohl@users.sourceforge.net>
 * @copyright  2009 Knut Kohl
 * @license    GNU General Public License http://www.gnu.org/licenses/gpl.txt
 * @version    $Id: v2.4.1-62-gb38404e 2011-01-30 22:35:34 +0100 $
 * @revision   $Rev$
 */
class esf_Plugin_Module_RSS extends esf_Plugin {

  /**
   * @return array Array of events handled by the plugin
   */
  public function handles() {
    return array('LanguageSet', 'Start', 'OutputStart');
  }

  /**
   *
   */
  function Start() {
    ModuleRequireModule( 'RSS', 'Auction', '0.6.0' );
  }

  /**
   * Add alternate link for RSS feed
   */
  function OutputStart() {
    if (!$user = esf_User::getActual()) return;

    TplData::add('HtmlHeader.Raw',
      sprintf('<link rel="alternate" type="application/rss+xml "'
             .'href="index.php?module=rss&amp;%1$s=%3$s" '
             .'title="RSS Feed of auctions for %2$s">'."\n",
              urlencode(APPID), $user, urlencode(Core::$Crypter->encrypt($user))));

    // Add RSS icon to footer, disable footer link in mobile layouts
    if (Session::get('Mobile') AND !$this->Mobile) return;

    $data = array(
      'APPID'   => APPID,
      'URLUSER' => Core::$Crypter->encrypt($user),
      'USER'    => $user
    );
    TplData::add('POWERED_BEFORE', ParseModuleTemplate('rss', 'inc.footer', $data));
  }
}

Event::attach(new esf_Plugin_Module_RSS);