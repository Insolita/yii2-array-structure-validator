<?php
use insolita\ArrayStructureValidator\ArrayStructureValidator;
use tests\TestCase;
use yii\base\DynamicModel;
use yii\base\Model;

class ArrayStructureValidatorTest extends TestCase
{
    public function testArrayAndSizeValidation():void
    {
        $data = [
            'notArray' => 'foo',
            'emptyArray' => [],
            'emptyArray2' => [],
            'hash' => ['a' => 1, 'b' => 2, 'c' => 'foo'],
            'hash2' => ['a' => 1, 'b' => 2, 'c' => 'foo'],
            'indexed' => [['x' => 'foo'], ['x' => 'bar'], ['x' => 'baz']],
        ];
        $rules = [
            ['notArray', ArrayStructureValidator::class, ['message' => 'notArray']],
            ['emptyArray', ArrayStructureValidator::class, []],
            ['emptyArray2', ArrayStructureValidator::class, ['min' => 1]],
            ['hash', ArrayStructureValidator::class, ['min' => 4]],
            ['hash2', ArrayStructureValidator::class, ['min' => 3, 'max' => 3]],
            ['indexed', ArrayStructureValidator::class, ['max' => 2]],
        ];
        $model = new DynamicModel($data);
        foreach ($rules as $rule) {
            $model->addRule(...$rule);
        }
        $model->validate();
        expect($model->hasErrors(), true);
        expect($model->getFirstError('notArray'))->equals('notArray');
        expect($model->getFirstError('emptyArray'))->null();
        expect($model->getFirstError('emptyArray2'))->contains('Empty Array2 is too small');
        expect($model->getFirstError('hash'))->contains('Hash is too small');
        expect($model->getFirstError('hash2'))->null();
        expect($model->getFirstError('indexed'))->contains('Indexed is too large');
    }

    public function testArraySizeValidationWithEachRule():void
    {
        $data = [
            'a' => [['foo', 'bar'], ['foo', 'bar'], ['foo', 'bar']],
            'b' => [['foo'], ['foo', 'bar'], []],
            'c' => [['foo'], ['foo', 'bar'], []],
        ];
        $rules = [
            ['a', 'each', ['rule' => [ArrayStructureValidator::class, 'min' => 2, 'max' => 2]]],
            ['b', 'each', ['rule' => [ArrayStructureValidator::class, 'min' => 2, 'max' => 2]]],
            [
                'c',
                'each',
                [
                    'rule' => [ArrayStructureValidator::class, 'min' => 2, 'max' => 2],
                    'stopOnFirstError' => false,
                ],
            ],
        ];
        $model = new DynamicModel($data);
        foreach ($rules as $rule) {
            $model->addRule(...$rule);
        }
        $model->validate();
        //VarDumper::dump($model->getErrors());
        expect($model->hasErrors())->true();
        expect($model->hasErrors('a'))->false();
        expect($model->hasErrors('b'))->true();
        expect($model->getErrors('b'))->count(1);
        expect($model->getErrors('c'))->count(2);
    }

    public function testWithAssociativeStructure():void
    {
        $data = [
            'valid' => [
                'a' => 1,
                'b' => 2,
                'c' => ' foo ',
                'd' => '123@mail.ru',
                'e' => 'http://mail.ru',
                'f' => '192.168.1.1',
            ],
            'invalid1' => [
                'a' => 1,
                'b' => 2,
                'c' => ' foo  ',
                'd' => '-123@mail.ru-',
                'e' => 'qwerty',
                'f' => '192.168.foo',
            ],
            'invalid2' => [
                'a' => 1,
                'b' => 2,
                'c' => ' foo  ',
                'd' => '-123@mail.ru-',
                'e' => 'qwerty',
                'f' => '192.168.foo',
            ],
            'invalid3' => ['a' => 1, 'b' => 2, 'foo' => 4, 'bar' => 5],
        ];
        $struct = [
            'a' => [['integer', 'min' => 5]],
            'b' => [['integer'], ['in', 'range' => ['3', '4']]],
            'c' => [['trim'], ['string', 'min' => 5]],
            'd' => [['email']],
            'e' => [['url']],
            'f' => [['ip']],
        ];
        $rules = [
            [
                'valid',
                ArrayStructureValidator::class,
                [
                    'rules' => [
                        'a' => [['integer', 'min' => 1]],
                        'b' => [['integer', 'max' => 3], ['in', 'range' => [1, 2, 3]]],
                        'c' => [['trim'], ['string', 'max' => 3]],
                        'd' => [['email']],
                        'e' => [['url']],
                        'f' => [['ip']],
                    ],
                ],
            ],
            ['invalid1', ArrayStructureValidator::class, ['rules' => $struct, 'compactErrors' => true]],
            ['invalid2', ArrayStructureValidator::class, ['rules' => $struct]],
            ['invalid3', ArrayStructureValidator::class, ['rules' => $struct]],
        ];
        $model = new DynamicModel($data);
        foreach ($rules as $rule) {
            $model->addRule(...$rule);
        }
        $model->validate();
        //VarDumper::dump($model->getErrors());
        expect($model->hasErrors())->true();
        expect($model->hasErrors('valid'))->false();
        expect($model->hasErrors('invalid1'))->true();
        expect($model->hasErrors('invalid2'))->true();
        expect($model->hasErrors('invalid3'))->true();
        expect($model->getErrors('invalid1'))->count(1);
        expect($model->getErrors('invalid2'))->count(count($data['invalid2']));
        expect($model->getErrors('invalid3'))->contains('Invalid3 contains unexpected items foo,bar');
    }

