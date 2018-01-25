define(function(require) {
    'use strict';

    var ExpressionEditorView;
    var $ = require('jquery');
    var _ = require('underscore');
    require('bootstrap');
    var BaseView = require('oroui/js/app/views/base/view');
    var ExpressionEditorUtil = require('oroform/js/expression-editor-util');
    var Typeahead = $.fn.typeahead.Constructor;

    ExpressionEditorView = BaseView.extend({
        optionNames: BaseView.prototype.optionNames.concat([
            'dataSource', 'delay'
        ]),

        /**
         * {Object} ExpressionEditorUtil
         */
        util: null,

        /**
         * {Object} Typeahead
         */
        typeahead: null,

        /**
         * {Object} Autocomplete data provided by ExpressionEditorUtil.getAutocompleteData
         */
        autocompleteData: null,

        /**
         * {Object} List of data source widgets
         */
        dataSource: null,

        /**
         * {Object} List of data source widget instances
         */
        dataSourceInstances: null,

        /**
         * {Integer} Validation and autocomplete delay in milliseconds
         */
        delay: 50,

        events: {
            'focus': 'debouncedAutocomplete',
            'click': 'debouncedAutocomplete',
            'input': 'debouncedAutocomplete',
            'keyup': 'debouncedValidate',
            'change': 'debouncedValidate',
            'blur': 'debouncedValidate',
            'paste': 'debouncedValidate'
        },

        constructor: function(options) {
            this.debouncedAutocomplete = _.debounce(function(e) {
                if (!this.disposed) {
                    this.autocomplete(e);
                }
            }.bind(this), this.delay);
            this.debouncedValidate = _.debounce(function(e) {
                if (!this.disposed) {
                    this.validate(e);
                }
            }.bind(this), this.delay);
            ExpressionEditorView.__super__.constructor.call(this, options);
        },

        /**
         * @inheritDoc
         */
        initialize: function(options) {
            var utilOptions = _.pick(options,
                'itemLevelLimit', 'allowedOperations', 'operations', 'rootEntities', 'entityDataProvider');
            utilOptions.dataSourceNames = _.keys(options.dataSource);
            this.util = new ExpressionEditorUtil(utilOptions);

            this.autocompleteData = this.autocompleteData || {};
            this.dataSource = this.dataSource || {};
            this.dataSourceInstances = this.dataSourceInstances || {};

            return ExpressionEditorView.__super__.initialize.apply(this, arguments);
        },

        render: function() {
            this.$el.typeahead({
                minLength: 0,
                items: 20,
                select: _.bind(this._typeaheadSelect, this),
                source: _.bind(this._typeaheadSource, this),
                lookup: _.bind(this._typeaheadLookup, this),
                highlighter: _.bind(this._typeaheadHighlighter, this),
                updater: _.bind(this._typeaheadUpdater, this)
            });

            this.typeahead = this.$el.data('typeahead');
            this.typeahead.$menu.addClass('expression-editor-autocomplete');
        },

        /**
         * @inheritDoc
         */
        dispose: function() {
            if (this.disposed) {
                return;
            }

            _.each(this.dataSourceInstances, function(dataSource) {
                dataSource.$widget.remove();
            });

            delete this.util;
            delete this.typeahead;
            delete this.autocompleteData;
            delete this.dataSource;
            delete this.dataSourceInstances;

            return ExpressionEditorView.__super__.dispose.apply(this, arguments);
        },

        /**
         * Show autocomplete list
         */
        autocomplete: function() {
            this.typeahead.lookup();
        },

        /**
         * Validate expression
         */
        validate: function() {
            var isValid = this.util.validate(this.$el.val());
            this.$el.toggleClass('error', !isValid);
            this.$el.parent().toggleClass('validation-error', !isValid);
        },

        /**
         * Override Typeahead.source function
         *
         * @return {Array}
         * @private
         */
        _typeaheadSource: function() {
            var expression = this.el.value;
            var position = this.el.selectionStart;

            this.autocompleteData = this.util.getAutocompleteData(expression, position);
            this._toggleDataSource();
            this.typeahead.query = this.autocompleteData.itemLastChild;

            return _.sortBy(_.keys(this.autocompleteData.items));
        },

        /**
         * Override Typeahead.lookup function
         *
         * @return {Typeahead}
         * @private
         */
        _typeaheadLookup: function() {
            return this.typeahead.process(this.typeahead.source());
        },

        /**
         * Override Typeahead.select function
         *
         * @return {Typeahead}
         * @private
         */
        _typeaheadSelect: function() {
            var original = Typeahead.prototype.select;
            var result = original.call(this.typeahead);
            this.typeahead.lookup();
            return result;
        },

        /**
         * Override Typeahead.highlighter function
         *
         * @param {String} item
         * @return {String}
         * @private
         */
        _typeaheadHighlighter: function(item) {
            var original = Typeahead.prototype.highlighter;
            var suffix = this.autocompleteData.items[item].hasChildren ? '&hellip;' : '';
            return original.call(this.typeahead, item) + suffix;
        },

        /**
         * Override Typeahead.updater function
         *
         * @param {String} item
         * @return {String}
         * @private
         */
        _typeaheadUpdater: function(item) {
            this.util.updateAutocompleteItem(this.autocompleteData, item);
            var position = this.autocompleteData.position;
            this.$el.one('change', function() {
                // set correct position after typeahead call change event
                this.selectionStart = this.selectionEnd = position;
            });

            return this.autocompleteData.expression;
        },

        /**
         * Return data source instance by key
         *
         * @param {String} dataSourceKey
         * @return {Object}
         */
        getDataSource: function(dataSourceKey) {
            return this.dataSourceInstances[dataSourceKey] || this._initializeDataSource(dataSourceKey);
        },

        /**
         * Create data source instance
         *
         * @param {String} dataSourceKey
         * @return {Object}
         * @private
         */
        _initializeDataSource: function(dataSourceKey) {
            var dataSource = this.dataSourceInstances[dataSourceKey] = {};

            dataSource.$widget = $('<div>').addClass('expression-editor-data-source')
                .html(this.dataSource[dataSourceKey]);
            dataSource.$field = dataSource.$widget.find(':input[name]:first');
            dataSource.active = false;

            this._hideDataSource(dataSource);

            this.$el.after(dataSource.$widget).trigger('content:changed');

            dataSource.$field.on('change', _.bind(function(e) {
                if (!dataSource.active) {
                    return;
                }

                this.util.updateDataSourceValue(this.autocompleteData, $(e.currentTarget).val());
                this.$el.val(this.autocompleteData.expression)
                    .change().focus();

                this.el.selectionStart = this.el.selectionEnd = this.autocompleteData.position;
            }, this));

            return dataSource;
        },

        /**
         * Hide all data sources and show active
         *
         * @private
         */
        _toggleDataSource: function() {
            this._hideDataSources();

            var dataSourceKey = this.autocompleteData.dataSourceKey;
            var dataSourceValue = this.autocompleteData.dataSourceValue;

            if (_.isEmpty(dataSourceKey) || !_.has(this.dataSource, dataSourceKey)) {
                return;
            }

            this.autocompleteData.items = {};// hide autocomplete list

            var dataSource = this.getDataSource(dataSourceKey);
            dataSource.$field.val(dataSourceValue).change();

            this._showDataSource(dataSource);
        },

        /**
         * Hide all data sources
         *
         * @private
         */
        _hideDataSources: function() {
            _.each(this.dataSourceInstances, this._hideDataSource, this);
        },

        /**
         * Hide data source
         *
         * @param {Object} dataSource
         * @private
         */
        _hideDataSource: function(dataSource) {
            dataSource.active = false;
            dataSource.$widget.hide().removeClass('active');
        },

        /**
         * Show data source
         *
         * @param {Object} dataSource
         * @private
         */
        _showDataSource: function(dataSource) {
            dataSource.$widget.show().addClass('active');
            dataSource.active = true;
        }
    });

    return ExpressionEditorView;
});
