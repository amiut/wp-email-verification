function resend_verify_link( identifier ){
    var resend = confirm( dwverify.confirm_text );

    if( resend === true ){
        jQuery.get( dwverify.ajaxurl, { action: 'dw_resend_verify', user_login: identifier }, function( response ){
            var response = jQuery.parseJSON( response );
            alert( response.message );
        });
    }
}
