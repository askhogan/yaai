
    
var FakeDialer = {
    
    call_counter: 1,
    call_setup_data: {},
    
    
    action : function (id, extension){
    
        FakeDialer.determine_action(id, extension);
    },

    determine_action : function(id, extension){
        var call_number = id.charAt(id.length-1);

        if(id.search("dial") >= 0){
            FakeDialer.action_dial(call_number, extension);
        }
        
        if(id.search("connected") >= 0){
            FakeDialer.action_connected(call_number, extension);
        }
        
        if(id.search("hangup") >= 0){
            FakeDialer.action_hangup(call_number, extension);
        }
        
        if(id.search("closed") >= 0){
            FakeDialer.action_closed(call_number, extension);
        }

    },

    action_dial : function(call_number, extension){
        $.ajax({
            url:"index.php?entryPoint=AsteriskFakeDialerActions",
            data: {
                action: 'dial',
                extension: extension     
            }, 
            type: "POST",			
            success: function(transport){      
                var call_data = $.parseJSON(transport);
            
                FakeDialer.call_setup_data[call_number] = call_data;
            },
            error: function (jqXHR, textStatus, thrownError){
                console.log(jqXHR.status);
                console.log(textStatus);
                console.log(thrownError);
            }
        });
    },

    action_connected : function(call_number, extension){
        $.ajax({
            url:"index.php?entryPoint=AsteriskFakeDialerActions",
            data: {
                action: 'connected',
                call_record_id: FakeDialer.call_setup_data[call_number]['call_record_id']     
            }, 
            type: "POST",			
            success: function(call_record_id){
                $('#dial_'+call_number).removeClass( "ui-state-highlight" );
        
            //could take ui-state off dialed
            },
            error: function (jqXHR, textStatus, thrownError){
                console.log(jqXHR.status);
                console.log(textStatus);
                console.log(thrownError);
            }
        });
    },

    action_hangup : function(call_number, extension){
        $.ajax({
            url:"index.php?entryPoint=AsteriskFakeDialerActions",
            data: {
                action: 'hangup',
                call_record_id: FakeDialer.call_setup_data[call_number]['call_record_id']    
            }, 
            type: "POST",			
            success: function(call_record_id){
                $('#connected_'+call_number).removeClass( "ui-state-highlight" );
        
            //could take ui-state off dialed
            },
            error: function (jqXHR, textStatus, thrownError){
                console.log(jqXHR.status);
                console.log(textStatus);
                console.log(thrownError);
            }
        });
    },

    action_closed : function(call_number, extension){
        $.ajax({
            url:"index.php?entryPoint=AsteriskFakeDialerActions",
            data: {
                action: 'closed',
                call_record_id: FakeDialer.call_setup_data[call_number]['call_record_id'],
                contact_1: FakeDialer.call_setup_data[call_number]['contact_1'],
                contact_2: FakeDialer.call_setup_data[call_number]['contact_2']
            }, 
            type: "POST",			
            success: function(call_record_id){
                $('#hangup_'+call_number).removeClass( "ui-state-highlight" );
        
            //could take ui-state off dialed
            },
            error: function (jqXHR, textStatus, thrownError){
                console.log(jqXHR.status);
                console.log(textStatus);
                console.log(thrownError);
            }
        });
    },
    extension_input_dialog : function(){
        $( "#extension-input" ).dialog({
            autoOpen: false,
            height: 200,
            width: 400,
            modal: true,
            buttons: {
                Ok: function() {                
                    $('#call_setup').clone().appendTo('#main').show().attr('id', 'call_setup_' + FakeDialer.call_counter).find('div').attr('id', function(i, id){
                        return id +'_'+ FakeDialer.call_counter
                    });
                    $('.draggable').draggable();
                    $( ".droppable" ).droppable({
                        drop: function( event, ui ) {
                            
                            $( this )
                            .addClass( "ui-state-highlight" )
                                
                            FakeDialer.action( $(this).attr('id'), $('#extension').val() )      
                                
                            if($(this).attr('id').indexOf("closed") >= 0){
                                $(this).parent('div').hide();
                                    
                            }
                        }
                    });
                    FakeDialer.call_counter++;
                    $( this ).dialog( "close" );
                },
                Cancel: function() {
                    $( this ).dialog( "close" );
                }
            }
        });
    }
}

$(function() {     
    FakeDialer.extension_input_dialog();
    
    $("#add_call").button().click(function( ) {
        $( "#extension-input" ).dialog( "open" );
    });
    
    $("#clone_elements").hide();
});