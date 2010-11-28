<?php
/**
 * @package    es-f
 * @author     Knut Kohl <knutkohl@users.sourceforge.net>
 * @copyright  2007-2010 Knut Kohl
 * @license    http://www.gnu.org/licenses/gpl.txt GNU General Public License
 * @version    $Id$
 */

// Configurations
Loader::Load(APPDIR.'/init.php');
Loader::Load(LOCALDIR.'/custom/init.php', TRUE, FALSE);

HTMLPage::$Debug = (DEVELOP OR Registry::get('cURL.Verbose'));

// include functions
Loader::Load(APPDIR.'/functions.php');

// Emulate register_globals off
unregister_GLOBALS();

checkDir(TEMPDIR);

// >> Debug
$sDebugFile = np('%s/%s.debug', TEMPDIR, APPID);
if (isset($_GET['DEBUG'])) {
  touch($sDebugFile);
} elseif (isset($_GET['TRACE'])) {
  $_TRACE = np('%s/trace-%s-%s.csv', TEMPDIR, ESF_VERSION, date('Ymd-Hi'));
  File::write($sDebugFile, $_TRACE);
} elseif (isset($_GET['STOP'])) {
  @unlink($sDebugFile);
  Messages::addInfo('Debug off');
}

define('_DEBUG', file_exists($sDebugFile));

$_TRACE = _DEBUG ? file_get_contents($sDebugFile) : FALSE;

#DebugStack::$TimeUnit = DebugStack::MICROSECONDS;
DebugStack::Active(_DEBUG);

if ($_TRACE) {
  Messages::addSuccess('Debug trace is active: '.$_TRACE, TRUE);
} elseif (_DEBUG) {
  Messages::addSuccess('Debug active!', TRUE);
}
unset($sDebugFile);
// << Debug

/// DebugStack::Info('Used cache: '.Registry::get('CacheClass', 'FileCombined'));
$aCacheOptions = array('CacheDir'=>TEMPDIR, 'Token'=>'es-f');
$oCache = Cache::Init(Registry::get('CacheClass', 'FileCombined'), $aCacheOptions);
unset($aCacheOptions);

if (Registry::get('ClearCache')) $oCache->clear();

$oXML = new XML_Array_Configuration($oCache);
$aConfiguration = $oXML->ParseXMLFile(LOCALDIR.'/config/config.xml');
if (!$aConfiguration) die($oXML->Error);

foreach ($aConfiguration['users'] as $aUser)
  esf_User::set($aUser['name'], $aUser['auth']);
unset($aConfiguration['users']);

foreach ($aConfiguration['esniper'] as $key => $value)
  Esniper::set($key, $value);
unset($aConfiguration['esniper']);

// Set all other into registry
Registry::set($aConfiguration);
unset($oXML, $aConfiguration, $aUser, $key, $value);

if (Registry::get('CfgVersion') < ESF_CONFIG_VERSION) {
  $oCache->clear();
  Header('Location: setup/index.php?msg='
        .urlencode('Need reconfiguration because of configuration changes!'));
}
if (count(esf_User::getAll()) == 0) {
  $oCache->clear();
  Header('Location: setup/index.php?msg='
        .urlencode('At least one user account have to be defined!'));
}

esf_Extensions::Init();

Loader::Load(APPDIR.'/ebay.php');

// include additional configuration, mostly for development
Core::ReadConfigs('local');

ErrorHandler::register(Registry::get('ErrorHandler', 'default'));
// >> Debug
DebugStack::Register();
// << Debug

// since PHP 5.1.0
if (function_exists('date_default_timezone_set'))
  // http://php.net/manual/en/timezones.php
  date_default_timezone_set(Registry::get('TimeZone'));

$sPath = dirname($_SERVER['PHP_SELF']);
if (substr($sPath, -1) != '/') $sPath .= '/';
define('BASEHTML', sprintf('%s://%s%s', ServerProtocol(), $_SERVER['HTTP_HOST'], $sPath));
unset($sPath);

TplData::setConstant('BASEHTML', BASEHTML);

Loader::Load('module/modules.php');
Loader::Load('plugin/plugins.php');

$oExec = Exec::getInstance();

// Load core and custom Exec commands
$oExec->setCommandsFromXMLFile(sprintf('%s/exec.%s.xml', APPDIR, ESF_OS));
$oExec->setCommandsFromXMLFile(BASEDIR.'/local/custom/exec.xml', FALSE);

