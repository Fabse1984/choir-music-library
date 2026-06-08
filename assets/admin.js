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

    function selectedPieceHtml(id, title) {
        return [
            '<li class="cml-collection-piece-item" data-piece-id="' + escapeAttr(id) + '">',
            '<span class="dashicons dashicons-menu" aria-hidden="true"></span>',
            '<strong>' + escapeHtml(title) + '</strong>',
            '<span class="cml-collection-piece-id">ID ' + escapeHtml(id) + '</span>',
            '<input type="hidden" name="cml_collection[piece_ids][]" value="' + escapeAttr(id) + '">',
            '<button type="button" class="button-link-delete cml-collection-remove-piece">' + escapeHtml(label('remove', 'Entfernen')) + '</button>',
            '</li>'
        ].join('');
    }

    function availablePieceHtml(id, title) {
        return [
            '<li class="cml-collection-piece-item" data-piece-id="' + escapeAttr(id) + '">',
            '<span class="dashicons dashicons-menu" aria-hidden="true"></span>',
            '<strong>' + escapeHtml(title) + '</strong>',
            '<span class="cml-collection-piece-id">ID ' + escapeHtml(id) + '</span>',
            '<button type="button" class="button cml-collection-add-piece">' + escapeHtml(label('add', 'Hinzufuegen')) + '</button>',
            '</li>'
        ].join('');
    }

    function normalizeCollectionBuilder($builder) {
        $builder.find('[data-cml-selected-pieces] .cml-collection-piece-item').each(function () {
            var $item = $(this);
            var id = $item.data('piece-id');
            if (!$item.find('input[name="cml_collection[piece_ids][]"]').length) {
                $item.append('<input type="hidden" name="cml_collection[piece_ids][]" value="' + escapeAttr(id) + '">');
            }
            $item.find('.cml-collection-add-piece').remove();
            if (!$item.find('.cml-collection-remove-piece').length) {
                $item.append('<button type="button" class="button-link-delete cml-collection-remove-piece">' + escapeHtml(label('remove', 'Entfernen')) + '</button>');
            }
        });

        $builder.find('.cml-collection-available .cml-collection-piece-item').each(function () {
            var $item = $(this);
            $item.find('input[name="cml_collection[piece_ids][]"]').remove();
            $item.find('.cml-collection-remove-piece').remove();
            if (!$item.find('.cml-collection-add-piece').length) {
                $item.append('<button type="button" class="button cml-collection-add-piece">' + escapeHtml(label('add', 'Hinzufuegen')) + '</button>');
            }
        });
    }

    $('[data-cml-collection-builder]').each(function () {
        var $builder = $(this);
        $builder.find('.cml-collection-piece-list').sortable({
            connectWith: '.cml-collection-piece-list',
            handle: '.dashicons-menu',
            items: '.cml-collection-piece-item',
            update: function () {
                normalizeCollectionBuilder($builder);
            },
            receive: function () {
                normalizeCollectionBuilder($builder);
            }
        });
    });

    $(document).on('click', '.cml-collection-add-piece', function () {
        var $item = $(this).closest('.cml-collection-piece-item');
        var id = $item.data('piece-id');
        var title = $item.find('strong').text();
        var $builder = $item.closest('[data-cml-collection-builder]');
        $builder.find('[data-cml-selected-pieces]').append(selectedPieceHtml(id, title));
        $item.remove();
        normalizeCollectionBuilder($builder);
    });

    $(document).on('click', '.cml-collection-remove-piece', function () {
        var $item = $(this).closest('.cml-collection-piece-item');
        var id = $item.data('piece-id');
        var title = $item.find('strong').text();
        var $builder = $item.closest('[data-cml-collection-builder]');
        $builder.find('.cml-collection-available .cml-collection-piece-list').append(availablePieceHtml(id, title));
        $item.remove();
        normalizeCollectionBuilder($builder);
    });
})(jQuery);
