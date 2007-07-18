<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_roles
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Diese Klasse dient dazu einen Rollenobjekt zu erstellen.
 * Eine Rolle kann ueber diese Klasse in der Datenbank verwaltet werden.
 * Dazu werden die Informationen der Rolle sowie der zugehoerigen Kategorie
 * ausgelesen. Geschrieben werden aber nur die Rollendaten
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $role = new Role($g_adm_con);
 *
 * Mit der Funktion getRole($user_id) kann die gewuenschte Rolle ausgelesen
 * werden.
 *
 * Folgende Funktionen stehen weiter zur Verfuegung:
 *
 * clear()                - Die Klassenvariablen werden neu initialisiert
 * setArray($field_arra)  - uebernimmt alle Werte aus einem Array in das Field-Array
 * setValue($field_name, $field_value) - setzt einen Wert fuer ein bestimmtes Feld
 * getValue($field_name)  - gibt den Wert eines Feldes zurueck
 * save($login_user_id)   - Rolle wird mit den geaenderten Daten in die Datenbank
 *                          zurueckgeschrieben bwz. angelegt
 * delete()               - Die gewaehlte Rolle wird aus der Datenbank geloescht
 * setInactive()          - setzt die Rolle auf inaktiv
 * setActive()            - setzt die Rolle wieder auf aktiv
 * countVacancies($count_leaders = false) - gibt die freien Plaetze der Rolle zurueck
 *                          dies ist interessant, wenn rol_max_members gesetzt wurde
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

class Role
{
    var $db_connection;
    
    var $db_fields_changed;         // Merker ob an den db_fields Daten was geaendert wurde
    var $db_fields = array();       // Array ueber alle Felder der Rollen-Tabelle der entsprechenden Rolle

    // Konstruktor
    function Role($connection, $role = 0)
    {
        $this->db_connection = $connection;
        if(strlen($role) > 0)
        {
            $this->getRole($role);
        }
        else
        {
            $this->clear();
        }
    }

    // Rolle mit der uebergebenen ID oder dem Rollennamen aus der Datenbank auslesen
    function getRole($role)
    {
        global $g_current_organization;
        
        $this->clear();
        
        if(is_numeric($role))
        {
        	$condition = " rol_id = $role ";
        }
        else
        {
            $role = addslashes($role);
            $condition = " rol_name LIKE '$role' ";
        }

        $sql = "SELECT * 
                  FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. " 
                 WHERE rol_cat_id = cat_id
                   AND $condition
                   AND cat_org_id = ". $g_current_organization->getValue("org_id");
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
            // alle Spalten der Tabelle adm_roles ins Array einlesen 
            // und auf null setzen
            $sql = "SHOW COLUMNS FROM ". TBL_ROLES;
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
                case "rol_id":
                case "rol_cat_id":
                    if(is_numeric($field_value) == false
                    || $field_value == 0)
                    {
                        $field_value = "";
                    }
                    break;

                case "rol_weekday":
                case "rol_max_members":
                case "rol_usr_id_change":
                    if(is_numeric($field_value) == false)
                    {
                        $field_value = "";
                    }
                    break;

                case "rol_approve_users":
                case "rol_assign_roles":
                case "rol_announcements":
                case "rol_dates":
                case "rol_download":
                case "rol_edit_user":
                case "rol_guestbook":
                case "rol_guestbook_comments":
                case "rol_mail_logout":
                case "rol_mail_login":
                case "rol_photo":
                case "rol_profile":
                case "rol_weblinks":
                case "rol_locked":
                case "rol_valid":
                case "rol_system":
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
    function save($login_user_id)
    {
        if((is_numeric($login_user_id) || strlen($login_user_id) == 0)
        && (is_numeric($this->db_fields['rol_id']) || strlen($this->db_fields['rol_id']) == 0))
        {
            if($login_user_id > 0)
            {
                $this->db_fields['rol_last_change']   = date("Y-m-d H:i:s", time());
                $this->db_fields['rol_usr_id_change'] = $login_user_id;
            }
            
            if($this->db_fields_changed || strlen($this->db_fields['rol_id']) == 0)
            {
                // SQL-Update-Statement fuer User-Tabelle zusammenbasteln
                $item_connection = "";                
                $sql_field_list  = "";
                $sql_value_list  = "";

                // Schleife ueber alle DB-Felder und diese dem Update hinzufuegen                
                foreach($this->db_fields as $key => $value)
                {
                    // ID und andere Tabellenfelder sollen nicht im Insert erscheinen
                    if($key != "rol_id" && strpos($key, "rol_") === 0) 
                    {
                        if($this->db_fields['rol_id'] == 0)
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

                if($this->db_fields['rol_id'] > 0)
                {
                    $sql = "UPDATE ". TBL_ROLES. " SET $sql_field_list 
                             WHERE rol_id = ". $this->db_fields['rol_id'];
                    error_log($sql);
                    $result = mysql_query($sql, $this->db_connection);
                    db_error($result,__FILE__,__LINE__);
                }
                else
                {
                    $sql = "INSERT INTO ". TBL_ROLES. " ($sql_field_list) VALUES ($sql_value_list) ";
                    error_log($sql);
                    $result = mysql_query($sql, $this->db_connection);
                    db_error($result,__FILE__,__LINE__);
                    $this->db_fields['rol_id'] = mysql_insert_id($this->db_connection);
                }
            }

            $this->db_fields_changed = false;
            return 0;
        }
        return -1;
    }    

    // aktuelle Rolle loeschen
    function delete()
    {
        // die Rolle "Webmaster" darf nicht geloescht werden
        if($this->db_fields['rol_name'] != "Webmaster")
        {
            $sql    = "DELETE FROM ". TBL_ROLE_DEPENDENCIES. " 
                        WHERE rld_rol_id_parent = ". $this->db_fields['rol_id']. "
                           OR rld_rol_id_child  = ". $this->db_fields['rol_id'];
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);

            $sql    = "DELETE FROM ". TBL_MEMBERS. " 
                        WHERE mem_rol_id = ". $this->db_fields['rol_id'];
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);

            $sql    = "DELETE FROM ". TBL_ROLES. " 
                        WHERE rol_id = ". $this->db_fields['rol_id'];
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);

            $this->clear();
            return 0;
        }
        return -1;
    }
    
