<?php
namespace Agarithm\pluginalytics;


class Image {
	public function process_file_upload (&$input)
	{
		TRACE(__METHOD__);
		$input_name = explode('.', $input['User']['image']['name']);
		if (isset($input['User']['image']['size']) && $input['User']['image']['size']>0) {
			$data = &$input['User']['image'];
			$src = $data['tmp_name'];
			// DO NOT TRUST $_FILES['upfile']['mime'] VALUE !!
			// Check MIME Type by yourself.
			$finfo = new finfo(FILEINFO_MIME_TYPE);
			if (false === $ext = array_search(
				$finfo->file($src),
				array(
					'jpg' => 'image/jpeg',
					'png' => 'image/png',
					'gif' => 'image/gif',
				),
				true
			)){
				ERROR('Please choose a file format like JPG.');
				unset($input['User']['image']);
			}else{
				//src is a file type we can handle...  Keep Going.
				$dst = realpath('img') . '/';
				$dst .= time().Strings::Trim(Strings::Before(mb_strtolower($data['name']),'.'));

				list( $width, $height, $source_type ) = getimagesize($src);

				switch ( $source_type )
				{
				case IMAGETYPE_GIF:
					$dst .= '.gif';
					break;

				case IMAGETYPE_JPEG:
					$dst .= '.jpg';
					break;

				case IMAGETYPE_PNG:
					$dst .= '.png';
					break;
				}

				//Strip any whitespace if it exists...
				$dst = preg_replace('/\s+/', '', $dst);

				move_uploaded_file($src,$dst);
				return $dst;
			}
		}else if($err = $this->err2str($input['User']['image']['error'])){
			$controller->Session->setFlash($err, 'error');
			unset($input['User']['image']);
		}

		//Still here? must have failed above.
		return false;
	}

	public function process_logo_upload (&$input,&$controller)
	{
		TRACE(__METHOD__);
		$input_name = explode('.', $input['User']['image']['name']);
		if (isset($input['User']['image']['size']) && $input['User']['image']['size']>0) {
			$data = &$input['User']['image'];
			$src = $data['tmp_name'];
			// DO NOT TRUST $_FILES['upfile']['mime'] VALUE !!
			// Check MIME Type by yourself.
			$finfo = new finfo(FILEINFO_MIME_TYPE);
			if (false === $ext = array_search(
				$finfo->file($src),
				array(
					'jpg' => 'image/jpeg',
					'png' => 'image/png',
					'gif' => 'image/gif',
				),
				true
			)){
				$controller->Session->setFlash(__('Please choose a file format like JPG.'), 'error');
				unset($input['User']['image']);
			}else{
				//src is a file type we can handle...  Keep Going.
				$dst = realpath('img/company') . '/';
				$dst .= time().Strings::Trim(Strings::Before(mb_strtolower($data['name']),'.'));

				list( $width, $height, $source_type ) = getimagesize($src);

				switch ( $source_type )
				{
				case IMAGETYPE_GIF:
					$dst .= '.gif';
					break;

				case IMAGETYPE_JPEG:
					$dst .= '.jpg';
					break;

				case IMAGETYPE_PNG:
					$dst .= '.png';
					break;
				}

				//Strip any whitespace if it exists...
				$dst = preg_replace('/\s+/', '', $dst);

				$this->resize($src,$dst);
				return $dst;
			}
		}else if($err = $this->err2str($input['User']['image']['error'])){
			$controller->Session->setFlash($err, 'error');
			unset($input['User']['image']);
		}

		//Still here? must have failed above.
		return false;
	}

	public function GetFileList($dir='.'){
		TRACE(__METHOD__." $dir");
		$files = scandir($dir);

		$results = array();
		foreach($files as $key => $value){
			$path = realpath($dir.DS.$value);
			if(!is_dir($path) && (preg_match('/\.(jpe?g|gif|png)$/i',$path))) {
				if(!Strings::Contains($path,'__scaled_'))$results[] = Strings::After($path,'webroot');
			} else if(is_dir($path) && $value != '.' && $value != '..') {
				//recurse
				$results = array_merge($this->GetFileList($path),$results);
			}
		}

		return $results;
	}



	/////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////
	protected static function err2str($code) 
	{ 
		switch ($code) { 
		case UPLOAD_ERR_INI_SIZE: 
		case UPLOAD_ERR_FORM_SIZE: 
			$message = __('The file is too big');
			break; 
		case UPLOAD_ERR_PARTIAL: 
			$message = __('The file was only partially uploaded');
			break; 
		case UPLOAD_ERR_NO_TMP_DIR: 
		case UPLOAD_ERR_CANT_WRITE: 
		case UPLOAD_ERR_EXTENSION: 
			$message = __('Failed to write file to disk');
			break; 

		default: 
			$message = '';
			break; 
		} 
		return $message; 
	} 

