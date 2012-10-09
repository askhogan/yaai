<?php

/* * *
 * Author: Blake Robertson
 *
 * Controller class for various AJAX things such as saving the UI state and saving the call details. 
 *
 * TODO: callListener and createCall should be refactored into this php file and then called by specifying an appropriate action for them.
 */



if (!defined('sugarEntry') || !sugarEntry)
    die('Not A Valid Entry Point');

require_once('include/utils.php');
require_once('include/export_utils.php');

global $sugar_config;
global $locale;
global $current_user;


if (!defined('sugarEntry'))
    define('sugarEntry', true);

require_once('modules/Calls/Call.php');
require_once('modules/Users/User.php');

// These variables in a perfect world would be asterisk configuration
$INBOUND_CALL_ABBR = $sugar_config['asterisk_call_subject_inbound_abbr']; //"IBC"; // Inbound calls will be prefixed with this in Call Record
$OUTBOUND_CALL_ABBR = $sugar_config['asterisk_call_subject_outbound_abbr']; //"OBC";
$MORE_INDICATOR = "..."; // When memo notes are longer then max length it displays this at the end to indicate the user should open the record for the rest of the notes.
$MAX_CALL_SUBJECT_LENGTH = $sugar_config['asterisk_call_subject_max_length'];
; // Set this to the max length you want the subject to be.  MUST BE SMALLER THEN DATABASE COLUMN SIZE which is 50 by default



