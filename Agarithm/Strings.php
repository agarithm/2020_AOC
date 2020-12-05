<?php
//////////////////////////////////////////////////////////////////////////////
//
// Copyright Mike Agar - www.agarithm.com
//
// This code is released under MIT License refer to the included license.txt
//
//////////////////////////////////////////////////////////////////////////////

namespace Agarithm;

class Strings {

	public static function isEmpty($needle){
		if($needle===null)return true;
		return (empty($needle) && (is_scalar($needle) ? (mb_strlen(static::Human($needle))==0) : true));
	}

	public static function Shorten($needle,$length=30){
		return mb_strlen($needle) > $length ? mb_substr($needle,0,$length)."..." : $needle;
	}

	public static function Contains($haystack, $needle, $caseSensitive=true) {
		$func = $caseSensitive ? 'mb_strpos' : 'mb_stripos' ;
		return !static::isEmpty($haystack) && !static::isEmpty($needle) && (false !== $func($haystack, $needle));
	}

	public static function Same($haystack, $needle, $caseSensitive=true) {
		$bothEmpty = false;
		$same = true;
		$same &= static::Contains($haystack,$needle,$caseSensitive) ? true : false;
		$same &= static::Contains($needle,$haystack,$caseSensitive) ? true : false;
		if(!$same) $bothEmpty = (static::isEmpty($haystack) && static::isEmpty($needle)) ? true : false;
		return ($same || $bothEmpty) ? true : false;
	}

	public static function StartsWith($haystack, $needle, $caseSensitive=true) {
		// search backwards starting from haystack length characters from the end
		$func = $caseSensitive ? 'mb_strrpos' : 'mb_strripos' ;
		return $needle === "" || $func($haystack, $needle, -mb_strlen($haystack)) !== FALSE;
	}

	public static function EndsWith($haystack, $needle, $caseSensitive=true) {
		// search forward starting from end minus needle length characters
		$func = $caseSensitive ? 'mb_strpos' : 'mb_stripos' ;
		return $needle === "" || (($temp = mb_strlen($haystack) - mb_strlen($needle)) >= 0 && $func($haystack, $needle, $temp) !== FALSE);
	}

	public static function After ($haystack,$needle,$ifMissing="",$caseSensitive=true){return static::AfterFirst($haystack,$needle,$ifMissing,$caseSensitive);}

	public static function AfterFirst ($haystack, $needle,$ifMissing="",$caseSensitive=true){
		$func = $caseSensitive ? 'mb_strpos' : 'mb_stripos' ;
		return (false !== $func($haystack, $needle))   ? mb_substr($haystack, $func($haystack,$needle)+mb_strlen($needle))
			: $ifMissing
			;
	}

	public static function AfterLast ($haystack, $needle,$ifMissing="",$caseSensitive=true){
		$func = $caseSensitive ? 'mb_strrpos' : 'mb_strripos' ;
		return (false !== $func($haystack, $needle))   ? mb_substr($haystack, $func($haystack,$needle)+mb_strlen($needle))
			: $ifMissing
			;
	}

	public static function Before ($haystack,$needle,$ifMissing="",$caseSensitive=true){return static::BeforeFirst($haystack,$needle,$ifMissing,$caseSensitive);}

	public static function BeforeFirst ($haystack, $needle,$ifMissing="",$caseSensitive=true){
		$func = $caseSensitive ? 'mb_strpos' : 'mb_stripos' ;
		return (false !== $func($haystack, $needle))   ? mb_substr($haystack, 0, $func($haystack, $needle))
			: $ifMissing
			;
	}

	public static function BeforeLast ($haystack, $needle,$ifMissing="",$caseSensitive=true){
		$func = $caseSensitive ? 'mb_strrpos' : 'mb_strripos' ;
		return (false !== $func($haystack, $needle))   ? mb_substr($haystack, 0, $func($haystack, $needle))
			: $ifMissing
			;
	}

