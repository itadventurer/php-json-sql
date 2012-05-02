<?php
abstract class jsonSqlBase {
	/**
	 * The Database-Structure
	 * @var json_object
	 */
	protected $db_structure;
	/**
	 * The aliases of Table/Field-names
	 * @var json_object
	 */
	protected $db_aliases;
	/**
	 * The allowed Operators for getWhere()
	 * @var array
	 */
	protected $allowed_op;

	/**
	 * The allowed Order-operators
	 * @var array
	 */
	protected $allowed_order;
    /**
     *  Debuging aktiv?
     * @var type boolean
     */
    protected $debug;
	/**
	* Firephp-Objekt
	*/
	protected $firephp;
	/**
	 * Preparing everything
	 * @param string $db_structure
	 * @param string $db_aliases
	 */
	public function __construct($db_structure,$db_aliases) {
		$this->db_structure=json_decode($db_structure);
		$this->db_aliases=json_decode($db_aliases);
		$this->debug=false;
	}
	/*
	 *Enable/disable Debuging(Shows Sql-Query, needs firephp)
	 * @param bool $input Enable/disable
	 * @param string $path Path to firephp lib
	 */
	public function set_debug($input,$path){
		$this->debug=$input;
		if($this->debug=true){
			require_once($path);
			ob_start();
			$this->firephp = FirePHP::getInstance(true);
		}	
	}
	/**
	 * Creates a new Query from a json_object
	 * @param json_object $in The input-object
	 * @throws sqlException
	 */
	public function query($in,$params=null) {
		global $dbh;
		$ret=null;
		//Check $in
		if(!is_string($in->type)) {
			if(is_array($in)) {
				foreach($in as $val) {
					$ret[]=$this->query($val);
				}
			}else  {
				throw new sqlException("Syntax Error",1324225145);
			}
		} else {
			switch($in->type) {
				case "insert":
					return $this->insert($in,$params);
					break;
				case "select":
					return $this->select($in,$params);
					break;
				case "count":
					return $this->count($in,$params);
					break;
				case "update":
					return $this->update($in,$params);
					break;
				case "delete":
					return $this->delete($in,$params);
					break;
			}
		}
	}
	/**
	 * Executes the query
	 * @param string $query The query-string
	 */
	//protected  abstract function exec($query);
	/**
	 * Formatiert einen String anhand eines formates
	 * @param mixed $string
	 * @param char $f p:DB-Bezeichner (Tabellennamen, etc.)
	 * string,s: String (Varchar, Text,....)
	 * float,f: float
	 * int,i: int
	 * bool,boolean,b: bool
	 */
	protected function format($string,$f,$param=false) {
		
		if(is_object($f)) {
			$f=$f->type;
		} elseif(is_array($f)) {
			if($f[0]=='foreign')
			$f='int';
			else
			$f=$f[0];
		} elseif(!is_string($f)) {
			var_dump($string,$f,$param,"<br>");
			throw new sqlException('Wrong type', 1325077369,$f);
		}


		switch($f) {
			case 'p':
				return preg_replace("%[^A-Za-z0-9_]%siU","",$string);
				break;
			case 'string':
			case 's':
			case 'date':
			case 'datetime':
			case 'text':
				$string=str_replace(
				array("\x00","\n","\r","\\","'","\"","\x1a"),
				array("\\x00","\\n","\\r","\\\\","\\'","\\\"","\\x1a")
				,$string);
				if($param)
				return '"'.$string.'"';
				return $string;
				break;
			case 'float':
			case 'f':
				return floatval($string);
				break;
			case 'int':
			case 'i':
			case 'id':
				return intval($string);
				break;
			case 'bool':
			case 'boolean':
			case 'b':
				if($string===true || $string=='true')
				return 'true';
				return 'false';
				break;
			case 'json':
				return json_encode($string);
				break;
		}
	}

