<?php

// Reservation is how long we leave a slot open before reusing it.
// The amount of time the user is given is hardcoded to 15 minutes.
// The extra 45 minutes is because blockchain.info has to call the callback
//  to lock in the backing, and we want to give it enough time.
// A whole hour might be overkill since the target is 10 minutes, but
// there is little reason not to be overly cautious here.
// ------------------------------------------------------------------------
$reservation = "1 hour";

include("process.php");
include("Lightning.php");

if(getLastCron() < time() - CRONSEC && count($_POST) == 0)
{
    // Not putting setLastCron first leads to infinite redirect.
    // ---------------------------------------------------------
    setLastCron();

    // Have the user make another request:
    global $ranking_debug;
    if($ranking_debug)
    {
        do_cron();
        header("Location: ".$_SERVER['REQUEST_URI']);
    }
    else
    {
        header("Location: ".$_SERVER['REQUEST_URI']);
        do_cron();
    }
    die();
}

function do_cron()
{
    if($who = getNoBettors())
    {
        foreach($who as $needAddr)
        {
            errLog("No addr for $needAddr");
        }
    }

    // Remove unbacked prompts and zero-backing every 4 hours
    // ------------------------------------------------------
    if(($glcs = getLastCron('spamChk')) < time() - SPAMSEC)
    {
        clearSpam();
        setLastCron('spamChk');
    }

    // Check to see if any prompt has at least 10 backed entries
    // ---------------------------------------------------------
    if( $newContests = getContestable() )
    {
        // The recipe for selecting 7 of 10 is to md5 hash the
        // last two digits (prompt slot)
        // ---------------------------------------------------
        foreach($newContests as $nc)
        {
            extract($nc);
            $me = substr(md5($slot),0,1);
            $backed = getBackedEntries($inspire_id, $level);
            $setOfSeven = array(
                '0' => array(0,1,2,3,5,6,9),
                '1' => array(0,1,2,3,5,6,7),
                '2' => array(0,1,2,4,5,6,8),
                '3' => array(0,1,2,4,6,7,8),
                '4' => array(0,1,2,4,5,7,8),
                '5' => array(0,2,3,4,6,7,8),
                '6' => array(0,2,4,6,7,8,9),
                '7' => array(0,3,4,5,6,7,8),
                '8' => array(0,3,4,5,6,8,9),
                '9' => array(1,3,4,5,6,8,9),
                'a' => array(1,3,4,5,7,8,9),
                'b' => array(1,3,5,6,7,8,9),
                'c' => array(1,4,5,6,7,8,9),
                'd' => array(2,3,4,5,7,8,9),
                'e' => array(2,3,4,6,7,8,9),
                'f' => array(3,4,5,6,7,8,9) );

            // Create the contest and add the entries to it
            // --------------------------------------------
            $entries = array();
            $entry_ids = array();
            foreach($setOfSeven[$me] as $index)
            {
                $entry_ids[] = $backed[$index]['entry_id'];
                $entries[] = $backed[$index];
            }
            $contest_id = makeContest($inspire_id, $level+1, 1440, $entry_ids);
            $sent = array();
            foreach($entries as $e)
            {
                if(!in_array($e['player_id'],$sent))
                {
                    $sent[] = $e['player_id'];
                    sendEntrantEmail($e, $entries, $contest_id);
                }
            }
        }
    }
    if($ended = getEnded())
    {
        foreach($ended as $end)
        {
            endContest($end['contest_id']);
        }
    }
 
    if($Invoice = lookupInvoice($r_hash))
    {
	if(isset($Invoice->settled) && $Invoice->settled){
		LNPayment($Invoice->memo,'',$Invoice->value);
//		bwb_received();		
	}	
    }

}

function LNPayment( $memo, $payerURI, $amount ) {
    if (!checkLNBet($memo, $payerURI, $amount)) {
        storeLNTx($memo, $payerURI, $amount);
    }
    // Make LND (the call in do_cron) stop returning the same invoice repeatedly.
}


function bwb_hero()
{
    bwb_slideshow();
}

function bwb_slideshow()
{
    global $siteURL;
    include('slideshow.html');
}

function bwb_payout()
{
    $ret = isPost("sendAll");
    if($debts = getDebts())
    {
        $owed = array();
        $plids = array();
        foreach($debts as $debt)
        {
            extract($debt);
            if($amount > 0.0005)
            {
                $owed[$destination] = $amount - 0.0005;
                $plids[] = $ids;
            }
        }
        if(count($owed) > 0)
        {
            $plids = implode(',', $plids);
            $ret = "Would send ".print_r($owed,true)." to cover ($plids)<br/>
                <form method='post'>
                    <input type='hidden' value='".json_encode($owed)."' name='owed' />
                    <input type='hidden' value='$ids' name='plids' />
                    Password<input type='password' name='pass1' /><br/>
                    2nd Password<input type='password' name='pass2' /><br/>
                    <input type='submit' value='Pay Now'/>
                </form>";
        }
    }
    return $ret;
}

