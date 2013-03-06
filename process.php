<?php
global $facts;
$facts = array(
    1 => 1,
    2 => 2,
    3 => 6,
    4 => 24,
    5 => 120,
    6 => 720);

function endContest($cid)
{
    $orders = getOrders($cid);

    $entries = getContestEntries($cid);
    $wtol = race($orders, $cid, $entries);
    recordRace($wtol, $cid);
    payRace($wtol, $cid, $entries);
}

function payRace($wtol, $cid, $entries)
{
    $bets = $wtol;
    $data = getBettors($cid);
//print_r($data);

    // First place gets back what he bet,
    // and index $bettors by btc address.
    // ----------------------------------
    foreach($data as $idx => $bettor)
    {
        if($bettor['entry_id'] == $wtol[0])
        {
            $bettor['earned'] += $bettor['bet'];
        }
        $bettors[$bettor['btc_addr']] = $bettor;
    }

    // Bets on losers get divided proportionally
    // -----------------------------------------
    while(count($bets) > 1)
    {
        $loser = array_pop($bets);
        $winners = implode(',', $bets);
        $backing = getTotalBet($winners, $cid);
        $winnings = getTotalBet($loser, $cid);
//echo "$winners beat $loser, earning $winnings\n";
        foreach($bettors as $idx => $bettor)
        {
            extract($bettor);
            if(in_array($entry_id,$bets))
            {
                $piece = $bet / $backing;
                $won = ($piece * $winnings);
                $bettors[$idx]['earned'] += $won;
// echo "$btc_addr gets $won, $piece of it.\n";
            }
        }
    }

    // Reduce everyone by percentage
    // -----------------------------
    $OUR_PCT = .1;
    $te = 0;
    $tb = 0;
    foreach($bettors as $idx=>$bettor)
    {
        $bettors[$idx]['earned'] = (1 - $OUR_PCT) * $bettor['earned'];
        $te += $bettors[$idx]['earned'];
        $tb += $bettor['bet'];
    }

    // Give half to investors
    // ----------------------
    $dividend = ($tb - $te) / 2;
    $backing = 0;
    if($investors = getBackers($cid))
    {
        foreach($investors as $investor)
        {
            $backing += $investor['amount'];
        }
//echo "Investors paid in $backing to earn $dividend...\n";
        foreach($investors as $investor)
        {
            $invested = $investor['amount'];
            $piece =  $invested / $backing;
            $divShare = ($piece * $dividend);
//echo "Investor ".$investor['btc_addr']." invested $invested and earned $divShare, or $piece of it\n";
            $bettors[$investor['btc_addr']]['earned'] += $divShare;
        }
// print_r(array($bettors));
    }

    // Divide the rest evenly between entrants
    // ---------------------------------------
    $entrants = array();
    foreach($entries as $e)
    {
        if($e['btc_addr'] > "")
        {
            $entrants[] = $e['btc_addr'];
        }
    }
    $dividend /= count($entrants);
    foreach($entrants as $e)
    {
        $bettors[$e]['earned'] += $dividend;
    }

    foreach($bettors as $key=>$bettor)
    {
        extract($bettor);
        if($earned > 0)
        {
            sendBTCTo($earned,$key, $cid);
        }
    }
}

function race($orders, $cid, $entries)
{
    // Turn those orders into strings
    // ------------------------------
    $votes = array();
    foreach($orders as $o)
    {
        $votes[] = orderFromOrdinal($o['ordord']);
    }
    $final = array(0,0,0,0,0,0,0);
    // Add tenths to break any ties
    // ----------------------------
    $tenths = 0.8;
    foreach(array_keys($entries) as $idx)
    {
        $final[$idx] =+ $tenths;
        $tenths -= 0.1;
    }
    $half = count($votes) / 2;
    for($p1 = 1; $p1 < 7; ++$p1)
    {
        for($p2 = $p1+1; $p2 < 8; ++$p2)
        {
            // Who wins this pair?
            // -------------------
            $count = 0;
            foreach($votes as $vote)
            {
                $pos1 = strpos($vote, ''.$p1);
                $pos2 = strpos($vote, ''.$p2);
                if($pos1 < $pos2)
                {
                    $count++;
                }
            }
// echo "When p1 is $p1 and p2 is $p2 count ends at $count.\n";
            if($count > $half)
            {
                $final[$p1-1]++;
            }
            elseif($count < $half)
            {
                $final[$p2-1]++;
            }
        }
    }

    arsort($final);
    $wtol = array();
    foreach(array_keys($final) as $idx)
    {
        $wtol[] = $entries[$idx]['entry_id'];
    }
// die(print_r(array($votes, $final, $wtol, $entries),true));
    return $wtol;
}

