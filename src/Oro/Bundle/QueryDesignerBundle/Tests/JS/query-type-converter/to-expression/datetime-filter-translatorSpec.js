define(function(require) {
    'use strict';

    var _ = require('underscore');
    var DatetimeFilterTranslator =
        require('oroquerydesigner/js/query-type-converter/to-expression/datetime-filter-translator');
    var FieldIdTranslator = require('oroquerydesigner/js/query-type-converter/to-expression/field-id-translator');
    var ExpressionLanguageLibrary = require('oroexpressionlanguage/js/expression-language-library');
    var BinaryNode = ExpressionLanguageLibrary.BinaryNode;
    var ConstantNode = ExpressionLanguageLibrary.ConstantNode;
    var FunctionNode = ExpressionLanguageLibrary.FunctionNode;
    var Node = ExpressionLanguageLibrary.Node;

    describe('oroquerydesigner/js/query-type-converter/to-expression/datetime-filter-translator', function() {
        var translator;
        var filterConfigProviderMock;
        var createGetFieldAST = ExpressionLanguageLibrary.tools.createGetAttrNode.bind(null, 'foo.bar'.split('.'));
        var createFuncCallAST = function(funcName) {
            return new FunctionNode(funcName, new Node(_.rest(arguments)));
        };

        beforeEach(function() {
            var entityStructureDataProviderMock = jasmine.combineSpyObj('entityStructureDataProvider', [
                jasmine.createSpy('getRelativePropertyPathByPath').and.returnValue('bar'),
                jasmine.combineSpyObj('rootEntity', [
                    jasmine.createSpy('get').and.returnValue('foo')
                ])
            ]);

            filterConfigProviderMock = jasmine.combineSpyObj('filterConfigProvider', [
                jasmine.createSpy('getFilterConfigsByType').and.returnValue([
                    {
                        type: 'datetime',
                        name: 'datetime',
                        choices: [
                            {value: '1'},
                            {value: '2'},
                            {value: '3'},
                            {value: '4'},
                            {value: '5'},
                            {value: '6'}
                        ],
                        dateParts: {
                            value: 'value',
                            dayofweek: 'day of week',
                            week: 'week',
                            day: 'day of month',
                            month: 'month',
                            quarter: 'quarter',
                            dayofyear: 'day of year',
                            year: 'year'
                        },
                        externalWidgetOptions: {
                            dateVars: {
                                value: {
                                    1: 'now',
                                    2: 'today',
                                    3: 'start of the week',
                                    4: 'start of the month',
                                    5: 'start of the quarter',
                                    6: 'start of the year',
                                    17: 'current month without year',
                                    29: 'this day without year'
                                },
                                dayofweek: {
                                    10: 'current day'
                                },
                                week: {
                                    11: 'current week'
                                },
                                day: {
                                    10: 'current day'
                                },
                                month: {
                                    12: 'current month',
                                    16: 'first month of quarter'
                                },
                                quarter: {
                                    13: 'current quarter'
                                },
                                dayofyear: {
                                    10: 'current day',
                                    15: 'first day of quarter'
                                },
                                year: {
                                    14: 'current year'
                                }
                            }
                        }
                    }
                ])
            ]);

            translator = new DatetimeFilterTranslator(
                new FieldIdTranslator(entityStructureDataProviderMock),
                filterConfigProviderMock
            );
        });

        it('calls filter provider\'s method `getFilterConfigsByType` with correct filter type', function() {
            translator.tryToTranslate({
                columnName: 'bar',
                criterion: {
                    filter: 'datetime',
                    data: {
                        type: '3',
                        value: {start: '2018-03-28 13:45', end: ''},
                        part: 'value'
                    }
                }
            });
            expect(filterConfigProviderMock.getFilterConfigsByType).toHaveBeenCalledWith('datetime');
        });

        describe('translates valid condition', function() {
            var cases = {
                // filter type
                'value part between start and end datetimes': [
                    // condition filter data
                    {
                        type: '1',
                        value: {start: '2018-03-01 00:00', end: '2018-03-31 00:00'},
                        part: 'value'
                    },
                    // expected AST
                    new BinaryNode(
                        'and',
                        new BinaryNode('>=', createGetFieldAST(), new ConstantNode('2018-03-01 00:00')),
                        new BinaryNode('<=', createGetFieldAST(), new ConstantNode('2018-03-31 00:00'))
                    )
                ],
                'value part between with empty start datetime and valuable end datetime': [
                    {
                        type: '1',
                        value: {start: '', end: '2018-03-31 00:00'},
                        part: 'value'
                    },
                    new BinaryNode('<=', createGetFieldAST(), new ConstantNode('2018-03-31 00:00'))
                ],
                'value part between with valuable start datetime and empty end datetime': [
                    {
                        type: '1',
                        value: {start: '2018-03-01 00:00', end: ''},
                        part: 'value'
                    },
                    new BinaryNode('>=', createGetFieldAST(), new ConstantNode('2018-03-01 00:00'))
                ],
                'value part not between start and end datetimes': [
                    {
                        type: '2',
                        value: {start: '2018-03-01 00:00', end: '2018-03-31 00:00'},
                        part: 'value'
                    },
                    new BinaryNode(
                        'and',
                        new BinaryNode('<', createGetFieldAST(), new ConstantNode('2018-03-01 00:00')),
                        new BinaryNode('>', createGetFieldAST(), new ConstantNode('2018-03-31 00:00'))
                    )
                ],
                'value part not between empty start datetime and valuable end datetime': [
                    {
                        type: '2',
                        value: {start: '', end: '2018-03-31 00:00'},
                        part: 'value'
                    },
                    new BinaryNode('>=', createGetFieldAST(), new ConstantNode('2018-03-31 00:00'))
                ],
                'value part not between valuable start datetime and empty end datetime': [
                    {
                        type: '2',
                        value: {start: '2018-03-01 00:00', end: ''},
                        part: 'value'
                    },
                    new BinaryNode('<=', createGetFieldAST(), new ConstantNode('2018-03-01 00:00'))
                ],
                'value part later than the datetime': [
                    {
                        type: '3',
                        value: {start: '2018-03-01 00:00', end: ''},
                        part: 'value'
                    },
                    new BinaryNode('>=', createGetFieldAST(), new ConstantNode('2018-03-01 00:00'))
                ],
                'value part earlier than the datetime': [
                    {
                        type: '4',
                        value: {start: '', end: '2018-03-31 00:00'},
                        part: 'value'
                    },
                    new BinaryNode('<=', createGetFieldAST(), new ConstantNode('2018-03-31 00:00'))
                ],
                'value part equals to the datetime': [
                    {
                        type: '5',
                        value: {start: '2018-03-01 00:00', end: ''},
                        part: 'value'
                    },
                    new BinaryNode('=', createGetFieldAST(), new ConstantNode('2018-03-01 00:00'))
                ],
                'value part not equals to the datetime': [
                    {
                        type: '6',
                        value: {start: '', end: '2018-03-31 00:00'},
                        part: 'value'
                    },
                    new BinaryNode('!=', createGetFieldAST(), new ConstantNode('2018-03-31 00:00'))
                ],
                'value part equals to now': [
                    {
                        type: '5',
                        value: {start: '{{1}}', end: ''},
                        part: 'value'
                    },
                    new BinaryNode('=', createGetFieldAST(), createFuncCallAST('now'))
                ],
                'value part equals to today': [
                    {
                        type: '5',
                        value: {start: '{{2}}', end: ''},
                        part: 'value'
                    },
                    new BinaryNode('=', createGetFieldAST(), createFuncCallAST('today'))
                ],
                'value part equals to start of the week': [
                    {
                        type: '5',
                        value: {start: '{{3}}', end: ''},
                        part: 'value'
                    },
                    new BinaryNode('=', createGetFieldAST(), createFuncCallAST('startOfTheWeek'))
                ],
                'value part equals to start of the month': [
                    {
                        type: '5',
                        value: {start: '{{4}}', end: ''},
                        part: 'value'
                    },
                    new BinaryNode('=', createGetFieldAST(), createFuncCallAST('startOfTheMonth'))
                ],
                'value part equals to start of the quarter': [
                    {
                        type: '5',
                        value: {start: '{{5}}', end: ''},
                        part: 'value'
                    },
                    new BinaryNode('=', createGetFieldAST(), createFuncCallAST('startOfTheQuarter'))
                ],
                'value part equals to start of the year': [
                    {
                        type: '5',
                        value: {start: '{{6}}', end: ''},
                        part: 'value'
                    },
                    new BinaryNode('=', createGetFieldAST(), createFuncCallAST('startOfTheYear'))
                ],
                'value part equals to current month without year': [
                    {
                        type: '5',
                        value: {start: '{{17}}', end: ''},
                        part: 'value'
                    },
                    new BinaryNode('=', createGetFieldAST(), createFuncCallAST('currentMonthWithoutYear'))
                ],
                'value part equals to this day without year': [
                    {
                        type: '5',
                        value: {start: '{{29}}', end: ''},
                        part: 'value'
                    },
                    new BinaryNode('=', createGetFieldAST(), createFuncCallAST('thisDayWithoutYear'))
                ],
                'day of week part equals to value': [
                    {
                        type: '5',
                        value: {start: '7', end: ''},
                        part: 'dayofweek'
                    },
                    new BinaryNode('=', createFuncCallAST('dayOfWeek', createGetFieldAST()), new ConstantNode('7'))
                ],
                'day of week part equals to current day of week': [
                    {
                        type: '5',
                        value: {start: '{{10}}', end: ''},
                        part: 'dayofweek'
                    },
                    new BinaryNode('=',
                        createFuncCallAST('dayOfWeek', createGetFieldAST()),
                        createFuncCallAST('currentDayOfWeek')
                    )
                ],
                'week part equals to value': [
                    {
                        type: '5',
                        value: {start: '5', end: ''},
                        part: 'week'
                    },
                    new BinaryNode('=', createFuncCallAST('week', createGetFieldAST()), new ConstantNode('5'))
                ],
                'week part equals to current week': [
                    {
                        type: '5',
                        value: {start: '{{11}}', end: ''},
                        part: 'week'
                    },
                    new BinaryNode('=',
                        createFuncCallAST('week', createGetFieldAST()),
                        createFuncCallAST('currentWeek')
                    )
                ],
                'day of month part equals to value': [
                    {
                        type: '5',
                        value: {start: '1', end: ''},
                        part: 'day'
                    },
                    new BinaryNode('=', createFuncCallAST('dayOfMonth', createGetFieldAST()), new ConstantNode('1'))
                ],
                'day of month part equals to current day of month': [
                    {
                        type: '5',
                        value: {start: '{{10}}', end: ''},
                        part: 'day'
                    },
                    new BinaryNode('=',
                        createFuncCallAST('dayOfMonth', createGetFieldAST()),
                        createFuncCallAST('currentDayOfMonth')
                    )
                ],
                'month part equals to value': [
                    {
                        type: '5',
                        value: {start: '2', end: ''},
                        part: 'month'
                    },
                    new BinaryNode('=', createFuncCallAST('month', createGetFieldAST()), new ConstantNode('2'))
                ],
                'month part equals to current month': [
                    {
                        type: '5',
                        value: {start: '{{12}}', end: ''},
                        part: 'month'
                    },
                    new BinaryNode('=',
                        createFuncCallAST('month', createGetFieldAST()),
                        createFuncCallAST('currentMonth')
                    )
                ],
                'month part equals to first month of current quarter': [
                    {
                        type: '5',
                        value: {start: '{{16}}', end: ''},
                        part: 'month'
                    },
                    new BinaryNode('=',
                        createFuncCallAST('month', createGetFieldAST()),
                        createFuncCallAST('firstMonthOfCurrentQuarter')
                    )
                ],
                'quarter part equals to value': [
                    {
                        type: '5',
                        value: {start: '1', end: ''},
                        part: 'quarter'
                    },
                    new BinaryNode('=', createFuncCallAST('quarter', createGetFieldAST()), new ConstantNode('1'))
                ],
                'quarter part equals to current quarter': [
                    {
                        type: '5',
                        value: {start: '{{13}}', end: ''},
                        part: 'quarter'
                    },
                    new BinaryNode('=',
                        createFuncCallAST('quarter', createGetFieldAST()),
                        createFuncCallAST('currentQuarter')
                    )
                ],
                'day of year part equals to value': [
                    {
                        type: '5',
                        value: {start: '32', end: ''},
                        part: 'dayofyear'
                    },
                    new BinaryNode('=', createFuncCallAST('dayOfYear', createGetFieldAST()), new ConstantNode('32'))
                ],
                'day of year part equals to current day of year': [
                    {
                        type: '5',
                        value: {start: '{{10}}', end: ''},
                        part: 'dayofyear'
                    },
                    new BinaryNode('=',
                        createFuncCallAST('dayOfYear', createGetFieldAST()),
                        createFuncCallAST('currentDayOfYear')
                    )
                ],
                'day of year part equals to first day of current quarter': [
                    {
                        type: '5',
                        value: {start: '{{15}}', end: ''},
                        part: 'dayofyear'
                    },
                    new BinaryNode('=',
                        createFuncCallAST('dayOfYear', createGetFieldAST()),
                        createFuncCallAST('firstDayOfCurrentQuarter')
                    )
                ],
                'year part equals to value': [
                    {
                        type: '5',
                        value: {start: '1981', end: ''},
                        part: 'year'
                    },
                    new BinaryNode('=', createFuncCallAST('year', createGetFieldAST()), new ConstantNode('1981'))
                ],
                'year part equals to current year': [
                    {
                        type: '5',
                        value: {start: '{{14}}', end: ''},
                        part: 'year'
                    },
                    new BinaryNode('=',
                        createFuncCallAST('year', createGetFieldAST()),
                        createFuncCallAST('currentYear')
                    )
                ],
                'year part between some year and current year': [
                    {
                        type: '1',
                        value: {start: '1981', end: '{{14}}'},
                        part: 'year'
                    },
                    new BinaryNode(
                        'and',
                        new BinaryNode('>=',
                            createFuncCallAST('year', createGetFieldAST()),
                            new ConstantNode('1981')
                        ),
                        new BinaryNode('<=',
                            createFuncCallAST('year', createGetFieldAST()),
                            createFuncCallAST('currentYear')
                        )
                    )
                ]
            };

            _.each(cases, function(testCase, caseName) {
                it(caseName, function() {
                    var condition = {
                        columnName: 'bar',
                        criterion: {
                            filter: 'datetime',
                            data: testCase[0]
                        }
                    };
                    expect(translator.tryToTranslate(condition)).toEqual(testCase[1]);
                });
            });
        });

        describe('can\'t translate condition because of', function() {
            var cases = {
                'unknown filter': {
                    columnName: 'bar',
                    criterion: {
                        filter: 'qux',
                        data: {
                            type: '3',
                            value: {start: '2018-03-28 00:00', end: ''},
                            part: 'value'
                        }
                    }
                },
                'unknown criterion type': {
                    columnName: 'bar',
                    criterion: {
                        filter: 'datetime',
                        data: {
                            type: 'qux',
                            value: {start: '2018-03-28 00:00', end: ''},
                            part: 'value'
                        }
                    }
                },
                'missing column name': {
                    criterion: {
                        filter: 'datetime',
                        data: {
                            type: '3',
                            value: {start: '2018-03-28 00:00', end: ''},
                            part: 'value'
                        }
                    }
                },
                'missing value': {
                    columnName: 'bar',
                    criterion: {
                        filter: 'datetime',
                        data: {
                            type: '3',
                            part: 'value'
                        }
                    }
                },
                'missing end value': {
                    columnName: 'bar',
                    criterion: {
                        filter: 'datetime',
                        data: {
                            type: '3',
                            value: {start: '2018-03-28 00:00'},
                            part: 'value'
                        }
                    }
                },
                'incorrect datetime value': {
                    columnName: 'bar',
                    criterion: {
                        filter: 'datetime',
                        data: {
                            type: '3',
                            value: {start: '2018-03-28', end: ''},
                            part: 'value'
                        }
                    }
                },
                'missing part': {
                    columnName: 'bar',
                    criterion: {
                        filter: 'datetime',
                        data: {
                            type: '3',
                            value: {start: '2018-03-28 00:00', end: ''}
                        }
                    }
                },
                'unknown part': {
                    columnName: 'bar',
                    criterion: {
                        filter: 'datetime',
                        data: {
                            type: '3',
                            value: {start: '2018-03-28 00:00', end: ''},
                            part: 'era'
                        }
                    }
                },
                'incorrect day of week part value': {
                    columnName: 'bar',
                    criterion: {
                        filter: 'datetime',
                        data: {
                            type: '3',
                            value: {start: '8', end: ''},
                            part: 'dayofweek'
                        }
                    }
                },
                'incorrect month part value': {
                    columnName: 'bar',
                    criterion: {
                        filter: 'datetime',
                        data: {
                            type: '3',
                            value: {start: '13', end: ''},
                            part: 'month'
                        }
                    }
                },
                'incorrect year part value': {
                    columnName: 'bar',
                    criterion: {
                        filter: 'datetime',
                        data: {
                            type: '3',
                            value: {start: '02018', end: ''},
                            part: 'year'
                        }
                    }
                }
            };

            _.each(cases, function(condition, caseName) {
                it(caseName, function() {
                    expect(translator.tryToTranslate(condition)).toBe(null);
                });
            });
        });
    });
});