function bwb_sendAll()
{
    global $OUR_BTC_ADDR;
    if($_POST['owed'])
    {
        include_once('JsonRPC.php');
        extract($_POST);
        try
        {
            $url = "https://7a9b3561-5d64-590f-24ba-dfe2b9cd9d90:$pass1@blockchain.info";
            errLog("Got URL $url from IP ".dbQuote($_SERVER['REMOTE_ADDR']));
            $blockChain = new JsonRpc($url);

            $blockChain->walletpassphrase($pass2,2);
            $ret = $blockChain->sendmany($OUR_BTC_ADDR,$owed);
            if(strlen($ret) == 64)
            {
                markPaid($plids);
                return "$plids have been paid.";
            }
            else
            {
                return "Got back $ret";
            }
        }
        catch( Exception $e )
        {
            $msg = $e->getMessage();
            $code = $e->getCode();
            $trace = $e->getTrace();
            $err = "$msg ($code) from ".$trace[0]['function'].", line ".$trace[0]['line'];
            errLog($err);
            return (preg_match('/^127\.0\./', $_SERVER['SERVER_ADDR']) || $_SERVER['SERVER_ADDR'] == "::1")
                ? $err
                : "Got an exception which has been logged.";
        }
    }
    return '';
}

function getBettor($tx)
{
    /*  Include here call to lnd to wait invoice */
    global $ranking_debug;
    $url = "https://blockchain.info/rawtx/$tx";
    if($ranking_debug)
    {
        $inAddr = substr(preg_replace('/[^a-zA-Z0-9]/','',$tx),0,10)."Input";
    }
    else
    {
        $info = doGet($url);
        $inAddr = $info->inputs[0]->prev_out->addr;
    }
    return checkBet($tx, '', $inAddr);
}

function doGet($url)
{
    $opts = array ('http' => array (
                        'method'  => 'GET',
                        ));
    $context  = stream_context_create($opts);
    if ($fp = fopen($url, 'r', false, $context)) {
        $response = '';
        while($row = fgets($fp))
        {
            $response .= $row;
        }
        fclose($fp);
    }
    return json_decode($response);
}

// checkBet returns "*OK*" when block chain can stop notifying us.
// ---------------------------------------------------------------
function checkBet($tx, $amt, $inaddr = '')
{
//    print_r(debug_backtrace());
//    echo "Checking Tx $tx, in which $inaddr paid $amt<br/>";
    $found = findTx($tx);
    if($found['tx_id'] != $tx)
    {
        if($inaddr)
        {
            return "The search for $tx gave <".$found['tx_id']."> $inaddr.";
        }
        $dAmt = ((float)$amt)/100000000;

        // storeTx will check if there is a slot.
        // --------------------------------------
        if(!storeTx($dAmt,$tx))
        {
            // Yay! Free bitcoin!  If they complain, we'll send it back.
            // ---------------------------------------------------------
            $ret = 3;
        }
        else
        {
            // Not *OK* yet, but we stored the Tx...
            // -------------------------------------
            $ret = 2;
        }
    }
    else
    {
        if($found['btc_addr'] > '')
        {
            // Tx already processed for Inspire ID
            // -----------------------------------
            $ret = 1;
        }
        elseif($inaddr)
        {
            if(!storeBet($tx, $inaddr))
            {
                // Something went wrong.
                // ---------------------
                $ret = "storeBet($tx, $inaddr) failed.";
            }
            else
            {
                // Bet is stored.
                // --------------
                $ret = 1;
            }
        }
        else
        {
            // Another block went by, but we still
            // haven't seen the transaction in do_cron!
            // ----------------------------------------
            $ret = 2;
        }
    }
    return $ret;
}

function bwb_test()
{
    global $path,$siteURL;
    array_shift($path);
    $fn = "bwb_".$path[0];
    $tests = $siteURL."js/unit_tests.js.php";
    return "<script type='text/javascript' src='$tests'></script>
    <div style='float:right' id='testlinks'></div>
        <span id='debug'></span><br/>
        <span id='problems' style='color:red; font-weight:bold;height:200px'></span>";
}

function bwb_faq()
{
    ob_start();
    include('faq.php');
    $ret = ob_get_contents();
    ob_end_clean();
    return $ret;
}

function bwb_contact()
{
    ob_start();
    include('contact.php');
    $ret = ob_get_contents();
    ob_end_clean();
    return $ret;
}

