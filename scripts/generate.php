<?php

require_once 'constants.php';
require_once 'epub.php';

$epub = new epub;

$id = $argv[1];
$stage = 1;

$epub->copyMasterCSS($id);

$numberedFiles = $epub->getNumberedXhtmlFiles($id);
$allFiles = $epub->getAllFiles($id);

$sectionHierarchy = $epub->getSectionHierarchy($numberedFiles);
$pageNumbers = $epub->getPageNumbers($numberedFiles);

$bookDetails = $epub->getBookDetails();


echo ($epub->printTocXhtml($id, $sectionHierarchy, $bookDetails, $pageNumbers)) ? $stage++ . ". toc.xhtml written\n" : $stage++ . ". Error in writing toc.xhtml\n";
echo ($epub->printTocNcx($id, $sectionHierarchy, $bookDetails)) ? $stage++ . ". toc.ncx written\n" : $stage++ . ". Error in writing toc.ncx\n";
echo ($epub->printContentOpf($id, $sectionHierarchy, $bookDetails, $numberedFiles, $allFiles)) ? $stage++ . ". content.opf written\n" : $stage++ . ". Error in writing content.opf\n";

if(isset($bookDetails['books'][$id]['isbn'])){

	$isbn = $bookDetails['books'][$id]['isbn'];
	$status = $epub->createEPUBFile($id,$isbn);
}
else
	$status = $epub->createEPUBFile($id);

$epub->deleteCSSFromSrc($id);

echo ($status) ? $status . "\n": $stage++ . ". " . $id . ".epub file created in epub/ directory\n";

echo ($epub->validateEPUBFile($id,$isbn)) ? $stage++ . ". " . $id . ".epub is valid\n" : $stage++ . ". Error: epub is invalid. See error message.\n";

?>