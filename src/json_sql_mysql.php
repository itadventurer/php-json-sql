<?php
/**
 * SQL-Klasse für MySQL (SQLite funktioniert auch)
 * @package php-json-sql
 */
class jsonSqlMysql extends jsonSqlBase {
	/**
	 * Erlaubte Operationen
	 * @var array
	 */
	protected $allowed_op=array('=','<=>','<>','!=','<=','<','>=','>','IS','IS NOT','IS NULL','IS NOT NULL','BETWEEN','IN','NOT IN');
	/**
	 * Erlaubte Operationen Für Case-then
	 * @var array
	 */
	protected $allowed_case_op=array('=','+','-','*','/','NOTHING');

	/**
	 * Erlaubte Order-Befehle
	 * @var array
	 */
	protected $allowed_order=array('ASC','DESC','RAND');
	/**
	 * The PDO-Database-Class
	 * @var pdo
	 */
	private $dbh;
	/**
	 * Konstruktor
	 * @param json-object $db_structure
	 * @param json-object $db_aliases
	 * @param pdo $dbh
	 */
	public function __construct($db_structure,$db_aliases,$dbh) {
		$this->dbh=$dbh;
		parent::__construct($db_structure, $db_aliases);
	}

	/**
	 * Abfrage zur Filter-Abfrage
	 * @var null|json-object
	 */
	private $queryGetter=null;
	/**
	 * Initialisiert die Funktion, Filter aus der Datenbank zu holen
	 * @param json-object $query Die Abfrage dazu
	 */
	public function initQueryGetter($query) {
		$this->queryGetter=$query;
	}
	/**
	 * Gecachte Filter
	 * @var array
	 */
	private $filters;
	/**
	 * Holt eine Abfrage aus der Datenbank und führt diese aus
	 * @param String $name Abfragenname
	 * @throws SQLException
	 */
	public function getQueryFromDB($name) {
		if($this->queryGetter===null)
			throw new SQLException('Query Getter was not initialized yet', 1327325963);

		if($this->debug)
			$this->firephp->group($name);

		if(isset($this->filters[$name])){
			$filter=$this->filters[$name];
		} else{
			$filter=$this->query($this->queryGetter,array($name));
			if(!isset($filter[0]))
				throw new sqlException('No such filter', 1329308645,$name);
			$filter=$filter[0]['filter_json'];
			$this->filters[$name]=$filter;
		}
		$params=func_get_args();
		unset($params[0]);
		if(!is_object($filter))
			$filter=json_decode($filter);
		$ret=$this->query($filter,$params);
		if($this->debug)
			$this->firephp->groupEnd();
		return $ret;
	}
	/**
	 * Führt die (evtl. Erweiterte) Abfrage mit Parametern aus
	 * @see jsonSqlMysql::getQueryFromDB
	 * @param string $name Abfragen-Name
	 * @param json-object $params Parameter
	 * @param json-object $additional Abfragen-Erweiterung
	 * @throws SQLException
	 */
	public function getExtQueryFromDB($name,$params=null,$additional=null) {
		if($this->queryGetter===null)
			throw new SQLException('Query Getter was not initialized yet', 1327325963);

		if($this->debug)
			$this->firephp->group($name);

		if(isset($this->filters[$name]) && is_object($this->filters[$name])){
			$filter=clone $this->filters[$name];
		} else{
			$filter=$this->query($this->queryGetter,array($name));

			if(count($filter)==0)
				throw new SQLException('No such Filter', 1328652428, $name);
			$filter=json_decode($filter[0]['filter_json']);
			$this->filters[$name]=clone $filter;
		}

		if($params==null)
			$params=array();
		if($additional!=null) {
			foreach($additional as $key=>$val) {
				if(!isset($filter->$key)){
					$filter->$key=$val;
				}else{
					if(is_object($val) && is_array($filter->$key)){
						$filter->{$key}[]=$val;
					}elseif(is_object($val) && is_object($filter->$key)){
						foreach($val as $key2=>$val2) {
							$filter->$key->$key2=$val2;
						}
					}elseif(is_array($val) && is_array($filter->$key)){
						foreach($val as $val2) {
							$filter->{$key}[]=$val2;
						}
					} else {
						$filter->$key=$val;
					}
				}
			}
		}

		$ret= $this->query($filter,$params);
		if($this->debug)
			$this->firephp->groupEnd();
		return $ret;
	}

