<?php
include( "mysql.php" );
class sql_db_r extends sql_db
{
    // Old code uses integers to identify queries and results
    // so this variable will be an array of the result objects
    // the index of which will be that number.
    //
    var $results = array();
    //
    //
    // Constructor
    //
    function sql_db_r($sqlserver, $sqluser, $sqlpassword, $database, $persistency = true)
    {
        $this->sql_db($sqlserver, $sqluser, $sqlpassword, $database, $persistency);
    }

    //
    // Base query method
    //
    function sql_query($query = "", $transaction = FALSE)
    {
        global $ranking_debug, $rd_queries;

        if($ranking_debug)
        {
            $rd_queries[] = $query;
        }
        // Remove any pre-existing queries
        unset($this->query_result);
        if($query != "")
        {
            $this->query_result = mysqli_query($this->db_connect_id, $query);
            $this->results[$this->num_queries++] = $this->query_result;
        }

        // Retry if MySQL went away (usu. from a timeout)
        if( @mysqli_errno($this->db_connect_id) != 0 )
        {
            $err = $this->sql_error();
            if($err['code'] == '2006')
            {
                $newCid = $this->sql_db($this->server, $this->user, $this->password,
                    $this->dbname, $this->persistency);
                $this->db_connect_id = $newCid;
                $this->query_result = mysqli_query($this->db_connect_id, $query);
            }
        }

        if( @mysqli_errno($this->db_connect_id) != 0 )
        {
            if( $ranking_debug )
            {
                $err = $this->sql_error();
                echo "<pre>";
                debug_print_backtrace(0,4);
                print_r( $err );
                echo "</pre><br />$query";
                die();
            }
            else
            {
                die( "Database Error.  The Webmaster will be forever in your debt if you report it!" );
            }
        }

        if($this->query_result)
        {
            unset($this->row[$this->num_queries-1]);
            unset($this->rowset[$this->num_queries-1]);
            
            return $this->query_result;
        }
        else
        {
            return false;
        }
    }

    function result($sql)
    {
        $query_id = $this->sql_query($sql);
        return (false === $query_id) ? $query_id : $this->sql_fetchrowset( $query_id );
    }

    function scalar($sql)
    {
        $query_id = $this->sql_query($sql);
        if(false === $query_id)
        {
            return $query_id;
        }
        $ret = @mysqli_fetch_array($query_id);
        return is_null($ret) ? false
            : (count($ret) == 2 ? $ret[0] : $ret);
    }

    function column($col = 0, $query_id = 0)
    {
        if(is_numeric($query_id))
        {
            $query_id = $this->results[$query_id];
        }
        if(!$query_id)
        {
            $query_id = $this->query_result;
        }
        if($query_id)
        {
            $last = $query_id->num_rows;
            $result = array();
            $rownum = 0;
            while( $rownum < $last )
            {
                $query_id->data_seek($rownum);
                $res = $query_id->fetch_array();
                $result[] = $res[$col];
                $rownum += 1;
            }
            return $result;
        }
        else
        {
            return false;
        }
    }
} // class sql_db_r
?>
