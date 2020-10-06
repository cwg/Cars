<?php
/*
 * Plugin Name: Cars
 * Plugin URI: https://staging.tedpenner.com
 * Description: Get the latest pre-sales & post-sales car data from Manheim and Alliance for the Waco region.
 * Rev Mon 2020.09.28 by TP @6:10 pm Central Time
 * Central Time Zone: https://time.is/CT
 * The current problem is that we cannot get API data from either allianceautoauction.com or manheim.com and must log-in algorithmically to scrape the data instead of using a proper API key. We have this process working with Alliance but we are not getting authenticated properly at Manheim and are not able to log-in programatically yet like we can at Alliance. We need help resolving this issue.
 
* This is a page to pre-sales data at Manheim in Dallas - This page will expire.
https://www.manheim.com/members/presale/control/vehicleList?auctionID=DALA&saleDate=20201006&saleYear=2020&saleNumber=41&country=USA&locale=en_US&format=enhanced

* This is a  page to pre-sales data at Alliance in Waco - This page will expire.
http://dealers.allianceautoauction.com/components/report/presale/event_list/alliwaco
 
 */
 
 /* hhb_inc.php is included here inline */
declare(strict_types = 1);
/**
 * convert any string to valid HTML, as losslessly as possible, assuming UTF-8
 *
 * @param string $str
 * @return string
 */
function hhb_tohtml(string $str): string {
	return htmlentities ( $str, ENT_QUOTES | ENT_HTML401 | ENT_SUBSTITUTE | ENT_DISALLOWED, 'UTF-8', true );
}
/**
 * enhanced var_dump
 *
 * @param mixed $mixed...
 * @return void
 */
function hhb_var_dump() {
	// informative wrapper for var_dump
	// <changelog>
	// version 5 ( 1372510379573 )
	// v5, fixed warnings on PHP < 5.0.2 (PHP_EOL not defined),
	// also we can use xdebug_var_dump when available now. tested working with 5.0.0 to 5.5.0beta2 (thanks to http://viper-7.com and http://3v4l.org )
	// and fixed a (corner-case) bug with "0" (empty() considders string("0") to be empty, this caused a bug in sourcecode analyze)
	// v4, now (tries to) tell you the source code that lead to the variables
	// v3, HHB_VAR_DUMP_START and HHB_VAR_DUMP_END .
	// v2, now compat with.. PHP5.0 + i think? tested down to 5.2.17 (previously only 5.4.0+ worked)
	// </changelog>
	// <settings>
	$settings = array ();
	$PHP_EOL = "\n";
	if (defined ( 'PHP_EOL' )) { // for PHP >=5.0.2 ...
		$PHP_EOL = PHP_EOL;
	}
	
	$settings ['debug_hhb_var_dump'] = false; // if true, may throw exceptions on errors..
	$settings ['use_xdebug_var_dump'] = true; // try to use xdebug_var_dump (instead of var_dump) if available?
	$settings ['analyze_sourcecode'] = true; // false to disable the source code analyze stuff.
	                                         // (it will fallback to making $settings['analyze_sourcecode']=false, if it fail to analyze the code, anyway..)
	$settings ['hhb_var_dump_prepend'] = 'HHB_VAR_DUMP_START' . $PHP_EOL;
	$settings ['hhb_var_dump_append'] = 'HHB_VAR_DUMP_END' . $PHP_EOL;
	// </settings>
	
	$settings ['use_xdebug_var_dump'] = ($settings ['use_xdebug_var_dump'] && is_callable ( "xdebug_var_dump" ));
	$argv = func_get_args ();
	$argc = count ( $argv, COUNT_NORMAL );
	if (version_compare ( PHP_VERSION, '5.4.0', '>=' )) {
		$bt = debug_backtrace ( DEBUG_BACKTRACE_IGNORE_ARGS, 1 );
	} else if (version_compare ( PHP_VERSION, '5.3.6', '>=' )) {
		$bt = debug_backtrace ( DEBUG_BACKTRACE_IGNORE_ARGS );
	} else if (version_compare ( PHP_VERSION, '5.2.5', '>=' )) {
		$bt = debug_backtrace ( false );
	} else {
		$bt = debug_backtrace ();
	}
	;
	$analyze_sourcecode = $settings ['analyze_sourcecode'];
	// later, $analyze_sourcecode will be compared with $config['analyze_sourcecode']
	// to determine if the reason was an error analyzing, or if it was disabled..
	$bt = $bt [0];
	// <analyzeSourceCode>
	if ($analyze_sourcecode) {
		$argvSourceCode = array (
				0 => 'ignore [0]...' 
		);
		try {
			if (version_compare ( PHP_VERSION, '5.2.2', '<' )) {
				throw new Exception ( "PHP version is <5.2.2 .. see token_get_all changelog.." );
			}
			;
			$xsource = file_get_contents ( $bt ['file'] );
			if (empty ( $xsource )) {
				throw new Exception ( 'cant get the source of ' . $bt ['file'] );
			}
			;
			$xsource .= "\n<" . '?' . 'php ignore_this_hhb_var_dump_workaround();'; // workaround, making sure that at least 1 token is an array, and has the $tok[2] >= line of hhb_var_dump
			$xTokenArray = token_get_all ( $xsource );
			// <trim$xTokenArray>
			$tmpstr = '';
			$tmpUnsetKeyArray = array ();
			ForEach ( $xTokenArray as $xKey => $xToken ) {
				if (is_array ( $xToken )) {
					if (! array_key_exists ( 1, $xToken )) {
						throw new LogicException ( 'Impossible situation? $xToken is_array, but does not have $xToken[1] ...' );
					}
					$tmpstr = trim ( $xToken [1] );
					if (empty ( $tmpstr ) && $tmpstr !== '0' /*string("0") is considered "empty" -.-*/ ) {
						$tmpUnsetKeyArray [] = $xKey;
						continue;
					}
					;
					switch ($xToken [0]) {
						case T_COMMENT :
						case T_DOC_COMMENT : // T_ML_COMMENT in PHP4 -.-
						case T_INLINE_HTML :
							{
								$tmpUnsetKeyArray [] = $xKey;
								continue 2;
							}
							;
						default :
							{
								continue 2;
							}
					}
				} else if (is_string ( $xToken )) {
					$tmpstr = trim ( $xToken );
					if (empty ( $tmpstr ) && $tmpstr !== '0' /*string("0") is considered "empty" -.-*/ ) {
						$tmpUnsetKeyArray [] = $xKey;
					}
					;
					continue;
				} else {
					// should be unreachable..
					// failed both is_array() and is_string() ???
					throw new LogicException ( 'Impossible! $xToken fails both is_array() and is_string() !! .. ' );
				}
				;
			}
			;
			ForEach ( $tmpUnsetKeyArray as $toUnset ) {
				unset ( $xTokenArray [$toUnset] );
			}
			;
			$xTokenArray = array_values ( $xTokenArray ); // fixing the keys..
			                                              // die(var_dump('die(var_dump(...)) in '.__FILE__.':'.__LINE__,'before:',count(token_get_all($xsource),COUNT_NORMAL),'after',count($xTokenArray,COUNT_NORMAL)));
			unset ( $tmpstr, $xKey, $xToken, $toUnset, $tmpUnsetKeyArray );
			// </trim$xTokenArray>
			$firstInterestingLineTokenKey = - 1;
			$lastInterestingLineTokenKey = - 1;
			// <find$lastInterestingLineTokenKey>
			ForEach ( $xTokenArray as $xKey => $xToken ) {
				if (! is_array ( $xToken ) || ! array_key_exists ( 2, $xToken ) || ! is_integer ( $xToken [2] ) || $xToken [2] < $bt ['line'])
					continue;
				$tmpkey = $xKey; // we don't got what we want yet..
				while ( true ) {
					if (! array_key_exists ( $tmpkey, $xTokenArray )) {
						throw new Exception ( '1unable to find $lastInterestingLineTokenKey !' );
					}
					;
					if ($xTokenArray [$tmpkey] === ';') {
						// var_dump(__LINE__.":SUCCESS WITH",$tmpkey,$xTokenArray[$tmpkey]);
						$lastInterestingLineTokenKey = $tmpkey;
						break;
					}
					// var_dump(__LINE__.":FAIL WITH ",$tmpkey,$xTokenArray[$tmpkey]);
					
					// if $xTokenArray has >=PHP_INT_MAX keys, we don't want an infinite loop, do we? ;p
					// i wonder how much memory that would require though.. over-engineering, err, time-wasting, ftw?
					if ($tmpkey >= PHP_INT_MAX) {
						throw new Exception ( '2unable to find $lastIntperestingLineTokenKey ! (PHP_INT_MAX reached without finding ";"...)' );
					}
					;
					++ $tmpkey;
				}
				break;
			}
			;
			if ($lastInterestingLineTokenKey <= - 1) {
				throw new Exception ( '3unable to find $lastInterestingLineTokenKey !' );
			}
			;
			unset ( $xKey, $xToken, $tmpkey );
			// </find$lastInterestingLineTokenKey>
			// <find$firstInterestingLineTokenKey>
			// now work ourselves backwards from $lastInterestingLineTokenKey to the first token where $xTokenArray[$tmpi][1] == "hhb_var_dump"
			// i doubt this is fool-proof but.. cant think of a better way (in userland, anyway) atm..
			$tmpi = $lastInterestingLineTokenKey;
			do {
				if (array_key_exists ( $tmpi, $xTokenArray ) && is_array ( $xTokenArray [$tmpi] ) && array_key_exists ( 1, $xTokenArray [$tmpi] ) && is_string ( $xTokenArray [$tmpi] [1] ) && strcasecmp ( $xTokenArray [$tmpi] [1], $bt ['function'] ) === 0) {
					// var_dump(__LINE__."SUCCESS WITH",$tmpi,$xTokenArray[$tmpi]);
					if (! array_key_exists ( $tmpi + 2, $xTokenArray )) { // +2 because [0] is (or should be) "hhb_var_dump" and [1] is (or should be) "("
						throw new Exception ( '1unable to find the $firstInterestingLineTokenKey...' );
					}
					;
					$firstInterestingLineTokenKey = $tmpi + 2;
					break;
					/* */
				}
				;
				// var_dump(__LINE__."FAIL WITH ",$tmpi,$xTokenArray[$tmpi]);
				-- $tmpi;
			} while ( - 1 < $tmpi );
			// die(var_dump('die(var_dump(...)) in '.__FILE__.':'.__LINE__,$tmpi));
			if ($firstInterestingLineTokenKey <= - 1) {
				throw new Exception ( '2unable to find the $firstInterestingLineTokenKey...' );
			}
			;
			unset ( $tmpi );
			// Note: $lastInterestingLineTokeyKey is likely to contain more stuff than only the stuff we want..
			// </find$firstInterestingLineTokenKey>
			// <rebuildInterestingSourceCode>
			// ok, now we have $firstInterestingLineTokenKey and $lastInterestingLineTokenKey....
			$interestingTokensArray = array_slice ( $xTokenArray, $firstInterestingLineTokenKey, (($lastInterestingLineTokenKey - $firstInterestingLineTokenKey) + 1) );
			unset ( $addUntil, $tmpi, $tmpstr, $tmpi, $argvsourcestr, $tmpkey, $xTokenKey, $xToken );
			$addUntil = array ();
			$tmpi = 0;
			$tmpstr = "";
			$tmpkey = "";
			$argvsourcestr = "";
			// $argvSourceCode[X]='source code..';
			ForEach ( $interestingTokensArray as $xTokenKey => $xToken ) {
				if (is_array ( $xToken )) {
					$tmpstr = $xToken [1];
					// var_dump($xToken[1]);
				} else if (is_string ( $xToken )) {
					$tmpstr = $xToken;
					// var_dump($xToken);
				} else {
					/* should never reach this */
					throw new LogicException ( 'Impossible situation? $xToken fails is_array() and fails is_string() ...' );
				}
				;
				$argvsourcestr .= $tmpstr;
				
				if ($xToken === '(') {
					$addUntil [] = ')';
					continue;
				} else if ($xToken === '[') {
					$addUntil [] = ']';
					continue;
				}
				;
				
				if ($xToken === ')' || $xToken === ']') {
					if (false === ($tmpkey = array_search ( $xToken, $addUntil, false ))) {
						$argvSourceCode [] = substr ( $argvsourcestr, 0, - 1 ); // -1 is to strip the ")"
						if (count ( $argvSourceCode, COUNT_NORMAL ) - 1 === $argc) /*-1 because $argvSourceCode[0] is bullshit*/ {
							break;
							/* We read em all! :D (.. i hope) */
						}
						;
						/* else... oh crap */
						throw new Exception ( 'failed to read source code of (what i think is) argv[' . count ( $argvSourceCode, COUNT_NORMAL ) . '] ! sorry..' );
					}
					unset ( $addUntil [$tmpkey] );
					continue;
				}
				
				if (empty ( $addUntil ) && $xToken === ',') {
					$argvSourceCode [] = substr ( $argvsourcestr, 0, - 1 ); // -1 is to strip the comma
					$argvsourcestr = "";
				}
				;
			}
			;
			// die(var_dump('die(var_dump(...)) in '.__FILE__.':'.__LINE__,
			// $firstInterestingLineTokenKey,$lastInterestingLineTokenKey,$interestingTokensArray,$tmpstr
			// $argvSourceCode));
			if (count ( $argvSourceCode, COUNT_NORMAL ) - 1 != $argc) /*-1 because $argvSourceCode[0] is bullshit*/ {
				throw new Exception ( 'failed to read source code of all the arguments! (and idk which ones i missed)! sorry..' );
			}
			;
			// </rebuildInterestingSourceCode>
		} catch ( Exception $ex ) {
			$argvSourceCode = array (); // clear it
			                            // TODO: failed to read source code
			                            // die("TODO N STUFF..".__FILE__.__LINE__);
			$analyze_sourcecode = false; // ERROR..
			if ($settings ['debug_hhb_var_dump']) {
				throw $ex;
			} else {
				/* exception ignored, continue as normal without $analyze_sourcecode */
			}
			;
		}
		unset ( $xsource, $xToken, $xTokenArray, $firstInterestingLineTokenKey, $lastInterestingLineTokenKey, $xTokenKey, $tmpi, $tmpkey, $argvsourcestr );
	}
	;
	// </analyzeSourceCode>
	$msg = $settings ['hhb_var_dump_prepend'];
	if ($analyze_sourcecode != $settings ['analyze_sourcecode']) {
		$msg .= ' (PS: some error analyzing source code)' . $PHP_EOL;
	}
	;
	$msg .= 'in "' . $bt ['file'] . '": on line "' . $bt ['line'] . '": ' . $argc . ' variable' . ($argc === 1 ? '' : 's') . $PHP_EOL; // because over-engineering ftw?
	if ($analyze_sourcecode) {
		$msg .= ' hhb_var_dump(';
		$msg .= implode ( ",", array_slice ( $argvSourceCode, 1 ) ); // $argvSourceCode[0] is bullshit.
		$msg .= ')' . $PHP_EOL;
	}
	// array_unshift($bt,$msg);
	echo $msg;
	$i = 0;
	foreach ( $argv as &$val ) {
		echo 'argv[' . ++ $i . ']';
		if ($analyze_sourcecode) {
			echo ' >>>' . $argvSourceCode [$i] . '<<<';
		}
		echo ':';
		if ($settings ['use_xdebug_var_dump']) {
			xdebug_var_dump ( $val );
		} else {
			var_dump ( $val );
		}
		;
	}
	
	echo $settings ['hhb_var_dump_append'];
	// call_user_func_array("var_dump",$args);
}
/**
 * works like var_dump, but returns a string instead of priting it (ob_ based)
 *
 * @param mixed $args...
 * @return string
 */
