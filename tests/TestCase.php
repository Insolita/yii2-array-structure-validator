<?php

namespace tests;

use yii\helpers\ArrayHelper;
use yii\web\Application;

class TestCase extends \PHPUnit\Framework\TestCase
{
    protected $backupGlobals = false;

    protected function setUp():void
    {
        parent::setUp();
        $this->mockApplication();
    }

    protected function mockApplication(array $extendConfig = []):Application
    {
        $config = ArrayHelper::merge([
            'id' => 'yii2-openapi-test',
            'basePath' => __DIR__,
            'language'=> 'en',
            'components'=>[]
        ], $extendConfig);
        return new Application($config);
    }
}