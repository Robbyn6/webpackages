<?php
/**
 *  Copyright (C) 2010 - 2017  <Robbyn Gerhardt>
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package       Webpackages
 * @subpackage    core
 * @author        Robbyn Gerhardt
 * @copyright     Copyright (c) 2010 - 2017, Robbyn Gerhardt (http://www.robbyn-gerhardt.de/)
 * @license       http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link          http://webpackages.de
 * @since         Version 2017.0
 * @filesource
 */

namespace package\core;

use package\exceptions\securityException;
use package\system\core\initiator;

/**
 * Überprüft jeglichen Input eines Benutzer
 *
 * Mit der security Klasse kann man jegliche Form von Angriff über Eingaben des Benutzers aufhalten. Ob XSS oder
 * SQL Injections, die security Klasse überprüft diese und gibt sie gereinigt zurück.
 *
 * @method static mixed autoSecurity(array $exceptionValues)
 * @method static mixed control(string $variable, $convert = null, $removeSQLFunctions = false)
 * @method static mixed url(string $variable, $input = 'get', $convert = 'string', $removeSQLFunctions = false)
 * @method static string|bool create_csrf_token(string $token_name, $token_duration = 0);
 * @method static bool exists_csrf_token(string $token_name)
 * @method static string|bool get_csrf_token(string $token_name, bool $remove_token_after = false)
 * @method static bool remove_csrf_token(string $token_name)
 * @method static mixed get_mime_type(string $path)
 * @method static mixed get_file_type(string $path)
 * @method static string shaSec(string $string)
 * @method static string sha_sec(string $string)
 * @method static string entity_decode(string $str, $charset = 'UTF-8')
 * @method static string remove_invisible_characters(string $str, $url_encoded = true)
 * @method static array is_bot()
 * @method static mixed get_ip_address()
 *
 * @package        Webpackages
 * @subpackage     core
 * @category       security
 * @author         Robbyn Gerhardt <gerhardt@webpackages.de>
 */
class security extends initiator
{
	/**
	 * @var string Der XSS-Hash
	 */
	public static $_xss_hash = '';

	/**
	 * @var array Liste aller bekannten Bots (stetig in Erweiterung)
	 */
	public static $botlist = array(
		"Teoma",
		"alexa",
		"froogle",
		"Gigabot",
		"inktomi",
		"looksmart",
		"URL_Spider_SQL",
		"Firefly",
		"NationalDirectory",
		"Ask Jeeves",
		"TECNOSEEK",
		"InfoSeek",
		"WebFindBot",
		"girafabot",
		"crawler",
		"www.galaxy.com",
		"Googlebot",
		"Scooter",
		"Slurp",
		"msnbot",
		"appie",
		"FAST",
		"WebBug",
		"Spade",
		"ZyBorg",
		"rabaz",
		"Baiduspider",
		"Feedfetcher-Google",
		"TechnoratiSnoop",
		"Rankivabot",
		"Mediapartners-Google",
		"Sogou web spider",
		"WebAlta Crawler",
		"TweetmemeBot",
		"Butterfly",
		"Twitturls",
		"Me.dium",
		"Twiceler",
		"bing",
		"microsoft",
		"yahoo"
	);

	/**
	 * @var array Liste aller erlaubten IP-Methoden
	 */
	public static $ipMethodes = array(
		'HTTP_CLIENT_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_FORWARDED',
		'HTTP_X_CLUSTER_CLIENT_IP',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
		'REMOTE_ADDR'
	);

	/**
	 * @var array Liste nicht erlaubter Schnipsel in Strings
	 */
	protected static $_never_allowed_str = array(
		'document.cookie'	=> '[removed]',
		'document.write'	=> '[removed]',
		'.parentNode'		=> '[removed]',
		'.innerHTML'		=> '[removed]',
		'-moz-binding'		=> '[removed]',
		'<!--'				=> '&lt;!--',
		'-->'				=> '--&gt;',
		'<![CDATA['			=> '&lt;![CDATA[',
		'<comment>'			=> '&lt;comment&gt;',
		'<%'              	=> '&lt;&#37;'
	);