	public static function Between ($haystack, $needle1, $needle2, $caseSensitive=true){
		return static::Before(static::After($haystack,$needle1,'',$caseSensitive),$needle2,'',$caseSensitive);
	}

	public static function BetweenNested ($haystack, $needle1, $needle2, $caseSensitive=true){
		return static::AfterLast(static::Before($haystack,$needle2,'',$caseSensitive),$needle1,'',$caseSensitive);
	}


	public static function ReplaceAll($needle,$replace,$haystack,$caseSensitive=true){
		$out = $haystack;
		$func = $caseSensitive ? 'str_replace' : 'str_ireplace' ;
		do{
			$old = $out;
			$out = $func($needle,$replace,$out);
		}while($old != $out);

		return $out;
	}

	public static function Trim($haystack){
		//removes all redundant whitespace within and from the outsides.
		return trim(preg_replace('/\s+/', ' ',$haystack));
	}

	public static function toNumber($haystack){
		$haystack = static::Trim($haystack);
		$sign = static::StartsWith($haystack,'-') ? -1 : 1;
		$sign = ($sign > 0 && static::EndsWith($haystack,'-')) ? -1 : 1;
		$dotPos = mb_strrpos($haystack, '.');
		$commaPos = mb_strrpos($haystack, ',');
		if($dotPos !== false
			&& $commaPos !==false
			&& $commaPos > $dotPos){
			//Rare: decimal sep = ',' detected
			TRACE(__METHOD__." COMMA SEP $haystack ".__LINE__);
			$deciSep = $commaPos;
		}elseif($dotPos===false
			&& $commaPos != false
			&& strlen(static::AfterLast($haystack,','))!=3){
			//',' is not in a valid thousands sep postion, so treat as deci sep
			TRACE(__METHOD__." COMMA SEP $haystack ".__LINE__." ".static::AfterLast($haystack,',').'='.strlen(static::AfterLast($haystack,',')));
			$deciSep = $commaPos;
		}elseif($dotPos===false && $commaPos!=false){
			//',' found but ambigous and expect to treat as thousand sep
			// so return integer
			TRACE(__METHOD__." INTEGER $haystack ".__LINE__);
			return $sign * intval(preg_replace("/\D/", "", $haystack));
		}elseif($dotPos!==false){
			//still hunting, must be '.' deci sep
			TRACE(__METHOD__." DOT SEP $haystack ".__LINE__);
			$deciSep = $dotPos;
		}else{
			//no matching case, return integer
			return $sign * intval(preg_replace("/\D/", "", $haystack));
		}

		//Still Here? must have detected proper deci sep.
		return $sign * floatval(
			preg_replace("/\D/", "", mb_substr($haystack, 0, $deciSep)) . '.' .
			preg_replace("/\D/", "", mb_substr($haystack, $deciSep+1, mb_strlen($haystack)))
		);

	}

	public static function Human($value){
		$out = $value;
		if ($value===true) $out = "true";
		if ($value===false) $out = "false";
		if ($value===null) $out = "null";
		if (is_array($value)) $out = static::Trim(json_encode($value));
		if (is_object($value)) $out = static::Trim(json_encode($value));
		return $out;
	}

	public static function HumanSeconds($seconds){
		settype($seconds, "integer");
		$thres = 2;
		$i18n = function($units){
			//LANG("years");
			//LANG("months");
			//LANG("days");
			//LANG("hours");
			//LANG("minutes");
			//LANG("seconds");
			return function_exists("LANG") ? " ".LANG($units) : " $units";
		};
		if($seconds > $thres*365*24*60*60)$seconds = ((int)($seconds/(365*24*60*60))).$i18n("years");
		else if($seconds > $thres*30*24*60*60)$seconds = ((int)($seconds/(30*24*60*60))).$i18n("months");
		else if($seconds > $thres*7*24*60*60)$seconds = ((int)($seconds/(7*24*60*60))).$i18n("weeks");
		else if($seconds > $thres*24*60*60)$seconds = ((int)($seconds/(24*60*60))).$i18n("days");
		else if($seconds > $thres*60*60)$seconds = ((int)($seconds/(60*60))).$i18n("hours");
		else if($seconds > $thres*60)$seconds = ((int)($seconds/(60))).$i18n("minutes");
		else $seconds = ($seconds).$i18n("seconds");
		return $seconds;
	}

