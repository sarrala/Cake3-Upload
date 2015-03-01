<?php
namespace sarrala\Cake3Upload\Recognizer;

use Cake\Filesystem\File;

abstract class Recognizer {
	
	protected $_type;
	protected $_encoding;
	
	public function __construct($type = false, $encoding = false) {
		$this->_type = $type;
		$this->_encoding = $encoding;
	}
	
	public function recognize($data) {
		return true;
	}
	
	public function canImprove( $mime ) {
		return false;
	}
	
	public function setType( $mime ) {
		$this->_type = $mime;
	}
	
	public function getType() {
		return $this->_type;
	}

	public function setEncoding( $encoding ) {
		$this->_encoding = $encoding;
	}
	
	public function getEncoding() {
		return $this->_encoding;
	}
	
}
