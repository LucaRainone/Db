<?php

/**
 * Class for DB management
 */
class Db {
    /**
     * array with all database already connected
     * @var array
     */
    static $dbs = array();
    /**
     * connection of the current instance
     * @var PDO
     */
    protected $db;
    /**
     * connection params (host,user,password,db) of all db connections
     * @var array
     */
    protected static $params = array();

    /**
     * connect to database $database. If the database is already connected, than returns the instance previously instantiated.
     * @param string $database default is "default".
     */
    public function __construct($database = 'default') {

        if(!isset(self::$dbs[$database])) {

            self::$dbs[$database] = new PDO('mysql:host='.self::$params[$database]['host'].';port='.self::$params[$database]['port'].';dbname='.self::$params[$database]['name'],
                self::$params[$database]['user'],self::$params[$database]['pass']);
        }
        $this->db = &self::$dbs[$database];
    }
    /**
     * set the params for the database $type.
     * @param string $type   a unique identified for the database. Generally "default" if the app has only one db connection
     * @param array $params array with "host, name, user, pass" keys
     */
    public static function set($type,$params) {
        self::$params[$type] = $params;
    }

    /**
     * execute a SELECT query and returns all rows.
     * @param  string  $qry     the query with parameters to bind
     * @param  array   $options array for parameters value
     * @return array           the result of query
     * @throws Exception If a MySql error has occurred
     * @example $db->get_rows('SELECT id,username FROM users_t WHERE user_id = :user_id', array(':user_id'=>1));
     *                 or equivalent
     *          $db->get_rows('SELECT id,username FROM users_t WHERE user_id = ?', array(1));
     *
     */
    public function get_rows($qry,$options = array()) {

        $db = &$this->db;
        $stmt = $db->prepare($qry);
        $res = $stmt->execute($options);
        if(!$res) {
            throw new Exception("Select Error " . print_r($stmt->errorInfo(),true));
        }

        $rows = array();
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        return $rows;

    }
    /**
     * Like get_rows but it returns only the first result
     * @see Db::get_rows
     * @param  string  $qry     the query with parameters to bind
     * @param  array   $options array for parameters value
     * @param  boolean $debug   if debug mode
     * @return array           the result of query
     */
    public function get_row($qry,$options = array(), $debug = false) {
        $rows = $this->get_rows($qry,$options, $debug);
        $return = null;
        if(count($rows)>0) {
            $return = $rows[0];
        }
        return $return;
    }

    /**
     * executes a generic query without returns any result
     * @param  string  $qry     the query with parameters to bind
     * @param  array   $options array for parameters value
     * @throws Exception If a MySql error has occurred
     */
    public function query($qry,$options = array()) {

        $db = &$this->db;
        $stmt = $db->prepare($qry);
        $res = $stmt->execute($options);
        if(!$res) {
            throw new Exception("query Error " . print_r($stmt->errorInfo(),true));
        }
    }

    /**
     * insert a row in the given table.
     * @param  string $table the desired table
     * @param  array $array  key=>value array for data to insert
     * @return int id   the new id row inserted
     * @throws Exception If a MySql error has occurred
     */
    public function insert($table,array $array) {
        $db = &$this->db;

        $fields = array();
        $params = $array;
        $values = array();

        // prepare array for parameters
        foreach($params as $field=>$value) {
            if(strtolower($value) == 'now()') {
                $values[] = $value;
            }else {
                $values[] = ':'.$field;
            }
            $fields[] = $field;
        }
        // prepare the query string
        $qry = 'INSERT INTO ' . $table . ' ('.implode(",",$fields).') VALUES('.implode(",",$values).')';

        $sth = $db->prepare($qry);

        // bind the values
        foreach($array as $field=>$value) {
            if(strtolower($value) != 'now()') {
                $sth->bindValue(':'.$field,$value);
            }
        }
        // execute the queries
        $res = $sth->execute();

        if(!$res) {
            throw new Exception("Insert Error " . print_r($sth->errorInfo(),true));
        }

        return $db->lastInsertId();
    }
    /**
     * update one or more rows of the given table.
     * @param  string $table the table to update
     * @param  array  $array the fields to update
     * @param  array  $where the "where" condition. Array key=>value that will be concat in AND condition.
     * @throws Exception If a MySql error has occurred.
     */
    public function update($table, $array, $where) {
        $db = &$this->db;
        $qry = 'UPDATE ' . $table . ' SET ';
        $parts = array();
        foreach($array as $field=>$value) {

            $parts[] = strtolower($value)!= 'now()'? "$field = :$field" : "$field = NOW()";
        }
        $qry .= implode(", ",$parts);
        $qry .= " WHERE ";
        $parts = array();
        foreach($where as $field=>$value) {
            if(strtolower($value) != "now()") {
                $parts[] = "$field = :$field";
            }
        }
        $qry .= implode(" AND ",$parts);
        $sth = $db->prepare($qry);

        foreach($array as $field=>$value) {
            if(strtolower($value) != "now()") {
                if(is_string($value)) {
                    $sth->bindValue(':'.$field,$value,PDO::PARAM_STR);
                }else {
                    $sth->bindValue(':'.$field,$value,PDO::PARAM_INT);
                }
            }
        }
        foreach($where as $field=>$value) {
            $sth->bindValue(':'.$field, $value);
        }

        $res = $sth->execute();

        if(!$res) {
            throw new Exception("Update failed ". print_r($sth->errorInfo(),true));
        }
    }

    /**
     * start a transaction
     */
    public function begin_transaction() {
        $db = &$this->db;
        $db->beginTransaction();
    }
    /**
     * rollback a transaction
     */
    public function rollback_transaction() {
        $db = &$this->db;
        $db->rollBack();
    }
    /**
     * commit a transaction
     */
    public function commit_transaction() {
        $db = &$this->db;
        $db->commit();
    }

    /**
     * convert an array of string in concatenated quotes separated by a comma.
     * Ex: array('a','b','c') => 'a','b','c'
     * Purpose of this is to get a string for build an IN condition.
     * @param  $array
     * @return array
     */
    public function bind_array($array) {
        $db = &$this->db;
        $s = array();
        foreach($array as $v) {
            $s[] = $db->quote($v);
        }
        return implode(",",$s);
    }

}