function hhb_return_var_dump(): string // works like var_dump, but returns a string instead of printing it.
{
	$args = func_get_args (); // for <5.3.0 support ...
	ob_start ();
	call_user_func_array ( 'var_dump', $args );
	return ob_get_clean ();
}
/**
 * convert a binary string to readable ascii...
 *
 * @param string $data
 * @param int $min_text_len
 * @param int $readable_min
 * @param int $readable_max
 * @return string
 */
function hhb_bin2readable(string $data, int $min_text_len = 3, int $readable_min = 0x40, int $readable_max = 0x7E): string { // TODO: better output..
	$ret = "";
	$strbuf = "";
	$i = 0;
	for($i = 0; $i < strlen ( $data ); ++ $i) {
		if ($min_text_len > 0 && ord ( $data [$i] ) >= $readable_min && ord ( $data [$i] ) <= $readable_max) {
			$strbuf .= $data [$i];
			continue;
		}
		if (strlen ( $strbuf ) >= $min_text_len && $min_text_len > 0) {
			$ret .= " " . $strbuf . " ";
		} elseif (strlen ( $strbuf ) > 0 && $min_text_len > 0) {
			$ret .= bin2hex ( $strbuf );
		}
		$strbuf = "";
		$ret .= bin2hex ( $data [$i] );
	}
	if (strlen ( $strbuf ) >= $min_text_len && $min_text_len > 0) {
		$ret .= " " . $strbuf . " ";
	} elseif (strlen ( $strbuf ) > 0 && $min_text_len > 0) {
		$ret .= bin2hex ( $strbuf );
	}
	$strbuf = "";
	return $ret;
}
/**
 * enables hhb_exception_handler and hhb_assert_handler and sets error_reporting to max
 */
