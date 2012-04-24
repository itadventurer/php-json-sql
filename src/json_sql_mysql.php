<?php
class jsonSqlMysql extends jsonSqlBase {
	/**
	 * Erlaubte Operationen
	 * @var array
	 */
	protected $allowed_op=array('=','<=>','<>','!=','<=','<','>=','>','IS','IS NOT','IS NULL','IS NOT NULL','BETWEEN','IN','NOT IN');
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
	private $filters;
	/**
	 * Holt eine Abfrage aus der Datenbank und führt diese aus
	 * @param String $name Abfragenname
	 * @throws SQLException
	 */
	public function getQueryFromDB($name) {
		if($this->queryGetter===null)
			throw new SQLException('Query Getter was not initialized yet', 1327325963);
		//echo $name;
		if(isset($this->filters[$name])){
			$filter=$this->filters[$name];
		} else{
			//var_dump($this->queryGetter,'<br>');
			$filter=$this->query($this->queryGetter,array($name));
			if(!isset($filter[0]))
			throw new sqlException('No such filter', 1329308645,$name);
			$filter=$filter[0]['filter_json'];
			$this->filters[$name]=$filter;
		}
		//var_dump('<hr>',$filter,$name,"\n\n<br><br>");
		//echo $filter;
		$params=func_get_args();
		unset($params[0]);
		return $this->query(json_decode($filter),$params);
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

		if(isset($this->filters[$name])){
			$filter=clone $this->filters[$name];
		} else{
			$filter=$this->query($this->queryGetter,array($name));
			//$this->filters[$name]=clone $filter;
			//var_dump($filter);

			if(count($filter)==0)
			throw new SQLException('No such Filter', 1328652428, $name);
			$filter=json_decode($filter[0]['filter_json']);
			$this->filters[$name]=clone $filter;
		}

		//var_dump($filter);
		if($params==null)
		$params=array();
		if($additional!=null) {
			foreach($additional as $key=>$val) {
				if(!isset($filter->$key)){
//var_dump($val);
				$filter->$key=$val;
				}else{
					if(is_object($val) && is_array($filter->$key)){
							$filter->{$key}[]=$val;
					}elseif(is_object($val) && is_object($filter->$key)){
						foreach($val as $key2=>$val2) {
							if(!isset($filter->$key->$key2))
								$filter->$key->$key2=$val2;
						}
					}elseif(is_array($val) && is_array($filter->$key)){
						foreach($val as $val2) {
							$filter->{$key}[]=$val2;
						}
					}
				}
			}
		}
	
//var_dump($filter);exit;
		//var_dump($filter);
		return $this->query($filter,$params);
	}


