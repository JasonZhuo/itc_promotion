define([
    'jquery',
    'ko',
    'uiRegistry',
    'Magento_Ui/js/grid/columns/column',
    'mage/translate'
], function ($, ko, registry, Column, $t) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'Itc_Promotion/grid/cells/position',
            updateUrl: ''
        },

        /**
         * @returns {Column}
         */
        initObservable: function () {
            this._super();
            this.editSku = ko.observable(null);
            this.draft = ko.observable('');
            this.updating = ko.observable(false);
            this.error = ko.observable('');
            this.editingRow = null;

            return this;
        },

        /**
         * @param {Object} row
         * @returns {Boolean}
         */
        isEditing: function (row) {
            return this.editSku() === row.sku;
        },

        /**
         * @param {Object} row
         * @returns {void}
         */
        startEdit: function (row) {
            this.error('');
            this.editingRow = row;
            this.draft(String(row.position));
            this.editSku(row.sku);
        },

        /**
         * Reads the number currently typed in the open editor.
         *
         * @param {Event} event
         * @returns {String}
         */
        readPosition: function (event) {
            var input = event && event.target ?
                $(event.target).closest('.itc-promotion-position').find('.itc-promotion-position__input').get(0) :
                null;

            if (input) {
                return String(input.value).trim();
            }

            return String(this.draft()).trim();
        },

        /**
         * Saves the typed position for the row that is open.
         *
         * @param {Object} row
         * @param {Event} event
         * @returns {void}
         */
        update: function (row, event) {
            var position = this.readPosition(event),
                self = this;

            if (!/^\d+$/.test(position)) {
                this.error($t('Enter a whole-number position.'));

                return;
            }

            this.draft(position);
            this.updating(true);
            this.error('');
            $.ajax({
                url: this.updateUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    sku: row.sku,
                    position: position,
                    form_key: window.FORM_KEY
                }
            }).done(function (response) {
                if (!response || !response.success) {
                    self.error(response && response.message ? response.message : $t('Could not update the position.'));

                    return;
                }
                self.editSku(null);
                self.editingRow = null;
                self.applySavedPositions(response.items || []);
                self.reloadGrid();
            }).fail(function () {
                self.error($t('Could not update the position.'));
            }).always(function () {
                self.updating(false);
            });
        },

        /**
         * Writes the saved order into the grid rows already on the page.
         *
         * @param {Array} items
         * @returns {void}
         */
        applySavedPositions: function (items) {
            var provider = registry.get(this.provider),
                positions = {},
                current,
                next;

            if (!provider || !items.length) {
                return;
            }

            items.forEach(function (item) {
                positions[item.sku] = item.position;
            });
            current = provider.get('data.items') || [];
            next = current.map(function (row) {
                var updated = $.extend({}, row);

                if (Object.prototype.hasOwnProperty.call(positions, updated.sku)) {
                    updated.position = positions[updated.sku];
                }

                return updated;
            });
            next.sort(function (left, right) {
                return Number(left.position) - Number(right.position);
            });
            provider.set('data.items', next);
        },

        /**
         * Reloads the listing without reusing the previous grid response.
         *
         * @returns {void}
         */
        reloadGrid: function () {
            var provider = registry.get(this.provider),
                storage;

            if (!provider || typeof provider.reload !== 'function') {
                return;
            }

            storage = typeof provider.storage === 'function' ? provider.storage() : null;

            if (storage) {
                storage.clearRequests();
                storage.requestConfig.cache = false;
            }

            provider.reload({refresh: true});
        },

        /**
         * @param {Object} row
         * @param {Event} event
         * @returns {Boolean}
         */
        onKey: function (row, event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                this.update(row, event);
            }

            return true;
        }
    });
});
