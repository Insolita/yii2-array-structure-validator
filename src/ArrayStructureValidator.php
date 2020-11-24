<?php

/**
 * @copyright Copyright (c) 2020 Insolita <webmaster100500@ya.ru> and contributors
 * @license https://github.com/insolita/yii2-array-structure-validator/blob/master/LICENSE
 */

namespace insolita\ArrayStructureValidator;

use ArrayAccess;
use Closure;
use Yii;
use yii\base\DynamicModel;
use yii\base\Model;
use yii\base\NotSupportedException;
use yii\validators\Validator;
use function array_diff;
use function array_keys;
use function array_map;
use function array_slice;
use function compact;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_object;

/**
 * Validator for array attributes, unlike builtin "each" validator, that support only one rule, this validator can
 * validate multiple array attributes and even nested data structures
 * All keys that should be present in array must be described, for optional keys default value should be set
 * When input array not contains key defined in rules, this key added automatically with null value
 * When input array contains key not defined in rules, "unexpected item" error will be defined
 * @example
 *  For a simple array with known keys like ['id'=>1, 'name'=>'John Doe'];
 *   ['simpleArray', ArrayStructureValidator::class, 'rules'=>[
 *         'id'=>[['required'], ['integer','min'=>0]],
 *         'name'=>[['required'], ['string', 'max'=>100]],
 *         'sex'=>[['default', 'value'=>'male'], ['in','range'=>['male','female']]
 *    ]]
 *    For multidimensional arrays like [['id'=>1, 'name'=>'John Doe'],['id'=>2, 'name'=>'Jane Doe','sex'=>'female'],..]
 *     ['multiArray', ArrayStructureValidator::class, 'each'=>true, 'rules'=>[
 *         'id'=>[['required'], ['integer','min'=>0]],
 *         'name'=>[['required'], ['string', 'max'=>100]],
 *         'sex'=>[['default', 'value'=>'male'], ['in','range'=>['male','female']]
 *    ]]
 *    For nested structures like ['user'=>['id'=>1, 'name'=>'John Doe'], 'coords'=>[['x'=>1, 'y'=>2],['x'=>3,'y'=>4]]]
 *    ['complexArray', ArrayStructureValidator::class, 'rules'=>[
 *         'user'=>[[ArrayStructureValidator::class, 'rules'=>[
 *            'id'=>[['required'], ['integer','min'=>0]],
 *            'name'=>[['required'], ['string', 'max'=>100]],
 *         ]]],
 *         'coords'=>[[ArrayStructureValidator::class, 'each'=>true, 'rules'=>[
 *            'x'=>[['required'], ['integer','min'=>0]],
 *            'y'=>[['required'], ['integer','min'=>0]],
 *         ], 'min'=>1, 'max'=>5]],
 *    ], 'min'=>2, 'max'=>2]
 *   Model scenarios supported
 *   ['conditional', ArrayStructureValidator::class, 'rules'=>[
 *         'a'=>[['integer','min'=>0]], //will be checked on any scenario
 *         'b'=>[
 *                 ['default', 'value'=>1, 'on'=>['create']],
 *                 ['integer', 'max'=>10, 'except'=>['create']],
 *                 ['required',  'on'=>['update']],
 *                 ['integer', 'max'=>1000, 'on'=>['update']],
 *             ]
 *    ]]
 *  Closure and Inline validators supported, but with another  input arguments
 *   ['array', ArrayStructureValidator::class, 'rules'=>[
 *         'item'=>[['required'], ['customValidator'],
 *         'item2'=>[['default','value'=>''], [function($attribute, $model, $index, $baseModel, $baseAttribute){
 *                 $model - Dynamic model with attributes equals value data, or value row, if used with each=>true
 *                 $attribute - current keyName
 *                 $index - current array index for multidimensional arrays, or null
 *                 $baseModel - instance of initial model, where validator was attached
 *                 $baseAttribute - name of initial attributed, where validator was attached
 *                 access to validated value - $model->$attribute
 *                 access to whole validated array  $baseModel->$baseAttribute
 *                 $model->addError($attribute, '[{index}][{attribute}] Error message', ['index'=>$index]);
 *          }]]
 *    ]],
 *    Inline method in model class
 *    public function customValidator($attribute, $model, $index, $baseModel, $baseAttribute){
 *          //same as in closure
 *     }
 *    When conditions supported (But not whenClient!)
 *    ['conditional', ArrayStructureValidator::class, 'rules'=>[
 *         'x'=>[['safe']],
 *         'y'=>[
 *                 ['default', 'value'=>1, 'when'=>function($model, $attribute){
 *                       return $model->x > 10;
 *                  }],
 *                 ['default', 'value'=>5, 'when'=>function($model, $attribute, $index, $baseModel, $baseAttribute){
 *                       return count($baseModel->$baseAttribute) > 5;
 *                  }],
 *             ]
 *    ]]
 **/