    public function testWithIndexedStructureEachOption():void
    {
        $data = [
            'valid' => [['x' => 1, 'y' => 2], ['x' => 5, 'y' => 10]],
            'invalid' => [['x' => 0, 'y' => 2], ['x' => 5, 'y' => 100], ['x' => 'foo', 'foo' => 10], ['y' => 20]],
            'invalid2' => [['x' => 0, 'y' => 2], ['x' => 5, 'y' => 100], ['x' => 'foo', 'foo' => 10], ['y' => 20]],
        ];
        $rules = [
            [
                'valid',
                ArrayStructureValidator::class,
                [
                    'rules' => ['x' => [['required'], ['integer']], 'y' => [['required'], ['integer']]],
                    'each' => true,
                ],
            ],
            [
                'invalid',
                ArrayStructureValidator::class,
                [
                    'rules' => ['x' => [['required'], ['integer', 'min' => 1]], 'y' => [['required'], ['integer']]],
                    'each' => true,
                    'compactErrors' => false,
                    'stopOnFirstError' => false,
                ],
            ],
        ];
        $model = new DynamicModel($data);
        foreach ($rules as $rule) {
            $model->addRule(...$rule);
        }
        $model->validate();
        //VarDumper::dump($model->getErrors());
        expect($model->hasErrors('valid'))->false();
        expect($model->hasErrors('invalid'))->true();
    }

    public function testWithIndexedStructureAndEachValidator():void
    {
        $data = [
            'valid' => [['x' => 1, 'y' => 2], ['x' => 5, 'y' => 10]],
            'invalid' => [['x' => 0, 'y' => 2], ['x' => 5, 'y' => 100], ['x' => 'foo', 'foo' => 10], ['y' => 20]],
        ];

        $rules = [
            [
                'valid',
                'each',
                [
                    'rule' => [
                        ArrayStructureValidator::class,
                        'rules' => [
                            'x' => [['required'], ['integer']],
                            'y' => [['required'], ['integer']],
                        ],
                    ],
                ],
            ],
            [
                'invalid',
                'each',
                [
                    'rule' => [
                        ArrayStructureValidator::class,
                        'rules' => [
                            'x' => [['required'], ['integer', 'min' => 1]],
                            'y' => [['required'], ['integer']],
                        ],
                        'stopOnFirstError' => false,
                    ],
                    'stopOnFirstError' => false,
                ],
            ],
        ];
        $model = new DynamicModel($data);
        foreach ($rules as $rule) {
            $model->addRule(...$rule);
        }
        $model->validate();
        //VarDumper::dump($model->getErrors());
        expect($model->hasErrors('valid'))->false();
        expect($model->hasErrors('invalid'))->true();
    }

