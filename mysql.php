<?php
/**
 * Prosta klasa do zarządzania bazą danych.
 * Pomocna przy wykonywaniu operacji na 1 tabeli.
 * Wspiera instrukcje: select, insert, update, delete.
 * Klasa zapewnia podstawową ochronę przed SQL-Injection.
 * 
 * Licencja: Open Source
 *
 * @author          Krzysztof Sikora
 * @link            http://ultra91.linuxpl.info Strona domowa autora
 * @version         v0.3
 * @created         20.04.2013
 * @last-modified   10.10.2013
 *
 * @TODO Operacje na kilku tabelach, ewentualnie przepisanie na PDO. 
 * @TODO 3 tryby debug: none, echo, exception
 * @TODO zwracanie obiektów w zapytaniu select
 * @TODO różne id
 * @TODO selectCursos i metoda next()
 */
class MysqlKs {
	// Parametry połączenia z bazą danych
	private $dbMysql = '';
	private $dbBase = '';
	private $dbLogin = '';
	private $dbPassword = '';
	private $dbKodowanie = 'utf-8';
	
	
	private static $instance;
	private $debugMode = ($_SERVER["HTTP_HOST"] == "localhost");
	private $db;
	private $zapytanie;
	private static $licznik = 0;

	
	private function __construct() {
		// Utworzenie nowego połączenia
		@$this->db = new mysqli($this->dbMysql, $this->dbLogin, $this->dbPassword, $this->dbBase);
		
		// Wyświetlenie komunikatu w przypadku błędy połączenia z bazą danych.
		if ($this->db->connect_errno) {
			echo "Połączenie z bazą danych nieudane.";
			exit;
		}
		
		// Ustawienie kodowania
		$this->setCharset($this->dbKodowanie);
	}
	
	/**
	 * Klasa to klasyczny przykład sigletona. Poniższa metoda służy do utworzenia obiektu.
	 * Przykład:
	 * $db = MysqlKs::getInstance();
	 */
	public static function getInstance() {
		if (is_null(self::$instance)) {
			self::$instance = new MysqlKs();
		}
		return self::$instance;
	}
	
	/**
	 * Wyświetla ostatnie zapytanie.
	 * Przykład:
	 * $uzytkownicy = $db->selectFull("uzytkownicy");
	 * // wyświetl zapytanie
	 * echo $db;
	 * Wynik: SELECT * FROM uzytkownicy
	 */
	public function __toString() {
		return $this->zapytanie;
	}
	
	/**
	 * Zwraca ostatni identyfikator wstawiony do bazy
	 * Przykład:
	 * $db->insert("wojewodztwa", "Lubelskie", "nazwa");
	 * $lastInsertedId = $db->lastId();
	 */
	public function lastId() {
		return $this->db->insert_id;
	}

	/**
	 * Ustawia kodowanie znaków
	 * Przykład: $db->setCharset('utf-8');
	 */
	public function setCharset($names) {
		$this->db->set_charset($names);
	}
	
	/** 
	 * Zwraca liczbę zmodyfikowanych rekordów przez ostatnie zapytanie
	 * Wynik tej metody jest zwracany przez metody: insert, update, updateById, delete, deleteById
	 * Przykład:
	 * if ($db->deleteById("uzytkownicy", 5)
	 *     echo "Usunięto użytkownika o id = 5";
	 * else
	 * 	   echo "Nie udało się usunąć użytkownika :(";
	 */
	public function getAffectedRows() {
		return $this->db->affected_rows;
	}
	
	/**
	 * Zabezpieczenie przed SQL-Injection
	 * Wszystkie wartości przekazywane przez użytkowników przechodzą przez tą metodę
	 * W razie potrzeby można samemu użyć tej metody
	 * Przykład:
	 * $string = $db->escape($string);
	 */
	public function escape($str) {
		if (is_null($str)) return $str;
		if ($this->isExpr($str)) {
			$str["value"] = $this->db->real_escape_string($str["value"]);
			return $str;
		}
		return $this->db->real_escape_string($str);
	}
	
