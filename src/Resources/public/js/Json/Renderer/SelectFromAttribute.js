define(
    [
        'flagbit/JsonGenerator/Observer',
        'oro/translator',
        'jquery',
        'underscore',
        'routing',
        'pim/user-context'
    ],
    function (JsonGeneratorObserver, __, jQuery, _, Routing, UserContext) {

        /**
         * Configures a column that offers the options of a simple select attribute of the PIM.
         *
         * @class
         * @param {Boolean} $editable
         * @param {HTMLElement} $container
         */
        var JsonGeneratorRendererSelectFromAttribute = function ($editable, $container) {

            /**
             * @protected
             * @type      {Number}
             */
            var $resultsPerPage = 20;

            /**
             * Code of the simple select attribute, kept here because select2 only knows it once the
             * attributes are loaded.
             *
             * @protected
             * @type      {String}
             */
            var $attribute = '';

            /**
             * @public
             * @type   {JsonGeneratorObserver}
             */
            this.observer = new JsonGeneratorObserver();

            /**
             * @public
             * @param  {Object} $data
             */
            this.render = function ($data) {

                $attribute = $data['attribute'] ? $data['attribute'] : '';

                var $label = document.createElement('label');
                $label.innerText = __('flagbit.table_attribute.simpleselect_from_attribute.attribute.label');
                $label.className = 'select-options-config-label';
                $container.appendChild($label);

                if (!$editable) {
                    var $code = document.createElement('div');
                    $code.innerText = $attribute;
                    $container.appendChild($code);

                    return;
                }

                var $input = document.createElement('input');
                $input.type = 'hidden';
                $input.name = 'attribute';
                $input.value = $attribute;
                $container.appendChild($input);

                var $select2 = jQuery($input).select2(
                    {
                        placeholder: ' ',
                        dropdownAutoWidth: true,
                        ajax: {
                            url: Routing.generate('pim_enrich_attribute_rest_index'),
                            dataType: 'json',
                            quietMillis: 250,
                            cache: true,
                            data: function (term, page) {
                                return {
                                    search: term,
                                    types: 'pim_catalog_simpleselect',
                                    options: {
                                        limit: $resultsPerPage,
                                        page: page,
                                        locale: UserContext.get('catalogLocale')
                                    }
                                };
                            },
                            results: function (attributes) {
                                return {
                                    results: _.map(attributes, toSelect2Item),
                                    more: attributes.length === $resultsPerPage
                                };
                            }
                        },
                        initSelection: function (element, callback) {
                            jQuery.getJSON(
                                Routing.generate('pim_enrich_attribute_rest_index'),
                                {identifiers: $attribute}
                            ).then(
                                function (attributes) {
                                    var attribute = _.findWhere(attributes, {code: $attribute});

                                    callback(attribute ? toSelect2Item(attribute) : {id: $attribute, text: $attribute});
                                }
                            );
                        }
                    }
                );

                $select2.on(
                    'change', function () {
                        $attribute = $select2.val();
                        notify();
                    }
                );
            };


            /**
             * @public
             * @returns {Object}
             */
            this.read = function () {

                return {attribute: $attribute};
            };


            /**
             * @protected
             * @param     {Object} $item normalized attribute
             * @returns   {Object}
             */
            var toSelect2Item = function ($item) {

                var $label = $item.labels ? $item.labels[UserContext.get('catalogLocale')] : '';

                return {id: $item.code, text: $label ? $label + ' (' + $item.code + ')' : $item.code};
            };


            /**
             * @protected
             */
            var notify = function () {

                this.observer.notify('update');
            }.bind(this);
        };

        return JsonGeneratorRendererSelectFromAttribute;
    }
);