if ($_REQUEST['action'] == "memoSave") {

    /**
      // DEBUG Stuff
      echo 'description:'. $_POST["description"]; //assuming you defined the column "name" in vardefs.php
      if( array_key_exists("message",$_POST) )
      echo ', message:'. $_POST["message"]; //assuming you defined the column "name" in vardefs.php
      echo ', call record id:'. $_POST["call_record"]; //assuming you defined the column "name" in vardefs.php
     * */
    // Workaround See Discussion here: https://github.com/blak3r/yaai/pull/20
    if (isset($_POST["call_record"])) {
        $callRecord = $_POST["call_record"];
    } else {
        $asteriskID = preg_replace('/-/', '.', $_POST['id']);
        $query = " SELECT call_record_id FROM asterisk_log WHERE asterisk_id=\"$asteriskID\"";
        $resultSet = $current_user->db->query($query, false);
        if ($current_user->db->checkError()) {
            trigger_error("RetrieveCallRecord-Query failed: $query");
        }
        while ($row = $current_user->db->fetchByAssoc($resultSet)) {
            $callRecord = $row['call_record_id'];
        }
        //log_entry("Set ID by fetching from db: " . $callRecord, "c:/debug.txt");
    }

    $focus = new Call(); //create your module object wich extends SugarBean
    $focus->retrieve($_POST["call_record"]); // retrieve a row by its id
    // TODO there are going to be language issues in this file... replace all strings with modstring equivalents.

    if (array_key_exists("name", $_POST))
        $focus->name = $_POST["name"];

    $focus->description = $_POST["description"];

    $direction = "Outbound";
    if (!empty($_POST['direction'])) {
        $direction = $_POST['direction'];
    }

    $directionAbbr = $OUTBOUND_CALL_ABBR;
    if ($direction == "Inbound") {
        $directionAbbr = $INBOUND_CALL_ABBR;
    }

    $subject = "$direction Call"; // default subject
    // Set subject to include part of memo if notes were left.
    if (strlen($focus->description) > 0) {
        $subject = $directionAbbr . $focus->description;
        if (strlen($subject) > $MAX_CALL_SUBJECT_LENGTH) {
            //$subject = $direction . " Call (w/ notes attached)";
            $substrLen = $MAX_CALL_SUBJECT_LENGTH - (strlen($directionAbbr) + strlen($MORE_INDICATOR) + 1);
            $subject = $directionAbbr . substr($focus->description, 0, $substrLen) . $MORE_INDICATOR;
        }
    }

    $focus->name = $subject;
    $focus->save();
} else if ($_REQUEST['action'] == "updateUIState") {
    $current_language = $_SESSION['authenticated_user_language'];
    if (empty($current_language)) {
        $current_language = $sugar_config['default_language'];
    }
    require("custom/modules/Asterisk/language/" . $current_language . ".lang.php");
    $cUser = new User();
    $cUser->retrieve($_SESSION['authenticated_user_id']);

    // query log
    // Very basic santization
    $uiState = preg_replace('/[^a-z0-9\-\. ]/i', '', $_REQUEST['ui_state']); //  mysql_real_escape_string($_REQUEST['ui_state']);
    $callRecord = preg_replace('/[^a-z0-9\-\. ]/i', '', $_REQUEST['call_record']); //mysql_real_escape_string($_REQUEST['call_record']);
    $asteriskID = preg_replace('/-/', '.', $_REQUEST['id']);
    // Workaround See Discussion here: https://github.com/blak3r/yaai/pull/20
    if (isset($_REQUEST['call_record'])) {
        $query = "update asterisk_log set uistate=\"$uiState\" where call_record_id=\"$callRecord\"";
    } else {
        $query = "update asterisk_log set uistate=\"$uiState\" where asterisk_id=\"$asteriskID\"";
    }

    $resultSet = $cUser->db->query($query, false);
    if ($cUser->db->checkError()) {
        trigger_error("Update UIState-Query failed: $query");
    }
} else if ($_REQUEST['action'] == "setContactId") {
    $current_language = $_SESSION['authenticated_user_language'];
    if (empty($current_language)) {
        $current_language = $sugar_config['default_language'];
    }
    require("custom/modules/Asterisk/language/" . $current_language . ".lang.php");
    $cUser = new User();
    $cUser->retrieve($_SESSION['authenticated_user_id']);

    // query log
    // Very basic santization
    $contactId = preg_replace('/[^a-z0-9\-\. ]/i', '', $_REQUEST['contact_id']);   // mysql_real_escape_string($_REQUEST['ui_state']);
    $callRecord = preg_replace('/[^a-z0-9\-\. ]/i', '', $_REQUEST['call_record']); // mysql_real_escape_string($_REQUEST['call_record']);
    $asteriskID = preg_replace('/-/', '.', $_REQUEST['id']);
    // Workaround See Discussion here: https://github.com/blak3r/yaai/pull/20
    if (isset($_REQUEST['call_record'])) {
        $query = "update asterisk_log set contact_id=\"$contactId\" where call_record_id=\"$callRecord\"";
    } else {
        $query = "update asterisk_log set contact_id=\"$contactId\" where asterisk_id=\"$asteriskID\"";
    }

    $resultSet = $cUser->db->query($query, false);
    if ($cUser->db->checkError()) {
        trigger_error("Update setContactId-Query failed: $query");
    }

    // Adds the new relationship!  (This must be done here in case the call has already been hungup as that's when asteriskLogger sets relations)
    $focus = new Call();
    $focus->retrieve($callRecord);
    $focus->load_relationship('contacts');
    // Remove any contacts already associated with call (if there are any)
    foreach ($focus->contacts->getBeans() as $contact) {
        $focus->contacts->delete($callRecord, $contact->id);
    }
    $focus->contacts->add($contactId); // Add the new one!
    $contactBean = new Contact();
    $contactBean->retrieve($contactId);
    $focus->parent_id = $contactBean->account_id;
    $focus->parent_type = "Accounts";
    $focus->save();
} else if ($_REQUEST['action'] == "call") {

// TODO: For some reason this code isn't working... I think it's getting the extension.
// For the time being, callCreate is still being used.	

    /*
      $cUser = new User();
      $cUser->retrieve($_SESSION['authenticated_user_id']);
      $extension = $cUser->asterisk_ext_c;

      //$extension = $current_user->asterisk_ext_c;
      $context = $sugar_config['asterisk_context'];

      // Take the user supplied pattern, we find the part with the #'s (which are the ext)... then we get something like
      // asterisk_dialout_channel == "SIP/###"   --> $matches[1] == SIP/, $matches[2] == "###", $matches[3] is "".
      // asterisk_dialout_channel == "Local/###@sugarsip/n"   --> $matches[1] == Local/, $matches[2] == "###", $matches[3] is "@sugarsip/n".
      preg_match('/([^#]*)(#+)([^#]*)/',$sugar_config['asterisk_dialout_channel'],$matches);
      $channel = $matches[1] . $extension . $matches[3];

      //format Phone Number
      $number = $_REQUEST['phoneNr'];
      $prefix = $sugar_config['asterisk_prefix'];
      $number = str_replace("+", "00", $number);
      $number = str_replace(array("(", ")", " ", "-", "/", "."), "", $number);
      $number = $prefix.$number;


      // dial number
      $cmd = "";
      $cmd .=  "Action: originate\r\n";
      $cmd .=  "Channel: ". $channel ."\r\n";
      $cmd .=  "Context: ". $context ."\r\n";
      $cmd .=  "Exten: " . $number . "\r\n";
      $cmd .=  "Priority: 1\r\n";
      $cmd .=  "Callerid:" . $_REQUEST['phoneNr'] ."\r\n";
      $cmd .=  "Variable: CALLERID(number)=" . $extension . "\r\n\r\n";

      SendAMICommand($cmd);
     */
} else if ($_REQUEST['action'] == "transfer") {

    $exten = preg_replace('/\D/', '', $_POST["extension"]); // removes anything that isn't a digit.
    if (empty($exten)) {
        echo "ERROR: Invalid extension";
    }

    $callRecord = preg_replace('/[^a-z0-9\-\. ]/i', '', $_POST["call_record"]);
    $query = "Select remote_channel from asterisk_log where call_record_id='$callRecord'";

    $resultSet = $current_user->db->query($query, false);
    if ($current_user->db->checkError()) {
        trigger_error("Find Remote Channel-Query failed: $query");
    }

    while ($row = $current_user->db->fetchByAssoc($resultSet)) {
        $context = $sugar_config['asterisk_context'];
        $cmd = "ACTION: Redirect\r\nChannel: {$row['remote_channel']}\r\nContext: $context\r\nExten: $exten\r\nPriority: 1\r\n\r\n";
        SendAMICommand($cmd);
    }


    // Inbound call trying, THIS WORKED!!!
    // 174-37-247-84*CLI> core show channels concise
    // SIP/207-00000f5a!from-internal!!1!Up!AppDial!(Outgoing Line)!207!!3!209!Local/207@sugarsip-ca35;2!1333295931.5214
    // Local/207@sugarsip-ca35;2!sugarsip!207!3!Up!Dial!SIP/207,,t!+14102152497!!3!214!SIP/207-00000f5a!1333295927.5213
    // Local/207@sugarsip-ca35;1!sugarsip!!1!Up!AppDial!(Outgoing Line)!207!!3!214!SIP/Flowroute-00000f59!1333295927.5212
    // SIP/Flowroute-00000f59!macro-dial!s!7!Up!Dial!Local/207@sugarsip/n,"",tr!+14102152497!!3!223!Local/207@sugarsip-ca35;1!1333295918.5211
    //$cmd ="ACTION: Redirect\r\nChannel: SIP/Flowroute-00000f59\r\nContext: from-internal\r\nExten: 208\r\nPriority: 1\r\n\r\n";
    //SendAMICommand($cmd);
    // At this point we should also update the channel in database
} else if ($_REQUEST['action'] == "get_calls") {


    $result_set = get_calls($current_user);
    $response = build_item_list($result_set, $current_user, $mod_strings);
    // print out json 
    $response_array = array();
    if (count($response) == 0) {
        print json_encode(array("."));
    } else {
        foreach ($response as $call) {

            $response_array[] = $call;
        }
        print json_encode($response_array);
        ob_flush();
    }
    
    //pass mod_strings for language support

    echo json_encode($response);
} else {
    echo "Undefined Action";
}