function bwb_logout()
{
    ob_start();
    session_unset();
    ob_flush();
    session_destroy();
    ob_clean();
    session_write_close();
    setcookie(session_name(),'',0,'/');
    session_regenerate_id(true);
    return "You've been logged out.".bwb_prompts();
}

function bwb_vote()
{
    global $path;
    $code = $path[1];

    if(isset($_SESSION['id']) && $pid = $_SESSION['id']) // Intentional assignment
    {
        $ret = "<div class='notice'>
            You're already logged in as ".$_SESSION['email']
            ."<br/>".listPlayerContests($pid);
    }
    else
    {
        if($player = getPlayer($_GET['p']))
        {
        if( !preg_match("/Player_[0-9]+@memeracing\.net/",$player['email']) )
        {
            $player = false;
        }
    }
    elseif($code > '')
    {
        $player = decode($code);
    }

        if($otl = oneTimeLogin($player))
    {
        $pid = $player['player_id'];
        $_SESSION['id'] = $pid;
        $_SESSION['email'] = $player['email'];
            $ret = $otl.listPlayerContests($pid);
        }
        else
        {
            $ret = bwb_prompts();
        }
    }
        return $ret;
}

function oneTimeLogin($player)
{
    global $siteURL, $domain;
    $ret = false;
    if($player)
    {
        extract($player);

        // "One time use" means we have to replace it now that it's used.
        // --------------------------------------------------------------
        $secret = substr(md5(time().$player_id),2,16);
        storeSecret($player_id,$secret);

        $ret = "Here is your new link to
            <a href='{$domain}{$siteURL}vote/$secret'>meme racing</a>";
    }
    return $ret;
}

function listPlayerContests($pid)
{
    global $db,$siteURL;
    if($contests = getPlayerContests($pid))
    {
        if(count($contests) == 1)
        {
            global $path;
            extract($contests[0]);
            $path = array('vote_on',$contest_id);
            $ret = bwb_vote_on();
        }
        else
        {
            $ret = "<ul>";
            foreach($contests as $c)
            {
                extract($c);
                $inspiration = htmlspecialchars($inspiration);
                $ret .= "<li><a href='{$siteURL}vote_on/$contest_id'>$inspiration</a></li>\n";
            }
            $ret .= "</ul>";
        }
    }
    else
    {
        return "<div class='notice'>There are no contests in which you have a vote at this time.</div>";
    }
    return $ret;
}

function myRandomOrder($pid)
{
    if($_SESSION['id'] == $pid)
    {
        $e = $_SESSION['email'];
    }
    else
    {
        $p = getPlayer($pid);
        $e = $p['email'];
    }
    return hexdec(substr(md5($e),0,4)) % 5040;
}

function bwb_vote_on()
{
    global $path;
    global $siteURL;
    $cid = $path[1];
    $pid = $_SESSION['id'];
    $myOrdering = orderFromOrdinal(getOrder($cid,$pid));
    $contest = getContest($cid);
    if(time() > strtotime($contest['deadline']))
    {
        $ret = "<div class='notice'>This contest has already been decided.
            Your vote on it is available from <a href='{$siteURL}contest/$cid'>
            the contest page</a></div>";
    }
    else
    {
        $entries = getContestEntries($cid);

        $newOrder = isset($path[2]) ? $path[2] : '';
        if(preg_match("/^([1-7]){7}$/",$newOrder))
        {
            $ret = "Your new ordering has been saved, and we emailed it to you in case you want to
                change it again later.";
            storeOrder($pid, $cid, ordinalFromOrder($newOrder));
            $myOrdering = $newOrder;
            voteEmail($myOrdering, $entries, $_SESSION['email'], $pid);
            $ret .= checkExpedite($cid, $entries);
        }

        $ret .= '<br/><span class="voting-instruction">You can click on the entries below to drag
            them around to a new order:</span>
            <script type="text/javascript" src="'.$siteURL.'js/jquery.dragsort-0.5.1.min.js"></script>'
            .showOrder($myOrdering, $entries). <<< ORDERJS
            <script type='text/javascript'>
                function enableStore()
                {
                    $("#storeOrder").css({'color':'black','cursor':'pointer'});
                    $("#storeOrder").attr('enabled','1');
                }
                $(function()
                {
                    $("#storeOrder").click(function()
                    {
                        if($(this).attr('enabled') == 1)
                        {
                            var newOrder = $("#myOrder li").map(function(){return this.id;}).get().join('');
                            location.href="{$siteURL}vote_on/$cid/"+newOrder;
                        }
                    });
                    $("#myOrder").dragsort({dragEnd: enableStore});
                });
            </script>
            <span id='storeOrder' style='color:lightgray;' enabled='0'>Save My New List</span>
ORDERJS;
    }
    return $ret;
}