	protected static function resize($src,$dst,$mode='auto',$r_width=1000,$r_height=618,$crop_x=0,$crop_y=0,$crop_width=0,$crop_height=0)
	{
		list( $width, $height, $source_type ) = getimagesize($src);

		switch ( $source_type )
		{
		case IMAGETYPE_GIF:
			$source_gdim = imagecreatefromgif( $src );
			break;

		case IMAGETYPE_JPEG:
			$source_gdim = imagecreatefromjpeg( $src );
			break;

		case IMAGETYPE_PNG:
			$source_gdim = imagecreatefrompng( $src );
			break;
		default:
			ERROR(__METHOD__." Undetected File Type: $src");

			return;

		}

		if($mode=='width')
		{
			$new_width = $r_width;

			$new_height = floor(($height/$width)*$new_width);
		}
		else if($mode=='height')
		{
			$new_height = $r_height;

			$new_width = floor(($width/$height)*$new_height);
		}
		/*** as_define is for height & weight  as you define as parameters in resize  function in controller ***/
		else if($mode=='as_define')
		{
			$new_height = $r_height;

			$new_width = $r_width;

		}
		else if($mode=='auto')
		{
			if($width/$height > $r_width/$r_height)
			{
				$new_width = $r_width;

				$new_height = floor(($height/$width)*$new_width);
			}
			else if($width/$height < $r_width/$r_height)
			{
				$new_height = $r_height;

				$new_width = floor(($width/$height)*$new_height);
			}
			else
			{
				$new_width = $r_width;

				$new_height = $r_height;
			}
		}
		else if($mode=='aspect_fill')
		{
			$new_width = $r_width;

			$new_height = $r_height;

			if($r_width==0 && $r_height==0)
			{
				$r_width = $crop_width;

				$r_height = $crop_height;
			}
			if($width/$height > $r_width/$r_height)
			{
				if($crop_x==0)
				{
					$crop_x = round(($width - ($r_width / ($r_height/$height)))/2); 

					$crop_y = 0;
				}
				$width = floor(($r_width/$r_height)*$height);
			}
			else if($width/$height < $r_width/$r_height)
			{
				if($crop_y==0)
				{
					$crop_y = round(($height - ($r_height / ($r_width/$width)))/2);

					$crop_x = 0;
				}
				$height = floor(($r_height/$r_width)*$width);
			}
			else
			{
				if($crop_y==0)
				{
					$crop_y=0;

					$crop_x=0;
				}
			}
			if($crop_width!=0)
			{
				$width= $crop_width;
			}
			if($crop_height!=0)
			{
				$height = $crop_height;
			}
		}
		else if($mode=='aspect_fit')
		{
			$new_width = $r_width;

			$new_height = $r_height;

			if($width/$height > $r_width/$r_height)
			{
				$crop_y = round(($r_height - $r_width*($height/$width))/2);

				$crop_x = 0;
			}
			else if($width/$height < $r_width/$r_height)
			{
				$crop_x = round(($r_width - $r_height*($width/$height))/2);

				$crop_y = 0;
			}
			else
			{
				$crop_y=0;

				$crop_x=0;
			}
		}
		$new_image = imagecreatetruecolor($new_width, $new_height);

		switch ($source_type)
		{

		case IMAGETYPE_PNG:
			// integer representation of the color black (rgb: 0,0,0)
			$background = imagecolorallocate($new_image, 255, 255, 255);
			// removing the black from the placeholder
			imagecolortransparent($new_image, $background);
			// turning off alpha blending (to ensure alpha channel information
			// is preserved, rather than removed (blending with the rest of the
			// image in the form of black))
			imagealphablending($new_image, false);

			// turning on alpha channel information saving (to ensure the full range
			// of transparency is preserved)
			imagesavealpha($new_image, true);
			break;
		case IMAGETYPE_GIF:
			// integer representation of the color black (rgb: 0,0,0)
			$background = imagecolorallocate($new_image, 255, 255, 255);
			// removing the black from the placeholder
			imagecolortransparent($new_image, $background);

			break;
		}

		if($mode=='aspect_fit')
		{
			$newColor = ImageColorAllocate($new_image, 255, 255, 255);

			imagefill($new_image,0,0,$newColor);

			if($width/$height > $r_width/$r_height)
			{
				$new_height = $r_width*($height/$width);
			}
			else if($width/$height < $r_width/$r_height)
			{
				$new_width = $r_height*($width/$height);
			}
		}

		if($mode=='aspect_fit')
		{
			//echo $crop_x,$crop_y,$new_width, $new_height, $width, $height; die;
			imagecopyresampled($new_image, $source_gdim,$crop_x,$crop_y, 0, 0, $new_width, $new_height, $width, $height);
		}
		else
		{
			imagecopyresampled($new_image, $source_gdim, 0, 0, $crop_x, $crop_y, $new_width, $new_height, $width, $height );
		}
		switch ($source_type)
		{
		case IMAGETYPE_GIF:
			imagegif($new_image, $dst );
			break;

		case IMAGETYPE_JPEG:
			imagejpeg($new_image, $dst, 70 );
			break;

		case IMAGETYPE_PNG:
			imagepng($new_image, $dst );
			break;
		}
		imagedestroy($source_gdim);

		imagedestroy($new_image);
	}


	//Syntactic Sugar: Static Methods for dealing with Images in Views
	public static function Src($wwwPath,$width,$height=0,$mode='auto'){
		// Dimension Size is a proxy for compressing images (smaller size less bytes)
		settype($width,'integer');
		settype($height,'integer');
		if($width && !$height){
			$height = $width;
		}else if($height && !$width){
			$width = $height;
		}else if($width && $height) {
			//All good, no missing values
		}else{
			$width = $height = 500;
			WARN(__METHOD__." Invalid dimensions: ".json_encode(func_get_args()));
		}

		$path = realpath(getcwd().($wwwPath));
		if(Strings::contains($path,'webroot')){
			$ext = Strings::AfterLast($wwwPath,'.');
			$decoration = '__scaled_'.$width.'_'.$height;
			$scalePath = Strings::BeforeLast($path,'.').$decoration.".$ext";
			if(!file_exists($scalePath))static::resize($path,$scalePath,$mode,$width,$height);
			return Strings::After($scalePath,'webroot');
		}else{
			ERROR(__METHOD__." File not found: ".json_encode(func_get_args()));
			return "";
		}
	}
}
