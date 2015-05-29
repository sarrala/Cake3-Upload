<?php

namespace sarrala\Cake3Upload\Model\Behavior;

use Cake\Event\Event;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;
use Cake\ORM\Behavior;
use Cake\ORM\Entity;

class UploadBehavior extends BaseUploadBehavior {

	/**
	 * Check if there is some files to upload and modify the entity before
	 * it is saved.
	 *
	 * At the end, for each files to upload, unset their "virtual" property.
	 *
	 * @param Event $event
	 *        	The beforeSave event that was fired.
	 * @param Entity $entity
	 *        	The entity that is going to be saved.
	 *        	
	 * @throws \LogicException When the path configuration is not set.
	 * @throws \ErrorException When the function to get the upload path failed.
	 *        
	 * @return void
	 */
	public function beforeSave(Event $event, Entity $entity, $options = []) {
		error_log('AT UPLOAD BEHAVIOR');
		foreach ( $this->_config['fields'] as $field => $fieldOption ) {
			
			$virtualField = $fieldOption['virtualField'];
			if (!isset( $entity->{$virtualField} ) || !is_array( $entity->{$virtualField} )) {
				continue;
			}
			
			$file = $entity->get( $virtualField );
			if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
				continue;
			}
			
			$extension = (new File( $file['name'], false ))->ext();
			$uploadPath = $this->_getUploadPath( $entity, $fieldOption['path'], $file['tmp_name'], $extension, $options );
			if (!$uploadPath) {
				throw new \ErrorException( __( 'Could not get the upload path.' ) );
			}
			
			$folder = new Folder( $this->_config['root'] );
			$folder->create( $this->_config['root'] . dirname( $uploadPath ) );
			
			$file_path = $this->_moveFile( $entity, $file['tmp_name'], $uploadPath, $field, $fieldOption );
			if ($file_path) {
				
				$entity->set( $field, $this->_prefix . $uploadPath );
				if ($fieldOption['name']) {
					$entity->set( $fieldOption['name'], $file['name'] );
				}
				
				$this->_postProcessUpload($entity, new File($file_path, false));
			}
			
			$entity->unsetProperty( $virtualField );
		}
	}
	
	public function beforeDelete(Event $event, Entity $entity, $options = []) {
		if ($this->_config['unlinkOnDelete']) {
			foreach ( $this->_config['fields'] as $field => $fieldOption ) {
				if ( ! $this->_deleteUpload( $entity, $field, $fieldOption )) {
					return false;
				}
			}
		}
		return true;
	}

}
