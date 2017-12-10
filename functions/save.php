<?php
$cptSave = new CptSaveThumbnail();
add_action( 'wp_ajax_cptSaveThumbnail', array($cptSave, 'saveThumbnailAjaxWrap') );

class CptSaveThumbnail {
	
	private static $debug = array();
	
	/**
	 * Handle-function called via ajax request.
	 * Check and crop multiple images. Update with wp_update_attachment_metadata if needed.
	 * Input parameters:
	 *    * $_REQUEST['selection'] - json-object - data of the selection/crop
	 *    * $_REQUEST['raw_values'] - json-object - data of the original image
	 *    * $_REQUEST['activeImageSizes'] - json-array - array with data of the images to crop
	 * The main code is wraped via try-catch - the errorMessage will send back to JavaScript for displaying in an alert-box.
	 * Called die() at the end.
	 */
	public function saveThumbnail() {
		global $cptSettings;
		$jsonResult = array();
		$settings = $cptSettings->getOptions();
		
		try {
			$input = $this->getValidatedInput();
			
			
			$sourceImgPath = get_attached_file( $input->sourceImageId );
			if(empty($sourceImgPath)) {
				throw new Exception(esc_html__("ERROR: Can't find original image file!",'cpt_lang'), 1);
			}
			
			
			$imageMetadata = wp_get_attachment_metadata($input->sourceImageId, true);//get the attachement metadata of the post
			if(empty($imageMetadata)) {
				throw new Exception(esc_html__("ERROR: Can't find original image metadata!",'cpt_lang'), 1);
			}
			
			//from DB
			$dbImageSizes = $cptSettings->getImageSizes();
			
			/**
			 * will be filled with the new image-url if the image format isn't in the attachements metadata, 
			 * and Wordpress doesn't know about the image file
			 */
			$changedImageName = array();
			$_processing_error = array();
			foreach($input->activeImageSizes as $activeImageSize) {
				self::addDebug('submitted image-data');
				self::addDebug($activeImageSize);
				if(!self::isImageSizeValid($activeImageSize,$dbImageSizes)) {
					self::addDebug("Image size not valid.");
					continue;
				}
				
				$oldFile_toDelete = '';
				if(empty($imageMetadata['sizes'][$activeImageSize->name])) {
					$changedImageName[ $activeImageSize->name ] = true;
				} else {
					//the old size hasent got the right image-size/image-ratio --> delete it or nobody will ever delete it correct
					if($imageMetadata['sizes'][$activeImageSize->name]['width'] != intval($activeImageSize->width) || $imageMetadata['sizes'][$activeImageSize->name]['height'] != intval($activeImageSize->height) ) {
						$oldFile_toDelete = $imageMetadata['sizes'][$activeImageSize->name]['file'];
						$changedImageName[ $activeImageSize->name ] = true;
					}
				}
				
				
				$croppedSize = self::getCroppedSize($activeImageSize,$imageMetadata,$input);
				
				$currentFilePath = self::generateFilename($sourceImgPath, $croppedSize['width'], $croppedSize['height']);
				self::addDebug("filename:".$currentFilePath);
				$currentFilePathInfo = pathinfo($currentFilePath);
				$temporaryCopyFile = $cptSettings->getUploadDir().DIRECTORY_SEPARATOR.$currentFilePathInfo['basename'];
				
				$result = wp_crop_image(							// * @return string|WP_Error|false New filepath on success, WP_Error or false on failure.
					$input->sourceImageId,							// * @param string|int $src The source file or Attachment ID.
					$input->selection->x,							// * @param int $src_x The start x position to crop from.
					$input->selection->y,							// * @param int $src_y The start y position to crop from.
					$input->selection->x2 - $input->selection->x,	// * @param int $src_w The width to crop.
					$input->selection->y2 - $input->selection->y,	// * @param int $src_h The height to crop.
					$croppedSize['width'],							// * @param int $dst_w The destination width.
					$croppedSize['height'],							// * @param int $dst_h The destination height.
					false,											// * @param int $src_abs Optional. If the source crop points are absolute.
					$temporaryCopyFile								// * @param string $dst_file Optional. The destination file to write to.
				);
				
				$_error = false;
				if(empty($result)) {
					$_processing_error[$activeImageSize->name][] = sprintf(esc_html__("Can't generate filesize '%s'.",'cpt_lang'),$activeImageSize->name);
					$_error = true;
				} else {
					if(!empty($oldFile_toDelete)) {
						self::addDebug("delete old image:".$oldFile_toDelete);
						@unlink($currentFilePathInfo['dirname'].DIRECTORY_SEPARATOR.$oldFile_toDelete);
					}
					if(!@copy($result,$currentFilePath)) {
						$_processing_error[$activeImageSize->name][] = sprintf(esc_html__("Can't copy temporary file to media library.",'cpt_lang'));
						$_error = true;
					}
					if(!@unlink($result)) {
						$_processing_error[$activeImageSize->name][] = sprintf(esc_html__("Can't delete temporary file.",'cpt_lang'));
						$_error = true;
					}
				}
				
				if(!$_error) {
					//update metadata --> otherwise new sizes will not be updated
					$imageMetadata = self::updateMetadata($imageMetadata, $activeImageSize->name, $currentFilePathInfo, $croppedSize['width'], $croppedSize['height']);
				} else {
					self::addDebug('error on '.$currentFilePathInfo['basename']);
					self::addDebug($_processing_error);
				}
			}//END foreach
			
			//we have to update the posts metadate
			//otherwise new sizes will not be updated
			$imageMetadata = apply_filters('crop_thumbnails_before_update_metadata', $imageMetadata, $input->sourceImageId);
			wp_update_attachment_metadata( $input->sourceImageId, $imageMetadata);
			
			//generate result;
			if(!empty($changedImageName)) {
				//there was a change in the image-formats 
				foreach($changedImageName as $key=>$value) {
					$newImageLocation = wp_get_attachment_image_src($input->sourceImageId, $key);
					$changedImageName[ $key ] = $newImageLocation[0];
				}
				$jsonResult['changedImageName'] = $changedImageName;
			}
			if(!empty($_processing_error)) {//one or more errors happend when generating thumbnails
				$jsonResult['processingErrors'] = $_processing_error;
			}
			if(!empty($settings['debug_data'])) {
				$jsonResult['debug'] = self::getDebug();
			}
			$jsonResult['success'] = time();//time for cache-breaker
			echo json_encode($jsonResult);
		} catch (Exception $e) {
			if(!empty($settings['debug_data'])) {
				$jsonResult['debug'] = self::getDebug();
			}
			$jsonResult['error'] = $e->getMessage();
			echo json_encode($jsonResult);
		}
	}
	