	/**
	 * @var array Liste nicht erlaubter Regex Funde in Strings (stetig in Erweiterung)
	 */
	protected static $_never_allowed_regex = array(
		'javascript\s*:',
		'(document|(document\.)?window)\.(location|on\w*)',
		'expression\s*(\(|&\#40;)', // CSS and IE
		'vbscript\s*:', // IE, surprise!
		'wscript\s*:', // IE
		'jscript\s*:', // IE
		'vbs\s*:', // IE
		'Redirect\s+30\d',
		"([\"'])?data\s*:[^\\1]*?base64[^\\1]*?,[^\\1]*?\\1?"
	);

	private static $hasControl = array('post' => array(), 'get' => array());

	/**
	 * Kontrolliert alle einkommenden Anfragen einmal auf schädlichen Code durch
	 *
	 * @param array $exceptionValues
	 * @return void
	 */
	protected static function _autoSecurity($exceptionValues)
	{
		$controlVariables	=	array(
			'get'		=>	'_GET',
			'post'		=>	'_POST',
			'session'	=>	'_SESSION',
			'request'	=>	'_REQUEST'
		);

		foreach($controlVariables as $key => $variable)
		{
			if(!empty($GLOBALS[$variable]))
			{
				foreach($GLOBALS[$variable] as $k => $v)
				{
					if(!in_array($k, $exceptionValues))
					{
						self::$hasControl[$key][$k]	=	gettype($v);

						$GLOBALS[$variable][$k]	=	self::control($v, self::$hasControl[$key][$k]);
					}
				}
			}
		}
	}


	/**
	 * Kontrolliert eine Variable auf Sicherheit und Konvertiert diese zur Sicherheit auch in das gewünschte Format.
	 *
	 * @param mixed   $variable           Die Variable die überprüft werden soll.
	 * @param string  $convert            Der Typ in den der Rückgabewert konvertiert werden soll.
	 * @param boolean $removeSQLFunctions Entfernt SQL Funktionen aus einem Wert
	 *
	 * @return mixed Gibt die konvertierte Variable zurück
	 */
	protected static function _control($variable, $convert = null, $removeSQLFunctions = false)
	{
		if(is_string($variable))
		{
			$variable = trim($variable);
		}

		return self::_controllSecurity($variable, $convert, $removeSQLFunctions);
	}

	/**
	 * Sicherer Weg um $_GET, $_POST, $_SESSION, $_COOKIE, $_SERVER, $_ENV und $_REQUEST aufzufangen,
	 * man sollte immer über diese Funktion Daten abrufen und prüfen lassen.
	 *
	 * @param string  $variable           Zu überprüfender Wert in $input
	 * @param string  $input              Die Quelle in der der Wert von $variable liegt.
	 * @param string  $convert            Der Typ in den der Rückgabewert konvertiert werden soll.
	 * @param boolean $removeSQLFunctions Entfernt SQL Funktionen aus einem Wert
	 *
	 * @return mixed Gibt den überprüften Wert konvertiert zurück.
	 */
	protected static function _url($variable, $input = 'get', $convert = 'string', $removeSQLFunctions = false)
	{
		$input		=	strtolower($input);

		$controlVariables	=	array(
			'get'		=>	'_GET',
			'post'		=>	'_POST',
			'session'	=>	'_SESSION',
			'request'	=>	'_REQUEST',
			'cookie'	=>	'_COOKIE',
			'server'	=>	'_SERVER',
			'env'		=>	'_ENV',
		);

		if(!isset($controlVariables[$input]))
		{
			return false;
		}

		if(AUTO_SECURE == true && isset(self::$hasControl[$input][$variable]) && self::$hasControl[$input][$variable] == $convert)
		{
			return $GLOBALS[$controlVariables[$input]][$variable];
		}

		$request = '';

		if(!empty($GLOBALS[$controlVariables[$input]][$variable]))
		{
			$request	=	$GLOBALS[$controlVariables[$input]][$variable];
		}

		return self::_controllSecurity($request, $convert, $removeSQLFunctions);
	}

