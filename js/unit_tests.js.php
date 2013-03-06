<?php
    include("../queries.php");

    if(isset($_GET['p']))
    {
        die(getSecrets($_GET['p']));
    }
?>
var unittest;
var links;
var limits = location.href.split('/test/')[1].split('/');
var step = 1 * limits[0];
var last = 1 * limits[1];
var retries = 0;
var cnttr = 0;
step = isNaN(step) ? 0 : step;
var state = 0;
last = 0 == last ? 1000000 : last;
var p1secret = '<?=getSecrets(1)?>';

$(function()
{
    if(window.name != "unittest")
    {
        unittest = window.open("<?=$siteURL?>","unittest");
    }

    cases = setupNext.toString().match(/case [0-9]+:.*/g);
    cases = cases.concat(checkStep.toString().match(/case [0-9]+:.*/g));
    cases.sort(function(a,b)
    {
        na = (1*(a.substring(5,a.indexOf(':'))));
        nb = (1*(b.substring(5,b.indexOf(':'))));
        return na < nb ? -1 : 1;
    });
    links = new Array();
    cases.forEach(function(v,i,c)
    {
        var ap = links[v.match(/[0-9]+/)] ? links[v.match(/[0-9]+/)]+"<br/>" : "";
        links[v.match(/[0-9]+/)] = ap+v;
    });
});

function debug(str)
{
    $("#debug").html(str);
}

function report(str)
{
    $("#problems").append("("+step+")"+str + "<br/>");
}

function proceed()
{
    if(++cnttr > 100)
    {
        alert("Stopping because cnttr is at 100");
        return;
    }

    step += 1;
    if(location.href.match(step-1) || checkStep())
    {
        $("div#testlinks").html(links.slice(step-2,step+2).join('<br/>'));
        if(step >= (1*last)+1)
        {
            var single = location.href.replace(last,(1*last)+1).replace(/test\/[0-9]+/,"test/"+last);
            var finish = location.href.replace(last,(1*last)+1000000).replace(/test\/[0-9]+/,"test/"+last);
            report("Stopped on step " + step +
                "<a href='"+single+"'>Single Step</a> | " +
                "<a href='"+finish+"'>Run the rest</a>");
            return;
        }
        retries = 0;
        while(!setupNext())
        {
            if(++retries > 100)
            {
                report("Tried setting up step "+(1+step)+" "+retries+" times.  Quitting.");
                return false;
            }
        }
    }
}

