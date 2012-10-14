<?php

/**
 * Asterisk SugarCRM Integration
 * (c) KINAMU Business Solutions AG 2009
 *
 * Parts of this code are (c) 2006. RustyBrick, Inc.  http://www.rustybrick.com/
 * Parts of this code are (c) 2008 vertico software GmbH
 * Parts of this code are (c) 2009 abcona e. K. Angelo Malaguarnera E-Mail admin@abcona.de
 * Parts of this code are (c) 2012 Blake Robertson. http://www.blakerobertson.com
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
 *
 * This file is added as an after_ui_frame logic hook by one of the manifest install scripts.  It calls:
 *    check_logic_hook_file("", "after_ui_frame",
 * 		array(1, 'Asterisk', 'custom/modules/Asterisk/include/AsteriskJS.php','AsteriskJS', 'echoJavaScript'));
 *
 */
//prevents directly accessing this file from a web browser
if (!defined('sugarEntry') || !sugarEntry)
    die('Not A Valid Entry Point');

global $sugar_config;

class AsteriskJS {

    function echoJavaScript($event, $arguments) {
        global $sugar_config;

        $GLOBALS['log']->fatal('test');

        // asterisk hack: include ajax callbacks in every sugar page except ajax requests:
        if ((!isset($_REQUEST['sugar_body_only']) || $_REQUEST['sugar_body_only'] != true) && $_REQUEST['action'] != 'modulelistmenu' && $_REQUEST['action'] != 'Popup' && empty($_REQUEST['to_pdf']) && (!empty($_REQUEST['module']) && $_REQUEST['module'] != 'ModuleBuilder') && empty($_REQUEST['to_csv'])) {

            $pollRate = !empty($sugar_config['asterisk_listener_poll_rate']) ? $sugar_config['asterisk_listener_poll_rate'] : "5000";
            $userExt = !empty($GLOBALS['current_user']->asterisk_ext_c) ? $GLOBALS['current_user']->asterisk_ext_c : "Not Configured!";


            echo '<link rel="stylesheet" type="text/css" media="all" href="custom/modules/Asterisk/include/css/asterisk.css">';
            echo '<script type="text/javascript" src="custom/modules/Asterisk/include/javascript/callPopups.js"></script>';
            echo '<script type="text/javascript">AST_PollRate = ' . $pollRate . ';</script>';
            echo '<script> AST_UserExtention = ' . $userExt . ';</script>';
            echo '<script type="text/javascript" src="custom/modules/Asterisk/include/javascript/dialout.js"></script>';
            echo '<script type="text/javascript" src="https://github.com/downloads/wycats/handlebars.js/handlebars-1.0.rc.1.js"></script>';
            echo "<script id='asterisk-template' type='text/x-handlebars-template'>{$this->get_handlebars_template()}</script>";
            echo '<!--[if lte IE 7]>';
            echo '<link type="text/css" rel="stylesheet" media="all" href="custom/modules/Asterisk/include/css/screen_ie.css" />';
            echo '<![endif]-->';
        }
    }

    private function get_handlebars_template() {
        $handlebars_template = <<<HTML
<div id="{{callbox_id}}" class="callbox">
    <div class="callboxhead" onclick="javascript:YAAI.toggleCallBoxGrowth('{{asterisk_id}}')">
        <div class="callboxtitle">{{title}}</div>
        <div class="callboxoptions">
            <a href="javascript:void(0)" style="font-size:110%;" onclick="javascript:YAAI.closeCallBox('{{asterisk_id}}')">X</a>
        </div>
        <br clear="all">
    </div>
    <div class="callboxcontent">  
        <div class="asterisk_info" id="{{asterisk_info}}">

            <h4 id="call_type">{{call_type}}</h4>

            <div class="tabForm">
                <table class="asterisk_data" id="asterisk_data">
                    <tr>
                        <td colspan="2" class="listViewThS1 asterisk_state">{{asterisk_state}}</td>
                    </tr>
                    
                    <tr id="multiplecontactcase">
                        <td colspan="2">
                            <b>Select Contact:</b>
                            <br>
                            <span class="call_contacts">
                                {{#each contacts}}
                                  <p id="contact_{{contact_id}}">
                                    <input type=radio name=contactSelect onclick="javascript:YAAI.setContactId('{{call_record_id}}','{{contact_id}}')" value={{contact_id}}>
                                <a id="astmultcontact" title="{{contact_full_name}}" href="index.php?module=Contacts&action=DetailView&record={{contact_id}}">{{contact_full_name}}</a>
                                </p>
                                {{/each}}
                            <br>
                            </span>
                        </td>
                    </tr>
                    
                    <tr id="nomatchingcontacts">
                        <td colspan="2">
                            <b>Select Contact:</b><br>
                            <span class="call_contacts"></span>
                        </td>
                    </tr>

                    <tr id="caller_id_box">
                        <td id="caller_id_label">Caller ID:</td>
                        <td id="caller_id">{{caller_id}}</td>
                    </tr>
                    <tr id="phone_number_box">
                        <td id="phone_number_label">Phone Number:</td>
                        <td id="phone_number">{{phone_number}}</td>
                    </tr>

                    <tr id="call_duration_box">
                        <td id="call_duration_label">Duration:</td>
                        <td>
                            <span class="call_duration">{{duration}}</span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="callboxinput" style="display: block; ">
        <textarea id="{{callboxtextarea}}" class="callboxtextarea callboxtextareaselected" onkeydown="javascript:return YAAI.checkCallBoxInputKey(event,this,'{{asterisk_id}}');">
        </textarea>
        <div class="callboxbuttons">
            <table width="100%">
                <tbody>
                    <tr>
                        <td valign="bottom">
                            <span style="width=150px;" class="asterisk_save_status">&nbsp;</span>
                            <img id="{{transfer_image}}" src="custom/modules/Asterisk/include/images/call_transfer-blue.png" height="19" title="Transfer Call" onclick="javascript:YAAI.showTransferMenu('{{asterisk_id}}');">
                        </td>
                        <td align="right">
                            <input style="" type="button" name="saveMemo" value="Save" onclick="javascript:YAAI.saveMemo('{{asterisk_id}}');">
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
   
HTML;

        return $handlebars_template;
    }

}

?>