    public function testMutableImmutable():void
    {
        $data = [
            'immutable' => ['x' => 1, 'y' => ' foo '],
            'mutable' => ['x' => 1, 'y' => ' foo '],
            'mutableIndexed' => [['x' => 1, 'y' => ' foo '], ['x' => 2, 'y' => ' bar ']],
        ];
        $structure = [
            'x' => [['filter', 'filter' => function($v) { return $v + 10; }], ['integer', 'min' => 10]],
            'y' => [['trim']],
            'z' => [['default', 'value' => 100500]],
        ];
        $rules = [
            ['immutable', ArrayStructureValidator::class, ['rules' => $structure, 'mutable' => false]],
            ['mutable', ArrayStructureValidator::class, ['rules' => $structure, 'mutable' => true]],
            [
                'mutableIndexed',
                ArrayStructureValidator::class,
                [
                    'rules' => $structure,
                    'mutable' => true,
                    'each' => true,
                ],
            ],
        ];
        $model = new DynamicModel($data);
        foreach ($rules as $rule) {
            $model->addRule(...$rule);
        }

        expect($model->validate())->true();
        expect($model->immutable)->equals($data['immutable']);
        expect($model->mutable)->equals(['x' => 11, 'y' => 'foo', 'z' => 100500]);
        expect($model->mutableIndexed[0])->equals(['x' => 11, 'y' => 'foo', 'z' => 100500]);
        expect($model->mutableIndexed[1])->equals(['x' => 12, 'y' => 'bar', 'z' => 100500]);
    }

    public function testWithClosureRules():void
    {
        $data = [
            'hash' => ['a' => 1, 'b' => 'foo'],
            'indexed' => [
                ['a' => 1, 'b' => 'foo'],
                ['a' => 12, 'b' => 'bar'],
            ],
        ];
        $closure = function($attribute, $model, $index, $baseModel, $baseAttribute) {
            if ($baseAttribute === 'hash') {
                expect($index)->null();
            } else {
                expect(in_array($index, [0, 1], true))->true();
            }
            expect($model)->isInstanceOf(DynamicModel::class);
            expect($model->hasAttribute('a'))->true();
            expect($model->hasAttribute('b'))->true();
            expect($model->hasAttribute('hash'))->false();
            expect($baseModel)->isInstanceOf(DynamicModel::class);
            expect($baseModel->hasAttribute('a'))->false();
            expect($baseModel->hasAttribute('hash'))->true();
            expect($baseModel->hasAttribute('indexed'))->true();
            if ($model->b !== 'foo') {
                $model->addError($attribute, $attribute . ' Fail condition from closure');
            }
        };
        $structure = [
            'a' => [
                ['integer'],
                [$closure],
            ],
            'b' => [[$closure]],
        ];
        $rules = [
            ['hash', ArrayStructureValidator::class, ['rules' => $structure]],
            [
                'indexed',
                ArrayStructureValidator::class,
                ['rules' => $structure, 'compactErrors' => false, 'each' => true],
            ],
        ];
        $model = new DynamicModel($data);
        foreach ($rules as $rule) {
            $model->addRule(...$rule);
        }
        expect($model->validate())->false();
        //VarDumper::dump($model->errors);
        expect($model->getErrors('indexed'))->contains('[1]a Fail condition from closure');
        expect($model->getErrors('indexed'))->contains('[1]b Fail condition from closure');
        expect($model->hasErrors('hash'))->false();
    }

