<?php
namespace Agarithm;

//Singleton Base Class
class Singleton {
	public static function &instance(){
		static $single=array();
		$class = get_called_class();
		if( ! isset($single[$class]) ) $single[$class] = new $class();
		return $single[$class];
	}
}
