<?php
/**
* A Class for connecting to a caldav server
*
* @package caldav
* @author Andrew McMillan <debian@mcmillan.net.nz>
* @copyright Andrew McMillan
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

/**
* I bet you find this hard to believe, but having to write this hack really
* annoys the crap out of me.  WTF!  Why does PHP/Curl not have a function which
* simply accepts a string as what the request will contain.  Oh no.  They only
* think of "POST" and "PUT a file".  Crap.
*
* So the following PoS code accepts that it will be called, and asked for $length
* bites of the $fd (which we ignore, because we get it all from the $_...data variable)
* and so it will eat it's way through the data.
*/
$__curl_read_callback_pos = 0;
$__curl_read_callback_data = "";
function __curl_init_callback( $data ) {
  global $__curl_read_callback_pos, $__curl_read_callback_data;
  $__curl_read_callback_pos = 0;
  $__curl_read_callback_data = $data;
}

/**
* As documented in the comments on this page(!)
*    http://nz2.php.net/curl_setopt
*/
function __curl_read_callback( $ch, $fd, $length) {
  global $__curl_read_callback_pos, $__curl_read_callback_data;

  if ( $__curl_read_callback_pos < 0 ) {
    unset($fd);
    return "";
  }

  $answer = substr($__curl_read_callback_data, $__curl_read_callback_pos, $length );
  if ( strlen($answer) < $length ) $__curl_read_callback_pos = -1;
  else $__curl_read_callback_pos += $length;

  return $answer;
}


/**
* A class for accessing DAViCal via CalDAV, as a client
*
* @package   caldav
*/
class CalDAVClient {
  /**
  * Server, username, password, calendar, $entry
  *
  * @var string
  */
  var $base_url, $user, $pass, $calendar, $entry;

  /**
  * The useragent which is send to the caldav server
  *
  * @var string
  */
  var $user_agent = 'DAViCalClient';

  var $headers = array();
  var $body = "";

  /**
  * Our cURL connection
  *
  * @var resource
  */
  var $curl;


  /**
  * Constructor, initialises the class
  *
  * @param string $base_url  The URL for the calendar server
  * @param string $user      The name of the user logging in
  * @param string $pass      The password for that user
  * @param string $calendar  The name of the calendar (not currently used)
  */
  function CalDAVClient( $base_url, $user, $pass, $calendar ) {
    $this->base_url = $base_url;
    $this->user = $user;
    $this->pass = $pass;
    $this->calendar = $calendar;

    $this->curl = curl_init();
    curl_setopt($this->curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($this->curl, CURLOPT_USERPWD, "$user:$pass" );
    curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true );
    curl_setopt($this->curl, CURLOPT_BINARYTRANSFER, true );

    $this->headers[] = array();

  }

  /**
  * Adds an If-Match or If-None-Match header
  *
  * @param bool $match to Match or Not to Match, that is the question!
  * @param string $etag The etag to match / not match against.
  */
  function SetMatch( $match, $etag = '*' ) {
    $this->headers[] = sprintf( "%s-Match: %s", ($match ? "If" : "If-None"), $etag);
  }

  /**
  * Add a Depth: header.  Valid values are 1 or infinity
  *
  * @param int $depth  The depth, default to infinity
  */
  function SetDepth( $depth = 'infinity' ) {
    $this->headers[] = "Depth: ". ($depth == 1 ? "1" : "infinity" );
  }

  /**
  * Add a Depth: header.  Valid values are 1 or infinity
  *
  * @param int $depth  The depth, default to infinity
  */
  function SetUserAgent( $user_agent = null ) {
    if ( !isset($user_agent) ) $user_agent = $this->user_agent;
    $this->user_agent = $user_agent;
  }

  /**
  * Add a Content-type: header.
  *
  * @param int $type  The content type
  */
  function SetContentType( $type ) {
    $this->headers[] = "Content-type: $type";
  }


