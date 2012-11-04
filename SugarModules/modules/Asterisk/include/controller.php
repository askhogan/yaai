<?php

/* * *
 * Author: Blake Robertson
 * 
 * Author: Patrick Hogan
 *
 * Controller class for various AJAX things such as saving the UI state and saving the call details. 
 *
 * TODO: createCall should be refactored into this php file and then called by specifying an appropriate action for them.
 */



if (!defined('sugarEntry') || !sugarEntry)
    die('Not A Valid Entry Point');

require_once('include/utils.php');
require_once('include/export_utils.php');
require_once('modules/Calls/Call.php');
require_once('modules/Users/User.php');
//sets $mod_strings variable
require_once("custom/modules/Asterisk/language/" . $GLOBALS['sugar_config']['default_language'] . ".lang.php");

//ACTIONS

switch ($_REQUEST['action']) {
    case "memoSave" :
        
        $GLOBALS['log']->fatal($_REQUEST['phone_number']);
        if ($_REQUEST['call_record']) {
            memoSave($_REQUEST['call_record'], $_REQUEST['sugar_user_id'], $_REQUEST['phone_number'], $_REQUEST['description'], $_REQUEST['contact_id']);
        }
        break;
    case "updateUIState" :
        updateUIState($_REQUEST['ui_state'], $_REQUEST['call_record'], $_REQUEST['id']);
        break;
    case "setContactID" :
        setContactID($_REQUEST['call_record'], $_REQUEST['contact_id']);
        break;
    case "call" :
        callCreate();
        break;
    case "transfer" :
        transferCall($_REQUEST["extension"], $_REQUEST['call_record']);
        break;
    case "block" :
        blockNumber($_REQUEST['number'], $_REQUEST['description']);
        break;
    case "get_calls" :
        getCalls($mod_strings);
        break;
    default :
        echo "undefined action";
        break;
}

// ACTION FUNCTIONS

function memoSave($call_record_id, $sugar_user_id, $phone_number, $description, $contact_id) {
    $GLOBALS['log']->fatal('memoSave' . $phone_number);
    if ($call_record_id) {
        $call = new Call();

        if (!empty($contact_id)) {
            $call->parent_id = $contact_id;
            $call->parent_type = 'Contacts';
        }


        $call->retrieve($call_record_id);
        $call->description = $description;
        //!$name ? $call->name = getMemoName($call, $direction) : $call->name = $_REQUEST["name"];
        $GLOBALS['log']->fatal('memoSave' . $phone_number);
        $call->name = $phone_number;
        $call->assigned_user_id = $sugar_user_id;
        $call->save();
        $GLOBALS['log']->fatal('callid_' . $call->id);
    }
}

function updateUIState($ui_state, $call_record, $asterisk_id) {
    $cUser = new User();
    $cUser->retrieve($_SESSION['authenticated_user_id']);

    // query log
    // Very basic santization
    $uiState = preg_replace('/[^a-z0-9\-\. ]/i', '', $ui_state); //  mysql_real_escape_string($_REQUEST['ui_state']);
    $callRecord = preg_replace('/[^a-z0-9\-\. ]/i', '', $call_record); //mysql_real_escape_string($_REQUEST['call_record']);
    $asteriskID = preg_replace('/-/', '.', $asterisk_id);
    // Workaround See Discussion here: https://github.com/blak3r/yaai/pull/20
    if (isset($call_record)) {
        $query = "update asterisk_log set uistate=\"$uiState\" where call_record_id=\"$callRecord\"";
    } else {
        $query = "update asterisk_log set uistate=\"$uiState\" where asterisk_id=\"$asteriskID\"";
    }

    $cUser->db->query($query, false);
    if ($cUser->db->checkError()) {
        trigger_error("Update UIState-Query failed: $query");
    }
}

