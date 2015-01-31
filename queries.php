<?php

@set_magic_quotes_runtime(0);
global $ranking_debug;
$MAXSLOTS=99;

include_once("config.php");
include('scripts/mysql_djs.php');

// Define a global object to hold non-fatal SQL errors
// ---------------------------------------------------
$SQLErrors = array();

$db = new sql_db_r($dbhost, $dbuser, $dbpasswd, $dbname);
if('' == $db->db_connect_id )
{
    die("Can't connect to $dbname");
}

if( '' == $db->scalar("SHOW TABLES LIKE 'backing'") )
{
    setup_db($dbname);
}

// Debug on local installations (127.0.0...), for testing
// ------------------------------------------------------
if(preg_match('/^127\.0\./', $_SERVER['SERVER_ADDR'])  || $_SERVER['SERVER_ADDR'] == "::1")
{
    error_reporting(E_ERROR|E_WARNING|E_PARSE);
    if( $_GET['test'] > '')
    {
    }
}

function setup_db()
{
    global $db;
    $sql[] = "CREATE TABLE entry  (
        entry_id    INT(11) AUTO_INCREMENT NOT NULL,
        inspire_id  INT(11) NULL,
        player_id   INT(11) NOT NULL,
        entry       VARCHAR(140) NULL,
        created     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        slot        INT(11) NULL,
        level       INT DEFAULT 0,
        hash        CHAR(32),
        btc_addr    CHAR(34),
        PRIMARY KEY(entry_id),
        INDEX idx_slot(slot),
        INDEX idx_hash(hash) )";

    $sql[] = "CREATE TABLE inspire  (
        inspire_id  int(11) AUTO_INCREMENT NOT NULL,
        inspiration varchar(140) NULL,
        created     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        slot        int(11) NULL,
        hash        CHAR(32),
        PRIMARY KEY(inspire_id),
        INDEX idx_slot(slot),
        INDEX idx_hash(hash) )";

    $sql[] = "CREATE TABLE backing (
        backing_id      INT AUTO_INCREMENT,
        amount          DECIMAL(12,8) NOT NULL,
        inspire_id      INT NOT NULL,
        entry_id        INT,
        tx_id           CHAR(64),
        btc_addr        char(34),
        ts              TIMESTAMP,
        contest_id      INT,
        PRIMARY KEY (backing_id),
        INDEX idx_inspire(inspire_id),
        INDEX idx_entry(entry_id),
        INDEX idx_when(ts),
        UNIQUE INDEX idx_ts(tx_id) )";

    $sql[] = "CREATE TABLE player (
        player_id       INT AUTO_INCREMENT,
        created         TIMESTAMP,
        email           VARCHAR(100),
        secret          VARCHAR(16),
        PRIMARY KEY (player_id) )";

    $sql[] = "CREATE TABLE contest (
        contest_id      INT AUTO_INCREMENT,
        inspire_id      INT,
        level           INT DEFAULT 1,
        created         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deadline        DATETIME,
        first           INT,
        second          INT,
        third           INT,
        fourth          INT,
        fifth           INT,
        sixth           INT,
        seventh         INT,
        PRIMARY KEY (contest_id),
        INDEX idx_when(created),
        INDEX idx_dead(deadline),
        INDEX idx_inspire(inspire_id) )";

    $sql[] = "CREATE TABLE contest_entry (
        ce_id           INT AUTO_INCREMENT,
        contest_id      INT,
        entry_id        INT,
        PRIMARY KEY(ce_id) )";

    $sql[] = "CREATE TABLE slot_log(
        s_log_id        INT AUTO_INCREMENT,
        event           CHAR(4),
        ts              TIMESTAMP,
        inspire_id      INT,
        slot            INT,
        entry_id        INT,
        PRIMARY KEY(s_log_id),
        INDEX idx_when(ts),
        INDEX idx_event(event))";

    $sql[] = "CREATE TABLE pay_log(
        p_log_id        INT AUTO_INCREMENT,
        contest_id      INT,
        amount          DECIMAL(12,8),
        destination     char(34),
        ts              TIMESTAMP,
        completed       DATETIME,
        PRIMARY KEY(p_log_id),
        INDEX idx_ctst(contest_id),
        INDEX idx_when(ts) )";

    $sql[] = "CREATE TABLE config(
        cfg_name    VARCHAR(63),
        cfg_value   VARCHAR(1023),
        ts          TIMESTAMP,
        PRIMARY KEY(cfg_name) )";

    $sql[] = "CREATE TABLE ipn_log(
        ipn_id      INT AUTO_INCREMENT,
        result      VARCHAR(255),
        ts          TIMESTAMP,
        PRIMARY KEY (ipn_id) )";

    $sql[] = "CREATE TABLE err_log(
        err_id      INT AUTO_INCREMENT,
        err         VARCHAR(2042),
        ts          TIMESTAMP,
        PRIMARY KEY (err_id) )";

    $sql[] = "CREATE TABLE player_order(
        player_id       INT,
        contest_id      INT,
        ordord          INT,
        ts              TIMESTAMP,
        PRIMARY KEY(player_id, contest_id),
        INDEX idx_contest(contest_id) )";

    foreach($sql as $s)
    {
        $db->sql_query($s);
    }
}

