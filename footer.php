    <footer>
        <div id="mr-footer" class="container">
            <div class="mr-footer-content">
            </div>
        </div>
            <p class="copyright text-center">&copy; Memeracing.net</p>
    </footer>
<?php
    if(!$hide_debug && (
    	preg_match('/^127\.0\./', $_SERVER['SERVER_ADDR'])
    	 || $_SERVER['SERVER_ADDR'] == "::1" 
         || $_SERVER['REMOTE_ADDR'] == '76.174.129.203')
    	 )
    {
        echo "<!-- /* ".print_r(get_defined_vars(),true)." */ -->";
    }
?>
  </body>
</html>
