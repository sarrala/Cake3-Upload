<?php
namespace sarrala\Cake3Upload\Recognizer;

abstract class DataRecognizer extends Recognizer {

	public function recognize($data) {
		return is_array($data) ? parent::recognize($data) : false;
	}

}
