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
    callboxFocus : [],
    newMessages : [],
    callBoxes : [],
    sugarUserID : window.current_user_id,
    phoneExtension : window.yaai_user_extension,
    pollRate: window.yaai_poll_rate,
    fop2 : true,
    fop2URL : 'http://67-228-218-138.lx-vs.net',
    fop2UserID : window.yaai_user_extension,
    fop2Password : window.current_user_id.substring(0, 8),
    filteredCallStates : ['Ringing'],
    options : {
        debug: true
    },
    checkForNewStates : function(){
        // Note: once the user gets logged out, the ajax requests will get redirected to the login page.
        // Originally, the setTimeout method was in this method.  But, no way to detect the redirect without server side
        // changes.  See: http://stackoverflow.com/questions/199099/how-to-manage-a-redirect-request-after-a-jquery-ajax-call
        // So, now I only schedule a setTimeout upon a successful AJAX call.  The only downside of this is if there is a legit reason
        // the call does fail it'll never try again..
        $.getJSON('index.php?entryPoint=AsteriskController&action=get_calls', function(data){
            console.log(data);
            var callboxids = [];
            setTimeout('YAAI.checkForNewStates()', YAAI.pollRate);  

            if( data != ".") {
                $.each(data, function(entryIndex, entry){    
                    if(YAAI.callStateIsNotFiltered){
                        var callboxid = YAAI.getAsteriskID(entry['asterisk_id']); 
                        callboxids.push(callboxid);            
	    
                        if(YAAI.callBoxHasNotAlreadyBeenCreated(callboxid)) {
                            YAAI.createCallBox(callboxid, entry);
                            console.log('create');
                        }
                        else {  
                            YAAI.updateCallBox(callboxid, entry);
                            console.log('update');
                        }
                    }
                });  
            }
        
            YAAI.wasCallBoxClosedInAnotherBrowserWindow(callboxids);
        })
        .error(function(){
            YAAI.log('there is a problem with getJSON in checkForNewStates()');
        });	
    },

    // CREATE
    
    createCallBox : function (callboxid, entry, modstrings) {
        var html;
        var template = Handlebars.templates['call-template.html'];
        var context = {
            callbox_id : 'callbox_' + callboxid,
            title : entry['title'],
            asterisk_state : entry['state'],
            call_type : entry['call_type'],
            duration : entry['duration'] + ' mins',
            phone_number: entry['phone_number'],
            caller_id: entry['caller_id'],
            call_record_id: entry['call_record_id'],
            select_contact_label: entry['mod_strings']['ASTERISKLBL_SELECTCONTACT'],
            name_label: entry['mod_strings']['ASTERISKLBL_NAME'],
            company_label: entry['mod_strings']['ASTERISKLBL_COMPANY'],
            create_label: entry['mod_strings']['CREATE'],
            relate_to_label: entry['mod_strings']['RELATE_TO'],
            caller_id_label: entry['mod_strings']['ASTERISKLBL_CALLERID'],
            phone_number_label: entry['mod_strings']['CALL_DESCRIPTION_PHONE_NUMBER'],
            duration_label: entry['mod_strings']['ASTERISKLBL_DURATION'],
            block_label: entry['mod_strings']['BLOCK']
        };

        switch(entry['contacts'].length){
            case 0 :
                html = template(context); 
                $('body').append(html);
                YAAI.createCallBoxWithNoMatchingContact(callboxid, entry);
                YAAI.bindActionDropdown(callboxid);
                $('#callbox_'+callboxid).find('.nomatchingcontact').show();
                break;
            
            case 1 :
                context = YAAI.createCallBoxWithSingleMatchingContact(callboxid, context, entry);
                html = template(context);
                $('body').append(html);
                $('#callbox_'+callboxid).find('.singlematchingcontact').show();
                break;
                
            default :
                context = YAAI.createCallBoxWithMultipleMatchingContacts(callboxid, context, entry);
                html = template(context);
                $('body').append(html);
                $('#callbox_'+callboxid).find('.multiplematchingcontacts').show();
                break;
        }
        
        //bind user actions
        YAAI.bindCheckCallBoxInputKey(callboxid, entry['call_record_id'], entry['phone_number'], entry['direction']);
        YAAI.fop2 ? YAAI.bindOperatorPanel(callboxid) : YAAI.bindTransferButton(callboxid, entry);
        YAAI.bindCloseCallBox(callboxid, entry['call_record_id']);
        YAAI.bindToggleCallBoxGrowth(callboxid);
        YAAI.bindSaveMemo(callboxid, entry['call_record_id'], entry['phone_number'], entry['direction']);

        //draw 
        YAAI.showCallerIDWhenAvailable(entry);
        YAAI.minimizeExistingCallboxesWhenNewCallComesIn();
        YAAI.startVerticalEndVertical(callboxid);  //procedurally this must go after minimizeExistingCallboxesWhenNewCallComesIn
        YAAI.checkMinimizeCookie(callboxid);
        YAAI.setupCallBoxFocusAndBlurSettings(callboxid);
        
        YAAI.checkForErrors(entry);

        $('.callbox').show();
        $("#callbox_"+callboxid).show();
    },
    
    // UPDATE
    
    updateCallBox : function (callboxid, entry){
        $(".asterisk_state", "#callbox_"+callboxid+" .callboxcontent").text(entry['state']);
       
        if( entry['is_hangup']  ) {
            $("#callbox_"+callboxid+" .callboxhead").css("background-color", "#f99d39");
        }
        else {
            $("#callbox_"+callboxid+" .callboxhead").css("background-color", "#0D5995"); // a blue color	
        }
				
        $(".call_duration", "#callbox_"+callboxid+" .callboxcontent").text( entry['duration'] ); // Updates duration
        
        YAAI.refreshContactView(callboxid, entry);
        
    },
    
    // CLEANUP
    
    wasCallBoxClosedInAnotherBrowserWindow : function  (callboxids){
        for(var i=0; i < YAAI.callBoxes.length; i++ ) {
            if( -1 == $.inArray(YAAI.callBoxes[i], callboxids) ) {
                if( YAAI.callboxFocus[i]) {
                // Don't auto close the callbox b/c there is something entered or it has focus.
                }
                else {
                    YAAI.closeCallBox( YAAI.callBoxes[i] );
                    YAAI.restructureCallBoxes();
                    YAAI.callBoxes.splice(i,1); // todo is callBoxes.length above evaluated dynamically?
                }
            }
        }
    },
    
    // BIND CLICKABLE ACTIONS TO HTML ELEMENTS
    bindToggleCallBoxGrowth : function (callboxid){
        $('#callbox_'+callboxid).find('.callboxhead').on("click",  function(){
            YAAI.toggleCallBoxGrowth(callboxid);
        });
    },
    
    bindCloseCallBox : function(callboxid, call_record_id){
        $('#callbox_'+callboxid).find('.callboxoptions a').on("click", function(){
            YAAI.closeCallBox(callboxid, call_record_id);
        });  
    },
    
    bindSaveMemo : function(callboxid, call_record_id, phone_number, direction){
        $('#callbox_'+callboxid).find('.save_memo').button().on("click", function(){
            YAAI.saveMemo(callboxid, call_record_id, phone_number, direction);  
        });
    },

    bindTransferButton : function(callboxid, entry){
        $('#callbox_'+callboxid).find('.operator_panel').button( { 
            icons: {
                primary: 'ui-icon-custom-phone', 
                secondary: null
            }
        }).on("click", function(){
            YAAI.showTransferMenu(entry);  
        }); 
    },
    bindActionDropdown : function(callboxid){
        
         $('#callbox_'+callboxid).find('.callbox_action').button({
                icons: {
                    primary: "ui-icon-flag",
                    secondary: "ui-icon-triangle-1-s"
                },
                text: false
            }).show();
       
        
    },
    
    bindOperatorPanel : function(callboxid){ 
        
        $('#callbox_'+callboxid).find('.operator_panel').button({
            icons: {
                primary: 'ui-icon-custom-phone', 
                secondary: null
            }
        }).on("click", function(){
        
            $.fancybox({
                href : YAAI.fop2URL + '/fop2/?exten=' + YAAI.fop2UserID + '&pass=' + YAAI.fop2Password,
                type : 'iframe',
                padding : 5,
                showCloseButton : true,
                afterClose : function() {
                    window.location.href=window.location.href
                }
            }); 
        });
        
    },

     
    bindCheckCallBoxInputKey : function(callboxid){
        $('#callbox_'+callboxid).find('.transfer_button').keydown(function(event){
            YAAI.checkCallBoxInputKey(event, callboxid, entry);
        }); 
        
    },
    
    bindOpenPopupSingleMatchingContact : function(callboxid, entry){
        $('#callbox_'+callboxid).find('.singlematchingcontact .unrelate_contact').button({
            icons: {
                primary: "ui-icon ui-icon-close"
            },
            text: false
        }).on("click", function(){
            YAAI.openPopup(entry);
        });  
    },
    
    bindSetContactID : function(callboxid, entry){
        $('#callbox_'+callboxid).find('.multiplematchingcontacts td p').on("click", "input",  function(){
            YAAI.setContactID(entry['call_record_id'], this.value);
        })  
    },
    
    /// USER ACTIONS
    closeCallBox : function(callboxid, call_record_id) {
        if( !YAAI.isCallBoxClosed(callboxid) ) {
            $('#callbox_'+callboxid).remove();
            $('#block-number-callbox_'+callboxid).remove();
            $('#dropdown-1_callbox_'+callboxid).remove();
            
            YAAI.restructureCallBoxes();  
            
            if(call_record_id){
                // Tells asterisk_log table that user has closed this entry.
                $.post("index.php?entryPoint=AsteriskController&action=updateUIState", {
                    id: callboxid, 
                    ui_state: "Closed", 
                    call_record: call_record_id
                } );
            }

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
    
    setContactID : function( callRecordId, contactId) {
        $.post("index.php?entryPoint=AsteriskController&action=setContactID", {
            call_record: callRecordId, 
            contact_id: contactId
        } );
        
    //once done swapping callbox should change from multiple select to one select
        
    },
    
    saveMemo : function(callboxid, call_record_id, phone_number, direction) {
        var message = YAAI.getMemoText(callboxid);
    
        if (message != '') {
            $.post("index.php?entryPoint=AsteriskController&action=memoSave", {
                id: callboxid, 
                call_record: call_record_id, 
                description: message, 
                direction: direction,
                sugar_user_id: YAAI.sugarUserID,
                phone_number: phone_number
            })
            .success(function() {
                // If you don't want SAVE button to also close then comment out line below
                YAAI.closeCallBox(callboxid, call_record_id);
            })
            .error(function(){
                alert("Problem Saving Notes")
            });
        }
    },
    openPopupNoMatchingContact : function(entry){
        YAAI.openPopup(entry);  
    },
    
    openPopup : function (entry){
        open_popup( "Contacts", 600, 400, "", true, true, {
            "call_back_function":"relate_popup_callback",
            "form_name": entry['call_record_id'],
            "field_to_name_array":{
                "id":"relateContactId",
                "last_name":"relateContactName"
            }
        },"single",true);   
    },

    showTransferMenu : function(entry, callboxid, exten ) {
        if( callboxid != '' ) {
            exten = prompt("Please enter the extension number you'd like to transfer to:\n(Leave Blank to cancel)","");
		
            if( exten != null && exten != '') {
                $.post("index.php?entryPoint=AsteriskController&action=transfer", {
                    id: callboxid, 
                    call_record: entry['call_record_id'], 
                    extension: exten
                });
            }
        }
    }, 
    
    showBlockNumberDialog : function(callboxid, entry){
        //setup form display
        $("#block-number-callbox_"+callboxid).find('.block-phone-number').val(entry['phone_number']);
    
        //present dialog
        $("#block-number-callbox_"+callboxid).dialog({
            autoOpen: true,
            resizable: false,
            width: 400,
            height:300,
            modal: true,
            buttons: {
                "Block Caller": function() {                     
                    $.ajax({
                        url:"index.php?entryPoint=AsteriskController&action=block",
                        data: {
                            number: entry['phone_number'],
                            reason: $("#block-number-"+callboxid).find('.reason').val(),
                            description: $("#block-number-"+callboxid).find('.block-description').val(),
                            agent_id: YAAI.sugarUserID
                        }, 
                        type: "POST",			
                        success: function(transport){
                            alert(entry['phone_number'] + ' Caller Blocked');
                        },
                        error: function (jqXHR, textStatus, thrownError){
                            YAAI.log(jqXHR.status);
                            YAAI.log(textStatus);
                            YAAI.log(thrownError);
                        }
                    });
                    $( this ).dialog( "close" );
                },
                Cancel: function() {
                    $( this ).dialog( "close" );
                }
            }
        });  
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
            YAAI.setContactID(form_name,contactId);
        }
        else {
            alert("Error updating related Contact");
        }
    },

    // DRAWING/UI FUNCTIONS

    restructureCallBoxes : function(callboxid) {
        var currHeight = 0;
        for(var i=0; i < YAAI.callBoxes.length; i++ ) {
            var callboxid = YAAI.callBoxes[i];
            
            if( !YAAI.isCallBoxClosed( callboxid ) ) {   
                //put first box at 0 height - bottom of page
                $("#callbox_"+callboxid).css('bottom', currHeight+'px');       
                //then grab the height of the box - this will tell if it is open or not
                currHeight += $("#callbox_"+callboxid).height();  
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
    },


    maximizeCallBox : function(callboxid) {
        $('#callbox_'+callboxid+' .control_panel').css('display', 'block');
        $('#callbox_'+callboxid+' .callboxcontent').css('display','block');
        $('#callbox_'+callboxid+' .callboxinput').css('display','block');
        //$("#callbox_"+callboxid+" .callboxcontent").scrollTop($("#callbox_"+callboxid+" .callboxcontent")[0].scrollHeight);
				
        if( YAAI.isCallBoxMinimized( callboxid ) ) {
            YAAI.log( callboxid + " minimize state cookie fail (should be maximized)");
        }
		
        YAAI.updateMinimizeCookie();
    },


    minimizeCallBox : function(callboxid) {
        $('#callbox_'+callboxid+' .control_panel').css('display', 'none');
        $('#callbox_'+callboxid+' .callboxcontent').css('display','none');
        $('#callbox_'+callboxid+' .callboxinput').css('display','none');
		
        if( !YAAI.isCallBoxMinimized( callboxid ) ) {
            YAAI.log( callboxid + " minimize state cookie fail");
        }
		
        YAAI.updateMinimizeCookie();
    },
    
    showCallerIDWhenAvailable : function(entry){
        if(entry['caller_id']){
            $('#caller_id').show();
        }
    },
    
    refreshContactView : function (callboxid, entry){
        //see if a multiple contacts match has been selected to one contact or a no contact match has been selected to one contact
      
        if(entry['contacts'].length == 1 && $('#callbox_'+callboxid).find('.singlematchingcontact').is(':hidden') ){  
            YAAI.refreshSingleMatchingContact(callboxid, entry);
        
            $('#callbox_'+callboxid).find('.nomatchingcontact').hide();
            $('#callbox_'+callboxid).find('.multiplematchingcontacts').hide()
            $('#callbox_'+callboxid).find('.singlematchingcontact').show();
        }
        
        //check if a single contacts match has been changes - must do this here because using SugarCRMs function we lose control of callback
        if(entry['contacts'].length == 1){
            var old_contact_name = $('#callbox_'+callboxid).find('.singlematchingcontact td span.call_contacts').text();
            var new_contact_name = entry['contacts'][0]['contact_full_name'];
            
            if(old_contact_name != new_contact_name){
                YAAI.refreshSingleMatchingContact(callboxid, entry);
            } 
        }
    },
    
    refreshSingleMatchingContact : function(callboxid, entry){
        $('#callbox_'+callboxid).find('.singlematchingcontact td a.contact_id').attr('href', 'index.php?module=Contacts&action=DetailView&record='+entry['contacts'][0]['contact_id']);
        $('#callbox_'+callboxid).find('.singlematchingcontact td span.call_contacts').text(entry['contacts'][0]['contact_full_name']); 
        $('#callbox_'+callboxid).find('.singlematchingcontact td a.company').attr('href', 'index.php?module=Accounts&action=DetailView&record='+entry['contacts'][0]['company_id']);
        $('#callbox_'+callboxid).find('.singlematchingcontact td a.company').text(entry['contacts'][0]['company']);    
    },



    // Saves what is placed in the input box whenever call is saved.
    checkCallBoxInputKey : function(event, callboxid, call_record_id, phone_number, direction) {
	 
        // 13 == Enter
        if(event.keyCode == 13)  {
            // CTRL + ENTER == quick save + close shortcut
            if( event.ctrlKey == 1 ) {
                YAAI.saveMemo(call_record_id, phone_number, direction);
                YAAI.closeCallBox(callboxid, call_record_id);
                return false;
            }
            else if( event.shiftKey != 0 ) {
                YAAI.saveMemo(call_record_id, phone_number, direction);
            //return false; // Returning false prevents return from adding a break.
            }
        }

    },
    
    createCallBoxWithNoMatchingContact : function(callboxid, entry){
        $("#dropdown-1_callbox_"+callboxid+" ul").append("<li><a href='#' class='relate_to_contact'>Relate to Contact</a></li>");
        $("#dropdown-1_callbox_"+callboxid+" ul a.relate_to_contact").on("click", entry, function() {
            YAAI.openPopupNoMatchingContact(entry)
        });
        $("#dropdown-1_callbox_"+callboxid+" ul").append("<li><a href='#' class='create_contact'>Create Contact</a></li>");
        $("#dropdown-1_callbox_"+callboxid+" ul a.create_contact").on("click", entry, function() {
            YAAI.createContact(entry)
        });
        $("#dropdown-1_callbox_"+callboxid+" ul").append("<li><a href='#' class='block_number'>Block Number</a></li>");
        $("#dropdown-1_callbox_"+callboxid+" ul a.block_number").on("click", {
            entry: entry, 
            callboxid: callboxid
        }, function() {
            YAAI.showBlockNumberDialog(callboxid, entry)
        });
    },
    
    createCallBoxWithSingleMatchingContact : function(callboxid, context, entry){
        context['contact_id'] = entry['contacts'][0]['contact_id'];
        context['full_name'] = entry['contacts'][0]['contact_full_name'];
        context['company'] = entry['contacts'][0]['company'];
        context['company_id'] = entry['contacts'][0]['company_id'];
        
        YAAI.bindOpenPopupSingleMatchingContact(callboxid, entry);
        
        return context;
    },
    createCallBoxWithMultipleMatchingContacts : function(callboxid, context, entry){
        
        context['contacts'] = entry['contacts'];
        Handlebars.registerHelper('each', function(context, options) {
            if(typeof context != "undefined"){
                var ret = "";

                for(var i=0, j=context.length; i<j; i++) {
                    ret = ret + options.fn(context[i]);
                }

                return ret;
            }
    
        });
    
        YAAI.bindSetContactID(callboxid, entry);
    
        return context;
    
    },

    //UTILITY FUNCTIONS
    
    createContact : function (entry) {
    
        var phone_number = entry['phone_number'];
    
        window.location = "index.php?module=Contacts&action=EditView&phone_work="+phone_number;
    },    
    
    // Updates the cookie which stores the state of all the callboxes (whether minimized or maximized)
    // Only problem with this approach is on second browser window you might have them open differently... and this would save the state as such.
    updateMinimizeCookie : function() {
        var cookieVal="";
        for( var i=0; i< YAAI.callBoxes.length; i++ ) {
		
            if( YAAI.isCallBoxMinimized( YAAI.callBoxes[i] ) ) {
                cookieVal = YAAI.callBoxes[i] + "|";
            }
        }
	
        cookieVal = cookieVal.substr(0, cookieVal.length - 1 ); // remove trailing "|"
	
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
            $('#callbox_'+callboxid+' .control_panel').css('display', 'none');
            $('#callbox_'+callboxid+' .callboxcontent').css('display','none');
            $('#callbox_'+callboxid+' .callboxinput').css('display','none');
        }
    },
    
    getAsteriskID : function(astId){
    
        var asterisk_id = astId.replace(/\./g,'-'); // ran into issues with jquery not liking '.' chars in id's so converted . -> -BR //this should be handled in PHP
    
        return asterisk_id;
    }, 

    isCallBoxClosed : function(callboxid) {
        return $('#callbox_'+callboxid).length == 0;
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
        }
        
        return open;
    },
    
    checkForErrors : function(entry){
        if( entry['call_record_id'] == "-1" ) {
            YAAI.log( "Call Record ID returned from server is -1, unable to save call notes for " + entry['title'] ); // TODO: disable the input box instead of this alert.
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
        if (YAAI.options.debug) {
            console.log(message);
        }
    },
    callStateIsNotFiltered : function(){
      return ($.inArray(entry.state, YAAI.filteredCallStates) == -1);
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
       YAAI.checkForNewStates();
});


