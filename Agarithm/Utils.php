<?php
namespace Agarithm;

include_once(dirname(__FILE__)."/Singleton.php");
include_once(dirname(__FILE__)."/Memo.php");
include_once(dirname(__FILE__)."/Strings.php");
include_once(dirname(__FILE__)."/Scraper.php");
include_once(dirname(__FILE__)."/UI.php");
include_once(dirname(__FILE__)."/DB/DB_UTILS.php");


//Init base ENVIRONMENT Type
if(empty(getenv('IS_PRODUCTION')))putenv("IS_PRODUCTION=1");
if(empty(getenv('IS_STAGING')))putenv("IS_STAGING=0");
if(empty(getenv('IS_DEV')))putenv("IS_DEV=0");


////////////////////////////////////////////////////////////////////////////////////////////////////////////
// LOGGER
class LOGGER extends Singleton{
	public const DEBUG = 0;
	public const TRACE = 1;
	public const INFO  = 2;
	public const WARN  = 3;
	public const ERROR = 4;
	public const ALERT = 5;
	public const FATAL = 6;

	public function __construct(){
		$this->log = array();
		$this->first = $this->utime();
		$this->whoami = '' ;
		$this->ip = empty(getenv('REMOTE_ADDR')) ? "127.0.0.1" : getenv('REMOTE_ADDR');
		$this->ip = str_pad($this->ip,15);
		$this->level = LOGGER::INFO;
	}

	private function utime(){
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}


	public static function LOG ($level,$message){
		$label = function($level){
			return [ 
				LOGGER::DEBUG   => 'DEBUG:',
				LOGGER::TRACE   => 'TRACE:',
				LOGGER::INFO    => ' INFO:',
				LOGGER::WARN    => ' WARN:',
				LOGGER::ERROR   => 'ERROR:',
				LOGGER::ALERT   => 'ALERT:',
				LOGGER::FATAL   => 'FATAL:',
			][$level];
		};

		$l = static::instance();
		$now = $l->utime();
		$runtime = $now - $l->first; 
		$runtime = str_replace(',','',number_format(($runtime<0.00001 ? 0.0 : $runtime),4));
		$runtime = $runtime < 300 ? $runtime."s" : Strings::HumanSeconds($runtime) ;
		$stamp = date('Y-m-d H:i:s');
		$prefix = $label($level);
		$whoami  = Strings::isEmpty($l->whoami) ? "" : ' ('.$l->whoami.')';
		$whoami = $l->ip . $whoami;
		$line = "$stamp - $runtime - $whoami - $prefix $message";

		$l->log[] = array('level'=>$level,'line'=>$line);

		//Comand line should echo to the console now...
		if (php_sapi_name() == "cli") {
			if($level>=$l->level)echo $line.PHP_EOL;
		}

		return "$level $message";
	}

	public static function SHOW($level=LOGGER::INFO){
		$l = static::instance();
		$out = '';
		foreach($l->log as $line){
			if($line['level'] >= $level) $out .= $line['line'].PHP_EOL;
		}
		return $out;
	}

	public static function FLUSH($level=LOGGER::INFO){
		//Capture the current buffer
		$out = static::SHOW($level);
		//clear it out
		$l = static::instance();
		$l->log = array();
		$l->first = $l->utime();
		return $out;
	}

}



function HTML_ERROR_LOG($level=LOGGER::INFO){
	return '<pre>'.TEXT_ERROR_LOG($level).'</pre>';
}

function TEXT_ERROR_LOG($level=LOGGER::INFO){
	return LOGGER::SHOW($level);
}

function STOP($message="") {
	//Development Log Level (no log in production)
	if(!getenv('IS_PRODUCTION')){
		die("<h1>$message</h1><h2>Stack Trace</h2>".STACK());
	}else{
		die("<h1>$message</h1>");
	}
}

function FATAL($message){
	LOGGER::LOG(LOGGER::FATAL,$message);
	STOP($message);
}

function ALERT($message,$rateLimit=1800){
	return LOGGER::LOG(LOGGER::ALERT,$message);
}

function ERROR($message){
	return LOGGER::LOG(LOGGER::ERROR, $message);
}

function WARN($message){
	return LOGGER::LOG(LOGGER::WARN, $message);
}