	/**
	 * Określa początek transakcji
	 * Przykład:
	 * $db->start();
	 * ... // kilka operacji na bazie danych
	 * $db->commit();
	 */
	public function start() {
		$this->query("START TRANSACTION");
	}
	
	/**
	 * Określa koniec transakcji
	 * Przykład:
	 * Patrz wyżej metoda start();
	 */
	public function commit() {
		$this->db->commit();
	}
	
	/**
	 * Domyślnie wszystkie wartości przekazywane do metod są traktowane jako łąńcuchy znaków i otaczane znakami '
	 * Kiedy zachodzi potrzeba przekazania innej wartości np funkcji sql jak np. NOW() należy użyć poniższej metody
	 * Wartość null nie musi być opakowywana przez poniższą metode
	 * Przykład
	 * $db->insert("tabela", array(null, $db->expr("NOW()"), "jakaś nazwa"), array("id, "czas", "nazwa"));
	 */
	public function expr($val) {
		return array('value' => $val, 'isExpr' => true);
	}
	
	/** 
	 * Zwraca liczbę zapytań wykonanych do tej pory.
	 */
	public function getQueryCount() {
		return $this->licznik;
	}
	
	/**
	 * Metoda sprawdza czy wartość jest wyrażeniem
	 * @see expr()
	 */
	private function isExpr($val) {
		if (!is_array($val)) return false;
		if (!isset($val["isExpr"])) return false;
		return ($val["isExpr"]);
	}
	
	/**
	 * Metoda pobiera wartość z wyrażenia
	 * @see expr()
	 * @see isExpr()
	 */
	private function exprGetValue($val) {
		return $val["value"];
	}
	
	/**
	 * Metoda zwraca wynik zapytania, wykorzystywana jest głównie wewnętrznie przez klasę.
	 * Można jej użyć do zapytań których nie można uzyskać przez metody klasy np.
	 * Wielo tabelowego zapytania insert
	 * W przypadku wielo tabelowych zapytań SELECT należy korzystać z metod: selectUser() i selectFullUser()
	 * Przykład:
	 * $zapytanie = "UPDATE uzytkownicy, firmy SET firmy.nazwa = 'Marex Co.' 
	 *		WHERE uzytkownicy.firma = firmy.id AND uzytkownicy.id = 5";
	 * $db->query($zapytanie);
	 */
	public function query($zapytanie) {
		// zwiększenie licznika zapytań
		// @see getQueryCount()
		self::$licznik++;
		
		// zapisanie aktualnego zapytania
		// @see __toSring()
		$this->zapytanie = $zapytanie;
		
		$tmp = $this->db->query($zapytanie);
		
		if ($this->debugMode && $this->db->errno) {
			throw new MysqlKsException("Błąd w zapytaniu: {$this->db->error}");
		} else {
			return $tmp;
		}
	}
	
	/**
	 * Metoda zwraca POJEDYNCZY wiersz z tabeli.
	 * @param $tabela - tabela
	 * @param $wartosci - wartości jakie mamy zwrócić * oznacza wszystko, wartości podajemy w postaci tablicy
	 * np array("id", "imie") pojedynczych wartości nie trzeba umieszczać w tablicy np: "imie"
	 * @param $dodatki - dodatki dodawane na koniec zapytania np: "WHERE imie='adam'"
	 * @return pojedynczy wiersz tabeli, gdy wybieramy pojedynczą wartość np $wartosci = "id" zwraca pojedynczą wartość 
	 * Tabela uzytkownicy wykorzystywana do przykładów
	 * ____________________________
	 * |  id  |  imie  | nazwisko |
	 * |--------------------------|
	 * |   1  | marek  |   kos    |
	 * |   2  | jarek  |   kot    |
	 * |   3  | darek  | kowalski |
	 * |--------------------------|
	 * Przykłady:
	 * $uzytkownik = $db->select("uzytkownicy", "*", "WHERE imie='marek'");
	 * print_r($uzytkownik);
	 * Wynik:
	 * Array
	 *(
	 *	[id] => 1
	 *	[imie] => marek
	 *	[nazwisko] => kos
	 *)
	 *
	 * $uzytkownik = $db->select("uzytkownicy", array("id", "imie"), "WHERE imie='marek'");
	 * print_r($uzytkownik);
	 * Wynik:
	 * Array
	 *(
	 *	[id] => 1
	 *	[imie] => marek
	 *)
	 *	 
	 * $uzytkownik = $db->select("uzytkownicy", "id", "WHERE imie='marek'");
	 * print_r($uzytkownik);
	 * Wynik:
	 * 1
	 */
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
	
