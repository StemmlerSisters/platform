define(function(require) {
    'use strict';

    var FieldIdTranslator =
        require('oroquerydesigner/js/query-type-converter/expression-to-condition/field-id-translator');
    var ExpressionLanguageLibrary = require('oroexpressionlanguage/js/expression-language-library');
    var ArgumentsNode = ExpressionLanguageLibrary.ArgumentsNode;
    var ConstantNode = ExpressionLanguageLibrary.ConstantNode;
    var FunctionNode = ExpressionLanguageLibrary.FunctionNode;
    var GetAttrNode = ExpressionLanguageLibrary.GetAttrNode;
    var NameNode = ExpressionLanguageLibrary.NameNode;
    var Node = ExpressionLanguageLibrary.Node;

    describe('oroquerydesigner/js/query-type-converter/expression-to-condition/field-id-translator', function() {
        var providerMock;
        var translator;
        var fooGetBarAST;

        beforeEach(function() {
            providerMock = {
                getPathByRelativePropertyPath: jasmine.createSpy('getPathByRelativePropertyPath').and
                    .callFake(function(relativePropertyPath) {
                        return {
                            'bar': 'bar',
                            'bar.qux': 'bar+Oro\\QuxClassName::qux',
                            'bar.qux.baz': 'bar+Oro\\QuxClassName::qux+Oro\\BazClassName::baz'
                        }[relativePropertyPath];
                    }),
                rootEntity: {
                    get: jasmine.createSpy('get').and
                        .callFake(function(attr) {
                            return {
                                alias: 'foo'
                            }[attr];
                        })
                }
            };

            translator = new FieldIdTranslator(providerMock);

            fooGetBarAST = new GetAttrNode(
                new NameNode('foo'),
                new ConstantNode('bar'),
                new ArgumentsNode(),
                GetAttrNode.PROPERTY_CALL
            );
        });

        it('translate foo.bar AST to fieldId', function() {
            expect(translator.translate(fooGetBarAST)).toEqual('bar');
        });

        it('translate foo.bar.qux AST to fieldId', function() {
            var AST = new GetAttrNode(
                fooGetBarAST,
                new ConstantNode('qux'),
                new ArgumentsNode(),
                GetAttrNode.PROPERTY_CALL
            );
            expect(translator.translate(AST)).toEqual('bar+Oro\\QuxClassName::qux');
        });

        it('translate foo.bar.qux.baz AST to fieldId', function() {
            var AST = new GetAttrNode(
                new GetAttrNode(
                    fooGetBarAST,
                    new ConstantNode('qux'),
                    new ArgumentsNode(),
                    GetAttrNode.PROPERTY_CALL
                ),
                new ConstantNode('baz'),
                new ArgumentsNode(),
                GetAttrNode.PROPERTY_CALL
            );
            expect(translator.translate(AST)).toEqual('bar+Oro\\QuxClassName::qux+Oro\\BazClassName::baz');
        });

        it('attempt to translate foo().bar, invalid AST', function() {
            var AST = new GetAttrNode(
                new FunctionNode('foo', new Node()),
                new ConstantNode('bar'),
                new ArgumentsNode(),
                GetAttrNode.PROPERTY_CALL
            );
            expect(function() {
                translator.translate(AST);
            }).toThrowError(Error);
        });

        it('attempt to translate foo.bar(), invalid AST', function() {
            var AST = new GetAttrNode(
                new NameNode('quux'),
                new ConstantNode('bar'),
                new ArgumentsNode(),
                GetAttrNode.METHOD_CALL
            );
            expect(function() {
                translator.translate(AST);
            }).toThrowError(Error);
        });

        it('attempt to translate foo[\'bar\'].qux, invalid AST', function() {
            var AST = new GetAttrNode(
                new GetAttrNode(
                    new NameNode('foo'),
                    new ConstantNode('bar'),
                    new ArgumentsNode(),
                    GetAttrNode.ARRAY_CALL
                ),
                new ConstantNode('qux'),
                new ArgumentsNode(),
                GetAttrNode.PROPERTY_CALL
            );
            expect(function() {
                translator.translate(AST);
            }).toThrowError(Error);
        });

        it('attempt to translate quux.bar, unknown variable name', function() {
            var AST = new GetAttrNode(
                new NameNode('quux'),
                new ConstantNode('bar'),
                new ArgumentsNode(),
                GetAttrNode.PROPERTY_CALL
            );
            expect(function() {
                translator.translate(AST);
            }).toThrowError(Error);
        });

        it('entityStructureDataProvider is required', function() {
            expect(function() {
                new FieldIdTranslator();
            }).toThrowError(TypeError);
        });

        it('rootEntity has to be defined in entityStructureDataProvider', function() {
            providerMock.rootEntity = null;
            expect(function() {
                translator.translate(fooGetBarAST);
            }).toThrowError(Error);
        });

        it('rootEntity of entityStructureDataProvider has to have alias', function() {
            providerMock.rootEntity = {
                get: jasmine.createSpy('get').and.callFake(function(attr) {
                    return {
                        alias: ''
                    }[attr];
                })
            };
            expect(function() {
                translator.translate(fooGetBarAST);
            }).toThrowError(Error);
        });
    });
});