class ArrayStructureValidator extends Validator
{
    public $skipOnEmpty = false;

    /**
     * Max array items number, if null - checking will be skipped
     * @var int
     */
    public $max;

    /**
     * Min array items number, if null - checking will be skipped
     * @var int
     */
    public $min;

    /**
     * Array ['keyName'=>[[validator],[validator2, options], ...]]
     * @var array
     * @example
     *    [
     *      'keyA'=>[['integer', 'allowEmpty'=>true]],
     *      'keyB'=>[['required'], ['trim'], ['string', 'min'=>5]],
     *      'keyC'=>[['filter', 'filter'=>function($v){
     *                              return str_rev($v);
     *                          }
     *       ]],
     *      'keyD'=>[function($attribute, $model, $index, $baseModel, $baseAttribute){
     *            if($model->keyA > $model->$attribute){
     *                $model->addError($attribute, 'Custom message');
     *            }
     *      }],
     *    ]
     */
    public $rules = [];

    /**
     * if false, structure rules will be applied directly for array keys, if true, structure rules will be applied
     * for each array item
     * @var bool
     * @example
     *   use "false", when input array should be as ['key1'=>'value1', 'key2'=>'value2'],
     *   use "true", when input array like [['key1'=>'value1', 'key2'=>'value2'],['key1'=>'value3', 'key2'=>'value4']]
     */
    public $each = false;

    /**
     * Allow value mutations
     * If false, filter, trim, default validators will be applied only inside validator layers, but value of attribute
     * will  be not changed; Set false for keep input value as is
     * @var boolean
     */
    public $mutable = true;

    /**
     * @var bool whether to stop validation once first error is detected.
     */
    public $stopOnFirstError = true;

    /**
     * @var bool When enabled, validation will produce single error message on attribute, when disabled - multiple
     * error messages mya appear: one per each invalid value.
     */
    public $compactErrors = false;

    /**
     * @var string separator for implode errors
     */
    public $errorSeparator = "\n";

    /**
     * @var bool
     * If true, each error message for multidimensional arrays will be prefixed with index of row that contains that
     * error, also nested keys and indexes for complex array with nested structure
     */
    public $prefixErrors = true;

    /**
     * @var string
     */
    public $message;

    /**
     * @var string
     */
    public $tooSmallMessage;

    /**
     * @var string
     */
    public $tooLargeMessage;

    /**
     * @var string
     */
    public $invalidStructureMessage;

    /**
     * @var string
     */
    public $unexpectedItemsMessage;

    /**
     * @internal
     * @var Model
     */
    public $nested = ['baseModel' => '', 'baseAttribute' => '', 'path' => []];

    public function init(): void
    {
        parent::init();
        if ($this->message === null) {
            $this->message = Yii::t('app', '{attribute} is not array.');
        }
        if ($this->tooSmallMessage === null) {
            $this->tooSmallMessage = Yii::t('app', '{attribute} is too small, it should contains at least {min} items');
        }
        if ($this->tooLargeMessage === null) {
            $this->tooLargeMessage =
                Yii::t('app', '{attribute} is too large, it should contains more than {max} items');
        }
        if ($this->invalidStructureMessage === null) {
            $this->invalidStructureMessage = Yii::t('app', '{attribute} contains invalid data');
        }
        if ($this->unexpectedItemsMessage === null) {
            $this->unexpectedItemsMessage = Yii::t('app', '{attribute} contains unexpected items {items}');
        }
    }

    /**
     * @param \yii\base\Model $model
     * @param string          $attribute
     * @throws \yii\base\NotSupportedException
     */
    public function validateAttribute($model, $attribute): void
    {
        $result = $this->validateValue($model->$attribute);
        if (!empty($result)) {
            $this->addError($model, $attribute, $result[0], $result[1] ?? []);
            return;
        }
        if (empty($this->rules)) {
            return;
        }
        $filteredValue = $model->$attribute;
        $baseModel = $this->nested['baseModel'] ?: $model;
        $baseAttribute = $this->nested['baseAttribute'] ?: $attribute;
        $path = $this->nested['path'] ?: [];
        if ($this->each === false) {
            $this->processHashValue($model, $attribute, $filteredValue, $baseModel, $baseAttribute, $path);
        } else {
            $this->processEachValue($model, $attribute, $filteredValue, $baseModel, $baseAttribute, $path);
        }
    }

