<?php
/**
 *
 * PHPMagic Models Abstract Model Class
 * 
 * @author Jeffrey L. Roberts (Jeffrey.L.Roberts@gmail.com)
 * @package PHPMagic\Models\Model
 * @version 0.0.1
 * @copyright 2014-Present The PHP Magic Group
 *
 */

namespace PHPMagic\Models;

abstract class Model {

    // ----------------------------
    // Define Default Table Columns (Public Variables)
    // ----------------------------

    // The row auto_increment primary key
    public $id = array('integer', '0');

    // The row creation date
    public $created = array('DateTime', '1970 01 01');

    // The row creation date
    public $modified = array('DateTime', '1970 01 01');

    // The row creation date
    public $deleted = array('DateTime', '1970 01 01');

    //
    // ----------------------------

    // ----------------------------
    // Define Class Private Variables
    // ----------------------------

    // Class scoped ReflectionClass object
    private $reflectionClass;

    // Array to store the class scoped model columns(public variables) in
    private $columns = array();

    // Array to store the column names and types in for table creation
    private $columnTypes = array();

    // The parent model table name
    private $tableName;

    // The primary key to populate and/or update by
    private $primaryKey;

    // The foreign key to populate and/or update by
    private $foreignKeys = array();

    // The key definition value
    private $populateKeyDefinition;

    //
    // ----------------------------

    public function setPrimaryKey($value) {
        $this->primaryKey = $value;
    }

    public function setForeignKey($value) {
        $this->foreignKey = $value;
    }

    public function setPopulateKeyDefinitionValue($value) {
        $this->populateKeyDefinition = $value;
    }

    // Declare the Abstract Model Class Contructor
    public function __construct() {

        // Instatiate the column types array
        $this->columnTypes = array();

        // Instatiate the Class Scoped ReflectionClass
        $this->reflectionClass = new ReflectionClass($this);

        // Get the current models variables (columns)
        $tempColumns = $this->reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC);

        // Interate columns and set them as new objects
        foreach($tempColumns as $column) {

            // Get the column type from reflection property object
            $columnTypeArray = $column->getValue($this);
            $columnType = $columnTypeArray[0];

            // Get the column name from the reflection property object
            $columnName = $column->getName();

            if($columnType == 'array') {

                continue;
            }

            // Assign the column name as a key to the column types array with a value of the column type
            $this->columnTypes[$columnName] = $columnType;

            // Set the type for the column
            $this->setColumnType($columnType, $columnName);

            // Get the default value for the column type
            $default = $this->getDefault($columnType);

            // If default does not equal null
            if($default !== null) {

                // Set the default value for the column name
                $this->{$columnName} = $default;
            }
            else {

                // Else unset the column name
                // (Likely a bad idea as it destroys the column type, and to be removed soon)
                unset($this->{$columnName});
            }
        }

