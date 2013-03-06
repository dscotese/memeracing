<?php
include( "mysql.php" );
class sql_db_r extends sql_db
{
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
            $this->num_queries++;
            $this->query_result = mysql_query($query, $this->db_connect_id);
        }

        // Retry if MySQL went away (usu. from a timeout)
        if( @mysql_errno($this->db_connect_id) != 0 )
        {
            $err = $this->sql_error();
            if($err['code'] == '2006')
            {
                $newCid = $this->sql_db($this->server, $this->user, $this->password,
                    $this->dbname, $this->persistency);
                $this->db_connect_id = $newCid;
                $this->query_result = mysql_query($query, $this->db_connect_id);
            }
        }

        if( @mysql_errno($this->db_connect_id) != 0 )
        {
            if( $ranking_debug )
            {
                $err = $this->sql_error();
                echo "<pre>";
                print_r( get_included_files() );
                print_r( $err );
                echo "</pre><br />$query";
            }
            else
            {
                die( "Database Error.  The Webmaster will be forever in your debt if you report it!" );
            }
        }

        if($this->query_result)
        {
            unset($this->row[$this->query_result]);
            unset($this->rowset[$this->query_result]);
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
        $ret = @mysql_fetch_array($query_id);
        return (count($ret) == 2) ? $ret[0] : $ret;
    }

    function column($col = 0, $query_id = 0)
    {
        if(!$query_id)
        {
            $query_id = $this->query_result;
        }
        if($query_id)
        {
            $result = array();
            $field = @mysql_field_name($query_id, $col);
            $rownum = 0;
            $last = $this->sql_numrows($query_id);
            while( $rownum < $last )
            {
                $result[] = @mysql_result($query_id, $rownum, $field);
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