<?php
/**
 * Rewrite urls
 *
 * @ingroup    Module-BulkAdd
 * @author     Knut Kohl <knutkohl@users.sourceforge.net>
 * @copyright  2009-2011 Knut Kohl
 * @license    GNU General Public License http://www.gnu.org/licenses/gpl.txt
 * @version    0.1.0
 * @version    $Id: v2.4.1-62-gb38404e 2011-01-30 22:35:34 +0100 $
 */
class esf_Plugin_Module_BulkAdd extends esf_Plugin {

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
    if (!esf_User::isValid() OR !Request::check('auction')) return;

    // sub-item to auctions
    esf_Menu::addModule( array( 'module' => 'bulkadd' ) );

    Event::dettach($this);
  }
}

Event::attach(new esf_Plugin_Module_BulkAdd);