function hhb_init() {
	static $firstrun = true;
	if ($firstrun !== true) {
		return;
	}
	$firstrun = false;
	error_reporting ( E_ALL );
	set_error_handler ( "hhb_exception_error_handler" );
	// ini_set("log_errors",'On');
	// ini_set("display_errors",'On');
	// ini_set("log_errors_max_len",'0');
	// ini_set("error_prepend_string",'<error>');
	// ini_set("error_append_string",'</error>'.PHP_EOL);
	// ini_set("error_log",__DIR__.DIRECTORY_SEPARATOR.'error_log.php.txt');
	assert_options ( ASSERT_ACTIVE, 1 );
	assert_options ( ASSERT_WARNING, 0 );
	assert_options ( ASSERT_QUIET_EVAL, 1 );
	assert_options ( ASSERT_CALLBACK, 'hhb_assert_handler' );
}
function hhb_exception_error_handler($errno, $errstr, $errfile, $errline) {
	if (! (error_reporting () & $errno)) {
		// This error code is not included in error_reporting
		return;
	}
	throw new ErrorException ( $errstr, 0, $errno, $errfile, $errline );
}
function hhb_assert_handler($file, $line, $code, $desc = null) {
	$errstr = 'Assertion failed at ' . $file . ':' . $line . ' ' . $desc . ' code: ' . $code;
	throw new ErrorException ( $errstr, 0, 1, $file, $line );
}
function hhb_combine_filepaths( /*...*/ ):string {
	$args = func_get_args ();
	if (count ( $args ) == 0) {
		return "";
	}
	$ret = "";
	$i = 0;
	foreach ( $args as $arg ) {
		++ $i;
		if ($i != 1) {
			$ret .= DIRECTORY_SEPARATOR;
		}
		$ret .= str_replace ( (DIRECTORY_SEPARATOR === '/' ? '\\' : '/'), DIRECTORY_SEPARATOR, $arg ) . DIRECTORY_SEPARATOR;
	}
	while ( false !== stripos ( $ret, DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR ) ) {
		$ret = str_replace ( DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $ret );
	}
	if (strlen ( $ret ) < 2) {
		return $ret; // edge case: a single DIRECTORY_SEPARATOR empty
	}
	if ($ret [strlen ( $ret ) - 1] === DIRECTORY_SEPARATOR) {
		$ret = substr ( $ret, 0, - 1 );
	}
	return $ret;
}
class hhb_curl {
	protected $curlh;
	protected $curloptions = [ ];
	protected $response_body_file_handle; // CURLOPT_FILE
	protected $response_headers_file_handle; // CURLOPT_WRITEHEADER
	protected $request_body_file_handle; // CURLOPT_INFILE
	protected $stderr_file_handle; // CURLOPT_STDERR
	protected function truncateFileHandles() {
		$trun = ftruncate ( $this->response_body_file_handle, 0 );
		assert ( true === $trun );
		$trun = ftruncate ( $this->response_headers_file_handle, 0 );
		assert ( true === $trun );
		// $trun = ftruncate ( $this->request_body_file_handle, 0 );
		// assert ( true === $trun );
		$trun = ftruncate ( $this->stderr_file_handle, 0 );
		assert ( true === $trun );
		return /*true*/;
	}
	/**
	 * returns the internal curl handle
	 *
	 * its probably a bad idea to mess with it, you'll probably never want to use this function.
	 *
	 * @return resource_curl
	 */
	public function _getCurlHandle()/*: curlresource*/ {
		return $this->curlh;
	}
	/**
	 * replace the internal curl handle with another one...
	 *
	 * its probably a bad idea. you'll probably never want to use this function.
	 *
	 * @param resource_curl $newcurl
	 * @param bool $closeold
	 * @throws InvalidArgumentsException
	 *
	 * @return void
	 */
	public function _replaceCurl($newcurl, bool $closeold = true) {
		if (! is_resource ( $newcurl )) {
			throw new InvalidArgumentsException ( 'parameter 1 must be a curl resource!' );
		}
		if (get_resource_type ( $newcurl ) !== 'curl') {
			throw new InvalidArgumentsException ( 'parameter 1 must be a curl resource!' );
		}
		if ($closeold) {
			if(true){
				// workaround for https://bugs.php.net/bug.php?id=78007
				curl_setopt_array( $this->curlh, array(CURLOPT_VERBOSE=>0,CURLOPT_URL=>null));
				curl_exec($this->curlh);
			}
			curl_close ( $this->curlh );
		}
		$this->curlh = $newcurl;
		$this->_prepare_curl ();
	}
	/**
	 * mimics curl_init, using hhb_curl::__construct
	 *
	 * @param string $url
	 * @param bool $insecureAndComfortableByDefault
	 * @return hhb_curl
	 */
	public static function init(string $url = null, bool $insecureAndComfortableByDefault = false): hhb_curl {
		return new hhb_curl ( $url, $insecureAndComfortableByDefault );
	}
	/**
	 *
	 * @param string $url
	 * @param bool $insecureAndComfortableByDefault
	 * @throws RuntimeException
	 */
	function __construct(string $url = null, bool $insecureAndComfortableByDefault = false) {
		$this->curlh = curl_init ( '' ); // why empty string? PHP Fatal error: Uncaught TypeError: curl_init() expects parameter 1 to be string, null given
		if (! $this->curlh) {
			throw new RuntimeException ( 'curl_init failed!' );
		}
		if ($url !== null) {
			$this->_setopt ( CURLOPT_URL, $url );
		}
		$fhandles = [ ];
		$tmph = NULL;
		for($i = 0; $i < 4; ++ $i) {
			$tmph = tmpfile ();
			if ($tmph === false) {
				// for($ii = 0; $ii < $i; ++ $ii) {
				// // @fclose($fhandles[$ii]);//yay, potentially overwriting last error to fuck your debugging efforts!
				// }
				throw new RuntimeException ( 'tmpfile() failed to create 4 file handles!' );
			}
			$fhandles [] = $tmph;
		}
		unset ( $tmph );
		$this->response_body_file_handle = $fhandles [0]; // CURLOPT_FILE
		$this->response_headers_file_handle = $fhandles [1]; // CURLOPT_WRITEHEADER
		$this->request_body_file_handle = $fhandles [2]; // CURLOPT_INFILE
		$this->stderr_file_handle = $fhandles [3]; // CURLOPT_STDERR
		unset ( $fhandles );
		$this->_prepare_curl ();
		if ($insecureAndComfortableByDefault) {
			$this->_setComfortableOptions ();
		}
	}
	function __destruct() {
		if(true){
			// workaround for https://bugs.php.net/bug.php?id=78007
			curl_setopt_array( $this->curlh, array(CURLOPT_VERBOSE=>0,CURLOPT_URL=>null));
			curl_exec($this->curlh);
		}
		curl_close ( $this->curlh );
		fclose ( $this->response_body_file_handle ); // CURLOPT_FILE
		fclose ( $this->response_headers_file_handle ); // CURLOPT_WRITEHEADER
		fclose ( $this->request_body_file_handle ); // CURLOPT_INFILE
		fclose ( $this->stderr_file_handle ); // CURLOPT_STDERR
	}
	/**
	 * sets some insecure, but comfortable settings..
	 *
	 * @return self
	 */
	public function _setComfortableOptions(): self {
		$this->setopt_array ( array (
				CURLOPT_AUTOREFERER => true,
				CURLOPT_BINARYTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTPGET => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_CONNECTTIMEOUT => 4,
				CURLOPT_TIMEOUT => 8,
				CURLOPT_COOKIEFILE => "", // <<makes curl save/load cookies across requests..
				CURLOPT_ENCODING => "", // << makes curl post all supported encodings, gzip/deflate/etc, makes transfers faster
				CURLOPT_USERAGENT => 'hhb_curl_php; curl/' . $this->version () ['version'] . ' (' . $this->version () ['host'] . '); php/' . PHP_VERSION 
		) ); //
		return $this;
	}
	/**
	 * curl_errno — Return the last error number
	 *
	 * @return int
	 */
	public function errno(): int {
		return curl_errno ( $this->curlh );
	}
	/**
	 * curl_error — Return a string containing the last error
	 *
	 * @return string
	 */
	public function error(): string {
		return curl_error ( $this->curlh );
	}
	/**
	 * curl_escape — URL encodes the given string
	 *
	 * @param string $str
	 * @return string
	 */
	public function escape(string $str): string {
		return curl_escape ( $this->curlh, $str );
	}
	/**
	 * curl_unescape — Decodes the given URL encoded string
	 *
	 * @param string $str
	 * @return string
	 */
	public function unescape(string $str): string {
		return curl_unescape ( $this->curlh, $str );
	}
	/**
	 * executes the curl request (curl_exec)
	 *
	 * @param string $url
	 * @throws RuntimeException
	 * @return self
	 */
	public function exec(string $url = null): self {
		$this->truncateFileHandles ();
		// WARNING: some weird error where curl will fill up the file again with 00's when the file has been truncated
		// until it is the same size as it was before truncating, then keep appending...
		// hopefully this _prepare_curl() call will fix that.. (seen on debian 8 on btrfs with curl/7.38.0)
		$this->_prepare_curl ();
		if (is_string ( $url ) && strlen ( $url ) > 0) {
			$this->setopt ( CURLOPT_URL, $url );
		}
		$ret = curl_exec ( $this->curlh );
		if ($this->errno ()) {
			throw new RuntimeException ( 'curl_exec failed. errno: ' . var_export ( $this->errno (), true ) . ' error: ' . var_export ( $this->error (), true ) );
		}
		return $this;
	}
	/**
	 * Create a CURLFile object for use with CURLOPT_POSTFIELDS
	 *
	 * @param string $filename
	 * @param string $mimetype
	 * @param string $postname
	 * @return CURLFile
	 */
	public function file_create(string $filename, string $mimetype = null, string $postname = null): CURLFile {
		return curl_file_create ( $filename, $mimetype, $postname );
	}
	/**
	 * Get information regarding the last transfer
	 *
	 * @param int $opt
	 * @return mixed
	 */
	public function getinfo(int $opt = null) {
		return curl_getinfo ( $this->curlh, $opt );
	}
	// pause is explicitly undocumented for now, but it pauses a running transfer
	public function pause(int $bitmask): int {
		return curl_pause ( $this->curlh, $bitmask );
	}
	/**
	 * Reset all options
	 */
	public function reset(): self {
		curl_reset ( $this->curlh );
		$this->curloptions = [ ];
		$this->_prepare_curl ();
		return $this;
	}
	/**
	 * curl_setopt_array — Set multiple options for a cURL transfer
	 *
	 * @param array $options
	 * @throws InvalidArgumentException
	 * @return self
	 */
	public function setopt_array(array $options): self {
		foreach ( $options as $option => $value ) {
			$this->setopt ( $option, $value );
		}
		return $this;
	}
	/**
	 * gets the last response body
	 *
	 * @return string
	 */
	public function getResponseBody(): string {
		return file_get_contents ( stream_get_meta_data ( $this->response_body_file_handle ) ['uri'] );
	}
	/**
	 * returns the response headers of the last request (when auto-following Location-redirect, only the last headers are returned)
	 *
	 * @return string[]
	 */
	public function getResponseHeaders(): array {
		$text = file_get_contents ( stream_get_meta_data ( $this->response_headers_file_handle ) ['uri'] );
		// ...
		return $this->splitHeaders ( $text );
	}
	/**
	 * gets the response headers of all the requets for the last execution (including any Location-redirect autofollow headers)
	 *
	 * @return string[][]
	 */
	public function getResponsesHeaders(): array {
		// var_dump($this->getStdErr());die();
		// CONSIDER https://bugs.php.net/bug.php?id=65348
		$Cr = "\x0d";
		$Lf = "\x0a";
		$CrLf = "\x0d\x0a";
		$stderr = $this->getStdErr ();
		$responses = [ ];
		while ( FALSE !== ($startPos = strpos ( $stderr, $Lf . '<' )) ) {
			$stderr = substr ( $stderr, $startPos + strlen ( $Lf ) );
			$endPos = strpos ( $stderr, $CrLf . "<\x20" . $CrLf );
			if ($endPos === false) {
				// ofc, curl has ths quirk where the specific message "* HTTP error before end of send, stop sending" gets appended with LF instead of the usual CRLF for other messages...
				$endPos = strpos ( $stderr, $Lf . "<\x20" . $CrLf );
			}
			// var_dump(bin2hex(substr($stderr,279,30)),$endPos);die("HEX");
			// var_dump($stderr,$endPos);die("PAIN");
			assert ( $endPos !== FALSE ); // should always be more after this with CURLOPT_VERBOSE.. (connection left intact / connecton dropped /whatever)
			$headers = substr ( $stderr, 0, $endPos );
			// $headerscpy=$headers;
			$stderr = substr ( $stderr, $endPos + strlen ( $CrLf . $CrLf ) );
			$headers = preg_split ( "/((\r?\n)|(\r\n?))/", $headers ); // i can NOT explode($CrLf,$headers); because sometimes, in the middle of recieving headers, it will spout stuff like "\n* Added cookie reg_ext_ref="deleted" for domain facebook.com, path /, expire 1457503459"
			                                                           // if(strpos($headerscpy,"report-uri=")!==false){
			                                                           // //var_dump($headerscpy);die("DIEDS");
			                                                           // var_dump($headers);
			                                                           // //var_dump($this->getStdErr());die("DIEDS");
			                                                           // }
			foreach ( $headers as $key => &$val ) {
				$val = trim ( $val );
				if (! strlen ( $val )) {
					unset ( $headers [$key] );
					continue;
				}
				if ($val [0] !== '<') {
					// static $r=0;++$r;var_dump('removing',$val);if($r>1)die();
					unset ( $headers [$key] ); // sometimes, in the middle of recieving headers, it will spout stuff like "\n* Added cookie reg_ext_ref="deleted" for domain facebook.com, path /, expire 1457503459"
					continue;
				}
				$val = trim ( substr ( $val, 1 ) );
			}
			unset ( $val ); // references can be scary..
			$responses [] = $headers;
		}
		unset ( $headers, $key, $val, $endPos, $startPos );
		return $responses;
	}
	// we COULD have a getResponsesCookies too...
	/*
	 * get last response cookies
	 *
	 * @return string[]
	 */
	public function getResponseCookies(): array {
		$headers = $this->getResponsesHeaders ();
		$headers_merged = array ();
		foreach ( $headers as $headers2 ) {
			foreach ( $headers2 as $header ) {
				$headers_merged [] = $header;
			}
		}
		return $this->parseCookies ( $headers_merged );
	}
	// explicitly undocumented for now..
	public function getRequestBody(): string {
		return file_get_contents ( stream_get_meta_data ( $this->request_body_file_handle ) ['uri'] );
	}
	/**
	 * return headers of last execution
	 *
	 * @return string[]
	 */
	public function getRequestHeaders(): array {
		$requestsHeaders = $this->getRequestsHeaders ();
		$requestCount = count ( $requestsHeaders );
		if ($requestCount === 0) {
			return array ();
		}
		return $requestsHeaders [$requestCount - 1];
	}
	// array(0=>array(request1_headers),1=>array(requst2_headers),2=>array(request3_headers))~
	/**
	 * get last execution request headers
	 *
	 * @return string[]
	 */
	public function getRequestsHeaders(): array {
		// CONSIDER https://bugs.php.net/bug.php?id=65348
		$Cr = "\x0d";
		$Lf = "\x0a";
		$CrLf = "\x0d\x0a";
		$stderr = $this->getStdErr ();
		$requests = [ ];
		while ( FALSE !== ($startPos = strpos ( $stderr, $Lf . '>' )) ) {
			$stderr = substr ( $stderr, $startPos + strlen ( $Lf . '>' ) );
			$endPos = strpos ( $stderr, $CrLf . $CrLf );
			if ($endPos === false) {
				// ofc, curl has ths quirk where the specific message "* HTTP error before end of send, stop sending" gets appended with LF instead of the usual CRLF for other messages...
				$endPos = strpos ( $stderr, $Lf . $CrLf );
			}
			assert ( $endPos !== FALSE ); // should always be more after this with CURLOPT_VERBOSE.. (connection left intact / connecton dropped /whatever)
			$headers = substr ( $stderr, 0, $endPos );
			$stderr = substr ( $stderr, $endPos + strlen ( $CrLf . $CrLf ) );
			$headers = explode ( $CrLf, $headers );
			foreach ( $headers as $key => &$val ) {
				$val = trim ( $val );
				if (! strlen ( $val )) {
					unset ( $headers [$key] );
				}
			}
			unset ( $val ); // references can be scary..
			$requests [] = $headers;
		}
		unset ( $headers, $key, $val, $endPos, $startPos );
		return $requests;
	}
	/**
	 * return last execution request cookies
	 *
	 * @return string[]
	 */
	public function getRequestCookies(): array {
		return $this->parseCookies ( $this->getRequestHeaders () );
	}
	/**
	 * get everything curl wrote to stderr of the last execution
	 *
	 * @return string
	 */
	public function getStdErr(): string {
		return file_get_contents ( stream_get_meta_data ( $this->stderr_file_handle ) ['uri'] );
	}
	/**
	 * alias of getResponseBody
	 *
	 * @return string
	 */
	public function getStdOut(): string {
		return $this->getResponseBody ();
	}
	protected function splitHeaders(string $headerstring): array {
		$headers = preg_split ( "/((\r?\n)|(\r\n?))/", $headerstring );
		foreach ( $headers as $key => $val ) {
			if (! strlen ( trim ( $val ) )) {
				unset ( $headers [$key] );
			}
		}
		return $headers;
	}
	protected function parseCookies(array $headers): array {
		$returnCookies = [ ];
		$grabCookieName = function ($str, &$len) {
			$len = 0;
			$ret = "";
			$i = 0;
			for($i = 0; $i < strlen ( $str ); ++ $i) {
				++ $len;
				if ($str [$i] === ' ') {
					continue;
				}
				if ($str [$i] === '=' || $str [$i] === ';') {
					-- $len;
					break;
				}
				$ret .= $str [$i];
			}
			return urldecode ( $ret );
		};
		foreach ( $headers as $header ) {
			// Set-Cookie: crlfcoookielol=crlf+is%0D%0A+and+newline+is+%0D%0A+and+semicolon+is%3B+and+not+sure+what+else
			/*
			 * Set-Cookie:ci_spill=a%3A4%3A%7Bs%3A10%3A%22session_id%22%3Bs%3A32%3A%22305d3d67b8016ca9661c3b032d4319df%22%3Bs%3A10%3A%22ip_address%22%3Bs%3A14%3A%2285.164.158.128%22%3Bs%3A10%3A%22user_agent%22%3Bs%3A109%3A%22Mozilla%2F5.0+%28Windows+NT+6.1%3B+WOW64%29+AppleWebKit%2F537.36+%28KHTML%2C+like+Gecko%29+Chrome%2F43.0.2357.132+Safari%2F537.36%22%3Bs%3A13%3A%22last_activity%22%3Bi%3A1436874639%3B%7Dcab1dd09f4eca466660e8a767856d013; expires=Tue, 14-Jul-2015 13:50:39 GMT; path=/
			 * Set-Cookie: sessionToken=abc123; Expires=Wed, 09 Jun 2021 10:18:14 GMT;
			 * //Cookie names cannot contain any of the following '=,; \t\r\n\013\014'
			 * //
			 */
			if (stripos ( $header, "Set-Cookie:" ) !== 0) {
				continue;
				/* */
			}
			$header = trim ( substr ( $header, strlen ( "Set-Cookie:" ) ) );
			$len = 0;
			while ( strlen ( $header ) > 0 ) {
				$cookiename = $grabCookieName ( $header, $len );
				$returnCookies [$cookiename] = '';
				$header = substr ( $header, $len );
				if (strlen ( $header ) < 1) {
					break;
				}
				if ($header [0] === '=') {
					$header = substr ( $header, 1 );
				}
				$thepos = strpos ( $header, ';' );
				if ($thepos === false) { // last cookie in this Set-Cookie.
					$returnCookies [$cookiename] = urldecode ( $header );
					break;
				}
				$returnCookies [$cookiename] = urldecode ( substr ( $header, 0, $thepos ) );
				$header = trim ( substr ( $header, $thepos + 1 ) ); // also remove the ;
			}
		}
		unset ( $header, $cookiename, $thepos );
		return $returnCookies;
	}
	/**
	 * Set an option for curl
	 *
	 * @param int $option
	 * @param mixed $value
	 * @throws InvalidArgumentException
	 * @return self
	 */
	public function setopt(int $option, $value): self {
		switch ($option) {
			case CURLOPT_VERBOSE :
				{
					trigger_error ( 'you should NOT change CURLOPT_VERBOSE. use getStdErr() instead. we are working around https://bugs.php.net/bug.php?id=65348 using CURLOPT_VERBOSE.', E_USER_WARNING );
					break;
				}
			case CURLOPT_RETURNTRANSFER :
				{
					trigger_error ( 'you should NOT use CURLOPT_RETURNTRANSFER. use getResponseBody() instead. expect problems now.', E_USER_WARNING );
					break;
				}
			case CURLOPT_FILE :
				{
					trigger_error ( 'you should NOT use CURLOPT_FILE. use getResponseBody() instead. expect problems now.', E_USER_WARNING );
					break;
				}
			case CURLOPT_WRITEHEADER :
				{
					trigger_error ( 'you should NOT use CURLOPT_WRITEHEADER. use getResponseHeaders() instead. expect problems now.', E_USER_WARNING );
					break;
				}
			case CURLOPT_INFILE :
				{
					trigger_error ( 'you should NOT use CURLOPT_INFILE. use setRequestBody() instead. expect problems now.', E_USER_WARNING );
					break;
				}
			case CURLOPT_STDERR :
				{
					trigger_error ( 'you should NOT use CURLOPT_STDERR. use getStdErr() instead. expect problems now.', E_USER_WARNING );
					break;
				}
			case CURLOPT_HEADER :
				{
					trigger_error ( 'you NOT use CURLOPT_HEADER. use  getResponsesHeaders() instead. expect problems now. we are working around https://bugs.php.net/bug.php?id=65348 using CURLOPT_VERBOSE, which is, until the bug is fixed, is incompatible with CURLOPT_HEADER.', E_USER_WARNING );
					break;
				}
			case CURLINFO_HEADER_OUT :
				{
					trigger_error ( 'you should NOT use CURLINFO_HEADER_OUT. use  getRequestHeaders() instead. expect problems now.', E_USER_WARNING );
					break;
				}
			
			default :
				{
				}
		}
		return $this->_setopt ( $option, $value );
	}
	/**
	 *
	 * @param int $option
	 * @param unknown $value
	 * @throws InvalidArgumentException
	 * @return self
	 */
	private function _setopt(int $option, $value): self {
		$ret = curl_setopt ( $this->curlh, $option, $value );
		if (! $ret) {
			throw new InvalidArgumentException ( 'curl_setopt failed. errno: ' . $this->errno () . '. error: ' . $this->error () . '. option: ' . var_export ( $this->_curlopt_name ( $option ), true ) . ' (' . var_export ( $option, true ) . '). value: ' . var_export ( $value, true ) );
		}
		$this->curloptions [$option] = $value;
		return $this;
	}
	/**
	 * return an option previously given to setopt(_array)
	 *
	 * @param int $option
	 * @param bool $isset
	 * @return mixed|NULL
	 */
	public function getopt(int $option, bool &$isset = NULL) {
		if (array_key_exists ( $option, $this->curloptions )) {
			$isset = true;
			return $this->curloptions [$option];
		} else {
			$isset = false;
			return NULL;
		}
	}
	/**
	 * return a string representation of the given curl error code
	 *
	 * (ps, most of the time you'll probably want to use error() instead of strerror())
	 *
	 * @param int $errornum
	 * @return string
	 */
	public function strerror(int $errornum): string {
		return curl_strerror ( $errornum );
	}
	/**
	 * gets cURL version information
	 *
	 * @param int $age
	 * @return array
	 */
	public function version(int $age = CURLVERSION_NOW): array {
		return curl_version ( $age );
	}
	private function _prepare_curl() {
		$this->truncateFileHandles ();
		$this->_setopt ( CURLOPT_FILE, $this->response_body_file_handle ); // CURLOPT_FILE
		$this->_setopt ( CURLOPT_WRITEHEADER, $this->response_headers_file_handle ); // CURLOPT_WRITEHEADER
		$this->_setopt ( CURLOPT_INFILE, $this->request_body_file_handle ); // CURLOPT_INFILE
		$this->_setopt ( CURLOPT_STDERR, $this->stderr_file_handle ); // CURLOPT_STDERR
		$this->_setopt ( CURLOPT_VERBOSE, true );
	}
	/**
	 * gets the constants name of the given curl options
	 *
	 * useful for error messages (instead of "FAILED TO SET CURLOPT 21387" , you can say "FAILED TO SET CURLOPT_VERBOSE" )
	 *
	 * @param int $option
	 * @return mixed|boolean
	 */
	public function _curlopt_name(int $option)/*:mixed(string|false)*/{
		// thanks to TML for the get_defined_constants trick..
		// <TML> If you had some specific reason for doing it with your current approach (which is, to me, approaching the problem completely backwards - "I dug a hole! How do I get out!"), it seems that your entire function there could be replaced with: return array_flip(get_defined_constants(true)['curl']);
		$curldefs = array_flip ( get_defined_constants ( true ) ['curl'] );
		if (isset ( $curldefs [$option] )) {
			return $curldefs [$option];
		} else {
			return false;
		}
	}
	/**
	 * gets the constant number of the given constant name
	 *
	 * (what was i thinking!?)
	 *
	 * @param string $option
	 * @return int|boolean
	 */
	public function _curlopt_number(string $option)/*:mixed(int|false)*/{
		// thanks to TML for the get_defined_constants trick..
		$curldefs = get_defined_constants ( true ) ['curl'];
		if (isset ( $curldefs [$option] )) {
			return $curldefs [$option];
		} else {
			return false;
		}
	}
}
class hhb_bcmath {
	public $scale = 200;
	public function __construct(int $scale = 200) {
		$this->scale = $scale;
	}
	public function add(string $left_operand, string $right_operand, int $scale = NULL): string {
		$scale = $scale ?? $this->scale;
		$ret = bcadd ( $left_operand, $right_operand, $scale );
		return $this->bctrim ( $ret );
	}
	public function comp(string $left_operand, string $right_operand, int $scale = NULL): int {
		$scale = $scale ?? $this->scale;
		$ret = bccomp ( $left_operand, $right_operand, $scale );
		return $ret;
	}
	public function div(string $left_operand, string $right_operand, int $scale = NULL): string {
		$scale = $scale ?? $this->scale;
		$right_operand = $this->bctrim ( trim ( $right_operand ) );
		if ($right_operand === '0') {
			throw new DivisionByZeroError ();
		}
		$ret = bcdiv ( $left_operand, $right_operand, $scale );
		return $this->bctrim ( $ret );
	}
	public function mod(string $left_operand, string $modulus): string {
		$scale = $scale ?? $this->scale;
		$modulus = $this->bctrim ( trim ( $modulus ) );
		if ($modulus === '0') {
			// if there was a ModulusByZero error, i would use it
			throw new DivisionByZeroError ();
		}
		$ret = bcmod ( $left_operand, $modulus );
		return $this->bctrim ( $ret );
	}
	public function mul(string $left_operand, string $right_operand, int $scale = NULL): string {
		$scale = $scale ?? $this->scale;
		$ret = bcmul ( $left_operand, $right_operand, $scale );
		return $this->bctrim ( $ret );
	}
	public function pow(string $left_operand, string $right_operand, int $scale = NULL): string {
		$scale = $scale ?? $this->scale;
		$ret = bcpow ( $left_operand, $right_operand, $scale );
		return $this->bctrim ( $ret );
	}
	public function powmod(string $left_operand, string $right_operand, string $modulus, int $scale = NULL): string {
		$scale = $scale ?? $this->scale;
		$modulus = $this->bctrim ( trim ( $modulus ) );
		if ($modulus === '0') {
			// if there was a ModulusByZero error, i would use it
			throw new DivisionByZeroError ();
		}
		$ret = bcpowmod ( $left_operand, $modulus, $modulus, $scale );
		return $this->bctrim ( $ret );
	}
	public function scale(int $scale): bool {
		$this->scale = $scale;
		return true;
	}
	public function sqrt(string $operand, int $scale = NULL) {
		$scale = $scale ?? $this->scale;
		if (bccomp ( $operand, '-1' ) !== - 1) {
			throw new RangeException ( 'tried to get the square root of number below zero!' );
		}
		$ret = bcsqrt ( $left_operand, $scale );
		return $this->bctrim ( $ret );
	}
	public function sub(string $left_operand, string $right_operand, int $scale = NULL): string {
		$scale = $scale ?? $this->scale;
		$ret = bcsub ( $left_operand, $right_operand, $scale );
		return $this->bctrim ( $ret );
	}
	public static function bctrim(string $str): string {
		$str = trim ( $str );
		if (false === strpos ( $str, '.' )) {
			return $str;
		}
		$str = rtrim ( $str, '0' );
		if ($str [strlen ( $str ) - 1] === '.') {
			$str = substr ( $str, 0, - 1 );
		}
		return $str;
	}
}

