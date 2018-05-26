<?php

class Stages{

	public function __construct() {
		
	}

	public function processFiles($bookID) {

		$allFiles = $this->getAllFiles($bookID);

		foreach($allFiles as $file){
			
			$this->process($bookID,$file);		
		}
	}

	public function getAllFiles($bookID) {

		$allFiles = [];
		
		$folderPath = RAW_SRC . $bookID . '/Stage1/';
		
	    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folderPath));

	    foreach($iterator as $file => $object) {
	    	
	    	if(preg_match('/.*\.VEN$/i',$file)) array_push($allFiles, $file);
	    }

	    sort($allFiles);

		return $allFiles;
	}

	public function process($bookID,$file) {

		// stage1.ven : Input text from ventura
		$rawVEN = file_get_contents($file);

		// Get ANSI text
		$rawVEN = $this->ventura2Text($rawVEN);

		// Form html from ventura tags
		$html = $this->formHTML($rawVEN);
		$html = $this->cleanupInlineElements($html);

		// stage2.html : Output html for conversion		
		$baseFileName = basename($file);

		if (!file_exists(RAW_SRC . $bookID . '/Stage2/')) {
			
			mkdir(RAW_SRC . $bookID . '/Stage2/', 0775);
			echo "Stage2 directory created\n";
		}

		$fileName = RAW_SRC . $bookID . '/Stage2/' . preg_replace('/\.ven$/i', '.html', $baseFileName);

		// $processedHTML = html_entity_decode($processedHTML, ENT_QUOTES);
		file_put_contents($fileName, $html);


		// Stage 3
		$unicodeHTML = $this->convert($html);

		// stage3.html : Output Unicode html with tags, english retained as it is
		if (!file_exists(RAW_SRC . $bookID . '/Stage3/')) {
			
			mkdir(RAW_SRC . $bookID . '/Stage3/', 0775);
			echo "Stage3 directory created\n";
		}

		$fileName = RAW_SRC . $bookID . '/Stage3/' . preg_replace('/\.ven$/i', '.html', $baseFileName);

		$unicodeHTML = html_entity_decode($unicodeHTML);
		
		file_put_contents($fileName, $unicodeHTML);
	}

	public function ventura2Text ($text) {

		$text = preg_replace_callback('/<(\d+)>/',
			function($matches) {

				return chr(intval($matches[1]) + 32);
			},
			$text);

		$text = str_replace('<<', '<', $text);
		$text = str_replace('>>', '>', $text);

		$text = mb_convert_encoding($text, 'UTF-8');

		$text = str_replace("\r\n", "\n", $text);
		return $text;
	}

	public function formHTML ($text) {

		$html = "<html>\n" . $this->sanitizeText($text) . "\n</html>\n";

		$html = preg_replace('/@CHAPTERNOS = (.*)/', "<h1>$1</h1>", $html);
		$html = preg_replace('/@CHAPTERHEADNG = (.*)/', "<h1>$1</h1>", $html);
		$html = preg_replace('/@FIRSTPARA = (.*)/', '<p class="noindent">' . "$1" . '</p>', $html);
		$html = preg_replace("/@[A-Z\.0-9#]+ = (.*)/", "<div>$1</div>", $html);
		$html = preg_replace("/\n([^<\n][^\n]*)/", "\n<p>$1</p>", $html);
		
		// $html = preg_replace("/(&lt;[A-Z\.0-9]*MI&gt;.*?&lt;D+&gt;)/", "<em>$1</em>", $html);
		// $html = preg_replace("/&lt;[A-Z\.0-9]*B&gt;(.*?)&lt;[A-Z\.0-9]*D&gt;/", "<strong>$1</strong>", $html);
		
		$html = preg_replace_callback("/&lt;F[A-Z\.0-9]+&gt;(.*?)&lt;F[A-Z\.0-9]+&gt;/",
			function($matches){

				return $this->handlePunctuations($matches[1]);
			}, $html);

		$html = preg_replace_callback("/&lt;F[A-Z\.0-9]+&gt;(.*?)(<\/)/",
			function($matches){

				return $this->handlePunctuations($matches[1]) . $matches[2];
			}, $html);

		$html = str_replace("&lt;MI&gt;", "", $html);
		$html = str_replace("&lt;D&gt;", "", $html);

		// removing entries like <P123>
		$html = preg_replace("/&lt;P[A-Z\.0-9]+?&gt;/", "", $html);
		$html = preg_replace("/&lt;M[A-Z\.0-9]+?&gt;/", "", $html);
		$html = preg_replace("/&lt;D[A-Z\.0-9]+?&gt;/", "", $html);
		$html = preg_replace("/&lt;R&gt;/", "<br />", $html);
		$html = preg_replace("/&lt;M&gt;/", "", $html);

		return $html;
	}

	public function handlePunctuations($text) {

		$punctuations = '[[:punct:]\h‘’“”–—BCD।॥]';

		$text = str_replace('`', '‘', $text);
		$text = str_replace('\'', '’', $text);
		$text = str_replace('É', '“', $text);
		$text = str_replace('Ê', '”', $text);
		$text = str_replace('å', '–', $text);
		$text = str_replace('ä', '—', $text);
		$text = str_replace('|', '।', $text);
		$text = str_replace('।।', '॥', $text);

		$text = preg_replace('/^(' . $punctuations . '*)D(' . $punctuations . '*)$/', "$1–$2", $text);
		$text = preg_replace('/^(' . $punctuations . '*)B(' . $punctuations . '*)$/', "$1“$2", $text);
		$text = preg_replace('/^(' . $punctuations . '*)C(' . $punctuations . '*)$/', "$1”$2", $text);

		$text = '<span class="en">' . preg_replace('/(.*)(<em>|<\/em>)(.*)/', "$2$1$3", $text) . '</span>';
		$text = preg_replace('/(<span class="en">)(<em>|<\/em>)/', "$2$1", $text);
		
		return $text;
	}

	public function sanitizeText($text) {

		return htmlspecialchars($text);
	}

	public function cleanupInlineElements($html) {

		// $html = preg_replace('/<span class="en">([^<\/span>]*?)<br \/>(.*?)<\/span>/', "<span class=\"en\">$1</span><br /><span class=\"en\">$2</span>", $html);
		$html = preg_replace('/<span class="en">([[:punct:]\hefi]+)<strong>/', "<strong><span class=\"en\">$1", $html);
		
		$html = preg_replace('/<span class="en">([^<\/span>]*?)<\/strong>/', "</strong><span class=\"en\">$1", $html);
		
		// $html = str_replace('<p></p>', '', $html);
		// $html = str_replace('<span class="en"></span>', '', $html);
		// $html = str_replace('<strong><strong>', '<strong>', $html);
		// $html = str_replace('</strong></strong>', '</strong>', $html);

		return $html;
	}

	public function convert ($html) {

		$dom = new DOMDocument("1.0");
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;

		$html = $this->normalizePraja($html);

		$dom->loadXML($html);
		$xpath = new DOMXpath($dom);

		foreach($xpath->query('//text()') as $text_node) {

			if(preg_replace('/\s+/', '', $text_node->nodeValue) === '') continue; 

			if($text_node->parentNode->hasAttribute('class'))
				if($text_node->parentNode->getAttribute('class') == 'en')
					 continue;

			$text_node->nodeValue = $this->praja2Unicode($text_node->nodeValue);
		}

		$html = html_entity_decode($dom->saveXML());
		$html = preg_replace('/<span class="en">([[:punct:]\h‘’“”–—।॥]*?)<\/span>/', "$1", $html);
		return $html;
	}

	public function praja2Unicode ($text) {

		// Initial parse

		$text = str_replace('"j', 'j"', $text);

		// ya group
		$text = str_replace('O"', 'ಯ', $text);
		$text = str_replace('O{', 'ಯ', $text);
		$text = str_replace('Ò"', 'ಯೆ', $text);
		$text = str_replace('Òr', 'ಯೊ', $text);
		$text = str_replace('h"', 'ಯಿ', $text);

		// ma group
		$text = str_replace('Aj"', 'ಮ', $text);
		$text = str_replace('Aj{', 'ಮ', $text);
		$text = str_replace('Au"', 'ಮೆ', $text);
		$text = str_replace('Aur', 'ಮೊ', $text);
		$text = str_replace('a"', 'ಮಿ', $text);
		
		// jjha group
		$text = str_replace('Újs"', 'ಝ', $text);
		$text = str_replace('Úus"', 'ಝೆ', $text);
		$text = str_replace('Úusr', 'ಝೊ', $text);
		$text = str_replace('Ys"', 'ಝಿ', $text);

		// swara

		// Vyanjana
		$text = str_replace('ül', 'ಛ್', $text);
		$text = str_replace('Vl', 'ಛಿ', $text);

		$text = str_replace('Úm', 'ಠ್', $text);
		$text = str_replace('Ym', 'ಠಿ', $text);

		$text = str_replace('Ûk', 'ಢ್', $text);
		$text = str_replace('Zk', 'ಢಿ', $text);

		$text = str_replace(';nk', 'ಥ್', $text);
		$text = str_replace(']nk', 'ಥಿ', $text);

		$text = str_replace(';k', 'ಧ್', $text);
		$text = str_replace(']k', 'ಧಿ', $text);
		
		$text = str_replace('=k', 'ಫ್', $text);
		$text = str_replace('_k', 'ಫಿ', $text);

		$text = str_replace('æl', 'ಭ್', $text);
		$text = str_replace('vl', 'ಭಿ', $text);
		$text = str_replace('`l', 'ಭಿ', $text);

		$text = str_replace('Š‡', '್ಧ', $text);
		$text = str_replace('†‡', '್ಢ', $text);
		$text = str_replace('’‡', '್ಭ', $text);

		// Lookup ---------------------------------------------
		$text = str_replace('!', 'ಅ', $text);
		$text = str_replace('"', 'ು', $text);
		$text = str_replace('#', 'ಇ', $text);
		$text = str_replace('$', 'ಈ', $text);
		$text = str_replace('%', 'ಉ', $text);
		$text = str_replace('&', 'ಊ', $text);
		$text = str_replace("'", 'ಪ್', $text);
		$text = str_replace('(', 'ಎ', $text);
		$text = str_replace(')', 'ಏ', $text);
		$text = str_replace('*', 'ಐ', $text);
		$text = str_replace('+', 'ಒ', $text);
		// $text = str_replace(',', '್ಛ', $text);
		$text = str_replace('', '್ಛ', $text);
		//~ $text = str_replace('-', '', $text);
		$text = str_replace('', '್ಝ', $text);
		$text = str_replace('/', 'ಃ', $text);
		$text = str_replace('0', 'ಂ', $text);
		// $text = str_replace('1', '೧', $text);
		// $text = str_replace('2', '೨', $text);
		// $text = str_replace('3', '೩', $text);
		// $text = str_replace('4', '೪', $text);
		// $text = str_replace('5', '೫', $text);
		// $text = str_replace('6', '೬', $text);
		// $text = str_replace('7', '೭', $text);
		// $text = str_replace('8', '೮', $text);
		// $text = str_replace('9', '೯', $text);
		$text = str_replace(':', 'ತ್', $text);
		$text = str_replace(';', 'ದ್', $text);
		$text = str_replace('<', 'ನ್', $text);
		$text = str_replace('=', 'ಪ್', $text);
		$text = str_replace('>', 'ì', $text); //?
		$text = str_replace('?', 'ಲ್', $text);
		$text = str_replace('@', 'ಣಿ', $text);
		$text = str_replace('A', 'ವ್', $text);
		$text = str_replace('B', 'ಶ್', $text);
		$text = str_replace('C', 'ಷ್', $text);
		$text = str_replace('D', 'ಸ್', $text);
		$text = str_replace('E', 'ಹ್', $text);
		$text = str_replace('F', 'ಜ್ಞ್', $text);
		$text = str_replace('G', 'ಖ', $text);
		$text = str_replace('H', 'ಙ', $text);
		$text = str_replace('I', 'ಜ', $text);
		$text = str_replace('J', 'ಞ', $text);
		$text = str_replace('K', 'ಟ', $text);
		$text = str_replace('L', 'ಣ', $text);
		$text = str_replace('M', 'ಋ', $text);// ?
		$text = str_replace('N', 'ಬ', $text);
		$text = str_replace('O', 'ಯ್', $text);
		$text = str_replace('P', 'ಲ', $text);
		$text = str_replace('Q', 'ಜ್ಞ', $text);
		$text = str_replace('R', 'ಕಿ', $text);
		$text = str_replace('S', 'ಖಿ', $text);
		$text = str_replace('T', 'ಗಿ', $text);
		$text = str_replace('U', 'ಚಿ', $text);
		// $text = str_replace('V', 'ಛಿ', $text); //?
		$text = str_replace('W', 'ಜಿ', $text);
		$text = str_replace('X', 'ಟಿ', $text);
		$text = str_replace('Y', 'ರಿ', $text);
		$text = str_replace('Z', 'ಡಿ', $text);
		//~ $text = str_replace('[', '', $text);
		$text = str_replace("\\", 'ತಿ', $text);
		$text = str_replace(']', 'ದಿ', $text);
		$text = str_replace('^', 'ನಿ', $text);
		$text = str_replace('_', 'ಪಿ', $text);
		$text = str_replace('`', 'v', $text);
		$text = str_replace('a', 'ವಿ', $text);
		$text = str_replace('b', 'ಲಿ', $text);
		$text = str_replace('c', 'ಷಿ', $text);
		$text = str_replace('d', 'ಸಿ', $text);
		$text = str_replace('e', 'ಹಿ', $text);
		$text = str_replace('f', 'ಳಿ', $text);
		$text = str_replace('g', 'ಜ್ಞಿ', $text);
		$text = str_replace('h', 'ಯಿ', $text); //?
		$text = str_replace('i', 'ಶಿ', $text);
		$text = str_replace('j', 'ಅ', $text);
		$text = str_replace('k', 'k', $text); //?
		//~ $text = str_replace('l', '', $text); //?
		//~ $text = str_replace('m', '', $text); //?
		//~ $text = str_replace('n', '', $text); //?
		$text = str_replace('o', 'ಾ', $text);
		$text = str_replace('p', 'ಿ', $text);
		$text = str_replace('q', 'ಆ', $text);
		$text = str_replace('r', 'ೂ', $text);
		//~ $text = str_replace('s', '', $text); //?
		$text = str_replace('t', 't', $text);
		$text = str_replace('u', 'ೆ', $text);
		$text = str_replace('v', 'ಬಿ', $text);
		$text = str_replace('w', 'ೆ', $text);
		$text = str_replace('x', '್ಕ', $text);
		$text = str_replace('y', 'ೄ', $text);
		$text = str_replace('z', 'ೌ', $text);
		//~ $text = str_replace('{', '', $text); //?
		//~ $text = str_replace('|', '', $text); //?
		$text = str_replace('}', '್', $text);
		$text = str_replace('~', 'R', $text);
		$text = str_replace('¡', '್ಖ', $text);
		$text = str_replace('¢', '್ಗ', $text);
		$text = str_replace('£', '್ಘ', $text);
		$text = str_replace('¤', '್ಙ', $text);
		$text = str_replace('¥', '್ಚ', $text);
		//~ $text = str_replace('¦', '', $text);
		$text = str_replace('§', '್ಜ', $text);
		$text = str_replace('©', '್ಞ', $text);
		$text = str_replace('ª', '್ಟ', $text);
		$text = str_replace('«', '್ಠ', $text);
		$text = str_replace('®', '್ಣ', $text);
		$text = str_replace('°', '್ಥ', $text);
		$text = str_replace('±', '್ಱ', $text);
		$text = str_replace('²', '್ೞ', $text);
		$text = str_replace('³', 'ೞ', $text);
		$text = str_replace('´', 'ಱ', $text);
		//~ $text = str_replace('µ', '', $text);
		$text = str_replace('¶', '್ಮ', $text);
		//~ $text = str_replace('·', '', $text);
		$text = str_replace('¸', '್ತ್ರ', $text);
		//~ $text = str_replace('¹', '', $text);
		$text = str_replace('º', '್ವ', $text);
		
		$text = str_replace('»', '್ಶ', $text);
		$text = str_replace('¿', '್ಳ', $text);
		$text = str_replace('À', '್ಹ', $text);
		$text = str_replace('Á', 'ॐ', $text);
		$text = str_replace('Â', '್ಕೃ', $text);
		$text = str_replace('Ã', '್ಬೈ', $text);
		$text = str_replace('Ä', '್ಟ್ರ', $text);
		$text = str_replace('Å', '್ತೃ', $text);
		$text = str_replace('Æ', '್ತೈ', $text);
		$text = str_replace('Ç', '್ಯ', $text);
		$text = str_replace('È', '್ರ', $text);
		$text = str_replace('É', '್ಪ್ರ', $text);
		$text = str_replace('Ê', '್ರೈ', $text);
		$text = str_replace('Ë', '್ಸ್ರ', $text);
		$text = str_replace('Ì', '್ಕ್ಷ', $text);
		$text = str_replace('Í', '್ಕ್ರ', $text);
		$text = str_replace('Î', 'ೆ', $text);
		//~ $text = str_replace('Ï', '', $text); //?
		$text = str_replace('Ñ', 'ೂ', $text);
		$text = str_replace('Ò', 'ಯೆ', $text); //?
		$text = str_replace('Ó', 'ಕ್', $text);
		$text = str_replace('Ô', 'ಗ್', $text);
		$text = str_replace('Õ', 'ಘ್', $text);
		$text = str_replace('Ö', 'ಚ್', $text);
		$text = str_replace('Ø', 'ಜ್', $text);
		$text = str_replace('Ù', 'ಟ್', $text);
		$text = str_replace('Ú', 'ರ್', $text);
		$text = str_replace('Û', 'ಡ್', $text);
		$text = str_replace('Ü', 'ಣ್', $text);
		$text = str_replace('ß', '', $text);
		$text = str_replace('à', 'ಂ', $text);
		$text = str_replace('á', 'ಶ್ರೀ', $text);
		$text = str_replace('â', 'ೃ', $text);
		$text = str_replace('ã', 'ೈ', $text);
		$text = str_replace('ä', ',', $text);
		$text = str_replace('å', '', $text); // handled separately inside span class english
		$text = str_replace('æ', 'ಬ್', $text);
		$text = str_replace('ç', 'ನ್', $text);
		$text = str_replace('è', 'ಳ್', $text);
		$text = str_replace('é', '್ತ್ರ', $text);
		$text = str_replace('ê', '್ತ್ಯ', $text);
		$text = str_replace('ë', '್ಷ', $text);
		//~ $text = str_replace('ì', '', $text); //?
		$text = str_replace('í', 'ಫ್', $text);
		$text = str_replace('î', 'ಖ್', $text);
		$text = str_replace('ò', 'ಔ', $text);
		$text = str_replace('ô', '', $text);
		$text = str_replace('ö', 'ಘಿ', $text);
		$text = str_replace('ø', 'ಓ', $text);
		$text = str_replace('ù', 'ಕ', $text);
		$text = str_replace('ú', 'ಕೆ', $text);
		$text = str_replace('û', 'ು', $text);
		$text = str_replace('ü', 'ಛ್ ', $text);
		$text = str_replace('ÿ', 'ಌ', $text);
		$text = str_replace('Œ', '್ಪ', $text);
		$text = str_replace('œ', '್ಸ', $text);
		$text = str_replace('Š', '್ದ', $text);
		$text = str_replace('š', '್ಲ', $text);
		$text = str_replace('–', 'ೞ', $text);
		$text = str_replace('—', 'ಱ', $text);
		$text = str_replace('‘', '್ಫ', $text);
		$text = str_replace('’', '್ಬ', $text);
		$text = str_replace('“', '್ಱ', $text);
		$text = str_replace('”', '್ೞ', $text);
		$text = str_replace('†', '್ಡ', $text);
		//~ $text = str_replace('‡', '', $text); //?
		$text = str_replace('‰', '್ತ', $text);
		$text = str_replace('‹', '್ನ', $text);
		$text = str_replace('›', '್ಷ', $text);
		$text = str_replace('™', '', $text);
		$text = str_replace('•', '್ತ್ಯ', $text);


		// Special cases

		// remove ottu spacer
		$text = str_replace('õ', '', $text);
		$text = str_replace('ï', '', $text);
		$text = str_replace('ñ', '', $text);
		$text = str_replace('ð', '', $text);

		// Swara
		$text = preg_replace('/್[ಅ]/u', '', $text);
		$text = preg_replace('/್([ಾಿೀುೂೃೄೆೇೈೊೋೌ್])/u', "$1", $text);
		
		// vyanjana
		$text = str_replace('ವt', 'ಮಾ', $text);
		$text = str_replace('ಯ್t', 'ಯಾ', $text);
		$text = str_replace('ಫì', 'ಘ', $text);
		$text = str_replace('ಫೆì', 'ಘೆ', $text);
		$text = str_replace('ಫಿì', 'ಘಿ', $text);

		$text = str_replace('ೊ', 'ೊ', $text);
		$text = str_replace('ೆೈ', 'ೈ', $text);

		$swara = "ಅ|ಆ|ಇ|ಈ|ಉ|ಊ|ಋ|ಎ|ಏ|ಐ|ಒ|ಓ|ಔ";
		$vyanjana = "ಕ|ಖ|ಗ|ಘ|ಙ|ಚ|ಛ|ಜ|ಝ|ಞ|ಟ|ಠ|ಡ|ಢ|ಣ|ತ|ಥ|ದ|ಧ|ನ|ಪ|ಫ|ಬ|ಭ|ಮ|ಯ|ರ|ಱ|ಲ|ವ|ಶ|ಷ|ಸ|ಹ|ಳ|ೞ";
		$swaraJoin = "ಾ|ಿ|ೀ|ು|ೂ|ೃ|ೄ|ೆ|ೇ|ೈ|ೊ|ೋ|ೌ|ಂ|ಃ|್";

		$syllable = "($vyanjana)($swaraJoin)|($vyanjana)($swaraJoin)|($vyanjana)|($swara)";

		$text = preg_replace("/($swaraJoin)್($vyanjana)/u", "್$2$1", $text);

		$text = str_replace('||', '|', $text);

		$text = str_replace('ೊ', 'ೊ', $text);
		$text = str_replace('ೆೈ', 'ೈ', $text);

		$text = str_replace('ಿ|', 'ೀ', $text);
		$text = str_replace('ೆ|', 'ೇ', $text);
		$text = str_replace('ೊ|', 'ೋ', $text);

		$text = preg_replace("/($swaraJoin)್($vyanjana)/u", "್$2$1", $text);

		$text = preg_replace("/($syllable)/u", "$1zzz", $text);
		$text = preg_replace("/್zzz/u", "್", $text);
		$text = preg_replace("/್R/u", "್zzzR", $text);

		$text = preg_replace("/zzz([^z]*?)zzzR/u", "zzzರ್zzz" . "$1", $text);

		$text = str_replace("zzz", "", $text);

		$text = str_replace('ೊ', 'ೊ', $text);
		$text = str_replace('ೆೈ', 'ೈ', $text);

		$text = str_replace('ಿ|', 'ೀ', $text);
		$text = str_replace('ೆ|', 'ೇ', $text);
		$text = str_replace('ೊ|', 'ೋ', $text);

		$text = preg_replace("/([[:punct:]\s0-9])ಂ/u", '${1}' . '0', $text);

		return $text;
	}

	public function normalizePraja ($text) {

		$text = str_replace('á', 'ù', $text);  // 225,E1 --> 249,F9
		$text = str_replace('¢', 'Â', $text);  // 162,A2 --> 194,C2
		$text = str_replace('¤', 'Ä', $text);  // 164,A4 --> 196,C4
		$text = str_replace('×', 'ü', $text);  // 164,A4 --> 196,C4
		
		$text = str_replace('', 'x', $text);
		$text = str_replace('', '¡', $text);
		$text = str_replace('', '¢', $text);
		$text = str_replace('', '£', $text);

		$text = str_replace('', '¥', $text);
		// $text = str_replace('', ',', $text); //handled in converter
		$text = str_replace('', '§', $text);
		// $text = str_replace('', '.', $text); //handled in converter
		$text = str_replace('', '©', $text);
		$text = str_replace('', 'ª', $text);
		$text = str_replace('', '«', $text);
		$text = str_replace('', '†', $text);
		$text = str_replace('', '‡', $text);
		$text = str_replace('', '®', $text);
		$text = str_replace('', '‰', $text); // 143,8F --> 137,89
		$text = str_replace('', '°', $text);
		$text = str_replace('', 'Š', $text);
		$text = str_replace('', '‹', $text);
		$text = str_replace('', 'Œ', $text);
		$text = str_replace('', '‘', $text);
		$text = str_replace('', '’', $text);
		$text = str_replace('', '¶', $text);
		$text = str_replace('', 'Ç', $text);
		$text = str_replace('', 'È', $text);
		$text = str_replace('', 'š', $text);
		$text = str_replace('', 'º', $text);
		$text = str_replace('', '»', $text);
		$text = str_replace('', 'ë', $text);
		$text = str_replace('', 'œ', $text);
		$text = str_replace('', '~', $text);
		$text = str_replace('', '¿', $text);
		
		// 
		
		$text = str_replace('Ð', 'û', $text);  // 164,A4 --> 196,C4
		$text = str_replace('¾', 'À', $text);

		return $text;
	}
}

?>