	/**
	 * Erstellt einen CSRF Token, für die
	 * anschließende Kontrolle.
	 *
	 * @param string $token_name
	 * @param int $token_duration
	 *
	 * @return string|bool
	 */
	protected static function _create_csrf_token($token_name, $token_duration = 0)
	{
		if(empty($token_name))
		{
			return false;
		}

		$token	=	md5(uniqid(mt_rand(), true));

		if($token_duration <= 0)
		{
			$token_duration	=	60*60;
		}

		if(setcookie($token_name, $token, (time() + $token_duration)))
		{
			return $token;
		}

		return false;
	}

	/**
	 * Kontrolliert ob der Token beim Benutzer existiert
	 *
	 * @param string $token_name
	 *
	 * @return bool
	 */
	protected function _exists_csrf_token($token_name)
	{
		return isset($_COOKIE[$token_name]);
	}

	/**
	 * Gibt den Token zurück oder ein false wenn er nicht existiert
	 *
	 * @param string $token_name
	 * @param bool $remove_token_after
	 *
	 * @return string|bool
	 */
	protected function _get_csrf_token($token_name, $remove_token_after = false)
	{
		if(!empty($_COOKIE[$token_name]))
		{
			$token	=	$_COOKIE[$token_name];

			if($remove_token_after)
			{
				$this->_remove_csrf_token($token_name);
			}

			return $token;
		}

		return false;
	}

	/**
	 * Entfernt den Token
	 *
	 * @param $token_name
	 *
	 * @return bool
	 */
	protected function _remove_csrf_token($token_name)
	{
		if(!empty($_COOKIE[$token_name]))
		{
			unset($_COOKIE[$token_name]);
		}

		return setcookie($token_name, '', (time() - 1));
	}


	/**
	 * Sicherheitskontrolle einer Variable
	 *
	 * @param mixed   $request
	 * @param string  $convert
	 * @param boolean $removeSQLFunctions Entfernt SQL Funktionen aus einem Wert
	 *
	 * @return mixed
	 */
	protected static function _controllSecurity($request, $convert, $removeSQLFunctions = false)
	{
		$convert = strtolower($convert);

		$filterIn = array('ip' => FILTER_VALIDATE_IP, 'mail' => FILTER_VALIDATE_EMAIL, 'email' => FILTER_VALIDATE_EMAIL, 'e' => FILTER_VALIDATE_EMAIL, 'number' => FILTER_VALIDATE_FLOAT, 'num' => FILTER_VALIDATE_FLOAT, 'dec' => FILTER_VALIDATE_FLOAT, 'decimal' => FILTER_VALIDATE_FLOAT, 'double' => FILTER_VALIDATE_FLOAT, 'float' => FILTER_VALIDATE_FLOAT, 'floatval' => FILTER_VALIDATE_FLOAT, 'n' => FILTER_VALIDATE_FLOAT, 'integer' => FILTER_VALIDATE_INT, 'int' => FILTER_VALIDATE_INT, 'long' => FILTER_VALIDATE_INT, 'i' => FILTER_VALIDATE_INT, 'boolean' => FILTER_VALIDATE_BOOLEAN, 'bool' => FILTER_VALIDATE_BOOLEAN, 'b' => FILTER_VALIDATE_BOOLEAN, 'string' => FILTER_SANITIZE_STRING, 'str' => FILTER_SANITIZE_STRING, 's' => FILTER_SANITIZE_STRING);

		if(!isset($filterIn[$convert]))
		{
			return false;
		}

		$isString	=	($convert == 'string' || $convert == 'str' || $convert == 's');

		if($isString)
		{
			if(is_array($isString))
			{
				return 'array()';
			}
			elseif(is_object($isString))
			{
				return 'std()';
			}
		}

		$request	=	filter_var($request, $filterIn[$convert]);

		if($request === false || $request == null)
		{
			return false;
		}

		if($isString)
		{
			$request = self::xss_clean($request);

			if($removeSQLFunctions)
			{
				$request = preg_replace(MYSQL_FUNCTIONS, '', $request, -1);
			}

			$request = trim($request);
		}

		return $request;
	}

	/**
	 * Gibt den aktuellen MIME-Type zurück
	 *
	 * @param string $path Der relative Pfad zur übeprüfenden Datei.
	 *
	 * @throws securityException
	 * @return bool|mixed Gibt MIME-Type zurück.
	 */
	protected static function _get_mime_type($path)
	{
		if(!function_exists('finfo_open'))
		{
			throw new securityException('finfo extensio not loaded');
		}

		$finfo     = finfo_open(FILEINFO_MIME_TYPE);
		$mime_type = finfo_file($finfo, $path);

		finfo_close($finfo);

		return $mime_type;
	}

