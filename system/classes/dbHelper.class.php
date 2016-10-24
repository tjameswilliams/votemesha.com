<?
/**
* dbHelper class
*
* @author Tim Williams
*/
class dbHelper {

  private $mysqli;

  public $insert_id;

  public function __construct($db,$user,$pass,$host = 'localhost') {
    $this->mysqli = new mysqli($host, $user, $pass, $db);
    if ($this->mysqli->connect_errno) {
      throw new Exception($mysqli->connect_error);
    }
  }

  /**
  * __call function
  *
  * Simply runs the SQL of the 'method' called, simply add a new query to create a new method
  * any method with 'get' will return database results
  * @return bool if method is NOT get, array if it is
  * @author Tim Williams
  */
  public function __call($method, $args)
  {
    if( method_exists($this,$method) ) // -> real methods override ephemeral methods
    return call_user_func_array(array($this, $method), $args);
    if( !in_array($method,array_keys($this->SQL)) )
    throw( new Exception(__CLASS__." :: Method does not exist: ".$method));
    // --> deflate json
    $pdo_vals = [];
    if( !empty($args) )
    {
      foreach( $args as $key => &$value) {
        if( is_array($value) || is_object($value) )
        {
          $value = json_encode($value);
        }
        if( is_integer($value) ) {
          $pdo_vals[] = ['i',$value];
        } elseif ( is_string($value) || is_null($value) ) {
          $pdo_vals[] = ['s',$value];
        } elseif ( is_double($value) ) {
          $pdo_vals[] = ['d',$value];
        }
      }
    }
    $get_results = strpos($method,'get') !== false ? true : false;
    if( property_exists( $this, 'SQL_PREPS' ) )
    {
      $SQL = $this->_prepSql($method);
    }
    else
    {
      $SQL = $this->SQL[$method];
    }

    if (!($stmt = $this->mysqli->prepare($SQL))) {
      echo "Prepare failed: (" . $this->mysqli->errno . ") " . $this->mysqli->error;
    }

    if( !empty($pdo_vals) ) {
      //var_dump($pdo_vals);
      if( count($pdo_vals) == 1 ) {
        if (!$stmt->bind_param($pdo_vals[0][0],$pdo_vals[0][1])) {
          echo "Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
        }
      } else {
        $vals = [implode('',array_column($pdo_vals,0))];
        foreach( $pdo_vals as $val ) {
          $vals[] = $val[1];
        }
        call_user_func_array(array($stmt, 'bind_param'), $this->_refValues($vals));
      }

      // if (!$stmt->bind_param(implode('',array_column($pdo_vals,0)),array_column($pdo_vals,1))) {
      //   echo "Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
      // }
    }

    $result = $stmt->execute();
    if($stmt === false) {
      trigger_error('Wrong SQL: ' . $sql . ' Error: ' . $this->mysqli->errno . ' ' . $this->mysqli->error, E_USER_ERROR);
    }

    // --> inflate json
    if( strpos($method,'get') !== false )
    {
      $result = $stmt->get_result();
      $data = [];
      if( $result ) {
        while($row = $result->fetch_assoc() ) {
          $data[] = $row;
        }
        $result =  $this->_inflateJson($data);
      } else {
        $result = [];
      }
      if( !empty($result) && strpos(strtolower($method),'single') !== false ) {
        $result = $result[0];
      }
    }
    else
    {
      $this->insert_id = $this->mysqli->insert_id;

    }
    return $result;
  }
  function _inflateJson($results)
  {
    if( !empty($results) )
    {
      $inflated = array();
      foreach($results as $result)
      {
        array_walk($result, function(&$value,$key) {
          if( strpos($key,'json') !== false )
          {
            $value = json_decode($value);
          }
        });
        $inflated[] = $result;
      }
    }
    return isset($inflated) ? $inflated : $results;
  }
  /**
  * prepSql function
  *
  * @param  string  $sql_stmt  the SQL to prep
  *
  * @return string, prepped SQL statement
  * @author Tim Williams
  */
  function _prepSql($sql_stmt)
  {
    return str_replace( array_keys($this->SQL_PREPS), array_values($this->SQL_PREPS), $this->SQL[$sql_stmt] );
  }
  function _refValues($arr){
    if (strnatcmp(phpversion(),'5.3') >= 0) //Reference is required for PHP 5.3+
    {
      $refs = array();
      foreach($arr as $key => $value)
      $refs[$key] = &$arr[$key];
      return $refs;
    }
    return $arr;
  }

}