	/**
	 * Get the end-size of the cropped image in pixels.
	 * Attention: these sizes are used to name the file.
	 * @param  object  $activeImageSize The image size that should be used
	 * @param  [type]  $imageMetadata   [description]
	 * @param  [type]  $input           [description]
	 * @return {[type]                  [description]
	 */
	public static function getCroppedSize($activeImageSize,$imageMetadata,$input) {
		//set target size of the cropped image
		$croppedWidth = $activeImageSize->width;
		$croppedHeight = $activeImageSize->height;
		try {
			if($activeImageSize->width===9999) {
				$croppedWidth = intval($imageMetadata['width']);
			} elseif($activeImageSize->height===9999) {
				$croppedHeight = intval($imageMetadata['height']);
			} elseif(intval($activeImageSize->width)===0 && intval($activeImageSize->height)===0) {
				$croppedWidth = $input->selection->x2 - $input->selection->x;
				$croppedHeight = $input->selection->y2 - $input->selection->y;
			} elseif(intval($activeImageSize->width)===0) {
				$croppedWidth = intval(( intval($imageMetadata['width']) / intval($imageMetadata['height']) ) * $activeImageSize->height);
				$croppedHeight = $activeImageSize->height;
			} elseif(intval($activeImageSize->height)===0) {
				$croppedWidth = $activeImageSize->width;
				$croppedHeight = intval(( intval($imageMetadata['height']) / intval($imageMetadata['width']) ) * $activeImageSize->width);
			}
			
			/* --- no need to use that ---
			if(!$activeImageSize->crop) {
				$croppedWidth = $input->selection->x2 - $input->selection->x;
				$croppedHeight = $input->selection->y2 - $input->selection->y;
			}*/
		} catch(Exception $e) {
			$croppedWidth = 10;
			$croppedHeight = 10;
		}
		
		return array('width' => $croppedWidth, 'height'=> $croppedHeight);
	}
	
	/**
	 * This function is called by the wordpress-ajax-callback. Its only purpose is to call the
	 * saveThumbnail function and die().
	 * All wordpress ajax-functions should call the "die()" function in the end. But this makes
	 * phpunit tests impossible - so we have to wrap it.
	 */
	public function saveThumbnailAjaxWrap() {
		$this->saveThumbnail();
		die();
	}

	private static function addDebug($text) {
		self::$debug[] = $text;
	}
	
	private static function getDebug() {
		if(!empty(self::$debug)) {
			return self::$debug;
		}
		return array();
	}
	
