<?php


/*
 * 
 * AWSNinja Core Package
 * http://www.awsninja.com
 * 
 * Database Helper Class
 *  
 */


class Db {
	
	private $pdo;
	private $prepStmts;
	
	private static $instance;
	public static function instance()
	{
		if (!isset(self::$instance))
		{
			self::$instance = new Db();
		}
		return self::$instance;
	}
	
	private function __construct()
	{
		$this->pdo = new PDO( DB_CONNECTION_STRING, DB_USERNAME, DB_PASSWORD);
		$this->prepStmts = array();
	}
	
	public function executeInsertStatement($sql, $vals)
	{
		if (!isset($this->prepStmt[$sql]))
		{
			$stmt = $this->pdo->prepare($sql);
			$this->prepStmt[$sql] = $stmt;
		}
		else
		{
			$stmt = $this->prepStmt[$sql];
		}
		$stmt->execute($vals);
		$res = $stmt->fetchall();
		$id = $this->pdo->lastInsertId();
		return $id;
	}
	
	public function executeSelectStatement($sql, $vals)
	{
		if (!isset($this->prepStmt[$sql]))
		{
			$stmt = $this->pdo->prepare($sql);
			$this->prepStmt[$sql] = $stmt;
		}
		else
		{
			$stmt = $this->prepStmt[$sql];
		}
		$stmt->execute($vals);
		$res = $stmt->fetchall();
		return $res;
	}
	
}





?>