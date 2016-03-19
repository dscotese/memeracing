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

    // When a request comes to the webserver, it checks
    // how long it has been since the last check for tasks
    // to take care of.  If it has been more than CRONSEC
    // seconds, it tells the visitor's browser to ask
    // again and proceeds to do the tasks in the background.
    // -----------------------------------------------------
    define('CRONSEC',10);

    // One of the tasks described above is to remove items
    // from the database that are not being used.  The
    // items checked are rows in backing (created when a
    // visitor visits a prompt), and rows in entry.
    // If it has been more than SPAMSEC seconds, it will
    // call clearSpam().
    // ---------------------------------------------------
    define('SPAMSEC',60);

    // clearSpam removes reservations of prompts that
    // are at least RESERVE_INTERVAL old and are for
    // amounts of zero (reserved, but not backed).
    // ----------------------------------------------
    define('RESERVE_INTERVAL','1 minute');

    // clearSpam removes prompts that have no entries
    // and no backing when they are ENTRY_INTERVAL old.
    // ------------------------------------------------
    define('ENTRY_INTERVAL','4 minute');
} elseif(true) { die($_SERVER['SERVER_ADDR']);}
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
    define('CRONSEC',10);
    define('SPAMSEC',60);
    define('RESERVE_INTERVAL','1 minute');
    define('ENTRY_INTERVAL','4 minute');
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
    define('CRONSEC',10);
    define('SPAMSEC',14400);
    define('RESERVE_INTERVAL','1 hour');
    define('ENTRY_INTERVAL','7 day');
}
?>