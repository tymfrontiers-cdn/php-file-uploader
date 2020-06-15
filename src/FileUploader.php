<?php
namespace TymFrontiers\Helper;
trait FileUploader{

  private $_temp_path;
  public $over_write=false;
  private $_extension;

  protected $_upload_errors = [
		// http://www.php.net/manual/en/features.file-upload.errors.php
		UPLOAD_ERR_OK 				=> "No errors.",
		UPLOAD_ERR_INI_SIZE  	=> "Larger than upload_max_filesize.",
    UPLOAD_ERR_FORM_SIZE 	=> "Larger than form MAX_FILE_SIZE.",
    UPLOAD_ERR_PARTIAL 		=> "Partial upload.",
    UPLOAD_ERR_NO_FILE 		=> "No file.",
    UPLOAD_ERR_NO_TMP_DIR => "No temporary directory.",
    UPLOAD_ERR_CANT_WRITE => "Can't write to disk.",
    UPLOAD_ERR_EXTENSION 	=> "File upload stopped by extension."
	];

	private function _attachFile(array $file) {
		global $session;
		// Perform error checking on the form parameters
    if( $session instanceof \TymFrontiers\Session ){
      $this->owner = empty($this->owner) ? $session->name : $this->owner;
    }
		if(!$file || empty($file) || !\is_array($file)) {
		  // error: nothing uploaded or wrong argument usage
		  $this->errors['upload'][] = [0,256,"No file was uploaded.",__FILE__,__LINE__];
		  return false;
		} elseif($file['error'] != 0) {
		  // error: report what PHP says went wrong
		  $this->errors['upload'][] = [0,256,$this->_upload_errors[$file['error']],__FILE__,__LINE__];
		  return false;
		} else {
			// Set object attributes to the form parameters.
	  	$this->_temp_path  	= $file['tmp_name'];
	  	$this->_name 	= \str_replace(' ','_',\basename($file['name']));
	  	$this->_type       	= $file['type'];
	  	$this->_size       	= $file['size'];
	  	$this->_creator     = $session->name;
	  	$this->type_group   = $this->groupName();
			$this->caption = !empty($this->caption) ? $this->caption : \pathinfo($file['name'], PATHINFO_FILENAME);
      $this->_extension = \pathinfo($file['name'], PATHINFO_EXTENSION);
			$this->nice_name = !empty($this->nice_name) ? $this->nice_name : \pathinfo($file['name'], PATHINFO_FILENAME).".{$this->_extension}";
			return true;
		}
	}
	public function upload(array $file){
    if( !\is_dir($this->_path) ){
      throw new Exception("Upload directory has not been set", 1);
    }
		$this->_attachFile($file);
		return $this->save();
	}

	public function save() {
		// A new record won't have an id yet.
		if( isset($this->id) ) {
			// Really just to update the caption
			$this->_update();
		} else {
			// Make sure there are no errors

			// Can't save if there are pre-existing errors
		  if(!empty($this->errors)) { return false; }

		  // Can't save without filename and temp location
		  if(empty($this->_name) || empty($this->_temp_path)) {
		    $this->errors[] = "The file location was not available.";
		    return false;
		  }
			// Determine the target_path
			$this->_name = \strtolower( \uniqid(true) . \uniqid(true) . \uniqid().'.'.$this->_extension );
			$target_path = "{$this->_path}/{$this->_name}";
			if( !$this->over_write ) $target_path = $this->_renameIfExist($target_path);
			// Attempt to move the file
			if( \move_uploaded_file($this->_temp_path, $target_path)) {
		  	// Success
				// Save a corresponding entry to the database
				if($this->_create()) {
					// We are done with temp_path, the file isn't there anymore
					unset($this->_temp_path);
					return true;
				}
			} else {
				// File was not moved.
		    $this->errors['upload'][] = [0,256,"The file upload failed, possibly due to incorrect permissions on the upload folder.",__FILE__,__LINE__];
		    return false;
			}
		}
	}


	protected function _renameIfExist(string $file){
		if($file){
			if(file_exists($file)){
				$file = pathinfo($file);
				$name = $file['filename'];
				$ext = $file['extension'];
				$dir_name = $file['dirname'];
				$i=1;
				$i++;
				if(strrpos($name, '(') <=0){
					$name = $name.'('.$i.')';
				}else{
					$pos = strrpos($name, '(');
					$dup = substr($name, $pos);
					preg_match_all('!\d+!', $dup, $matches);
					$num =  implode(' ', $matches[0]);;
					$num++;
					$name = $num > 0 ? $name."(".$num.")" : $name."(".$i.")";
					$name = str_replace($dup, '', $name);
				}
				$done_file=$dir_name.DS.$name.'.'.$ext;
				$this->_name = $name.'.'.$ext;
				return !file_exists($done_file) ? $done_file : $this->_renameIfExist($done_file);
			}else{
				return $file;
				// $this->errors[] = "Can't rename, \"{$file}\" does not exist or wrong directory given.";
				// return false;
			}
		}else{
			return $file;
			// $this->errors[] = "No file given.";
			// return false;
		}
	}

}