function dbQuote($raw)
{
    return trim(str_replace(array("'","\\"),array("''","\\\\"),$raw));
}

function getSelect($table, $field, $val, $selected, $blockAll = false)
{
    global $db;

    if( '' == $val )
    {
        $valField = '';
    }
    else
    {
        $valField = ", $val as val";
    }
    $sql = "SELECT DISTINCT $field AS disp $valField FROM $table ORDER BY $field";
echo "<!-- $sql -->";
    $data = $db->sql_query($sql);
    $opts = $blockAll ? '' : "<option value='all'>Include All</option>";
    while($row = $db->sql_fetchrow())
    {
        $disp = $row['disp'];
        $ov = $val == '' ? $disp : $row['val'];
        $opts .= "<option value='$ov'>$disp</option>\n";
    }
    $opts = str_replace("value='$selected'","value='$selected' selected='1'", $opts);
    return $opts;
}

function storeSecret($pid, $secret)
{
    global $db;
    $sql = "UPDATE player SET secret='$secret' WHERE player_id=$pid";
    $db->sql_query($sql);
}

function ipnLog($str)
{
    global $db;
    $db->sql_query("INSERT INTO ipn_log(result)VALUES('$str')");
}

function errLog($str)
{
    global $db;
    $db->sql_query("INSERT INTO err_log(err)VALUES('$str')");
}

function getJSON($table, $field, $val)
{
    global $db;

    if( '' == $val )
    {
        $valField = '';
    }
    else
    {
        $valField = ", $val as val";
    }
    $sql = "SELECT DISTINCT $field AS disp $valField FROM $table ORDER BY $field";
    $data = $db->sql_query($sql);
    $opts = array();
    while($row = $db->sql_fetchrow())
    {
        $disp = $row['disp'];
        $ov = $val == '' ? $disp : $row['val'];
        $opts[] = array('value' => $ov, 'display' => $disp);
    }
    $opts = json_encode($opts);
    return $opts;
}

function test()
{
    global $db;
    return $db->result("SHOW TABLES");
}

function getInspires($where = "")
{
    global $db;
    $sql = "SELECT i.*, IFNULL(SUM(b.amount),0) as invested, COUNT(DISTINCT b.btc_addr) as investors,
            COUNT(DISTINCT c.contest_id) as numc,
            (SELECT COUNT(e.entry_id) FROM entry e WHERE e.inspire_id=i.inspire_id) AS nume
        FROM inspire i LEFT JOIN backing b ON b.inspire_id=i.inspire_id AND b.entry_id IS NULL
            LEFT JOIN contest c ON c.inspire_id=i.inspire_id
        $where
        GROUP BY i.inspire_id ORDER BY invested DESC, nume DESC, i.inspiration";
    return $db->result($sql);
}

