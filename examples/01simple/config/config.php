<?php
if(!isset(ROOTDIR)) exit();
include_once ROOTDIR.'../../src/json_sql.php';
include_once ROOTDIR.'../../src/json_sql_mysql.php';
include_once ROOTDIR.'../../src/sqlException.php';

$dbh=new PDO("sqlite:db.sdb");
$db=new jsonSqlMysql(file_get_contents(ROOTDIR.'config/db.json'), file_get_contents(ROOTDIR.'config/aliases.json'),$dbh);
$db->set_debug(true,ROOTDIR.'../FirePHP.class.php');
function handle($exception) {
	echo '<br ><b>'.$exception->getCode().':</b> ';
	echo $exception->getMessage().' ';
	if($exception instanceof sqlException) {
		var_dump($exception->getAdditional());
	}
}
set_exception_handler('handle');
?>