function checkStep()
{
    debug("Checking step "+step);
    switch(step)
    {
    case 2: // Ensure that the inspire showed up.
        var found = false;
        unittest.$("a").each(function(i,v)
        {
            found = found || ('Wanna try' == (v.innerHTML.match(/Wanna try/)));
        });
        if(!found)
        {
            report("Wanna try didn't get added.");
            return false;
        }
        break;
    case 3: // Check for "too similar" message.
        if(!unittest.$("div.notice").text().match(/Wanna try/))
        {
            report("Adding prompt 0 a second time didn't give a warning.");
            return false;
        }
        break;
    case 4: // Verify second prompt
        unittest.$("a").each(function(i,v)
        {
            found = found || ('dog food' == (v.innerHTML.match(/dog food/)));
        });
        if(!found)
        {
            report("Second prompt was not added correctly.");
            return false;
        }
        break;
    case 15: // Verify we have 10 answers we can reserve.
        if(unittest.$("input[value='Reserve']").length < 10)
        {
            report("I don't see 10 Reserve buttons.");
            return false;
        }
        break;
    case 16: // Make sure we got a slot
        if(!unittest.$("div.notice").text().match(/as an answer to/))
        {
            report("Reservation of answer(1) not indicated");
            return false;
        }
        break;
    case 36: // Check for "Active Contests"
        if(!unittest.$("h3").text().match(/Active Level 1 Contests/))
        {
            report("Level 1 Contest not found.");
            step = 35;
            unittest = window.open("<?=$siteURL?>proposal/1","unittest");
            return false;
        }
        break;
    case 37: // Vote as player 0
        return checkVoting(1);
        break;
    case 39: // Vote as player 1
        return checkVoting(2);
        break;
    case 41: // Vote as player 2
        return checkVoting(3);
        break;
    case 49: // There should be no "new link" for a nonexistant player.
        if(unittest.$("body").text().match(/your new link/))
        {
            report("vote/?p=8 gave a login link !??.");
            return false;
        }
        break;
    case 50: // Check that Player 3 has a link but lists no contests.
        if(!unittest.$("body").text().match(/your new link/))
        {
            report("No login link for Player_3.");
            return false;
        }
        if(unittest.$("ol li").length > 0)
        {
            report("Player_3 should have no contests.");
        }
        break;
    case 54: // Make sure we still don't see the 'Everyone has voted now' message.
        if(unittest.$("#notice").text().match(/Everyone has voted/))
        {
            report("Expediting happened early");
            return false;
        }
        break;
    case 56: // Check for closed contest.
        if(unittest.$(".notice").text().match(/no contests in which you/))
        {
            state = 1;
        }
        break;
    case 57: // Now we should see an expedited contest / No voting on closed contets.
        break;
        if(state > 0)
        {
            if(!unittest.$(".notice").text().match(/already been decided/))
            {
                report("Player_6 may have voted after contest was closed!");
                return false;
            }
        }
        else if(!unittest.$(".notice").text().match(/Everyone has voted/))
        {
            report("Expediting failed.");
            return false;
        }
        break;
    case 58: // Setup has already set the deadline to a minute ago. Make sure we did payouts.
        if(!unittest.$("h4").text().match(/Payouts/))
        {
            report("No payouts found.");
            return false;
        }
        else
        {
            var p = unittest.$("body").text().match(/Address.*?received.*?BTC/g);
            var req = {206:0.11190507,216:0.23329600,226:0.02788295,236:0.01670681,246:0.00294575,
                256:0.06015739,"11O":0.00407184,"11G":0.00407184,"14T":0.00407184,
                "15D":0.00407184,"16P":0.00407184,"10W":0.00407184,"12F":0.00407184};
            var r = '';
            for(var i in p)
            {
                r = req[p[i].substr(8,3)];
                if(!p[i].match(r))
                {
                    report(p[i]+" appears to be wrong (not "+r+").");
                }
            }
        }
        if(state == 0)
        {
            state = 1;
            step = 54;
        }
        break;
    case 59:
        if(!unittest.$("form input[value*='voting instructions']"))
        {
            report("Doesn't look like we have a Voting Instructions form.");
            return false;
        }
        break;
    case 60:
        if(!unittest.$("a[href*='vote/']"))
        {
            report("Player_0's login link didn't show up.");
            return false;
        }
        break;
    case 61: // Verify Player_0 has no contests
        if(!unittest.$("div.notice").text().match(/no contests in which/))
        {
            report("I see no message for Player_0 about having no contests.");
            return false;
        }
        break;
    case 62:
        var frm = unittest.$(".prop form")[0];
        if(!frm || !frm.email)
        {
            report("No answer form in proposal 1");
            return false;
        }
        break;
    case 63: // Verify that entry "to live at the expense of" is still there.
        if(unittest.$("input[name^='r[2]']").length < 1)
        {
            report("Bastiat has disappeared, though it's slotted.");
            return false;
        }
        break;
    default: break;
    }
    return true;
}

