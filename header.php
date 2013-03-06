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
    <link href="<?php echo $siteURL;?>favicon.png" rel="icon" type="image/x-icon">
    <title>Meme Racing Dot Net</title>
    <meta name="viewport" content="width=device-width, minimum-scale=0.5, maximum-scale=1.6, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=9">
    <meta name="description" content="MemeRacing.net is a breakthrough bitcoin based playground where ingenuity, creativity, and a sense for what others will appreciate profits anyone willing to use them.">
    <meta name="keywords" content="free bitcoin, earn bitcoin, meme racing, meme, ideas, brainstorm, crowdsource">
    <meta name="robots" content="index, follow">
    <link href="<?=$siteURL?>bootstrap/css/bootstrap.min.css" rel="stylesheet" media="screen">
    <link href="<?=$siteURL?>bootstrap/css/bootstrap-responsive.min.css" rel="stylesheet" media="screen">
    <link rel="stylesheet" type="text/css" media="all" href="<?=$siteURL?>style.css">
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
    <script src="<?=$siteURL?>bootstrap/js/bootstrap.min.js"></script>
    <script type='text/javascript' src="<?=$siteURL?>js/bitwitbet.js"></script>
    <!--[if lt IE 9]>
        <script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->
    <!--[if gte IE 9]>
      <style type="text/css">
        .gradient {
           filter: none;
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
?>

  <div class="container containerPadded">
      <?=$notMailed?>
      <div class="masthead">
        <h3 class="mrheader">Meme Racing <small class="pull-right bc-addr">Our bitcoin address: 1CyuAfo4r6KzspipMoXkN8ReaKf797QrPW</small></h3>
        <div class="navbar">
          <div class="navbar-inner">
            <div class="container">
              <ul class="nav">
                <li class="active"><a href="<?=$siteURL?>">Home</a></li>
                <li><a href="<?=$siteURL?>">Prompts</a></li>
                <li><a href="<?=$siteURL?>faq">FAQ</a></li>
                <li><a href="<?=$siteURL?>contact">Contact</a></li>
                <?=$logout?>
              </ul>
            </div>
          </div>
        </div><!-- /.navbar -->
      </div>