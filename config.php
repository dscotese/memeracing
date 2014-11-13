<?php
if( count( get_included_files() ) == 1 )
{
    header("Location: http://fbi.gov/");
    die;
}

$OUR_BTC_ADDR = '1CyuAfo4r6KzspipMoXkN8ReaKf797QrPW';
if (get_magic_quotes_gpc()) {
    $process = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
    while (list($key, $val) = each($process)) {
        foreach ($val as $k => $v) {
            unset($process[$key][$k]);
            if (is_array($v)) {
                $process[$key][stripslashes($k)] = $v;
                $process[] = &$process[$key][stripslashes($k)];
            } else {
                $process[$key][stripslashes($k)] = stripslashes($v);
            }
        }
    }
    unset($process);
}

if( substr($_SERVER['SERVER_ADDR'],0,6) == '127.0.' || $_SERVER['SERVER_ADDR'] == "::1")
{
    $dbhost   = "localhost";
    $dbuser   = "root";
    $dbpasswd = "";
    $ranking_debug = true;
    $siteURL = "/memeracing/";
    $docroot = "/htdocs/memeracing";
    $fnElemNum = 1;
    $dbname   = "test";
    define('BC_SERVER','127.0.0.1');
}
elseif(preg_match('/devtest/',$_SERVER['REQUEST_URI']))
{
    $dbhost   = "localhost";
    $dbuser   = "eminizer_mracing";
    $dbpasswd = "F6XFA@A906^11SH33";
    $ranking_debug = true;
    $siteURL = "/devtest/";
    $fnElemNum = 1;
    $docroot = "/home/eminizer/public_html/memeracing.net/devtest";
    $dbname   = "eminizer_mrtest";
    define('BC_SERVER','127.0.0.1');
}
else
{
    $dbhost   = "localhost";
    $dbuser   = "eminizer_mracing";
    $dbpasswd = "F6XFA@A906^11SH33";
    $siteURL = "/";
    $domain = "http://memeracing.net";
    $fnElemNum = 0;
    $docroot = "/home/eminizer/public_html/memeracing.net";
    $dbname   = "eminizer_memeracing";
    define('BC_SERVER','91.203.74.202');
}
?>