function setupNext()
{
    debug("Setting up after step "+step);
    switch(step)
    {
    case 1: case 2: // Make the first prompt and test for rejection of duplicates.
        makeInspire(0); break;
    case 3: // Make the second prompt.
        makeInspire(1); break;
    case 4: // Visit the first prompt.
        unittest = window.open("<?=$siteURL?>proposal/1","unittest");
        break;
    case 5: case 6: case 7: case 8: case 9: case 10: // (to case14): Add answers
    case 17: case 18: case 19: case 20: case 21: // (to case 21): Add answers
    case 11: case 12: case 13: case 14:
        return addAnswer(0,step-5);
        break;
    case 15: // Verify we have 10 answers we can reserve.
        reserve(1,1,-1);
        break;
    case 16: // Make sure we got a slot
        return addAnswer(0,12);    // Next 5 steps with case 5, (0,12) makes two random answers.
        break;
    case 22: case 23: case 24: case 25: // (to case 33): Reserve everything
    case 26: case 27: case 28: case 29:
    case 30: case 31: case 32: case 33:
        reserve(1,step-22,-1);
        break;
    case 34: // We now have 12 reservations.  Submit bets to trigger a contest (.2 on 3rd place).
        var amt = 0;
        var pretx = "";
        for(var counter = 10; counter > 0; --counter)
        {
            amt = counter * 1000100 + 1;
            pretx = (15+counter);
            testBet(amt, pretx, counter);
        }
        // Bet again on what will end up as 3rd place.
        testBet(10001001,99,"Extra 3rd");
        // And have a coupld investors.
        testBet(1000001,"Inv1","Investor 1");
        testBet(10000001,"Inv2","Investor 2");
        // Now visit the prompt while it's processing.
        unittest = window.open("<?=$siteURL?>proposal/1","unittest");
        break;
    case 35: // Wait 2 seconds and then procced to the next step.
        window.setTimeout('proceed()',2000);
        break;
    case 36: // Check for "Active Contests" and login as player 0
        // Now vote for players 0, 1, and 2 to make player 5 win. (p=1 is player 0)
        return bePlayer(1);
        break;
    case 37: // Vote as player 0 putting #3 in first.
        unittest = window.open("<?=$siteURL?>vote_on/1/3175264","unittest");
        break;
    case 38: // Be player 1 (this uses an actual login secret)
        return bePlayer(2);
        break;
    case 39: // Vote as player 1 putting #3 in first.
        unittest = window.open("<?=$siteURL?>vote_on/1/3412675","unittest");
        break;
    case 40: // Be player 2
        return bePlayer(3);
        break;
    case 41: // Vote as player 2 putting #3 in first.
        unittest = window.open("<?=$siteURL?>vote_on/1/3542176","unittest");
        break;
    case 42: case 44: case 46: // Be players 4, 5, and 6 (p=5, 6, and 7)
        return bePlayer(step/2 - 16);
        break;
    case 43: case 45: case 47: // Make sure id 3 is in first place before being player 4
        noVote(1);
        break;
    case 48: // Verify that player 8 does not exist.
        return bePlayer(8);
        break;
    case 49: // There should be no "new link" if there is no player 8, then be player 3 again.
        return bePlayer(4);
        break;
    case 50: // Just logout
        logout(); break;
    case 51: case 53: case 55: // Be players 4, 5, and 6 (p=5, 6, and 7)
        return bePlayer((step-41)/2);
        break;
    case 52: case 54: case 56: // Vote #3 in second place.
        unittest = window.open("<?=$siteURL?>vote_on/1/7326451","unittest");
        break;
    case 57: // Set deadline to now (Hidden feature)
        $.get("<?=$siteURL?>received?value=1000001&transaction_hash="
            + "x64d98add54e324565bcb4e3b006d7fd9b3eafb09757bcf7bb6dcc2e488b2e36&"
            + "input_address=1CyuAfo4r6KzspipMoXkN8ReaKf797QrPW&confirmations=0&secret=2e5389693l17e2634",
            "",function(data, status)
            {
                if(!data.match(/\*OK\*/))
                {
                    report("Deadline not updated");
                    return false;
                }
                if( cronWait("<?=$siteURL?>proposal/1",function()
                    {
                        if(!unittest.$("h3").text().match(/Completed/))
                        {
                            report("No H3 with 'Completed' in it.");
                            return false;
                        }
                        else
                        {
                            report("Ok - saw the H3");
                            return true;
                        }
                    },'Expedited Contest 1'))
                {
                    unittest.location=unittest.$("a[href*='contest']").attr('href');
                };
            });
            return true;
        break;
    case 58: // Go home to use Voting Instructions box.
        unittest.location="<?=$siteURL?>logout";
        break;
    case 59: // Get voting instructions for player_0
        var frm = unittest.$("form input[value*='voting instructions']")[0].form;
        frm.email.value='Player_0@memeracing.net';
        frm.submit();
        break;
    case 60: // Click not-emailed link.
        unittest.location = unittest.$("a[href*='vote/']").attr('href');
        cnttr = 13;
        break;
    case 61:
        unittest.location = "<?=$siteURL?>proposal/1";
        break;
    case 62: // Add 83 more answers for a total of 99.
        if(cnttr < 98)
        {
            step = step-1;
            if(addAnswer(0,cnttr + 15))
            {
                debug("Adding at counter " + cnttr);
            }
        }
        else
        {
            addAnswer(0,cnttr + 15);
        }
        break;
    case 63: // Reserve a bunch of times.
        cnttr = 0;
        step = 64;
    case 64:
        if(cnttr < 98)
        {
            step = step-1;
            reserve(1, -1, cnttr);
            debug("Reserving "+cnttr);
        }
        else
        {
            reserve(1, -1, cnttr);
            debug("Reserving "+cnttr);
        }
        break;
    default: debug("End of tests");
    }
    return true;
}