        // Get the current models variables (columns)
        $this->columns = $this->reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC);

        // Assign the model class name to the private table name variable
        $this->tableName = strtolower($this->reflectionClass->getName());

        // Trim table name to the last part of the class name
        $this->tableName = substr($this->tableName, strrpos($this->tableName, '_', -0) + 1);

        // Notify the user that the construct finished
        debug('Core Model Construct Finishded.');
    }

    // ----------------------------
    // Define Default Table Methods
    // ----------------------------

    // Insert the model into the database
    public function insert($child) {

        // Instantiate the column values array
        $columnValues = array();

        // Iterate the columns (variables) and grab their values storing them in an array
        foreach($this->columns as $column) {
            $columnValues[$column->getName()] = $column->getValue($child);
        }

        // Start the insert query string
        $insertQuery = 'INSERT INTO ' . $this->tableName;

        // Start the insert query columns string
        $insertQueryColumns = '(';

        // Start the insert query values string
        $insertQueryValues = 'VALUES(';

        // Build the insert columns string
        foreach($columnValues as $columnValueKey => $columnValue) {

            // If column name is id then continue since its the primary key
            if($columnValueKey == 'id') {
                continue;
            }

            if(is_array($columnValue)) {
                continue;
            }

            // Add the current column to the insert columns string
            $insertQueryColumns .= $columnValueKey . ',';

            // MySQLi escape the current column value
            $columnValue = BotCenter_Libs_Mysql::cleanValue(BotCenter_Libs_Mysql::$writeConnection, $columnValue);

            switch($columnValueKey) {
                case 'created':
                    // Add the current value to the insert values string
                    $insertQueryValues .= 'NOW(),';
                    break;
                case 'user_id':
                    // Add the current value to the insert values string
                    $insertQueryValues .= '\'' . BotCenter_Framework_Core_Global::$user->id . '\',';
                    break;
                default:
                    // Add the current value to the insert values string
                    $insertQueryValues .= '\'' . $columnValue . '\',';
                    break;
            }
        }

        // Strip the last comma from the insert columns string and finalize with closing parenthesis
        $insertQueryColumns = substr($insertQueryColumns, 0, strlen($insertQueryColumns) - 1) . ')';

        // Strip the last comma from the insert values string and finalize with closing parenthesis
        $insertQueryValues = substr($insertQueryValues, 0, strlen($insertQueryValues) - 1) . ')';

        // Finish building the insert query
        $insertQuery .= $insertQueryColumns . ' ' . $insertQueryValues;

        // Insert the query into the database
        $this->id = BotCenter_Libs_Mysql::insert($insertQuery, $child);
    }

    // Populate the model by the specified column
    public function populate($params = array(), $child) {

        // Notify the user that the populate method began firing
        debug('Populate method firing...');

        // Check to make sure the specified column model class variable to populate by is isset, filled, and not null
        if(
            !isset($this->populateKeyDefinition) ||
            !isset($this->{$this->populateKeyDefinition}) ||
            $this->{$this->populateKeyDefinition} == '' ||
            $this->{$this->populateKeyDefinition} == null
        ) {

            BotCenter_Libs_Error::reportError(
            // Error message
                'Specified Model Column To Populate By populateKeyDefinition is Empty, Null or Not Set',
                // Class error occurred in
                __CLASS__,
                // Method error occurred in
                __METHOD__,
                // This Line Number
                __LINE__,
                // Fatal error
                true
            );
        }

        // Create the select query variable
        $selectQuery = 'SELECT ';

        // Build the select query using the model class columns(variables) array
        foreach($this->columns as $targetColumn) {

            if(is_array($targetColumn->getValue($this))) {
                continue;
            }

            $selectQuery .= $targetColumn->getName() . ',';
        }

        // Remove trailing comma from select query
        $selectQuery = substr($selectQuery, 0, strlen($selectQuery) - 1);

        // Add a space to the end of the select query after the columns
        $selectQuery .= ' ';

        // Finish building the generic select query
        $selectQuery .= 'FROM ' . $this->tableName . ' WHERE ' . $this->populateKeyDefinition . '=\'' . $this->{$this->populateKeyDefinition} . '\'';

        // If params array is not empty, then add additional options to select query
        if(count($params) > 0) {

            // Iterate additional parameters, check viability, add to select query or throw error
            foreach($params as $paramKey => $paramValue) {

                // Check to make sure the param value is set, not empty, and not null
                if(!isset($paramValue) || $paramValue == '' || $paramValue == null) {
                    BotCenter_Libs_Error::reportError(
                    // Error message
                        'Select param value can not be not set, empty string, or null',
                        // Class error occurred in
                        __CLASS__,
                        // Method error occurred in
                        __METHOD__,
                        // This Line Number
                        __LINE__,
                        // Fatal error
                        true
                    );
                }

                // Create boolean to insure we only add viable mysql commands to the query
                $viableMysqlCommand = false;

                // Switch param key to ensure it is a viable mysql command
                switch(strtolower($paramKey)) {
                    case 'limit':
                        $viableMysqlCommand = true;
                        break;
                    case 'order by':
                        $viableMysqlCommand = true;
                        break;
                }

                // Check to make sure we found a viable mysql command
                if($viableMysqlCommand == false) {

                    // No viable mysql command found, throw error
                    BotCenter_Libs_Error::reportError(
                    // Error message
                        'Invalid MySQL Command Found In Additional Populate Parameters',
                        // Class error occurred in
                        __CLASS__,
                        // Method error occurred in
                        __METHOD__,
                        // This Line Number
                        __LINE__,
                        // Fatal error
                        true
                    );
                }

                $selectQuery .= ' ' . $paramKey . ' ' . $paramValue;
            } // End foreach of additional parameters
        }

        // Execute mysql query on finalized select query, add true to return array
        $selectResultsArray = BotCenter_Libs_Mysql::select($selectQuery, $this);

        if(count($selectResultsArray) == 0) {
            return false;
        }

        // Iterate the array and populate the parent model
        if(isset($selectResultsArray[0]) && is_array($selectResultsArray[0]))
        {
            $selectResultsArray = $selectResultsArray[0];
        }

        foreach($selectResultsArray as $selectResultKey => $selectResultValue) {

            // Switch the result key for core model columns
            switch($selectResultKey) {
                case 'id':
                    $child->id = $selectResultValue;
                    break;
                case 'created':
                    $this->created = $selectResultValue;
                    break;
                default:
                    $child->{$selectResultKey} = $selectResultValue;
                    break;
            }
        }

        // Notify the user that the populate method began firing
        debug('Populate method finished firing...');

        return true;
    }

    public function update($child) {

        $updateQuery = 'UPDATE ' . $this->tableName . ' SET ';

        // Build the select query using the model class columns(variables) array
        foreach($this->columns as $targetColumn) {
            $updateQuery .= $targetColumn->getName() . '=\'' . mysql_real_escape_string($targetColumn->getValue($child)) . '\',';
        }

        // Remove trailing comma from select query
        $updateQuery = substr($updateQuery, 0, strlen($updateQuery) - 1);

        $updateQuery .= ' WHERE id=\'' . $this->id . '\'';

        BotCenter_Libs_Mysql::writeQuery($updateQuery, $this);
    }

    // Set the column type for the column name
    private function setColumnType($columnType, $columnName) {

        // Set a variable with an acceptable column type
        // Set Column Type

        switch($columnType) {
            case "string":
            case "integer":
            case "int":
            case "float":
            case "boolean":
            case "bool":
            case "object":
            case "array":
                unset($this->{$columnName});
                settype($this->{$columnName}, $columnType);
                break;
            default:
                $this->{$columnName} = new $columnType();
                break;
        }
    }

    // Get the default value for the column
    private function getDefault($columnType) {
        // Create var to hold default value
        $default = null;

        // Set Default Values
        switch($columnType) {
            case "string":
                $default = '';
                break;
            case "integer":
            case "int":
                $default = 0;
                break;
            case "float":
                $default = 0.0;
                break;
            case "bool":
            case "boolean":
                $default = false;
                break;
            case "object":
                $default = null;
                break;
            case "array":
                $default = array();
                break;
            case "DateTime":
                $default = 'NOW()';
                break;
        }

        return $default;
    }

    // Return columns to mysql class for error processing
    public function getColumns() {
        return $this->columnTypes;
    }

    // Return table name to mysql class for error processing
    public function getTableName() {
        return $this->tableName;
    }
}