jQuery(document).ready(function($) {
    // Uploader for single company logo
    $('#upload_logo_button').click(function(e) {
        e.preventDefault();
        var uploader = wp.media({
            title: 'انتخاب لوگوی شرکت', button: { text: 'انتخاب لوگو' }, multiple: false
        }).on('select', function() {
            var attachment = uploader.state().get('selection').first().toJSON();
            $('#cim_company_logo').val(attachment.id);
            var previewWrapper = $('.logo-preview-wrapper.single');
            previewWrapper.html('<div class="logo-item"><img src="' + attachment.url + '" /></div>');
            $('.remove-single-logo').show();
        }).open();
    });

    $('.remove-single-logo').click(function(e) {
        e.preventDefault();
        $('#cim_company_logo').val('');
        $('.logo-preview-wrapper.single').html('');
        $(this).hide();
    });

    // Uploader for multiple client logos
    $('#upload_logos_button').click(function(e) {
        e.preventDefault();
        var uploader = wp.media({
            title: 'انتخاب لوگوی مشتریان', button: { text: 'انتخاب لوگوها' }, multiple: true
        }).on('select', function() {
            var attachments = uploader.state().get('selection').models;
            var ids = $('#cim_client_logos').val().split(',').filter(Boolean);
            var previewWrapper = $('.logo-preview-wrapper.multiple');
            
            attachments.forEach(function(attachment) {
                var id = String(attachment.id);
                if (!ids.includes(id)) {
                    ids.push(id);
                    previewWrapper.append('<div class="logo-item"><img src="' + attachment.attributes.url + '" data-id="'+id+'" /><span class="remove-logo">×</span></div>');
                }
            });
            $('#cim_client_logos').val(ids.join(','));
        });
        uploader.open();
    });

    $('.logo-preview-wrapper.multiple').on('click', '.remove-logo', function() {
        var item = $(this).closest('.logo-item');
        var idToRemove = String(item.data('id'));
        var idsInput = $('#cim_client_logos');
        var ids = idsInput.val().split(',').filter(Boolean);
        var newIds = ids.filter(id => id !== idToRemove);
        
        idsInput.val(newIds.join(','));
        item.remove();
    });
});