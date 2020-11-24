#### Yii2 validator for complex array structures

![yii2-array-structure-validator](https://github.com/Insolita/yii2-array-structure-validator/workflows/yii2-array-structure-validator/badge.svg?branch=master)

Validator for array attributes, unlike builtin "each" validator, that support only one rule, this validator can
 * validate multiple array attributes and even nested data structures
 * All keys that should be present in array must be described, for optional keys default value should be set
 * When input array not contains key defined in rules, this key added automatically with null value
 * When input array contains key not defined in rules, "unexpected item" error will be defined
 
#### Installation

```composer require insolita/yii2-array-structure-validator ```

#### Usage

For a simple array with known keys like `['id'=>1, 'name'=>'John Doe']`;

```php

public function rules() 
{
   return [
   //...
       ['simpleArray', ArrayStructureValidator::class, 
          'rules'=>[
                 'id'=>[['required'], ['integer','min'=>0]],
                 'name'=>[['required'], ['string', 'max'=>100]],
                 'sex'=>[['default', 'value'=>'male'], ['in','range'=>['male','female']]
            ]]
       ],
   ];
}
```    

For multidimensional arrays like 
`
[
    ['id'=>1, 'name'=>'John Doe'],
    ['id'=>2, 'name'=>'Jane Doe','sex'=>'female'],
     ...
]`
 set each = true

```php

public function rules() 
{
   return [
   //...
       [['multiArray', 'some', 'attrs'], 'required'],
       ['multiArray', ArrayStructureValidator::class, 
          'each'=>true,
           'rules'=>[
                 'id'=>[['required'], ['integer','min'=>0]],
                 'name'=>[['required'], ['string', 'max'=>100]],
                 'sex'=>[['default', 'value'=>'male'], ['in','range'=>['male','female']]
            ]]
       ]
   ];
}
```    

For nested structures like 
```
[
    'user'=>['id'=>1, 'name'=>'John Doe'],
    'coords'=>[['x'=>1, 'y'=>2],['x'=>3,'y'=>4]]
]
```
```php

public function rules() 
{
   return [
   //...
       ['complexArray', ArrayStructureValidator::class, 
        'rules'=>[
                 'user'=>[[ArrayStructureValidator::class, 
                       'rules'=>[
                           'id'=>[['required'], ['integer','min'=>0]],
                           'name'=>[['required'], ['string', 'max'=>100]],
                 ]]],
                 'coords'=>[[ArrayStructureValidator::class, 
                      'each'=>true, 
                      'rules'=>[
                            'x'=>[['required'], ['integer','min'=>0]],
                            'y'=>[['required'], ['integer','min'=>0]],
                 ], 'min'=>1, 'max'=>5]],
           ], 'min'=>2, 'max'=>2]
   ];
}
```    

Model scenarios supported

```php
public function rules() 
{
   return [
   //...
        ['conditional', ArrayStructureValidator::class, 
        'rules'=>[
                  'a'=>[['integer','min'=>0]], //will be checked on any scenario
                  'b'=>[
                          ['default', 'value'=>1, 'on'=>['create']],
                          ['integer', 'max'=>10, 'except'=>['create']],
                          ['required',  'on'=>['update']],
                          ['integer', 'max'=>1000, 'on'=>['update']],
                      ]
             ]
        ]
    ];
}
```

Closure and Inline validators supported, but with signature different from default

Inline method in model class

```php
public function rules() 
{
   return [
      ['array', ArrayStructureValidator::class,  'rules'=>[
             'item'=>[['required'], ['customValidator']]
      ]]
   ];
}

public function customValidator($attribute, $model, $index, $baseModel, $baseAttribute){
 /**
 * $model - Dynamic model with attributes equals value data, or value row, if used with each=>true
 * $attribute - current keyName
 * $index - current array index for multidimensional arrays, or null
 * $baseModel - instance of initial model, where validator was attached
 * $baseAttribute - name of initial attributed, where validator was attached

 * access to validated value - $model->$attribute
 * access to whole validated array  $baseModel->$baseAttribute
 * $model->addError($attribute, '[{index}][{attribute}] Error message', ['index'=>$index]);
*/
}
```

When conditions supported (But not whenClient!)

```php

public function rules() 
{
   return [
      ['conditional', ArrayStructureValidator::class, 
          'rules'=>[
               'x'=>[['safe']],
               'y'=>[
                   ['default', 'value'=>1, 'when'=>fn(DynamicModel $model) => $model->x < 10],
                   [
                      'default',
                      'value'=>5,
                      'when'=>function($model, $attribute, $index, $baseModel, $baseAttribute){
                              return count($baseModel->$baseAttribute) > 5;
                         }],
                   ]
           ]]
   ];
}
```

#### Note:
Database related validators (exists, unique) not covered by tests yet and not supported 