/**
 * needInputVariables: easy way to require variables, give a http 400 Bad Request with good error reports on missing parameters,
 * and cast the variables to the correct native php type (i use it with extract(needInputVariables(['mail_to'=>'email','i'=>'int','foo'=>'bool','bar','P'])) )
 *
 * @param array $variables
 *        	variables that you require. if key is numeric, any type is accepted, and name is taken from value, otherwise name is taken from key and type is taken from variable.
 * @param string $inputSources
 *        	G=$_GET P=$_POST C=$_COOKIE A=$argv (not yet implemented) X=$customSources, and variables are extracted in the order given here.
 * @param array $customSources
 *        	(otional)
 *        	array of custom sources to look through - ignored unless $inputSources contains X.
 * @throws \LogicException
 * @throws \InvalidArgumentException
 * @throws \RuntimeException
 * @return array
 */
function needInputVariables(array $variables, string $inputSources = 'P', array $customSources = array(), bool $exceptionMode = false): array {
	$ret = array ();
	$errors = array ();
	foreach ( $variables as $key => $type ) {
		if (is_numeric ( $key )) {
			$key = $type;
			$type = ''; // anything
		}
		// X (Custom)
		$found = false;
		foreach ( str_split ( $inputSources ) as $source ) {
			switch ($source) {
				case 'G' : // $_GET
					{
						if (array_key_exists ( $key, $_GET )) {
							$found = true;
							$val = $_GET [$key];
							break 2;
						}
						break;
					}
				case 'P' : // $_POST
					{
						if (array_key_exists ( $key, $_POST )) {
							$found = true;
							$val = $_POST [$key];
							break 2;
						}
						break;
					}
				case 'C' : // $_COOKIE
					{
						if (array_key_exists ( $key, $_COOKIE )) {
							$found = true;
							$val = $_COOKIE [$key];
							break 2;
						}
						break;
					}
				case 'A' : // $argv
					{
						throw new \LogicException ( 'FIXME: $argv NOT YET IMPLEMENTED' );
					}
				case 'X' : // $customSources
					{
						foreach ( $customSources as $customSource ) {
							if (array_key_exists ( $key, $customSource )) {
								$found = true;
								$val = $customSource [$key];
								break 3;
							}
						}
						break;
					}
				default :
					{
						throw new \InvalidArgumentException ( 'unknown input source: ' . hhb_return_var_dump ( $source ) );
					}
			}
		}
		
		if (! $found) {
			$errors [] = 'missing parameter: ' . $key;
			continue;
		}
		if ($type === '') {
			// anything, pass
		} elseif (substr ( $type, 0, 6 ) === 'string') {
			if (! is_string ( $val )) {
				$errors [] = 'following parameter is not a string: ' . $key;
				continue;
			}
			$type = substr ( $type, 6 );
			if (strlen ( $type )) {
				if ($type [0] !== '(') {
					throw \InvalidArgumentException ();
				}
				preg_match ( '/(\d+)(?:\,(\d+))?/', $type, $matches );
				$c = count ( $matches );
				if ($c > 3) {
					throw new \InvalidArgumentException ();
				}
				if ($c > 2) {
					$maxLen = $matches [2];
					if (strlen ( $val ) > $maxLen) {
						$errors [] = 'following parameter cannot be longer than ' . $maxLen . ' byte(s): ' . $key;
						continue;
					}
				}
				if ($c > 1) {
					$minLen = $matches [1];
					if (strlen ( $val ) < $minLen) {
						$errors [] = 'following parameter must be at least ' . $minLen . ' byte(s): ' . $key;
						continue;
					}
				}
			}
		} elseif ($type === 'bool') {
			$val = filter_var ( $val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
			if (NULL === $val) {
				$errors [] = 'following parameter is not a bool: ' . $key;
			}
		} elseif ($type === 'int' || $type === 'integer') {
			$val = filter_var ( $val, FILTER_VALIDATE_INT );
			if (false === $val) {
				$errors [] = 'following parameter is not a integer: ' . $key;
			}
		} elseif ($type === 'float' || $type === 'double') {
			$val = filter_var ( $val, FILTER_VALIDATE_FLOAT );
			if (false === $val) {
				$errors [] = 'following parameter is not a float: ' . $key;
			}
		} elseif ($type === 'email') {
			$val = filter_var ( $val, FILTER_VALIDATE_EMAIL, (defined ( 'FILTER_FLAG_EMAIL_UNICODE' ) ? FILTER_FLAG_EMAIL_UNICODE : 0) );
			if (false === $val) {
				$errors [] = 'following parameter is not an email: ' . $key;
			}
		} elseif ($type === 'ip') {
			$val = filter_var ( $val, FILTER_VALIDATE_IP );
			if (false === $val) {
				$errors [] = 'following parameter is not an ip address: ' . $key;
			}
		} elseif (is_callable ( $type )) {
			$req = (new ReflectionFunction ( $type ))->getNumberOfRequiredParameters ();
			if ($req === 1) {
				$ret [$key] = $type ( $val );
			} elseif ($req === 2) {
				$errstr = '';
				$ret [$key] = $type ( $val, $errstr );
				if (! empty ( $errstr )) {
					$error [] = "parameter \"$key\": $errstr";
				}
			} else {
				throw new \InvalidArgumentException ( "callback validator must accept 1 or 2 parameters, but accepts \"$req\" parameters. (\$input[,&\$errorDescription]){...return \$input}" );
			}
			continue;
		} else {
			throw new \InvalidArgumentException ( 'unsupported type: ' . hhb_return_var_dump ( $type ) );
		}
		$ret [$key] = $val;
	}
	if (empty ( $errors )) {
		return $ret;
	}
	$errstr = json_encode ( $errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR | (defined ( 'JSON_UNESCAPED_LINE_TERMINATORS' ) ? JSON_UNESCAPED_LINE_TERMINATORS : 0) );
	if ($exceptionMode) {
		throw new \RuntimeException ( $errstr );
	}
	http_response_code ( 400 );
	header ( "content-type: text/plain;charset=utf8" );
	echo "HTTP 400 Bad Request: following errors were found: \n";
	die ( $errstr );
}
 /* end of hhb_inc.php file */
 define("DEBUG_VERBOSE", TRUE); //cwg flag to control messages

 	//call register settings function
	add_action( 'admin_init', 'register_refresh_carsinfo_settings' );

function register_refresh_carsinfo_settings() {
	//register our settings
	register_setting('refresh_carsinfo_options_group', 'refresh_alliance_user');
	register_setting('refresh_carsinfo_options_group', 'refresh_alliance_pw');
	register_setting('refresh_carsinfo_options_group', 'refresh_manheim_user');
	register_setting('refresh_carsinfo_options_group', 'refresh_manheim_pw');
}

add_action('admin_menu', 'refresh_cars_button_menu');

//register shortcode,which will directly fetch postsales latest list from alliance for waco region,and display on page. i dont advice this though
add_shortcode('fetchpostsales','refresh_cars_button_action');

//register shortcode,used to test the table structure.it will return table structure with 1 dummy result.
add_shortcode('dummytable','showdummytable');


function refresh_cars_button_menu(){
  add_menu_page('Cars Admin Page', 'Cars', 'manage_options', 'cars', 'refresh_cars_button_admin_page');

}

function refresh_cars_button_admin_page() {

  // This function creates the output for the admin page.
  // It also checks the value of the $_POST variable to see whether
  // there has been a form submission. 

  // The check_admin_referer is a WordPress function that does some security
  // checking and is recommended good practice.

  // General check for user permissions.
  if (!current_user_can('manage_options'))  {
    wp_die( __('You do not have sufficient pilchards to access this page.')    );
  }

  // Start building the page
?>
<div class="wrap">
<h2>Cars Info for Waco region</h2>
<h2>Alliance</h2>
<?php
  // Check whether the button has been pressed AND also check the nonce
  if (isset($_POST['refresh_alliance_button']) && check_admin_referer('refresh_alliance_button_clicked')) {
    // the button has been pressed AND we've passed the security check
    refresh_alliance_button_action();
  }

  echo '<form action="options-general.php?page=cars" method="post">';

  // this is a WordPress security feature - see: https://codex.wordpress.org/WordPress_Nonces
  wp_nonce_field('refresh_alliance_button_clicked');
  echo '<input type="hidden" value="true" name="refresh_alliance_button" />';
  submit_button('Update Data');
  echo '</form>';

  echo '</div>';
/* cwg: add the user/pw info for alliance */
?>
<div class="wrap">
<form method="post" action="options.php"> 
<?php 
settings_fields( 'refresh_carsinfo_options_group' );
do_settings_sections( 'refresh_carsinfo_options_group' );

?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">Alliance Username</th>
        <td><input type="text" name="refresh_alliance_user" value="" /></td>
        </tr>
         
        <tr valign="top">
        <th scope="row">Alliance Password</th>
        <td><input type="text" name="refresh_alliance_pw" value="" /></td>
        </tr>
    </table>
<?php
submit_button('Set New Credentials'); 
?>
</form>
</div>
<div class="wrap">
<h2>Cars Info for Waco region</h2>
<h2>Manheim</h2>

<?php
  // Check whether the button has been pressed AND also check the nonce
  if (isset($_POST['refresh_manheim_button']) && check_admin_referer('refresh_manheim_button_clicked')) {
    // the button has been pressed AND we've passed the security check
    refresh_manheim_button_action();
  }

  echo '<form action="options-general.php?page=cars" method="post">';

  // this is a WordPress security feature - see: https://codex.wordpress.org/WordPress_Nonces
  wp_nonce_field('refresh_manheim_button_clicked');
  echo '<input type="hidden" value="true" name="refresh_manheim_button" />';
  submit_button('Update Data');
  echo '</form>';

  echo '</div>';
/* cwg: add the user/pw info for Manheim */
?>
<div class="wrap">
<form method="post" action="options.php"> 
<?php 
settings_fields( 'refresh_carsinfo_options_group' );
do_settings_sections( 'refresh_carsinfo_options_group' );

?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">Manheim Username</th>
        <td><input type="text" name="refresh_manheim_user" value="" /></td>
        </tr>
         
        <tr valign="top">
        <th scope="row">Manheim Password</th>
        <td><input type="text" name="refresh_manheim_pw" value="" /></td>
        </tr>
    </table>
<?php
submit_button('Set New Credentials'); 
?>
</form>
</div>
<?php
}

//	echo '<div id="message" class="updated fade"><p>' .'The "Call Function" button was clicked.' . '</p></div>';
// cwg: 20sep20	including auto-login-final.php inline rather than in separate file	

// this code was formerly in auto-login-final.php
//DISABLE php script timeout, ignore cancellaton by user.
//ignore_user_abort();
//set_time_limit(0);

//the site uses sessions,so i will need cookie support to send the cookies needed.

//
function fetch_url($url, $post_fields = null, $headers = null){
	global $html;
    global $error_occurred;
    
    //
    $path = get_temp_dir();
			
	// Create a new cURL resource
	$curl = curl_init();

	if (!$curl) {
		die("Couldn't initialize a cURL handle");
	}

	// Set the file URL to fetch through cURL
	curl_setopt($curl, CURLOPT_URL, $url);

	// Set a different user agent string (Googlebot)
	//curl_setopt($curl, CURLOPT_USERAGENT, 'Googlebot/2.1 (+http://www.google.com/bot.html)');

	// Follow redirects, if any
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

	// Fail the cURL request if response code = 400 (like 404 errors)
    curl_setopt($curl, CURLOPT_FAILONERROR, true);

    // Return the actual result of the curl result instead of success code
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    // Wait for 10 seconds to connect, set 0 to wait indefinitely
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);

    // Execute the cURL request for a maximum of 50 seconds
    curl_setopt($curl, CURLOPT_TIMEOUT, 50);

    // Do not check the SSL certificates
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		
	//COOKIES: you need to specify where to store cookies sent by server,AND also specify file with cookies to send to server with requests.usually this will be same file if you want to send back the cookies the server is sending.
	//specify where cookies are to be stored.file created if it doesnt exist.
	curl_setopt($curl, CURLOPT_COOKIEJAR, $path.'/cookies.txt');
	//specify the file containing cookies,which should be sent to server.No error issued if file doesnt exist
	curl_setopt($curl, CURLOPT_COOKIEFILE, $path.'/cookies.txt');
		
	//HEADERS: Just in case you need to send some headers along.
	#curl_setopt($curl, CURLOPT_HTTPHEADER, array(
	#	'Header-Key: Header-Value',
	#	'Header-Key-2: Header-Value-2'
	#));
	if ($headers && !empty($headers)) {
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	}
		
	//SENT POST DATA
	/* $postRequest = array(
		'firstFieldData' => 'foo',
		'secondFieldData' => 'bar'
	);*/
	if ($post_fields && !empty($post_fields)) {
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $post_fields);
	}
		


    // Fetch the URL and save the content in $html variable
    $html = curl_exec($curl);

    // Check if any error has occurred
    if (curl_errno($curl)){
        echo 'cURL error: ' . curl_error($curl);
        $error_occurred = true;
    }
	else{
        // cURL executed successfully
        
		//print_r(curl_getinfo($curl));
		//print_r($html);
		
		return $html;
    }

    // close cURL resource to free up system resources
    curl_close($curl);
}