function checkExpedite($cid, $entries)
{
    // If none of the players' orderings match their original,
    // then we allow only 15 minutes more, and warn everyone.
    // -------------------------------------------------------
    $orders = getOrders($cid);
    $pids = array();
    foreach($orders as $order)
    {
        if($order['ordord'] == myRandomOrder($order['player_id']))
        {
            return "Player ".$order['player_id']." hasn't voted yet.";
        }
        $pids[] = $order['player_id'];
    }
    $deadline = expedite($cid, '15 minute');
    $emails = getEmails($pids);
    $prompt = getInspiration($cid);
    foreach($emails as $pid => $email)
    {
        $msg = "The meme race inspired by &ldquo;$prompt&rdquo;
            has been voted on by all entrants, which means we can end it early!<br/><br/>
            If you'd like to change your list, shown below, you have until $deadline.";
        $msg .= listForPlayer(orderFromOrdinal(getOrder($cid,$pid)), $entries, $pid);
        sendPlayerMail($email,"Expedited Meme Race",$msg);
    }
    return "<div class='notice'>Everyone has voted now, so y'all have until
    $deadline to change your votes.</div>";
}

function sendBTCTo($dAmt, $destAddr, $cid = 'null')
{
    logPay($dAmt, $destAddr, $cid);
    return true;
}