/// Logs in, Sends the AMI Command Payload passed as a parameter, then logs out.
/// results of the command are "echo"ed and get show up in ajax response for debugging.
function SendAMICommand($amiCmd, &$status = true) {
    global $sugar_config;
    $server = $sugar_config['asterisk_host'];
    $port = (int) $sugar_config['asterisk_port'];
    $Username = "Username: " . $sugar_config['asterisk_user'] . "\r\n";
    $Secret = "Secret: " . $sugar_config['asterisk_secret'] . "\r\n";
    $context = $sugar_config['asterisk_context'];

    $socket = fsockopen($server, $port, $errno, $errstr, 20);

    if (!$socket) {
        echo "couldn't connect ($errno): $errstr <br>\n";
    } else {
        // log on to Asterisk
        fputs($socket, "Action: Login\r\n");
        fputs($socket, $Username);
        fputs($socket, $Secret);
        fputs($socket, "\r\n");

        $response = ReadResponse($socket);
        echo "Login Response: \n";
        echo $response;
        $status = $status && WasAmiCmdSuccessful($response);

        if ($status) {
            fputs($socket, $amiCmd);
            $response = ReadResponse($socket);
            echo "\nAMI Comand Response: \n";
            echo $response;
            $status = $status && WasAmiCmdSuccessful($response);

            fputs($socket, "Action: Logoff\r\n\r\n");
            fputs($socket, "\r\n");

            $response = ReadResponse($socket);
            echo "\nLogout Response: \n";
            echo $response;
            // Don't really care if logoff was successful;
            //$status = $status && WasAmiCmdSuccessful( $response );			
        }
        sleep(1);
        fclose($socket);
    }
}

