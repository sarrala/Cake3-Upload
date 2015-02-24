<?php
namespace sarrala\Cake3Upload\Recognizer;

use Cake\Filesystem\File;

class FinfoRecognizer extends Recognizer {

	public function recognize(File $file) {
		
		$file_mime = false;
		$file_encoding = false;
		
		if ($file->exists()) {
			$finfo = new \finfo();
			$file_mime = $finfo->file( $file->path, \FILEINFO_MIME_TYPE );
			$file_encoding = $finfo->file( $file->path, \FILEINFO_MIME_ENCODING );
			
			// Save information
			$this->setType($file_mime);
			$this->setEncoding($file_encoding);
		}
		
		return $file_mime ? $file_mime : false;
		
	}

}