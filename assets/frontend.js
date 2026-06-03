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

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-cml-overview]').forEach(initOverview);
    });
})();
