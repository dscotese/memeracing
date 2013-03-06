<?
$home = 1;
session_start();
include('queries.php');
include('controller.php');
$path = explode("/",$_SERVER['REQUEST_URI']);
$notMailed = "";
if($path[0] == '')
{
    array_shift($path);
}
$fn = 'bwb_'.preg_replace("/\?.*/",'',$path[$fnElemNum]);
if(function_exists($fn))
{
    while($fnElemNum-- > 0)
    {
        array_shift($path);
    }
    try
    {
        $content = call_user_func($fn);
    }
    catch( Exception $e )
    {
        $err = print_r($e,true);
        errLog($err);
        if(preg_match('/^127\.0\./', $_SERVER['SERVER_ADDR']) )
        {
            $content = $err;
        }
    }
}
else
{
    $content = bwb_main();
}
if($_SESSION['id'] > '')
{
    preg_match('/^(?P<name>[^@]+)@/',$_SESSION['email'],$n);
    $name = $n['name'];
    $logout = "<li><a href='{$siteURL}logout'>$name Log out</a></li>";
}
include('header.php');
?>
      <?php if(preg_match("'<main/>'",$content)) { ?>
      <div class="jumbotron">
        <h1>What is a Meme Race?</h1>
        <p class="lead"><strong>Meme Race</strong> [mim reɪs] <em>noun</em></p>
        <p class="lead">1) An initial inspirational prompt and all its recorded responses, which are then randomly batched into sevens, giving the response creators an opportunity to order them from best to worst. The results are processed to identify the best of those seven.</p>
      </div>
      <?php } ?>

      <hr>

        <div id="content">
<?php if( substr($_SERVER['SERVER_ADDR'],0,6) == '127.0.' ) {
    $randTx = md5(time()).'b3eafb09757bcf7bb6dcc2e488b2e368';
?>
            <form action='<?=$siteURL?>received' target='BCT' onsubmit='location.href="";'>
                <input name='value' value='1001002'/>
                <input type='hidden' name='transaction_hash' value='<?=$randTx?>' />
                <input type='hidden' name='input_address' value='1CyuAfo4r6KzspipMoXkN8ReaKf797QrPW'/>
                <input type='hidden' name='confirmations' value='0'/>
                <input type='hidden' name='secret' value='2e5389693117e2634'/>
                <input type='submit' value='Test Bet' class='btn' />
            </form><br/>
            <a href='<?=$siteURL?>reset'>Reset</a> |
<?php } ?>
            <?php if($fn != 'bwb_search') echo bwb_search(); ?>

            <?php echo $content; ?>

        </div><!--end content-->

<?php include('footer.php'); ?>