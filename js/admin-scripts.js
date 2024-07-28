jQuery(document).ready(function($) {
    var mediaUploader;

    $('.upload-media-button').click(function(e) {
        e.preventDefault();

        var inputId = $(this).data('input-id');
        var title = $(this).data('title');
        var buttonText = $(this).data('button-text');

        // If the uploader object has already been created, reopen the dialog
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        // Extend the wp.media object
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: title,
            button: {
                text: buttonText
            },
            multiple: false
        });

        // When a file is selected, grab the URL and set it as the text field's value
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#' + inputId).val(attachment.url);
        });

        // Open the uploader dialog
        mediaUploader.open();
    });
});
