<?php
//////////////////////////////////////////////////////////////////////////////
// Scraper v4.0.0, August 23, 2016
// by:  Mike Agar
//      www.agarithm.com
//////////////////////////////////////////////////////////////////////////////
// This code is released under MIT License refer to the included license.txt
//////////////////////////////////////////////////////////////////////////////

namespace Agarithm;

class Scraper {

	public static function Test(){

$page = <<<___TEST_PAGE
<html>
<head>Stuff in the HEAD<head>
<body>

Think of the Scraper as a "Reverse Template Engine".  You supply a page and a template
and the scraper will return an array of data that would have solved this equation:

	DATA * TEMPLATE = PAGE


So here is a simple bit of HTML to test with:

	<div class="title"><h1>Title</h1></div>

	<div class="list"><li>Item 0</div>
	<div class="list"><li>Item 1</div>
	<div class="list"><li>Item 2</div>
	<div class="list"><li>Item 3</div>
	<div class="list"><li>Item 4</div>
	<div class="list"><li>Item 5</div>

	<div class="footer">Footer</div>


The Template Syntax is fairly straight forward: the scraper will perform basic pattern
matching to find the data that is represented by "{KEY_NAME}" in the supplied template.

There are three Public Methods:
	Scrape(pageData,scraperTemplate) - Applies the template once
	Repeat(pageData,scraperTemplate) - Repeats the template until no pattern match is found
	Test() - performs unit tests and returns "PASS" on success.

Both methods return an associative array of the data extracted where the template variable names
are the keys to the output data array. In the case of Repeat() the output will be a
multidimensional array.

There is one reserved named template variable: {null}.  The {null} placeholder is provided
for you to dump vast sections of HTML or other data and not have it returned in the output array.
This is a convenience so that you can make simpler scraper templates that are more readable.

See two sample scraper templates below.

</body>
</html>
___TEST_PAGE;


$scrapeTemplate = <<<___SCRAPE
{null}<h1>{TITLE}</h1>
{null}<div class="footer">{FOOTER}</div>
{null}
___SCRAPE;

$repeatTemplate = <<<___REPEAT
{null}<div class="list"><li>{LIST}</div>
___REPEAT;


		//TEST CASE 1: Scrape
		$output = static::Scrape($page,$scrapeTemplate);
		if($output["TITLE"] != "Title")die("FAIL: Did not extract the Title\n");
		if($output["FOOTER"] != "Footer")die("FAIL: Did not extract the Footer\n");

		//TEST CASE 2: Repeat
		$output = static::Repeat($page,$repeatTemplate);
		if(count($output)!=6)die("FAIL: Did not extract the proper number of repeated elements\n");
		if($output[2]["LIST"] != "Item 2")die("FAIL: Did not extract the expected repeated value\n");

		//If not dead, must have passed
		return get_class().": PASS\n";
	}


	public static function Scrape($page,$template){
		return static::DoIt($page,$template,false);
	}

	public static function Repeat($page,$template){
		return static::DoIt($page,$template,true);
	}


	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	private static function DoIt($page,$template,$repeat){
		TRACE(__METHOD__." START");
		$stripLineEnds = function($in){
			$in = str_replace("\n","",$in);
			$in = str_replace("\r","",$in);
			$in = str_replace("\t","",$in);
			return $in;
		};

		$pagePtr = $stripLineEnds($page);
		$template = $stripLineEnds($template);
		$err = false; //all is fine
		$patterns = explode("{",$template);
		$start = 0;
		$loop = 0;
		$end = count($patterns);
		$output = array();
		$bulkOutput = array();

		$close = function($haystack, $needle) use (&$pagePtr, &$err){
			$pagePtr = $haystack;
			if(strpos($haystack,$needle)!== FALSE){
				//found
				$needleLength = strlen($needle);
				$output  = substr($haystack,0,strpos($haystack,$needle));
				$pagePtr = substr($haystack,strlen($needle)+strpos($haystack,$needle));
				return $output;
			}else{
				//not found
				DEBUG("Scraper::DoIt::close() - haystack = ".Strings::Shorten(HTMLspecialchars($haystack)));
				DEBUG("Scraper::DoIt::close() - needle   = ".Strings::Shorten(HTMLspecialchars($needle)));
				DEBUG("Scraper::DoIt::close() - Not Found");
				$err = true;
				$pagePtr = "";
				return "";
			}
		};

		$open = function ($needle, $haystack) use (&$pagePtr, &$err){
			$pagePtr = $haystack;
			if(strpos($haystack,$needle)!== FALSE){
				//found
				$output  = substr($haystack,strlen($needle)+strpos($haystack,$needle));
				$pagePtr = $output;
				return $output;
			}else{
				//not found
				DEBUG("Scraper::DoIt::open() - haystack = ".Strings::Shorten(HTMLspecialchars($haystack)));
				DEBUG("Scraper::DoIt::open() - needle   = ".Strings::Shorten(HTMLspecialchars($needle)));
				DEBUG("Scraper::DoIt::open() - Not Found");
				$err = true;
				$pagePtr = "";
				return "";
			}
		};

		$resetStateMachine = function() use (&$patterns, &$start, &$state, &$index, &$outputi, &$loop){
			$start = 0;
			$output = array();
			if(strstr($patterns[0],"}")){
				//first pattern is a variable
				$state = "capture";
			}else{
				//first pattern is an index
				if(strlen($patterns[0])>0){
					$state = "index";
					$index = $patterns[0];
				}else{
					$state = "capture";
					$start = 1;
				}
			}
			$loop=$start;
		};

		$resetStateMachine();

		$dontStop = $repeat;
		for (;$loop<$end||$dontStop;){//we increment $loop within the body.  Bad Form... I know.
			DEBUG(__METHOD__." state=$state");
			switch($state){
				case "index":
					$pagePtr = $open($index,$pagePtr);
					$loop++;
					$state = "capture";
					break;
				case "capture":
					$pieces = explode("}",$patterns[$loop],2);
					$cnt = @count($pieces);
					if($cnt == 2){
						//have var name and next pattern...
						if(strlen($pieces[1])>=1){
							$output[$pieces[0]] = $close($pagePtr,$pieces[1]);
						}else{
							//last one...
							$output[$pieces[0]] = $pagePtr;
							$err = 0;
						}
						$index = $output[$pieces[0]];
						$loop++;
					}else{
						ERROR(__METHOD__." expecting two parts during capture");
						$err = true;
						$output[$pattern[$loop]] = $pagePtr;
					}
					break;
				default:
					$err = true;
			}

			if(isset($output["null"]))unset($output["null"]);

			if($err){
				$loop = $end;
				$dontStop = false; //STOP
			}elseif($repeat && $loop>=$end && count($output)>0 ){
				//append data to Bulk Output
				$bulkOutput[] = $output;
				$resetStateMachine();
			}
		}
		//so now $output array is populated...
		$out = $repeat ? $bulkOutput : $output;
		TRACE(__METHOD__." END count() = ".count($out));
		return $out;
	}
}