function emailID($email)
{
    global $db;
    $e = dbQuote($email);
    $ret = $db->scalar("SELECT player_id FROM player WHERE email='$e'");

    if(!$ret)
    {
        $db->sql_query("INSERT INTO player(email)VALUES('$e')");
        $ret = $db->sql_nextid();
    }
    return $ret;
}

function normHash($str)
{
    return md5(normalize($str));
}

function normalize($str)
{
    return preg_replace("/[^a-z]/",'',strtolower($str));
}

function getByHash($table,$str)
{
    global $db;
    $hash = normHash($str);
    $sql = "SELECT * FROM $table WHERE hash='$hash'";
    return $db->result($sql);
}

function storePrompt($prompt)
{
    global $db;
    $p = dbQuote($prompt);
    $hash = normHash($prompt);
    $pid=dbQuote($_POST['edit']);
    if($pid != '')
    {
        if(@in_array($pid, $_SESSION['myPrompts']))
        {
            $sql = '' == $p
                ? "DELETE FROM inspire"
                : "UPDATE inspire SET inspiration='$p', hash='$hash'";

            $db->sql_query("$sql WHERE slot IS NULL AND inspire_id=$pid");
        }
        else
        {
            $SQLErrors[] = "Session Expired.";
            return false;
        }
    }
    else
    {
        $db->sql_query("INSERT INTO inspire(inspiration, hash)
            VALUES('$p','$hash')");
        $pid = $db->sql_nextid();
    }
    return $pid;
}

function storeAnswer($answer, $email,$iid, $addr)
{
    if('Enter your email address' == $email)
    {
        return 'No email address entered.';
    }
    global $db;
    $eid = emailID($email);
    $p = dbQuote($answer);
    $a = dbQuote($addr);
    $hash = normHash($answer);
    if(!$eid)
    {
        $SQLErrors[] = 'Email address could not be added.';
        return false;
    }
    $entry_id=dbQuote($_POST['edit']);
    if($entry_id > '')
    {
        if(@in_array($entry_id, $_SESSION['myAnswers']))
        {
            $sql = '' == $p
                ? "DELETE FROM entry"
                : "UPDATE entry INNER JOIN player USING(player_id)
                    SET entry='$p', hash='$hash', email=$eid";

            $db->sql_query("$sql WHERE slot IS NULL AND entry_id=".$entry_id);
            if($db->sql_affectedrows() == 0)
            {
                return 'Entry has already been reserved.';
            }
        }
        else
        {
            $SQLErrors[] = 'Session Expired.';
            return false;
        }
    }
    else
    {
        $db->sql_query("INSERT INTO entry(entry, player_id, inspire_id, hash, btc_addr)
            VALUES('$p', $eid, $iid,'$hash','$a')");
        $entry_id = $db->sql_nextid();
    }
    return $entry_id;
}

function getEntries($inspire_id)
{
    $where = is_numeric($inspire_id)
        ? "WHERE e.inspire_id=$inspire_id"
        : $inspire_id;

    global $db, $MAXSLOTS;
    $sql = "SELECT * FROM (
            SELECT e.*, IFNULL(SUM(CASE WHEN c.first IS NULL THEN b.amount ELSE 0 END),0) as backing
            FROM entry e
                LEFT JOIN backing b ON b.entry_id=e.entry_id
                LEFT JOIN contest c ON c.contest_id=b.contest_id
            $where
            GROUP BY entry_id
            ORDER BY level DESC, CASE WHEN e.slot IS NULL THEN 1 ELSE 0 END,
                e.created DESC LIMIT 99) i
        ORDER BY level DESC, entry";

    $ret = $db->result($sql);
    if(count($ret) >= $MAXSLOTS)
    {
        freeEntrySlots($inspire_id);
    }
    return $ret;
}

function getBackedEntries($inspire_id, $level)
{
    if(!is_numeric($inspire_id))
    {
        return false;
    }
    global $db;
    $sql = "SELECT e.*, SUM(b.amount) as backing, p.email
        FROM entry e INNER JOIN backing b USING(entry_id, inspire_id)
            INNER JOIN player p USING(player_id)
        WHERE inspire_id=$inspire_id AND level=$level
            AND contest_id IS NULL AND b.amount > 0
        GROUP BY entry_id ORDER BY entry_id";
    return $db->result($sql);
}

function getInspire($i_id)
{
    if(!is_numeric($i_id))
    {
        return false;
    }
    global $db;
    return $db->scalar("SELECT * FROM inspire WHERE inspire_id=$i_id");
}

// Identify the oldest prompt that has the least backing
// Also puts the slot into the passed in var.
// -----------------------------------------------------
function findLowBid(&$slot)
{
    global $db;

    $sql = "SELECT inspire_id, slot, SUM(amount) as cap
        FROM inspire INNER JOIN backing USING(inspire_id)
        GROUP BY inspire_id
        ORDER BY cap, created LIMIT 1";
    if($lowBid = $db->scalar($sql))
    {
        $slot = $lowBid['slot'];
        return $lowBid['inspire_id'];
    }
    else
    {
        return 0;
    }
}

function getEntry($entry_id)
{
    global $db;
    return $db->scalar("SELECT * FROM entry
        WHERE entry_id=$entry_id");
}

function getSlot($iid, $eid = null)
{
    global $db;
    $table = $eid ? 'entry' : 'inspire';
    $where = "WHERE inspire_id=$iid AND entry_id".($eid ? "=$eid" : " IS NULL");
    $using = $eid ? "inspire_id, entry_id" : "inspire_id";

    $sql = "SELECT slot, SUM(amount) AS amt
        FROM $table i INNER JOIN backing b USING($using)
        $where GROUP BY inspire_id, entry_id";
    $slot = $db->scalar($sql);
    if($slot['amt'] !== false)
    {
        $db->sql_query("UPDATE backing SET ts=NOW()
            $where AND amount=0 AND tx_id IS NULL");
    }
    return $slot['slot'];
}

function newSlot($iid, $eid = null)
{
    global $db, $MAXSLOTS;
    if($eid)
    {
        freeEntrySlots($iid);
    }
    else
    {
        freeInspireSlots();
    }

    $table = $eid ? 'entry' : 'inspire';
    $rsv = $eid ? 'RSVE' : 'RSVI';
    $qeid = $eid ? "'$eid'" : 'null';
    $where = $eid ? "AND inspire_id=$iid" : "";
    $sql = "SELECT DISTINCT slot FROM $table WHERE slot IS NOT NULL $where ORDER BY slot";
    $i = 0;
    if($taken = $db->result($sql))
    {
        while($taken[$i]['slot'] == ++$i)
        {
            if($i == $MAXSLOTS)
            {
                return 0;
            }
        }
    }
    else
    {
        $i = 1;
    }
    $db->sql_query("INSERT INTO slot_log(event, inspire_id, entry_id, slot)
        VALUES('$rsv', $iid, $qeid, $i)");
    $db->sql_query("INSERT INTO backing(inspire_id, entry_id, amount)
        VALUES($iid, $qeid, 0)");
    $where = $eid ? "inspire_id=$iid AND entry_id=$eid" : "inspire_id=$iid";
    $db->sql_query("UPDATE $table SET slot=$i WHERE $where");
    return $i;
}

function freeInspireSlots()
{
    global $db, $reservation;

    // Free 1 unbacked and unanswered inspire slot with expired reservation
    // --------------------------------------------------------------------
    $sql = "SELECT DISTINCT r.backing_id, r.inspire_id, i.slot
        FROM inspire i INNER JOIN backing r USING(inspire_id)
            LEFT JOIN backing b ON b.inspire_id=r.inspire_id
                AND IFNULL(b.tx_id,b.entry_id) IS NOT NULL
        WHERE r.tx_id IS NULL AND b.backing_id IS NULL
            AND r.ts < NOW() - INTERVAL $reservation
        ORDER BY r.ts LIMIT 1";  // "> reservation is defined in controller.php
    if( $unbacked = $db->result($sql) )
    {
        $logs = array();
        $dels = array();
        $insps = array();
        $logged = '';
        foreach($unbacked as $row)
        {
            extract($row);
            $dels[] = $backing_id;
            $insps[] = $inspire_id;
            if($logged != "$inspire_id,$slot" && $slot > '')
            {
                $logs[] = "('FREE',$inspire_id,$slot)";
            }
            $logged = "$inspire_id,$slot";
        }
        $db->sql_query("INSERT INTO slot_log(event, inspire_id, slot)
            VALUES ".implode(',', $logs));
        $db->sql_query("UPDATE inspire SET slot=NULL WHERE inspire_id IN(".implode(',', $insps).')');
        $db->sql_query("DELETE FROM backing WHERE backing_id IN(".implode(',', $dels).')');
    }
}

function freeEntrySlots($iid)
{
    global $db, $reservation;

    // Free 1 unbacked entry slot with expired reservation
    // ---------------------------------------------------
    $sql = "SELECT DISTINCT r.backing_id, i.entry_id, i.slot, i.inspire_id
        FROM entry i INNER JOIN backing r USING(inspire_id, entry_id)
            LEFT JOIN backing b ON b.inspire_id=r.inspire_id
                AND b.entry_id=r.entry_id AND b.tx_id IS NOT NULL
        WHERE r.tx_id IS NULL AND b.backing_id IS NULL AND i.inspire_id=$iid
            AND r.ts < NOW() - INTERVAL $reservation
        ORDER BY r.ts LIMIT 1";  // "> reservation is defined in controller.php
    if( $unbacked = $db->result($sql) )
    {
        $logs = array();
        $dels = array();
        $ents = array();
        foreach($unbacked as $row)
        {
            extract($row);
            $dels[] = $backing_id;
            $ents[] = $entry_id;
            $logs[] = "('FREE',$entry_id,$inspire_id,$slot)";
        }
        $db->sql_query("INSERT INTO slot_log(event, entry_id, inspire_id, slot)
            VALUES ".implode(',', $logs));
        $db->sql_query("UPDATE entry SET slot=NULL WHERE inspire_id=$iid
            AND entry_id IN(".implode(',', $ents).')');
        $db->sql_query("DELETE FROM backing WHERE backing_id IN(".implode(',', $dels).')');
    }
}

function getBackers($cid)
{
    global $db;

    return $db->result("SELECT b.* FROM backing b
            INNER JOIN contest c USING(inspire_id)
        WHERE b.tx_id IS NOT NULL AND c.contest_id=$cid AND b.entry_id IS NULL");
}

function getLastCron()
{
    global $db;

    return $db->scalar("SELECT cfg_value FROM config WHERE cfg_name='Last Cron'");
}

function setLastCron()
{
    global $db;

    $db->sql_query("REPLACE INTO config(cfg_name, cfg_value)
        VALUES ('Last Cron',UNIX_TIMESTAMP(NOW()))");
}

function findTx($tx)
{
    global $db;
    $sql = "SELECT * FROM backing WHERE tx_id='$tx'";
    return $db->scalar($sql);
}

function storeTx($amt, $txid)
{
    global $db;
    $amt = number_format($amt,8);
    if(preg_match("/\.[0-9]{4}([0-9][0-9])([0-9][1-9])$/",$amt, $slots))
    {
        $eSlot = $slots[1];
        $iSlot = $slots[2];
        $iSlot = "i.slot=$iSlot";
        $eSlot = $eSlot == '00'
            ? "e.slot IS NULL AND entry_id IS NULL"
            : "e.slot = $eSlot";

        $sql = "INSERT INTO backing(inspire_id, entry_id, amount, tx_id, btc_addr)
            SELECT i.inspire_id, e.entry_id, $amt, '$txid', null
            FROM inspire i LEFT JOIN entry e ON e.inspire_id=i.inspire_id AND $eSlot
            WHERE $iSlot";
        $db->sql_query($sql);
        return true;
    }
    return false;
}

function storeBet($txid, $betAddr)
{
    global $db;
    $sql = "UPDATE backing SET btc_addr='$betAddr' WHERE tx_id='$txid'";
    $db->sql_query($sql);
    return 1;
}

function getNoBettors($count = 5)
{
    global $db;
    $sql = "SELECT tx_id FROM backing
        WHERE btc_addr IS NULL AND tx_id IS NOT NULL
        ORDER BY backing_id ASC LIMIT $count";
    $nb = $db->sql_query($sql);

    $ret = $db->column(0, $nb);
    return count($ret) > 0 ? $ret : false;
}

function getBacking($iid, $eid = null)
{
    global $db;
    $where = "WHERE inspire_id=$iid AND entry_id".($eid ? "=$eid" : " IS NULL");
    $sql = "SELECT SUM(amount) FROM backing $where";
    return $db->scalar($sql);
}

function getContestable()
{
    global $db;
    $sql = "SELECT inspire_id, COUNT(DISTINCT b.entry_id) AS c, i.slot, e.level
        FROM backing b INNER JOIN inspire i USING(inspire_id)
            INNER JOIN entry e USING(inspire_id, entry_id)
        WHERE amount > 0 AND contest_id IS NULL
        GROUP BY inspire_id, e.level HAVING c > 9";
    return $db->result($sql);
}

function getEnded()
{
    global $db;
    $sql = "SELECT contest_id FROM contest
        WHERE first IS NULL AND NOW() > deadline";
    return $db->result($sql);
}

function getDebts()
{
    global $db;

    return $db->result("SELECT GROUP_CONCAT(p_log_id) as ids,
            destination, SUM(amount) as amount
        FROM pay_log WHERE completed IS NULL
        GROUP BY destination");
}

function getOrder($cid,$pid)
{
    global $db;
    $sql = "SELECT ordord FROM player_order
        WHERE player_id=$pid AND contest_id=$cid";
    return $db->scalar($sql);
}

function getOrders($cid)
{
    global $db;
    $sql = "SELECT DISTINCT po.* FROM player_order po
        INNER JOIN contest_entry USING(contest_id)
        INNER JOIN entry USING(player_id, entry_id)
        WHERE contest_id=$cid ORDER BY player_id";
    return $db->result($sql);
}

function makeContest($iid, $level, $minutes, $ids)
{
    global $db;
    $sql = "INSERT INTO contest(inspire_id, level, deadline) VALUES
        ($iid, $level, NOW() + INTERVAL $minutes minute)";
    $db->sql_query($sql);
    $cid = $db->sql_nextid();

    $inIds = implode(',', $ids);
    $sql = "UPDATE entry SET level=$level WHERE entry_id IN($inIds)";
    $db->sql_query($sql);

    $sql = "INSERT INTO contest_entry(entry_id, contest_id)
        SELECT entry_id, $cid FROM entry WHERE entry_id IN ($inIds)";
    $db->sql_query($sql);

    $sql = "UPDATE backing SET contest_id=$cid
        WHERE inspire_id=$iid AND entry_id IN ($inIds)";
    $db->sql_query($sql);

    return $cid;
}

function storeOrder($pid, $cid, $order)
{
    global $db;
    $sql = "REPLACE INTO player_order(player_id, contest_id, ordord)
        VALUES($pid, $cid, $order)";
    $db->sql_query($sql);
}

function getPlayerByEmail($email)
{
    global $db;
    return $db->scalar("SELECT * FROM player WHERE email='$email'");
}

function getEmails($parray)
{
    global $db;
    $pids = implode(',', $parray);
    $sql = "SELECT email, player_id FROM player WHERE player_id IN ($pids)";
    $data = $db->result($sql);
    foreach($data as $row)
    {
        extract($row);
        $ret[$player_id] = $email;
    }
    return $ret;
}



function getContestEntries($cid)
{
    global $db;
    $sql = "SELECT e.* FROM contest_entry
        INNER JOIN entry e USING(entry_id)
    WHERE contest_id=$cid ORDER BY entry_id";
    return $db->result($sql);
}

function getBetsByEntry($cid)
{
    global $db;
    $sql = "SELECT entry_id, SUM(amount) as total
        FROM backing INNER JOIN contest_entry USING(contest_id,entry_id)
        WHERE contest_id=$cid GROUP BY entry_id ORDER BY entry_id";
    return $db->result($sql);
}

function getTotalBet($inList, $cid)
{
    global $db;
    $sql = "SELECT SUM(amount) FROM backing
        WHERE contest_id=$cid AND entry_id IN ($inList)";
    return $db->scalar($sql);
}

function getBettors($cid)
{
    global $db;
    $sql = "SELECT btc_addr, SUM(amount) as bet, entry_id
        FROM backing WHERE contest_id=$cid AND amount > 0 AND entry_id IS NOT NULL
        GROUP BY btc_addr, entry_id";
    return $db->result($sql);
}

function recordRace($wtol,$cid)
{
    global $db;
    $sets = array();
    foreach(array('first','second','third','fourth','fifth','sixth','seventh') as $i => $field)
    {
        $sets[] = "$field=".$wtol[$i];
    }
    $sets = implode(',', $sets);
    $sql = array();
    $sql[] = "UPDATE contest SET $sets WHERE contest_id=$cid";

    $sql[] = "UPDATE entry INNER JOIN contest_entry ce USING(entry_id)
        SET level = level-1
        WHERE contest_id=$cid AND entry_id != ".$wtol[0];

    foreach($sql as $s)
    {
        $db->sql_query($s);
    }
}

function logPay($dAmt, $destAddr, $contest_id = 'null')
{
    global $db;
    $sql = "INSERT INTO pay_log(contest_id, amount, destination) VALUES
        ($contest_id, $dAmt, '$destAddr')";
    $db->sql_query($sql);
}

function decode($code)
{
    global $db;
    $c = dbQuote($code);
    return $db->scalar("SELECT * FROM player WHERE secret='$c'");
}

function getPlayer($pid)
{
    global $db;
    return $db->scalar("SELECT * FROM player WHERE player_id='$pid'");
}

function getSecret($pid)
{
    global $db;
    return $db->scalar("SELECT secret FROM player WHERE player_id=$pid");
}

function getPlayerContests($pid)
{
    global $db;
    $sql = "SELECT DISTINCT c.contest_id, i.inspiration
        FROM contest_entry ce INNER JOIN entry e USING(entry_id)
            INNER JOIN inspire i USING(inspire_id)
            INNER JOIN contest c USING (contest_id)
        WHERE e.player_id=$pid AND c.deadline > NOW()
        ORDER BY c.contest_id";
    return $db->result($sql);
}

function getInspiration($cid)
{
    global $db;
    return $db->scalar("SELECT inspiration
        FROM inspire INNER JOIN contest USING(inspire_id)
        WHERE contest_id=$cid");
    return date(DATE_RFC822,strtotime("now + $cid"));
}

function expedite($cid, $fromNow)
{
    global $db;
    $sql = "UPDATE contest
        SET deadline=NOW() + INTERVAL $fromNow
        WHERE contest_id=$cid";
    $db->sql_query($sql);
    return date(DATE_RFC822,strtotime("now + $fromNow"));
}

function getContests($iid)
{
    global $db;
    $sql = "SELECT * FROM contest WHERE inspire_id=$iid
        ORDER BY CASE WHEN first IS NULL THEN 1 ELSE 0 END, level DESC, created";
    return $db->result($sql);
}

function getSecrets($p)
{
    global $db;
    return $db->scalar("SELECT secret FROM player WHERE email='Player_$p@memeracing.net'");
}

function getContest($cid)
{
    global $db;
    $sql = "SELECT * FROM contest INNER JOIN inspire USING(inspire_id)
        WHERE contest_id=$cid";
    return $db->scalar($sql);
}

function getPayouts($cid)
{
    global $db;
    $sql = "SELECT * FROM pay_log WHERE contest_id=$cid";
    return $db->result($sql);
}
?>
