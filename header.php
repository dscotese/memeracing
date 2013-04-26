<?php
function auto_version($file)
{
  if(strpos($file, '/') !== 0 || !file_exists($_SERVER['DOCUMENT_ROOT'] . $file))
    return $file;

  $mtime = filemtime($_SERVER['DOCUMENT_ROOT'] . $file);
  return preg_replace('{\\.([^./]+)$}', ".$mtime.\$1", $file);
}
?>
<!DOCTYPE HTML>
<html dir="ltr" lang="en-US">
<head>
    <meta charset="UTF-8">
    <link href="<?auto_version($siteURL."favicon.png")?>" rel="icon" type="image/x-icon">
    <title>Meme Racing Dot Net</title>
    <meta name="viewport" content="width=device-width, minimum-scale=0.5, maximum-scale=1.6, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=9">
    <meta name="description" content="MemeRacing.net is a breakthrough bitcoin based playground where ingenuity, creativity, and a sense for what others will appreciate profits anyone willing to use them.">
    <meta name="keywords" content="free bitcoin, earn bitcoin, meme racing, meme, ideas, brainstorm, crowdsource">
    <meta name="robots" content="index, follow">
    <link href="<?=auto_version($siteURL."bootstrap/css/bootstrap.min.css")?>" rel="stylesheet" media="screen">
    <link href="<?=auto_version($siteURL."bootstrap/css/bootstrap-responsive.min.css")?>" rel="stylesheet" media="screen">
    <link rel="stylesheet" type="text/css" media="all" href="<?=auto_version($siteURL."style.css")?>">
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
    <script src="<?=auto_version($siteURL."bootstrap/js/bootstrap.min.js")?>"></script>
    <script type='text/javascript' src="<?=auto_version($siteURL."js/bitwitbet.js")?>"></script>
    <!--[if lt IE 9]>
        <script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->
    <!--[if gte IE 9]>
      <style type="text/css">
        .gradient {
           filter: none !important;
        }
      </style>
    <![endif]-->

</head>
<body>
<?php
    if(!$hide_debug)
    {
        echo "<!-- /* ".print_r(get_defined_vars(),true)." */ -->";
    }
    if(!preg_match('/^127\.0\./', $_SERVER['SERVER_ADDR']) )
    {
        echo <<< GA
<script type='text/javascript'>

  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', 'UA-39383231-1']);
  _gaq.push(['_trackPageview']);

  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();

</script>
GA;
    }
?>

      <?=$notMailed?>
    <header>
      <div class="masthead container">
        <h3 class="mr-header">Meme Racing <small style='color:white'>Alpha</small><small class="pull-right bc-addr">Our bitcoin address: <?=$OUR_BTC_ADDR?></small></h3>
        <div class="navbar navbar-inverse">
          <div class="navbar-inner gradient">
            <div class="container">
              <ul class="nav">
                <li><a href="<?=$siteURL?>">Home</a></li>
                <li><a href="<?=$siteURL?>prompts">Prompts</a></li>
                <li><a href="<?=$siteURL?>faq">FAQ</a></li>
                <li><a href="<?=$siteURL?>contact">Contact</a></li>
                <?=$logout?>
              </ul>
            </div>
          </div>
        </div><!-- /.navbar -->
      </div>
    </header>
