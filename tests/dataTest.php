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

        $getDataSnapshot = function() use ($app) {
            $list = $app->data->getList();
            $array = [];
            foreach ($list as $item) {
                $array[$item->key] = [
                    'value' => $item->value,
                    'metadata' => $item->metadata->toArray(),
                    'public' => (int) $app->data->isPublic($item->key)
                ];
            }
            return $array;
        };

        $this->assertTrue($getDataSnapshot() === []);

        $item = $app->data->make('test1', '1');
        $item->metadata['v1'] = '1';
        $app->data->set($item);

        $item = $app->data->make('test/test2', '2');
        $app->data->set($item);
        $app->data->makePublic('test/test2');

        $item = $app->data->make('test/test3', '3');
        $app->data->set($item);

        $snapshotBeforeBackup = $getDataSnapshot();

        $backupFileName = sys_get_temp_dir() . '/data-backup-test-' . uniqid() . '.zip';
        $keys = $app->dataBackup->backupAll($backupFileName);
        $this->assertTrue($keys === [
            'test/test2',
            'test/test3',
            'test1'
        ]);

        $this->assertTrue($getDataSnapshot() === $snapshotBeforeBackup);

        $list = $app->data->getList();
        foreach ($list as $item) {
            $app->data->delete($item->key);
        }

        $this->assertTrue($getDataSnapshot() === []);

        $app->dataBackup->restoreBackup($backupFileName);

        $this->assertTrue($getDataSnapshot() === $snapshotBeforeBackup);
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