	/**
	 * Gibt ein Key/Value-Pair
	 * @param json_object $params Das JSON-Objekt für die Insert-Abfrage
	 * @param array $insert_params Die einzufügenden Daten
	 * @throws sqlException
	 */
	protected function getInsert($params,$insert_params=null) {
		$insert=array();
		foreach($params as $key=>$val) {
			if($key=='type')
			continue;
			if(!isset($this->db_aliases->$key))
			throw new sqlException('No such alias', 1325435040,$key);
			$alias=$this->db_aliases->$key;
			
			//var_dump($alias,"<br><br>\n\n");
			if($val=='?') {
/*
			if($value=='?') {
				$p_key=key($insert_params);
				$value=$insert_params[$p_key];
				unset($insert_params[$p_key]);
			}*/
				if($insert_params!=null && count($insert_params)>0) {
					$p_key=key($insert_params);
					$val=$insert_params[$p_key];
					unset($insert_params[$p_key]);
				}
			}
			
			
			
			if(isset($alias->foreign)) {
				$value=$this->getForeignId($val,$alias);
				//echo $value;
			} else {
				$value=$val;
			}
			
			//var_dump($alias,"<hr>");
			if(($value==='--' || $value==='++') && ($this->db_structure->{$alias->table}->{$alias->field}='int' || $this->db_structure->{$alias->table}->{$alias->field}='id'))
			$insert[$alias->table][$alias->field]=$value;
			else
			$insert[$alias->table][$alias->field]=$this->format($value,$this->db_structure->{$alias->table}->{$alias->field});
			//echo $insert[$alias->table][$alias->field].'<br>';
			
		}
		return $insert;
	}
	/**
	 * Erstellt aus einem JSON-Objekt eine SQL-Insert-Abfrage
	 * @param json_object $params {"_alias_":"value"}
	 * @throws sqlException
	 */
	protected function insert($params,$insert_params) {
		$insert=$this->getInsert($params,$insert_params);
//var_dump($insert);
		foreach($insert as $key=>$value) {
			return intval($this->execInsert($key, $value));
		}
	}
	/**
	 * Führt eine Insert-Abfrage aus
	 * @param string $table Tabelle
	 * @param array $fields Felder (Key-Value Pair)
	 */
	protected abstract function execInsert($table,$fields);

	/**
	 * Gets a foreign id for a input-value of a foreign table (select || input)
	 * @param json_object $value The input-value
	 * @param json_object $alias The alias-Object
	 */
	protected abstract function getForeignId($value,$alias);
	/**
	 * Helper function for select()
	 * @param json_object $pwhat Which field you want to select?
	 * @throws sqlException
	 * @return array The fields wich you want to select
	 */
	protected function getWhat($pwhat) {
		$what=array();
		$join=array();
		$new_alias=array();
			$position=0;
		foreach($pwhat as $val_alias) {
			$val_obj=false;
			if(is_object($val_alias)) {
				$val=$val_alias->alias;
				$val_obj=true;
			} else {
				$val=$val_alias;
				//$new_alias[$val]=$val;
			}
			if(!isset($this->db_aliases->$val))
			throw new sqlException('no such db-alias', 1325074427,$val);
			$alias=$this->db_aliases->$val;
			if(!isset($what[$alias->table]))
			$what[$alias->table]=array();
			if(!in_array($alias->field,$what[$alias->table])) {
				if($val_obj) {
					$what[$val_alias->from][]=array('field'=>$alias->foreign->field,'alias'=>$val,'position'=>$position);
					++$position;
					
					if(!isset($join[$val_alias->alias])) {
						$alias->alias=$val_alias->from;
						if(isset($val_alias->via))
						$alias->via=$val_alias->via;
						$join[$alias->alias]=$alias;
						$new_alias[$val_alias->alias]=$val_alias->from;
					}
					continue;
				}
				if(!isset($alias->foreign)) {
				$what[$alias->table][]=array('field'=>$alias->field,'alias'=>$val,'position'=>$position);
					++$position;
				}else {
					if(!isset($what[$alias->foreign->table]))
					$what[$alias->foreign->table]=array();
					if(!in_array($alias->foreign->field,$what[$alias->foreign->table])) {
						$what[$alias->foreign->table][]=array('field'=>$alias->foreign->field,'alias'=>$val,'position'=>$position);
						++$position;
					}
					$join[]=$alias;
				}

			}
		}
		//var_dump($what,"<br><br>\n\n");
		return array($what,$join,$new_alias);
	}
	/**
	 * Helper function for select()
	 * @param json_object $pwhere where-params
	 * @throws sqlException
	 * @return array the query-params
	 */
	protected function getWhere($pwhere,$where_params=null,$new_alias=null,$join=null) {
		$where='';
		$from=array();
		foreach($pwhere as $val) {
			$where.=' ';
//var_dump($val);
			if(isset($val->field)) {

				$field=$val->field;
				$op=@$val->op;
				$value=$val->value;
				if(!isset($this->db_aliases->$field))
				throw new sqlException('No such db-alias', 1325075468,$field);
				$alias=$this->db_aliases->$field;
				$alias_table=$alias->table;
				$alias_field=$alias->field;
				if(!in_array($alias_table,$from))
				$from[]=$alias_table;

				//Operator
				$op=strtoupper($op);
				if(isset($alias->foreign)) {
					if($new_alias==null || !isset($new_alias[$field])){
					$where.=$alias->foreign->table.'.'.$alias->foreign->field;
						$alias_table=$alias->foreign->table;
						$alias_field=$alias->foreign->field;
					}else {
						//var_dump($new_alias);
						$alias_table=$alias->foreign->table;
						$alias_field=$alias->foreign->field;
						$where.=$new_alias[$field].'.'.$alias->foreign->field;
					}
				}else {
				$where.=$alias_table.'.'.$alias_field;
				}
				$where.=' '.$op.' ';

				//Value format
				if($value==='?') {
					dbg::out('where_params', 0,$where_params);
					$p_key=key($where_params);
					$value=$where_params[$p_key];
					unset($where_params[$p_key]);
				}
				//var_dump($alias_field,$value,$this->db_structure->$alias_table->$alias_field,'<br><br>');
				$value=$this->format($value,$this->db_structure->$alias_table->$alias_field,true);
				switch($op) {
					case '=':
					case '<=>':
					case '<>':
					case '!=':
					case '<=':
					case '<':
					case '>=':
					case '>':
						$where.=$value;
						break;
					case 'IS':
					case 'IS NOT':
						$value=str_replace('"','',strtoupper($value));
						$allowed_values=array('TRUE','FALSE','NULL');
						if(!in_array($value, $allowed_values))
						throw new sqlException('wrong is-value', 1325077665,$value);
						$where.=$value;
						break;
					case 'BETWEEN':
						$where.=$this->format($value,$this->db_structure->$alias_table->$alias_field,true)
						.' AND '.$this->format($value2,$this->db_structure->$alias_table->$alias_field,true);
						break;
					case 'LIKE':
						$where.=$value;
						break;
					case 'IN':
					case 'NOT IN':
					    $where.='(';
						$in=0;
						foreach($val->value as $inval) {
							if($in!=0)
						    $where.=', ';
							$where.=$this->format($inval,$this->db_structure->$alias_table->$alias_field,true);
							++$in;
						}
					    $where.=')';
					    break;
					
				}
			} else {
				
			//var_dump($val);
				$allowed_ops=array('AND','OR','NOT','(',')');
				if(!in_array($val->op,$allowed_ops)) {
					throw new sqlException('wrong op', 1325078411,$val->op);
				}
				$where.=$val->op;
			}
		}
		return array($where,$join);
	}