function INFO($message) {
	return LOGGER::LOG(LOGGER::INFO, $message);
}

function TRACE($message) {
	return LOGGER::LOG(LOGGER::TRACE, $message);
}

function DEBUG($message) {
	return LOGGER::LOG(LOGGER::DEBUG, $message);
}

function TEXT_STACK() {
	ob_start();
	debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	$trace = ob_get_contents();
	ob_end_clean();

	return $trace;
}

function CALLER($verbose=false){
	$stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,3);
	$out = "";
	if(isset($stack[2]['class']))$out .= $stack[2]['class'];
	if(isset($stack[2]['type']))$out .= $stack[2]['type'];
	if(isset($stack[2]['function']))$out .= $stack[2]['function'];
	if($verbose){
		$out .= ' - ';
		if(isset($stack[1]['file']))$out .= $stack[1]['file'];
		$out .= ':';
		if(isset($stack[1]['line']))$out .= $stack[1]['line'];
	}
	return $out;
}

function STACK(){return nl2br(TEXT_STACK());}

function RATE_LIMIT_OKAY($what, $seconds){
	//returns true if greater than $seconds has elapsed since last $what (allowed)
	$rtn = true;

	//Get Last Stamp
	$hash = md5(__METHOD__.$what);
	$oldStamp = Memo::GetCache($hash);
	settype($oldStamp,"integer");

	//Check Threshold
	$now = time();
	if($now < ($oldStamp+$seconds))$rtn = false; //too soon

	//if TRUE Update Stamp
	if($rtn){
		Memo::SetCache($hash,$now);
	}else{
		TRACE(__METHOD__." Too soon for $what limited to once every ".Strings::HumanSeconds($seconds));
	}

	return $rtn;
}


function URL($link, $params = array()) {
	$parse_query = function($q){
		$out = array(); //PHP7 need to supply the destination output array
		parse_str($q,$out);
		return $out;
	};

	$parsed_url = parse_url($link);
	$scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
	$host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
	$port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
	$user     = isset($parsed_url['user']) ? $parsed_url['user'] : '' ;
	$pass     = isset($parsed_url['pass']) ? ':'.$parsed_url['pass'] : '' ;
	$pass     = ($user || $pass) ? "$pass@" : '';
	$path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';

	//Query Strings Are Fun.
	$params = isset($parsed_url['query']) ? array_merge($parse_query($parsed_url['query']),$params) : $params;

	//HACK: SORT UTM near front of url; We Suspect that Google trims URLs and if UTM is late in long URL it is dropped.
	krsort($params);
	$query    = empty($params) ? '' : '?' .http_build_query($params);

	$fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
	$out = "$scheme$user$pass$host$port$path$query$fragment";
	TRACE(__METHOD__." = ".REDACT($out));
	return $out;
}


