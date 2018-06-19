<?php

/*
 * Data backup addon for Bear Framework
 * https://github.com/ivopetkov/data-backup-bearframework-addon
 * Copyright (c) 2018 Ivo Petkov
 * Free to use under the MIT license.
 */

/**
 * @runTestsInSeparateProcesses
 */
class DataTest extends BearFrameworkAddonTestCase
{

    /**
     * 
     */
    public function testDefaults()
    {
        $app = $this->getApp();

        $item = $app->data->make('test1', '1');
        $item->metadata['v1'] = '1';
        $app->data->set($item);

        $item = $app->data->make('test/test2', '2');
        $app->data->set($item);
        $app->data->makePublic('test/test2');

        $item = $app->data->make('test/test3', '3');
        $app->data->set($item);

        $backupFileName = sys_get_temp_dir() . '/data-backup-test-' . uniqid() . '.zip';
        $keys = $app->dataBackup->backupAll($backupFileName);
        $this->assertTrue($keys === [
            'test/test2',
            'test/test3',
            'test1'
        ]);
    }

    /**
     * 
     */
    public function testExceptions1()
    {
        $app = $this->getApp();

        $backupFileName = sys_get_temp_dir() . '/data-backup-test-' . uniqid() . '.zip';
        $keys = $app->dataBackup->backupAll($backupFileName);
        $this->assertTrue($keys === []);
        $this->setExpectedException('Exception'); // Backup file exists
        $keys = $app->dataBackup->backupAll($backupFileName);
    }

}
