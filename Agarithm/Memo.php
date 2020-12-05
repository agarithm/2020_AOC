<?php
namespace Agarithm;
include_once(dirname(__FILE__)."/Singleton.php");


//Master Memoizer Class enables a Global Reset which is needed for Testing and Long Running Workers
class Memo extends Singleton{
	public function __construct(){
		$this->data = array();
	}

	public static function Get($key){
		$memo = static::instance();
		return isset($memo->data[$key]) ? $memo->data[$key] : null ;
	}

	public static function Set($key, $value){
		$memo = static::instance();
		$memo->data[$key]=$value;
		return $value;
	}

	public static function Clear(){
		$memo = static::instance();
		$memo->data = array();
	}

	public static function GetCache($key,$server='127.0.0.1',$port='11211'){
		if(extension_loaded('memcached')){
			$memCache = new \Memcached();
			$memCache->addServer($server,$port);
			return $memCache->get($key);
		}elseif(function_exists('get_transient')){
			return get_transient($key);
		}else{
			WARN(__METHOD__." memcached extension not loaded");
			return static::Get($key);
		}
	}

	public static function SetCache($key, $value, $server='127.0.0.1',$port='11211'){
		if(extension_loaded('memcached')){
			$memCache = new \Memcached();
			$memCache->addServer($server,$port);
			$memCache->set($key, $value);
		}elseif(function_exists('set_transient')){
			set_transient($key,$value,30*24*60*60); //30 days expirey
		}else{
			WARN(__METHOD__." memcached extension not loaded");
			static::Set($key, $value);
		}
		return static::GetCache($key);
	}

}

