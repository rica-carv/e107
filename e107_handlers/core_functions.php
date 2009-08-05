<?php
/*
 * e107 website system
 *
 * Copyright (C) 2001-2008 e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * e107 Core functions
 *
 * $Source: /cvs_backup/e107_0.8/e107_handlers/core_functions.php,v $
 * $Revision: 1.1 $
 * $Date: 2009-08-05 19:53:47 $
 * $Author: secretr $
*/

//
// Use these to combine isset() and use of the set value. or defined and use of a constant
// i.e. to fix  if($pref['foo']) ==> if ( varset($pref['foo']) ) will use the pref, or ''.
// Can set 2nd param to any other default value you like (e.g. false, 0, or whatever)
// $testvalue adds additional test of the value (not just isset())
// Examples:
// $something = pref;  // Bug if pref not set         ==> $something = varset(pref);
// $something = isset(pref) ? pref : "";              ==> $something = varset(pref);
// $something = isset(pref) ? pref : default;         ==> $something = varset(pref,default);
// $something = isset(pref) && pref ? pref : default; ==> use varsettrue(pref,default)
//
function varset(&$val, $default='')
{
	if (isset($val)) { return $val; }
	return $default;
}

function defset($str, $default='')
{
	if (defined($str)) { return constant($str); }
	return $default;
}

//
// These variants are like the above, but only return the value if both set AND 'true'
//
function varsettrue(&$val, $default='')
{
	if (isset($val) && $val) { return $val; }
	return $default;
}

function defsettrue($str,$default='')
{
	if (defined($str) && constant($str)) {return constant($str); }
	return $default;
}

function e107_include($fname)
{
	global $e107_debug;
	$ret = ($e107_debug ? include($fname) : @include($fname));
	return $ret;
}

function e107_include_once($fname)
{
	global $e107_debug;
	if(is_readable($fname))
	{
		$ret = (!$e107_debug)? @include_once($fname) : include_once($fname);
	}
	return (isset($ret)) ? $ret : '';
}

function e107_require_once($fname)
{
	global $e107_debug;
	$ret = ($e107_debug ? require_once($fname) : @require_once($fname));
	return $ret;
}

function e107_require($fname)
{
	global $e107_debug;
	$ret = ($e107_debug ? require($fname) : @require($fname));
	return $ret;
}


function print_a($var, $return = FALSE)
{
	if( ! $return)
	{
		echo '<pre>'.htmlspecialchars(print_r($var, TRUE), ENT_QUOTES, 'utf-8').'</pre>';
		return TRUE;
	}
	else
	{
		return '<pre>'.htmlspecialchars(print_r($var, true), ENT_QUOTES, 'utf-8').'</pre>';
	}
}

/**
 * Strips slashes from a var if magic_quotes_gqc is enabled
 *
 * @param mixed $data
 * @return mixed
 */
function strip_if_magic($data)
{
	if (MAGIC_QUOTES_GPC == true)
	{
		return array_stripslashes($data);
	}
	else
	{
		return $data;
	}
}

/**
 * Strips slashes from a string or an array
 *
 * @param mixed $value
 * @return mixed
 */
function array_stripslashes($data)
{
	return is_array($data) ? array_map('array_stripslashes', $data) : stripslashes($data);
}

function echo_gzipped_page()
{

    if(headers_sent())
	{
        $encoding = false;
    }
	elseif( strpos($_SERVER["HTTP_ACCEPT_ENCODING"], 'x-gzip') !== false )
	{
        $encoding = 'x-gzip';
    }
	elseif( strpos($_SERVER["HTTP_ACCEPT_ENCODING"],'gzip') !== false )
	{
        $encoding = 'gzip';
    }
	else
	{
        $encoding = false;
    }

    if($encoding)
	{
        $contents = ob_get_contents();
        ob_end_clean();
        header('Content-Encoding: '.$encoding);
        print("\x1f\x8b\x08\x00\x00\x00\x00\x00");
        $size = strlen($contents);
        $contents = gzcompress($contents, 9);
        $contents = substr($contents, 0, $size);
        print($contents);
        exit();
    }
	else
	{
        ob_end_flush();
        exit();
    }
}

?>