function setContactID($call_record, $call_record) {
    //wrapped the entire action to require a call_record - if this is not being passed then there is no point for this action - PJH
    if ($call_record) {
        // Very basic santization
        $contactId = preg_replace('/[^a-z0-9\-\. ]/i', '', $contact_id);  
        $callRecord = preg_replace('/[^a-z0-9\-\. ]/i', '', $call_record); 
        // Workaround See Discussion here: https://github.com/blak3r/yaai/pull/20

        $query = "update asterisk_log set contact_id=\"$contactId\" where call_record_id=\"$callRecord\"";


        $GLOBALS['current_user']->db->query($query, false);
        if ($GLOBALS['current_user']->db->checkError()) {
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
    }
}

function callCreate() {
    // TODO: For some reason this code isn't working... I think it's getting the extension.
// For the time being, callCreate is still being used.	

    /*
      $cUser = new User();
      $cUser->retrieve($_SESSION['authenticated_user_id']);
      $extension = $cUser->asterisk_ext_c;

      //$extension = $current_user->asterisk_ext_c;
      $context = $GLOBALS['sugar_config']['asterisk_context'];

      // Take the user supplied pattern, we find the part with the #'s (which are the ext)... then we get something like
      // asterisk_dialout_channel == "SIP/###"   --> $matches[1] == SIP/, $matches[2] == "###", $matches[3] is "".
      // asterisk_dialout_channel == "Local/###@sugarsip/n"   --> $matches[1] == Local/, $matches[2] == "###", $matches[3] is "@sugarsip/n".
      preg_match('/([^#]*)(#+)([^#]*)/',$GLOBALS['sugar_config']['asterisk_dialout_channel'],$matches);
      $channel = $matches[1] . $extension . $matches[3];

      //format Phone Number
      $number = $_REQUEST['phoneNr'];
      $prefix = $GLOBALS['sugar_config']['asterisk_prefix'];
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
}

function transferCall($extension, $call_record) {
    $exten = preg_replace('/\D/', '', $extension); // removes anything that isn't a digit.
    if (empty($exten)) {
        echo "ERROR: Invalid extension";
    }

    $callRecord = preg_replace('/[^a-z0-9\-\. ]/i', '', $call_record);
    $query = "Select remote_channel from asterisk_log where call_record_id='$callRecord'";

    $resultSet = $GLOBALS['current_user']->db->query($query, false);
    if ($GLOBALS['current_user']->db->checkError()) {
        trigger_error("Find Remote Channel-Query failed: $query");
    }

    while ($row = $GLOBALS['current_user']->db->fetchByAssoc($resultSet)) {
        $context = $GLOBALS['sugar_config']['asterisk_context'];
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
}

function blockNumber($number, $description) {
    $e164_number = formatPhoneNumberToE164($number);
    $description = trim($description);
    $cmd = "ACTION: DBPut\r\nFamily: blacklist\r\nKey: {$e164_number}\r\nValue: {$description}\r\n\r\n\r\n\r\n";
    SendAMICommand($cmd);
}

function getCalls($mod_strings) {
    $result_set = get_calls();
    $response = build_item_list($result_set, $GLOBALS['current_user'], $mod_strings);
    // print out json 
    $response_array = array();
    if (count($response) == 0) {
        print json_encode(array("."));
    } else {
        foreach ($response as $call) {

            $response_array[] = $call;
        }
        print json_encode($response_array);
    }
    sugar_cleanup();
}

// HELPER FUNCTIONS

/**
 * Logs in, Sends the AMI Command Payload passed as a parameter, then logs out.
 * results of the command are "echo"ed and show up in ajax response for debugging.
 * 
 * @param string $response    AMI Command
 *
 * @param string $status      
 *  
 */
function SendAMICommand($amiCmd, &$status = true) {
    $server = $GLOBALS['sugar_config']['asterisk_host'];
    $port = (int) $GLOBALS['sugar_config']['asterisk_port'];
    $Username = "Username: " . $GLOBALS['sugar_config']['asterisk_user'] . "\r\n";
    $Secret = "Secret: " . $GLOBALS['sugar_config']['asterisk_secret'] . "\r\n";

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

/**
 * Check if AMI Command Was Successful
 *
 * @param object $response    AMI Response
 *
 * @return string                  Success resonse
 */
function WasAmiCmdSuccessful($response) {
    return preg_match('/.*Success.*/s', $response);
}

/**
 * Read the socket response
 *
 * @param object $socket    Socket
 *
 * @return array                  Array of socket responses
 */
function ReadResponse($socket) {
    $retVal = '';

    // Sets timeout to 1/2 a second
    stream_set_timeout($socket, 0, 500000);
    while (($buffer = fgets($socket, 20)) !== false) {
        $retVal .= $buffer;
    }
    return $retVal;
}

/**
 * GET the description to save to the memo box
 *
 * @param object $socket    Socket
 *
 * @return array                  Array of socket responses
 */
function getMemoName($call, $direction) {

    //set the proper abbreviation
    if ($direction == "Outbound") {
        $directionAbbr = $GLOBALS['sugar_config']['asterisk_call_subject_outbound_abbr'];
    }

    if ($direction == "Inbound") {
        $directionAbbr = $GLOBALS['sugar_config']['asterisk_call_subject_inbound_abbr'];
    }

    //set the description
    if (strlen($call->description) > 0) {
        $name = $directionAbbr . $call->description;
    } else {
        $name = "$direction Call"; // default subject
    }

    //check the length of the description
    if (strlen($name) > $GLOBALS['sugar_config']['asterisk_call_subject_max_length']) {
        $substrLen = $GLOBALS['sugar_config']['asterisk_call_subject_max_length'] - (strlen($directionAbbr) + strlen("...") + 1);
        $name = $directionAbbr . substr($call->description, 0, $substrLen) . "...";
    }

    return $name;
}

/**
 * GET list of calls from the database
 *
 * @param object $current_user    SugarCRM current_user object allows DB access
 *
 * @return array                  Array of calls from database
 */
function get_calls() {
    $last_hour = date('Y-m-d H:i:s', time() - 1 * 60 * 30);
    $query = " SELECT * FROM asterisk_log WHERE \"$last_hour\" < timestampCall AND (uistate IS NULL OR uistate != \"Closed\") AND (callstate != 'NeedID') AND (channel LIKE 'SIP/{$GLOBALS['current_user']->asterisk_ext_c}%' OR channel LIKE 'Local%{$GLOBALS['current_user']->asterisk_ext_c}%')";
    $result_set = $GLOBALS['current_user']->db->query($query, false);
    if ($GLOBALS['current_user']->db->checkError()) {
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

        $state = get_call_state($row, $mod_strings);
        $phone_number = get_callerid($row);
        $call_direction = get_call_direction($row, $mod_strings);
        $contacts = get_contact_information($phone_number, $row, $current_user);

        $call = array(
            'id' => $row['id'],
            'asterisk_id' => $row['asterisk_id'],
            'state' => $state,
            'is_hangup' => $state == $mod_strings['YAAI']['HANGUP'],
            'call_record_id' => $row['call_record_id'],
            'phone_number' => $phone_number,
            'asterisk_name' => $row['callerName'],
            'timestampCall' => $row['timestampCall'],
            'title' => get_title($contacts, $phone_number, $state, $mod_strings),
            'contacts' => $contacts,
            'call_type' => $call_direction['call_type'],
            'direction' => $call_direction['direction'],
            'duration' => get_duration($row),
            'mod_strings' => $mod_strings['YAAI']
        );

        $response[] = $call;
    }

    return $response;
}

/**
 * GET the call state
 *
 * @param array  $row          Results from database call in build_item_list
 *
 * @return string                     state of call
 */
function get_call_state($row, $mod_strings) {
    $state = isset($mod_strings[strtoupper($row['callstate'])]) ? $mod_strings[strtoupper($row['callstate'])] : $row['callstate'];

    return $state;
}

/**
 * GET the callerid
 * 
 * @param array  $row          Results from database call in build_item_list
 *
 * @return array               Returns the whole item array
 */
function get_callerid($row) {
    $callPrefix = get_call_prefix($row);

    $tmpCallerID = trim($row['callerID']);
    if ((strlen($callPrefix) > 0) && (strpos($tmpCallerID, $callPrefix) === 0)) {
        $tmpCallerID = substr($tmpCallerID, strlen($callPrefix));
    }

    return $tmpCallerID;
}

/**
 * GET the prefix of the call
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
 * GET the call direction
 * 
 * @param array  $row          Results from database call in build_item_list
 *
 * @return array               Returns the whole item array
 */
function get_call_direction($row, $mod_strings) {
    $result = array();

    if ($row['direction'] == 'I') {
        $result['call_type'] = $mod_strings['YAAI']['ASTERISKLBL_COMING_IN'];
        $result['direction'] = "Inbound";
    }

    if ($row['direction'] == 'O') {
        $result['call_type'] = $mod_strings['YAAI']['ASTERISKLBL_GOING_OUT'];
        $result['direction'] = "Outbound";
    }

    return $result;
}

/**
 * GET the call duration
 * 
 * @param array  $row          Results from database call in build_item_list
 *
 * @return array               Returns the whole item array
 */
function get_duration($row) {
    if (!empty($row['timestampHangup'])) {
        $to_time = strtotime($row['timestampHangup']);
    } else {
        $to_time = time();
    }

    $from_time = strtotime($row['timestampCall']);
    $duration = number_format(round(abs($to_time - $from_time) / 60, 1), 1);

    return $duration;
}

/**
 * GET contacts array
 * 
 * @param array  $row          Results from database call in build_item_list
 *
 * @return array               Returns the whole item array
 */
function get_contact_information($phone_number, $row, $current_user) {
    $innerResultSet = fetch_contacts_associated_to_phone_number($phone_number, $row, $current_user);

    $contacts = get_contacts($innerResultSet, $current_user, $row);

    return $contacts;
}

/**
 * GET contacts from database
 * 
 * @param array  $innerResultSet  Results from function fetch_contacts_associated_to_phone_number
 * 
 * @param object  $current_user Global current_user object - allows db access
 * 
 * @param array  $row          Results from database call in build_item_list
 *
 * @return array               Returns contacts
 */
function get_contacts($innerResultSet, $current_user, $row) {
    $contacts = array();

    while ($contactRow = $current_user->db->fetchByAssoc($innerResultSet)) {
        $contact = array(
            'contact_id' => $contactRow['contact_id'],
            'contact_full_name' => $contactRow['first_name'] . " " . $contactRow['last_name'],
            'company' => $contactRow['account_name'],
            'company_id' => $contactRow['account_id']
        );

        $contacts[] = $contact;
    }



    return $contacts;
}

function fetch_contacts_associated_to_phone_number($phoneToFind, $row, $current_user) {
    $phoneToFind = ltrim($phoneToFind, '0');
    $phoneToFind = preg_replace('/\D/', '', $phoneToFind); // Removes and non digits such as + chars.

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
        return $current_user->db->query($queryContact, false);
    }
}

/**
 * GET the opencnam callerid information
 *
 * @param array  $row          Results from database call in build_item_list
 * 
 * @param object  $current_user Global current_user object - allows db access
 * 
 * @return array $callerid    Returns the callerid information         
 *
 * @todo implement a number cleaner that always formats input into 10 digits
 */
function get_open_cnam_result($row, $current_user) {

    // Check OpenCNAM if we don't already have the Company Name in Sugar.
    if (!isset($found['company']) && $GLOBALS['sugar_config']['asterisk_opencnam_enabled'] == "true") {
        if ($row['opencnam'] == NULL) {
            $tempCnamResult = opencnam_fetch(get_callerid($row));
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
 * Fetch a list of records from OpenCNAM
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

/**
 * GET the title of the call
 *
 * @param string $full_name         Full name of
 * 
 * @param string $phoneNumber         10 digit US telephone number
 * 
 * @return string                     title
 * 
 * title changes based on whether there are 1) multiple matches found 2) single match found 3) no matches found
 */
function get_title($contacts, $phone_number, $state, $mod_strings) {

    switch (count($contacts)) {
        case 0:
            $title = $phone_number;
            break;

        case 1:
            $title = $contacts[0]['contact_full_name'];
            break;

        default:
            $title = $mod_strings["ASTERISKLBL_MULTIPLE_MATCHES"];
            break;
    }
    $title = $title . " - " . $state;

    return $title;
}

/**
 * Helper method for turning any number into an e164 number 
 *
 * @param string $number    The number you want to convert
 */
function formatPhoneNumberToE164($number) {

    // get rid of any non (digit, + character)
    $phone = preg_replace('/[^0-9+]/', '', $number);

    // validate intl 10
    if (preg_match('/^\+([2-9][0-9]{9})$/', $phone, $matches)) {
        return "+{$matches[1]}";
    }

    // validate US DID
    if (preg_match('/^\+?1?([2-9][0-9]{9})$/', $phone, $matches)) {
        return "+1{$matches[1]}";
    }

    // validate INTL DID
    if (preg_match('/^\+?([2-9][0-9]{8,14})$/', $phone, $matches)) {
        return "+{$matches[1]}";
    }

    // premium US DID
    if (preg_match('/^\+?1?([2-9]11)$/', $phone, $matches)) {
        return "+1{$matches[1]}";
    }
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