  /**
  * Send a request to the server
  *
  * @param string $relative_url The URL to make the request to, relative to $base_url
  *
  * @return string The content of the response from the server
  */
  function DoRequest( $relative_url = "" ) {

    curl_setopt($this->curl, CURLOPT_URL, $this->base_url . $relative_url );
    curl_setopt($this->curl, CURLOPT_USERAGENT, $this->user_agent );
    curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->headers );

    /**
    * So we don't get annoyed at self-signed certificates.  Should be a setup
    * configuration thing really.
    */
    curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false );

    $bodylen = strlen($this->body);
    if ( $bodylen > 0 ) {
      /**
      * Call our magic write the data function.  You'd think there would be a
      * simple setopt call where we could set the data to be written, but no,
      * we have to pass a function, which passes the data.
      */
      curl_setopt($this->curl, CURLOPT_UPLOAD, true );
      __curl_init_callback($this->body);
      curl_setopt($this->curl, CURLOPT_INFILESIZE, $bodylen );
      curl_setopt($this->curl, CURLOPT_READFUNCTION, '__curl_read_callback' );
    }

    $this->response = curl_exec($this->curl);
    $this->resultcode = curl_getinfo( $this->curl, CURLINFO_HTTP_CODE);

    $this->headers[] = array();  // reset the headers array for our next request

    return $this->response;
  }


  /**
  * Send an OPTIONS request to the server
  *
  * @param string $relative_url The URL to make the request to, relative to $base_url
  *
  * @return array The allowed options
  */
  function DoOptionsRequest( $relative_url = "" ) {
    curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "OPTIONS" );
    $this->body = "";
    curl_setopt($this->curl, CURLOPT_HEADER, true);
    $headers = $this->DoRequest($relative_url);
    $options_header = preg_replace( '/^.*Allow: ([a-z, ]+)\r?\n.*/is', '$1', $headers );
    $options = array_flip( preg_split( '/[, ]+/', $options_header ));
    return $options;
  }



  /**
  * Send an XML request to the server (e.g. PROPFIND, REPORT, MKCALENDAR)
  *
  * @param string $method The method (PROPFIND, REPORT, etc) to use with the request
  * @param string $xml The XML to send along with the request
  * @param string $relative_url The URL to make the request to, relative to $base_url
  *
  * @return array An array of the allowed methods
  */
  function DoXMLRequest( $request_method, $xml, $relative_url = '' ) {
    $this->body = $xml;

    curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $request_method );
    curl_setopt($this->curl, CURLOPT_HEADER, false);
    $this->SetContentType("text/xml");
    return $this->DoRequest($relative_url);
  }



  /**
  * Get a single item from the server.
  *
  * @param string $relative_url The part of the URL after the calendar
  */
  function DoGETRequest( $relative_url ) {
    $this->body = "";
    curl_setopt($this->curl, CURLOPT_HTTPGET, true);
    curl_setopt($this->curl, CURLOPT_HEADER, false);
    $response = $this->DoRequest( $relative_url );
  }


  /**
  * PUT a text/icalendar resource, returning the etag
  *
  * @param string $relative_url The URL to make the request to, relative to $base_url
  * @param string $icalendar The iCalendar resource to send to the server
  * @param string $etag The etag of an existing resource to be overwritten, or '*' for a new resource.
  *
  * @return string The content of the response from the server
  */
  function DoPUTRequest( $relative_url, $icalendar, $etag = null ) {
    $this->body = $icalendar;

    curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "PUT" );
    curl_setopt($this->curl, CURLOPT_HEADER, true);
    if ( $etag != null ) {
      $this->SetMatch( ($etag != '*'), $etag );
    }
    $this->SetContentType("text/icalendar");
    $headers = $this->DoRequest($relative_url);

    /**
    * RSCDS will always return the real etag on PUT.  Other CalDAV servers may need
    * more work, but we are assuming we are running against RSCDS in this case.
    */
    $etag = preg_replace( '/^.*Etag: "?([^"\r\n]+)"?\r?\n.*/is', '$1', $headers );
    return $etag;
  }


  /**
  * DELETE a text/icalendar resource
  *
  * @param string $relative_url The URL to make the request to, relative to $base_url
  * @param string $etag The etag of an existing resource to be deleted, or '*' for any resource at that URL.
  *
  * @return int The HTTP Result Code for the DELETE
  */
  function DoDELETERequest( $relative_url, $etag = null ) {
    $this->body = "";

    curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "DELETE" );
    curl_setopt($this->curl, CURLOPT_HEADER, true);
    if ( $etag != null ) {
      $this->SetMatch( true, $etag );
    }
    $this->DoRequest($relative_url);
    return $this->resultcode;
  }


  /**
  * Given XML for a calendar query, return an array of the events (/todos) in the
  * response.  Each event in the array will have a 'href', 'etag' and '$response_type'
  * part, where the 'href' is relative to the calendar and the '$response_type' contains the
  * definition of the calendar data in iCalendar format.
  *
  * @param string $filter XML fragment which is the <filter> element of a calendar-query
  * @param string $relative_url The URL relative to the base_url specified when the calendar was opened.  Default ''.
  * @param string $report_type Used as a name for the array element containing the calendar data. @deprecated
  *
  * @return array An array of the relative URLs, etags, and events from the server.  Each element of the array will
  *               be an array with 'href', 'etag' and 'data' elements, corresponding to the URL, the server-supplied
  *               etag (which only varies when the data changes) and the calendar data in iCalendar format.
  */
  function DoCalendarQuery( $filter, $relative_url = '', $reponse_type = 'data' ) {

    $xml = <<<EOXML
<?xml version="1.0" encoding="utf-8" ?>
<calendar-query xmlns:D="DAV:" xmlns="urn:ietf:params:xml:ns:caldav">
  <D:prop>
    <href/>
    <calendar-data/>
    <D:getetag/>
  </D:prop>$filter
</calendar-query>
EOXML;

    $this->SetDepth("1");
    $this->DoXMLRequest( 'REPORT', $xml, $relative_url );
    $xml_parser = xml_parser_create_ns('UTF-8');
    $this->xml_tags = array();
    xml_parser_set_option ( $xml_parser, XML_OPTION_SKIP_WHITE, 1 );
    xml_parse_into_struct( $xml_parser, $this->response, $this->xml_tags );
    xml_parser_free($xml_parser);

    $report = array();
    foreach( $this->xml_tags AS $k => $v ) {
      switch( $v['tag'] ) {
        case 'DAV::RESPONSE':
          if ( $v['type'] == 'open' ) {
            $response = array();
          }
          elseif ( $v['type'] == 'close' ) {
            $report[] = $response;
          }
          break;
        case 'DAV::HREF':
          $response['href'] = basename( $v['value'] );
          break;
        case 'DAV::GETETAG':
          $response['etag'] = preg_replace('/^"?([^"]+)"?/', '$1', $v['value']);
          break;
        case 'URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-DATA':
          $response['data'] = $v['value'];
          $response[$report_type] = $v['value'];  // deprecated - will be removed.  Just use 'data' please :-)
          break;
      }
    }
    return $report;
  }


  /**
  * Get the events in a range from $start to $finish.  The dates should be in the
  * format yyyymmddThhmmssZ and should be in GMT.  The events are returned as an
  * array of event arrays.  Each event array will have a 'href', 'etag' and 'event'
  * part, where the 'href' is relative to the calendar and the event contains the
  * definition of the event in iCalendar format.
  *
  * @param timestamp $start The start time for the period
  * @param timestamp $finish The finish time for the period
  * @param string    $relative_url The URL relative to the base_url specified when the calendar was opened.  Default ''.
  *
  * @return array An array of the relative URLs, etags, and events, returned from DoCalendarQuery() @see DoCalendarQuery()
  */
  function GetEvents( $start, $finish, $relative_url = '' ) {
    $filter = "";
    if ( $start && $finish ) {
      $filter = <<<EOFILTER

  <filter>
    <comp-filter name="VCALENDAR">
      <comp-filter name="VEVENT">
        <time-range start="$start" end="$finish"/>
      </comp-filter>
    </comp-filter>
  </filter>
EOFILTER;
    }

    return DoCalendarQuery($filter, $relative_url, 'event');
  }


  /**
  * Get the todo's in a range from $start to $finish.  The dates should be in the
  * format yyyymmddThhmmssZ and should be in GMT.  The events are returned as an
  * array of event arrays.  Each event array will have a 'href', 'etag' and 'event'
  * part, where the 'href' is relative to the calendar and the event contains the
  * definition of the event in iCalendar format.
  *
  * @param timestamp $start The start time for the period
  * @param timestamp $finish The finish time for the period
  * @param boolean   $completed Whether to include completed tasks
  * @param boolean   $cancelled Whether to include cancelled tasks
  * @param string    $relative_url The URL relative to the base_url specified when the calendar was opened.  Default ''.
  *
  * @return array An array of the relative URLs, etags, and events, returned from DoCalendarQuery() @see DoCalendarQuery()
  */
  function GetTodos( $start, $finish, $completed = false, $cancelled = false, $relative_url = "" ) {

    if ( $start && $finish ) {
$time_range = <<<EOTIME
                <C:time-range start="$start" end="$finish"/>
EOTIME;
    }

    // Warning!  May contain traces of double negatives...
    $neg_cancelled = ( $cancelled === true ? "no" : "yes" );
    $neg_completed = ( $cancelled === true ? "no" : "yes" );

    $filter = <<<EOFILTER
  <C:filter>
    <C:comp-filter name="VCALENDAR">
          <C:comp-filter name="VTODO">
                <C:prop-filter name="STATUS">
                        <C:text-match negate-condition="$neg_completed">COMPLETED</C:text-match>
                </C:prop-filter>
                <C:prop-filter name="STATUS">
                        <C:text-match negate-condition="$neg_cancelled">CANCELLED</C:text-match>
                </C:prop-filter>$time_range
          </C:comp-filter>
    </C:comp-filter>
  </C:filter>
EOFILTER;

    return DoCalendarQuery($filter, $relative_url, 'todo');
  }


  /**
  * Get the calendar entry by UID
  *
  * @param uid
  * @param string    $relative_url The URL relative to the base_url specified when the calendar was opened.  Default ''.
  *
  * @return array An array of the relative URL, etag, and calendar data returned from DoCalendarQuery() @see DoCalendarQuery()
  */
  function GetEntryByUid( $uid, $relative_url = '' ) {
    $filter = "";
    if ( $uid ) {
      $filter = <<<EOFILTER
  <C:filter>
    <C:comp-filter name="VCALENDAR">
          <C:comp-filter name="VTODO">
                <C:prop-filter name="UID">
                        <C:text-match icollation="i;octet">$uid</C:text-match>
                </C:prop-filter>
          </C:comp-filter>
    </C:comp-filter>
  </C:filter>
EOFILTER;
    }

    return DoCalendarQuery($filter, $relative_url);
  }


  /**
  * Get the calendar entry by HREF
  *
  * @param string    $href         The href from a call to GetEvents or GetTodos etc.
  * @param string    $relative_url The URL relative to the base_url specified when the calendar was opened.  Default ''.
  *
  * @return string The iCalendar of the calendar entry
  */
  function GetEntryByHref( $href, $relative_url = '' ) {
    return DoGETRequest( $relative_url . $href );
  }

}

/**
* Usage example

$cal = new CalDAVClient( "http://calendar.example.com/caldav.php/username/calendar/", "username", "password", "calendar" );
$options = $cal->DoOptionsRequest();
if ( isset($options["PROPFIND"] ) {
  // Fetch some information about the events in that calendar
  $cal->SetDepth(1);
  $folder_xml = $cal->DoXMLRequest("PROPFIND", '<?xml version="1.0" encoding="utf-8" ?><propfind xmlns="DAV:"><prop><getcontentlength/><getcontenttype/><resourcetype/><getetag/></prop></propfind>' );
}
// Fetch all events for February
$events = $cal->GetEvents("20070101T000000Z","20070201T000000Z");
foreach ( $events AS $k => $event ) {
  do_something_with_event_data( $event['data'] );
}
*/