function login_alliance(){
echo 'logging in to alliance ';
//go to login page


//fetch_url('http://dealers.allianceautoauction.com/components/login');
// i can see the cookies in /tmp/cookies.txt, so i think all is well so far.


//now try the login - submit the POST data.the cookies will be appended by default,so this could work (if authenticity_token is not being used)
//if login still fails,then it could be that authenticity_token is needed,thus i need to figure out where this token is generated,or how it is generated.
$url = 'http://dealers.allianceautoauction.com/components/login/attempt';
$username=get_option('refresh_alliance_user');
$password=get_option('refresh_alliance_pw');
//authenticity_token = '';
$data = array(
	'username' => $username,
	'password' => $password
);
$login_html = fetch_url($url,$data);
if (strpos($login_html,"Welcome, ") == FALSE) {
      echo '<p>Exception: login_alliance() - login failed</p>';
      die;
}
else echo 'login successful<br/>';
//get postsales data test.
//fetch_url('http://dealers.allianceautoauction.com/components/report/postsale/view/alliwaco/2020/9/4');

    //fetch_url('http://dealers.allianceautoauction.com/components/report/postsale/ajax/alliwaco/2020/9/4?_=1599601740516');

//get postsales list for waco - we only need the latest, so take the forst on the list
fetch_url('http://dealers.allianceautoauction.com/components/report/postsale/list/alliwaco');
//get the first entry url.

}

function login_manheim(){
	$login_html = do_manheim_login_stackoverflow();
	echo 'returned to login_manheim code';

echo $login_html;
if ($login_html == NULL || strpos($login_html,"<h2>My Manheim</h2>") == FALSE) {
      echo '<p>Exception: login_manheim() - login failed</p>';
      die;
}
else echo 'login successful<br/>';

/*
	return;
echo 'logging in to manheim ';
//go to login page


fetch_url('https://www.manheim.com/members/mymanheim/?classic=true');
// i can see the cookies in /tmp/cookies.txt, so i think all is well so far.


//now try the login - submit the POST data.the cookies will be appended by default,so this could work (if authenticity_token is not being used)
//if login still fails,then it could be that authenticity_token is needed,thus i need to figure out where this token is generated,or how it is generated.


$url = 'https://api.manheim.com/auth/authorization.oauth2?adaptor=manheim_customer&client_id=qdp6ewmug522t9umyxyqydnx&response_type=code&scope=openid&redirect_uri=https://members.manheim.com/gateway/callback&back_uri=https://www.manheim.com/members/mymanheim/?classic=true';
$username=get_option('refresh_manheim_user');
$password=get_option('refresh_manheim_pw');
//authenticity_token = '';
$data = array(
	'username' => $username,
	'password' => $password
);
$login_html = fetch_url($url,$data);
//print_r($login_html);
if (strpos($login_html,"<h2>My Manheim</h2>") == FALSE) {
      echo '<p>Exception: login_manheim() - login failed</p>';
      die;
}
else echo 'login successful<br/>';

//get postsales data test.
//fetch_url('http://dealers.allianceautoauction.com/components/report/postsale/view/alliwaco/2020/9/4');

    //fetch_url('http://dealers.allianceautoauction.com/components/report/postsale/ajax/alliwaco/2020/9/4?_=1599601740516');

//get postsales list for waco - we only need the latest, so take the forst on the list
//fetch_url('http://dealers.allianceautoauction.com/components/report/postsale/list/alliwaco');
//get the first entry url.
*/
}
/*
 * php_curl code copied from postman
 */
 function do_manheim_login() {
 	echo 'trying code from postman';

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "https://api.manheim.com/auth/authorization.oauth2?adaptor=manheim_customer&client_id=qdp6ewmug522t9umyxyqydnx&response_type=code&scope=openid&redirect_uri=https://members.manheim.com/gateway/callback&back_uri=https://www.manheim.com/members/mymanheim/?classic=true",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "GET",
  CURLOPT_HTTPHEADER => array(
    "Authorization: Basic Y2FycnJzOmNhcnJyMQ==",
    "Cookie: _ga=GA1.2.1349192351.1600917568; optimizelyEndUserId=oeu1600917567898r0.04117115790487125; AMCVS_130C4673527845910A490D45%40AdobeOrg=1; s_ecid=MCMID%7C18192489095612450561448246009274966096; s_cc=true; contactguid=1d2745ce-ff6a-db11-86d1-00145e7f535f; bm_sz=0FB21C949C05F988DFF30B5023704D42~YAAQj4gauMmMyLR0AQAA7XSk0QlyTbgiyDUdsLZkqX5r8RgEG9/xkHGCfAr91Ddt/tVi0vM6yu4gfChVsJ0EMlIrQJ31Kb+2ke6uKonVbuVBuTpU2oZsE/0ZbjxGgiOmGmsD3Exk3MN0hP8NKVcn7/RDKJbzzh/zui2Am6gsp2u1nlRKpjMvBnYq+NdOtR6eLg==; _gid=GA1.2.1458230726.1601245052; AMCV_130C4673527845910A490D45%40AdobeOrg=-432600572%7CMCIDTS%7C18533%7CMCMID%7C18192489095612450561448246009274966096%7CMCAAMLH-1601849852%7C7%7CMCAAMB-1601849852%7CRKhpRz8krg2tLO6pguXWp5olkAcUniQYPHaMWWgdJ3xzPWQmdj0y%7CMCCIDH%7C-1091511093%7CMCOPTOUT-1601252252s%7CNONE%7CMCAID%7CNONE%7CvVersion%7C4.5.2; _uiq_id.403025501.bec8=6bafd960aed68a3e.1600914338.0.1601246995..; PF=Yi6VBcXXRk7kQEf31lBk9xkbWgTVbEZSFxqC9m9RU1u1; PF.PERSISTENT=LY6y2QvunmVns55Oeb7wGYZSV; lane_alert=N; s_sq=%5B%5BB%5D%5D; auth_tkt=98fb16811182b393867ae39e614885145f7118a1carrrs%21Checkout%2CLane%2BNotifications%2CMy%2BPurchases%2CNewSimulcastAVPlugin%2CSimNewBuySellClient%2CSonar%2BSearch%2CVehicle%2BValuation%2BTool%2CWorkbook%2CWorkbook%2BOVE%21Sam%2BMusia; bearerToken=p2qywsbytxerjj8jfc8ut6c6; cais_refresh=N1EJ45NWpuPtstj3ISbqvE5EvuVvwnPh9ksVVOmBOq; bearerTokenExp=1601333791; ak_bmsc=4A04FA480BBD4F3980F8DD6842DE6FDCB81A888FCB5100007A0F715F7DCCF15D~pl5U7AXT5DlcrMlGF7yJ7gDZpMJIZv80YD23rgXhwU5SJUfKhgpHwrWkzUmTdOne4ZnsTB7MkdU2Z3ESQAaZQWu5SSekQjEWfN6AlIKQgyGTHKh3fD8yGOP2Otz8MZ8x00ipYkOEKZRtKw7GqWZxunzez5UtfWzhq25GVIqZ90Ur3eZp2a3Qq/uGN6N5Li7bmokUVL7q/hQt6ITYGOeP+6Am5o/w0WhlpT70p6ijJZUOfv4R5DmfHiF87bBGp0YMW2; bm_sv=CD86489E3293174CBB367EFB91117918~HSU0vN4R2Xvp2ykXpVUXIqviHd9Hk0b+pr5pWri0b2JNSSFr9WNI00HGGfYqsuXElEogLFHV2dX+UZnnhS3JhCOoxF3dhqJ3r7zOk4pYFdz3wY45o4c7YExKRBoXVrCHZthuqu+Uy1zoyvlzGIUW6I7Asw0A3QT8Uto6j//9F8A=; _abck=5EEE11D70C007A046C6AC3E88B66C370~-1~YAAQj4gauBqaybR0AQAA1TvI0QQhDOoHmg7A+20jbD7CzSSikRUv8g+YFSs+eplWwUi7+C/yQ6UTuFz1OlHlSDhYkaw9k87TQp+qANBH7g0zPqMGfEM0gdL4UwwPfhtqwzCakuZmAkRajJQH+sBbou/eS+Vtu+OVC3jO1zPoIAF/O8xBkTKnrc7p83qb6+dWyJIOlEi9MdSqsXdyPD6u98R4g3cf5qw0r6OqHM+cumCNJoy0jyYnkJLj8dT9v7TOjtLEHrprClFBYC/TNNfntyI5b7Rihy1VW9XH8RyVUlZ7wMOdVr0jnUxGzt4xvhLtTLn8coSk/2Y=~0~-1~-1"
  ),
));

$response = curl_exec($curl);

//curl_close($curl);
return  $response;

}
/* helper function from stackoverflow */

