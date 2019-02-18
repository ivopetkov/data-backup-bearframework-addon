<?php

/*
 * Data backup addon for Bear Framework
 * https://github.com/ivopetkov/data-backup-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

use BearFramework\App;

$app = App::get();
$context = $app->contexts->get(__FILE__);

$context->classes
        ->add('IvoPetkov\BearFrameworkAddons\DataBackup', 'classes/DataBackup.php');

$app->shortcuts
        ->add('dataBackup', function() {
            return new \IvoPetkov\BearFrameworkAddons\DataBackup();
        });
