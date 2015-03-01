<?php
namespace sarrala\Cake3Upload\Recognizer;

use Cake\Filesystem\File;

abstract class FileRecognizer extends Recognizer {

	public function recognize($file) {
		return $file instanceof File ? parent::recognize($file) : false;
	}

}