    public function testNestedArray():void
    {
        $data = [
            'a' => [
                'foo' => [['x' => 1], ['x' => 5], ['x' => 3, 'y' => 4]],
                'bar' => [['a' => 1], ['b' => 5], ['x' => 3, 'a' => 4, 'c' => 'foo']],
            ],
            'b' => ['foo' => 1, 'bar' => ['id' => 1, 'v' => '123']],
        ];
        $rules = [
            [
                'a',
                ArrayStructureValidator::class,
                [
                    'rules' => [
                        'foo' => [
                            [
                                ArrayStructureValidator::class,
                                'each' => true,
                                'rules' => [
                                    'x' => [['integer', 'min' => 0, 'max' => 10]],
                                    'y' => [['safe']],
                                    'z' => [['default', 'value' => 100500]],
                                ],
                            ],
                        ],
                        'bar' => [
                            [
                                ArrayStructureValidator::class,
                                'each' => true,
                                'rules' => [
                                    'x' => [['default', 'value' => 100500], ['integer', 'min' => 0]],
                                    'a' => [['safe']],
                                    'b' => [['default', 'value' => 33], ['match', 'pattern' => '/\d+/']],
                                    'c' => [['default', 'value' => 'bar'], ['string']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'b',
                ArrayStructureValidator::class,
                [
                    'rules' => [
                        'foo' => [['required'], ['integer']],
                        'bar' => [
                            [
                                ArrayStructureValidator::class,
                                'rules' => [
                                    'id' => [['integer']],
                                    'v' => [['required']],
                                    'a' => [['safe']],
                                    'b' => [['default', 'value' => 'foo']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $model = new DynamicModel($data);
        foreach ($rules as $rule) {
            $model->addRule(...$rule);
        }
        expect($model->validate())->true();
        //VarDumper::dump($model->getAttributes());
        foreach ($model->a['foo'] as $item) {
            expect($item)->hasKey('z');
            expect($item['z'])->equals(100500);
        }
        foreach ($model->a['bar'] as $item) {
            expect($item)->hasKey('a');
            expect($item)->hasKey('b');
            expect($item)->hasKey('c');
            expect($item)->hasKey('x');
        }
        expect($model->b['bar']['a'])->equals(null);
        expect($model->b['bar']['b'])->equals('foo');
    }

    public function testErrorMessages():void
    {
        $data = [
            'indexedFullErrors' => [
                'foo' => [['x' => 100], ['x' => 5], ['x' => 3, 'y' => 4]],
                'bar' => [['a' => 1], ['b' => 5], ['x' => 3, 'a' => 4]],
            ],
            'indexedStopOnFirst' => [
                'foo' => [['x' => 100], ['x' => 5], ['x' => 3, 'y' => 4]],
                'bar' => [['a' => 1], ['b' => 5], ['x' => 3, 'a' => 4]],
            ],
            'indexedCompactErrors' => [
                'foo' => [['x' => 100], ['x' => 5], ['x' => 3, 'y' => 4]],
                'bar' => [['a' => 1], ['b' => 5], ['x' => 3, 'a' => 4]],
            ],
            'hashFullErrors' => ['foo' => 1, 'bar' => ['id' => 1, 'v' => '123']],
            'hashCompactErrors' => ['foo' => 1, 'bar' => ['id' => 1, 'v' => '123']],
        ];
        $rules = [
            [
                'indexedFullErrors',
                ArrayStructureValidator::class,
                [
                    'rules' => [
                        'foo' => [
                            [
                                ArrayStructureValidator::class,
                                'each' => true,
                                'rules' => [
                                    'x' => [['integer', 'min' => 0, 'max' => 10]],
                                    'y' => [['required']],
                                ],
                                'stopOnFirstError' => false,
                                'compactErrors' => false,
                            ],
                        ],
                        'bar' => [
                            [
                                ArrayStructureValidator::class,
                                'each' => true,
                                'rules' => [
                                    'x' => [['default', 'value' => 100500], ['integer', 'min' => 0]],
                                    'a' => [['integer', 'min' => 2]],
                                    'b' => [
                                        ['default', 'value' => 'foo'],
                                        ['match', 'pattern' => '/\d+/', 'not' => true],
                                    ],
                                ],
                                'stopOnFirstError' => false,
                                'compactErrors' => false,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'indexedStopOnFirst',
                ArrayStructureValidator::class,
                [
                    'rules' => [
                        'foo' => [
                            [
                                ArrayStructureValidator::class,
                                'each' => true,
                                'rules' => [
                                    'x' => [['integer', 'min' => 0, 'max' => 10]],
                                    'y' => [['required']],
                                ],
                                'stopOnFirstError' => true,
                                'compactErrors' => false,
                            ],
                        ],
                        'bar' => [
                            [
                                ArrayStructureValidator::class,
                                'each' => true,
                                'rules' => [
                                    'x' => [['default', 'value' => 100500], ['integer', 'min' => 0]],
                                    'a' => [['integer', 'min' => 2]],
                                    'b' => [
                                        ['default', 'value' => 'foo'],
                                        ['match', 'pattern' => '/\d+/', 'not' => true],
                                    ],
                                ],
                                'stopOnFirstError' => true,
                                'compactErrors' => false,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'indexedCompactErrors',
                ArrayStructureValidator::class,
                [
                    'rules' => [
                        'foo' => [
                            [
                                ArrayStructureValidator::class,
                                'each' => true,
                                'rules' => [
                                    'x' => [['integer', 'min' => 0, 'max' => 10]],
                                    'y' => [['required']],
                                ],
                                'stopOnFirstError' => false,
                                'compactErrors' => true,
                            ],
                        ],
                        'bar' => [
                            [
                                ArrayStructureValidator::class,
                                'each' => true,
                                'rules' => [
                                    'x' => [['default', 'value' => 100500], ['integer', 'min' => 0]],
                                    'a' => [['integer', 'min' => 2]],
                                    'b' => [
                                        ['default', 'value' => 'foo'],
                                        ['match', 'pattern' => '/\d+/', 'not' => true],
                                    ],
                                ],
                                'stopOnFirstError' => false,
                                'compactErrors' => true,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'hashFullErrors',
                ArrayStructureValidator::class,
                [
                    'rules' => [
                        'foo' => [['required'], ['integer']],
                        'bar' => [
                            [
                                ArrayStructureValidator::class,
                                'rules' => [
                                    'id' => [['email']],
                                    'v' => [['required']],
                                    'a' => [['required', 'message' => '{attribute} custom message for required']],
                                ],
                                'stopOnFirstError' => false,
                                'compactErrors' => false,
                            ],
                        ],
                    ],
                    'stopOnFirstError' => false,
                    'compactErrors' => false,
                ],
            ],
            [
                'hashCompactErrors',
                ArrayStructureValidator::class,
                [
                    'rules' => [
                        'foo' => [['required'], ['integer']],
                        'bar' => [
                            [
                                ArrayStructureValidator::class,
                                'rules' => [
                                    'id' => [['email']],
                                    'v' => [['required']],
                                    'a' => [['required', 'message' => '{attribute} custom message for required']],
                                ],
                                'stopOnFirstError' => false,
                                'compactErrors' => true,
                            ],
                        ],
                    ],
                    'stopOnFirstError' => false,
                    'compactErrors' => false,
                ],
            ],
        ];
        $model = new DynamicModel($data);
        foreach ($rules as $rule) {
            $model->addRule(...$rule);
        }
        expect($model->validate())->false();
        expect($model->getErrors('indexedFullErrors'))->contains('[foo][0]Y cannot be blank.');
        expect($model->getErrors('indexedFullErrors'))->contains('[foo][1]Y cannot be blank.');
        expect($model->getErrors('indexedFullErrors'))->contains('[foo][0]X must be no greater than 10.');
        expect($model->getErrors('indexedFullErrors'))->contains('[bar][0]A must be no less than 2.');
        expect($model->getErrors('indexedFullErrors'))->contains('[bar][1]B is invalid.');

        expect($model->getErrors('indexedStopOnFirst'))->contains('[foo][0]Y cannot be blank.');
        expect($model->getErrors('indexedStopOnFirst'))->notContains('[foo][1]Y cannot be blank.');
        expect($model->getErrors('indexedStopOnFirst'))->contains('[foo][0]X must be no greater than 10.');
        expect($model->getErrors('indexedStopOnFirst'))->contains('[bar][0]A must be no less than 2.');
        expect($model->getErrors('indexedStopOnFirst'))->notContains('[bar][1]B is invalid.');

        expect($model->getErrors('hashFullErrors'))->contains('[bar]A custom message for required');
        expect($model->getErrors('hashFullErrors'))->contains('[bar]Id is not a valid email address.');

        $compactErrors1 = implode("\n",
            ['[bar]A custom message for required', '[bar]Id is not a valid email address.']
        );
        $compactErrors2 = implode("\n",
            ['[bar]Id is not a valid email address.', '[bar]A custom message for required']
        );
        $errors = $model->getErrors('hashCompactErrors');
        expect($errors === $compactErrors1 || $errors === $compactErrors2);
    }

    public function testUniqueValidatorNotSupported()
    {
        $this->expectExceptionMessage('Unique rule not supported with current validator');
        $model = new DynamicModel(['x' => ['a' => 1]]);
        $model->addRule('x', ArrayStructureValidator::class, ['rules' => ['a' => [['unique']]]]);
        $model->validate();
    }

    public function testExistValidatorNotSupported()
    {
        $this->expectExceptionMessage('Avoid exist validator usage with multidimensional array');
        $model = new DynamicModel(['x' => [['a' => 1]]]);
        $model->addRule('x', ArrayStructureValidator::class, ['each' => true, 'rules' => ['a' => [['exist']]]]);
        $model->validate();
    }

    public function testValidationWithoutModel()
    {
        $validator = new ArrayStructureValidator([
            'each' => true,
            'rules' => [
                'x' => [['integer', 'min' => 0, 'max' => 10]],
                'y' => [['required']],
            ],
        ]);
        $error = '';
        $isValid = $validator->validate([['x' => 100], ['x' => 5], ['x' => 3, 'y' => 4]], $error);
        expect($isValid)->false();
        expect($error)->contains('[0]Y cannot be blank.');
        expect($error)->contains('[0]X must be no greater than 10.');
    }

    public function testScenarioConditionsShouldBeApplied():void
    {
        $model = new class extends Model {
            public $value;

            public function scenarios()
            {
                return [
                    'default' => ['value'],
                    'test1' => ['value'],
                    'test2' => ['value'],
                ];
            }

            public function rules()
            {
                return [
                    [
                        'value',
                        ArrayStructureValidator::class,
                        'rules' => [
                            'x' => [
                                ['integer', 'min' => 5, 'on' => ['test1']],
                                ['integer', 'max' => 5, 'on' => ['test2']],
                            ],
                            'y' => [
                                ['default', 'value' => 1, 'on' => ['test1']],
                                ['default', 'value' => 2, 'on' => ['test2']],
                            ],
                            'z' => [['default', 'value' => 'foo']],
                            'foo' => [['string', 'max' => 3]],
                        ],
                        'on' => ['test1', 'test2'],
                    ],
                    [
                        'value',
                        ArrayStructureValidator::class,
                        'rules' => [
                            'x' => [['safe']],
                            'y' => [['safe']],
                            'foo' => [['safe']],
                            'z' => [['default', 'value' => 'bar']],
                        ],
                        'on' => ['default'],
                    ],
                ];
            }
        };

        $model->scenario = 'default';
        $model->value = ['x' => '1', 'y' => null, 'foo' => '123'];
        $model->validate();
        expect($model->value['y'])->null();
        expect($model->value['z'])->equals('bar');
        expect($model->hasErrors())->false();

        $model->value = ['x' => '1', 'y' => null, 'foo' => '1234'];
        $model->scenario = 'test1';
        $model->validate();
        //VarDumper::dump([$model->scenario, $model->getErrors()]);
        expect($model->value['y'])->equals(1);
        expect($model->value['z'])->equals('foo');
        expect($model->getErrors('value'))->contains('X must be no less than 5.');
        expect($model->getErrors('value'))->contains('Foo should contain at most 3 characters.');

        $model->scenario = 'test2';
        $model->value = ['x' => '1', 'y' => null, 'foo' => '123'];
        $model->validate();
        expect($model->value['y'])->equals(2);
        expect($model->value['z'])->equals('foo');
        expect($model->hasErrors())->false();
    }

    public function testWhenConditionsShouldBeApplied()
    {
        $model = new class extends Model {
            public $value;

            public $dummy;

            public function scenarios()
            {
                return [
                    'default' => ['value'],
                    'test1' => ['value'],
                    'test2' => ['value'],
                ];
            }

            public function rules()
            {
                return [
                    [
                        'value',
                        ArrayStructureValidator::class,
                        'rules' => [
                            'x' => [['safe']],
                            'z' => [
                                [
                                    'default',
                                    'value' => 'foo',
                                    'when' => function(
                                        $model, $attribute, $index, $baseModel,
                                        $baseAttribute
                                    ) {
                                        expect($model)->isInstanceOf(DynamicModel::class);
                                        expect($attribute)->equals('z');
                                        expect($baseModel)->isInstanceOf(Model::class);
                                        expect($baseAttribute)->equals('value');
                                        expect($index)->equals(null);
                                        return $model->x > 10;
                                    },
                                ],
                                [
                                    'default',
                                    'value' => 'bar',
                                    'when' => function($model, $attribute, $index, $baseModel) {
                                        return $model->x < 10 && $baseModel->dummy === 'bar';
                                    },
                                ],
                            ],
                        ],
                    ],
                ];
            }
        };
        $model->dummy = 'bar';
        $model->value = ['x' => 1];
        $model->validate();
        expect($model->value['z'])->equals('bar');

        $model->dummy = 'bar';
        $model->value = ['x' => 15];
        $model->validate();
        expect($model->value['z'])->equals('foo');

        $model->dummy = '';
        $model->value = ['x' => 5];
        $model->validate();
        expect($model->value['z'])->null();
    }
}
