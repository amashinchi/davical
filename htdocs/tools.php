<?php
/**
* Tools for manipulating calendars
*
* @package   davical
* @subpackage   RSCDSSession
* @author    Maxime Delorme <mdelorme@tennaxia.com>
* @copyright Maxime Delorme
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

require_once("../inc/always.php");
require_once("RSCDSSession.php");
$session->LoginRequired();

require_once("DataEntry.php");
require_once("interactive-page.php");
require_once("classBrowser.php");

if ( !$session->AllowedTo("Admin" ) )
  exit;

if(isset($_POST['Sync_LDAP'])){
  sync_LDAP();
}

if(isset($_POST['import_from_directory'])){
  Tools::importFromDirectory();
}


include("page-header.php");
$Tools = new Tools();

$Tools->render();
class Tools {

  function render(){
    global $c;
    echo  $this->renderImportFromDirectory();
    if($c->authenticate_hook['call'] == 'LDAP_check'){
      echo $this->renderSyncLDAP();
    }
  }

  function renderSyncLDAP(){
    $html = '<div id="entryform">';
    $html .= '<h1>'.translate('Sync LDAP with RSCDS') .'</h1>';

    $data = (object) array('directory_path' => '/path/to/your/ics/files','calendar_path' => 'home');
    $ef = new EntryForm( $_SERVER['REQUEST_URI'],$data , true,true );
    $html .= "<table width=\"100%\" class=\"data\">\n";
    $html .= $ef->StartForm( array("autocomplete" => "off" ) );
    $html .= sprintf( "<tr><td style=\"text-align:left\" colspan=\"2\" >%s</td></tr>\n",
    translate("This operation does the following: <ul><li>check valid users in LDAP directory</li>
    <li>check users in RSCDS</li></ul> then
    <ul><li>if a user is present in RSCDS but not in LDAP set him as inactive in RSCDS</li>
        <li>if a user is present in LDAP but not in RSCDS create the user in RSCDS</li>
        <li>if a user in present in LDAP and RSCDS then update information in RSCDS</li>
    </ul>"));
    $html .= "</table>\n";

    $html .= $ef->SubmitButton( "Sync_LDAP", 'submit');
    $html .= $ef->EndForm();

    $html .= "</div>";
    return $html;
  }

  function renderImportFromDirectory(){
      $html = '<div id="entryform">';
      $html .= '<h1>'.translate('Import all .ics files of a directory') .'</h1>';

      $data = (object) array('directory_path' => '/path/to/your/ics/files','calendar_path' => 'home');
      $ef = new EntryForm( $_SERVER['REQUEST_URI'],$data , true,true );
      $html .= "<table width=\"100%\" class=\"data\">\n";
      $html .= $ef->StartForm( array("autocomplete" => "off" ) );

      $html .= $ef->DataEntryLine( translate("path to store your ics"), "%s", "text", "calendar_path",
                array( "size" => 20,
                        "title" => translate("Set the path to store your ics e.g. 'home' will be referenced as /caldav.php/me/home/"),
                        "help" => translate("<b>WARNING: all events in this path will be deleted before inserting allof the ics file</b>")
                      )
                      , '' );

      $html .= $ef->DataEntryLine( translate("Directory on the server"), "%s", "text", "directory_path",
                array( "size" => 20, "title" => translate("The path on the server where your .ics files are.")));

      $html .= "</table>\n";
      $html .= $ef->SubmitButton( "import_from_directory", 'submit');
      $html .= $ef->EndForm();

      $html .= "</div>";
      return $html;
  }

  function importFromDirectory(){
    global $c;
    if(!isset($_POST["calendar_path"])){
      dbg_error_log( "importFromDirectory", "calendar path not given");
      return ;
    }
    $path_ics = $_POST["calendar_path"];
    if ( substr($path_ics,-1,1) != '/' ) $path_ics .= '/';          // ensure that we target a collection
    if ( substr($path_ics,0,1) != '/' )  $path_ics = '/'.$path_ics; // ensure that we target a collection

    if(!isset($_POST["directory_path"])){
      dbg_error_log( "importFromDirectory", "directory path not given");
      return ;
    }
    $dir = $_POST["directory_path"];
    if(!is_readable($dir)){
      $c->messages[] = sprintf(i18n('directory %s is not readable'),htmlentities($dir));
      dbg_error_log( "importFromDirectory", "directory is not readable");
      return ;
    }
    if ($handle = opendir($dir)) {
      while (false !== ($file = readdir($handle))) {
        if ($file != "." && $file != ".." && substr($file,-4) != '.ics') {
          continue;
        }
        if(!is_readable($dir.'/'.$file)){
          dbg_error_log( "importFromDirectory", "ics file '%s' is not readable",$dir .'/'.$file);
          continue;
        }
        $ics = file_get_contents($dir.'/'.$file);
        $ics = trim($ics);


        if ( $ics != '' ) {
          include_once('check_UTF8.php');
          if ( check_string($ics) ) {
            $path = "/".substr($file,0,-4).$path_ics;
            dbg_error_log( "importFromDirectory", "importing to $path");
            include_once("caldav-PUT-functions.php");
            if ( $user = getUserByName(substr($file,0,-4),'importFromDirectory',__LINE__,__FILE__)) {
              $user_no = $user->user_no;
            }
            if(controlRequestContainer(substr($file,0,-4),$user_no, $path,false) === -1)
              continue;
            import_collection($ics,$user_no,$path,1);
            $c->messages[] = sprintf(i18n("all events of user %s were deleted and replaced by those from file %s"),substr($file,0,-4),$dir.'/'.$file);
          }
          else {
            $c->messages[] =  sprintf(i18n("the file %s is not UTF-8 encoded, please check error for more details"),$dir.'/'.$file);
          }
        }
      }
      closedir($handle);
    }
  }
}

include("page-footer.php");

?>
