<?php
  
    namespace Winbip;

    class DatabaseException extends \PDOException
    {
    }


    class Database 
    {
        private $host = ''; //server name- localhost or IP address
        private $db   = '';
        private $user = '';
        private $pass = '';
        private $charset = 'utf8';
        private $prevFetchStyle = array();
        private $fetchStyle = \PDO::FETCH_ASSOC;

        private $dsn = '';
        private $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
            \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ];

        public function __construct(string $server, string $databaseName, string $databaseUser, string $databasePassword) {
            $this->host = $server;
            $this->db = $databaseName;
            $this->user = $databaseUser;
            $this->pass = $databasePassword;
            $this->dsn = "mysql:host=$this->host;dbname=$this->db;$this->charset";
            $this->_setFetchStyle("assoc"); //set default fetch style.
        }
           
        public function __destruct() {}
        
        private $pdo;
        public function connect(){
            try {
                $this->pdo = new \PDO($this->dsn, $this->user, $this->pass, $this->options);
                // return $this;
             } catch (\PDOException $e) {
                throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
             }
        }
       
        public function close()
        {
            $this->pdo = null;
        }

        //Get primary key columna name
        // $sql = 'show columns from tableName where `Key` = "PRI";';
        public function getFields(string $table) {
            try {
               $sql = "SHOW COLUMNS FROM $table";
               $statement =  $this->pdo->prepare($sql) ;
               $statement->execute();
               $data = $statement->fetchAll();
               return $data;
            } catch (\PDOException $e) {
                throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
            }
        }

        public function select(string $sql, array $whereConditions = array()) {
            try {
                $statement =  $this->pdo->prepare($sql) ;
                $statement->execute($whereConditions);
                $data = $statement->fetchAll($this->fetchStyle);
                return $data;
            } catch (\PDOException $e) {
                throw new DatabaseException($e->getMessage()."SQL-" . $sql, (int) $e->getCode(), $e);
            }
        }

        /**
         * insert()
         * 
         * Insert operation.
         * 
         * @param array $params  i.e. $params["colName1"] = intValue, $params["colName2"] = "stringValue"
         * @param string $tableName  Name of the table.
         * 
         * @return integer lastInsertId
         */
        public function insert(array $params, string $tableName) {
            $keys = implode(",", array_keys($params));
            $values = ":" .implode(", :", array_keys($params));
            $sql = "INSERT INTO $tableName($keys) VALUES($values)";
            return $this->_insert($sql, $params);
        }

        public function _insert(string $sql, array $data = array()) {
            try {
               $statement =  $this->pdo->prepare($sql) ;
               $statement->execute($data);
              
               return $this->pdo->lastInsertId();
            } catch (\PDOException $e) {
                throw new DatabaseException($e->getMessage() ."SQL-" . $sql, (int) $e->getCode(), $e);
            }
        }


        /**
         * update()
         * 
         * Update table from raw SQL statement.
         * 
         * @param string $sql Raw SQL string.
         * @param array $data array of data. Optional.
         * 
         * @return int number of affected rows.
         */
        public function update(string $sql, array $data = array()) {
            try {
               $statement =  $this->pdo->prepare($sql) ;
               $statement->execute($data);
              
               return $statement->rowCount();

            } catch (\PDOException $e) {
                throw new DatabaseException($e->getMessage() ."SQL-" . $sql, (int) $e->getCode(), $e);
            }
        }

        /**
         * updateAuto()
         * 
         * @param string $tableName Table name
         *  
         * @param string $whereSQL Where SQL i.e. "conditionA=:conditionA AND conditionB>:conditionB AND .....".
         * 
         * @param array $updateParams, Optional.
         * 
         * @param array $whereParams, Optional.
         * 
         * @return integer affected rows. Return false if fails.
         */
        public function updateAuto(string $tableName, string $whereSQL, array $updateParams = array(), array $whereParams = array()) {
            try {
              
                $setSQL = "";
                foreach ($updateParams as $column => $value){
                    $setSQL .= "$column=:$column,";
                    $values[$column] = $value;
                }

                foreach ($whereParams as $column => $value){
                    $values[$column] = $value;
                }

                $setSQL = rtrim($setSQL, ",");
                $sql = "UPDATE $tableName SET $setSQL WHERE $whereSQL";


                $statement =  $this->pdo->prepare($sql) ;
                $statement->execute($values);
              
                return $statement->rowCount();

            } catch (\PDOException $e) {
                throw new DatabaseException($e->getMessage() ."SQL-" . $sql, (int) $e->getCode(), $e);
            }
        }


        public function delete(string $sql, array $data = array()) {
            try {
               $statement =  $this->pdo->prepare($sql) ;
               $statement->execute($data);
              
               return $statement->rowCount();
               
            } catch (\PDOException $e) {
                throw new DatabaseException($e->getMessage() ."SQL-" . $sql, (int) $e->getCode(), $e);
            }
        }

        #region FETCH TYPE
        private function _setFetchStyle($fetchStyle = "assoc"){
            $count = count($this->prevFetchStyle);
            if($count == 0){
                $this->prevFetchStyle[] = $fetchStyle; //if there is ne element, add new one.
            }
            else{
                $lastFetchStyle = $this->prevFetchStyle[$count-1];
                if($lastFetchStyle != $fetchStyle){
                    $this->prevFetchStyle[] = $fetchStyle;  //if prev style is not the same, add new one.
                }
            }
            
           
            switch ($fetchStyle) {
                //Fetch Associative Array
                case 'assoc':
                    $this->fetchStyle = \PDO::FETCH_ASSOC;
                    break;
                
                //Fetch Array of Objects
                case 'class':
                    $this->fetchStyle = \PDO::FETCH_CLASS;
                    break;
                
                case 'object':
                    $this->fetchStyle = \PDO::FETCH_OBJ;
                    break;
                
                //Fetch Numeric Array
                case 'number':
                    $this->fetchStyle = \PDO::FETCH_NUM;
                    break;
                
                //Fetch Single Column as Array Variable
                case 'column':
                    $this->fetchStyle = \PDO::FETCH_COLUMN;
                    break;
                
                case 'class_type':
                    $this->fetchStyle = \PDO::FETCH_CLASSTYPE;
                    break;
                
                //Fetch Object, Associative and Numeric Array Lazily
                case 'lazy':
                    $this->fetchStyle = \PDO::FETCH_LAZY;
                    break;
                
                //Fetch Into Existing Class
                case 'into':
                    $this->fetchStyle = \PDO::FETCH_INTO;
                    break;

                //Only for fetch_all method
                //Fetch Key/Value Pair
                case 'key_pair':
                    $this->fetchStyle = \PDO::FETCH_KEY_PAIR;
                    break;
                
                //Fetch Key/Value Pair Array
                case 'unique':
                    $this->fetchStyle = \PDO::FETCH_UNIQUE;
                    break;
                
                //Fetch in Groups
                case 'group':
                    $this->fetchStyle = \PDO::FETCH_GROUP;
                    break;
                
                //Fetch in Groups, One Column
                case 'group_column':
                    $this->fetchStyle = \PDO::FETCH_GROUP | \PDO::FETCH_COLUMN;
                    break;
                
                //Fetch in Groups, Object Arrays
                case 'group_class':
                    $this->fetchStyle = \PDO::FETCH_GROUP | \PDO::FETCH_CLASS;
                    break;
                
                default:
                    $this->fetchStyle = \PDO::FETCH_ASSOC;
                    break;
            }
            // print_r($this->fetchStyle);
        }

       
        public function backToPrevFetchStyle(){
            $count = count($this->prevFetchStyle);
            if($count == 0){
                //do nthing
            }
            else{
                if($count == 1){
                    // echo $array[0];
                    $this->_setFetchStyle($this->prevFetchStyle[0]);
                }
                else{
                    // echo $array[$count-2];
                    $this->_setFetchStyle($this->prevFetchStyle[$count-2]);
                }
            }
        }

        public function fetchAsAssoc(){
            $this->_setFetchStyle("assoc");
            return $this;
        }
        
        // public function fetchAsClass(){
        //     $this->_setFetchStyle('class');
        //     return $this;
        // }

        public function fetchAsObject(){
            $this->_setFetchStyle('object');
            return $this;
        }

        public function fetchAsNumber(){
            $this->_setFetchStyle('number');
            return $this;
        }

        public function fetchAsColumn(){
            $this->_setFetchStyle('column');
            return $this;
        }

        // public function fetchAsClassType(){
        //     $this->_setFetchStyle('class_type');
        //     return $this;
        // }

        // public function fetchAsLazy(){
        //     $this->_setFetchStyle('lazy');
        //     return $this;
        // }

        // public function fetchAsInto(){
        //     $this->_setFetchStyle('into');
        //     return $this;
        // }

        // public function fetchAsKeyPair(){
        //     $this->_setFetchStyle('key_pair');
        //     return $this;
        // }

        // public function fetchAsUnique(){
        //     $this->_setFetchStyle('unique');
        //     return $this;
        // }

        // public function fetchAsGroup(){
        //     $this->_setFetchStyle('group');
        //     return $this;
        // }

        // public function fetchAsGroupColumn(){
        //     $this->_setFetchStyle('group_column');
        //     return $this;
        // }

        // public function fetchAsGroupClass(){
        //     $this->_setFetchStyle('group_class');
        //     return $this;
        // }

        #endregion

        #region transaction methods
            /**
             * @return bool Returns true on success or false on failure.
             */
            public function beginTransaction():bool
            {
                /* Begin a transaction, turning off autocommit */
                return $this->pdo->beginTransaction();
            }

            /**
             * @return bool Returns true on success or false on failure.
             */
            public function commit():bool
            {
                return $this->pdo->commit();
                /* Database connection is now back in autocommit mode */
            }

            /**
             * 
             * @return bool
             */
            public function rollBack()
            {
                if($this->pdo->inTransaction())
                    return $this->pdo->rollBack();
                else
                    false;
            }

            /**
             * Checks if inside a transaction.
             * 
             * @return bool
             */
            public function inTransaction():bool
            {
                return $this->pdo->inTransaction();
            }
        #endregion

    }

?>