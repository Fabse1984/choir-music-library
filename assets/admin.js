(function ($) {
    function label(key, fallback) {
        return window.cmlAdminLabels && window.cmlAdminLabels[key] ? window.cmlAdminLabels[key] : fallback;
    }

    function createRow(group, attachment) {
        var filename = attachment.filename || attachment.title || '';

        return [
            '<div class="cml-file-row">',
            '<input type="hidden" name="cml[' + group + '][]" value="' + attachment.id + '" class="cml-file-id">',
            '<input type="text" name="cml[' + group + '_titles][]" value="" class="cml-file-title" placeholder="' + escapeAttr(filename) + '">',
            '<span class="cml-file-name">' + escapeHtml(filename) + '</span>',
            '<button type="button" class="button cml-change-file">' + escapeHtml(label('change', 'Aendern')) + '</button>',
            '<button type="button" class="button-link-delete cml-remove-file">' + escapeHtml(label('remove', 'Entfernen')) + '</button>',
            '</div>'
        ].join('');
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (match) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[match];
        });
    }

    function escapeAttr(value) {
        return escapeHtml(value);
    }

    function openFrame($group, onSelect) {
        var libraryType = $group.data('library-type');
        var options = {
            title: label('chooseFile', 'Datei auswaehlen'),
            button: {
                text: label('useFile', 'Datei verwenden')
            },
            multiple: false
        };

        if (libraryType) {
            options.library = {
                type: libraryType
            };
        }

        var frame = wp.media(options);
        frame.on('select', function () {
            onSelect(frame.state().get('selection').first().toJSON());
        });
        frame.open();
    }

    $(document).on('click', '.cml-add-file', function () {
        var $group = $(this).closest('.cml-file-group');
        var group = $group.data('group');

        openFrame($group, function (attachment) {
            $group.find('.cml-file-list').append(createRow(group, attachment));
        });
    });

    $(document).on('click', '.cml-change-file', function () {
        var $row = $(this).closest('.cml-file-row');
        var $group = $(this).closest('.cml-file-group');

        openFrame($group, function (attachment) {
            $row.find('.cml-file-id').val(attachment.id);
            $row.find('.cml-file-title').val('').attr('placeholder', attachment.filename || attachment.title || '');
            $row.find('.cml-file-name').text(attachment.filename || attachment.title || '');
        });
    });

    $(document).on('click', '.cml-remove-file', function () {
        $(this).closest('.cml-file-row').remove();
    });
})(jQuery);
