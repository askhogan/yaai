//**
// * Asterisk SugarCRM Integration 
// * (c) KINAMU Business Solutions AG 2009
// * 
// * Parts of this code are (c) 2006. RustyBrick, Inc.  http://www.rustybrick.com/
// * Parts of this code are (c) 2008 vertico software GmbH  
// * Parts of this code are (c) 2009 Copyright (c) 2009 Anant Garg (anantgarg.com | inscripts.com)
// * Parts of this code are (c) 2009 abcona e. K. Angelo Malaguarnera E-Mail admin@abcona.de
// * Parts of this code are (c) 2011 Blake Robertson http://www.blakerobertson.com
// * Parts of this code are (c) 2012 Patrick Hogan askhogan@gmail.com
// * http://www.sugarforge.org/projects/yaai/
// * Contribute To Project: http://www.github.com/blak3r/yaai
// * 
// * This program is free software; you can redistribute it and/or modify it under
// * the terms of the GNU General Public License version 3 as published by the
// * Free Software Foundation with the addition of the following permission added
// * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
// * IN WHICH THE COPYRIGHT IS OWNED BY SUGARCRM, SUGARCRM DISCLAIMS THE WARRANTY
// * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
// * 
// * This program is distributed in the hope that it will be useful, but WITHOUT
// * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
// * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
// * details.
// * 
// * You should have received a copy of the GNU General Public License along with
// * this program; if not, see http://www.gnu.org/licenses or write to the Free
// * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
// * 02110-1301 USA.
// * 
// * You can contact KINAMU Business Solutions AG at office@kinamu.com
// * 
// * The interactive user interfaces in modified source and object code versions
// * of this program must display Appropriate Legal Notices, as required under
// * Section 5 of the GNU General Public License version 3.
// * 

