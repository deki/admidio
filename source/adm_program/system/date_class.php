<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_dates
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Diese Klasse dient dazu einen Terminobjekt zu erstellen. 
 * Ein Termin kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $date = new Date($g_adm_con);
 *
 * Mit der Funktion getDate($dat_id) kann nun der gewuenschte Termin ausgelesen
 * werden.
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * clear()                - Die Klassenvariablen werden neu initialisiert
 * setArray($field_arra)  - uebernimmt alle Werte aus einem Array in das Field-Array 
 * setValue($field_name, $field_value) - setzt einen Wert fuer ein bestimmtes Feld
 * getValue($field_name)  - gibt den Wert eines Feldes zurueck
 * save($login_user_id, $organization)   
 *                        - Termin wird mit den geaenderten Daten in die Datenbank
 *                          zurueckgeschrieben bwz. angelegt
 * delete()               - Der gewaehlte User wird aus der Datenbank geloescht
 * getIcal()              - gibt einen Termin im iCal-Format zurueck
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

include(SERVER_PATH. "/adm_program/libs/bennu/bennu.inc.php");

class Date
{
    var $db_connection;
    var $db_fields_changed;         // Merker ob an den db_fields Daten was geaendert wurde
    var $db_fields = array();       // Array ueber alle Felder der Rollen-Tabelle der entsprechenden Rolle
    
    // Konstruktor
    function Date($connection, $date_id = 0)
    {
        $this->db_connection = $connection;
        if($date_id > 0)
        {
            $this->getRole($date_id);
        }
        else
        {
            $this->clear();
        }
    }

    // Termin mit der uebergebenen ID aus der Datenbank auslesen
    function getDate($date_id)
    {
        $this->clear();
        
        if($date_id > 0 && is_numeric($date_id))
        {
            $sql    = "SELECT * FROM ". TBL_DATES. " WHERE dat_id = $date_id";
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
    
            if($row = mysql_fetch_array($result, MYSQL_ASSOC))
            {
                // Daten in das Klassenarray schieben
                foreach($row as $key => $value)
                {
                    if(is_null($value))
                    {
                        $this->db_fields[$key] = "";
                    }
                    else
                    {
                        $this->db_fields[$key] = $value;
                    }
                }
            }
        }
    }

    // alle Klassenvariablen wieder zuruecksetzen
    function clear()
    {
        $this->db_fields_changed = false;
    
        if(count($this->db_fields) > 0)
        {
            foreach($this->db_fields as $key => $value)
            {
                $this->db_fields[$key] = "";
            }
        }
        else
        {
            // alle Spalten der Tabelle adm_dates ins Array einlesen 
            // und auf null setzen
            $sql = "SHOW COLUMNS FROM ". TBL_DATES;
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
            
            while ($row = mysql_fetch_array($result))
            {
                $this->db_fields[$row['Field']] = "";
            }
        }
    }

    // Funktion uebernimmt alle Werte eines Arrays in das Field-Array
    function setArray($field_array)
    {
        foreach($field_array as $field => $value)
        {
            $this->db_fields[$field] = $value;
        }
    }
    
    // Funktion setzt den Wert eines Feldes neu, 
    // dabei koennen noch noetige Plausibilitaetspruefungen gemacht werden
    function setValue($field_name, $field_value)
    {
        $field_name  = strStripTags($field_name);
        $field_value = strStripTags($field_value);
        
        if(strlen($field_value) > 0)
        {
            // Plausibilitaetspruefungen
            switch($field_name)
            {
                case "dat_id":
                case "dat_usr_id":
                case "dat_usr_id_change":
                    if(is_numeric($field_value) == false)
                    {
                        $field_value = null;
                    }
                    break;

                case "dat_global":
                    if($field_value != 1)
                    {
                        $field_value = 0;
                    }
                    break;
            }
        }

        if(isset($this->db_fields[$field_name])
        && $field_value != $this->db_fields[$field_name])
        {
            $this->db_fields[$field_name] = $field_value;
            $this->db_fields_changed      = true;
        }
    }

    // Funktion gibt den Wert eines Feldes zurueck
    // hier koennen auch noch bestimmte Formatierungen angewandt werden
    function getValue($field_name)
    {
        return $this->db_fields[$field_name];
    }
    
