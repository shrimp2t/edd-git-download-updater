
jQuery( document ).ready( function( $ ){
    $( '.git-repo' ).each( function(){
        var repo = $( this );
        var current_repo = $( this ).val();
        var  p = $( this ).parent();
        var tag =  $( '.git-tag', p );
        $( '.edd_git_load_file', p ) .hide();
        $( '.git-reload-tags', p ) .show();
        if ( current_repo ) {
            $( '.spinner', p ) .addClass('show_spinner');
            $.post(ajaxurl, {action: 'edd_git_get_tags', repo: current_repo}, function (response) {
                var current_tag = $( 'option', tag ).eq( 0 ).attr('value');
                $( '.spinner', p ) .removeClass('show_spinner');
                console.log( current_tag );
                if ( 'undefined' == typeof response.error ) {
                    tag.find( 'option' ).remove();
                    _.each( response, function( t ) {
                        tag.append( '<option value="' + t + '">' + t + '</option>' );
                    } );
                    tag.trigger( 'change' );

                    $( 'option[value="'+current_tag+'"]', tag ).attr( 'selected', 'selected' );
                    tag .show();
                    if ( tag.val() ){
                        $( '.edd_git_load_file', p ) .show();
                    }

                }
            });
        } else {
            tag.hide();
            $( '.git-reload-tags', p ) .hide();
        }
    } );

    $( document ).on( 'change', '.git-repo', function(){
        var repo         = $( this );
        var  p           = repo.parent();
        var current_repo = repo.val();
        var tag          =  $( '.git-tag', p );
        if ( current_repo ) {

            if (typeof  this.__xhr === 'undefined') {
                this.__xhr = false;
            }
            if (this.__xhr) {
                this.__xhr.abort();
                this.__xhr = false;
            }
            $( '.spinner', p ) .addClass('show_spinner');
            $( '.git-reload-tags', p ) .show();
            this.__xhr = $.ajax({
                url: ajaxurl,
                data: {action: 'edd_git_get_tags', repo: current_repo},
                dataType: 'json',
                success: function (response) {
                    var current_tag = '';
                    $( '.spinner', p ) .removeClass('show_spinner');
                    if ('undefined' == typeof response.error) {
                        tag.find('option').remove();
                        _.each(response, function (t) {
                            tag.append('<option value="' + t + '">' + t + '</option>');
                        });
                        $('option[value="' + current_tag + '"]', tag ).attr('selected', 'selected');
                        tag.trigger('change');
                        tag.show();

                    }
                }
            });
        } else {
            tag .hide();
            $( '.edd_git_load_file, .git-reload-tags', p ) .hide();
            $( '.spinner', p ) .removeClass('show_spinner');
        }

    } );


    $( document ).on( 'change', '.git-tag', function( e ){
        var  p           = $( this ).parent();
        if ( $( this ).val() ) {
            $( '.edd_git_load_file', p ) .show();
        } else {
            $( '.edd_git_load_file', p ) .hide();
        }
    } );


    $( document ).on( 'click', '.git-reload-tags', function( e ){
        $( '.git-repo', $( this ).closest( '.edd_git_wrapper' ) ).trigger( 'change' );
    } );


    $( document ).on( 'click', '.edd_git_load_file', function( e ){
        e.preventDefault();
        var button = $( this );
        if ( button.hasClass( 'updating-message' ) ) {
            return false;
        }
        var p = button.closest( '.edd_repeatable_row' );
        var repo_url = $( '.git-repo', p ).val();
        var folder_name = '';
        var tag = $( '.git-tag', p ).val();
        var key = p.data( 'key' );
        var file_name = '';
        var condition = $( '.edd_repeatable_condition_field', p ).val();
        button.addClass( 'updating-message' );
        $( '.spinner', p ) .addClass('show_spinner');
        $.post( ajaxurl, { action: 'edd_git_update_file', post_id: edd_vars.post_id, condition: condition, file_name: file_name, key:key, version: tag, folder_name: folder_name, repo_url: repo_url }, function( response ) {
            $( '.git-update-spinner' ).hide();
            console.log(response);
            button.removeClass( 'updating-message' );
            $( '.spinner', p ) .removeClass('show_spinner');
            if ( null == response.errors && 'object' == typeof response ) { // No errors

                $( '.edd_repeatable_name_field', p ).val( response.name );
                $( '.edd_repeatable_upload_field', p ).val( response.file );
                // Update changelog
                if ( key == 0 || key == '0' ) {
                    if ('checked' == $('#edd_license_enabled').attr('checked')) {
                        $('#edd_sl_version').val(response.sl_version);
                        if (response.changelog) {
                            if (typeof tinyMCE != 'undefined' && tinyMCE.get('edd_sl_changelog')) {
                                tinyMCE.get('edd_sl_changelog').setContent(response.changelog);
                            } else {
                                $('#edd_sl_changelog').val(response.changelog);
                            }
                        }
                    }
                }


            } else if ( 'undefined' != typeof response.errors ) { // We had an errors
                // Errror
            } else {
               //
            }

        } );
    } );



} );
