<?php
/**
 * Rewrite urls
 *
 * @category   Plugin
 * @package    Plugin-Savings
 * @author     Knut Kohl <knutkohl@users.sourceforge.net>
 * @copyright  2009 Knut Kohl
 * @license    http://www.gnu.org/licenses/gpl.txt GNU General Public License
 * @version    0.1.0
 */
class esf_Plugin_Module_Savings extends esf_Plugin {

  /**
   * @return array Array of events handled by the plugin
   */
  public function handles() {
    return array('BuildMenu');
  }

  /**
   *
   */
  function BuildMenu() {
    // disable on mobile layouts
    if (Session::get('Mobile') AND !$this->Mobile) return;

    // require valid login
    if (!$user = esf_User::getActual() OR !Request::check('auction')) return;

    // find at least one won auction
    foreach (esf_Auctions::$Auctions as $item=>$auction) {
    	if ($auction['ended'] AND $auction['bidder'] == $user) {
        esf_Menu::addModule( array( 'module' => 'savings' ) );
        return;
      }
    }

    Event::dettach($this);
  }
}

Event::attach(new esf_Plugin_Module_Savings);