if (IniFile::Parse(APPDIR.'/language/languages.ini')) {
  $esf_Languages = IniFile::$Data;
} else {
  Messages::addError(IniFile::$Error);
  $esf_Languages = array('en' => 'English');
}

/// DebugStack::StartTimer('LoadPlugins', 'Load plugins');

Core::ReadConfigs(esf_Extensions::MODULE);

Core::IncludeSpecial(esf_Extensions::MODULE, 'plugin.class', TRUE);

// include all plugin configs
################################
Core::IncludeSpecial(esf_Extensions::PLUGIN, 'config');
Core::ReadConfigs(esf_Extensions::PLUGIN);

Event::ProcessInform('PluginConfigsLoaded');

Core::IncludeSpecial(esf_Extensions::PLUGIN, 'plugin.class');

Event::ProcessInform('PluginsLoaded');
Event::ProcessInform('ModuleConfigsLoaded');

/// DebugStack::StopTimer('LoadPlugins');

################################
Core::StartSession();

Event::ProcessInform('SessionStarted');

if (Session::get('Mobile')) Registry::set('Layout', 'mobile');

if (PluginEnabled('Validate')) {
  DefineValidator('module', 'Regex', array('pattern'=>'\w*'));
  DefineValidator('action', 'Regex', array('pattern'=>'\w*'));
}

/// DebugStack::StartTimer('AnalyseRequestParams', 'Analyse request parameters');

//_dbg($_COOKIE, '$_COOKIE');
//_dbg($_SERVER, '$_SERVER');
//_dbg($_SESSION);
//_dbg($_REQUEST);

Core::StripSlashes($_REQUEST);

/// DebugStack::Debug('$_REQUEST : '.print_r($_REQUEST, TRUE));

if (strtoupper($_SERVER['REQUEST_METHOD']) == 'GET') {
  /// DebugStack::Debug('$_GET before: '.print_r($_GET, TRUE));
  Core::StripSlashes($_GET);
  Event::Process('UrlUnRewrite', $_GET);
  Event::Process('AnalyseRequest', $_GET);
  /// DebugStack::Debug('$_GET after analyse: '.print_r($_GET, TRUE));
  $_POST = array();
} else {
  /// DebugStack::Debug('$_POST : '.print_r($_POST, TRUE));
  Core::StripSlashes($_POST);
  Event::Process('UrlUnRewrite', $_POST);
  Event::Process('AnalyseRequest', $_POST);
  /// DebugStack::Debug('$_POST after analyse: '.print_r($_POST, TRUE));
  $_GET = array();
}
Event::Process('UrlUnRewrite', $_REQUEST);
Event::Process('AnalyseRequest', $_REQUEST);

// analyse request
Registry::set('esf.Module', checkR('module', Registry::get('StartModule')));
$sModule = Registry::get('esf.Module');

// check module for mobile capability
if ($sModule != Registry::get('StartModule') AND
    Session::get('Mobile') AND
    Registry::get('Module.'.$sModule.'.Mobile') === FALSE)
  Core::Redirect(Core::URL(array('module'=>Registry::get('StartModule'))));

Registry::set('esf.Action',      checkR('action',      'index'));
Registry::set('esf.contentonly', checkR('contentonly', FALSE));

/// DebugStack::StopTimer('AnalyseRequestParams');

// ----------------------------------------------------------------------------
// initialize application
// ----------------------------------------------------------------------------
Event::ProcessInform('Start');

Session::checkRequest('language', Registry::get('language'));

// only initiate empty array data to mark as array, empty strings not required
TplData::set('HtmlHeader.JS', array());
TplData::set('HtmlHeader.CSS', array());
TplData::set('HtmlHeader.Script', array());

/// DebugStack::StartTimer('CoreLangLoad', 'Load core languages');

$sLanguage = Session::get('language');

if (Registry::get('EnglishAsDefault') AND $sLanguage != 'en') {
  // load as default english texts and config
  foreach (glob(APPDIR.'/language/*en.tmx') as $file)
    Translation::LoadTMXFile($file, 'en', $oCache);
  // Settings
  Loader::Load(APPDIR.'/language/en.php');
}

// include translation
if (file_exists(APPDIR.'/language/core.'.$sLanguage.'.tmx')) {
  foreach (glob(APPDIR.'/language/*'.$sLanguage.'.tmx') as $file)
    Translation::LoadTMXFile($file, $sLanguage, $oCache);
  // Settings
  Loader::Load(APPDIR.'/language/'.$sLanguage.'.php');
} else {
  Messages::addError(sprintf('Unknown language [%s]! Fallback to english!', $sLanguage));
  Session::set('language', 'en');
}
unset($sLanguage);