	/**
	 * returns The PDO::PARAM_*-Type for a Field
	 * @param json_object $structure The Field-Structure
	 * @throws sqlException
	 */
	protected function getPDOType($table,$field) {
		//var_dump($this->db_structure->$table,$field,$table);
		//echo "<br><br>\n\n";
		$f=$this->db_structure->$table->$field;
		//var_dump($f);echo '<br>';
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
		}
		throw new sqlException('Wrong type for query', 1325520117,$f);
	}

	/**
	 * (non-PHPdoc)
	 * @see jsonSqlBase::execUpdate()
	 */
	protected function execUpdate($update, $where=null) {
		$types=array();
		//echo "uiae";
		$table=key($update);
		$sql='UPDATE '.$table.' SET ';
		$additional='';
		$i=0;
		//echo PHP_INT_MAX;
		foreach($update[$table] as $key=>$val) {
			//echo $val.'<br>';
			if($i!=0)
			$sql.=',';
			if($val===99999998)
				$sql.=' '.$key.'='.$key.'-1 ';
			elseif($val===99999999) {
				$sql.=' '.$key.'='.$key.'+1 ';
				$additional=' ORDER BY '.$key.' DESC';
			} else {
				$sql.=' '.$key.'=?';
				switch ($val) {
				    case 'true':
				        $val=true;
				        break;
				    case 'false':
				        $val=false;
				        break;
				}
				
				$types[]=array($val, $this->getPDOType($table, $key));
			}
			++$i;
			//echo $key.':'.$val.'<br>';
		}
		if($where)
		$sql.=' WHERE '.$where;
		$sql.=$additional;

		dbg::out('query',0,$sql);
		$stmt=$this->dbh->prepare($sql);

		//var_dump($types);
		foreach($types as $i=>$type) {
			$stmt->bindValue($i+1, $type[0],$type[1]);
		}
		if($this->debug)
        	echo $sql.'<hr>';
		return $stmt->execute();
	}


	/**
	 * (non-PHPdoc)
	 * @see jsonSqlBase::getForeignId()
	 */
	protected function getForeignId($value,$alias) {
		$sql='SELECT id FROM '.$alias->foreign->table.' WHERE '.$alias->foreign->field.'=?';
		$sth=$this->dbh->prepare($sql);
		//var_dump($sth,$sql,'<br>');
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
	 * (non-PHPdoc)
	 * @see jsonSqlBase::getOrder()
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
	 * (non-PHPdoc)
	 * @see jsonSqlBase::execInsert()
	 */
	protected function execInsert($table, $fields) {
		$sql='INSERT INTO '.$table.' (';
		$values='';
		$types=array();
		$i=0;
//var_dump($fields);
		foreach($fields as $key=>$val) {
			if($i!=0) {
				$sql.=',';
				$values.=',';
			}
			$sql.=$key;
			$values.='?';
				switch ($val) {
				    case 'true':
				        $val=true;
				        break;
				    case 'false':
				        $val=false;
				        break;
				}
			//$types[]=array($this->format($val,$this->db_structure->$table->$key), $this->getPDOType($table, $key));
			//var_dump($this->getPDOType($table, $key));
			$types[]=array($val, $this->getPDOType($table, $key));
			++$i;
			//echo $key.'='.$val.'<br>';
		}

		$sql.=') VALUES ('.$values.');';
		if($this->debug)
        	echo $sql.'<hr>';
		$stmt=$this->dbh->prepare($sql);

		foreach($types as $i=>$type) {
			//var_dump($type,'<br>');
			$stmt->bindValue($i+1, $type[0],$type[1]);
		}
		$stmt->execute();
//echo $this->dbh->lastInsertId();

		return $this->dbh->lastInsertId();
	}
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
				$sql.=' LEFT JOIN '.$val->foreign->table.$tbl_alias.
				'  ON '.$val_table.'.'.$val->field.'='.$foreign_table.'.id';
			}
			return $sql;
	}
	/**
	 * (non-PHPdoc)
	 * @see jsonSqlBase::execSelect()
	 */
	protected function execSelect($what, $from,$where=null,$order=null,$group=null,$limit=null,$join=null) {
		$values=null;
		$sql='SELECT ';
		$ordered_what=array();
		$counter=false;
		foreach($what as $table=>$val) {
			foreach($val as $field) {
				//echo $field['position'];
				if($field['field']=='__count') {
					$counter=true;
					$count_where='';
					if(isset($this->db_aliases->{$field['alias']}) && isset($this->db_aliases->{$field['alias']}->where)){
						$where_object=$this->db_aliases->{$field['alias']}->where;
						$count_where=' WHERE '.$where_object->field.$where_object->op.'"'.$where_object->value.'" ';
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

					$csql=' ( SELECT COUNT(*) FROM '.$table . $cjoin . $count_where.' ' .$owhere.') AS '.$field['alias'];
//exit($csql);

					$ordered_what[$field['position']]=$csql;
					$limit=1;
				} elseif($counter==false) {
					if(isset($field['type']) && $field['type']=='count')
					$ordered_what[$field['position']]=' COUNT('.$table.'.'.$field['field'].') AS '.$field['alias'];
					else
					$ordered_what[$field['position']]=' '.$table.'.'.$field['field'].' AS '.$field['alias'];
				}
			}
		}
		
		//var_dump($ordered_what);
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
/*			if(is_array($limit))
			$sql.=' LIMIT '.intval($limit[0]).','.intval($limit[1]);
			else*/
			$sql.=' GROUP BY '.$group['table'].'.'.$group['field'];
		}
		if($order) {
			foreach($order as $val) {
				$sql.=' ORDER BY '.$val['table'].'.'.$val['field'].' '.$val['op'].' ';
			}
		}
		if($limit) {
			if(is_array($limit))
			$sql.=' LIMIT '.intval($limit[0]).','.intval($limit[1]);
			else
			$sql.=' LIMIT '.intval($limit);
		}
		//$sql.=' LIMIT 200;';
		dbg::out('query',0,$sql);
		$stmt=$this->dbh->prepare($sql);
		//if($values) {

		//}
		if($this->debug)
        	echo $sql.'<hr>';
		$stmt->execute();
		//var_dump($stmt);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	/**
	 * (non-PHPdoc)
	 * @see jsonSqlBase::execDelete()
	 */
	protected function execDelete($table, $where=null) {
		$sql='DELETE FROM '.$table;
		if($where)
		$sql.=' WHERE '.$where;
	//	echo $sql.'<br/><br/>';
		$stmt=$this->dbh->prepare($sql);
		if($this->debug)
        	echo $sql.'<hr>';
		return $stmt->execute();
	}

}
