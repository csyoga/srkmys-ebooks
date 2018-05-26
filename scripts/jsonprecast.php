<?php

class Jsonprecast {

	public $publisher = 'Ramakrishna Math, Nagpur';

	public function __construct() {
		
	}

	public function getCSVFiles() {

		$allFiles = [];
		
	    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT_DIRECTORY . 'scripts'));

	    foreach($iterator as $file => $object) {
	    	
	    	if(preg_match('/.*\.csv$/',$file)) array_push($allFiles, $file);
	    }

	    sort($allFiles);

		return $allFiles;
	}

	public function generateBookDetailsFromCSV($csvFiles){

		$allBooksDetails['books'] = [];
		$bookDetails = "";

		$jsonFilePath = JSON_PRECAST . 'book-details.json';

		foreach ($csvFiles as $csvFile) {

			$fileContents = file_get_contents($csvFile);

			$lines = preg_split("/\n/", $fileContents);
			array_shift($lines);

			foreach ($lines as $line) {
								
				$fields = explode('|', $line);
				$fields = array_filter($fields);

				if(empty($fields)) continue;

				$bookCode = $fields[1];

				$bookDetails[$bookCode]["language"] = (preg_match('/^H/', $fields[1]))? 'hi' : 'mr';
				$bookDetails[$bookCode]["identifier"] = "Nagpur_eBooks/" . $bookCode;
				$bookDetails[$bookCode]["isbn"] = (isset($fields[2]))? trim($fields[2]) : '';
				$bookDetails[$bookCode]["title"] = (isset($fields[4]))? trim($fields[4]) : '';				

				$bookDetails[$bookCode]["creators"]["author"] = (isset($fields[7]))? trim($fields[7]) : '';
				$bookDetails[$bookCode]["creators"]["translator"] = (isset($fields[12]))? trim($fields[12]) : '';
				$bookDetails[$bookCode]["creators"]["compiler"] = (isset($fields[13]))? trim($fields[13]) : '';				
				$bookDetails[$bookCode]["creators"] = array_filter($bookDetails[$bookCode]["creators"]);
	
				if(empty($bookDetails[$bookCode]["creators"]))
					unset($bookDetails[$bookCode]["creators"]);

				$bookDetails[$bookCode]["category"] = (isset($fields[6]))? trim($fields[6]) : '';
				$bookDetails[$bookCode]["publisher"] = $this->publisher;
				$bookDetails[$bookCode]["pages"] = (isset($fields[8]))? trim($fields[8]) : '';

				$bookDetails[$bookCode]["description"] = (isset($fields[10]))? trim($fields[10]) : '';
				

				// $bookDetails[$bookCode]["price_p"] = (isset($fields[9]))? trim($fields[9]) : '';
				// $bookDetails[$bookCode]["price_e"] = (isset($fields[10]))? trim($fields[10]) : '';
				
				//~ $bookDetails[$bookCode] = array_filter($bookDetails[$bookCode]);
				$bookDetails[$bookCode] = $bookDetails[$bookCode];

			}

		}

		$allBooksDetails['books'] = $bookDetails;
		$jsonData = json_encode($allBooksDetails,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if($jsonData) file_put_contents($jsonFilePath, $jsonData);
	}
}

?>
