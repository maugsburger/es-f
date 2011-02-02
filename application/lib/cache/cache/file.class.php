<?php
/**
 * Cache_File extends Cache_FileBase
 */
require_once dirname(__FILE__).'/filebase.class.php';

/**
 * Class Cache_File
 *
 * Store all data into one file
 * All data will be held in memeory during the script runs
 *
 * The following settings are supported:
 * - @c token    : used to build unique cache ids (optional)
 * - @c cachedir : Where to store the file with the cached data (optional)
 *
 * @ingroup    Cache
 * @author     Knut Kohl <knutkohl@users.sourceforge.net>
 * @copyright  2007-2011 Knut Kohl
 * @license    GNU General Public License http://www.gnu.org/licenses/gpl.txt
 * @version    1.0.0
 * @version    $Id: v2.4.1-45-g3faf669 - Wed Jan 12 21:35:21 2011 +0100 $
 */
class Cache_File extends Cache_FileBase {

  // -------------------------------------------------------------------------
  // PUBLIC
  // -------------------------------------------------------------------------

  /**
   * @name Implemented abstract functions
   * @{
   */
  public function set( $id, $data, $ttl=0 ) {
    // optimized for probability Set -> Delete -> Clear
    if ($data !== NULL) {
      $this->data[$this->id($id)] = array($this->ts, $ttl, $data);
      $this->modified = TRUE;
      return TRUE;
    } elseif ($id !== NULL) { // AND $data === NULL
      return $this->delete($id);
    } else { // $id === NULL AND $data === NULL
      return $this->clear();
    }
  } // function set()

  public function get( $id, $expire=0 ) {
    $id = $this->id($id);
    // Id set?
    if (!isset($this->data[$id])) return;
    // split into store time, ttl, data
    list($ts, $ttl, $data) = $this->data[$id];
    // Data valid?
    if (isset($expire)) {
      // expiration timestamp set
      if ($expire === 0 OR
          $expire > 0 AND $this->ts+$expire >= $ts+$ttl OR
          $expire < 0 AND $ts >= -$expire) return $data;
    } else {
      // expiration timestamp NOT set
      if ($ttl === 0 OR
          $ttl > 0 AND $ts+$ttl >= $this->ts OR
          $ttl < 0 AND -$ttl >= $this->ts) return $data;
    }
    // else drop data for this key
    $this->delete($id);
  } // function get()

  public function delete( $id ) {
    $id = $this->id($id);
    if (isset($this->data[$id])) unset($this->data[$id]);
    $this->modified = TRUE;
    return TRUE;
  } // function remove()

  public function flush() {
    $this->data = array();
    // Don't just RemoveFile, if this fails, cache will remain,
    // so write empty data if it fails
    return $this->WriteFile($this->FileName(), NULL);
  } // function clear()

  public function __destruct() {
    // Save only if data was modified
    if ($this->modified)
      $this->WriteFile($this->FileName(), $this->data);
  } // function __destruct()
  /** @} */

  // -------------------------------------------------------------------------
  // PROTECTED
  // -------------------------------------------------------------------------

  /**
   * Class constructor
   *
   * @param array $settings
   * @return void
   */
  protected function __construct( $settings=array() ) {
    parent::__construct($settings);
    // Load cached data
    $this->data = $this->ReadFile($this->FileName());
    if (!is_array($this->data)) $this->data = array();
    // Data not yet modified
    $this->modified = FALSE;
  } // function __construct()

  // -------------------------------------------------------------------------
  // PRIVATE
  // -------------------------------------------------------------------------

  /**
   * Data storage
   *
   * @var array $data
   */
  private $data;

  /**
   * Save whole cache file only if at least one id was changed/deleted
   *
   * @var bool $modified
   */
  private $modified;

}
