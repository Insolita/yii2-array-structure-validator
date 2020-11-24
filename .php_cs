<?php
$finder = PhpCsFixer\Finder::create()
            ->in(['src']);
return PhpCsFixer\Config::create()
    ->setFinder($finder)
    ->setRules([
        '@PSR2' => true,
        'array_syntax' => ['syntax' => 'short'],
        'general_phpdoc_annotation_remove' => ['annotations' => ['author']],
        'header_comment' => [
            'comment_type' => 'PHPDoc',
            'header' => <<<COMMENT
@copyright Copyright (c) 2020 Insolita <webmaster100500@ya.ru> and contributors
@license https://github.com/insolita/yii2-array-structure-validator/blob/master/LICENSE
COMMENT
        ]
    ])
;

