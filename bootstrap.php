<?php

require __DIR__ . '/vendor/autoload.php';

$moduleAutoload = __DIR__ . '/src/files/custom/Espo/Modules/CalendarFeed/vendor/autoload.php';

if (is_file($moduleAutoload)) {
    require $moduleAutoload;
}

$siteAutoload = __DIR__ . '/site/vendor/autoload.php';

if (is_file($siteAutoload)) {
    require $siteAutoload;
}
