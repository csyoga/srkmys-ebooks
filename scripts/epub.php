<?php

class epub {

	public function __construct() {
		
	}

	public function createEPUBFile($id,$isbn='') {

		$bookName = ($isbn != '')? $isbn : $id;

		$out = '';
		$out .= exec('mkdir -p ' . $id);
		$out .= exec('cp -r ' . UNICODE_SRC . $id . ' ' . $id . '/OEBPS');
		$out .= exec('cp -r files/META-INF ' . $id . '/.');
		$out .= exec('cp files/mimetype ' . $id . '/.');
		
		$out .= exec('cd ' . $id . '; zip -Xq ' . $bookName . '.epub mimetype; zip -rgq ' . $bookName . '.epub META-INF; zip -rgq ' . $bookName . '.epub OEBPS/;');
		$out .= exec('cp ' . $id . '/' . $bookName . '.epub ' . EPUB_FILES . '/.');
		$out .= exec('cp ' . UNICODE_SRC . $id . '/images/cover.jpg ' . EPUB_FILES . '/' . $bookName . '_frontcover.jpg');
		$out .= exec('rm -fr ' . $id);

		return $out;
	}

	public function validateEPUBFile($id,$isbn='') {

		$bookName = ($isbn != '')? $isbn : $id;

		$status = exec('java -jar epubcheck-4.0.2/epubcheck.jar ' . EPUB_FILES . '/' . $bookName . '.epub', $output, $returnVar);

		return (($returnVar == 0) && (in_array('No errors or warnings detected.', $output)));
	}

	public function copyMasterCSS($id) {

		exec('cp -r files/css ' . UNICODE_SRC . $id . '/.');
	}

	public function deleteCSSFromSrc($id) {
	
		exec('rm -fr ' . UNICODE_SRC . $id . '/css');
	}

	public function getNumberedXhtmlFiles($id) {

		$numberedFiles = [];
		$files = glob(UNICODE_SRC . $id . '/*.xhtml');
		
		foreach ($files as $file) {
			
			if(preg_match('/^[0-9]+[a-z]*\-.*\.xhtml/', str_replace(UNICODE_SRC . $id . '/', '', $file))) array_push($numberedFiles, $file);
		}
		return $numberedFiles;
	}
	
	public function getAllFiles($id) {

		$allFiles = [];
		
	    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(UNICODE_SRC . $id));
	    $fileObject = [];
	    $mimetypes = $this->getMimetypes();


	    foreach($iterator as $file => $object) {
	    	
	    	// Excluding opf file here
	    	if(!(preg_match('/\.$|\.opf/', $file))) {

	    		$fileObject['filePath'] = $file;
	    		$fileObject['fileName'] = str_replace(UNICODE_SRC . $id . '/', '', $fileObject['filePath']);

	    		$fileObject['extension'] = preg_replace('/.*\.(.*)/', "$1", $fileObject['fileName']);
	    		$fileObject['mimetype'] = $mimetypes['mimetypes']{$fileObject['extension']};

				array_push($allFiles, $fileObject);
	    	}
	    }

	    sort($allFiles);

