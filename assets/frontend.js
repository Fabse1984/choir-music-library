(function () {
    function normalize(value) {
        return String(value || '').toLowerCase().trim();
    }

    function initOverview(root) {
        var collection = root.querySelector('[data-cml-collection]');
        var items = Array.prototype.slice.call(root.querySelectorAll('.cml-overview-item'));
        var overlay = root.querySelector('[data-cml-search-overlay]');
        var filters = Array.prototype.slice.call(root.querySelectorAll('[data-cml-filter]'));
        var resetButtons = Array.prototype.slice.call(root.querySelectorAll('[data-cml-search-reset]'));
        var tagFilterInput = root.querySelector('[data-cml-filter="tags"]');

        root.querySelectorAll('[data-cml-view]').forEach(function (button) {
            button.addEventListener('click', function () {
                root.querySelectorAll('[data-cml-view]').forEach(function (item) {
                    item.classList.remove('is-active');
                });
                button.classList.add('is-active');
                collection.classList.toggle('is-list', button.dataset.cmlView === 'list');
                collection.classList.toggle('is-grid', button.dataset.cmlView !== 'list');
            });
        });

        function applyFilters() {
            var active = {};
            var hasActiveFilter = false;

            filters.forEach(function (input) {
                active[input.dataset.cmlFilter] = normalize(input.value);
                if (active[input.dataset.cmlFilter]) {
                    hasActiveFilter = true;
                }
            });

            items.forEach(function (item) {
                var visible = ['title', 'composer', 'tags'].every(function (key) {
                    return !active[key] || normalize(item.dataset[key]).indexOf(active[key]) !== -1;
                });
                item.hidden = !visible;
            });

            resetButtons.forEach(function (button) {
                button.hidden = !hasActiveFilter;
            });
        }

        filters.forEach(function (input) {
            input.addEventListener('input', applyFilters);
        });

        root.querySelectorAll('[data-cml-tag]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!tagFilterInput) {
                    return;
                }

                tagFilterInput.value = button.dataset.cmlTag || '';
                applyFilters();
            });
        });

        root.querySelectorAll('[data-cml-search-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                overlay.hidden = false;
                var firstInput = overlay.querySelector('input');
                if (firstInput) {
                    firstInput.focus();
                }
            });
        });

        root.querySelectorAll('[data-cml-search-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                overlay.hidden = true;
            });
        });

        resetButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                filters.forEach(function (input) {
                    input.value = '';
                });
                applyFilters();
            });
        });
    }

    function initSubmissionForm(form) {
        var existingSelectWrap = form.querySelector('[data-cml-existing-piece-select]');
        var existingSelect = form.querySelector('[name="cml_submission_target"]');
        var dataNode = form.querySelector('[data-cml-existing-pieces]');
        var pieces = {};

        try {
            pieces = dataNode ? JSON.parse(dataNode.textContent || '{}') : {};
        } catch (error) {
            pieces = {};
        }

        var fields = {
            title: form.querySelector('[name="cml_submission_title"]'),
            composer: form.querySelector('[name="cml[composer]"]'),
            lyricist: form.querySelector('[name="cml[lyricist]"]'),
            arranger: form.querySelector('[name="cml[arranger]"]'),
            voicing: form.querySelector('[name="cml[voicing]"]'),
            extra_info: form.querySelector('[name="cml[extra_info]"]'),
            singing_info: form.querySelector('[name="cml[singing_info]"]')
        };

        function setFields(piece) {
            Object.keys(fields).forEach(function (key) {
                if (!fields[key]) {
                    return;
                }

                fields[key].value = piece && piece[key] ? piece[key] : '';
            });
        }

        function isExistingMode() {
            var selected = form.querySelector('[name="cml_submission_type"]:checked');
            return selected && selected.value === 'existing_piece';
        }

        function updateMode() {
            var existing = isExistingMode();
            if (existingSelectWrap) {
                existingSelectWrap.hidden = !existing;
            }

            if (!existing) {
                if (existingSelect) {
                    existingSelect.value = '0';
                }
                setFields(null);
                return;
            }

            if (existingSelect && existingSelect.value !== '0') {
                setFields(pieces[existingSelect.value] || null);
            }
        }

        form.querySelectorAll('[name="cml_submission_type"]').forEach(function (input) {
            input.addEventListener('change', updateMode);
        });

        if (existingSelect) {
            existingSelect.addEventListener('change', function () {
                setFields(isExistingMode() ? pieces[existingSelect.value] || null : null);
            });
        }

        updateMode();
    }

    function initPdfPreview() {
        var overlay = document.createElement('div');
        overlay.className = 'cml-pdf-overlay';
        overlay.hidden = true;
        overlay.innerHTML = [
            '<div class="cml-pdf-dialog" role="dialog" aria-modal="true">',
            '<div class="cml-pdf-toolbar">',
            '<strong data-cml-pdf-title></strong>',
            '<button type="button" data-cml-pdf-close aria-label="Schliessen">&times;</button>',
            '</div>',
            '<iframe title="PDF Vorschau" data-cml-pdf-frame></iframe>',
            '</div>'
        ].join('');
        document.body.appendChild(overlay);

        var frame = overlay.querySelector('[data-cml-pdf-frame]');
        var title = overlay.querySelector('[data-cml-pdf-title]');

        function close() {
            overlay.hidden = true;
            frame.src = 'about:blank';
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-cml-pdf-preview]');
            if (!trigger) {
                return;
            }

            title.textContent = trigger.dataset.cmlPdfTitle || '';
            frame.src = trigger.dataset.cmlPdfPreview || 'about:blank';
            overlay.hidden = false;
        });

        overlay.querySelector('[data-cml-pdf-close]').addEventListener('click', close);
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                close();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !overlay.hidden) {
                close();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-cml-overview]').forEach(initOverview);
        document.querySelectorAll('[data-cml-submission-form]').forEach(initSubmissionForm);
        if (document.querySelector('[data-cml-pdf-preview]')) {
            initPdfPreview();
        }
    });
})();
