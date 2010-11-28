<?php
/**
 * Check for new esniper releases
 *
 * @category   Plugin
 * @package    Plugin-esniperVersion
 * @author     Knut Kohl <knutkohl@users.sourceforge.net>
 * @copyright  2009 Knut Kohl
 * @license    http://www.gnu.org/licenses/gpl.txt GNU General Public License
 * @version    Release: @package_version@
 */
class esf_Plugin_esniperVersion extends esf_Plugin {

  /**
   * @return array Array of events handled by the plugin
   */
  public function handles() {
    return array('ProcessStart');
  }

  /**
   * Handle ProcessStart notofication
   */
  public function ProcessStart() {
    $file = Registry::get('RunDir').'/.esniper-version';
    if (!Session::get('esniperVersion') OR                 /* once per session */
        $_SERVER['REQUEST_TIME'] > File::MTime($file)+60*60*6 /* every 6 hours */) {
      // read esniper version
      $cmd = array('ESNIPERVERSION::VERSION', Registry::get('bin_esniper'));
      // alarm in case of new version
      if (Exec::getInstance()->ExecuteCmd($cmd, $res) OR count($res) > 1)
        Messages::addError($res);
      $ver = trim(implode("\n", $res));
      file_put_contents($file, $ver);
      Session::set('esniperVersion', $ver);
    }
    // once per script run
    Event::dettach($this);
  }
}

Event::attach(new esf_Plugin_esniperVersion);