// Use this as the callback to collect Transactions
// that need to be processed by do_cron.
// Test with: localhost/memeracing/received?value=100002&transaction_hash=ccd04ee4382a1d16db095fdfc9f425a0b3eafb09757bcf7bb6dcc2e488b2e368&input_address=abc123&confirmations=3&secret=notso
// -------------------------------------------------
function bwb_received()
{
    $value = '';
    $transaction_hash = '';
    $input_address = '';
    $confirmations = '';
    $d7fd9b3 = '';
    $secret = '';
    extract($_GET,EXTR_IF_EXISTS);
    if(($secret == '2e5389693117e2634' || $d7fd9b3 == 'eafb09757bcf') && ((int)$value) > 9999)
    {
        $ret = checkBet($transaction_hash, $value);
        if($ret == 2)
        {
            $ret = getBettor($transaction_hash);
        }
        if($ret == 1)
        {
            die("*OK*");
        }
        ipnLog($ret);
        die("What if we don't return the ok?");
    }
    elseif($secret == '2e5389693l17e2634')
    {
        global $db;
        $cid = $value - 1000000;
        $db->sql_query("UPDATE contest SET deadline=NOW() - INTERVAL 1 minute
            WHERE contest_id=$cid");
        die("*OK*");
    }
    else
    {
        ipnLog("Attempted, but no secret or $value is too low.");
    }
}

function bwb_reset()
{
    global $siteURL, $db;
    if($siteURL != "/" || $_GET['pre']=='prod')
    {
        $db->sql_query("DROP TABLES IF EXISTS entry, inspire,
            contest_entry,  player_order, backing, player,
            pay_log, ipn_log, slot_log, contest, config, err_log");
    }
    echo "<script>location.href='$siteURL';</script>";
}

function bwb_prompts($tag = true)
{
    global $contests, $siteURL;
    $contests = getInspires();

    $ret = notice(isPost('propose')).($tag ? '<main/>' : '');
    if($contests)
    {
        $ret .= showInspireTable($contests);
    }
    else
    {
        $ret .= "No contests are open at this time.";
    }

    $ret .= <<< PROPOSE
<br/><br/>
<div class='prop'><a name='new-prompt'></a>
    <form class='regPrompt text-center' method='post' onsubmit='return validate_proposal(this);'>
        <input type='hidden' name='edit'/>
        <textarea name='prompt' style='width:350px;' class='text-center'/>Enter your prompt here</textarea><br/>
        <input type='submit' value='Register My Prompt' class='btn btn-primary'
        <input type='submit' value='Register My Prompt' class='btn btn-primary'
            title='After submitting, you can click your\nunderlined prompt to make edits.

If anyone (even you!) backs your prompt or adds an answer,\nit will stick around a lot longer.'/>
    </form><br/><br/>
    <form class='form-inline' method='post'>
        <input type='submit' value='Send voting instructions' class='btn btn-inverse'/> to
        <input type='email' maxlength='50' name='email'
            value='Enter your email address' size='50'/>
    </form>
</div>
PROPOSE;
    return $ret;
}

function showInspireTable($contests)
{
    global $siteURL;
    $ret = '';
    foreach($contests as $c)
    {
        extract($c);
        $inspiration = htmlspecialchars($inspiration);
        $c_pl = $numc == 1 ? "contest" : "contests";
        $e_pl = $nume == 1 ? "entry" : "entries";
        $invested = $invested / $numc; // Correcting for Query in getInspires
        if(isset($_SESSION['myPrompts'])
            && @in_array($inspire_id,$_SESSION['myPrompts'])
            && 0 == $numc && 0 == $nume)
        {
            $inspiration = "<span id='p$inspire_id' class='editme'>$inspiration</span>
                <a href='{$siteURL}proposal/$inspire_id'
            title='Valued at $invested by $investors people.'>Visit your prompt.</a>";
        }
        else
        {
            $inspiration = "<a href='{$siteURL}proposal/$inspire_id'
            title='Valued at $invested by $investors people.'>$inspiration</a>";
        }
        $ret .= "<tr><td>$inspiration</td>
            <td>$numc $c_pl with $nume $e_pl</td></tr>\n";
    }
    $ret = "<table class='contest list'>
        <tr><th>Prompt</th><th>Details</th></tr>$ret</table>";

    return $ret;
}

function isPost($fn)
{
    $ret = count($_POST) > 0 ? call_user_func("bwb_$fn") : "";
    return $ret;
}

function bwb_propose()
{
    global $contests,$siteURL;
    if($_POST['prompt'] > '')
    {
        $prompt = $_POST['prompt'];

        // Do we have this prompt yet?
        // ---------------------------
        $had = getByHash('inspire',$prompt);
        if(count($had) > 0)
        {
            foreach($had as $contest)
            {
                if(normalize($contest['inspiration']) == normalize($prompt))
                {
                    extract($contest);
                    $inspiration = htmlspecialchars($inspiration);

                    return "Your prompt is too similar to
                        <a href='{$siteURL}proposal/$inspire_id'>$inspiration</a>.";
                }
            }
        }
        if( $newp = storePrompt($prompt) )
        {
            $ret = "Your prompt has been stored.";
            $contests = getInspires();
            if(!isset($_SESSION["myPrompts"]))
            {
                $_SESSION['myPrompts'] = array();
            }
            if($_POST['edit'] == '' && !in_array($newp,$_SESSION['myPrompts']))
            {
                $_SESSION["myPrompts"][] = $newp;
            }
        }
        else
        {
            $ret = "An error occurred while saving your prompt.";
        }
    }
    elseif($_POST['email'] > '')
    {
        $inEmail = dbQuote($_POST['email']);
        if( $player = getPlayerByEmail($inEmail) )
        {
            extract($player);
            $mail = oneTimeLogin($player);
            sendPlayerMail($email,"Meme Racing Access",$mail);
        }
        $ret = "<br/>If $inEmail was in our database, we sent a new login to it.";
    }
    return $ret;
}

function sendPlayerMail($to, $subj, $msg)
{
    global $notMailed;
    preg_match('/^(?P<name>[^@]+)@/',$to,$n);
    $name = $n['name'];
    $body = "<h3>Hi, $name</h3>$msg<br/><br/>
             - The Meme Racing Team";
    if( substr($_SERVER['SERVER_ADDR'],0,6) == '127.0.'
        || $_SERVER['SERVER_ADDR'] == "::1"
        || preg_match("/^Player_.*memeracing.net$/",$to) )
    {
        $notMailed .= "<div class='notice'>
            Not emailing '$subj' email to $to:<br/>$body<hr/>
            </div>";
        errLog(dbQuote("Skipped mailing $msg to $to."));
    }
    else
    {
        $mailHeaders = "From: admin@".$_SERVER['HTTP_HOST']."\r\n"
            .'Content-type: text/html; charset=iso-8859-1' . "\r\n";

        mail($to, $subj, $body, $mailHeaders);
    }
}

function bwb_proposal()
{
    global $path, $entries, $inspire_id;
    $inspire_id=$path[1];

    $entries = getEntries($inspire_id);
    $ret = isPost('respond');

    $ret .= start_timer( $inspire_id,0,'back this prompt' );

    $inspire = getInspire($inspire_id);
    extract($inspire);

    $inspiration = htmlspecialchars($inspiration);
    $ret .= "<h2>Answers to &ldquo;$inspiration&rdquo;</h2>";
    if($entries)
    {
        $ret .= showEntryTable($entries);
    }
    else
    {
        $ret .= "There are no answers yet.";
    }

    $frmEmail = isset($_SESSION['email']) ? $_SESSION['email'] : 'Enter your email address';
    $ret .= <<< PROPOSE
<br/><br/>
<h2>Propose your own answer</h2>
<div class='inx'>
    <div>
    Got an answer better than all those above?  Enter it here.  It will then show up in the
    list above so you can reserve it and then back it.  You will receive some bitcoin if
    anyone backs your answer.
    </div>
    <div style='float:right; width: 30%'>
    Your answer will appear in the list above with an underline.
    Click it to make changes before your session expires in about an hour.
    Note that if someone reserves it, you will no longer be able to edit it.
    </div>
</div>
<div class='prop'>
    <form method='post' onsubmit='return validate_answer(this);'>
        <input type='hidden' name='inspire_id' value='$inspire_id' />
        <input type='hidden' name='edit'/>
        <textarea name='answer' style='width:350px;'/>Enter your response here</textarea><br/>
        <input type='email' name='email' value='$frmEmail'/><br/>
        <input type='text' name='btc_addr' size='34' value='Bitcoin Address'/>
        <input type='submit' value='Register My Answer'/>
    </form>
</div>
PROPOSE;

    $ret .= showContests(getContests($inspire_id));
    return $ret;
}

function showEntryTable($entries)
{
    global $OUR_BTC_ADDR;
    $entry_rows = '';
    foreach($entries as $_entry)
    {
        extract($_entry);
        $entry = htmlspecialchars($entry);
        if(isset($_SESSION['myAnswers'])
            && @in_array($entry_id, $_SESSION['myAnswers'])
            && $backing == 0)
        {
            $entry = "<span id='a$entry_id' class='editme'>$entry</span>";
        }
        $entry_rows .= "<tr><td>$level</td>
            <td><input type='submit' name='r[$entry_id]' value='Reserve' /></td>
            <td title='backed by $backing'>$entry</td></tr>\n";
    }
    $ret = "<p class='inx'>
        If you would like to back one of these answers, click the reserve button.
        Instructions will appear at the top of the next page.  You will have
        15 minutes to send in the bitcoin.  If you send it after the 15 minutes, you may
        be backing a different answer.  You can always come back to this page to
        reserve this answer again, if it's still here.
    </p>
    <form method='post' id='reserver'><input type='hidden' name='inspire_id' value='$inspire_id' />
        <table class='contest list'>
            <tr><th>Level</th><th>Reserve</th><th>Entry</th></tr>
            $entry_rows
        </table>
    </form>";

    return $ret;
}

function bwb_contest()
{
    global $path;
    $cid = $path[1];
    // Display a completed contest
    // ---------------------------
    $nth = array (
        1 => "1<sup>st</sup>",
        2 => "2<sup>nd</sup>",
        3 => "3<sup>rd</sup>",
        4 => "4<sup>th</sup>",
        5 => "5<sup>th</sup>",
        6 => "6<sup>th</sup>",
        7 => "7<sup>th</sup>" );
    $ordies = array();
    $entriesByVoter = array();
    $entries = getContestEntries($cid);
    $cData = getContest($cid);
    extract($cData);
    $inspiration = htmlspecialchars($inspiration);
    $places = array(0,$first,$second,$third,$fourth,$fifth,$sixth,$seventh);
    $placesByEntry = array_flip($places);
    $n = 0;
    foreach($entries as $e)
    {
        $ordies[$e['entry_id']] = ++$n;
        $entriesByVoter[$e['player_id']][] = $e;
    }
    $sOrder = $ordies[$first].$ordies[$second].$ordies[$third]
        .$ordies[$fourth].$ordies[$fifth].$ordies[$sixth].$ordies[$seventh];
    $ret .= "\n<h3>Results</h3><h4>Contest $cid under <em>$inspiration</em></h4>"
        .showOrder($sOrder,$entries,'').'<br/>';

    $bets = getBettors($cid);
    $sBet = "";
    $totals = array();
    foreach($bets as $bet)
    {
        extract($bet);
        $firstBits = substr($btc_addr,0,5);
        $sBet .= "Address $firstBits... backed "
            .$nth[$placesByEntry[$entry_id]]." place with $bet BTC.<br/>";
        $totals[$placesByEntry[$entry_id]] += $bet;
        $totals[0] += $bet;
    }
    for($i = 1; $i < 8; ++$i)
    {
        $sBet .= "A total of ".$totals[$i]." backed ".$nth[$i]." place.<br/>";
    }
    $sBet .= "A grand total of ".$totals[0]." backed entries in this contest.<br/>\n";
    $ret .= "\n<h4>Backing</h4>$sBet<br/>";

    $payouts = getPayouts($cid);
    $sPay = "";
    $paidTtl = 0;
    foreach($payouts as $po)
    {
        extract($po);
        $firstBits = substr($destination,0,5);
        $sPay .= "Address $firstBits... received $amount BTC.<br/>";
        $paidTtl += $amount;
    }
    $sPay .= "A grand total of $paidTtl was paid out for this contest.<br/>\n";
    $ret .= "\n<h4>Payouts</h4>$sPay<br/>";

    $orders = getOrders($cid);
    $sOrders = "";
    //print_r($entriesByVoter);
    //print_r($orders);
    //print_r($places); die();
    foreach($orders as $o)
    {
        $player = $o['player_id'];
        $myEntries = $entriesByVoter[$player];
        $place = $placesByEntry[$myEntries[0]['entry_id']];
        $whoMult = count($myEntries) == 1 ? ''
            : "(who had ".count($myEntries)." entries in this contest) ";
        $sOrder = orderFromOrdinal($o['ordord']);
        $sOrder2 = "";
        for($i = 1; $i < 8; ++$i)
        {
            $placeth = $nth[$placesByEntry[$entries[$sOrder[$i-1]-1]['entry_id']]];
            $sOrder2 .= "$placeth ";
        }
        $sOrders .= $nth[$place]." place's creator $whoMult voted $sOrder2.<br/>";
    }
    $ret .= "\n<h4>Voting</h4>$sOrders<br/>";
    return $ret;
}

function showContests($contests)
{
    global $siteURL;
    if(count($contests) == 0)
    {
        return "";
    }
    $ret = "";
    $oHead = "";
    foreach($contests as $c)
    {
        extract($c);
        $head = $first > '' ? "Completed" : "Active";
        $head .= " Level $level Contests";
        if($oHead != $head)
        {
            $ret .= "\n<h3>$head</h3>";
            $oHead = $head;
        }
        $entries = getContestEntries($contest_id);
        if($first > '')
        {
            foreach($entries as $e)
            {
                if($e['entry_id'] == $first)
                {
                    $winner = $e['entry'];
                    break;
                }
            }
            $ret .= "Level $level contest won by
                <a href={$siteURL}contest/$contest_id><em>$winner</em><br/></a>";
        }
        else
        {
            $bets = getBetsByEntry($contest_id);
            $entry_rows = "";

            // Display contests with entrants still voting
            // -------------------------------------------
            foreach($entries as $idx => $_entry)
            {
                extract($_entry);
                $entry = htmlspecialchars($entry);
                $bet = $bets[$idx]['total'];
                $entry_rows .= "<li title='backed by $bet BTC'>$entry</li>\n";
            }
            $ret .= "<h4>Contest $contest_id ends at $deadline
                <span class='note' title='May end sooner if entrants all vote'>*</span></h4>
                <ul>$entry_rows</ul>";
        }
    }
    return $ret;
}

function bwb_search($isMain = false)
{
    global $siteURL;
    $val = isset($_POST['terms']) ? $_POST['terms'] : 'Search for something';
    $ret = "<form action='{$siteURL}search' method='get'>
            <input type='search' maxlength='20' name='terms' value='$val' size='20'/>
            <input type='submit' class='btn btn-primary' value='Find Memes'
                style='margin-top:-10px'/><br/>
            <input type='submit' title='Visit the form at the bottom of the Prompt page.'
                onclick='location.href=\"{$siteURL}prompts#new-prompt\";return false;'
                class='btn btn-primary' value='Add a Prompt'/>
        </form>";
    if(!$isMain)
    {
        $ret = "<center>$ret</center>";
    }
    if(isset($_GET['terms']) && ($terms = $_GET['terms']) > '')
    {
        $ret .= isPost('reserve');
        $ret .= searchQuery(dbQuote($terms));
    }
    return $ret;
}

function searchQuery($terms)
{
    $foundPrompts = getInspires("WHERE inspiration LIKE '%$terms%'");
    $foundAnswers = getEntries("WHERE entry LIKE '%$terms%'");
    $ret = '';
    if($foundPrompts)
    {
        $ret = '<h2>Prompts</h2>'.showInspireTable($foundPrompts);
    }
    if($foundAnswers)
    {
        $ret .= '<h2>Entries</h2>'.showEntryTable($foundAnswers);
    }
    if('' == $ret)
    {
        $ret = "We couldn't find any prompts or answers containing '$terms'";
    }
    return $ret;
}

function bwb_respond()
{
    global $entries, $inspire_id;

    if(($answer = (isset($_POST['answer']) ? $_POST['answer'] : '')) > '')
    {
        $email = $_POST['email'];
        $addr = $_POST['btc_addr'];

        // Do we have this answer yet?
        // ---------------------------
        $had = getByHash('entry',$answer);
        if(count($had) > 0)
        {
            foreach($had as $e)
            {
                if(normalize($e['entry']) == normalize($answer))
                {
                    extract($e);
                    $entry = htmlspecialchars($entry);
                    return notice("Your answer is too similar to '$entry'.");
                }
            }
        }
        if( $newa = storeAnswer($answer, $email, $inspire_id, $addr) )
        {
            if(is_numeric($newa))
            {
                $entries = getEntries($inspire_id);
                $ret = "Your answer has been stored.";
                if(!isset($_SESSION["myAnswers"]))
                {
                    $_SESSION['myAnswers'] = array();
                }
                if($_POST['edit'] == '' && !in_array($newa,$_SESSION['myAnswers']))
                {
                    $_SESSION["myAnswers"][] = $newa;
                }
                $_SESSION['email'] = $email;
            }
            else
            {
                $ret = notice("Apparently, your answer has already been reserved.");
            }
        }
        else
        {
            $ret = notice("An error occurred while saving your answer.");
        }
    }
    else
    {
        $ret = isPost('reserve');
    }
    return $ret;
}

function notice($str)
{
    return "<div class='notice'>$str</div>";
}

function bwb_reserve()
{
    global $siteURL;
    $ret = '';
    if(isset($_POST['r']) && is_array($_POST['r']))
    {
        $keys = array_keys($_POST['r']);
        $entry_to_reserve = $keys[0];
        $entry = getEntry($entry_to_reserve);
        $entry_text = htmlspecialchars($entry['entry']);
        $inspire_id = $entry['inspire_id'];
        $inspire = getInspire($inspire_id);
        $inspiration = "<a href='{$siteURL}proposal/$inspire_id'>"
            .htmlspecialchars($inspire['inspiration'])."</a>";
        $ret = start_timer($inspire_id, $entry_to_reserve,
                "back '$entry_text' as an answer to '$inspiration'", $entry_text);
    }
    return $ret;
}

function start_timer($iid, $eid, $goal, $answer = '')
{
    global $OUR_BTC_ADDR;
    $slot = find_slot($iid, $eid);
    $backed = ($eid > 0 ? "response" : "prompt");

    if($slot == '0000' || (substr($slot,0,2) == '00' && $eid > ''))
    {
        $ret =  "($slot)We are unable to reserve a slot for this $backed at this time.
            Please try again in a few minutes.";
    }

    $for = $eid > 0 ? str_replace("'","&apos;",strip_tags($answer)) : "this prompt";
//    $lnInvoice = `lncli -n testnet addinvoice --memo "$slot backing a Memeracing $backed"`;
    $text_ln = $slot." backing a Memeracing ".$backed;
    $lnInvoice = getPaymentRequest($text_ln,'0','1800');
    $ret = "<div id='bd$slot'><br/>To $goal, please use the following Lightning Invoice to pay 
        whatever amount you wish to.<br/><textarea readonly>$lnInvoice->payment_request</textarea>
    Time left: <span class='timer' mins='15'></span> Please reload if you can't pay before this timer runs out.<br/>
        Bitcoins are paid back to the address from which they came.<br/>
        <strong>Only use private wallets to back prompts and answers!</strong></div>";
    return $ret;
};

function find_slot($iid, $eid)
{
    $iSlot = get_inspire_slot($iid);

    return $eid == 0
    ? "00$iSlot"
    : get_entry_slot($iid, $eid).$iSlot;
}

function get_inspire_slot($iid)
{
    $slot = getSlot($iid);
    $hasSlot = substr('00'.$slot,-2);
    if( '00' == $hasSlot )
    {
        $hasSlot = substr('00'.( ($empty = newSlot($iid)) ? $empty : outbid() ), -2);
    }
    return $hasSlot;
}

function get_entry_slot($iid, $eid)
{
    $slot = getSlot($iid, $eid);
    $hasSlot = substr('00'.$slot,-2);
    if( '00' == $hasSlot )
    {
        $hasSlot = substr('00'.newSlot($iid, $eid), -2);
    }
    return $hasSlot;
}

function outbid()
{
    // Identify the oldest of the prompts that has the least backing.
    // --------------------------------------------------------------
    $slot = 0;
    $outbidMe = findLowBid($slot);

    if(0 == $outbidMe)
    {
        return 0;
    }

    // Return BTC to all addresses that backed entries in
    // as-yet-undecided contests
    // --------------------------------------------------
    returnBacking($outbidMe);

    // We keep the entries because the prompt may receive
    // a healthy amount of backing in the future.
    // --------------------------------------------------

    return $slot;
}

function returnBacking($iid)
{
    if($backers = getBackers($iid))
    {
        foreach($backers as $b)
        {
            extract($b);
            echo "TODO: send $amount BTC to $btc_addr<br/>\n";
        }
    }
}
?>
