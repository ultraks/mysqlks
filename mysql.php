<?php
/**
 * Klasa do zarządzania bazą danych.
 *
 * @author	 		Krzysztof Sikora
 * @link 			http://ultra91.linuxpl.info Strona domowa autora
 * @version 		0.3
 * @created 		20.04.2013
 * @last-modified	10.10.2013
 */
class MysqlKs {
	private static $instance;
	private $debugMode = ($_SERVER["HTTP_HOST"] == "localhost");
	
	private $dbMysql = '';
	private $dbBase = '';
	private $dbLogin = '';
	private $dbPassword = '';
	private $dbKodowanie = 'utf-8';
	private $db;
	private $zapytanie;
	public static $licznik = 0;

	
	private function __construct() {
		
		@$this->db = new mysqli($this->dbMysql, $this->dbLogin, $this->dbPassword, $this->dbBase);
		
		if ($this->db->connect_errno) {
			echo "Połączenie z bazą danych nieudane.";
			exit;
		}
		
		$this->setCharset($this->dbKodowanie);
	}
	
	public static function getInstance() {
		if (is_null(self::$instance)) {
			self::$instance = new MysqlKs();
		}
		return self::$instance;
	}
	
	public function __toString() {
		return $this->zapytanie;
	}
	
	public function lastId() {
		return $this->db->insert_id;
	}
	
	public function setCharset($names) {
		$this->db->set_charset($names);
	}
	
	public function getAffectedRows() {
		return $this->db->affected_rows;
	}
	
	public function escape($str) {
		if (is_null($str)) return $str;
		if ($this->isExpr($str)) {
			$str["value"] = $this->db->real_escape_string($str["value"]);
			return $str;
		}
		return $this->db->real_escape_string($str);
	}
	
	public function start() {
		$this->query("START TRANSACTION");
	}
	
	public function commit() {
		$this->db->commit();
	}
	
	public function expr($val) {
		return array('value' => $val, 'isExpr' => true);
	}
	
	private function isExpr($val) {
		if (!is_array($val)) return false;
		if (!isset($val["isExpr"])) return false;
		return ($val["isExpr"]);
	}
	
	private function exprGetValue($val) {
		return $val["value"];
	}
	
	public function query($zapytanie) {
		self::$licznik++;
		$this->zapytanie = $zapytanie;
		
		$tmp = $this->db->query($zapytanie);
		
		if ($this->debugMode && $this->db->errno) {
			throw new MysqlKsException("Błąd w zapytaniu: {$this->db->error}");
		} else {
			return $tmp;
		}
	}
	
	public function select($tabela, $wartosci = "*", $dodatki = "") {
		
		$wynik = $this->selectFull($tabela, $wartosci, $dodatki);
		if ($wynik) {
			// jeśli tylko 1 pole było szukane nie zwracamy całej tablicy asocjacyjnej
			// tylko pojedynczą wartość;
			if (count($wynik[0]) == 1) {
				$tmp = array_keys($wynik[0]);
				return $wynik[0][$tmp[0]];
			} 
			return $wynik[0];
		}
		return null;
	}
	
	public function selectId($tabela, $id, $wartosci = "*") {
		$id = (int)$id;
		$dodatki = "WHERE id=$id";
		return $this->select($tabela, $wartosci, $dodatki);
	}
	
	public function selectFull($tabela, $wartosci = "*", $dodatki = "") {
		$zapytanie = "SELECT ";
	
		if (!is_array($wartosci)) $wartosci = array($wartosci);
		//$wartosci = array_map(array($this, 'escape'), $wartosci);
		
		for ($i = 0; $i < count($wartosci); $i++) {
			if ($this->isExpr($wartosci[$i])) 
				$zapytanie .= $this->exprGetValue($wartosci[$i]).", ";
			else 
				$zapytanie .= "$wartosci[$i], ";
		}
		$zapytanie = rtrim($zapytanie, ", ");
		
		$zapytanie .= " FROM $tabela $dodatki";
	
		return $this->selectFullUser($zapytanie);
	}
	