	public static function HumanBytes($bytes){
		settype($bytes, "integer");
		$thres = 2;
		if($bytes > $thres*1024*1024*1024*1024*1024)$bytes = ((int)($bytes/(1024*1024*1024*1024*1024)))." PB";
		else if($bytes > $thres*1024*1024*1024*1024)$bytes = ((int)($bytes/(1024*1024*1024*1024)))." TB";
		else if($bytes > $thres*1024*1024*1024)$bytes = ((int)($bytes/(1024*1024*1024)))." GB";
		else if($bytes > $thres*1024*1024)$bytes = ((int)($bytes/(1024*1024)))." MB";
		else if($bytes > $thres*1024)$bytes = ((int)($bytes/(1024)))." KB";
		else $bytes = ($bytes)." B";
		return $bytes;
	}

	public static function HumanPercent($percent,$decimals=2){
		return number_format($percent*100,$decimals)." %";
	}

	public static function HumanBin2Hex($data,$columns=16){
		$bytePosition = $columnCount = $lineCount = 0;
		$columns = max(8,floor($columns/8)*8);
		$dataLength = strlen($data);
		$return = array();
		$return[] = '<table border="1" cellspacing="0" cellpadding="2">';
		for($n = 0; $n < $dataLength; $n++){
			$lines[$lineCount][$columnCount++] = substr($data, $n, 1);
			if($columnCount == $columns){
				$lineCount++;
				$columnCount = 0;
			}
		}
		foreach($lines as $line){
			$return[] = '<tr><td align="right"><strong>'.$bytePosition.':</strong> </td>';
			for($n = 0; $n < $columns; $n++){
				if($n&&($n)%8==0)$return[] = "<td>&nbsp;&nbsp;&nbsp;</td>";
				$return[] = isset($line[$n]) ? '<td align="center">'.strtoupper(bin2hex($line[$n])).'</td>' : '<td></td>';
			}
			$return[] = '</tr>';
			$bytePosition = $bytePosition + $columns;
		}
		$return[] = '</table>';
		return implode('', $return);
	}



	public static function HumanCents($cents){
		return $cents<0 ? "-$".number_format(1.0*abs($cents)/100.0, 2) : "$".number_format(1.0*$cents/100.0, 2);
	}

}


if (!function_exists('mb_split_str')) {
	function mb_split_str ($str)
	{
		preg_match_all("/./u", $str, $arr);

		return $arr[0];
	}
}

if (!function_exists('mb_similar_text')) {
	function mb_similar_text ($str1, $str2, &$percent, $caseSensitive = true)
	{
		if (!$caseSensitive) {
			$str1 = mb_strtolower($str1);
			$str2 = mb_strtolower($str2);
		}

		if ($str1 === $str2) {
			$similarity = mb_strlen($str1);
			$percent = 100;
		} else {
			$arr_1 = array_unique(mb_split_str($str1));
			$arr_2 = array_unique(mb_split_str($str2));
			$similarity = count($arr_2) - count(array_diff($arr_2, $arr_1));
			$percent = ($similarity * 200) / (strlen($str1) + strlen($str2));
		}

		return $similarity;
	}
}

if (!function_exists('mb_str_pad')) {
	function mb_str_pad ($input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT, $encoding = 'UTF-8')
	{

		$pad_length = strlen($input) - mb_strlen($input, $encoding) + $pad_length;

		return str_pad($input, $pad_length, $pad_string, $pad_type);
	}
}


