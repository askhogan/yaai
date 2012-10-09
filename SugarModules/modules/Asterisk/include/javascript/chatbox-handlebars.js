 /* 
$(window).load(function(){
  var source;
    var template;
    var context;
    var html;
	
    $.ajax({
        url: 'custom/modules/Asterisk/include/templates/chatbox-template.html',
        cache: true,
        success: function(data) {
            source    = data;
            template  = Handlebars.compile(source);
            context = {
                title: "My New Post", 
                body: "This is my first post!"
            }
            html = template(context);
            $('body').prepend(html);
            $('#chatbox').show();
            
        }               
    });
});
*/