	/**
	 * Gibt die Dateiendung zurück die für diese Datei die richtige ist
	 *
	 * @param string $path Der realative Pfad zur Datei.
	 *
	 * @throws securityException
	 * @return mixed Gibt die Dateierweiterung zurück.
	 */
	protected static function _get_file_type($path)
	{
		if(!class_exists('\SplFileInfo'))
		{
			throw new securityException('Error: SplFileInfo in php not installed');
		}

		$file = new \SplFileInfo($path);

		return $file->getExtension();
	}

	/**
	 * Verschlüsselt einen String
	 *
	 * @deprecated
	 *
	 * @param string $string Der String der Verschlüsselt werden soll.
	 *
	 * @throws securityException
	 * @return string Gibt den SHA512 Verschlüsselten String zurück.
	 */
	protected static function _shaSec($string)
	{
		return self::_sha_sec($string);
	}

	/**
	 * Verschlüsselt einen String
	 *
	 * @param string $string Der String der Verschlüsselt werden soll.
	 *
	 * @throws securityException
	 * @return string Gibt den SHA512 Verschlüsselten String zurück.
	 */
	protected static function _sha_sec($string)
	{
		if(!function_exists('hash_hmac'))
		{
			throw new securityException('hash extension not loaded');
		}

		$crypt = hash_hmac("sha512", $string, SECURITY_KEY);

		return $crypt;
	}

	/**
	 * Säubert einen String vor schädlichen XSS Code
	 *
	 * @param string|string[] $str Der String der von XSS gesäubert werden soll
	 *
	 * @return string $str
	 */
	protected static function xss_clean($str)
	{
		// Is string a array
		if(is_array($str))
		{
			while(list($key) = each($str))
			{
				$str[$key] = self::xss_clean($str[$key]);
			}

			return $str;
		}

		// Remove Invisible Characters
		$str = self::remove_invisible_characters($str);

		/*
		 * URL Decode
		 *
		 * <a href="http://%77%77%77%2E%67%6F%6F%67%6C%65%2E%63%6F%6D">webpackages</a>
		 */
		if(strpos($str, '%') !== false)
		{
			while(true)
			{
				$str = rawurldecode($str);

				if(preg_match('/%[0-9a-f]{2,}/i', $str) != 1)
				{
					break;
				}
			}
		}

		/*
		 * Convert character entities to ASCII
		 */
		$str = preg_replace_callback("/[^a-z0-9>]+[a-z0-9]+=([\'\"]).*?\\1/si", array(
			'self',
			'_convert_attribute'
		), $str);
		$str = preg_replace_callback('/<\w+.*/si', array(
			'self',
			'_decode_entity'
		), $str);

		// Remove Invisible Characters
		$str = self::remove_invisible_characters($str);

		/*
		 * Convert all tabs to spaces
		 */
		$str = str_replace("\t", ' ', $str);

		// Remove Strings that are never allowed
		$str = self::_do_never_allowed($str);

		//Kommt oft in Bildern vor
		$str = preg_replace('/<\?(php)/i', '&lt;?\\1', $str);

		$str = str_replace(array(
			'<?',
			'?>'
		), array(
			'&lt;?',
			'?&gt;'
		), $str);

		/*
		 * Compact any exploded words
		 */
		$words = array(
			'javascript',
			'expression',
			'vbscript',
			'jscript',
			'wscript',
			'vbs',
			'script',
			'base64',
			'applet',
			'alert',
			'document',
			'write',
			'cookie',
			'window',
			'confirm',
			'prompt',
			'eval'
		);

		foreach($words as $word)
		{
			$word = implode('\s*', str_split($word)).'\s*';

			$str  = preg_replace_callback('#('.substr($word, 0, -3).')(\W)#is', array(
				'self',
				'_compact_exploded_words'
			), $str);
		}

		/*
		 * Remove disallowed Javascript in links or img tags
		 * We used to do some version comparisons and use of stripos(),
		 * but it is dog slow compared to these simplified non-capturing
		 * preg_match(), especially if the pattern exists in the string
		 */

		while(true)
		{
			$original = $str;

			if(preg_match('/<a/i', $str) == 1)
			{
				$str = preg_replace_callback('#<a[^a-z0-9>]+([^>]*?)(?:>|$)#si', array(
					'self',
					'_js_link_removal'
				), $str);
			}

			if(preg_match('/<img/i', $str) == 1)
			{
				$str = preg_replace_callback('#<img[^a-z0-9]+([^>]*?)(?:\s?/?>|$)#si', array(
					'self',
					'_js_img_removal'
				), $str);
			}

			if(preg_match('/script|xss/i', $str) == 1)
			{
				$str = preg_replace('#</*(?:script|xss).*?>#si', '[removed]', $str);
			}

			if($original == $str)
			{
				unset($original);
				break;
			}
		}

		/*
		 * Sanitize naughty HTML elements
		 */
		$pattern = '#'.'<((?<slash>/*\s*)(?<tagName>[a-z0-9]+)(?=[^a-z0-9]|$)' // tag start and name, followed by a non-tag character
			.'[^\s\042\047a-z0-9>/=]*' // a valid attribute character immediately after the tag would count as a separator
			// optional attributes
			.'(?<attributes>(?:[\s\042\047/=]*' // non-attribute characters, excluding > (tag close) for obvious reasons
			.'[^\s\042\047>/=]+' // attribute characters
			// optional attribute-value
			.'(?:\s*=' // attribute-value separator
			.'(?:[^\s\042\047=><`]+|\s*\042[^\042]*\042|\s*\047[^\047]*\047|\s*(?U:[^\s\042\047=><`]*))' // single, double or non-quoted value
			.')?' // end optional attribute-value group
			.')*)' // end optional attributes group
			.'[^>]*)(?<closeTag>\>)?#isS';

		while(true)
		{
			$old_str = $str;
			$str     = preg_replace_callback($pattern, array(
				'self',
				'_sanitize_naughty_html'
			), $str);

			if($old_str == $str)
			{
				unset($old_str);
				break;
			}
		}

		/*
		 * Sanitize naughty scripting elements
		 *
		 * For example:	eval('some code')
		 * Becomes:	eval&#40;'some code'&#41;
		 */
		$str = preg_replace('#(alert|prompt|confirm|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink)(\s*)\((.*?)\)#si', '\\1\\2&#40;\\3&#41;', $str);

		// Final clean up
		$str = self::_do_never_allowed($str);

		return $str;
	}