	public function selectUser($zapytanie) {
		$wynik = $this->query($zapytanie);
		return $wynik->fetch_assoc();
	}
	
	public function selectFullUser($zapytanie) {
		$wynik = $this->query($zapytanie);
		$wynik2;
		$wynik3 = array();
		while ($wynik2 = $wynik->fetch_assoc()) {
			$wynik3[] = $wynik2;
		}
		return $wynik3;
	}

	public function insert($tabela, $wartosci, $pola = null) {
		$zapytanie = "INSERT INTO $tabela ";
		
		if (!is_array($wartosci)) $wartosci = array($wartosci);
		$wartosci = array_map(array($this, 'escape'), $wartosci);
		
		// insert nie pełny
		if ($pola) {
			if (!is_array($pola)) $pola = array($pola);
			$zapytanie .= "(";
			
			for ($i = 0; $i < count($pola); $i++) {
				$zapytanie .= "$pola[$i], ";
			}
			
			$zapytanie = rtrim($zapytanie, ", ");
			$zapytanie .= ") ";
		}
		$zapytanie .= "VALUES (";
		//$pola = array_map(array($this, 'escape'), $pola);
		
		for ($i = 0; $i < count($wartosci); $i++) {
			if (is_null($wartosci[$i])) $zapytanie .= "NULL, ";
			elseif ($this->isExpr($wartosci[$i])) $zapytanie .= $this->exprGetValue($wartosci[$i]).", ";
			else $zapytanie .= "'$wartosci[$i]', ";
		}
		$zapytanie = rtrim($zapytanie, ", ");
		$zapytanie .= ")";

		$wynik = $this->query($zapytanie);
		return $this->getAffectedRows();
	}
	
	public function update($tabela, $wartosci, $pola, $dodatki = '') {
		$zapytanie = "UPDATE $tabela SET ";
	
		if (!is_array($pola)) {
			$pola = array($pola);
			$wartosci = array($wartosci);
		}
		//$pola = array_map(array($this, 'escape'), $pola);
		$wartosci = array_map(array($this, 'escape'), $wartosci);
		
		for ($i = 0; $i < count($pola); $i++) {
			if (is_null($wartosci[$i])) $zapytanie .= "$pola[$i] = NULL, ";
			elseif ($this->isExpr($wartosci[$i])) $zapytanie .= "$pola[$i] = "
					.$this->exprGetValue($wartosci[$i]).", ";
			else $zapytanie .= "$pola[$i] = '$wartosci[$i]', ";
			
		}
		$zapytanie = rtrim($zapytanie, ", ");
	
		$zapytanie .= " $dodatki" ;
	
		$wynik = $this->query($zapytanie);
		return $this->getAffectedRows();
	}
	
	public function updateId($tabela, $wartosci, $pola , $id) {
		$id = (int)$id;
		$dodatki = "WHERE id = ".$id;

		return $this->update($tabela, $wartosci, $pola, $dodatki);
	}
	
	public function delete($tabela, $wartosci, $pola) {
		$zapytanie = "DELETE FROM $tabela WHERE ";
	
		if (!is_array($pola)) {
			$pola = array($pola);
			$wartosci = array($wartosci);
		}

		$wartosci = array_map(array($this, 'escape'), $wartosci);
		//$pola = array_map(array($this, 'escape'), $pola);
		
		for ($i = 0; $i < count($pola); $i++) {
			if (is_null($wartosci[$i])) $zapytanie .= "$pola[$i] = NULL AND ";
			elseif ($this->isExpr($wartosci[$i])) $zapytanie .= "$pola[$i] = "
					.$this->exprGetValue($wartosci[$i])." AND ";
			else $zapytanie .= "$pola[$i] = '$wartosci[$i]' AND ";
			
		}
		$zapytanie = rtrim($zapytanie, " AND ");
	
		$wynik = $this->query($zapytanie);
		return $this->getAffectedRows();
	}
	
	public function deleteId($tabela, $id) {
		$id = (int)$id;
		return $this->delete($tabela, $id, 'id');
	}
}
$db = MysqlKs::getInstance();
?>