	/**
	 * returns The PDO::PARAM_*-Type for a Field
	 * @param string $table Tabellenname
	 * @param string $field Feldname
	 * @throws sqlException
	 */
	protected function getPDOType($table,$field) {
		$f=$this->db_structure->$table->$field;
		if(is_object($f)) {
			$f=$f->type;
		} elseif(is_array($f)) {
			if($f[0]=='foreign')
				$f='int';
			else
				$f=$f[0];
		} elseif(!is_string($f)) {
			throw new sqlException('Wrong type', 1325077369,$f);
		}

		switch($f) {
		case 'string':
		case 'text':
		case 'json':
		case 's':
		case 'date':
		case 'datetime':
			return PDO::PARAM_STR;
			break;
		case 'float':
		case 'f':
			return PDO::PARAM_STR;
			break;
		case 'int':
		case 'i':
		case 'id':
			return PDO::PARAM_INT;
			break;
		case 'bool':
		case 'boolean':
		case 'b':
			return PDO::PARAM_BOOL;
			break;
		}
		throw new sqlException('Wrong type for query', 1325520117,$f);
	}

	/**
	 * Führt eine Update-Abfrage aus
	 * @param array $update Update-Teil
	 * @param array $where Where-Teil
	 * @return @see update()
	 */
	protected function execUpdate($update, $where=null) {
		$types=array();
		$table=key($update);
		$sql='UPDATE '.$table.' SET ';
		$additional='';
		$i=0;
		foreach($update[$table] as $key=>$val) {
			if($i!=0)
				$sql.=',';
			if($val===99999998)
				$sql.=' '.$key.'='.$key.'-1 ';
			elseif($val===99999999) {
				$sql.=' '.$key.'='.$key.'+1 ';
				$additional=' ORDER BY '.$key.' DESC';
			}elseif(is_array($val)){
				$sql.=' '.$key.'= CASE ';
				foreach($val as $val2) {
					if(isset($val2->else)){
						$sql.=' ELSE ';
						switch($val2->else->op){
						case '=':
							$sql.=$val2->else->value;
							break;
						case 'NOTHING':
							$sql.=$val2->else->field->field;
							break;
						default:
							$sql.=$val2->else->field->field.' '.$val2->else->op.' '.$val2->else->value;
							break;
						}
					} else {
						$sql.='WHEN '.$val2->case->field->field.' ';
						switch($val2->case->op){
						case 'IS NULL':
						case 'IS NOT NULL':
							$sql.=$val2->case->op;
							break;
						case 'BETWEEN':
							$sql.=$val2->case->op.' '.$val2->case->value[0].' AND '.$val2->case->value[1];
							break;
						default:
							$sql.=' '.$val2->case->op.' '.$val2->case->value;
						}
						$sql.=' THEN ';
						if($val2->then->op=='=')
							$sql.=$val2->then->value;
						else
							$sql.=$val2->then->field->field.' '.$val2->then->op.'('.$val2->then->value.')';
					}
					$sql.=' ';
				}
				$sql.='END ';

			}else {
				$sql.=' '.$key.'=?';
				if($val==='true')
					$val=true;
				elseif($val==='false')
					$val=false;

				$types[]=array($val, $this->getPDOType($table, $key));
			}
			++$i;
		}
		if($where)
			$sql.=' WHERE '.$where;
		$sql.=$additional;

		$stmt=$this->dbh->prepare($sql);

		foreach($types as $i=>$type) {
			$stmt->bindValue($i+1, $type[0],$type[1]);
		}
		if($this->debug){
			$this->firephp->info($sql, 'SQL-Update-Query');
		}
		return $stmt->execute();
	}


