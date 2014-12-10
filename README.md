Database wrapper for PDO
===============================

This is a very very simplistic database wrapper for PDO.
For simple purpose only.

Connection
--------------------------
    <?php
    // DB Params
    Db::set('default',array(
        'host' => "localhost",
        'name' => "database_name",
        'user' => "root",
        'pass' => "pass",
        'port' => 3306
    ));

    // DB Connection
    try {
        $db = new Db('default');
        $db->query('set names utf8');
    }catch(Exception $e) {
        die("db connection error");
    }

Query
--------------------------

    $rows = $db->get_rows('SELECT id FROM user WHERE email=?', array('email@email.it'));
    // or 
    $rows = $db->get_rows('SELECT id FROM user WHERE email=:email', array(':email'=>'email@email.it'));
    
    // get only the first row
    $row = $db->get_row('SELECT id FROM user WHERE login=:login AND pass = :pass', array(':login'=>'username', ':pass'=>'password'));

Fast INSERT
--------------------------
    $new_user_id = $db->insert(
        "user",
        array(
            'login' => 'username',
            'pass'  => 'pass',
        )
    );
    
Fast UPDATE
--------------------------
    $db->update(
        "user",
        array(
            'pass'=>'newpass'
        ),
        array('login'=>'username')
    );

Enjoy :-)