<?php
namespace sarrala\Cake3Upload\Recognizer;

use Cake\Filesystem\File;

class CsvRecognizer extends Recognizer {
	
	const MIN_ROWS = 5;
	const MAX_LINE_LENGTH = 4000;
	
	protected $_separators = [',', ';', '|', ':'];
	
	public function canImprove( $mime ) {
		switch ($mime) {
			case 'text/plain':
				return true;
		}
		return false;
	}
	
	public function recognize(File $file) {
		
		$data = false;
		$separator = false;
		
		// Read first line from file and look for field separator
		$line = fgets($file->handle);
		foreach ($this->_separators as $sep) {
			$data = str_getcsv($line, $sep);
			if ($data !== false) {
				// @TODO Heuristics could be improved here by looking for data patterns
				// However, we should not decide if some patter is correct or not
				// but instead, look for most probable pattern and simply fall back
				// selecting separator by highest character count and other
				// probability factors (requires some research and statistics).
				$separator = $sep;
				break;
			}
		}
		
		// Check if we found possible separator
		if (! $separator) {
			return false;
		}
		
		$requirecount = count($data);
		
		// Validate csv using collected information
		$rows = 1;
		while (($data = fgetcsv($file->handle, self::MAX_LINE_LENGTH, ",")) !== false) {
			
			$columncount = count($data);
			if ($columncount !== $requirecount) {
				return false;
			}
			$rows++;
			
		}
		
		// Save results and return
		if ($rows >= self::MIN_ROWS) {
			// It walks like a duck and quacks like a duck, 
			// so I think it most probably is what it looks like
			$this->setType('text/csv');
			return 'text/csv';
		}
		return false;
	}
	
}
