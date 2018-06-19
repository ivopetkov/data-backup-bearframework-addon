<?php

/*
 * Data backup addon for Bear Framework
 * https://github.com/ivopetkov/data-backup-bearframework-addon
 * Copyright (c) 2018 Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons;

use BearFramework\App;

/**
 * Data backup
 */
class DataBackup
{

    /**
     * 
     * @param string $backupFileName
     * @return array Returns a list of backed up keys
     * @throws \Exception
     */
    public function backupAll(string $backupFileName): array
    {
        $app = App::get();

        $dateStarted = new \DateTime();
        $list = $app->data->getList()
                ->filterBy('key', '.', 'notStartWith') // skip .temp, .recyclebin, etc.
                ->sliceProperties(['key']);
        if (is_file($backupFileName)) {
            throw new \Exception('The backup file ' . $backupFileName . ' already exists!');
        }
        $zip = new \ZipArchive();
        if ($zip->open($backupFileName, \ZipArchive::CREATE) === true) {
            $keysInArchive = [];
            $metadataInArchive = [];
            foreach ($list as $item) {
                $item = $app->data->get($item->key);
                if ($item !== null) {
                    $keysInArchive[] = $item->key;
                    $metadata = [];
                    if ($app->data->isPublic($item->key)) {
                        $metadata['public'] = '1';
                    }
                    $itemMetadata = $item->metadata->toArray();
                    if (!empty($itemMetadata)) {
                        $metadata['metadata'] = $itemMetadata;
                    }
                    $zip->addFromString('values/' . $item->key, $item->value);
                    if (!empty($metadata)) {
                        $zip->addFromString('metadata/' . $item->key, json_encode($metadata));
                        $metadataInArchive[] = $item->key;
                    }
                }
            }

            $dateCompleted = new \DateTime();
            $zip->addFromString('about', json_encode([
                'version' => '1',
                'dateStarted' => $dateStarted->format('c'),
                'dateCompleted' => $dateCompleted->format('c')
            ]));

            $zip->close();

            $zip = new \ZipArchive();
            if ($zip->open($backupFileName) === true) {
                $status = $zip->getStatusString();
                if ($status !== 'No error') {
                    throw new \Exception('Archive status: ' . $status);
                }
                foreach ($keysInArchive as $key) {
                    if ($zip->locateName('values/' . $key) === false) {
                        throw new \Exception('Cannot find values/' . $key . ' in the archive!');
                    }
                }
                foreach ($metadataInArchive as $key) {
                    if ($zip->locateName('metadata/' . $key) === false) {
                        throw new \Exception('Cannot find metadata/' . $key . ' in the archive!');
                    }
                }
                $zip->close();
                return $keysInArchive;
            } else {
                throw new \Exception('Cannot open zip file for validation (' . $backupFileName . ')');
            }
        } else {
            throw new \Exception('Cannot open zip file for writing (' . $backupFileName . ')');
        }
    }

}