if ($locale = Session::get('locale')) {
  setlocale(LC_ALL, $locale);
} else {
  if (!setlocale(LC_ALL, Registry::get('locale'))) {
    Messages::addError(sprintf('Locale <tt>['.Registry::get('locale').']</tt> not found on your system! '
                              .'Please <a href="setup/">reconfigure</a> your system and select a correct locale!'),
                       TRUE);
    Messages::addInfo(sprintf('Fall back for now to locale <tt>[%s]</tt>.', setlocale(LC_ALL, 0)), TRUE);
  }
  Session::set('locale', setlocale(LC_ALL, 0));
}

/// DebugStack::StopTimer('CoreLangLoad');

// Init template engine
$oTemplate = esf_Template::getInstance();
if (Registry::get('ClearCache')) $oTemplate->Template->ClearCache();

// check if requested module is enabled
if (!ModuleEnabled($sModule)) {
  if ($sModule == Registry::get('StartModule')) {
    die('<p>Default module "'.$sModule.'" is disabled!</p>'
       .'<p>Please <a href="setup/">configure</a> a different default module!</p>');
  }
  Messages::addError(Translation::get('Core.ModuleNotFound', $sModule));
  Core::Redirect(Core::URL(array('module'=>Registry::get('StartModule'))));
} elseif (!Core::CheckRequired('module', $sModule, $Err)) {
  Messages::addError($Err);
  Core::Redirect(Core::URL(array('module'=>Registry::get('StartModule'))));
}

/// DebugStack::StartTimer('MoreLangLoad', 'Load plugin / module languages');

$sLanguage = Session::get('language');

foreach (esf_Extensions::$Types as $Scope) {
  foreach (esf_Extensions::getExtensions($Scope) as $Extension) {
    if (esf_Extensions::checkState($Scope, $Extension, esf_Extensions::BIT_ENABLED) AND
        ($Scope != esf_Extensions::MODULE OR
         !Registry::get('Module.'.$Extension.'.LoginRequired') OR
         esf_User::isValid())) {
      $path = sprintf(BASEDIR.'%1$s%2$s%1$s%3$s%1$s', DIRECTORY_SEPARATOR, $Scope, $Extension);
      if (Registry::get('EnglishAsDefault') AND $sLanguage != 'en') {
        // include as default all english texts
        foreach (glob($path.'language/*en.tmx') as $file) {
          Translation::LoadTMXFile($file, 'en', $oCache);
        }
      }
      if (!Registry::get('EnglishAsDefault') OR $sLanguage != 'en') {
        // include only for enabled modules and plugins
        foreach (glob($path.'language/*'.$sLanguage.'.tmx') as $file) {
          Translation::LoadTMXFile($file, $sLanguage, $oCache);
        }
      }
    }
  }
}

/// DebugStack::StopTimer('MoreLangLoad');

// ----------------------------------------------------------------------------
// pre process
// ----------------------------------------------------------------------------
if (esf_User::isValid()) esf_Auctions::Load();

Event::ProcessInform('PageStart');

$sModuleActions = Registry::get('Module.'.$sModule.'.Actions');
if (empty($sModuleActions)) $sModuleActions = 'index';

/**
 * TODO: >> esfmodule.class.php
 *
$aModuleActions = explode('|', $sModuleActions);
// supress not supported module actions
if (!in_array(Registry::get('esf.Action'), $aModuleActions)) {
  trigger_error(sprintf('Unknown module action: %s/%s!',
                        $sModule, Registry::get('esf.Action')));
  Registry::set('esf.Action', $aModuleActions[0]);
}
*/
unset($sModuleActions, $aModuleActions);

if (!esf_User::isValid() AND
    Registry::get('Module.'.$sModule.'.LoginRequired')) {
  /// DebugStack::Info('Forward: '.$sModule.' -> Login');
  if ($sModule != 'login') {
    unset($_REQUEST['ESFSESSID']);
    Session::setP('LoginReturn', $_REQUEST);
  }
  Core::Forward('login');
} elseif (esf_User::isValid() AND $sLoginReturn = Session::getP('LoginReturn')) {
  Session::setP('LoginReturn');
  Core::Forward(NULL, NULL, $sLoginReturn);
}

$sModule = Registry::get('esf.Module');