function sendEntrantEmail($entry, $entries, $contest_id)
{
    // Calculate the predictably random order
    // --------------------------------------
    $myOrdering = orderFromOrdinal(getMyOrder($entry, $contest_id));
    return voteEmail($myOrdering, $entries, $entry['email'], $entry['player_id']);
}

function voteEmail($myOrdering, $entries, $email, $pid)
{
    // Build the list of entries from it
    // ---------------------------------
    $myList = listForPlayer($myOrdering, $entries, $pid);
    sendPlayerMail($email,"Meme Racing Needs Your Judgment",
        "You've got a meme in a contest that needs your vote:<br/>
        $myList");
}

function getMyOrder($entry,$cid)
{
    extract($entry);
    $myOrder = getOrder($cid,$player_id);
    if($myOrder === false)
    {
        $myOrder = myRandomOrder($player_id);
        storeOrder($player_id, $cid, $myOrder);
    }
    return $myOrder;
}

function showOrder($sOrder, $entries, $id = 'myOrder')
{
    $list = "<ol id='$id'>";
    for($i = 0; $i < 7; ++$i)
    {
        $firstBits = $id == 'result'
            ? '('.substr($entries[substr($sOrder,$i,1)-1]['btc_addr'],0,5).')'
            : "";
        $eid = substr($sOrder,$i,1);
        $list .= "<li id='$eid'>".$entries[substr($sOrder,$i,1)-1]['entry']."$firstBits</li>\n";
    }
    $list .= "</ol>";
    return $list;
}

function listForPLayer($sOrder, $entries, $pid)
{
    global $siteURL;

    $list = showOrder($sOrder, $entries, '');

    $secret = getSecret($pid);
    if($secret == '')
    {
        $secret = substr(md5(time().$pid),2,18);
        storeSecret($pid,$secret);
    }

    $list .= "You may change this order by visiting
        <a href='{$siteURL}vote/$secret'>memeracing.net</a>, but this link can only be
            used once.  When it does get used, we'll send another link.  If you get
            this email without having visited the site recently, double check that
            the order shown above is correct.";
    return $list;
}

function ordinalFromOrder($sOrder)
{
    global $facts;
    $available = '1234567';
    $ord = 0;
    $pos = 7;
    $idx = -1;
    while($available > '')
    {
        $id = substr($sOrder,++$idx,1);
        $ord += ($facts[--$pos]) * strpos($available, $id);
        $available = str_replace($id, '', $available);
    }
    return $ord;
}

function orderFromOrdinal($ordinal)
{
    global $facts;
    // If you list all the possible orders of the numbers 1 - 7 and then sort it,
    // you will have 7! (5040) entries.  The first 6! (720) of them will start
    // with 1, and the next 720 will start with 2, on down to the last 720, which
    // will all start with 7.  We find our way into this list by counting the
    // number of 720s we can remove to get first place.  Whatever is left can
    // be analyzed the same way using the numbers 1 - 6.
    // --------------------------------------------------------------------------
    $remaining = $ordinal;
    $available = "1234567";
    $places = '';
    for($position=6; $position > 0; $position -= 1)
    {
        $occupier = floor($remaining / $facts[$position]);
        $remaining -= $occupier * $facts[$position];
        $place = $available[$occupier];
        $places .= $place;
        $available = str_replace($place,'',$available);
    }
    $places .= $available;
    return $places;
}
/*
    $o = $_GET['o'];
    $order = orderFromOrdinal($o);
    $oInv = ordinalFromOrder($order);
    echo "The order that $o gives is [$order], which gives $oInv as the order.";
*/
?>