function REDACT($arr,$allowed=false,$remove=false){
	static $mask;

	//DEV SYSTEMS ARE NOT REDACTED
	if(getenv('IS_DEV' ))return $arr;

	if(!isset($mask)) $mask = '[redacted]';

	$parse_query = function($q){
		$out = array(); //PHP7 need to supply the destination output array
		parse_str($q,$out);
		return $out;
	};

	$is_url = function($url){
		return filter_var($url, FILTER_VALIDATE_URL) ? true : false;
	};

	$redact_url = function ($parsed_url)use($parse_query,&$mask,$allowed,$remove) {
		$oldMask = $mask;
		$mask = '__';
		$scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
		$host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
		$port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
		$user     = isset($parsed_url['user']) ? $mask : '';
		$pass     = isset($parsed_url['pass']) ? ":".$mask : '';
		$pass     = ($user || $pass) ? "$pass@" : '';
		$path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
		$query    = isset($parsed_url['query']) ? '?' .http_build_query(REDACT($parse_query($parsed_url['query']),$allowed,$remove)) : '';
		$fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
		$mask = $oldMask;
		return "$scheme$user$pass$host$port$path$query$fragment";
	};


	$allowed = is_array($allowed) ? $allowed : array();

	//Handy little tool for removing sensitive data
	$blocked = array('name','salutation', 'name_honorific'
		,'firstname','name_first','first_name'
		,'lastname','name_last','last_name'
		,'phone','email','mobile','primary_phone', 'home_phone','email_address','phone_home'
		,'billingstreet','mailingstreet','street_number','address','addressl1','address_street_address','addressl2','street_address'
		,'homeaddress','homecity','homestate','homezip','homephone'
		,'address_unit','apartment_number','appartment_number'
		,'city','billingcity','mailingcity','address_city'
		,'zip','postal','postal_code','address_postal_code','billingpostalcode','mailingpostalcode','pc','zip_code'
		,'dob','birthday','dateofbirth','birthdate'
		,'sin','insurance_no'
		,'gtn','gclid_field','sf_id'
		,'company','website','description','link','actions', 'company_name','job_title'
		,'previous_address','previous_street_number','previous_appartment_number','previous_apartment_number','previous_postal_code','previous_city'
		,'activation_key','agent_code','token','confirmation','api_key','key','access_key'
		,'password_status','password','pass','secret','secret_2fa'
		,'json','json_data'
	);

	$blocked = array_diff($blocked,$allowed);

	if(is_array($arr)){
		foreach($arr as $key => $value){
			if(in_array(mb_strtolower($key),$blocked,true)){
				if($remove){
					unset($arr[$key]);
				}else{
					$arr[$key] = $mask;
				}
			}elseif(is_object($value)||is_array($value)){
				$arr[$key] = REDACT($value,$allowed,$remove);
			}elseif($is_url($value)){
				$arr[$key] = $redact_url($value);
			}
		}
	}elseif(is_object($arr)){
		//Clone it because we are about to change the values referenced, and we want no side effects
		$arr = clone $arr;
		foreach($arr as $key => $value){
			if(in_array(mb_strtolower($key),$blocked,true)){
				if($remove){
					unset($arr->$key);
				}else{
					$arr->$key = $mask;
				}
			}elseif(is_object($value)||is_array($value)){
				$arr->$key = REDACT($value,$allowed,$remove);
			}elseif($is_url($value)){
				$arr->$key = $redact_url($value);
			}
		}

	}elseif($is_url($arr)){
		$arr = $redact_url(parse_url($arr));
	}

	return $arr;
}

function RenderArray($arr,$name='array',$allow=array()){return '<pre>'.doRenderArray(REDACT($arr,$allow),$name).'</pre>';}
function RenderTextArray($arr,$name='array',$allow=array()){return doRenderArray(REDACT($arr,$allow),$name);}

function doRenderArray($arr,$name="array"){
	//Handy little tool for debuggin & displaying multi dimensional arrays / objects
	$out = "\n";

	$prefix = function(&$key)use(&$arr){return is_array($arr) ? "[".$key."]" : "->$key" ;};

	if(is_array($arr)||is_object($arr)){

		foreach($arr as $key => $value){
			if(is_array($value)||is_object($value)){
				$out .= doRenderArray($value,$name.$prefix($key));
			}else if(is_null($value)){
				$out .=  $name.$prefix($key)." = (null)\n";
			}else if(is_bool($value)){
				$out .=  $name.$prefix($key)." = ".($value ? "(bool) true":"(bool) false")."\n";
			}else{
				$out .=  $name.$prefix($key)." = ".$value."\n";
			}
		}

	}else{
		$out .= "$name = $arr\n";
	}
	return $out;
}

function CURL_GET($url, $timeout=10, $options=array() )
{

	$conn = curl_init();

	$logURL = REDACT($url);
	INFO("CURL_GET: $logURL");

	$ssl_host = (getenv("IS_PRODUCTION")) ? 2 : 0 ;
	$ssl_peer = (getenv("IS_PRODUCTION")) ? true : false ;

	$defaults = [
		CURLOPT_URL => $url,
		CURLOPT_FAILONERROR => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS => 5,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER => false,
		CURLOPT_HTTPHEADER => array(),
		CURLOPT_TIMEOUT => $timeout,
		CURLOPT_SSL_VERIFYPEER => $ssl_peer,
		CURLOPT_SSL_VERIFYHOST => $ssl_host
	];

	//Merge any injected Headers with headers needed for this interface
	if(isset($options[CURLOPT_HTTPHEADER]) && is_array($options[CURLOPT_HTTPHEADER])){
		$options[CURLOPT_HTTPHEADER] = array_unique(array_merge($defaults[CURLOPT_HTTPHEADER],$options[CURLOPT_HTTPHEADER]));
	}

	foreach($defaults as $key => $default)$options[$key] = isset($options[$key]) ? $options[$key] : $default;

	foreach ($options as $constantName => $option) curl_setopt($conn, $constantName, $option);

	$result = curl_exec($conn);
	$err = curl_error($conn);
	if($err){
		$result .= " ".$err;
		ERROR("CURL_GET: ERROR on $logURL - $result");
	}
	curl_close($conn);

	return $result;
}