function cronWait(url,test,name)
{
    if(!test())
    {
        step = step - 1;
        unittest = window.open(url,'unittest');
        report("Waiting for "+name+" again...");
        return false;
    }
    return true;
}

function bePlayer(n)
{
    // Can't be a player unless we're logged out.
    // ------------------------------------------
    if(unittest.$("li a[href*='logout']").length == 1)
    {
        step = step - 1;
        logout();
        return true;
    }
    if(n == 2) // Use secret
    {
        $.get("<?=$siteURL?>js/unit_tests.js.php?p=2",function(data,stat)
        {
            unittest = window.open("<?=$siteURL?>vote/"+data,"unittest");
        });
    }
    else
    {
        unittest = window.open("<?=$siteURL?>vote/?p="+n,"unittest");
    }
    return true;
}

function logout(page)
{
    unittest = window.open("<?=$siteURL?>logout","unittest");
}

function noVote(contest, page)
{
    var newOrder = unittest.$("#myOrder li").map(function(){return this.id;}).get().join('');
    if(newOrder == "")
    {
        report("No new order");
        return;
    }
    unittest = window.open("<?=$siteURL?>vote_on/"+contest+"/"+newOrder, "unittest");
}

function checkFirst(n,page)
{
    var first = unittest.$("ol li")[0].getAttribute('id');
    if(first != n)
    {
        report("Entry "+n+" is not in first place, "+first+" is.");
    }
    logout(page);
}

function checkVoting(p)
{
    if(!unittest.$("body").text().match(/your new link/))
    {
        report("No new link for Player "+p+"... Retrying...");
        step = step - 1;
        logout();
        return false;
    }
    // And that we have an orderable list
    if(unittest.$("ol#myOrder").length == 0)
    {
        report("No orderable list on Player "+p+"'s page.");
        alert("We didn't stop last time, so here's an alert.");
        return false;
    }
    return true;
}

function testBet(amt, pretx, name)
{
    var misc = "&input_address=1CyuAfo4r6KzspipMoXkN8ReaKf797QrPW&confirmations=0&secret=2e5389693117e2634";
    var txid = (pretx + '64d98add54e324565bcb4e3b006d7fd9b3eafb09757bcf7bb6dcc2e488b2e368').substr(0,64);
    var url = "<?=$siteURL?>received?value="+amt+"&transaction_hash="+txid+misc;
    $.get(url,function(c)
    {
        return function(isOK,stat)
        {
            if(isOK != "*OK*")
                report("Bet on "+c+" failed with "+isOK+".("+stat+").");
        };
    }(name));
}