function do_manheim_login_stackoverflow() {
		echo 'trying code from stackoverflow';

hhb_init(); // better error reporting
$username=get_option('refresh_manheim_user');
$password=get_option('refresh_manheim`_pw');

$hc = new hhb_curl ( '', true );


// header ( "content-type: text/plain;charset=utf8" );
$hc->exec ( 'https://www.manheim.com/' ); // getting a cookie session id

$html = $hc->exec ( 'https://www.manheim.com/login/' )->getResponseBody (); // getting authenticity_token , required for logging in
$domd = @DOMDocument::loadHTML ( $html );
$xp = new DOMXPath ( $domd );

hhb_var_dump ( $xp, $hc->getStdErr (), $hc->getStdOut () );

/*
$token = $xp->query ( '//input[@name="authenticity_token"]' )->item ( 0 )->getAttribute ( "value" );
$hc->setopt_array ( array (
        CURLOPT_URL => 'https://www.manheim.com/login/authenticate',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query ( array (
                'utf8' => '✓',
                'authenticity_token' => $token,
                'user' => array (
                        'username' => $username,
                        'password' => $password 
                ),
                'submit' => 'Login' 
        ) ) 
) )->exec ();
$html = $hc->getResponseBody ();
$domd = @DOMDocument::loadHTML ( $html );
$xp = new DOMXPath ( $domd );
$errmsg = $xp->query ( '//*[contains(@class,"msgError")]' );
if ($errmsg->length > 0) {
    echo 'Error logging in: ' . $errmsg->item ( 0 )->textContent;
} else {
    echo 'logged in!';
}
hhb_var_dump ( $token, $hc->getStdErr (), $hc->getStdOut () );
/* */
}
/* */
function getElementsByClassName($parentNode,$tagName,$classname){
	//select by tagname, then filter through to find the ones i need (class passed in as variable to this function
    //echo '<p>'.$parentNode.'</p>';
	$nodes = array();
    try{
	$childNodeList = $parentNode->getElementsByTagName($tagName);
    }catch(Exception $e){
      echo '<p>Exception: '.$e->getMessage().'</p>';
        die;
    }
	for($i=0; $i < $childNodeList->length; $i++){
		$temp = $childNodeList->item($i);
		if(stripos($temp->getAttribute('class'),$classname) !== false){
			$nodes[] = $temp;
		}
	}
	return $nodes;
}

function getHyperlinks($parentNode,$tagName,$classname=null){
        //select by tagname, then filter through to find the ones i need (class passed in as variable to this function
        $nodes = array();
        $childNodeList = $parentNode->getElementsByTagName($tagName);
        for($i=0; $i < $childNodeList->length; $i++){
                $temp = $childNodeList->item($i);
                //if(stripos($temp->getAttribute('class'),$classname) !== false){
				if(stripos($temp->getAttribute('class'),'view') !== false){
					//print('class view found...'."\n");

					//class found.now check child items for anchor tag. i need that value.
					foreach($temp->childNodes as $child){
						//print_r($child);
						if ($child->hasChildNodes() and $child->hasAttribute('href')){
							//print('childnode with attribute href found.'."\n");
							//print($child->getAttribute('href'));
							//print($child->nodeValue."\n");
							
							//i need the first anchor with text 'View'
							if($child->nodeValue === 'View'){
								//print('most recent postsales found -->'.$child->getAttribute('href')."\n");
								$url_to_fetch = $child->getAttribute('href');
								return $url_to_fetch;
							}
						}
						
					}
                }
        }
}

function getCarsInTable($parentNode,$tagName,$classname=null){
        //select by tagname, then filter through to find the ones i need (class passed in as variable to this function
        $nodes = array();
        $childNodeList = $parentNode->getElementsByTagName($tagName);
        for($i=0; $i < $childNodeList->length; $i++){
                $temp = $childNodeList->item($i);
				//if(stripos($temp->getAttribute('class'),'view') !== false){
					//print('class view found...'."\n");

					//class found.now check child items for anchor tag. i need that value.
					foreach($temp->childNodes as $child){
						//print_r($child);
						//if ($child->hasChildNodes() and $child->hasAttribute('href')){
						if ($child->hasChildNodes() ){
							//print('childnode with attribute href found.'."\n");
							
							//print($child->nodeValue."\n");
							
							//i need the first anchor with text 'View'
							/*if($child->nodeValue === 'View'){
								//print('most recent postsales found -->'.$child->getAttribute('href')."\n");
								$url_to_fetch = $child->getAttribute('href');
								return $url_to_fetch;
							}*/
						}
						
					}
                //}
        }
}

function displaypostsalestable($vehicles){
  //
 $result = '<table><tbody><thead><tr class="bottom"><th class="unique_vehicle_id"><span class="button">&nbsp;</span></th><th class="run_number"><span>ID</span></th><th class="run_number"><span>RUN#</span></th><th class="year"><span>YEAR</span></th><th class="exterior_color"><span>COLOR</span></th><th class="make"><span>MAKE</span></th><th class="model"><span>MODEL</span></th><th class="mileage"><span>ODOMETER</span></th><th class="exterior_color"><span>OLD PRICE</span></th><th class="exterior_color"><span>NEW PRICE</span></th><th class="view_vehicle"><span class="button">&nbsp;</span></th><th class="view_vehicle"><span class="button">&nbsp;</span></th></tr></thead><!-- VEHICLES go in gere  -->';
	foreach($vehicles as $vehicle){
		//
        /*echo 'ID: '.$vehicle['ID'];
	echo 'Run #: '.$vehicle['run_number'];
	echo 'Year: '.$vehicle['year'];
	echo 'Color: '.$vehicle['color'];
	echo 'Make: '.$vehicle['make'];
	echo 'Model: '.$vehicle['model'];
	echo 'Odometer: '.$vehicle['odometer'];
	echo 'Price: '.$vehicle['price'];
	echo 'New price: '.$vehicle['new_price'];*/		
$row = '<tr class="even bottom"><td class="unique_vehicle_id thumbnail"><img ' .$thumbnail_url. ' alt="vehicle photo"></td><td class="year number"><span>' .$vehicle['ID']. '</span></td><td class="run_number">' .$vehicle['run_number']. '</td><td class="year number"><span>' .$vehicle['year']. '</span></td><td class="exterior_color">' .$vehicle['color']. '</td><td class="make">' .$vehicle['make'] .'</td><td class="model">' .$vehicle['model']. '</td><td class="mileage number"><span>' .$vehicle['odometer']. '</span></td><td class="vin">' .$vehicle['price']. '</td><td class="vin">' .$vehicle['new_price']. '</td></tr>';
		//
		$result .= $row;
	}
	//
	$result .= '</tbody></table>';
	
	//
	return $result;
}


function fetch_latest_postsales_data_waco(){
$content = fetch_url('http://dealers.allianceautoauction.com/components/report/postsale/list/alliwaco');
$html = $content;

//print for debugging
//print_r($content);
//echo $content;

//try using xpath to get the first waco link 
//findClassByXpath('view');

//try getting by classname 
$dom = new DOMDocument('1.0','utf-8');
$dom->loadHTML($html);
$content_node = $dom->getElementById('page_content');
$results = getElementsByClassName($content_node,'div','view');
//print out the matchhes,for debugging
//print_r($results);
//echo '<p>'.$results.'</p>';

$url_to_fetch = getHyperlinks($content_node,'div');
//replace the 'build' in url with 'ajax'
$url_to_fetch = str_replace('build','ajax',$url_to_fetch);
//add the base url 
$url_to_fetch = 'http://dealers.allianceautoauction.com'.$url_to_fetch;
//print_r('url to fetch: '.$url_to_fetch);
//echo 'url to fetch: '.$url_to_fetch."\r\n";

$content = fetch_url($url_to_fetch);
//add html and body tags,to make it a little more like a html page.
$content = '<html><body>'.$content.'</body></html>';
$html = $content;
//print_r($content);
######################
//try replacing the 'nbsp' with ' ', as it is causing warnings when parsing
//$html = str_replace('&nbsp;','no value',$html);
$html = str_replace('&nbsp;','',$html);



//print for debugging
//print_r($content);
//print_r($html);

//try getting by classname
$dom = new DOMDocument('1.0','utf-8');
//$dom->loadHTML($html);
//$dom->loadXML($html,LIBXML_PARSEHUGE);

//$dom->loadXML($html);

@$dom->loadHTML($html);
$dom->preserveWhiteSpace = false;
$tables = $dom->getElementsByTagName('table');
//print_r($tables);
////print_r($tables->item(0));
if ($tables->length == 0) {
      echo '<p>Exception: fetch_latest_postsales_data_waco() - No Tables</p>';
      die;
}	

//$rows = $tables->item(1)->getElementsByTagName('tr');
$rows = $tables->item(0)->getElementsByTagName('tr');

//create array that will hold all vehicles extracted
$vehicles = array();
$counter=0;  //will use this for numbering the items in the table.
foreach ($rows as $row) {
		//skip rows that dont have car data. since all cars have a make, i will use that to filter out rows that dont have it.
	
    //echo "\r\n";
		$counter +=1;
		//print_r('ID: '.$counter."\n");
        $cols = $row->getElementsByTagName('td');
		foreach($cols as $col){
			//print_r($col);
			//print_r($col->getAttribute('class'));
			//print_r(' -> '.$col->nodeValue);
			//print("\n");
			if(stripos($col->getAttribute('class'),'view_vehicle view_detail') !== false){
				//get the url for showing more details about the car.
				$temp_nodes = $col->getElementsByTagName('a');
				foreach ($temp_nodes as $tmp){
					$more_details_url = $tmp->getAttribute('href');
					//print($more_details_url);
					//print("\n");
				}		
			}
			//get price, i need to add $2,000 markup to it.
			if(stripos($col->getAttribute('class'),'amount currency') !== false){
				//get the amount as integer
				//remove preceding '$' sign, remove the decimal part,then remove any comma - in that order.
				$price = $col->nodeValue;
				$amount = explode(".",$price);
				$amount = $amount[0];
				$amount = str_replace('$','',$amount);
				$amount = str_replace(',','',$amount);
				$estimated_price = $amount + 2000;
				//lets add the dollar sign,and also have the amount formatted to 2 decimal places.
				setlocale(LC_MONETARY, 'en_US.UTF-8');
				//$estimated_price = money_format('%.2n', $estimated_price);
				
				//print($amount."\n");
				//$amount = (int)$amount;
				//print('price: '.$price."\n");
				//print('estimated price after markup: '.$estimated_price."\n");
			}
			//get year of manufacture
			if(stripos($col->getAttribute('class'),'year number') !== false){
				//
				$year = $col->nodeValue;
			}
			//get make of car
			if(stripos($col->getAttribute('class'),'make') !== false){
				//
				$make = $col->nodeValue;
			}
			//get model of car
			if(stripos($col->getAttribute('class'),'model') !== false){
				//
				$model = $col->nodeValue;
			}
			//get mileage(odometer) of the car 
			if(stripos($col->getAttribute('class'),'mileage number') !== false){
				//
				$mileage = $col->nodeValue;
			}
			//get color of the car 
			if(stripos($col->getAttribute('class'),'exterior_color') !== false){
				//
				$color = $col->nodeValue;
			}
			//get run number of the car 
			if(stripos($col->getAttribute('class'),'run_number') !== false){
				//
				$run_number = $col->nodeValue;
			}	
		}
		//ALL rows i need have been checked, and grabbed from the row if available.
		//if $make is NOT present, then this is NOT a row containing car data. skip it.
		if(!isset($make)){
			$counter -=1;
			continue;
		}
		//print out the values of the columns retrieved, to confirm the values are ok.
		//print out in order specified by project owner
                /*echo 'ID: '.$counter."\r\n";
		echo 'Run #: '.$run_number."\r\n";
		echo 'Year: '.$year."\r\n";
		echo 'Color: '.$color."\r\n";
		echo 'Make: '.$make."\r\n";
		echo 'Model: '.$model."\r\n";
		echo 'Odometer: '.$mileage."\r\n";
		echo 'Price: '.$price."\r\n";
		echo 'New price: '.$estimated_price."\r\n";*/

        $vehicles[$counter]['ID'] = $counter ;
	$vehicles[$counter]['run_number'] = $run_number;
	$vehicles[$counter]['year'] = $year;
	$vehicles[$counter]['color'] = $color;
	$vehicles[$counter]['make'] = $make;
	$vehicles[$counter]['model'] = $model;
	$vehicles[$counter]['odometer'] = $mileage;
	//$vehicles[$counter]['price'] = $price;
	$vehicles[$counter]['price'] = '$'.$amount;
	$vehicles[$counter]['new_price'] = '$'.$estimated_price;
    
}
//
return $vehicles;
}

//
//return $vehicles;

/*foreach($vehicles as $vehicle){
                echo 'ID: '.$counter."\r\n";
		echo 'Run #: '.$run_number."\r\n";
		echo 'Year: '.$year."\r\n";
		echo 'Color: '.$color."\r\n";
		echo 'Make: '.$make."\r\n";
		echo 'Model: '.$model."\r\n";
		echo 'Odometer: '.$mileage."\r\n";
		echo 'Price: '.$price."\r\n";
		echo 'New price: '.$estimated_price."\r\n";
}*/
//showdummytable($vehicles);

//return displaypostsalestable($vehicles);
######################