    // aktuelle Rolle wird auf inaktiv gesetzt
    function setInactive()
    {
        // die Rolle "Webmaster" darf nicht auf inaktiv gesetzt werden
        if($this->db_fields['rol_name'] != "Webmaster")
        {
            $sql    = "UPDATE ". TBL_MEMBERS. " SET mem_valid = 0
                                                  , mem_end   = SYSDATE()
                        WHERE mem_rol_id = ". $this->db_fields['rol_id']. "
                          AND mem_valid  = 1 ";
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);

            $sql    = "UPDATE ". TBL_ROLES. " SET rol_valid = 0
                        WHERE rol_id = ". $this->db_fields['rol_id'];
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
            
            return 0;
        }
        return -1;
    }

    // aktuelle Rolle wird auf aktiv gesetzt
    function setActive()
    {
        // die Rolle "Webmaster" ist immer aktiv
        if($this->db_fields['rol_name'] != "Webmaster")
        {
            $sql    = "UPDATE ". TBL_MEMBERS. " SET mem_valid = 1
                                                  , mem_end   = NULL
                        WHERE mem_rol_id = ". $this->db_fields['rol_id'];
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);

            $sql    = "UPDATE ". TBL_ROLES. " SET rol_valid = 1
                        WHERE rol_id = ". $this->db_fields['rol_id'];
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
            
            return 0;
        }
        return -1;
    }
    
    // die Funktion gibt die Anzahl freier Plaetze zurueck
    // ist rol_max_members nicht gesetzt so wird immer 999 zurueckgegeben
    function countVacancies($count_leaders = false)
    {
        if($this->db_fields['rol_max_members'] > 0)
        {
            $sql    = "SELECT mem_usr_id FROM ". TBL_MEMBERS. "
                        WHERE mem_rol_id = ". $this->db_fields['rol_id']. "
                          AND mem_valid  = 1";
            if($count_leaders == false)
            {
                $sql = $sql. " AND mem_leader = 0 ";
            }
            $sql    = prepareSQL($sql, array($req_rol_id));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result,__FILE__,__LINE__);
            
            $num_members = mysql_num_rows($result);            
            return $this->db_fields['rol_max_members'] - $num_members;
        }
        return 999;
    }
}
?>