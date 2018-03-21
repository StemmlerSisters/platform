define(function(require) {
    'use strict';

    var ExpressionLanguageLibrary = require('oroexpressionlanguage/js/expression-language-library');
    var GetAttrNode = ExpressionLanguageLibrary.GetAttrNode;
    var NameNode = ExpressionLanguageLibrary.NameNode;

    /**
     * Translates AST of Expression Language (sequence of GetAttrNode's) to fieldId string
     * (see definition in EntityStructureDataProvider)
     *
     * @param {EntityStructureDataProvider} entityStructureDataProvider
     * @constructor
     * @throws TypeError if instance of EntityStructureDataProvider is missing
     */
    function FieldIdTranslator(entityStructureDataProvider) {
        if (!entityStructureDataProvider) {
            throw new TypeError('Instance of `EntityStructureDataProvider` is required for `FieldIdTranslator`');
        }
        this.provider = entityStructureDataProvider;
    }

    Object.assign(FieldIdTranslator.prototype, {
        constructor: FieldIdTranslator,

        /**
         * Translates AST of Expression Language (sequence of GetAttrNode's) to fieldId string
         * (see definition in EntityStructureDataProvider)
         *
         * @param {GetAttrNode} node
         * @return {fieldId} fieldId (see type definition in EntityStructureDataProvider)
         * @throws Error if rootEntity is not defined or it does not have alias
         * @throws Error if AST can to be converted to fieldId
         * @throws Error if variable name does not match root entity alias
         */
        translate: function(node) {
            var name;
            var props = [];
            var entityAlias = this.provider.rootEntity && this.provider.rootEntity.get('alias');

            if (!entityAlias) {
                throw new Error('Alias of root entity is required for `FieldIdTranslator`');
            }

            while (node instanceof GetAttrNode) {
                switch (node.attrs.type) {
                    case GetAttrNode.PROPERTY_CALL:
                        props.unshift(node.nodes[1].attrs.value);
                        node = node.nodes[0];
                        break;
                    default:
                        throw new Error('Provided AST can not be converted to fieldId');
                }
            }

            if (node instanceof NameNode) {
                name = node.attrs.name;
            } else {
                throw new Error('Provided AST can not be converted to fieldId');
            }

            if (name !== entityAlias) {
                throw new Error('Name `' + name + '` does not match root entity alias `' + entityAlias + '`');
            }

            return this.provider.getPathByRelativePropertyPath(props.join('.'));
        }
    });

    return FieldIdTranslator;
});
