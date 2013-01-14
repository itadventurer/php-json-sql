<?php
/**
 * Installationsklasse
 * @package php-json-sql
 */
class jsonSqlInstaller{
	/**
	 * The PDO-Database-Class
	 * @var pdo
	 */
	private $dbh;
	/**
	 * The Database-Structure
	 * @var json_object
	 */
	private $tables;
	private $isSqlite=false;
	/**
	 * Initialisiert die Klasse
	 * @param String $input_tables JSON-Objekt mit Datenbank-Structur
	 * @param pdo $input_dbh	pdo-Objekt
	 */
	public function __construct($input_tables,$input_dbh,$is_sqlite=false){
		$this->tables=$input_tables;
		$this->dbh=$input_dbh;
		$this->isSqlite=$is_sqlite;
	}
	/**
	 * Konvertiert den im JSON-Objekt $tables festgelegten Datentyp in den Passenden Mysql-Datentyp
	 * @param String $type Typ aus $tables
	 * @return String mit dem Mysql-Datentyp
	 */
	private function getFieldType($type) {
		$sql_type='';
		if(is_array($type)) {
			switch($type[0]) {
			case "string":
				$sql_type=' VARCHAR('.intval($type[1]).')';
				break;
			case 'foreign':
				$sql_type=' INT';
				break;
			}
		} else {
			switch($type) {
			case "id":
				if($this->isSqlite)
					$sql_type=' INTEGER PRIMARY KEY AUTOINCREMENT';
				else
					$sql_type=' INT PRIMARY KEY AUTO_INCREMENT';
				break;
			case "int":
				$sql_type=' INT';
				break;
			case 'date':
				$sql_type=' DATE';
				break;
			case 'datetime':
				$sql_type=' DATETIME';
				break;
			case 'bool':
				//@deprecated
			case 'boolean':
				$sql_type=' BOOLEAN';
				break;
			case 'text':
			case 'json':
				$sql_type=' TEXT';
				break;
			case 'timestamp':
				$sql_type=' TIMESTAMP';
				break;
			}
		}
		return $sql_type;
	}
	/**
	 * Funktion legt die in $tables beschriebene Datenbank an
	 */
	public function install(){
		$query='';
		foreach($this->tables as $name=>$fields) {
			$query.='DROP TABLE IF EXISTS '.$name.";\n";
			$query.='CREATE TABLE '.$name." (\n";
			$i=0;
			foreach($fields as $fname=>$type) {
				if($i!=0)
					$query.=",\n";
				$query.=' '.$fname.' '.$this->getFieldType($type);
				if($type=='int'&&$fname=='id')
					$query.=' PRIMARY KEY ';
				++$i;
			}	
			$query.="\n);\n";
		}
		try {
			//echo $query;
			$e=$this->dbh->exec($query);
			$error=$this->dbh->errorInfo();
			if($error[0]!="00000")
				var_dump($this->dbh->errorInfo());
			else
				echo 'Database created';
		}catch(Exception $e) {
			echo 'Fehler: '.$e;
		}
	}
}
?>

