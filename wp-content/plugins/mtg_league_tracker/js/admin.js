jQuery(document).ready(function ($) {
    var frame;
    $('#upload_button, #mtglt_result_file').click(function() {
        
        event.preventDefault();

        // If the media frame already exists, reopen it.
        if ( frame ) {
          frame.open();
          return;
        }

        // Create a new media frame
        frame = wp.media({
          title: 'Select or Upload a Result File',
          button: {
            text: 'Use this Result File'
          },
          multiple: false  // Set to true to allow multiple files to be selected
        });
        frame.open();


        // When an image is selected in the media frame...
        frame.on( 'select', function() {

          // Get media attachment details from the frame state
          var attachment = frame.state().get('selection').first().toJSON();

          // Send the attachment id to our input field
          $('#mtglt_result_file').val( attachment.url );
        });
    });

});