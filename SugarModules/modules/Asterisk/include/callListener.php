<?php

/**
 * Asterisk SugarCRM Integration
 * (c) KINAMU Business Solutions AG 2009
 *
 * Parts of this code are (c) 2006. RustyBrick, Inc.  http://www.rustybrick.com/
 * Parts of this code are (c) 2008 vertico software GmbH
 * Parts of this code are (c) 2009 abcona e. K. Angelo Malaguarnera E-Mail admin@abcona.de
 * Parts of this code are (c) 2012 Blake Robertson http://www.blakerobertson.com
 * http://www.sugarforge.org/projects/yaai/
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SUGARCRM, SUGARCRM DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 *
 * You can contact KINAMU Business Solutions AG at office@kinamu.com
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 */
if (!defined('sugarEntry') || !sugarEntry)
    die('Not A Valid Entry Point');

// All Sugar timestamps are UTC
date_default_timezone_set('UTC');

require_once('include/utils.php');
require_once('include/export_utils.php');
require_once('include/entryPoint.php');
require_once('modules/Contacts/Contact.php');
require_once("custom/modules/Asterisk/language/" . $GLOBALS['sugar_config']['default_language'] . ".lang.php");

global $current_user;
global $mod_strings;


// PROCEDURAL INSTRUCTIONS
$call_listener = new CallListener();
$result_set = $call_listener->get_calls($current_user);
$response = $call_listener->build_item_list($result_set, $current_user, $mod_strings);

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
    exit();
}

sugar_cleanup();

class CallListener {

    function __construct() {
        
    }

    // HELPER FUNCTIONS

    /**
     * Get a list of calls from the database
     *
     * @param object $current_user    SugarCRM current_user object allows DB access
     *
     * @return array                  Array of calls from database
     */
    public function get_calls($current_user) {
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
    public function build_item_list($result_set, $current_user, $mod_strings) {

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
    private function set_call_state($row) {
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
    private function set_callerid($row) {
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
    private function get_call_prefix($row) {
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
    private function set_call_direction($row) {
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

    private function set_duration($row) {


        if (!empty($row['timestampHangup'])) {
            $to_time = strtotime($row['timestampHangup']);
        } else {
            $to_time = time();
        }

        $from_time = strtotime($row['timestampCall']);
        $duration = number_format(round(abs($to_time - $from_time) / 60, 1), 1);

        return $duration;
    }

    private function set_contact_information($phoneToFind, $row, $current_user) {

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
    
    private function get_open_cnam_result($row, $current_user){
        
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
    private function opencnam_fetch($phoneNumber) {
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
    
    private function is_multiple_contact_case(){
        
        
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
    private function set_title($full_name, $phone_number, $state) {
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
    private function log_entry($str, $file = "default") {
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
    private function printrs($data) {
        $str = "";
        if ($data) {
            $str = '<pre>\n';
            $str .= print_r($data, TRUE);
            $str .= '</pre>\n';
        }
        return $str;
    }

}

?>