		return $allFiles;
	}

	public function getSectionHierarchy($files) {

		$hierarchy = [];
		foreach ($files as $file) {

			$xml = simplexml_load_file($file);

			$xml->registerXPathNamespace('path', XHTML_NS);
			$sections = $xml->xpath("//path:section");

			foreach ($sections as $section) {

				$sectionHierarchy['file'] = preg_replace('/.*\/(.*)/', "$1", $file);
				$sectionHierarchy['class'] = (string) $section->attributes()['class'];
				$sectionHierarchy['id'] = (string) $section->attributes()['id'];
				$sectionHierarchy['level'] = preg_replace('/level(\d+).*/', "$1", $sectionHierarchy['class']);

				$section->registerXPathNamespace('path', XHTML_NS);
				$xpathURL = 'child::path:h' . $sectionHierarchy['level'] . '[contains(@class, \'level' . $sectionHierarchy['level'] . '-title\')]';
				
				if(!(isset($section->xpath($xpathURL)[0]))) continue;
				
				$titleBlock = $section->xpath($xpathURL)[0];
				$title = $titleBlock->asXML();
				$sectionHierarchy['title'] = preg_replace('/<span class="index">.*?<\/span>/', '', $title);
				$sectionHierarchy['title'] = preg_replace('/<sup>.*?<\/sup>/u', '', $sectionHierarchy['title']);
				$sectionHierarchy['title'] = preg_replace('/<span class="num">([०१२३४५६७८९0-9]+)\.<\/span>/u', "$1. ", $sectionHierarchy['title']);
				$sectionHierarchy['title'] = preg_replace('/<span class="num">([०१२३४५६७८९0-9]+)<\/span>/u', "$1 ", $sectionHierarchy['title']);
				$sectionHierarchy['title'] = preg_replace('/<span class="num">(.*?)<\/span>/u', "$1 – ", $sectionHierarchy['title']);
				$sectionHierarchy['title'] = strip_tags($sectionHierarchy['title']);
				$hierarchy[] = $sectionHierarchy;				
			}
		}

		return $hierarchy;
	}

	public function getPageNumbers($files) {

		$pageNumbers = [];
		foreach ($files as $file) {

			$xml = simplexml_load_file($file);

			$xml->registerXPathNamespace('path', XHTML_NS);
			$pages = $xml->xpath("//path:span[contains(@role, 'doc-pagebreak')]");

			foreach ($pages as $page) {

				$pageNumber['file'] = preg_replace('/.*\/(.*)/', "$1", $file);

				$pageNumber['id'] = (string) $page->attributes()['id'];
				$pageNumber['title'] = (string) $page->attributes()['title'];

				$pageNumbers[] = $pageNumber;
			}
		}

		return $pageNumbers;
	}

	public function getBookDetails() {

        $details = json_decode(file_get_contents(JSON_PRECAST . 'book-details.json'), true);
        return $details;
	}

	public function getMimetypes() {

        $details = json_decode(file_get_contents(JSON_PRECAST . 'mimetypes.json'), true);
        return $details;
	}

	public function getMarcRelators() {

        $details = json_decode(file_get_contents(JSON_PRECAST . 'marc-relators.json'), true);
        return $details;
	}

	public function printTocXhtml($id, $sectionHierarchy, $bookDetails, $pageNumbers) {

		$template['bookTitle'] = (isset($bookDetails['books']{$id}['title'])) ? $bookDetails['books']{$id}['title'] : DEFAULT_TITLE;

		$prevLevel = 0;
		$template['structure'] = '';
		foreach ($sectionHierarchy as $section) {
			
			if($prevLevel < $section['level']){

				$template['structure'] .= "\n" . '<ol>';
				$template['structure'] .= "\n" . '<li><a href="' . $section['file'] . '#' . $section['id'] . '">' . $section['title'] . '</a>';
			}
			elseif($prevLevel == $section['level']){

				$template['structure'] .= '</li>' . "\n" . '<li><a href="' . $section['file'] . '#' . $section['id'] . '">' . $section['title'] . '</a>';
			}
			elseif($prevLevel > $section['level']){
				
				$diffLevel = $prevLevel - $section['level'];
				while($diffLevel--) $template['structure'] .= "</li>\n</ol>" . "\n";

				$template['structure'] .= "</li>" . "\n" . '<li><a href="' . $section['file'] . '#' . $section['id'] . '">' . $section['title'] . '</a>';
			}

			$prevLevel = $section['level'];
		}
		while($prevLevel--) $template['structure'] .= "</li>\n</ol>" . "\n";

		$template['pageNumbers'] = '';
		if($pageNumbers) {
		
			$template['pageNumbers'] .= '<nav epub:type="page-list">' . "\n\t" . '<ol>';
			foreach ($pageNumbers as $pageNumber) {
				
				$template['pageNumbers'] .= "\n\t\t" . '<li><a href="' . $pageNumber['file'] . '#' . $pageNumber['id'] . '">' . $pageNumber['title'] . '</a></li>';
			}
			$template['pageNumbers'] .= "\n\t" . '</ol>' . "\n" . '</nav>';
		}
	
		$html = file_get_contents('templates/toc-xhtml-template.xhtml');

		foreach ($template as $key => $value) {

			$html = str_replace('{{ ' . $key . ' }}', $value, $html);
		}

		$html = $this->prettyPrintHTML($html);

		return file_put_contents(UNICODE_SRC . $id . '/toc.xhtml', $html);
	}

	public function printTocNcx($id, $sectionHierarchy, $bookDetails) {

		$template['bookIdentifier'] = (isset($bookDetails['books']{$id}['isbn'])) ? $bookDetails['books']{$id}['isbn'] : DEFAULT_TITLE;
		$template['bookTitle'] = (isset($bookDetails['books']{$id}['title'])) ? $bookDetails['books']{$id}['title'] : DEFAULT_TITLE;

		$prevLevel = 0;
		$navId = 1;
		$template['structure'] = '';
		foreach ($sectionHierarchy as $section) {
			
			if($prevLevel < $section['level']){

				$template['structure'] .= "\n" . '<navPoint id="navPoint-' . $navId . '" playOrder="' . $navId . '">' . "\n" . '<navLabel><text>' . $section['title'] . '</text></navLabel><content src="' . $section['file'] . '#' . $section['id'] . '"/>';
				$navId++;
			}
			elseif($prevLevel == $section['level']){

				$template['structure'] .= "\n" . '</navPoint>' . "\n" . '<navPoint id="navPoint-' . $navId . '" playOrder="' . $navId . '">' . "\n" . '<navLabel><text>' . $section['title'] . '</text></navLabel><content src="' . $section['file'] . '#' . $section['id'] . '"/>';
				$navId++;
			}
			elseif($prevLevel > $section['level']){
				
				$diffLevel = $prevLevel - $section['level'];
				while($diffLevel--) $template['structure'] .= "\n" . "</navPoint>";

				$template['structure'] .= "\n" . '</navPoint>' . "\n" . '<navPoint id="navPoint-' . $navId . '" playOrder="' . $navId . '">' . "\n" . '<navLabel><text>' . $section['title'] . '</text></navLabel><content src="' . $section['file'] . '#' . $section['id'] . '"/>';
				$navId++;
			}

			$prevLevel = $section['level'];
		}
		while($prevLevel--) $template['structure'] .= "\n" . "</navPoint>";
	
		$html = file_get_contents('templates/toc-ncx-template.xhtml');

		foreach ($template as $key => $value) {

			$html = str_replace('{{ ' . $key . ' }}', $value, $html);
		}

		$html = $this->prettyPrintHTML($html);

		return file_put_contents(UNICODE_SRC . $id . '/toc.ncx', $html);
	}

	public function printContentOpf($id, $sectionHierarchy, $bookDetails, $numberedFiles, $allFiles) {

		$template = $bookDetails['books']{$id};
		
		date_default_timezone_set('Asia/Kolkata');
		$template['dateModified'] = date('Y-m-d') . 'T' . date('G:i:s') . 'Z';
	
		if(isset($template['creators'])) $template['creators'] = $this->formCreatorMetadata($template['creators']);

		$fileListing = '';
		$spine = '';
		foreach ($allFiles as $file) {
			
			$fileId = 'id-' . preg_replace('/\/|\./', "_", $file['fileName']);
	    	$fileListing .= "\t\t" . '<item ';

	    	// Include nav property only for toc.xhtml and cover.jpg
	    	if($file['fileName'] == 'toc.xhtml') $fileListing .= 'properties="nav" ';
	    	if(preg_match('/cover\.jpg$/', $file['fileName'])) $fileListing .= 'properties="cover-image" ';
	    	
	    	$fileListing .= 'id="' . $fileId . '" href="' . $file['fileName'] . '" media-type="' . $file['mimetype'] . '" />' . "\n";

	    	if(preg_match('/^\d+/', $file['fileName'])) {

				$spine .= "\t\t" . '<itemref idref="' . $fileId . '" linear="yes" />' . "\n";
	    	}
		}
		$template['fileListing'] = $fileListing;
		$template['spine'] = $spine;

		$html = file_get_contents('templates/content-opf-template.xhtml');

		foreach ($template as $key => $value) {

			$html = str_replace('{{ ' . $key . ' }}', $value, $html);
		}

		$html = $this->sanitizeHTML($html);
		$html = $this->prettyPrintHTML($html);

		return file_put_contents(UNICODE_SRC . $id . '/content.opf', $html);
	}

	public function prettyPrintHTML($html) {

		return $html;
	}

	public function sanitizeHTML($html) {

		// Remove empty elements
		$html = preg_replace('/.*><\/.*\n/', '', $html);
		// Remove unused template variables
		$html = preg_replace('/.*\{\{.*\}\}.*\n/', '', $html);

		return $html;
	}

	public function formCreatorMetadata($creators) {

		$creators = array_merge($creators, ["bookDesigner" => "Sriranga Digital Software Technologies Private Limited"]);
		$creatorString = '';

	    $marcRelators = $this->getMarcRelators();

		foreach ($creators as $type => $creator) {
			
			$names = explode(';', $creator);

			$index = 1;
			foreach ($names as $name) {

				$creatorString .= '
		<dc:creator id="' . $type . $index . '">' . $name . '</dc:creator>
		<meta refines="#' . $type . $index . '" property="role" scheme="marc:relators">' . $marcRelators['marcRelators']{$type} . '</meta>    
				';
				$index++;
			}
		}
		return $creatorString;
	}
}

?>