	/**
	 * Gets a foreign id for a input-value of a foreign table (select || input)
	 * @param json_object $value The input-value
	 * @param json_object $alias The alias-Object
	 * @return int ID des Datensatzes
	 */
	protected function getForeignId($value,$alias) {
		$sql='SELECT id FROM '.$alias->foreign->table.' WHERE '.$alias->foreign->field.'=?';

		$sth=$this->dbh->prepare($sql);
		$sth->bindParam(1, $value,$this->getPDOType($alias->foreign->table, $alias->foreign->field));
		$sth->execute();
		$result=$sth->fetch();
		if(!$result) {
			$sth=$this->dbh->prepare('INSERT INTO '.$alias->foreign->table.' ('.$alias->foreign->field.') VALUES (?)');
			$sth->bindParam(1, $value,$this->getPDOType($alias->foreign->table, $alias->foreign->field));
			$sth->execute();
			$id=$this->dbh->lastInsertId();
			return $id;
		}
		return $result['id'];
	}

	/**
	 * returns an order-by-String
	 * @param json_object $porder The Order-Param
	 * @throws sqlException
	 */
	protected function getOrder($porder) {
		$porder='ORDER BY';
		$i=0;

		foreach($porder as $key=>$val) {
			if($i!=0)
				$order.=',';
			$order.=' '.$val['table'].'.'.$val['field'].' '.$val['op'];
			++$i;
		}
		return $order;
	}
	/**
	 * Führt eine Insert-Abfrage aus
	 * @param string $table Tabelle
	 * @param array $fields Felder (Key-Value Pair)
	 * @return @see insert()
	 */
	protected function execInsert($table, $fields) {
		$sql='INSERT INTO '.$table.' (';
		$values='';
		$types=array();
		$i=0;
		foreach($fields as $key=>$val) {
			if($i!=0) {
				$sql.=',';
				$values.=',';
			}
			$sql.=$key;
			$values.='?';
			if($val==='true')
				$val=true;
			elseif($val==='false')
				$val=false;
			$types[]=array($val, $this->getPDOType($table, $key));
			++$i;
		}

		$sql.=') VALUES ('.$values.');';
		if($this->debug){
			$this->firephp->info($sql, 'SQL-Insert-Query');
		}
		$stmt=$this->dbh->prepare($sql);

		foreach($types as $i=>$type) {
			$stmt->bindValue($i+1, $type[0],$type[1]);
		}
		$stmt->execute();

		return $this->dbh->lastInsertId();
	}
	/**
	 * Erstellt den Join-Teil für die Select-Abfrage
	 * @param array $join Das Join-Array
	 * @return string der Join-Teil der Abfrage
	 */
	private function getJoinSelect($join){
		$sql_join='';
		$sql='';
		foreach($join as $val) {
			$tbl_alias='';
			$val_table=$val->table;
			$foreign_table=$val->foreign->table;
			if(isset($val->alias)) {
				$tbl_alias=' AS '.$val->alias.' ';
				$foreign_table=$val->alias;
				if(isset($val->via))
					$val_table=$val->via;
			}
			if(!isset($val->foreign))
				throw new sqlException('No foreign table for',1335287771,$val);
			$sql.=' LEFT JOIN '.$val->foreign->table.$tbl_alias.
				'  ON '.$val_table.'.'.$val->field.'='.$foreign_table.'.id';
		}
		return $sql;
	}
	/**
	 * Create and execute the select-query
	 * @param array $what Was soll geholt werden
	 * @param array $from aus welchen Tabellen
	 * @param array $where Die Where-parameter
	 * @param aray $order Order
	 * @param array $group Gruppen-Infos
	 * @param array $limit Limit
	 * @param array $join Join-Infos
	 * @return @see select()
	 */
	protected function execSelect($what, $from,$where=null,$order=null,$group=null,$limit=null,$join=null) {
		$values=null;
		$sql='SELECT ';
		$ordered_what=array();
		$counter=false;
		foreach($what as $table=>$val) {
			foreach($val as $field) {
				if($field['field']=='__count') {
					$counter=true;
					$count_where='';
					$group='';
					$select='*';
					if(isset($this->db_aliases->{$field['alias']})){
						if(isset($this->db_aliases->{$field['alias']}->where)){
							$where_object=$this->db_aliases->{$field['alias']}->where;
							$count_where=' WHERE '.$where_object->field.$where_object->op.'"'.$where_object->value.'" ';
						}
						if(isset($this->db_aliases->{$field['alias']}->group))
							$group=' GROUP BY '.$this->db_aliases->{$field['alias']}->group;
						if(isset($this->db_aliases->{$field['alias']}->distinct))
							$select=' DISTINCT '.$this->db_aliases->{$field['alias']}->distinct;
					}
					$owhere='';
					if($where && empty($count_where))
						$owhere=' WHERE '.$where;
					elseif($where && !empty($count_where))
						$owhere=' AND '.$where;
					$cjoin='';
					if($join) {
						$cjoin=$this->getJoinSelect($join);
					}

					$csql=' ( SELECT COUNT('.$select.') FROM '.$table . $cjoin . $count_where.' ' .$owhere.' '.$group.') AS '.$field['alias'];

					$ordered_what[$field['position']]=$csql;
					$limit=1;
				} elseif($counter==false) {
					if(isset($field['type']) && $field['type']=='count')
						$ordered_what[$field['position']]=' COUNT('.$table.'.'.$field['field'].') AS '.$field['alias'];
					elseif(isset($field['type']) && $field['type']=='count_distinct'){
						$ordered_what[$field['position']]=' COUNT( DISTINCT '.$table.'.'.$field['field'].') AS '.$field['alias'];
					}else
						$ordered_what[$field['position']]=' '.$table.'.'.$field['field'].' AS '.$field['alias'];
				}
			}
		}

		ksort($ordered_what);
		$sql.=implode(',', $ordered_what);
		unset($ordered_what);
		$sql.=' FROM';
		$sql.=' '.$from[0];
		if($join && count($join)>0) {
			$sql.=$this->getJoinSelect($join);
		}
		if($where) {
			$sql.=' WHERE '.$where;
		}
		if($group){
			$sql.=' GROUP BY ';
			$i=0;
			foreach($group as $g) {
				if($i!=0) $sql.=', ';
				$sql.=$g['table'].'.'.$g['field'].' ';
				++$i;
			}

		}
		if($order) {
			$sql.=' ORDER BY ';
			foreach($order as $key=>$val) {
				if($key!=0) $sql.=', ';
				$sql.=$val['table'].'.'.$val['field'].' '.$val['op'].' ';
			}
		}
		if($limit) {
			if(is_array($limit))
				$sql.=' LIMIT '.intval($limit[0]).','.intval($limit[1]);
			else
				$sql.=' LIMIT '.intval($limit);
		}
		$stmt=$this->dbh->prepare($sql);
		if($this->debug){
			$this->firephp->info($sql, 'SQL-Select-Query');
		}
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	/**
	 * Führt eine Delete-Abfrage aus
	 * @param string $table Tabellenname
	 * @param object $where optionales Where-Objekt
	 * @return @see delete()
	 */
	protected function execDelete($table, $where=null) {
		$sql='DELETE FROM '.$table;
		if($where)
			$sql.=' WHERE '.$where;
		$stmt=$this->dbh->prepare($sql);
		if($this->debug){
			$this->firephp->info($sql, 'SQL-Delete-Query');
		}
		return $stmt->execute();
	}

}