// generic styles/scripts, from defined layout or fallback layout
TplData::add('HtmlHeader.raw', StylesAndScripts('.', Registry::get('Layout')));

if (strtoupper($_SERVER['REQUEST_METHOD']) == 'GET')
  Session::set('returnto', @$_REQUEST['returnto']);

$sModuleLast = FALSE;

// module preparation
do {

  $sTitle = Translation::getNVL($sModule.'.Title', ucwords($sModule));
  TplData::set('Title', $sTitle);
  TplData::set('SubTitle1', $sTitle);
  /// TplData::set('SubTitle1',
  ///              '<tt style="color:red"><img src="layout/images/devel.png" alt="[DEV]">&nbsp;</tt>'.$sTitle);
  unset($sTitle);

  TplData::set('SubTitle2', '');

  // >> Debug
  DebugStack::Info('Processing '.$sModule.' / '.Registry::get('esf.Action'));
  // << Debug

  if ($sModuleLast != $sModule) unset($oModule);

  $sModuleLast = $sModule;

  if (!isset($oModule) AND
      Loader::Load('module/'.$sModule.'/module.class.php', TRUE, FALSE)) {
    $sClass = 'esf_Module_'.$sModule;
    $oModule = new $sClass;
  }
  if (isset($oModule)) $oModule->handle(Registry::get('esf.Action'));

  // handle ReturnTo=...
  $aReturnTo = decodeReturnTo(Session::get('returnto'));
  if (!empty($aReturnTo) AND
      (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST' OR isset($aReturnTo['force']))) {

    // >> Debug
    //_dbg($aReturnTo, 'ReturnTo');
    // << Debug

    unset($aReturnTo['force']);

    foreach ($aReturnTo as $param => $value) {
      switch ($param) {
        case 'module':
          Registry::set('esf.Module', $value);
          $sModule = $value;
          break;
        case 'action':
          Registry::set('esf.Action', $value);
          break;
        default:
          $_REQUEST[$param] = $value;
      }
    }
    Session::set('returnto');
  }

  $sModuleLayout = FindActualLayout(Registry::get(esf_Extensions::MODULE.'.'.$sModule.'.Layout', 'default'));

  // module specific styles/scripts, from defined layout
  TplData::add('HtmlHeader.raw', StylesAndScripts('module/'.$sModule, $sModuleLayout));

  // loop until no more forwarded
} while ($sModuleLast != $sModule);
unset($sModuleLast);

// ----------------------------------------------------------------------------
// process
// ----------------------------------------------------------------------------

Event::ProcessInform('ProcessStart');

if (esf_User::isValid()) {
  // reread auctions AND GROUPS!!
  esf_Auctions::Load();

  if ($sLastUpdate = Event::ProcessReturn('getLastUpdate')) {
    TplData::set('LastUpdate', strftime(Registry::get('Format.DateTimeS'), $sLastUpdate));
    unset($sLastUpdate);
  }
  TplData::set('User', esf_User::getActual());
}

TplData::setConstant('ESF.TITLE', ESF_TITLE);
TplData::setConstant('ESF.SLOGAN', ESF_SLOGAN);
TplData::setConstant('ESF.VERSION', ESF_VERSION);
TplData::setConstant('ESF.RELEASE', ESF_RELEASE);
TplData::setConstant('ESF.HOMEPAGE', ESF_HOMEPAGE);
TplData::setConstant('ESF.AUTHOR', ESF_AUTHOR);
TplData::setConstant('ESF.EMAIL', ESF_EMAIL);
TplData::setConstant('ESF.LONG_TITLE', ESF_LONG_TITLE);
TplData::setConstant('ESF.FULL_VERSION', ESF_FULL_VERSION);
TplData::setConstant('ESF.FULL_TITLE', ESF_FULL_TITLE);
TplData::setConstant('ESF.APPID', APPID);
TplData::setConstant('ESF.LANGUAGE', Session::get('language'));
TplData::setConstant('YEAR', date('Y'));
TplData::setConstant('ESNIPER.VERSION', Session::get('esniperVersion'));
TplData::setConstant('YUELO_VERSION', 'Yuelo - Template engine V. '.Yuelo::VERSION);
TplData::setConstant('PHP.VERSION', PHP_VERSION);

if (stristr($_SERVER['SERVER_SOFTWARE'], 'Apache')) {
  TplData::setConstant('SERVER', array('NAME' => 'apache', 'URL' => 'www.apache.org'));
} elseif (stristr($_SERVER['SERVER_SOFTWARE'], 'lighttpd')) {
  TplData::setConstant('SERVER', array('NAME' => 'lighttpd', 'URL' => 'www.lighttpd.net'));
} elseif (stristr($_SERVER['SERVER_SOFTWARE'], 'IIS')) {
  TplData::setConstant('SERVER', array('NAME' => 'iis', 'URL' => 'www.microsoft.com/iis'));
} else {
  preg_match('~^[\w\s]+~', $_SERVER['SERVER_SOFTWARE'], $args);
  TplData::setConstant('SERVER', array('NAME' => trim($args[0]), 'URL' => NULL));
}

TplData::setConstant('SERVER.VERSION', $_SERVER['SERVER_SOFTWARE']);

TplData::set('Ebay_Homepage', Registry::get('ebay.Homepage'));
TplData::set('Layouts', getLayouts());
TplData::set('GetCategoryFromGroup', FROMGROUP);

TplData::set('FormAction', Core::URL(array('module'=>$sModule)));
TplData::set('NoJS', Registry::get('NoJS'));
TplData::set('Layout', Registry::get('LAYOUT'));

if (_DEBUG) {
  TplData::add('HtmlHeader.CSS', 'application/lib/debugstack/style.css');
  TplData::add('HtmlHeader.JS', 'application/lib/debugstack/script.js');
}

$bc = Translation::getNVL($sModule.'.TitleIndex', TplData::get('Title'));

// ----------------------------------------------------------------------------
// post process / output
// ----------------------------------------------------------------------------
// load additional layout specific code for all layouts, if exists
$path = 'module/'.$sModule.'/layout/';
Loader::Load($path . 'layout.php', TRUE, FALSE);
// for actual module layout, if exists
Loader::Load($path . $sModuleLayout.'.php', TRUE, FALSE);

foreach (esf_Extensions::$Types as $ExtType) {
  foreach (esf_Extensions::getExtensions($ExtType) as $Ext) {
    $ExtKey = strtoupper($ExtType).'S.'.strtoupper($Ext);
    $enabled = esf_Extensions::checkState($ExtType, $Ext, esf_Extensions::BIT_ENABLED);
    TplData::setConstant($ExtKey.'.ENABLED', $enabled);
    if ($enabled AND $vars = Registry::get($ExtType.'.'.$Ext)) {
      foreach ($vars as $key => $val) {
        $key = strtoupper($key);
        if (strpos('|NAME|AUTHOR|EMAIL|VERSION|', "|$key|") === FALSE) {
          TplData::setConstant($ExtKey.'.'.$key, $val);
        }
      }
    }
  }
}

// put actual module configuration in separate (common) array
TplData::set('MODULE', $sModule);
foreach (getModuleVar($sModule) as $key => $val)
  TplData::setConstant('MODULE.'.strtoupper($key), $val);
unset($ExtType, $Event, $pExt, $vars, $key, $val);

Event::ProcessInform('OutputStart');

/// DebugStack::StartTimer('LoadPluginStyles', 'Load plugin styles and scripts');

// plugin specific styles/scripts, from defined layout or fallback layout
foreach (esf_Extensions::getExtensions(esf_Extensions::PLUGIN) as $plugin) {
  if (PluginEnabled($plugin)) {
    $PluginLayout = FindActualLayout(Registry::get(esf_Extensions::PLUGIN.'.'.$plugin.'.Layout', 'default'));
    TplData::add('HtmlHeader.raw', StylesAndScripts('plugin/'.$plugin, $PluginLayout));
  }
}

/// DebugStack::StopTimer('LoadPluginStyles');
/// DebugStack::StartTimer('BuildMenus', 'Build menus');

// language selectors
foreach ((array)$esf_Languages as $name => $desc) {
  TplData::set('Language.'.$name.'.Name', $name);
  TplData::set('Language.'.$name.'.Desc', $desc);
  TplData::set('Language.'.$name.'.URL',  Core::URL(array('params'=>array('language' => $name))));
}

// build menu
$menustyle = Registry::get('MenuStyle');
if (!is_array($menustyle)) $menustyle = explode(',', $menustyle);

Event::ProcessInform('BuildMenu');

TplData::set('Menu.Main',   esf_Menu::getMain($menustyle[0]));
TplData::set('Menu.Module', esf_Menu::getModule($menustyle[1]));
TplData::set('Menu.System', esf_Menu::getSystem($menustyle[2]));

/// DebugStack::StopTimer('BuildMenus');
/// DebugStack::StartTimer('esniperBugs', 'Check for esniper bug reports');

if (esf_User::isValid()) {
  // check for encountered esniper bug
  $aBugReports = glob(np(esf_User::UserDir().'/esniper.*.html'));
  $sBugDir = np(esf_User::UserDir().'/esniper.bug/');
  if (count($aBugReports)) {
    checkDir($sBugDir);
    foreach ($aBugReports as $sFile) {
      $sTo = sprintf('%s%s', $sBugDir, basename($sFile));
      if ($oExec->Move($sFile, $sTo, $sResult)) {
        Messages::addError($sResult);
      }
    }
    Messages::addError(Translation::get('Core.EsniperEncounteredBug', $sBugDir));
  }
}
unset($aBugReports, $sBugDir, $sFile, $sTo, $sResult);

/// DebugStack::StopTimer('esniperBugs');
/// DebugStack::StartTimer('HTMLHead');

if (!DEVELOP) ob_start();

Yuelo::set('Language', Session::get('Language'));
Yuelo::set('Layout', $sModuleLayout);
$RootDir = array(
  BASEDIR.'/module/'.$sModule.'/layout',
  BASEDIR.'/layout',
);

$oTemplate->Output('doctype', FALSE, $RootDir);

$html = $oTemplate->Render('html.head', TRUE, $RootDir);
Event::Process('OutputFilterHtmlHead', $html);
Event::Process('OutputFilter', $html);
echo $html;

/// DebugStack::StopTimer('HTMLHead');
/// DebugStack::StartTimer('HTMLStart');

if (!Registry::get('esf.contentonly')) {
  TplData::set('esf_Messages', implode((array)Messages::get()));
}

$html = $oTemplate->Render('html.start', TRUE, $RootDir);
Event::Process('OutputFilterHtmlStart', $html);
Event::Process('OutputFilter', $html);
echo $html;

$steps = Registry::get('esf.contentonly')
       ? array('content')
       : array('header', 'content', 'footer');

/// DebugStack::StopTimer('HTMLStart');

if (!DEVELOP) ob_end_flush();

/// DebugStack::StartTimer('IndexSteps', 'HTML Content steps');

foreach ($steps as $step) {
  /// DebugStack::StartTimer($step.'block', ucwords($step).' block');

  Event::ProcessInform('Output'.$step);

  if (isset($oModule)) $oModule->handle(Registry::get('esf.Action'), $step);

  if (!DEVELOP) ob_start();

  if ($step == 'content') {

/*
    TplData::set('esf_Messages', implode((array)Messages::get()));

    if (!Registry::get('esf.contentonly')) {
      $html = $oTemplate->Render('html.messages', TRUE, $RootDir);
      Event::Process('OutputFilter', $html);
      echo $html;
    }
*/

    // if actual module is only a display module, try to render $step.Registry::get('esf.Action') ...
    if (TplData::isEmpty('Content')) {
      $content = $oTemplate->Render('content.'.Registry::get('esf.Action'), FALSE, $RootDir);
      TplData::set('Content', $content);
    }

    // Render general template 'content', if exists
    $content = $oTemplate->Render('content', FALSE, $RootDir);
    if ($content) TplData::set('Content', $content);

  }

  $html = $oTemplate->Render('html.'.$step, TRUE, $RootDir);
  Event::Process('OutputFilter'.$step, $html);
  Event::Process('OutputFilter', $html);
  echo $html;

  if (!DEVELOP) ob_end_flush();

  /// DebugStack::StopTimer($step.'block');
}

/// DebugStack::StopTimer('IndexSteps');

if (!DEVELOP) ob_start();

if (_DEBUG) {
  DebugStack::Finalize();
  if ($_TRACE) DebugStack::Save($_TRACE);
  echo '<div id="debugstack_title"
             style="margin-top:1em;cursor:pointer;text-align:center;padding:0.25em"
             onclick="$(\'debugstack_wrap\').toggle()"><tt><strong>DebugStack</strong></tt></div>
        <div id="debugstack_wrap" style="height:40em;overflow:auto;display:none">';
  DebugStack::Output(TRUE);
  echo '</div>';
}
// << Debug

$html = $oTemplate->Render('html.end', TRUE, $RootDir);
Event::Process('OutputFilterHtmlEnd', $html);
Event::Process('OutputFilter', $html);
echo $html;

Event::ProcessInform('PageEnded');

Session::close();