function fetch_latest_presales_data_waco(){
	//get page containing listings for all available regions, then get for waco region,if available.
	$url = 'http://dealers.allianceautoauction.com/components/report/presale/event_list/all';
	$url = 'http://dealers.allianceautoauction.com/components/report/presale/event_list/alliwaco';
	$content = fetch_url($url);
	$html = $content;

	//print($html."\n\n");
	
	//look for the url to a listing, and pull that. most likely there will either be 1 list, or none at all.
	//below is a sample url(it is a button at the moment):
	//http://dealers.allianceautoauction.com/components/report/presale/build/alliwaco/2020/9/18
	
	//try getting by classname 
$dom = new DOMDocument('1.0','utf-8');
$dom->loadHTML($html);
$content_node = $dom->getElementById('page_content');
$results = getElementsByClassName($content_node,'div','buttons');
foreach($results as $result){
	if($result->hasChildnodes()){
	//	print('has child nodes'."\n");
	}
	$links = $result->getElementsByTagName('a');
	//var_dump($links);
	foreach($links as $link){
	//print($link->nodeValue.' --> '.$link->getAttribute('href')."\n");
	
		$text = $link->nodeValue;
		$url = 'http://dealers.allianceautoauction.com'.$link->getAttribute('href');
		//print($text.' --> '.$url."\n");
		if(stripos($url,'presale/build/')!== false){
			//url found
			//return $url;
			
			//let's break out of the 2 foreach loops,at once.
			break 2;
		}
	}
	print("\n\n");
}

//i have the url of the latest presales. i can now fetch it, and return the data fetched,so it can be displayed
//print('url to latest presales cars waco region: '.$url."\n");

//sample url that returns the presales table: http://dealers.allianceautoauction.com/components/report/presale/ajax/alliwaco/2020/9/18?_=1600341928594
//replace 'build' with 'ajax' in the url - since that is the url that returns the table data.
//am not sure what the parameter in the url does. i will ommit it for now ('?_=1600341928594')
$url = str_replace('build','ajax',$url);
//print('url to latest presales cars waco region: '.$url."\n");
$content = fetch_url($url);
$html = $content;
//lets scheck the returned data:
//print($html);
if (is_null($html)) {
      echo '<p>Exception: No Data from fetch_url()</p>';
      die;
}	
//lets add some html tags around the returned data,to make it valid html(sort of)
//$html = '<html><body>'.$html.'</body></html>';
//ok, i see the table in the response returned. now lets extract the table:
//select by tagname 'table'
//
$dom = new DOMDocument('1.0','utf-8');
$dom->loadHTML($html);

//@$dom->loadHTML($html);
$dom->preserveWhiteSpace = false;//$content_node = $dom->getElementById('page_content');
$tables = $dom->getElementsByTagName('table');
if ($tables->length == 0) {
      echo '<p>Exception: fetch_latest_presales_data_waco() - No Tables</p>';
      die;
}	
//var_dump($content_node);
$rows = $tables->item(0)->getElementsByTagName('tr');
$vehicles = array();
$counter = 0;
foreach($rows as $row){
	$counter +=1;
	//
	$cols = $row->getElementsByTagName('td');
		foreach($cols as $col){
			//
			//var_dump($col);
			//print($col->nodeValue."\n");
			
			//get run_number of car
			if(stripos($col->getAttribute('class'),'run_number') !== false){
				//
				$run_number = $col->nodeValue;
		$vehicles[$counter]['run_number'] = $run_number; 
			}
			//get year of car
			if(stripos($col->getAttribute('class'),'year number') !== false){
				//
				$year = $col->nodeValue;
		$vehicles[$counter]['year'] = $year; 
			}
			//get make of car
			if(stripos($col->getAttribute('class'),'make') !== false){
				//
				$make = $col->nodeValue;
		$vehicles[$counter]['make'] = $make; 
			}
			//get model of car
			if(stripos($col->getAttribute('class'),'model') !== false){
				//
				$model = $col->nodeValue;
		$vehicles[$counter]['model'] = $model; 
			}
			//get mileage of car
			if(stripos($col->getAttribute('class'),'mileage number') !== false){
				//
				$mileage = $col->nodeValue;
		$vehicles[$counter]['odometer'] = $mileage; 
			}
			//get color of car
			if(stripos($col->getAttribute('class'),'exterior_color') !== false){
				//
				$color = $col->nodeValue;
		$vehicles[$counter]['color'] = $color; 
			}
			//get vin number of car
			if(stripos($col->getAttribute('class'),'vin') !== false){
				//
				$vin = $col->nodeValue;
		$vehicles[$counter]['vin'] = $vin; 
			}
			//get grade of car
			if(stripos($col->getAttribute('class'),'grade') !== false){
				//
				$grade = $col->nodeValue;
		$vehicles[$counter]['grade'] = $grade; 
			}
			//get more details url of car(i need the pictures from that page)
			if(stripos($col->getAttribute('class'),'view_detail') !== false){
				//
				$car_details = $col->nodeValue;
				$car_details_url = $col->getAttribute('href');
				print($car_details_url."\n");
		$vehicles[$counter]['car_details_url'] = $car_details_url; //need to work on getting the url from the child node 'a'
			}
		}
		//add car details to array, they need to be sent back
		//$vehicles[$counter]['run_number'] = $run_number; 
		
}

//lets now check the extracted cars data, in array 'vehicles' before returning it
//foreach($vehicles as $vehicle){
///	var_dump($vehicle);
//}


//$results = getElementsByClassName($content_node,'div','view');



// i need the first href in that. - just ensure it is the right one by checking for words in url.
//i can get away by selecting first item(link) with class 'button'
//$

return $vehicles;
}

//. end of code from auto-login-final

function refresh_alliance_button_action(){

    //login
    login_alliance();

    //get latest postsales data for waco region.
    $vehicles = fetch_latest_postsales_data_waco();
	if (DEBUG_VERBOSE) {
		echo 'fetched postsales data for alliance<br/>';
		print_r($vehicles);
	}
	
	//save the postsales cars for waco region, as a csv file
	save_as_csv('postsales','test_cars_csv.csv',$vehicles);
		if (DEBUG_VERBOSE) {
		echo 'saved postsales data to csv for testing<br/>';
	}

	//get latest presales data for waco region - this might return an empty list if there is no upcoming auction.
	$results = fetch_latest_presales_data_waco();
	if (DEBUG_VERBOSE) {
		echo 'fetched presales data for alliance<br/>';
		print_r($vehicles);
	}

    //display the retrieved cars
    //return displaypostsalestable($vehicles);
	
	
	//save the presales cars for waco region, as a csv file 
	save_as_csv('presales','test_presales_cars.csv',$results);
		if (DEBUG_VERBOSE) {
		echo 'saved presales data to csv for testing<br/>';
	}
	
	//save both presales and postsales data into a single file.
	//save_to_single_csv('postsales',$vehicles);
	//save_to_single_csv('presales',$results);
	
	save_to_single_csv($results,$vehicles); //pass both presales and postsales,to be written to file.
		if (DEBUG_VERBOSE) {
		echo 'saved pre- and postsales data to csv for testing<br/>';
	}
	
	//let user know work is done
	echo '<p>Getting fresh Alliance data complete. It might take up to 15 minutes before any new data shows up in tablepress.</p>';

}  
function refresh_manheim_button_action(){
    //login
    login_manheim();

    //get latest postsales data for waco region.
    $vehicles = fetch_latest_manheim_postsales_data_waco();
	
	//get latest presales data for waco region - this might return an empty list if there is no upcoming auction.
	$results = fetch_latest_manheim_presales_data_waco();


    //display the retrieved cars
    //return displaypostsalestable($vehicles);
	
	//save the postsales cars for waco region, as a csv file
	save_as_csv('postsales','test_cars_csv.csv',$vehicles);
	
	//save the presales cars for waco region, as a csv file 
	save_as_csv('presales','test_presales_cars.csv',$results);
	
	//save both presales and postsales data into a single file.
	//save_to_single_csv('postsales',$vehicles);
	//save_to_single_csv('presales',$results);
	
	save_to_single_csv($results,$vehicles); //pass both presales and postsales,to be written to file.
	
	//let user know work is done
	echo '<p>Getting fresh Manheim data complete. It might take up to 15 minutes before any new data shows up in tablepress.</p>';

}  


    //get latest postsales data for waco region.
    function  fetch_latest_manheim_postsales_data_waco(){
		echo 'fetch_latest_manheim_postsales_data_waco()';
	}
	
	//get latest presales data for waco region - this might return an empty list if there is no upcoming auction.
function fetch_latest_manheim_presales_data_waco(){
	echo 'fetch_latest_manheim_presales_data_waco()';
}



function get_latest_postsales_cars_waco_from_website(){
	//login to alliance, get latest postsales cars for region waco
	// i can include the script i already wrote
}

function save_latest_postsales_cars_waco_to_database($cars_array){
	global $wpdb;
	//i need to insert that data into database, in table named 'alliance_waco_latest_postsales_cars'
	//i need to create the table if it doesnt exist - unless wordpress will automatically create it if it doesnt exist.
	//i need to pass in the data i obtained from the page, as array.i can then use a loop to do the inserts.
	//add column for date of the postsales.then if date for the just retrieved data is different from date of those in db,delete the ones in db and insert the new ones. i can also use count of results since it is unlikely different auctions will have same number of cars. date of auction is foolproof however.
	//NB:raw sql commands could also work here,but not advised.
	 
	$table_name = '';
	$wpdb->insert( 
    'table_name', 
    array( 
        'column1' => 'value1', 
        'column2' => 123 
    ), 
    array( 
        '%s', 
        '%d' 
    ) 
);

function empty_postsales_table(){
	//just before inserting new postsales data,delete the old ones first. this function does just that.
	
}

}

function get_latest_postsales_cars_waco_from_db(){
	//this function doesnt belong here, but am writing it for completeness
	//it reads the local database for the latest postsales cars in waco region,which was pulled in earlier and saved.
	//the data can then be displayed on the page.
}

/*function showdummytable($vehicles=null){
	$result = '<table><tbody><thead><tr class="bottom"><th class="unique_vehicle_id"><span class="button">&nbsp;</span></th><th class="run_number"><span>ID</span></th><th class="run_number"><span>RUN#</span></th><th class="year"><span>YEAR</span></th><th class="exterior_color"><span>COLOR</span></th><th class="make"><span>MAKE</span></th><th class="model"><span>MODEL</span></th><th class="mileage"><span>ODOMETER</span></th><th class="exterior_color"><span>OLD PRICE</span></th><th class="exterior_color"><span>NEW PRICE</span></th><th class="view_vehicle"><span class="button">&nbsp;</span></th><th class="view_vehicle"><span class="button">&nbsp;</span></th></tr></thead><!-- VEHICLES go in gere  --><tr class="even bottom"><td class="unique_vehicle_id thumbnail"><img src="//file3.autolookout.net/63595/016239/9182/O3Jlc29sdXRpb246NzB4NTM7/5754_01.jpg" alt="vehicle photo"></td><td class="year number"><span>1</span></td><td class="run_number">V-105</td><td class="year number"><span>2018</span></td><td class="exterior_color">BLACK</td><td class="make">Chevrolet</td><td class="model">Silverado 1500</td><td class="mileage number"><span>0</span></td><td class="vin">$1,000.00</td><td class="vin">$3,000.00</td></tr></tbody></table>';
    //return $result;

//create dummy vehicles array for debugging purposes.
if(!isset($vehicles)){
    global $vehicles;
	$vehicles = array();
	$vehicles[1]['ID'] = 1;
	$vehicles[1]['run_number'] = 'v-64';
	$vehicles[1]['year'] = 2008;
	$vehicles[1]['color'] = 'RED';
	$vehicles[1]['make'] = 'TOYOTA';
	$vehicles[1]['model'] = 'CH-R';
	$vehicles[1]['odometer'] = 299011;
	$vehicles[1]['price'] = '$1,000.00';
	$vehicles[1]['new_price'] = '$3,000.00';
	
	$vehicles[2]['ID'] = 2;
	$vehicles[2]['run_number'] = 'Y-748';
	$vehicles[2]['year'] = 2010;
	$vehicles[2]['color'] = 'BLACK';
	$vehicles[2]['make'] = 'HONDA';
	$vehicles[2]['model'] = 'CIVIC';
	$vehicles[2]['odometer'] = 529011;
	$vehicles[2]['price'] = '$2,000.00';
	$vehicles[2]['new_price'] = '$4,000.00';
	}

	$result = '<table><tbody><thead><tr class="bottom"><th class="unique_vehicle_id"><span class="button">&nbsp;</span></th><th class="run_number"><span>ID</span></th><th class="run_number"><span>RUN#</span></th><th class="year"><span>YEAR</span></th><th class="exterior_color"><span>COLOR</span></th><th class="make"><span>MAKE</span></th><th class="model"><span>MODEL</span></th><th class="mileage"><span>ODOMETER</span></th><th class="exterior_color"><span>OLD PRICE</span></th><th class="exterior_color"><span>NEW PRICE</span></th><th class="view_vehicle"><span class="button">&nbsp;</span></th><th class="view_vehicle"><span class="button">&nbsp;</span></th></tr></thead><!-- VEHICLES go in gere  -->';
	foreach($vehicles as $vehicle){
		//
		$row = '<tr class="even bottom"><td class="unique_vehicle_id thumbnail"><img ' .$thumbnail_url. ' alt="vehicle photo"></td><td class="year number"><span>' .$vehicle['ID']. '</span></td><td class="run_number">' .$vehicle['run_number']. '</td><td class="year number"><span>' .$vehicle['year']. '</span></td><td class="exterior_color">' .$vehicle['color']. '</td><td class="make">' .$vehicle['make'] .'</td><td class="model">' .$vehicle['model']. '</td><td class="mileage number"><span>' .$vehicle['odometer']. '</span></td><td class="vin">' .$vehicle['price']. '</td><td class="vin">' .$vehicle['new_price']. '</td></tr>';
		//
		$result .= $row;
	}
	//
	$result .= '</tbody></table>';
	
	//
	return $result;
    //return 'hello world';
}*/

function create_table_if_not_exists(){
	global $wpdb;
	//creates the table where the fetched vehicles data will be stored,if not available.
	//
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	$table = "alliance_postsales_table";
	//row_id BIGINT|run_number |year|color|make|model|odometer BIGINT|price INT|new_price INT|auction_date YEAR|
	$sql = "CREATE TABLE ".$table." run_number VARCHAR(128) NOT NULL, year YEAR, color VARCHAR(128),make VARCHAR(128),model VARCHAR(128),odometer BIGINT,price INT,new_price INT,auction_date YEAR";
	//maybe_create_table($wpdb->prefix . $tablename, $sql);
	maybe_create_table($wpdb-> $tablename, $sql);
}