	/**
	 * JS Links entfernen
	 *
	 * Entfernt vielleicht infizierte a Tags
	 *
	 * @param array $match
	 * @return string
	 */
	protected static function _js_link_removal($match)
	{
		return str_replace(
			$match[1],
			preg_replace(
				'#href=.*?(?:(?:alert|prompt|confirm)(?:\(|&\#40;)|javascript:|livescript:|mocha:|charset=|window\.|document\.|\.cookie|<script|<xss|d\s*a\s*t\s*a\s*:)#si',
				'',
				self::_filter_attributes($match[1])
			),
			$match[0]
		);
	}

	/**
	 * JS Bilder entfernen
	 *
	 * Entfernt vielleicht infizierte img Tags
	 *
	 * @param array	$match
	 * @return	string
	 */
	protected static function _js_img_removal($match)
	{
		return str_replace(
			$match[1],
			preg_replace(
				'#src=.*?(?:(?:alert|prompt|confirm|eval)(?:\(|&\#40;)|javascript:|livescript:|mocha:|charset=|window\.|document\.|\.cookie|<script|<xss|base64\s*,)#si',
				'',
				self::_filter_attributes($match[1])
			),
			$match[0]
		);
	}

	/**
	 * Filtert Attribute
	 *
	 * @param string $str
	 * @return string
	 */
	protected function _filter_attributes($str)
	{
		$out = '';
		if (preg_match_all('#\s*[a-z\-]+\s*=\s*(\042|\047)([^\\1]*?)\\1#is', $str, $matches))
		{
			foreach ($matches[0] as $match)
			{
				$out .= preg_replace('#/\*.*?\*/#s', '', $match);
			}
		}

		return $out;
	}