    /**
     * @param mixed $value
     * @param null  $error
     * @return bool
     * @throws \yii\base\NotSupportedException
     */
    public function validate($value, &$error = null): bool
    {
        $result = $this->validateValue($value);
        if (!empty($result)) {
            $error = $this->prepareError($value, ...$result);
            return false;
        }
        if (empty($this->rules)) {
            return true;
        }
        $model = new DynamicModel(['value' => $value]);
        $baseModel = $this->nested['baseModel'] ?: $model;
        $baseAttribute = $this->nested['baseAttribute'] ?: 'value';
        $path = $this->nested['path'] ?: [];
        if ($this->each === false) {
            $this->processHashValue($model, 'value', $value, $baseModel, $baseAttribute, $path);
        } else {
            $this->processEachValue($model, 'value', $value, $baseModel, $baseAttribute, $path);
        }
        if ($baseModel->hasErrors()) {
            $error = $this->prepareError($value, implode($this->errorSeparator, $baseModel->getErrorSummary(true)), []);
            return false;
        }
        return true;
    }

    protected function validateValue($value):?array
    {
        if (!is_array($value) && !$value instanceof ArrayAccess) {
            return [$this->message, []];
        }
        $size = count($value);
        if ($this->min && $size < $this->min) {
            return [$this->tooSmallMessage, ['min' => $this->min, 'count' => $size]];
        }
        if ($this->max && $size > $this->max) {
            return [$this->tooLargeMessage, ['max' => $this->max, 'count' => $size]];
        }
        return null;
    }

    /**
     * @param string               $baseAttribute
     * @param DynamicModel         $model
     * @param null                 $index
     * @param \yii\base\Model|null $baseModel
     * @param                      $path
     * @return bool
     * @throws \yii\base\NotSupportedException
     */
    private function applyValidators(string $baseAttribute, DynamicModel $model, $index, $baseModel, $path):bool
    {
        foreach ($this->rules as $attribute => $rules) {
            foreach ($rules as $rule) {
                $validator = $rule[0];
                $ruleOptions = array_slice($rule, 1);
                $on = $ruleOptions['on'] ?? [];
                $except = $ruleOptions['except'] ?? [];
                if (!empty($except) && in_array($baseModel->scenario, $except, true)) {
                    continue;
                }
                if (!empty($on) && !in_array($baseModel->scenario, $on, true)) {
                    continue;
                }
                $when = $ruleOptions['when'] ?? null;
                if ($when !== null) {
                    $ruleOptions['when'] =
                         function ($model, $attribute) use ($when, $index, $baseAttribute, $baseModel) {
                             return $when($model, $attribute, $index, $baseModel, $baseAttribute);
                         };
                }
                unset($ruleOptions['except'], $ruleOptions['on'], $ruleOptions['whenClient']);
                if ($validator instanceof Closure) {
                    $originRule = $rule[0];
                    $validator =  function ($attribute) use ($model, $originRule, $index, $baseAttribute, $baseModel) {
                        $originRule($attribute, $model, $index, $baseModel, $baseAttribute);
                    };
                } elseif (!isset(static::$builtInValidators[$validator]) && $baseModel->hasMethod($validator)) {
                    $originRule = $rule[0];
                    $validator =  function ($attribute) use ($model, $originRule, $index, $baseAttribute, $baseModel) {
                        $baseModel->$originRule($attribute, $model, $index, $baseModel, $baseAttribute);
                    };
                } elseif ($validator === self::class) {
                    $ruleOptions['nested'] = compact('baseModel', 'baseAttribute', 'path');
                } elseif ($validator === 'unique') {
                    throw new NotSupportedException('Unique rule not supported with current validator');
                } elseif ($this->each === true && $validator === 'exist') {
                    throw new NotSupportedException('Avoid exist validator usage with multidimensional array');
                }
                $model->addRule($attribute, $validator, $ruleOptions);
            }
        }
        $model->validate();
        if (!$model->hasErrors()) {
            return true;
        }
        return false;
    }

    private function prepareError($value, $message, $params):string
    {
        $params['attribute'] = Yii::t('yii', 'the input value');
        if (is_array($value)) {
            $params['value'] = 'array()';
        } elseif (is_object($value)) {
            $params['value'] = 'object';
        } else {
            $params['value'] = $value;
        }
        return $this->formatMessage($message, $params);
    }

