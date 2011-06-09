<?php
/******************************************************************************
 * Allgemeine Datenbankschnittstelle
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
class DBCommon
{
    public $db_type;
    public $user;
    public $password;
    public $dbname;
    public $server;

    protected $name;		// Name of database system like "MySQL"
    protected $version;    
    protected $minVersion;    
    protected $connect_id;
    protected $query_result;
    protected $sql;
    protected $transactions = 0;
	protected $db_structure;	// array with arrays of every table with their structure
    
    // Ausgabe der Datenbank-Fehlermeldung
    public function db_error($code = 0, $message = '')
    {
        global $g_root_path, $g_message, $g_preferences, $g_current_organization, $g_debug, $g_l10n;

        $backtrace = getBacktrace();

        // Rollback bei einer offenen Transaktion
        if($this->transactions > 0)
        {
            $this->endTransaction(true);
        }        

        if(headers_sent() == false && isset($g_preferences) && defined('THEME_SERVER_PATH'))
        {
            // Html-Kopf ausgeben
            $g_layout['title']  = $g_l10n->get('SYS_DATABASE_ERROR');
            require(SERVER_PATH. '/adm_program/system/overall_header.php');       
        }
        
        // Ausgabe des Fehlers an Browser
        $error_string = '<div style="font-family: monospace;">
                         <p><b>S Q L - E R R O R</b></p>
                         <p><b>CODE:</b> '.$code.'</p>
                         '.$message.'<br /><br />
                         <b>B A C K T R A C E</b><br />
                         '.$backtrace.'
                         </div>';
        echo $error_string;
        
        // ggf. Ausgabe des Fehlers in Log-Datei
        if($g_debug == 1)
        {
            error_log($code. ': '. $message);
        }
        
        if(headers_sent() == false && isset($g_preferences) && defined('THEME_SERVER_PATH'))
        {
            require(SERVER_PATH. '/adm_program/system/overall_footer.php');       
        }
        
        exit();
    }

    public function endTransaction($rollback = false)
    {
        if($rollback)
        {
            $result = $this->query('ROLLBACK');
        }
        else
        {
            // If there was a previously opened transaction we do not commit yet... 
            // but count back the number of inner transactions
            if ($this->transactions > 1)
            {
                $this->transactions--;
                return true;
            }

            $result = $this->query('COMMIT');

            if (!$result)
            {
                $this->db_error();
            }
        }
        $this->transactions = 0;
        return $result;
    }
	
	// returns the minimum required version of the database
	public function getName()
	{
		if(strlen($this->name) == 0)
		{
			$xmlDatabases = new SimpleXMLElement(SERVER_PATH.'/adm_program/system/db/databases.xml', 0, true);
			$node = $xmlDatabases->xpath("/databases/database[@id='".$this->db_type."']/name");
			$this->name = (string)$node[0]; // explicit typcasting because of problem with simplexml and sessions
		}
		return $this->name;		
	}
	
	// returns the minimum required version of the database
	public function getMinVersion()
	{
		if(strlen($this->minVersion) == 0)
		{
			$xmlDatabases = new SimpleXMLElement(SERVER_PATH.'/adm_program/system/db/databases.xml', 0, true);
			$node = $xmlDatabases->xpath("/databases/database[@id='".$this->db_type."']/minversion");
			$this->minversion = (string)$node[0]; // explicit typcasting because of problem with simplexml and sessions
		}
		return $this->minVersion;		
	}

	// returns the version of the database
	public function getVersion()
	{
		if(strlen($this->version) == 0)
		{
			$this->version = $this->server_info();
		}
		return $this->version;
	}
	
    // Modus der Transaktoin setzen (Inspiriert von phpBB)
    public function startTransaction()
    {
        // If we are within a transaction we will not open another one, 
        // but enclose the current one to not loose data (prevening auto commit)
        if ($this->transactions > 0)
        {
            $this->transactions++;
            return true;
        }

        $result = $this->query('START TRANSACTION');

        if (!$result)
        {
            $this->db_error();
        }

        $this->transactions = 1;
        return $result;
    }
}
 
?>