	/**
	 * Sanitize Naughty HTML
	 *
	 * Callback method for xss_clean() to remove naughty HTML elements.
	 *
	 * @param    array $matches
	 *
	 * @return    string
	 */
	protected static function _sanitize_naughty_html($matches)
	{
		$naughty_tags = array(
			'alert',
			'prompt',
			'confirm',
			'applet',
			'audio',
			'basefont',
			'base',
			'behavior',
			'bgsound',
			'blink',
			'body',
			'embed',
			'expression',
			'form',
			'frameset',
			'frame',
			'head',
			'html',
			'ilayer',
			'iframe',
			'input',
			'button',
			'select',
			'isindex',
			'layer',
			'link',
			'meta',
			'keygen',
			'object',
			'plaintext',
			'style',
			'script',
			'textarea',
			'title',
			'math',
			'video',
			'svg',
			'xml',
			'xss'
		);

		$evil_attributes = array(
			'on\w+',
			'style',
			'xmlns',
			'formaction',
			'form',
			'xlink:href',
			'FSCommand',
			'seekSegmentTime'
		);

		// First, escape unclosed tags
		if(empty($matches['closeTag']))
		{
			return '&lt;'.$matches[1];
		}
		elseif(in_array(strtolower($matches['tagName']), $naughty_tags, true)) // Is the element that we caught naughty? If so, escape it
		{
			return '&lt;'.$matches[1].'&gt;';
		}
		elseif(isset($matches['attributes'])) // For other tags, see if their attributes are "evil" and strip those
		{
			$attributes = array();

			$attributes_pattern = '#(?<name>[^\s\042\047>/=]+)(?:\s*=(?<value>[^\s\042\047=><`]+|\s*\042[^\042]*\042|\s*\047[^\047]*\047|\s*(?U:[^\s\042\047=><`]*)))#i';

			$is_evil_pattern = '#^('.implode('|', $evil_attributes).')$#i';

			while(true)
			{
				$matches['attributes'] = preg_replace('#^[^a-z]+#i', '', $matches['attributes']);

				if(preg_match($attributes_pattern, $matches['attributes'], $attribute, PREG_OFFSET_CAPTURE) != 1)
				{
					break;
				}

				if(preg_match($is_evil_pattern, $attribute['name'][0]) == 1 || (trim($attribute['value'][0]) == ''))
				{
					$attributes[] = 'xss=removed';
				}
				else
				{
					$attributes[] = $attribute[0][0];
				}

				$matches['attributes'] = substr($matches['attributes'], $attribute[0][1] + strlen($attribute[0][0]));

				if($matches['attributes'] == '')
				{
					break;
				}
			}

			$attributes = (empty($attributes) ? '' : ' '.implode(' ', $attributes));

			return '<'.$matches['slash'].$matches['tagName'].$attributes.'>';
		}

		return $matches[0];
	}

	/**
	 * Compact Exploded Words
	 *
	 * Callback method for xss_clean() to remove whitespace from
	 * things like 'j a v a s c r i p t'.
	 *
	 * @param    array $matches
	 *
	 * @return    string
	 */
	protected static function _compact_exploded_words($matches)
	{
		return preg_replace('/\s+/s', '', $matches[1]).$matches[2];
	}

	/**
	 * Nicht erlaubte Regex Funde werden hier durchgespielt
	 *
	 * @param string
	 *
	 * @return string
	 */
	protected static function _do_never_allowed($str)
	{
		$neverAllowedStr   = self::$_never_allowed_str;
		$str               = str_replace(array_keys($neverAllowedStr), $neverAllowedStr, $str);
		$neverAllowedRegex = self::$_never_allowed_regex;

		foreach($neverAllowedRegex as $regex)
		{
			$str = preg_replace('#'.$regex.'#is', '[removed]', $str);
		}

		return $str;
	}

	/**
	 * HTML Entity Decode Callback
	 *
	 * @param array
	 *
	 * @return string
	 */
	protected static function _decode_entity($match)
	{
		$match = preg_replace('|\&([a-z\_0-9\-]+)\=([a-z\_0-9\-/]+)|i', 'XSS\\1=\\2', $match[0]);

		return self::entity_decode($match[0], strtoupper('UTF-8'));
	}

