<?php

namespace sarrala\Cake3Upload\Model\Behavior;

use Cake\Event\Event;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;
use Cake\ORM\Behavior;
use Cake\ORM\Entity;

abstract class BaseUploadBehavior extends Behavior {

	/**
	 * Default config.
	 *
	 * @var array
	 */
	protected $_defaultConfig = [ 
			'root' => WWW_ROOT, 
			'suffix' => '_file', 
			'recognizers' => false, 
			'mimeField' => 'mime',
			'encodingField' => 'encoding',
			'defaultMime' => '',
			'defaultEncoding' => '',
			'unlinkOnDelete' => true,
			'fields' => [],
			'templates' => []
	];
	
	protected $_recognizers = [];
	
	/**
	 * Overwrite all file on upload.
	 *
	 * @var bool
	 */
	protected $_overwrite = true;
	
	protected $_unlinkOnDelete;

	/**
	 * The prefix of the file.
	 *
	 * @var bool string
	 */
	protected $_prefix = false;

	/**
	 * The default file of the field.
	 *
	 * @var bool string
	 */
	protected $_defaultFile = false;

	abstract public function beforeSave(Event $event, Entity $entity, $options = []);
	abstract public function beforeDelete(Event $event, Entity $entity, $options = []);

	public function initialize(array $config) {
		
		// For some reason local $config is not merged with $_defaultConfig
		$config = $this->_config;
		
		// Check configuration
		foreach ( $config['fields'] as $field => $fieldOption ) {
			
			$this->_config['fields'][$field]['virtualField'] = $field . $config['suffix'];
			
			if (!isset( $fieldOption['path'] )) {
				throw new \FatalErrorException( __( 'The path for the {0} field is required.', $field ) );
			}
			
			if (isset( $fieldOption['prefix'] ) && (is_bool( $fieldOption['prefix'] ) || is_string( $fieldOption['prefix'] ))) {
				$this->_prefix = $fieldOption['prefix'];
			}
			
			if (!$this->_prefix) {
				$this->_prefix = '';
			}
			
		}
		
		$_unlinkOnDelete = $config['unlinkOnDelete'];
		
	}
	
	protected function _loadRecognizers() {
		$config = $this->_config;
		
		// Anything to load?
		if ( ! $config['recognizers']) {
			return false;
		}
		
		// Recognizers already loaded?
		if (!empty($this->_recognizers)) {
			return true;
		}
		
		// Loop through all defined recognizers
		foreach ((array) $config['recognizers'] as $recognizer_name ) {
			$class = 'sarrala\\Cake3Upload\\Recognizer\\' . $recognizer_name . 'Recognizer';
			$this->_recognizers[$recognizer_name] = new $class();
		}
		
		return true;
	}
	
	protected function _postProcessUpload(Entity $entity, File $file) {
		
		$config = $this->_config;
		
		if ($config['recognizers']) {
			
			$file->open('r');
			
			$file_mime = false;
			$file_encoding = false;
			
			$this->_loadRecognizers();
			
			// Loop through all defined methods
			foreach ($this->_recognizers as $recognizer ) {
				
				// If we already got mime check if next recognizer could improve it
				if ($file_mime && ! $recognizer->canImprove( $file_mime )) {
					// This one can't improve current type
					continue;
				}
				
				// Execute recognizer and fetch results
				$recognizer->setType( $file_mime );
				$recognizer->setEncoding( $file_encoding );
				$recognizer->recognize( $file );
				$file_mime = $recognizer->getType();
				$file_encoding = $recognizer->getEncoding();
				
			}
			
			// Set information to entity
			$file_mime = $file_mime ? $file_mime : $config['defaultMime'];
			if (isset($config['mimeField'])) {
				$entity->set( $config['mimeField'], $file_mime ? $file_mime : 'Error: 1;' );
			}
			
			if (isset($config['encodingField'])) {
				$entity->set( $config['encodingField'], $file_encoding ? $file_encoding : 'Error: 1;' );
			}

		}
	}
	
	/**
	 * Move the temporary source file to the destination file.
	 *
	 * @param \Cake\ORM\Entity $entity
	 *        	The entity that is going to be saved.
	 * @param bool|string $source
	 *        	The temporary source file to copy.
	 * @param bool|string $destination
	 *        	The destination file to copy.
	 * @param bool|string $field
	 *        	The current field to process.
	 * @param array $options
	 *        	The configuration options defined by the user.
	 *        	
	 * @return mixed|bool|string False or file path
	 */
	protected function _moveFile(Entity $entity, $source = false, $destination = false, $field = false, array $options = []) {

		if ($source === false || $destination === false || $field === false) {
			return false;
		}
		
		if (isset( $options['overwrite'] ) && is_bool( $options['overwrite'] )) {
			$this->_overwrite = $options['overwrite'];
		}
		
		if ($this->_overwrite) {
			$this->_deleteOldUpload( $entity, $field, $destination, $options );
		}
		
		$file = new File( $source, false, 0755 );
		
		$dstPath = $this->_config['root'] . $destination;
		
		if ($file->copy( $dstPath, $this->_overwrite )) {
			return $dstPath;
		}
		
		return false;
	
	}

