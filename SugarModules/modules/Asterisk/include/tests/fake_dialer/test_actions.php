<?php

require_once('include/utils.php');
require_once("modules/Contacts/Contact.php");


if ($_REQUEST["action"] == 'dial') {

    $call_setup_data = action_dial();
    print json_encode($call_setup_data);
    sugar_cleanup();
}

if ($_REQUEST["action"] == 'connected') {

    action_connected();
}

if ($_REQUEST["action"] == 'hangup') {

    action_hangup();
}

if ($_REQUEST["action"] == 'closed') {
    action_closed();
}

function action_dial() {
    $extension = get_extension_input();
    $new_id = get_new_call_record_id();
    $time = get_current_server_time();
    $asterisk_id = get_random_asterisk_id();

    $GLOBALS['current_user']->db->query(
            "INSERT INTO asterisk_log (call_record_id, asterisk_id, callstate, callerID, channel, remote_channel, timestampCall, direction) 
            VALUES ('{$new_id}', '{$asterisk_id}', 'Dial', '+11111111111', '{$extension}', 'SIP/flowroute-00000023', FROM_UNIXTIME({$time}), 'I' )"
    );

    $call_setup_data = create_fake_contacts();
    $call_setup_data['call_record_id'] = $new_id;

    return $call_setup_data;
}

function action_connected() {
    $GLOBALS['log']->fatal($call_record_id);
    $call_record_id = $_REQUEST["call_record_id"];
    $GLOBALS['current_user']->db->query("UPDATE asterisk_log SET callstate = 'Connected' WHERE call_record_id = '{$call_record_id}'");
}

function action_hangup() {
    $call_record_id = $_REQUEST["call_record_id"];
    $GLOBALS['current_user']->db->query("UPDATE asterisk_log SET callstate = 'Hangup' WHERE call_record_id = '{$call_record_id}'");
}

function action_closed() {
    $call_record_id = $_REQUEST["call_record_id"];
    $GLOBALS['current_user']->db->query("UPDATE asterisk_log SET uistate = 'Closed' WHERE call_record_id = '{$call_record_id}'");
    $contact_1 = $_REQUEST["contact_1"];
    $contact_2 = $_REQUEST["contact_2"];

    delete_fake_contact($contact_1);
    delete_fake_contact($contact_2);
}

function create_fake_contacts() {
    $results = $GLOBALS['current_user']->db->query("SELECT UUID() AS newid");
    $result = $GLOBALS['current_user']->db->fetchByAssoc($results);
    $new_id_jon = $result["newid"];
    $insert = $GLOBALS['current_user']->db->query("
            INSERT INTO contacts (id, date_entered, date_modified, modified_user_id, first_name, last_name, phone_mobile) 
            VALUES ('{$new_id_jon}', NOW(), NOW(), 1,  'Jon', 'Doe', '+11111111111')");

    $results = $GLOBALS['current_user']->db->query("SELECT UUID() AS newid");
    $result = $GLOBALS['current_user']->db->fetchByAssoc($results);
    $new_id_jane = $result["newid"];

    $insert = $GLOBALS['current_user']->db->query("
            INSERT INTO contacts (id, date_entered, date_modified, modified_user_id, first_name, last_name, phone_mobile) 
            VALUES ('{$new_id_jane}', NOW(), NOW(), 1,  'Jane', 'Doe', '+11111111111')");

    $contact_ids = array(
        'contact_1' => $new_id_jon,
        'contact_2' => $new_id_jane
    );

    return $contact_ids;
}

function delete_fake_contact($contact_id) {
    $GLOBALS['current_user']->db->query("DELETE FROM contacts WHERE id = '{$contact_id}'");
}

function get_new_call_record_id() {
    $results = $GLOBALS['current_user']->db->query("SELECT UUID() AS newid");
    $result = $GLOBALS['current_user']->db->fetchByAssoc($results);
    $new_id = $result["newid"];

    return $new_id;
}

function get_extension_input() {
    $extension = "SIP/{$_REQUEST['extension']}-00000036";

    return $extension;
}

function get_current_server_time() {
    $time = time();

    return $time;
}

function get_random_asterisk_id() {
    $asterisk_id = rand(10000000000, 9999999999) . '.' . rand(10, 99);

    return $asterisk_id;
}

?>