	/**
	 * returns an order-by-String
	 * @param json_object $porder The Order-Param
	 * @throws sqlException
	 */
	protected abstract function getOrder($porder);

	/**
	 * Erstellt aus einem JSON-Objekt eine Select-Abfrage
	 * @param json_object $params
	 * {"what":["_alias_"...],
	 * 	"where":[
	 * 	{
	 * 		"field":"_alias_",
	 * 		"op":"_operator_", //'=','<>','<','>'...
	 * 		"value":"_value_"
	 * 	},
	 * 	{ "op":"_op"},...
	 * 	],
	 *  "order":{
	 *  "_alias_":"op",...
	 *  },
	 *  "limit":1
	 * }
	 * @throws sqlException
	 */
	protected function select($params,$select_params,$return=false) {
		//SELECT $what FROM $from [WHERE $where] [ORDER BY $order] [LIMIT $limit]
		$what=array();
		$from=array();
		$where=null;
		$order=null;
		$limit=isset($params->limit) ? $params->limit : null;

		$pwhat=$params->what;
		if(!is_array($pwhat))
		throw new sqlException('params->what should be an array', 1325074338);

		//$what
		$temp=$this->getWhat($pwhat);
		$what=$temp[0];
		$join=$temp[1];
		$new_alias=$temp[2];
		foreach($what as $key=>$val) {
			if(!in_array($key,$from))
			$from[]=$key;
		}
		//var_dump($join);

		//[$where]
		if(isset($params->where)) {
			$temp=$this->getWhere($params->where,$select_params,$new_alias,$join);
			$where=$temp[0];
			$join=$temp[1];
		}

		//Order
		if(isset($params->order) && is_object($params->order)) {
			foreach($params->order as $key=>$val) {
				$val=strtoupper($val);
				if(!in_array($val, $this->allowed_order))
				throw new sqlException('No such order', 1325083418,$val);
				if(!isset($this->db_aliases->$key))
				throw new sqlException('No such alias', 1325083415,$key);
  
				$alias=$this->db_aliases->$key;
				$alias_table=$alias->table;
				$alias_field=$alias->field;

				if(isset($alias->foreign)) {
					if($new_alias==null || !isset($new_alias[$key])){
					//$where.=$alias->foreign->table.'.'.$alias->foreign->field;
				$order[]=array('table'=>$alias->foreign->table,'field'=>$alias->foreign->field,'op'=>$val);
					}else {
						$order[]=array('table'=>$new_alias[$key],'field'=>$alias->foreign->field,'op'=>$val);
					}
				}else {
					$order[]=array('table'=>$alias->table,'field'=>$alias->field,'op'=>$val);
				}
			}
		}
		//Group
		$group=null;
		if(isset($params->group)) {
			if(!isset($this->db_aliases->{$params->group}))
				throw new sqlException('No such alias', 1325083415,$key);
			$alias=$this->db_aliases->{$params->group};

				$alias_table=$alias->table;
				$alias_field=$alias->field;

				if(isset($alias->foreign)) {
					if($new_alias==null || !isset($new_alias[$key])){
						$group=array('table'=>$alias->foreign->table,'field'=>$alias->foreign->field);
					}else {
						$group=array('table'=>$new_alias[$params->group],'field'=>$alias->foreign->field);
					}
				}else {
					$group=array('table'=>$alias->table,'field'=>$alias->field);
				}
		}
		if($return)
			return array(
				'what'=>$what,
				'from'=>$from,
				'where'=>$where,
				'order'=>$order,
				'group'=>$group,
				'limit'=>$limit,
				'join'=>$join
			);
		else
		return $this->execSelect($what, $from,$where,$order,$group,$limit,$join);
	}
	private  function count($params,$select_params) {
		$params=$this->select($params,$select_params,true);
		$params['what'][key($params['what'])][0]['type']='count';
//		var_dump($params);
		return $this->execSelect($params['what'], $params['from'], $params['where'], $params['order'], $params['group'], $params['limit'], $params['join']);
	}
	/**
	 * Create and execute the select-query
	 * @param array $what
	 * @param array $from
	 * @param array $where
	 * @param aray $order
	 * @param array $limit
	 * @param array $join
	 */
	protected abstract function execSelect($what,$from,$where=null,$order=null,$group=null,$limit=null,$join=null);
	/**
	 * Creates an update-query
	 * @param json_object $params The input-param
	 */
	protected function update($params,$update_params) {
//var_dump($update_params);
		/*echo "<br><br>\n\n";
		 var_dump($params,$update_params);
		 echo "<br><br>\n\n";/**/
		if(!isset($update_params['update']))
		$update_params=$update_params[key($update_params)];
		if(!isset($params->update))
		throw new sqlException('Nothing to update', 1325688162);
		$update=$this->getInsert($params->update,@$update_params['update']);
		if(count($update)!=1)
		throw new sqlException('You can update only one table a time!', 1325688732);
		if(isset($params->where)) {
			//var_dump($params->where);
			$temp=$this->getWhere($params->where,@$update_params['where']);
			$where=$temp[0];
		} else $where=null;
		return $this->execUpdate($update, $where);
	}
	/**
	 * Führt eine Update-Abfrage aus
	 * @param array $update Update-Teil
	 * @param array $where Where-Teil
	 */
	protected abstract function execUpdate($update,$where=null);
	/**
	 * Creates an delete-query
	 * @param json_object $params The input-param
	 */
	protected function delete($params,$delete_params){
		if(!isset($params->table) || !isset($this->db_structure->{$params->table}))
		throw new sqlException('No such table', 1325689698,isset($params->table)?$params->table:'no table given');

		//var_dump($delete_params);
		if(isset($params->where)) {
			$temp=$this->getWhere($params->where,$delete_params);
			$where=$temp[0];
		} else $where=null;
		return $this->execDelete($params->table, $where);
	}
	protected abstract function execDelete($table,$where=null);

	/**
	 * Holt die Label-Eigenschaft eines Aliases
	 * @param String $alias Alias-Name
	 * @return null|string
	 */
	public function getLabel($alias) {
		if(isset($this->db_aliases->$alias->label)) {
			return $this->db_aliases->$alias->label;
		}
		return null;
	}
	/**
	 * Existiert ein Alias unter dem Namen?
	 * @param string $alias
	 */
	public function existAlias($alias) {
		return isset($this->db_aliases->$alias);
	}
	public function getType($alias_name,$foreign=false) {
		if(isset($this->db_aliases->$alias_name)) {
			$alias=$this->db_aliases->$alias_name;
			if(isset($alias->foreign)){
				if($foreign===true){
					return $alias->foreign;
				}
				$alias=$alias->foreign;
			}
			return $this->db_structure->{$alias->table}->{$alias->field};
		}
		return null;
	}
	public function getAliasForeign($alias){
		if(isset($this->db_aliases->$alias_name)) {
			return $this->db_aliases->$alias_name->foreign;
		}
		return null;
	}
}