	/**
	 * Delete the old upload file before to save the new file.
	 *
	 * We can not just rely on the copy file with the overwrite, because if you use
	 * an identifier like :md5 (Who use a different name for each file), the copy
	 * function will not delete the old file.
	 *
	 * @param \Cake\ORM\Entity $entity
	 *        	The entity that is going to be saved.
	 * @param bool|string $field
	 *        	The current field to process.
	 * @param bool|string $newFile
	 *        	The new file path.
	 * @param array $options
	 *        	The configuration options defined by the user.
	 *        	
	 * @return bool
	 */
	protected function _deleteOldUpload(Entity $entity, $field = false, $newFile = false, array $options = []) {

		if ($field === false || $newFile === false) {
			return true;
		}
		
		$fileInfo = pathinfo( $entity->$field );
		$newFileInfo = pathinfo( $newFile );
		
		if (isset( $options['defaultFile'] ) && (is_bool( $options['defaultFile'] ) || is_string( $options['defaultFile'] ))) {
			$this->_defaultFile = $options['defaultFile'];
		}
		
		if ($fileInfo['basename'] == $newFileInfo['basename'] || $fileInfo['basename'] == pathinfo( $this->_defaultFile )['basename']) {
			return true;
		}
		
		if ($this->_prefix) {
			$entity->$field = str_replace( $this->_prefix, "", $entity->$field );
		}
		
		$file = new File( $this->_config['root'] . $entity->$field, false );
		
		if ($file->exists()) {
			$file->delete();
		}
		
		return true;
	}

	protected function _deleteUpload(Entity $entity, $field = false, array $options = []) {
		
		if ($field === false || ! $this->_config['unlinkOnDelete']) {
			return true;
		}
		
		$fileInfo = pathinfo( $entity->$field );
		
		if (isset( $options['defaultFile'] ) && (is_bool( $options['defaultFile'] ) || is_string( $options['defaultFile'] ))) {
			$this->_defaultFile = $options['defaultFile'];
		}
		
		if ($fileInfo['basename'] == pathinfo( $this->_defaultFile )['basename']) {
			return true;
		}
		
		if ($this->_prefix) {
			$entity->$field = str_replace( $this->_prefix, "", $entity->$field );
		}
		
		$file = new File( $this->_config['root'] . $entity->$field, false );
		
		// Check if this file has other references (db rows)
		if ($this->_table->find()->where(["$field =" => $entity->$field])->count() == 1) {
			
			if ($file->exists()) {
				error_log('FILE DELETED');
				return $file->delete();
			} else {
				error_log('FILE NOT FOUND: '.$file->path);
			}
			
		} else {
			error_log('FILE REFERENCE COUNT > 1');
		}
		
		// Already gone
		return true;
		
	}
	
	/**
	 * Get the path formatted without its identifiers to upload the file.
	 *
	 * Identifiers:
	 * :uid			Logged in user id.
	 * :id			Id of the Entity.
	 * :mime		File mime type. Currently useless, requires fix.
	 * :md5			File contents checksum.
	 * :sha256		File contents checksum.
	 * :fast-hash	Random MD5 hash.
	 * :fast-uniq	Random SHA-256 hash.
	 * :y			Current year.
	 * :m			Current month.
	 * :d			Current date.
	 * :date		Current date in 1970-12-30 format.
	 * :time		Current time in 235959 format.
	 * :extcase		File extension in its original form.
	 * :ext			File extension in all-lowercase format.
	 *
	 * Examples:
	 * 
	 * 	- Template: upload/:uid/:md5
	 * 	- Result: upload/2/5e3e0d0f163196cb9526d97be1b2ce26
	 * 
	 * 	- Template: upload/:uid/:date_:time:ext
	 * 	- Result: upload/2/1970-12-30_235959jpg
	 * 
	 * 	- Template: upload/:id/:date_:time.:ext
	 * 	- Result: upload/10/1970-12-30_235959.jpg
	 * 
	 * 	- Template: :id-:uid-:fast-hash
	 * 	- Result: 10-2-cd0f1a9c68d2a5dbd98e80f4cc054c33
	 * 
	 * 	- Template: :id-:uid-:fast-uniq
	 * 	- Result: 10-2-e0db5ac43ad9e152ad6c889e5ed4325987f3a6a7be9689a9d7d11be95236d68a
	 * 
	 * @param \Cake\ORM\Entity $entity
	 *        	The entity that is going to be saved.
	 * @param bool|string $path
	 *        	The path to upload the file with its identifiers.
	 * @param bool|string $extension
	 *        	The extension of the file.
	 *        	
	 * @return bool string
	 */
	protected function _getUploadPath(Entity $entity, $path = false, $source_file = false, $extension = false, $options = []) {

		if ($extension === false || $path === false) {
			return false;
		}
		
		$path = trim( $path, DS );
		
		$identifiers = array_merge([ 
				':uid' => function ($e) use($options) { return $options['loggedInUser']; },
				':id' => $entity->id, 
				//':mime' => *CURRENTLY USELESS, REQUIRES EXECUTION REORDERING*
				':md5' => function ($e) use($source_file) { return md5_file($source_file); }, 
				':sha256' => function ($e) use($source_file) { return hash_file('sha256',$source_file); },
				':fast-hash' => function ($e) { return md5( uniqid( uniqid('', rand(0,1)), true )); }, 
				':fast-uniq' => function ($e) { return hash('sha256', mt_rand() . uniqid( uniqid('', rand(0,1)), true ) . uniqid('', mt_rand()%2 )); },
				':date' => date( 'Y-m-d' ),
				':time' => date( 'His' ),
				':y' => date( 'Y' ),
				':m' => date( 'm' ),
				':d' => date( 'd' ),
				':extcase' => $extension,
				':ext' => strtolower( $extension ),
				':.extcase' => '.' . $extension,
				':.ext' => '.' . strtolower( $extension ),
		],$this->_config['templates']);
		
		foreach ($identifiers as $src => $dst) {
			if (mb_strpos($path, $src) !== false) {
				$path = strtr($path, [$src => is_callable($dst) ? $dst($entity) : $dst]);
			}
		}
		
		return $path;
	
	}

}