	private static function updateMetadata($imageMetadata, $imageSizeName, $currentFilePathInfo, $croppedWidth, $croppedHeight) {
		$fullFilePath = trailingslashit($currentFilePathInfo['dirname']) . $currentFilePathInfo['basename'];
		
		$newValues = array();
		$newValues['file'] = $currentFilePathInfo['basename'];
		$newValues['width'] = intval($croppedWidth);
		$newValues['height'] = intval($croppedHeight);
		$newValues['mime-type'] = mime_content_type($fullFilePath);
		
		$oldValues = array();
		if(empty($imageMetadata['sizes'])) {
			$imageMetadata['sizes'] = array();
		}
		if(!empty($imageMetadata['sizes'][$imageSizeName])) {
			$oldValues = $imageMetadata['sizes'][$imageSizeName];
		}
		$imageMetadata['sizes'][$imageSizeName] = array_merge($oldValues,$newValues);
		
		do_action('crop_thumbnails_after_save_new_thumb', $fullFilePath, $imageSizeName, $imageMetadata['sizes'][$imageSizeName] );
		return $imageMetadata;
	}

	/**
	 * @param object data of the new ImageSize the user want to crop
	 * @param array all available ImageSizes
	 * @return boolean true if the newImageSize is in the list of ImageSizes and dimensions are correct
	 */
	private static function isImageSizeValid(&$submitted,$dbData) {
		if(empty($submitted->name)) {
			return false;
		}
		if(empty($dbData[$submitted->name])) {
			return false;
		}
		
		//restore the default data just to make sure nothing is compromited
		$submitted->crop = empty($dbData[$submitted->name]['crop']) ? 0 : 1;
		$submitted->width = $dbData[$submitted->name]['width'];
		$submitted->height = $dbData[$submitted->name]['height'];
		//eventually we want to test some more later
		return true;	
	}
	
	/**
	 * Some basic validations and value transformations
	 * @return object JSON-Object with submitted data
	 * @throw Exception if the security validation fails
	 */
	private function getValidatedInput() {
		global $cptSettings;
		
		if(!check_ajax_referer($cptSettings->getNonceBase(),'_ajax_nonce',false)) {
			throw new Exception(esc_html__("ERROR: Security Check failed (maybe a timeout - please try again).",'cpt_lang'), 1);
		}
		
		
		if(empty($_REQUEST['crop_thumbnails'])) {
			throw new Exception(esc_html__('ERROR: Submitted data is incomplete.','cpt_lang'), 1);
		}
		$input = json_decode(stripcslashes($_REQUEST['crop_thumbnails']));
		
		
		if(empty($input->selection) || empty($input->sourceImageId) || !isset($input->activeImageSizes)) {
			throw new Exception(esc_html__('ERROR: Submitted data is incomplete.','cpt_lang'), 1);
		}
		
		
		if(!isset($input->selection->x) || !isset($input->selection->y) || !isset($input->selection->x2) || !isset($input->selection->y2)) {
			throw new Exception(esc_html__('ERROR: Submitted data is incomplete.','cpt_lang'), 1);
		}
		
		
		$input->selection->x = intval($input->selection->x);
		$input->selection->y = intval($input->selection->y);
		$input->selection->x2 = intval($input->selection->x2);
		$input->selection->y2 = intval($input->selection->y2);
		
		if($input->selection->x < 0 || $input->selection->y < 0) {
			throw new Exception(esc_html__('Cropping to these dimensions on this image is not possible.','cpt_lang'), 1);
		}
		
		
		$input->sourceImageId = intval($input->sourceImageId);
		$_tmp = get_post($input->sourceImageId);//need to be its own var - cause of old php versions
		if(empty($_tmp)) {
			throw new Exception(esc_html__("ERROR: Can't find original image in database!",'cpt_lang'), 1);
		}
		
		return $input;
	}


	/**
	 * Generate the Filename (and path) of the thumbnail based on width and height the same way as wordpress do.
	 * @see generate_filename in wp-includes/class-wp-image-editor.php
	 * @param string Path to the original (full-size) file.
	 * @param int width of the new image
	 * @param int height of the new image
	 * @return string path to the new image
	 */
	private static function generateFilename( $file, $w, $h ){
		$info = pathinfo($file);
		$dir = $info['dirname'];
		$ext = $info['extension'];
		$name = wp_basename($file, '.'.$ext);
		$suffix = $w.'x'.$h;
		$destfilename = $dir.'/'.$name.'-'.$suffix.'.'.$ext;
		return $destfilename;
	}
}