var YAAI = {
    nextHeight : '0',
    callboxFocus : new Array(),
    newMessages : new Array(),
    callBoxes : new Array(),
    callBoxCallRecordIds : new Array(),
    callBoxCallDirections : new Array(),
    sugarUserId : window.current_user_id,
    
    options : {
        debug: true
    },
    
    checkForNewStates : function (){
        // Note: once the user gets logged out, the ajax requests will get redirected to the login page.
        // Originally, the setTimeout method was in this method.  But, no way to detect the redirect without server side
        // changes.  See: http://stackoverflow.com/questions/199099/how-to-manage-a-redirect-request-after-a-jquery-ajax-call
        // So, now I only schedule a setTimeout upon a successful AJAX call.  The only downside of this is if there is a legit reason
        // the call does fail it'll never try again..
        $.getJSON('index.php?entryPoint=AsteriskController&action=get_calls', function(data){
            YAAI.checkData(data);
        })
        .error(function(){
            console.log('there is a problem with getJSON')
        });	
    },
    checkData : function(data){
        console.log(data);
        
        var tmpList = new Array();
        // Note: AST_PollRate is set in AsteriskJS.php
        setTimeout('YAAI.checkForNewStates()', AST_PollRate); // Only when the previous request was successful do we try again.
        
        
        if( data == "." ) {
        // do nothing
        }
        else { 
            $.each(data, function(entryIndex, entry){
                console.log(entry);
                var callboxid = YAAI.getAsteriskID(entry['asterisk_id']); 
                tmpList.push(callboxid);            
	    
                
                if(YAAI.callBoxHasNotAlreadyBeenCreated(callboxid)) {
                    YAAI.createCallBox(callboxid, entry);
                }
                else {  
                    YAAI.updateCallBox(callboxid, entry);
                }	
            });
        }
        YAAI.wasCallBoxClosedInAnotherBrowserWindow(tmpList);

	
    },

    // CREATE
    
    createCallBox : function (callboxid, entry) { 
        var source = $('#asterisk-template').html();
        var template = Handlebars.compile(source);
        //Only adds the common elements shared amongst all calls - any specific elements necessary for the particular type of callbox are added within their relative function below
        var context = {
            callbox_id : 'callbox_' + callboxid,
            title : entry['title'],
            transfer_image : 'transferImg_' + callboxid,
            callboxtextarea : 'callboxtextarea_' + callboxid,
            asterisk_state : entry['state'],
            call_type : entry['call_type'],
            asterisk_id : callboxid,
            duration : entry['duration'] + ' mins',
            phone_number: entry['phone_number'],
            caller_id: entry['caller_id'],
            call_record_id: entry['call_record_id']
        };
        
        switch(entry['contacts'].length){
            case 0 :
                YAAI.createCallBoxWithNoMatchingContact();
                var html = template(context);
                $('body').append(html);
                $('#callbox_'+callboxid).find('.nomatchingcontact').show();
                $('#callbox_'+callboxid).find('.nomatchingcontact .relate_contact').button().on("click", function(){
                    open_popup( "Contacts", 600, 400, "", true, true, {"call_back_function":"relate_popup_callback","form_name": entry['call_record_id'],"field_to_name_array":{"id":"relateContactId","last_name":"relateContactName"}},"single",true);   
                });
                $('#callbox_'+callboxid).find('.nomatchingcontact .create_new_contact').button().on("click", function(){
                    window.location = "index.php?module=Contacts&action=EditView&phone_work="+entry['phone_number'];
                 });
                
            break;
            
            case 1 :
                context = YAAI.createCallBoxWithSingleMatchingContact(entry['contacts'], context, entry);
                var html = template(context);
                $('body').append(html);
                $('#callbox_'+callboxid).find('.singlematchingcontact').show();
                $('#callbox_'+callboxid).find('.singlematchingcontact .unrelate_contact').button({icons: {primary: "ui-icon ui-icon-close"},text: false}).on("click", function(){
                    open_popup( "Contacts", 600, 400, "", true, true, {"call_back_function":"relate_popup_callback","form_name": entry['call_record_id'],"field_to_name_array":{"id":"relateContactId","last_name":"relateContactName"}},"single",true);
                });
                console.log('single matching case');
                console.log(context);
            break;
            
            default :
                console.log('working');
                //first you must search through the multiple matching contacts to see if setcontactID has already selected a match or do in php
                
                context = YAAI.createCallBoxWithMultipleMatchingContacts(entry['contacts'], context, entry);
                var html = template(context);
                $('body').append(html);
                $('#callbox_'+callboxid).find('.multiplematchingcontacts').show();
                
                $('#callbox_'+callboxid).find('.multiplematchingcontacts td p').on("click", "input",  function(){
                    YAAI.setContactId(entry['call_record_id'], this.value);
                })
            break;
        }
        
        if(entry['caller_id']){$('#caller_id').show();}
        
        $('#callbox_'+callboxid).find('.callboxoptions a').on("click", function(){
            YAAI.closeCallBox(callboxid);
        });
        
        $('#callbox_'+callboxid).find('.callboxhead').on("click",  function(){
            YAAI.toggleCallBoxGrowth(callboxid);
        });
        
        
        
        
        
        $('.callbox').show();
        
        
   
        YAAI.callBoxCallRecordIds[callboxid] = entry['call_record_id'];
        YAAI.callBoxCallDirections[callboxid] = entry['direction'];
        
        YAAI.minimizeExistingCallboxesWhenNewCallComesIn();
        YAAI.startVerticalEndVertical(callboxid);  //procedurally this must go after minimizeExistingCallboxesWhenNewCallComesIn
        YAAI.checkMinimizeCookie(callboxid);
        YAAI.setupCallBoxFocusAndBlurSettings(callboxid);
        YAAI.checkForErrors(entry);

        $("#callbox_"+callboxid).show();
    },
    
    // UPDATE
    
    updateCallBox : function (astId, entry){
        $(".asterisk_state", "#callbox_"+astId+" .callboxcontent").text(entry['state']);

        if( entry['is_hangup']  ) {
            $("#callbox_"+astId+" .callboxhead").css("background-color", "#f99d39");
            $("#transferImg_"+astId).hide(); // hide transfer icon once call is over.
        }
        else {
            $("#callbox_"+astId+" .callboxhead").css("background-color", "#0D5995"); // a blue color
            $("#transferImg_"+astId).show();	
        }
				
        $(".call_duration", "#callbox_"+astId+" .callboxcontent").text( entry['duration'] ); // Updates duration
    },
    
    // CLEANUP
    
    wasCallBoxClosedInAnotherBrowserWindow : function  (tmpList){
        for(var i=0; i < YAAI.callBoxes.length; i++ ) {
            if( -1 == $.inArray(YAAI.callBoxes[i], tmpList) ) {
                if( YAAI.callboxFocus[i] || YAAI.getMemoText(YAAI.callBoxes[i]).length > 0 ) {
                // Don't auto close the callbox b/c there is something entered or it has focus.
                }
                else {
                    YAAI.closeCallBox( YAAI.callBoxes[i] );
                    // Pop it from the array?
				
                    $('#callbox_'+YAAI.callBoxes[i]).css('display','none');
                    YAAI.restructureCallBoxes();
                    YAAI.callBoxes.splice(i,1); // todo is callBoxes.length above evaluated dynamically?
                }
            }
        }
    },

        /// USER ACTIONS
    closeCallBox : function(callboxid) {
        if( !YAAI.isCallBoxClosed(callboxid) ) {
            $('#callbox_'+callboxid).css('display','none');
            YAAI.restructureCallBoxes();
		
            YAAI.callRecordId = YAAI.getCallCallRecordId( callboxid );   

            // Tells asterisk_log table that user has closed this entry.
            $.post("index.php?entryPoint=AsteriskController&action=updateUIState", {
                id: callboxid, 
                ui_state: "Closed", 
                call_record: YAAI.callRecordId
            } );

        }
    },
    toggleCallBoxGrowth : function(callboxid) {
        if (YAAI.isCallBoxMinimized(callboxid) ) {  
            YAAI.maximizeCallBox(callboxid);
        } 
        else {	
            YAAI.minimizeCallBox(callboxid);
        }
        YAAI.restructureCallBoxes(); // BR added... only needed for vertical stack method.
    },
    
    setContactId : function( callRecordId, contactId) {
        //alert("invoking setContactId");
        $.post("index.php?entryPoint=AsteriskController&action=setContactId", {
            call_record: callRecordId, 
            contact_id: contactId
        } );
        
        //once done swapping callbox should change from multiple select to one select
        
    },
    
    saveMemo : function( callboxid ) {
        var message = YAAI.getMemoText(callboxid);
		
        $('#callbox_'+callboxid+' .callboxinput .callboxtextarea').focus();
        $('#callbox_'+callboxid+' .callboxinput .callboxtextarea').css('height','44px');
        if (message != '') {
		
            var callRecordId = YAAI.getCallCallRecordId( callboxid );
            var theDirection = YAAI.callBoxCallDirections[callboxid];

            $.post("index.php?entryPoint=AsteriskController&action=memoSave", {
                id: callboxid, 
                call_record: callRecordId, 
                description: message, 
                direction: theDirection
            } , function(data){
                //message = message.replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/\"/g,"&quot;");
                $("#callbox_"+callboxid+" .asterisk_save_status").html('Call Details Saved').css("display","block").fadeOut(5000); 
				
            });
        }
		
        // If you don't want SAVE button to also close then comment out line below
        YAAI.closeCallBox(callboxid);
    },

    showTransferMenu : function( callboxid, exten ) {
        if( callboxid != '' ) {
            exten = prompt("Please enter the extension number you'd like to transfer to:\n(Leave Blank to cancel)","");
		
            if( exten != null && exten != '') {
                //alert(exten);	
                var callRecordId = YAAI.getCallCallRecordId( callboxid );
                $.post("index.php?entryPoint=AsteriskController&action=transfer", {
                    id: callboxid, 
                    call_record: callRecordId, 
                    extension: exten
                } , function(data){
                    //alert(data);
                    });
            }
        }
    },     
 /*
 * Relate Contact Callback method.
 * This is called by the open_popup sugar call when a contact is selected.
 *
 * I basically copied the set_return method and added some stuff onto the bottom.  I couldn't figure out how to add
 * change events to my form elements.  This method wouldn't be needed if I figured that out.
 */
    relate_popup_callback : function(popup_reply_data)
    {
        var from_popup_return2 = true;
        var form_name = popup_reply_data.form_name;
        var name_to_value_array = popup_reply_data.name_to_value_array;

        for (var the_key in name_to_value_array)
        {
            if(the_key == 'toJSON')
            {
            /* just ignore */
            }
            else
            {
                var displayValue=name_to_value_array[the_key].replace(/&amp;/gi,'&').replace(/&lt;/gi,'<').replace(/&gt;/gi,'>').replace(/&#039;/gi,'\'').replace(/&quot;/gi,'"');
                ;
                if(window.document.forms[form_name] && window.document.forms[form_name].elements[the_key])
                {
                    window.document.forms[form_name].elements[the_key].value = displayValue;
                    SUGAR.util.callOnChangeListers(window.document.forms[form_name].elements[the_key]);
                }
            }
        }

        // Everything above is from the default set_return method in parent_popup_helper.
        var contactId = window.document.forms[form_name].elements['relateContactId'].value;
        if( contactId != null ) {
            //alert("Setting Contact Id");
            YAAI.setContactId(form_name,contactId);
        }
        else {
            alert("Error updating related Contact");
        }
    },

    // DRAWING/UI FUNCTIONS

    restructureCallBoxes : function(callboxid) {
        var HEIGHT_MINIMIZED = 32;
        var HEIGHT_NORMAL = 293;
        var currHeight = 0;
        for(var i=0; i < YAAI.callBoxes.length; i++ ) {
            var callboxid = YAAI.callBoxes[i];
		
            if( !YAAI.isCallBoxClosed( callboxid ) ) {
                $("#callbox_"+callboxid).css('bottom', currHeight+'px');
			
                if( YAAI.isCallBoxMinimized(callboxid) ) {
                    currHeight += HEIGHT_MINIMIZED;
                }
                else {
                    currHeight += HEIGHT_NORMAL;
                }
            }
        }
        YAAI.nextHeight = currHeight;
	
    },
    
    minimizeExistingCallboxesWhenNewCallComesIn : function(){
        for(var x=0; x < YAAI.callBoxes.length; x++ ) {
            YAAI.minimizeCallBox( YAAI.callBoxes[x] ); // updates a cookie each time... perhaps check first.
        }
          
    },
    
    startVerticalEndVertical : function(callboxid){
        // START VERTICAL
        YAAI.restructureCallBoxes();
        $("#callbox_"+callboxid).css('right', '20px');
        $("#callbox_"+callboxid).css('bottom', YAAI.nextHeight+'px');
        // END VERTICAL
        YAAI.callBoxes.push(callboxid);
    },
    
    setupCallBoxFocusAndBlurSettings : function(callboxid){
        YAAI.callboxFocus[callboxid] = false;
        $("#callbox_"+callboxid+" .callboxtextarea").blur(function(){
            YAAI.callboxFocus[callboxid] = false;
            $("#callbox_"+callboxid+" .callboxtextarea").removeClass('callboxtextareaselected');
        }).focus(function(){
            YAAI.callboxFocus[callboxid] = true;
            YAAI.newMessages[callboxid] = false;
            $('#callbox_'+callboxid+' .callboxhead').removeClass('callboxblink');
            $("#callbox_"+callboxid+" .callboxtextarea").addClass('callboxtextareaselected');
        });

        $("#callbox_"+callboxid).click(function() {
            if ($('#callbox_'+callboxid+' .callboxcontent').css('display') != 'none')
            {
                // TODO Investigate... this sets focus to textarea whenever anywhere is clicked in callbox, needs to be tweaked if I add other inputboxes and could be cause of focus stealing problems which happen occasionally.
                $("#callbox_"+callboxid+" .callboxtextarea").focus();
            }
        });
    },


    maximizeCallBox : function(callboxid) {
        $('#callbox_'+callboxid+' .callboxcontent').css('display','block');
        $('#callbox_'+callboxid+' .callboxinput').css('display','block');
        //$("#callbox_"+callboxid+" .callboxcontent").scrollTop($("#callbox_"+callboxid+" .callboxcontent")[0].scrollHeight);
				
        if( YAAI.isCallBoxMinimized( callboxid ) ) {
            alert( callboxid + " minimize state cookie fail (should be maximized)");
        }
		
        YAAI.updateMinimizeCookie();
    },


    minimizeCallBox : function(callboxid) {
        $('#callbox_'+callboxid+' .callboxcontent').css('display','none');
        $('#callbox_'+callboxid+' .callboxinput').css('display','none');
		
        if( !YAAI.isCallBoxMinimized( callboxid ) ) {
            alert( callboxid + " minimize state cookie fail");
        }
		
        YAAI.updateMinimizeCookie();
    },
    
    switchMultipleMatchViewToSingleMatchView : function (){
      
      
      
    },



    // Saves what is placed in the input box whenever call is saved.
    checkCallBoxInputKey : function(event, callboxtextarea, callboxid) {
	 
        // 13 == Enter
        if(event.keyCode == 13)  {
            // CTRL + ENTER == quick save + close shortcut
            if( event.ctrlKey == 1 ) {
                YAAI.saveMemo( callboxid );
                YAAI.closeCallBox(callboxid);
                return false;
            }
            else if( event.shiftKey != 0 ) {
                YAAI.saveMemo( callboxid );
            //return false; // Returning false prevents return from adding a break.
            }
        }

    },
    
    createCallBoxWithNoMatchingContact : function(){
        
    },
    createCallBoxWithSingleMatchingContact : function(contacts, context, entry){
        context['contact_id'] = entry['contacts'][0]['contact_id'];
        console.log(context['contact_id'] = entry['contacts'][0]['contact_id']);
        context['full_name'] = entry['contacts'][0]['contact_full_name'];
        context['company'] = entry['contacts'][0]['company'];
        context['company_id'] = entry['contacts'][0]['company_id'];      
        
        return context;
    },
    createCallBoxWithMultipleMatchingContacts : function(contacts, context, entry){
        
        context['contacts'] = entry['contacts'];
        Handlebars.registerHelper('each', function(context, options) {
            var ret = "";

            for(var i=0, j=context.length; i<j; i++) {
                ret = ret + options.fn(context[i]);
            }

            return ret;
        });
    
        return context;
    
    },

    //UTILITY FUNCTIONS
    
    getCallCallRecordId : function( callboxid ) {
        return YAAI.callBoxCallRecordIds[callboxid];
    },
    
     // Updates the cookie which stores the state of all the callboxes (whether minimized or maximized)
    // Only problem with this approach is on second browser window you might have them open differently... and this would save the state as such.
    updateMinimizeCookie : function() {
        var cookieVal="";
        console.log(YAAI.callBoxes.length);
        for( var i=0; i< YAAI.callBoxes.length; i++ ) {
		
            if( YAAI.isCallBoxMinimized( YAAI.callBoxes[i] ) ) {
                cookieVal = YAAI.callBoxes[i] + "|";
            }
        }
	
        cookieVal = cookieVal.substr(0, cookieVal.length - 1 ); // remove trailing "|"
	
        console.log(cookieVal);
        $.cookie('callbox_minimized', cookieVal);
    },
    
    checkMinimizeCookie : function (callboxid){
        // Check by looking at the cookie to see if it should be minimized or not.
        var minimizedCallBoxes = new Array();

        if ($.cookie('callbox_minimized')) {
            minimizedCallBoxes = $.cookie('callbox_minimized').split(/\|/);
        }
        var minimize = 0;
        for (var j=0;j < minimizedCallBoxes.length;j++) {
            if (minimizedCallBoxes[j] == callboxid) {
                minimize = 1;
            }
        }

        if (minimize == 1) {
            $('#callbox_'+callboxid+' .callboxcontent').css('display','none');
            $('#callbox_'+callboxid+' .callboxinput').css('display','none');
        }
    },
    
    getAsteriskID : function(astId){
    
        var asterisk_id = astId.replace(/\./g,'-'); // ran into issues with jquery not liking '.' chars in id's so converted . -> -BR //this should be handled in PHP
    
        return asterisk_id;
    }, 

    isCallBoxClosed : function(callboxid) {
        return $("#callbox_"+callboxid).css('display') == 'none';
    },
    
    isCallBoxMinimized : function( callboxid ) {

        return $('#callbox_'+callboxid+' .callboxcontent').css('display') == 'none';

    },
    
    callBoxHasNotAlreadyBeenCreated : function(callboxid){
        var open = (-1 == $.inArray(callboxid, YAAI.callBoxes));
        
        if ($("#callbox_"+callboxid).length > 0) {
            if ($("#callbox_"+callboxid).css('display') == 'none') {
                $("#callbox_"+callboxid).css('display','block');
                YAAI.restructureCallBoxes(callboxid);
            }
            $("#callbox_"+callboxid+" .callboxtextarea").focus();
        }
        
        return open;
    },
    
    checkForErrors : function(entry){
        if( entry['call_record_id'] == "-1" ) {
            alert( "Call Record ID returned from server is -1, unable to save call notes for " + entry['title'] ); // TODO: disable the input box instead of this alert.
        }  
    },
 
    getMemoText : function( callboxid ) {
        var message = "";
        message = $('#callbox_'+callboxid+' .callboxinput .callboxtextarea').val();
        message = message.replace(/^\s+|\s+$/g,""); // Trims message
	
        return message;
    },
 
    getCookies : function(){
        var pairs = document.cookie.split(";");
        var cookies = {};
        for (var i=0; i<pairs.length; i++){
            var pair = pairs[i].split("=");
            cookies[pair[0]] = unescape(pair[1]);
        }
        return cookies;
    },
    
    log : function(message) {
        if (Switchboard.options.debug) {
            console.log(message);
        }
    }

}


/**
 * Cookie plugin
 *
 * Copyright (c) 2006 Klaus Hartl (stilbuero.de)
 * Dual licensed under the MIT and GPL licenses:
 * http://www.opensource.org/licenses/mit-license.php
 * http://www.gnu.org/licenses/gpl.html
 *
 */

jQuery.cookie = function(name, value, options) {
    if (typeof value != 'undefined') { // name and value given, set cookie
        options = options || {};
        if (value === null) {
            value = '';
            options.expires = -1;
        }
        var expires = '';
        if (options.expires && (typeof options.expires == 'number' || options.expires.toUTCString)) {
            var date;
            if (typeof options.expires == 'number') {
                date = new Date();
                date.setTime(date.getTime() + (options.expires * 24 * 60 * 60 * 1000));
            } else {
                date = options.expires;
            }
            expires = '; expires=' + date.toUTCString(); // use expires attribute, max-age is not supported by IE
        }
        // CAUTION: Needed to parenthesize options.path and options.domain
        // in the following expressions, otherwise they evaluate to undefined
        // in the packed version for some reason...
        var path = options.path ? '; path=' + (options.path) : '';
        var domain = options.domain ? '; domain=' + (options.domain) : '';
        var secure = options.secure ? '; secure' : '';
        document.cookie = [name, '=', encodeURIComponent(value), expires, path, domain, secure].join('');
    } else { // only name given, get cookie
        var cookieValue = null;
        if (document.cookie && document.cookie != '') {
            var cookies = document.cookie.split(';');
            for (var i = 0; i < cookies.length; i++) {
                var cookie = jQuery.trim(cookies[i]);
                // Does this cookie string begin with the name we want?
                if (cookie.substring(0, name.length + 1) == (name + '=')) {
                    cookieValue = decodeURIComponent(cookie.substring(name.length + 1));
                    break;
                }
            }
        }
        return cookieValue;
    }
};


$(document).ready(function(){
    // no checking for the login page
    if(location.href.indexOf('action=Login') == -1){
        YAAI.checkForNewStates();
        console.log('works');
    }
});


