<?php

/*********************************************************************
/*## Portal class extends mysqli */
class php_mysqli_crud extends mysqli {

	public function __construct(){
			
		//OR SUPPLY OTHER CONNECTION INFO
		$DBHost 		= "localhost";
		$DBUser			= "user";
		$DBPass			= "pass";
		
		//SELECT THE DB
		$databaseName 	= "INFORMATION_SCHEMA";
		
		
		@parent::__construct($DBHost, $DBUser, $DBPass, $databaseName);
   		 // check if connect errno is set
   		 
   		//IF THE CONNECTION DOES NOT WORK - REDIRECT TO OUR "DB DOWN" PAGE, BUT PASS THE URL TO THE APPLICATION
   		 if (mysqli_connect_error()) {
    		trigger_error(mysqli_connect_error(), E_USER_WARNING);
		 }
	
	}
	
	/*** QUERY ******************************************************************
	/*## CreateReadUpdateDelete QUERY FUNCTION */
	public function run_query($query, $type, $params)
	{  

		if ($stmt = parent::prepare($query)){
			//IF PARAMS ARE NOT EMPTY, BIND
			if($params){
				$ref    = new ReflectionClass('mysqli_stmt'); 
				$method = $ref->getMethod("bind_param"); 
				$method->invokeArgs($stmt,$params);
			}
			if(!$stmt->execute()){
				list($callee, $caller) = debug_backtrace(false);
				$calling_function = " <b>Function:</b> ".$caller['function']." <b>Line:</b> ".$callee['line'];
				trigger_error($this->error.$calling_function, E_USER_WARNING);
			} 
			if(in_array($type, array('info','list'))){
				//FOR SELECT INFO AND LIST TYPES GET META DATA
				$meta = $stmt->result_metadata();
				//BUILD RETURN ARRAY
				while ( $field = $meta->fetch_field() ) {
					$parameters[] = &$row[$field->name];
				}
			
				call_user_func_array(array($stmt, 'bind_result'), $parameters);
			}
			switch($type){
				case 'list':
					//FOR INFO (SINGLE ARRAY)
					while($stmt->fetch()){
						$x = array();
						foreach( $row as $key => $val ) {
							$x[$key] = $val;
						}
						$results[] = $x;
					}
				break;
				case 'info':
					//FOR LIST (MULTI ARRAY)
					$stmt->fetch();
					$x = array();
					foreach( $row as $key => $val ) {
						$results[$key] = $val;
					}
				break;
				case 'insert':
					$results = $this->insert_id;
				break;
				case 'update':	
				break;
				case 'delete':
				break;
			}
			//RETURN DATA AND CLOSE
			return $results;	
			$stmt->close();
		}//END PREPARE
		else{
			list($callee, $caller) = debug_backtrace(false);
			$calling_function = " <b>Function:</b> ".$caller['function']." <b>Line:</b> ".$callee['line'];
			trigger_error($this->error.$calling_function, E_USER_WARNING);
		} 
	}
	
	/*** LIST ******************************************************************
	/*## List all data */
	public function generate_crud($db,$table)
	{
	
		$query ="
			SELECT *
			FROM COLUMNS
			WHERE TABLE_SCHEMA='$db' 
			    AND TABLE_NAME='$table';";
						 
			if ($stmt = parent::prepare($query)){
				if(!$stmt->execute()){trigger_error($this->error, E_USER_NOTICE);} 
				$meta = $stmt->result_metadata();
	
			   	while ( $field = $meta->fetch_field() ) {
			     	$parameters[] = &$row[$field->name];
			   	}
			
			   call_user_func_array(array($stmt, 'bind_result'), $parameters);
			
			      while ( $stmt->fetch() ) {
				      $x = array();
				      foreach( $row as $key => $val ) {
				         $x[$key] = $val;
				      }
				      $results[] = $x;
				   }	   
			
			   return $results;
			   $stmt->close();
			}//END PREPARE
			else{trigger_error($this->error, E_USER_NOTICE);} 		
	}
	
	/*** LIST ******************************************************************
	/*## LIST */
	public function output_crud($db,$table){	
		
		$results = $this->generate_crud($db,$table);
		$rcount = count($results);
		ob_start();
echo '
	/*** '.strtoupper($table).' LIST ******************************************************************
	/*## LIST */
	public function '.$table.'_list(){	
		$query = "
		SELECT 
			*	
		FROM 
			'.$table.'";

		$result = $this->run_query($query, "list", $params);
		return $result;
	}
	
	/*** '.strtoupper($table).' INFO ******************************************************************
	/*## INFO */
	public function '.$table.'_info($id){	
		$query = "
		SELECT 
			*	
		FROM 
			'.$table.'
		WHERE
			'.$results[0]['COLUMN_NAME'].' = ?";

		$params = array("i",&$id);
		$result = $this->run_query($query, "info", $params);
		return $result;
	}
	
	/*** '.strtoupper($table).' INSERT ******************************************************************
	/*## INSERT */
	public function '.$table.'_insert(';for($x=1;$x<$rcount;$x++){if($x > 1){echo ",";}echo '$'.$results[$x]['COLUMN_NAME']; } echo' ){	
		$query = "
		INSERT INTO '.$table.'
			(
			';
			for($x=1;$x<$rcount;$x++){if($x > 1){echo ",\n\t\t\t";}echo $results[$x]['COLUMN_NAME'];}
			echo ')	
		VALUES
			('; for($x=1;$x<$rcount;$x++){if($x > 1){echo ",";}echo "?";}
			echo ')";

		$params = array("';for($x=1;$x<$rcount;$x++){if($x > 0){echo "";}if($results[$x]['DATA_TYPE'] =="int"){echo "i";}else{echo "s";} }
		echo'",';
		for($x=1;$x<$rcount;$x++){if($x > 1){echo ",";}echo '&$'.$results[$x]['COLUMN_NAME']; } 
		echo ');
		$result = $this->run_query($query, "insert", $params);
		return $result;
	}
	
	/*** '.strtoupper($table).' UPDATE ******************************************************************
	/*## UPDATE */
	public function '.$table.'_update(';for($x=0;$x<$rcount;$x++){if($x > 0){echo ",";}echo '$'.$results[$x]['COLUMN_NAME']; } echo'){	
		$query = "
		UPDATE '.$table.' SET 
			'; for($x=1;$x<$rcount;$x++){if($x > 1){echo ",\n\t\t\t";}echo $results[$x]['COLUMN_NAME']." = ?";}	
		echo "\n\t\t"; echo 'WHERE
			'.$results[0]['COLUMN_NAME'].' = ?";

		$params = array("';for($x=1;$x<$rcount;$x++){if($x > 1){echo "";}if($results[$x]['DATA_TYPE'] =="int"){echo "i";}else{echo "s";} }
		echo'i",';
		for($x=1;$x<$rcount;$x++){if($x > 1){echo ",";}echo '&$'.$results[$x]['COLUMN_NAME']; } 
		echo ',&$'.$results[0]['COLUMN_NAME']; echo ');
		$result = $this->run_query($query, "update", $params);
		return $result;
	}
	
	/*** '.strtoupper($table).' DELETE ******************************************************************
	/*## DELETE */
	public function '.$table.'_delete($id){	
		$query = "
		DELETE FROM 
			'.$table.'
		WHERE
			'.$results[0]['COLUMN_NAME'].' = ?";
		$params = array("i",&$id);
		$result = $this->run_query($query, "delete", $params);
		return $result;
	}

';
		
		
		$output = ob_get_clean();
		
		$output = "<pre>".$output."</pre>";
				
		return $output;
	}
	
}//END CLASS