    // die Funktion speichert die Rollendaten in der Datenbank,
    // je nach Bedarf wird ein Insert oder Update gemacht
    function save($login_user_id, $organization)
    {
        if((is_numeric($login_user_id) || strlen($login_user_id) == 0)
        && (is_numeric($this->db_fields['dat_id']) || strlen($this->db_fields['dat_id']) == 0))
        {
            if($login_user_id > 0)
            {
                // Default-Felder vorbelegen
                if($this->db_fields['dat_id'] > 0)
                {
                    $this->db_fields['dat_last_change']   = date("Y-m-d H:i:s", time());
                    $this->db_fields['dat_usr_id_change'] = $login_user_id;
                }
                else
                {
                    $this->db_fields['dat_timestamp']     = date("Y-m-d H:i:s", time());
                    $this->db_fields['dat_usr_id']        = $login_user_id;
                    $this->db_fields['dat_org_shortname'] = $organization;
                }
            }
            
            if($this->db_fields_changed || is_null($this->db_fields['dat_id']))
            {
                // SQL-Update-Statement fuer User-Tabelle zusammenbasteln
                $item_connection = "";                
                $sql_field_list  = "";
                $sql_value_list  = "";

                // Schleife ueber alle DB-Felder und diese dem Update hinzufuegen                
                foreach($this->db_fields as $key => $value)
                {
                    // ID und andere Tabellenfelder sollen nicht im Insert erscheinen
                    if($key != "dat_id" && strpos($key, "dat_") === 0) 
                    {
                        if($this->db_fields['dat_id'] == 0)
                        {
                            if(strlen($value) > 0)
                            {
                                // Daten fuer ein Insert aufbereiten
                                $sql_field_list = $sql_field_list. " $item_connection $key ";
                                if(is_numeric($value))
                                {
                                    $sql_value_list = $sql_value_list. " $item_connection $value ";
                                }
                                else
                                {
                                    $value = addSlashes($value);
                                    $sql_value_list = $sql_value_list. " $item_connection '$value' ";
                                }
                            }
                        }
                        else
                        {
                            // Daten fuer ein Update aufbereiten
                            if(strlen($value) == 0 || is_null($value))
                            {
                                $sql_field_list = $sql_field_list. " $item_connection $key = NULL ";
                            }
                            elseif(is_numeric($value))
                            {
                                $sql_field_list = $sql_field_list. " $item_connection $key = $value ";
                            }
                            else
                            {
                                $value = addSlashes($value);
                                $sql_field_list = $sql_field_list. " $item_connection $key = '$value' ";
                            }
                        }
                        if(strlen($item_connection) == 0 && strlen($sql_field_list) > 0)
                        {
                            $item_connection = ",";
                        }
                    }
                }

                if($this->db_fields['dat_id'] > 0)
                {
                    $sql = "UPDATE ". TBL_DATES. " SET $sql_field_list 
                             WHERE dat_id = ". $this->db_fields['dat_id'];
                    error_log($sql);
                    $result = mysql_query($sql, $this->db_connection);
                    db_error($result,__FILE__,__LINE__);
                }
                else
                {
                    $sql = "INSERT INTO ". TBL_DATES. " ($sql_field_list) VALUES ($sql_value_list) ";
                    error_log($sql);
                    $result = mysql_query($sql, $this->db_connection);
                    db_error($result,__FILE__,__LINE__);
                    $this->db_fields['dat_id'] = mysql_insert_id($this->db_connection);
                }
            }

            $this->db_fields_changed = false;
            return 0;
        }
        return -1;
    }    
    
    // aktuellen Benutzer loeschen   
    function delete()
    {
        $sql    = "DELETE FROM ". TBL_DATES. " 
                    WHERE dat_id = ". $this->db_fields['dat_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $this->clear();
    }
   
    // gibt einen Termin im iCal-Format zurueck
    function getIcal($domain)
    {
        $cal = new iCalendar;
        $event = new iCalendar_event;
        $cal->add_property('METHOD','PUBLISH');
        $prodid = "-//www.admidio.org//Admidio" . ADMIDIO_VERSION . "//DE";
        $cal->add_property('PRODID',$prodid);
        $uid = mysqldatetime("ymdThis", $this->db_fields['dat_timestamp']) . "+" . $this->db_fields['dat_usr_id'] . "@" . $domain;
        $event->add_property('uid', $uid);
    
        $event->add_property('summary',     utf8_encode($this->db_fields['dat_headline']));
        $event->add_property('description', utf8_encode($this->db_fields['dat_description']));

        $event->add_property('dtstart', mysqldatetime("ymdThis", $this->db_fields['dat_begin']));
        $event->add_property('dtend',   mysqldatetime("ymdThis", $this->db_fields['dat_end']));
        $event->add_property('dtstamp', mysqldatetime("ymdThisZ", $this->db_fields['dat_timestamp']));

        $event->add_property('location', utf8_encode($this->db_fields['dat_location']));

        $cal->add_component($event);
        return $cal->serialize();    
    }    
}
?>