	/**
	 * HTML Entities Decode
	 *
	 * This function is a replacement for html_entity_decode()
	 *
	 * The reason we are not using html_entity_decode() by itself is because
	 * while it is not technically correct to leave out the semicolon
	 * at the end of an entity most browsers will still interpret the entity
	 * correctly.  html_entity_decode() does not convert entities without
	 * semicolons, so we are left with our own little solution here. Bummer.
	 *
	 * @param    string
	 * @param    string
	 *
	 * @return    string
	 */
	protected static function _entity_decode($str, $charset = 'UTF-8')
	{
		if(!stristr($str, '&'))
		{
			return $str;
		}

		$_entities	=	array_map('strtolower', get_html_translation_table(HTML_ENTITIES, ENT_COMPAT | ENT_HTML5));

		do
		{
			$str_compare = $str;

			if(preg_match_all('/&[a-z]{2,}(?![a-z;])/i', $str, $matches))
			{
				$replace = array();
				$matches = array_unique(array_map('strtolower', $matches[0]));

				foreach($matches as &$match)
				{
					if(($char = array_search($match.';', $_entities, true)) !== false)
					{
						$replace[$match] = $char;
					}
				}

				$str = str_replace(array_keys($replace), array_values($replace), $str);
			}

			$str = html_entity_decode(
				preg_replace('/(&#(?:x0*[0-9a-f]{2,5}(?![0-9a-f;])|(?:0*\d{2,4}(?![0-9;]))))/iS', '$1;', $str),
				ENT_COMPAT | ENT_HTML5,
				$charset
			);
		}
		while($str_compare !== $str);

		return $str;
	}

	/**
	 * Attribute Conversion
	 *
	 * Used as a callback for XSS Clean
	 *
	 * @param    array
	 *
	 * @return    string
	 */
	protected static function _convert_attribute($match)
	{
		return str_replace(array(
			'>',
			'<',
			'\\'
		), array(
			'&gt;',
			'&lt;',
			'\\\\'
		), $match[0]);
	}

	/**
	 * Entfernt leere Zeichen aus einem String
	 *
	 * @param string $str
	 * @param bool   $url_encoded
	 *
	 * @return mixed
	 */
	protected static function _remove_invisible_characters($str, $url_encoded = true)
	{
		$non_displayables = array();

		if($url_encoded)
		{
			$non_displayables[] = '/%0[0-8bcef]/';
			$non_displayables[] = '/%1[0-9a-f]/';
		}

		$non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';

		while(true)
		{
			$str = preg_replace($non_displayables, '', $str, -1, $count);

			if($count == 0)
			{
				break;
			}
		}

		return $str;
	}

	/**
	 * Kontrolliert ob es sich um den
	 * Benutzer um einen Bot handelt.
	 *
	 * @return array Gibt ein assoziatives Array zurück mit Informationen über die Auswertung.
	 */
	protected static function _is_bot()
	{
		if(!empty($_SERVER['HTTP_USER_AGENT']))
		{
			$http_user_agent	=	strtolower($_SERVER['HTTP_USER_AGENT']);

			foreach(self::$botlist as $bot)
			{
				if(strpos($http_user_agent, strtolower($bot)) !== false)
				{
					return array(
						'isBot' => true,
						'version' => $bot
					);
				}
			}
		}

		return array(
			'isBot' => false,
			'version' => ''
		);
	}

	/**
	 * Gibt die aktuelle IP-Adresse des Benutzers zurück
	 *
	 * @return mixed Gibt die Ip-Adresse zurück oder ein false wenn diese nicht ermittelt werden konnte.
	 */
	protected static function _get_ip_address()
	{
		$ipMethodes = self::$ipMethodes;

		foreach($ipMethodes as $key)
		{
			if(!empty($_SERVER[$key]))
			{
				foreach(explode(',', $_SERVER[$key]) as $ip)
				{
					$ip = trim($ip);

					if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false)
					{
						return $ip;
					}
				}
			}
			elseif(getenv($key) != false)
			{
				foreach(explode(',', getenv($key)) as $ip)
				{
					$ip = trim($ip);

					if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false)
					{
						return $ip;
					}
				}
			}
		}

		return false;
	}
}