function reserve(c,n,x)
{
    if(unittest.$("form#reserver").length == 0)
    {
        report("Going to prompt "+c+" to reserve answers...");
        unittest = window.open("<?=$siteURL?>proposal/"+c,"unittest");
        step = step - 1;
        return;
    }
    if( x < 0 )
    {
        unittest.$("form#reserver input[name^='r["+n+"]']").attr('type','hidden');
    }
    else
    {
        unittest.$("form#reserver input[name^='r']")[x].type='hidden';
    }
    unittest.$("form#reserver").trigger('submit');
}

function addEntry(e, a)
{
    var frm = unittest.$(".prop form")[0];
    if(!frm || !frm.email)
    {
        report("Going to prompt 1.");
        unittest = window.open("<?=$siteURL?>proposal/1","unittest");
        return false;
    }
    frm.email.value = e;
    frm.answer.value = a;
    frm.btc_addr.value = ('1' + e.match(/[0-9]+/)+a.replace(' ','').replace("'","")).substr(0,34);
    frm.submit();
    return true;
}

function addPrompt(p)
{
    var frm = unittest.$(".prop form")[0];
    frm.prompt.value = p;
    frm.submit();
}

function makeInspire(n)
{
    prompts = Array(
        'Can a beautiful idea fit in 140 characters? Twitter thought so. Wanna try?',
        'We like dog food:  How can memeracing.com be improved?',
        'Acronym: A word spelled from the first letters of its definition.  Invent a good one!',
        'Find a Youtube video that is funnier each time you watch it.',
        'Describe Bitcoin in a way that non-technical people will appreciate.');
    addPrompt(prompts[n]);
}

function addAnswer(contest,n)
{
    var answers = Array( Array (
        'Those who would give up essential liberty to purchase a little temporary safety deserve neither (Ben Franklin)',
        'Government is the great fiction through which everybody endeavors to live at the expense of everybody else. (Bastiat)',
        'If one takes care of the means, the end will take care of itself (voluntaryist.com)',
        'Litmocracy, Facebook\'s Likeness App, and Meme Racing all use subjective ordering to find people with similar likes.',
        'The first baby\'s first laugh broke into a thousand pieces and went skipping about, and that was the beginning of fairies.(JM Barrie)',
        'Divide something into an ever larger number of pieces, measure one, and then multiply by the number.  Calculus is the limit.',
        'Plato claimed that every concept or idea has an objective ideal. Perhaps his view has done more harm than good.',
        'We evolved to process logic subconsciously.  Have you ever found after "unreasonable behavior" that you had good reasons for it?',
        'One man\'s treasure... Every body is different.  You can know yourself better than anyone else can.  Explore diets and exercises.',
        'Find people you can trust by risking what you\'re ok losing.  This often leads to pleasant surprises.',
        '"Just doing my job" is one of the most destructive abdications of human conscience that ever existed. (Anonymous)' ),
    Array(
        'Instead of putting requirements on the amount, use different bitcoin addresses for different prompts.',
        'It needs colors and graphics',
        'The ways in which one can earn bitcoin should be made way more obvious.' ),
    Array(
        'The Authorities Robbing People',
        'Institutionalized Robbery Sycophants' )
    );

    var answer;
    if(n > answers[contest].length)
    {
        var words = answers.join().replace(/,/g,' ').split(' ');
        var numw = words.length;
        answer = "";
        for(var i = 0; i < 10; ++i)
        {
            r = Math.floor(Math.random() * numw);
            answer = answer + ' ' + words[r];
        }
    }
    else
    {
        answer = answers[contest][n];
    }
    // Players 0-6 each play 4 or 5 times. After that, every 5th player plays twice.
    var emn = n < 30 ? n%7 : (n%5 == 1 ? n-1 : n );
    return addEntry("Player_"+emn+"@memeracing.net", answer);
}