function CURL_POST($url,$params,$timeout=10,$options=array()){
	$logURL = REDACT($url);
	INFO("CURL_POST: $logURL - ".json_encode(REDACT($params)));

	$defaults = [
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => $params,
		CURLOPT_HTTPHEADER => array('Expect: '),
	];

	//Merge any injected Headers with headers needed for this interface
	if(isset($options[CURLOPT_HTTPHEADER]) && is_array($options[CURLOPT_HTTPHEADER])){
		$options[CURLOPT_HTTPHEADER] = array_unique(array_merge($defaults[CURLOPT_HTTPHEADER],$options[CURLOPT_HTTPHEADER]));
	}

	foreach($defaults as $key => $default)$options[$key] = isset($options[$key]) ? $options[$key] : $default;

	return CURL_GET($url,$timeout,$options);
}

function CURL_POST_JSON($url,$params,$timeout=10, $options=array()){
	$logURL = REDACT($url);
	INFO("CURL_POST_JSON: $logURL - ".json_encode(REDACT($params)));
	TRACE("CURL_POST_JSON: $logURL - ".json_encode($params));
	$defaults = [
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => json_encode($params),
		CURLOPT_HTTPHEADER => array('Content-Type: application/json','Expect:'),
	];

	//Merge any injected Headers with headers needed for this interface
	if(isset($options[CURLOPT_HTTPHEADER]) && is_array($options[CURLOPT_HTTPHEADER])){
		$options[CURLOPT_HTTPHEADER] = array_unique(array_merge($defaults[CURLOPT_HTTPHEADER],$options[CURLOPT_HTTPHEADER]));
	}

	foreach($defaults as $key => $default)$options[$key] = isset($options[$key]) ? $options[$key] : $default;

	return CURL_GET($url,$timeout,$options);
}

function CURL_POST_XML($url,$payload,$timeout=10, $options=array()){
	$logURL = REDACT($url);
	$data = new SimpleXMLElement($payload);
	$data = json_decode(json_encode($data),true);
	INFO("CURL_POST_XML: $logURL - ".json_encode(REDACT($data)));
	$defaults = [
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => $payload,
		CURLOPT_HTTPHEADER => array('Content-Type: application/xml','Expect:'),
	];

	//Merge any injected Headers with headers needed for this interface
	if(isset($options[CURLOPT_HTTPHEADER]) && is_array($options[CURLOPT_HTTPHEADER])){
		$options[CURLOPT_HTTPHEADER] = array_unique(array_merge($defaults[CURLOPT_HTTPHEADER],$options[CURLOPT_HTTPHEADER]));
	}

	foreach($defaults as $key => $default)$options[$key] = isset($options[$key]) ? $options[$key] : $default;

	return CURL_GET($url,$timeout,$options);
}

if (!function_exists('array_random')) {
	function array_random($arr, $num=1) {
		//returns random element(s) from the array
		$r = array();
		$idx=null;
		while(($arr = array_values($arr)) && $num>0 ){
			$end = count($arr)-1;
			$idx = mt_rand(0,$end);
			//Add selected item to return array
			$r[] = $arr[$idx];
			//remove this item and repeat
			unset($arr[$idx]);
			//check for exit condition
			if(count($r)==$num)break;
		}
		return (($num==1) && isset($idx)) ? $r[0] : $r;
	}
}

function FIND_KEY_BY_VALUE($haystack,$needle,$caseSensitive=false){
	//Recursive hunt through multi-dimensional array/object to retrieve the value for this key ($needle)
	$out = null;
	$found = false;
	foreach((array)$haystack as $key => $value){
		$value = $caseSensitive ? $value : mb_strtolower($value);
		if(!$found && Strings::Same($value,$needle,$caseSensitive)){
			//Keep looking until a non-empty thing is found
			$found = Strings::isEmpty($key) ? false : true;
			if($found)$out = $key;
		}

		if(!$found && (is_array($value)||is_object($value))){
			//recurse
			$out = FIND_KEY_BY_VALUE($value,$needle,$caseSensitive);
			if($out!==null)$found = true;
		}

		if($found)break; //foreach
	}
	return $out;
}