    /**
     * @param                 $model
     * @param                 $attribute
     * @param                 $filteredValue
     * @param \yii\base\Model $baseModel
     * @param string          $baseAttribute
     * @param                 $path
     * @throws \yii\base\NotSupportedException
     */
    private function processEachValue($model, $attribute, $filteredValue, Model $baseModel, $baseAttribute, $path):void
    {
        foreach ($filteredValue as $index => $value) {
            $keyPath = $path;
            if ($attribute !== $baseAttribute) {
                $keyPath[] = "[$attribute]";
            }
            $keyPath[] = "[$index]";
            if (!is_array($value) && !$value instanceof ArrayAccess) {
                $this->addError(
                    $baseModel,
                    $baseAttribute,
                    ($this->prefixErrors ? '{keyPath}' : '') . $this->message,
                    ['keyPath' => implode('', $keyPath)]
                );
            }
            $attributes = array_keys($this->rules);
            $existedAttributes = array_keys($value);
            $unexpectedItems = array_diff($existedAttributes, $attributes);

            if (!empty($this->rules) && !empty($unexpectedItems)) {
                $this->addError(
                    $baseModel,
                    $baseAttribute,
                    ($this->prefixErrors ? '{keyPath}' : '') . $this->unexpectedItemsMessage,
                    ['items' => implode(',', $unexpectedItems), 'keyPath' => implode('', $keyPath)]
                );
                if ($this->stopOnFirstError) {
                    break;
                }
                continue;
            }
            $missingItems = array_diff(array_keys($this->rules), array_keys($value));
            foreach ($missingItems as $item) {
                $value[$item] = null;
            }
            $dynamicModel = new DynamicModel($value);
            //$dynamicModel->setScenario($baseModel->scenario);
            $isValid = $this->applyValidators($attribute, $dynamicModel, $index, $baseModel, $keyPath);
            if ($this->mutable === true) {
                $filteredValue[$index] = $dynamicModel->getAttributes();
                $model->$attribute = $filteredValue;
            }
            if ($isValid === true) {
                continue;
            }
            $params = ['keyPath' => implode('', $keyPath)];
            if ($this->compactErrors) {
                $errors = array_map(
                    function ($err) {
                    return ($this->prefixErrors ? '{keyPath}' : '') . $err;
                },
                    $dynamicModel->getErrorSummary(false)
                );
                $this->addError($baseModel, $baseAttribute, implode($this->errorSeparator, $errors), $params);
            } else {
                $errors = array_map(
                    function ($err) {
                    return ($this->prefixErrors ? '{keyPath}' : '') . $err;
                },
                    $dynamicModel->getErrorSummary(true)
                );
                foreach ($errors as $message) {
                    $this->addError($baseModel, $baseAttribute, $message, $params);
                }
            }
            if ($this->stopOnFirstError) {
                break;
            }
        }
    }

    /**
     * @param                 $model
     * @param                 $attribute
     * @param                 $filteredValue
     * @param \yii\base\Model $baseModel
     * @param                 $baseAttribute
     * @param                 $path
     * @throws \yii\base\NotSupportedException
     */
    private function processHashValue($model, $attribute, $filteredValue, Model $baseModel, $baseAttribute, $path):void
    {
        $attributes = array_keys($this->rules);
        $existedAttributes = array_keys($filteredValue);
        $unexpectedItems = array_diff($existedAttributes, $attributes);
        $keyPath = $path;
        if ($attribute !== $baseAttribute) {
            $keyPath[] = "[$attribute]";
        }
        if (!empty($this->rules) && !empty($unexpectedItems)) {
            $this->addError(
                $baseModel,
                $baseAttribute,
                ($this->prefixErrors ? '{keyPath}' : '') . $this->unexpectedItemsMessage,
                ['items' => implode(',', $unexpectedItems), 'keyPath' => implode('', $keyPath)]
            );
            return;
        }
        $missingItems = array_diff(array_keys($this->rules), array_keys($filteredValue));
        foreach ($missingItems as $item) {
            $filteredValue[$item] = null;
        }
        $dynamicModel = new DynamicModel($filteredValue);
        //$dynamicModel->setScenario($baseModel->scenario);
        $isValid = $this->applyValidators($attribute, $dynamicModel, null, $baseModel, $keyPath);
        if ($this->mutable === true) {
            $model->$attribute = $dynamicModel->getAttributes();
        }
        if ($isValid === true) {
            return;
        }
        $params = ['keyPath' => implode('', $keyPath)];
        if ($this->compactErrors) {
            $errors = array_map(
                function ($err) {
                return ($this->prefixErrors ? '{keyPath}' : '') . $err;
            },
                $dynamicModel->getErrorSummary(false)
            );
            $this->addError($baseModel, $baseAttribute, implode($this->errorSeparator, $errors), $params);
        } else {
            $errors = array_map(
                function ($err) {
                return ($this->prefixErrors ? '{keyPath}' : '') . $err;
            },
                $dynamicModel->getErrorSummary(true)
            );
            foreach ($errors as $message) {
                $this->addError($baseModel, $baseAttribute, $message, $params);
            }
        }
    }
}