function insert($tablename,$vehicles){
	global $wpdb;
	//inserts the vehicles in the array into the database, in the table specified
	//the tables will be having different columns, so i need to generate sql statement based on the table name. if table is not known, dont do any inserts.
	foreach($vehicles as $vehicle){
		//$wpdb->insert($table,$data,$format);
		//$wpdb->insert($table,$data); format is optional - %f for float,%s for string and %d for integer
		$table = 'alliance_postsales_table';
		$data = array(
		  'run_number' => $vehicle['run_number'],
		  'color' => $vehicle['color'],
		  'year' => $vehicle['year'],
		  'make' => $vehicle['make'],
		  'model' => $vehicle['model'],
		  'odometer' => $vehicle['run_number'],
		  'price' => $vehicle['price'],
		  'new_price' => $vehicle['new_price'],
		  'auction_date' => $vehicle[''],
		);
		$wpdb->insert($table,$data);
	}
}

function delete_all_rows_before_insert($table){
	//after fresh data has been retrieved,the old one has to be deleted,before the new one is inserted
	//i can ommit row_id in the tables.
	$sql = "DELETE from ".$tablename;
	$wpdb->query($sql);
}

function get_latest_presales_cars_waco_from_website(){
	global $wpdb;
	//no need to login, just get the latest data 
	//return an array containing the loot.
	$table = 'alliance_postsales_table';
	$vehicles = $wpdb->get_results("SELECT * FROM ".$table);
	//echo $vehicles[0]->run_number;
	
	//ccreate the table to return.
	$counter=0;
	foreach($vehicles as $vehicle){
		$counter +=1;
		
	}
}

function register_cron_job(){
	//the pulling of new data,and saving to db should be run at intervals - currently once everyday
	//i need to hook into the wordpress cron job for this.
	//Note that the wordpress cron is not a 'real' cron job. but for our purpose it should work just fine
}

function save_as_csv($type,$filename,$vehicles){
	//i need to save the vehicles data as a csv file or json. i will use csv format for now.
	//the first line will be table headers.
	$path = WP_PLUGIN_DIR; //returns plugin directory.
	//i now need name of my plugin.
	//NB: i can use wp-content dir for this. then all plugins can access the file. i need read/write content tpo this directory.
	$wp_content_dir = WP_CONTENT_DIR;
	$full_path = $wp_content_dir.'/'.$filename;
	//echo 'wp content dir + file path = '.$full_path;
	
	$file = fopen($full_path,'w'); //overwrite it. in future, i might want to read it,add data,remove duplicates,then save to file.
	if($type == 'postsales'){
	echo '<p>saving postsales data  ... </p>';
	$table_headers = "'run_number','year','color','make','model','odometer','price','new price','auction date'";
	//fwrite($table_data,$table_headers."\n");
	fwrite($file,$table_headers."\n");
	foreach($vehicles as $vehicle){
		$table_data = $vehicle['run_number'].','.
			$vehicle['year'].','.
			$vehicle['color'].','.
			$vehicle['make'].','.
			$vehicle['model'].','.
			$vehicle['odometer'].','.
			$vehicle['price'].','.
			$vehicle['new_price']; 
		fwrite($file,$table_data."\n");
	}
	
	}
	else if($type == 'presales'){
		//presales cars data.for now,just print it out for debugging.
		echo '<p>saving presales data  ... </p>';
		$table_headers = "'run_number','year','make','model','odometer','color','vin #','grade','auction date'";
		fwrite($file,$table_headers."\n");
		foreach($vehicles as $vehicle){
				$table_data = $vehicle['run_number'].','.
				$vehicle['year'].','.
				$vehicle['make'].','.
				$vehicle['model'].','.
				$vehicle['odometer'].','.
				$vehicle['color'].','.
				$vehicle['vin'].','.
				$vehicle['grade']; 
				fwrite($file,$table_data."\n");
		}
	}
	//
	fclose($file);
}

//function save_to_single_csv($type,$vehicles){
function save_to_single_csv($results,$vehicles){
	//$results is presales, $vehicles is postsales
	
	//$filename = 'combined_postsales_presales.csv';
	$filename ='5-Cars-2020-09-17.csv';
	
	//i need to save the vehicles data as a csv file or json. i will use csv format for now.
	//the first line will be table headers.
	
	$path = WP_PLUGIN_DIR; //returns plugin directory.
	//$path = $filename; //used for testing in terminal.
	
	//i now need name of my plugin.
	//NB: i can use wp-content dir for this. then all plugins can access the file. i need read/write content tpo this directory.
	$wp_content_dir = WP_CONTENT_DIR;
	$full_path = $wp_content_dir.'/'.$filename;
	//echo 'wp content dir + file path = '.$full_path;
	
	$file = fopen($full_path,'w'); //overwrite it. in future, i might want to read it,add data,remove duplicates,then save to file.
	
	$table_headers = "Type,Most Recent Auction Company,Most Recent Auction Sale Date,Most Recent Auction City,Most Recent Auction State,Ln/Run,Vin Number,Grade,Year,Color,Make,Model,Body Style,Trim Level,Odometer,Auction Price,Estimated Price,Spv (Std Presumptive Value),Loan (Bank Loan Value),Mmr1 (Lo Wholesale),Mmr2 (Mid Wholesale),Mmr3 (Hi Wholesale),Psv1 (Lo Retail),Psv2 (Mid Retail),Psv3 (High Retail),Stock Number,Unique Vehicle Id Src,Sale Date,Pic,View Vehicle Href,Grade Href,Eng /T,Cr,Drs,Cyl,Fuel,Trans,4X4,Ew,Radio,Top,Manufactured In,Production Seq. Number,Body Style,Engine Type,Transmission Short,Transmission Long,Driveline,Tank,Fuel Economy City,Fuel Economy Highway,Anti Brake System,Steering Type,Front Brake Type,Rear Brake Type,Turning Diameter,Front Suspension,Rear Suspension,Front Spring Type,Rear Spring Type,Tires,Front Headroom,Rear Headroom,Front Legroom,Rear Legroom,Front Shoulder Room,Rear Shoulder Room,Front Hip Room,Rear Hip Room,Interior Trim,Curb Weight Automatic,Curb Weight Manual,Overall Length,Overall Width,Overall Height,Wheelbase,Ground Clearance,Track Front,Track Rear,Cargo Length,Width At Wheelwell,Width At Wall,Depth,Standard Seating,Optional Seating,Passenger Volume,Cargo Volume,Standard Towing,Maximum Towing,Standard Payload,Maximum Payload,Standard Gvwr,Maximum Gvwr,Basic Duration,Basic Distance,Powertrain Duration,Powertrain Distance,Rust Duration,Rust Distance,Msrp,Dealer Invoice,Destination Charge,Child Safety Door Locks,Power Door Locks,Vehicle Anti Theft,Abs Brakes,Traction Control,Vehicle Stability Control System,Driver Airbag,Passenger Airbag,Side Head Curtain Airbag,Electronic Parking Aid,Trunk Anti Trap Device,Keyless Entry,Remote Ignition,Air Conditioning,Separate Driver/Front Passenger Climate Controls,Cruise Control,Tachometer,Tilt Steering,Tilt Steering Column,Leather Steering Wheel,Steering Wheel Mounted Controls,Telescopic Steering Column,Genuine Wood Trim,Tire Pressure Monitor,Trip Computer,Am/Fm Radio,Cd Player,Cd Changer,Navigation Aid,Voice Activated Telephone,Subwoofer,Telematics System,Driver Multi Adjustable Power Seat,Front Heated Seat,Front Power Lumbar Support,Front Split Bench Seat,Leather Seat,Passenger Multi Adjustable Power Seat,Second Row Folding Seat,Cargo Net,Power Sunroof,Manual Sunroof,Automatic Headlights,Daytime Running Lights,Fog Lights,High Intensity Discharge Headlights,Front Air Dam,Front Air Dam,Alloy Wheels,Run Flat Tires,Chrome Wheels,Power Windows,Electrochromic Exterior Rearview Mirror,Heated Exterior Mirror,Electrochromic Interior Rearview Mirror,Power Adjustable Exterior Mirror,Interval Wipers,Rain Sensing Wipers,Rear Window Defogger,Safety And Recall Data";
	
	//$table_headers = "'Type','Most Recent Auction Company','Most Recent Auction Sale Date','Most Recent Auction City','Most Recent Auction State','Ln/Run','Vin Number','Grade','Year','Color','Make','Model','Body Style','Trim Level','Odometer','Auction Price','Estimated Price','Spv (Std Presumptive Value)','Loan (Bank Loan Value)','Mmr1 (Lo Wholesale)','Mmr2 (Mid Wholesale)','Mmr3 (Hi Wholesale)','Psv1 (Lo Retail)','Psv2 (Mid Retail)','Psv3 (High Retail)','Stock Number','Unique Vehicle Id Src','Sale Date','Pic','View Vehicle Href','Grade Href','Eng /T','Cr','Drs','Cyl','Fuel','Trans','4X4','Ew','Radio','Top','Manufactured In','Production Seq. Number','Body Style','Engine Type','Transmission Short','Transmission Long','Driveline','Tank','Fuel Economy City','Fuel Economy Highway','Anti Brake System','Steering Type','Front Brake Type','Rear Brake Type','Turning Diameter','Front Suspension','Rear Suspension','Front Spring Type','Rear Spring Type','Tires','Front Headroom','Rear Headroom','Front Legroom','Rear Legroom','Front Shoulder Room','Rear Shoulder Room','Front Hip Room','Rear Hip Room','Interior Trim','Curb Weight Automatic','Curb Weight Manual','Overall Length','Overall Width','Overall Height','Wheelbase','Ground Clearance','Track Front','Track Rear','Cargo Length','Width At Wheelwell','Width At Wall','Depth','Standard Seating','Optional Seating','Passenger Volume','Cargo Volume','Standard Towing','Maximum Towing','Standard Payload','Maximum Payload','Standard Gvwr','Maximum Gvwr','Basic Duration','Basic Distance','Powertrain Duration','Powertrain Distance','Rust Duration','Rust Distance','Msrp','Dealer Invoice','Destination Charge','Child Safety Door Locks','Power Door Locks','Vehicle Anti Theft','Abs Brakes','Traction Control','Vehicle Stability Control System','Driver Airbag','Passenger Airbag','Side Head Curtain Airbag','Electronic Parking Aid','Trunk Anti Trap Device','Keyless Entry','Remote Ignition','Air Conditioning','Separate Driver/Front Passenger Climate Controls','Cruise Control','Tachometer','Tilt Steering','Tilt Steering Column','Leather Steering Wheel','Steering Wheel Mounted Controls','Telescopic Steering Column','Genuine Wood Trim','Tire Pressure Monitor','Trip Computer','Am/Fm Radio','Cd Player','Cd Changer','Navigation Aid','Voice Activated Telephone','Subwoofer','Telematics System','Driver Multi Adjustable Power Seat','Front Heated Seat','Front Power Lumbar Support','Front Split Bench Seat','Leather Seat','Passenger Multi Adjustable Power Seat','Second Row Folding Seat','Cargo Net','Power Sunroof','Manual Sunroof','Automatic Headlights','Daytime Running Lights','Fog Lights','High Intensity Discharge Headlights','Front Air Dam','Front Air Dam','Alloy Wheels','Run Flat Tires','Chrome Wheels','Power Windows','Electrochromic Exterior Rearview Mirror','Heated Exterior Mirror','Electrochromic Interior Rearview Mirror','Power Adjustable Exterior Mirror','Interval Wipers','Rain Sensing Wipers','Rear Window Defogger','Safety And Recall Data'";
	
	fwrite($file,$table_headers."\n");
	foreach($vehicles as $vehicle){
		$type = 'postsales';
		$table_data = $type.',,,,,'.$vehicle['run_number'].',,,'.$vehicle['year'].','.$vehicle['color'].','.$vehicle['make'].','.$vehicle['model'].',,,'.$vehicle['odometer'].','.$vehicle['price'].','.$vehicle['new_price'];
		fwrite($file,$table_data."\n");
	/*if($type == 'presales'){
		//$table_data = "$type,,,,,$vehicle['run_number'],$vehicle['vin'],$vehicle['grade'],,$vehicle['year'],$vehicle['color'],$vehicle['make'],$vehicle['model'],,,$vehicle['odometer']";
		$table_data = $type.',,,,,'.$vehicle['run_number'].','.$vehicle['vin'].','.$vehicle['grade'].',,'.$vehicle['year'].','.$vehicle['color'].','.$vehicle['make'].','.$vehicle['model'].',,,'.$vehicle['odometer'];
		fwrite($file,$table_data);
	}else if($type == 'postsales'){
		$table_data = $type.',,,,,,'.$vehicle['run_number'].',,,'.$vehicle['year'].','.$vehicle['color'].','.$vehicle['make'].','.$vehicle['model'].',,,'.$vehicle['odometer'].','.$vehicle['price'].','.$vehicle['new_price'];
		fwrite($file,$table_data);
	}*/
	}
	foreach($results as $result){
		$type = 'presales';
		//
		$table_data = $type.',,,,,'.$result['run_number'].','.$result['vin'].','.$result['grade'].','.$result['year'].','.$result['color'].','.$result['make'].','.$result['model'].',,,'.$result['odometer'];
		fwrite($file,$table_data."\n");
	}
	//
	fclose($file);
}
//'run_number','year','make','model','odometer','color','vin #','grade','auction date'";  --> presales
//'run_number','year','color','make','model','odometer','price','new price','auction date'";  --> postsales

//$table_headers  = "'run_number' 'year' 'make' 'model' 'color' 'odometer' 'vin' 'grade' 'estimated price'  'auction date' 'type'";