function WasAmiCmdSuccessful($response) {
    return preg_match('/.*Success.*/s', $response);
}

function ReadResponse($socket) {
    $retVal = '';

    // Sets timeout to 1/2 a second
    stream_set_timeout($socket, 0, 500000);
    while (($buffer = fgets($socket, 20)) !== false) {
        $retVal .= $buffer;
    }
    return $retVal;
}


// HELPER FUNCTIONS

    /**
     * Get a list of calls from the database
     *
     * @param object $current_user    SugarCRM current_user object allows DB access
     *
     * @return array                  Array of calls from database
     */
    function get_calls($current_user) {
        $last_hour = date('Y-m-d H:i:s', time() - 1 * 60 * 60);
        $query = " SELECT * FROM asterisk_log WHERE \"$last_hour\" < timestampCall AND (uistate IS NULL OR uistate != \"Closed\") AND (callstate != 'NeedID') AND (channel LIKE 'SIP/{$current_user->asterisk_ext_c}%' OR channel LIKE 'Local%{$current_user->asterisk_ext_c}%')";
        $result_set = $current_user->db->query($query, false);
        if ($current_user->db->checkError()) {
            trigger_error("checkForNewStates-Query failed: $query");
        }
        return $result_set;
    }

    /**
     * Build the item list
     *
     * @param array  $result_set           Array of calls from database
     * @param object $current_user         SugarCRM current_user object allows DB access
     * @param array  $mod_strings          SugarCRM module strings 
     *
     */
    function build_item_list($result_set, $current_user, $mod_strings) {

        $response = array();
        while ($row = $current_user->db->fetchByAssoc($result_set)) {

            $state = $this->set_call_state($row);
            $phone_number = $this->set_callerid($row);
            $contact_info = $this->set_contact_information($phone_number, $row, $current_user);
            $call_direction = $this->set_call_direction($row);

            $call = array(
                'id' => $row['id'],
                'asterisk_id' => $row['asterisk_id'],
                'state' => $state,
                'is_hangup' => $this->set_call_state($row) == $mod_strings['HANGUP'],
                'call_record_id' => $row['call_record_id'],
                'phone_number' => $phone_number,
                'asterisk_name' => $row['callerName'],
                'asterisk_id' => $row['asterisk_id'],
                'timestampCall' => $row['timestampCall'],
                'title' => $this->set_title($contact_info['full_name'], $phone_number, $state),
                'full_name' => $contact_info['full_name'],
                'company' => $contact_info['company'],
                'contact_id' => $contact_info['contact_id'],
                'company_id' => $contact_info['company_id'],
                'callerid' => $contact_info['callerid'],
                'call_type' => $call_direction['call_type'],
                'direction' => $call_direction['direction'],
                'duration' => $this->set_duration($row),
            );

            $response[] = $call;

            return $response;
        }
    }

    /**
     * Build the item list
     *
     * @param string $phoneNumber         10 digit US telephone number
     *
     * @return string                     state of call
     */
    function set_call_state($row) {
        $state = isset($mod_strings[strtoupper($row['callstate'])]) ? $mod_strings[strtoupper($row['callstate'])] : $row['callstate'];
        $state = "'" . $state . "'";

        return $state;
    }

    /**
     * Sets the callerid
     * 
     * @param array  $row          Results from database call in build_item_list
     *
     * @return array               Returns the whole item array
     */
    function set_callerid($row) {
        $callPrefix = $this->get_call_prefix($row);

        $tmpCallerID = trim($row['callerID']);
        if ((strlen($callPrefix) > 0) && (strpos($tmpCallerID, $callPrefix) === 0)) {
            $tmpCallerID = substr($tmpCallerID, strlen($callPrefix));
        }

        return $tmpCallerID;
    }

    /**
     * Determine the prefix of the call
     * 
     * @param array  $row          Results from database call in build_item_list
     *
     * @return array               Returns the call prefix
     */
    function get_call_prefix($row) {
        $calloutPrefix = $GLOBALS['sugar_config']['asterisk_prefix'];
        $callinPrefix = $GLOBALS['sugar_config']['asterisk_dialinPrefix'];

        if ($row['direction'] == 'I') {
            $callPrefix = $callinPrefix;
        }
        if ($row['direction'] == 'O') {
            $callPrefix = $calloutPrefix;
        }

        return $callPrefix;
    }

    /**
     * Sets the call direction
     * 
     * @param array  $row          Results from database call in build_item_list
     *
     * @return array               Returns the whole item array
     */
    function set_call_direction($row) {
        $result = array();

        if ($row['direction'] == 'I') {
            $result['call_type'] = "Inbound";
            $result['direction'] = "Inbound";
        }

        if ($row['direction'] == 'O') {
            $result['call_type'] = "Outbound";
            $result['direction'] = "Outbound";
        }

        return $result;
    }

    function set_duration($row) {


        if (!empty($row['timestampHangup'])) {
            $to_time = strtotime($row['timestampHangup']);
        } else {
            $to_time = time();
        }

        $from_time = strtotime($row['timestampCall']);
        $duration = number_format(round(abs($to_time - $from_time) / 60, 1), 1);

        return $duration;
    }

    function set_contact_information($phoneToFind, $row, $current_user) {

        $result = array();

        $phoneToFind = ltrim($phoneToFind, '0');
        $phoneToFind = preg_replace('/\D/', '', $phoneToFind); // Removes and non digits such as + chars.

        $found = array();
        if (strlen($phoneToFind) > 5) {
            $sqlReplace = "
			    replace(
			    replace(
			    replace(
			    replace(
			    replace(
			    replace(
			    replace(
			    replace(
			    replace(
			      %s,
			        ' ', ''),
			        '+', ''),
			        '.', ''),
			        '/', ''),
			        '(', ''),
			        ')', ''),
			        '[', ''),
			        ']', ''),
			        '-', '')
			        REGEXP '%s$' = 1
			";


// TODO fix the join so that account is optional... I think just add INNER
            $selectPortion = "SELECT c.id as contact_id, first_name, last_name, phone_work, phone_home, phone_mobile, phone_other, a.name as account_name, account_id "
                    . " FROM contacts c "
                    . " left join accounts_contacts ac on (c.id=ac.contact_id) and (ac.deleted='0' OR ac.deleted is null)"
                    . " left join accounts a on (ac.account_id=a.id) and (a.deleted='0' or a.deleted is null)";

            if ($row['contact_id']) {
                $wherePortion = " WHERE c.id='{$row['contact_id']}' and c.deleted='0'";
            }
// We only do this expensive query if it's not already set!
            else {
                $wherePortion = " WHERE (";
                $wherePortion .= sprintf($sqlReplace, "phone_work", $phoneToFind) . " OR ";
                $wherePortion .= sprintf($sqlReplace, "phone_home", $phoneToFind) . " OR ";
                $wherePortion .= sprintf($sqlReplace, "phone_other", $phoneToFind) . " OR ";
                $wherePortion .= sprintf($sqlReplace, "assistant_phone", $phoneToFind) . " OR ";
                $wherePortion .= sprintf($sqlReplace, "phone_mobile", $phoneToFind) . ") and c.deleted='0'";
            }

            $queryContact = $selectPortion . $wherePortion;
            $innerResultSet = $current_user->db->query($queryContact, false);


/////// THIS IS A MONSTER - BREAK THIS UP INTO THREE DISTINCT METHODS OR USE ONE SIMPLER GET_CONTACTS & MOVE OUT UPDATE LOGIC 
            
            $isMultipleContactCase = false;
            $radioButtonCode = "";


            if ($innerResultSet->num_rows > 1) {
                $isMultipleContactCase = true;
            }

            
            
// Once contact_id db column is set, $innerResultSet will only have a single row int it.
            while ($contactRow = $current_user->db->fetchByAssoc($innerResultSet)) {
                $found['contactFullName'] = $contactRow['first_name'] . " " . $contactRow['last_name'];
                $found['company'] = $contactRow['account_name'];
                $found['contactId'] = $contactRow['contact_id'];
                $found['companyId'] = $contactRow['account_id'];

                $mouseOverTitle = "{$found['contactFullName']} - {$found['company']}"; // decided displaying <contact> - <account> took up too much space and 95% of the time you have multiple contacts its going to be from the same account... so we use mouse over to display account.
                
                $contacts = array(
                    'call_record_id' => $row['call_record_id'],
                    'contact_id' => $found['contactId'],
                    'mouse_over_title' => $mouseOverTitle,
                    'contact_full_name' => $found['contactFullName']
                    
                );
                
                if (empty($row['contact_id']) && !$isMultipleContactCase) {
                    $tempContactId = preg_replace('/[^a-z0-9\-\. ]/i', '', $contactRow['contact_id']);
                    $tempCallRecordId = preg_replace('/[^a-z0-9\-\. ]/i', '', $row['call_record_id']);
                    $insertQuery = "UPDATE asterisk_log SET contact_id='$tempContactId' WHERE call_record_id='$tempCallRecordId'";
                    $current_user->db->query($insertQuery, false);
                }
            }

            if ($isMultipleContactCase) {
                $found['contactFullName'] = $mod_strings["ASTERISKLBL_MULTIPLE_MATCHES"];
            }
            
        }

///////// END MONSTER
        
        
        $result['callerid'] = get_open_cnam_result($found, $row, $current_user);
        $result['full_name'] = isset($found['contactFullName']) ? $found['contactFullName'] : "";
        $result['company'] = isset($found['company']) ? $found['company'] : "";
        $result['contact_id'] = isset($found['contactId']) ? $found['contactId'] : "";
        $result['company_id'] = isset($found['companyId']) ? $found['companyId'] : "";

        return $result;
    }
    
    function get_open_cnam_result($row, $current_user){
        
        // Check OpenCNAM if we don't already have the Company Name in Sugar.
            if (!isset($found['company']) && $GLOBALS['sugar_config']['asterisk_opencnam_enabled'] == "true") {
                if ($row['opencnam'] == NULL) {
                    $tempCnamResult = opencnam_fetch($phoneToFind);
                    $tempCnamResult = preg_replace('/[^a-z0-9\-\. ]/i', '', $tempCnamResult);
                    $tempCallRecordId = preg_replace('/[^a-z0-9\-\. ]/i', '', $row['call_record_id']);
                    $cnamUpdateQuery = "UPDATE asterisk_log SET opencnam='$tempCnamResult' WHERE call_record_id='$tempCallRecordId'";
                    $current_user->db->query($cnamUpdateQuery, false);
                    $callerid = $tempCnamResult;
                }
            }
        return $callerid;
            
    }

    /**
     * GET the opencnam callerid information
     *
     * @param string $phoneNumber         10 digit US telephone number
     * 
     * @return array fetch results of OpenCNAM lookup
     *
     * @todo implement a number cleaner that always formats input into 10 digits
     */
    function opencnam_fetch($phoneNumber) {
        $request_url = "https://api.opencnam.com/v1/phone/" . $phoneNumber . "?format=text";
        $found = false;
        $i = 0;
        do {
            $response = file_get_contents($request_url); // First call returns with 404 immediately with free api, 2nd call will succeed. See https://github.com/blak3r/yaai/issues/5
            // "Currently running a lookup for phone '7858647222'. Please check back in a few seconds."
            if (empty($response) || strpos($response, "running a lookup") !== false) {
                usleep(1000000 * ($i)); // wait 500ms, 1000ms, then 1500ms, etc.
                // 2:25pm uped to 500,000 8x
            } else {
                $found = true;
            }
        } while ($i++ < 7 && $found == false);
        if (empty($response)) {
            $response = " "; // return a space character so it doesn't keep attempting to lookup number next time callListener is called.
        }
        return $response;
    }
    
    function is_multiple_contact_case(){
        
        
    }

    /**
     * SET the title of the call
     *
     * @param string $phoneNumber         10 digit US telephone number
     * 
     * @return string                     title
     *
     * @todo implement a number cleaner that always formats input into 10 digits
     */
    function set_title($full_name, $phone_number, $state) {
        if (strlen($full_name) == 0) {
            $title = $phone_number;
        }
        else{
            $title = $full_name;
        }

        $title = $title . " - " . $state;

        return $title;
    }

    /**
     * Helper method for logging 
     *
     * @param string $str    The string you want to log
     * @param string $file   The log file you want to log to
     */
    function log_entry($str, $file = "default") {
        $handle = fopen($file, 'a');
        fwrite($handle, "[" . date('Y-m-j H:i:s') . "] " . $str);
        fclose($handle);
    }

    /**
     * Helper method for converting print_r into a nicely formated string for logging
     *
     * @param string $str    The string you want to log
     *
     * @return string The string of the array data you want to print
     */
    function printrs($data) {
        $str = "";
        if ($data) {
            $str = '<pre>\n';
            $str .= print_r($data, TRUE);
            $str .= '</pre>\n';
        }
        return $str;
    }

?>