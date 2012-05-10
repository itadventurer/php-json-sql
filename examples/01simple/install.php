<?php
define('ROOTDIR','');
include_once 'config/config.php';
include_once '../../src/json_sql_installer.php';

$install=new jsonSqlInstaller(json_decode(file_get_contents('config/db.json')),$dbh,true);
$install->install();
?>