function FIND_VALUE_BY_KEY($haystack,$needle,$caseSensitive=false){
	//Recursive hunt through multi-dimensional array/object to retrieve the value for this key ($needle)
	$out = null;
	$found = false;
	foreach((array)$haystack as $key => $value){
		$key = $caseSensitive ? $key : mb_strtolower($key);
		if(!$found && Strings::Same($key,$needle,$caseSensitive)){
			//Keep looking until a non-empty thing is found
			$found = Strings::isEmpty($value) ? false : true;
			if($found)$out = $value;
		}

		if(!$found && (is_array($value)||is_object($value))){
			//recurse
			$out = FIND_VALUE_BY_KEY($value,$needle,$caseSensitive);
			if($out!==null)$found = true;
		}

		if($found)break; //foreach
	}
	return $out;
}

function REMOVE_VALUE_BY_KEY(&$haystack,$needle,$caseSensitive=false){
	//Recursive hunt through multi-dimensional array/object to remove the value for this key ($needle)
	if(is_object($haystack)||is_array($haystack)){
		foreach($haystack as $key => $value){
			if(Strings::Same($key,$needle,$caseSensitive)){
				if(is_object($haystack))unset($haystack->$key);
				if(is_array($haystack))unset($haystack[$key]);
			}

			if((is_array($value)||is_object($value))){
				//recurse
				REMOVE_VALUE_BY_KEY($value,$needle,$caseSensitive);
			}
		}
	}
}

function XML2ARRAY($xml){
	if(!is_object($xml)&&is_scalar($xml))$xml = new SimpleXMLElement($xml);
	if(is_array($xml))return $xml;
	$parser = function (SimpleXMLElement $xml, array $collection = []) use (&$parser) {
		$nodes = $xml->children();
		$attributes = $xml->attributes();

		if (0 !== count($attributes)) {
			foreach ($attributes as $attrName => $attrValue) {
				$collection['attributes'][$attrName] = strval($attrValue);
			}
		}

		if (0 === $nodes->count()) {
			$collection['value'] = strval($xml);
			return $collection;
		}

		foreach ($nodes as $nodeName => $nodeValue) {
			if (count($nodeValue->xpath('../' . $nodeName)) < 2) {
				$collection[$nodeName] = $parser($nodeValue);
				continue;
			}

			$collection[$nodeName][] = $parser($nodeValue);
		}

		return $collection;
	};

	return [
		$xml->getName() => $parser($xml)
	];
}

function FIND_XML_VALUE($haystack,$needle,$caseSensitive=false){
	$out = null;
	if($xml = XML2ARRAY($haystack)){
		if($value = FIND_VALUE_BY_KEY($xml,$needle,$caseSensitive))$out = isset($value['value']) ? $value['value'] : FIND_VALUE_BY_KEY($value,'value') ;
	}
	return $out;
}

class DIRTY {
	private static function HASH(){
		return __METHOD__;
	}

	public static function GET($key=null){
		if(Memo::Get(self::HASH())===null){
			$dirty = array();
			$dirty += $_COOKIE;
			$dirty += $_POST;
			$dirty += $_GET;
			$json = json_decode(file_get_contents('php://input'), true);  //JSON Payloads
			if(is_array($json))$dirty += $json;

			INFO(__METHOD__." = ".json_encode(REDACT($dirty)));
			Memo::Set(self::HASH(),$dirty);
		}
		return $key===null ? Memo::Get(self::HASH()) : FIND_VALUE_BY_KEY(Memo::Get(self::HASH()),$key);
	}

	public static function SET($dirty=array(), $replace=false){
		if($replace){
			$orig = $dirty;
		}else{
			//merge
			$orig = static::GET();
			if(is_array($dirty))foreach($dirty as $key =>$value) $orig[$key]=$value;
		}
		Memo::Set(self::HASH(),$orig);
	}

	public static function toSQL($key){return DB_UTILS::Escape(static::GET($key));}
	public static function toInteger($key){return (int)(static::GET($key));}
}

