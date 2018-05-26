<?php

require_once 'constants.php';
require_once 'jsonprecast.php';

$precast = new Jsonprecast();

$csvFiles = $precast->getCSVFiles();
$precast->generateBookDetailsFromCSV($csvFiles);

?>