	/**
	 * @TODO rozne ID
	 */
	public function selectId($tabela, $id, $wartosci = "*") {
		$id = (int)$id;
		$dodatki = "WHERE id=$id";
		return $this->select($tabela, $wartosci, $dodatki);
	}
	
	/**
	 * Metoda zwraca wszystkie pasujący rekordy w postaci tabeli indeksowanej
	 * Parametry identyczne jak w przypadku metody select()
	 * Od metody select() różni się tylko tym, że zwraca wszystkie rekordy a nie pojedynczy wiersz
	 * Przykłady: 
	 * $uzytkownicy = $db->selectFull("uzytkownicy");
	 * print_r($uzytkownicy);
	 * Wynik: 
	 *Array
	 *(
	 *	[0] => Array
     *   (
     *       [id] => 1
     *      [imie] => marek
     *       [nazwisko] => kos
     *   )
	 *
     *[1] => Array
     *   (
     *       [id] => 2
     *       [imie] => jarek
     *       [nazwisko] => kot
     *   )
	 *
     *[2] => Array
     *   (
     *       [id] => 3
     *       [imie] => darek
     *       [nazwisko] => kowalski
     *   )
	 *
	 *)
	 * $uzytkownicy = $db->selectFull("uzytkownicy", array("id", "imie"));
	 * print_r($uzytkownicy);
	 * Wynik: 
	 *Array
	 *(
	 *	[0] => Array
     *   (
     *       [id] => 1
     *      [imie] => marek
     *   )
	 *
     *[1] => Array
     *   (
     *       [id] => 2
     *       [imie] => jarek
     *   )
	 *
     *[2] => Array
     *   (
     *       [id] => 3
     *       [imie] => darek
     *   )
	 *
	 *)
	 * $uzytkownicy = $db->selectFull("uzytkownicy", array("id", "imie"), "WHERE id < 3");
	 * print_r($uzytkownicy);
	 * Wynik: 
	 *Array
	 *(
	 *	[0] => Array
     *   (
     *       [id] => 1
     *      [imie] => marek
     *   )
	 *
     *[1] => Array
     *   (
     *       [id] => 2
     *       [imie] => jarek
     *   )
	 *
	 *)
	 */
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
	
	/**
	 * Metoda zwraca POJEDYNCZY wynik z zapytania napisanego przez uzytkownika
	 * Przykład:
	 * $zapytanie = "SELECT uzytkownicy.*, firmy.id WHERE uzytkownicy.firma = firma.id AND firma.id=4";
	 * $uzytkownicy = $db->selectUser($zapytanie);
	 */
	public function selectUser($zapytanie) {
		$wynik = $this->query($zapytanie);
		return $wynik->fetch_assoc();
	}
	
	/**
	 * Metoda zwraca wszystkie wyniki w postaci tabeli indeksowanej z zapytania napisanego przez uzytkownika
	 * Różni się od metody selectUser() tylko liczbą zwracanych wierszy
	 * Przykład:
	 * $zapytanie = "SELECT uzytkownicy.*, firmy.id WHERE uzytkownicy.firma = firma.id";
	 * $uzytkownicy = $db->selectFullUser($zapytanie);
	 */
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
	
	public function updateById($tabela, $wartosci, $pola , $id) {
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
	
	public function deleteById($tabela, $id) {
		$id = (int)$id;
		return $this->delete($tabela, $id, 'id');
	}
}
$db = MysqlKs::getInstance();
?>