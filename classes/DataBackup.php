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
     * @param array $options Available options: dataRepository
     * @return array Returns a list of backed up keys
     * @throws \Exception
     */
    public function backupAll(string $backupFileName, $options = []): array
    {
        $app = App::get();

        $dataRepository = isset($options['dataRepository']) ? $options['dataRepository'] : $app->data;
        if (!($dataRepository instanceof \BearFramework\App\DataRepository)) {
            throw new \Exception('Invalid data repository!');
        }

        $dateStarted = new \DateTime();
        $list = $dataRepository->getList()
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
                $item = $dataRepository->get($item->key);
                if ($item !== null) {
                    $keysInArchive[] = $item->key;
                    $metadata = [];
                    if ($dataRepository->isPublic($item->key)) {
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
                'dateCompleted' => $dateCompleted->format('c'),
                'valuesCount' => sizeof($keysInArchive)
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

    /**
     * 
     * @param string $backupFileName
     * @param array $options Available options: dataRepository
     * @throws \Exception
     */
    public function restoreBackup(string $backupFileName, $options = [])
    {
        $app = App::get();

        $dataRepository = isset($options['dataRepository']) ? $options['dataRepository'] : $app->data;
        if (!($dataRepository instanceof \BearFramework\App\DataRepository)) {
            throw new \Exception('Invalid data repository!');
        }

        if (!is_file($backupFileName)) {
            throw new \Exception('Backup file not found (' . $backupFileName . ')');
        }

        $zip = new \ZipArchive();
        if ($zip->open($backupFileName) === true) {
            $about = $zip->getFromName('about');
            if ($about === false) {
                throw new \Exception('Invalid backup file (' . $backupFileName . ')! The about file is missing!');
            }
            $about = json_decode($about, true);
            if (!is_array($about) || !isset($about['valuesCount'])) {
                throw new \Exception('Invalid backup file (' . $backupFileName . ')! The about file is not valid!');
            }
            $valuesCount = (int) $about['valuesCount'];
            $keysInArchive = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (strpos($filename, 'values/') === 0) {
                    $keysInArchive[] = substr($filename, 7);
                }
            }
            if (sizeof($keysInArchive) !== $valuesCount) {
                throw new \Exception('Invalid backup file (' . $backupFileName . ')! The values files count is not valid!');
            }
            $tempPrefix = '.temp/data-backup-restore-' . md5(uniqid()) . '/';
            $tempKeys = [];
            $cleanUpTempItems = function() use ($dataRepository, $tempPrefix, &$tempKeys) {
                foreach ($tempKeys as $tempKey) {
                    $dataRepository->delete($tempPrefix . $tempKey);
                }
            };
            foreach ($keysInArchive as $keyInArchive) {
                $content = $zip->getFromName('values/' . $keyInArchive);
                if ($content === false) {
                    $cleanUpTempItems();
                    throw new \Exception('Invalid backup file (' . $backupFileName . ')! The value for ' . $keyInArchive . ' is not valid!');
                }
                $metadata = $zip->getFromName('metadata/' . $keyInArchive);
                $metadata = $metadata === false ? [] : json_decode($metadata, true);
                $dataItem = $dataRepository->make($tempPrefix . $keyInArchive, $content);
                $makePublic = false;
                if (is_array($metadata)) {
                    if (isset($metadata['metadata'])) {
                        foreach ($metadata['metadata'] as $metadataKey => $metadataValue) {
                            $dataItem->metadata[$metadataKey] = $metadataValue;
                        }
                    }
                    $makePublic = isset($metadata['public']);
                }
                $dataRepository->set($dataItem);
                if ($makePublic) {
                    $dataRepository->makePublic($tempPrefix . $keyInArchive);
                }
                $tempKeys[] = $keyInArchive;
            }
            foreach ($tempKeys as $tempKey) {
                $dataRepository->rename($tempPrefix . $tempKey, $tempKey);
            }
            $zip->close();
        } else {
            throw new \Exception('Cannot open backup file (' . $backupFileName . ') as zip archive!');
        }
    }

}
