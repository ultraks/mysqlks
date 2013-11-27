<?php
/**
 * Klasa wyjątków wyrzuconych przez klasę MysqlKs.
 */
class MysqlKsException extends Exception {}
/**
 * Prosta klasa do zarządzania bazą danych.
 * Pomocna przy wykonywaniu operacji na 1 tabeli.
 * Wspiera instrukcje: select, insert, update, delete.
 * Klasa zapewnia podstawową ochronę przed SQL-Injection.
 * Klasa to singleton po którym nie można dziedziczyć dlatego
 * wszelkie zmiany są możliwe przez zmiane kodu klasy a nie
 * przez dziedziczenie.
 * Klasa przeznaczona dla małych projektów gdzie nie chcemy
 * odpalać żadnego ORMa czy innej abstrakcji baz danych
 * lub gdy nie wiemy co ORM lub abstrakcja baz danych.
 * 
 * Dodatkowe przykłady oprócz tu zamieszczonych w kodzie
 * znajdują się w pliku example.php
 * 
 *
 * @author          Krzysztof Sikora
 * @link            http://ultra91.linuxpl.info Strona domowa autora
 * @license         Open Source
 * @version         v0.4
 * @since           20.04.2013
 * @last-modified   21.11.2013
 *
 * @TODO Operacje na kilku tabelach, ewentualnie przepisanie na PDO.
 */
class MysqlKs {
	// Parametry połączenia z bazą danych.
	// Można je wpisać tutaj albo podać przy tworzeniu egzemplarza klasy
	// @see getInstance()
	private $dbHost = '';
	private $dbBase = '';
	private $dbLogin = '';
	private $dbPassword = '';
	private $dbKodowanie = 'utf-8';
	
	// Zmienne do ustawienia przez użytkownika
	private $debugMode = self::ECHO_MODE;
	private $fetchType = self::FETCH_ASSOC;
	// Jest to domyślna nazwa dla klucza głównego tabeli
	// @see setId()
	private $defaultId = "id";
	
	// Komunikaty
	private $komunikatBladPolaczenia = "Połączenie z bazą danych nieudane.";
	private $komunikatNiepodanoDanychPolaczenia = "Nie podano danych do połączenia.";
	private $komunikatNiekompletneDanePolaczenia = "Nie podano wszystkich parametrów połączenia.";
	private $komunikatZleId = "Niepoprawny identyfikowator";
	private $komunikatNieMaCursora = "Niepoprawne wywołanie metody next(), powinno być
			poprzedzone wywołaniem metody selectCursor(), selectCursorUser() lub next().";
	// %error% - zostanie zamienione na błąd zapytania
	// %errno% - numer błedu zapytania
	private $komunikatZleZapytanie = "Błąd w zapytaniu: %error%";
	
	// Tryby debugowania
	// @see setDebugMode
	const NONE = 0;
	const ECHO_MODE = 1;
	const EXCEPTION_MODE = 2;
	const AUTO_MODE = 3;
	
	// Typy wartości zwracanych przez polecenia SELECT
	// @see setFetchType
	const FETCH_ASSOC = 0;
	const FETCH_OBJECT = 1;
	
	// Zmienne klasowe
	private static $instance;
	private $db;
	private $zapytanie;
	private static $licznik = 0;
	private $cursor = null;

	
	/**
	 * Ustanaowienie połączenia z bazą i ustawienie kodowania.
	 */
	private function __construct($dbHost = null, $dbLogin = null, $dbPassword = null, $dbBase = null) {
		// Czy podano parametry połączenia
		if ($dbHost != null) {
			// Sprawdzamy czy podano wszystkie dane do połączenia
			if ($dbLogin == null || $dbPassword === null || $dbBase == null) {
				showError($this->komunikatNiekompletneDanePolaczenia);
				return;
			// Podano wszystkie dane do połączenia
			} else {
				// Zapisujemy dane do połączenia w zmiennych klasy, aby przy kolejnym
				// połączeniu przy braku podania danych do połączenia 
				// móc skorzystać z poprzednich
				$this->dbHost = $dbHost;
				$this->dbLogin = $dbLogin;
				$this->dbPassword = $dbPassword;
				$this->dbBase = $dbBase;
			}
		}
		
		
		// Utworzenie nowego połączenia
		@$this->db = new mysqli($this->dbHost, $this->dbLogin, $this->dbPassword, $this->dbBase);
		
		// Wyświetlenie komunikatu w przypadku błędy połączenia z bazą danych.
		if ($this->db->connect_errno) {
			echo $this->komunikatBladPolaczenia;
			exit;
		}
		
		// Ustawienie kodowania
		$this->setCharset($this->dbKodowanie);
		
		// Ustawienie trybu debugowania
		$this->setDebugMode();
	}
	
	/**
	 * Klasa to klasyczny przykład sigletona. Poniższa metoda służy do utworzenia obiektu.
	 * Można przekazać do metody parametry połączenia. Można to zrobić jednorazowo.
	 * Jeżeli nie podamy parametrów połączenia to korzystamy z parametrów podanych na 
	 * sztywno w klasie albo z poprzednich parametrów połączenia.
	 * 
	 * @exmaple $db = MysqlKs::getInstance('localhost', 'root', 'pass123', 'jakas_baza');
	 * @example $db = MysqlKs::getInstance();
	 */
	public static function getInstance($dbHost = null, $dbLogin = null, $dbPassword = null, $dbBase = null) {
		// Czy podano parametry połączenia
		if ($dbHost != null) {	
				self::$instance = new MysqlKs($dbHost, $dbLogin, $dbPassword, $dbBase);			
		} elseif (is_null(self::$instance)) {
			// Jeżeli nie ma jakiejś danej do połączenia
			if (!$this->dbBase || !$this->dbLogin || $this->dbPassword === null || !$this->dbBase) {
				showError($this->komunikatNiekompletneDanePolaczenia);
				return;
			}
			
			self::$instance = new MysqlKs();
		}
		return self::$instance;
	}
	
	/**
	 * Ustaw host bazy danych.
	 * @see getInstance()
	 * @see getDbHost()
	 */
	public function setDbHost($dbHost) {
		$this->dbHost = $dbHost;
	}

	/**
	 * Ustaw login bazy danych.
	 * @see getInstance()
	 * @see getDbLogin()
	 */
	public function setDbLogin($dbLogin) {
		$this->dbLogin = $dbLogin;
	}

	/**
	 * Ustaw hasło bazy danych.
	 * @see getInstance()
	 * @see getDbPassword()
	 */
	public function setDbPassword($dbPassword) {
		$this->dbPassword = $dbPassword;
	}

	/**
	 * Ustaw baze danych.
	 * @see getInstance()
	 * @see getDbBase()
	 */
	public function setDbBase($dbBase) {
		$this->dbBase = $dbBase;
	}
	
	/**
	 * Pobierz host bazy danych.
	 * @see getInstance()
	 * @see setDbHost()
	 */
	public function getDbHost() {
		return $this->dbHost;
	}
	
	/**
	 * Pobierz login bazy danych.
	 * @see getInstance()
	 * @see setDbLogin()
	 */
	public function getDbLogin() {
		return $this->dbLogin;
	}

	/**
	 * Pobierz hasło bazy danych.
	 * @see getInstance()
	 * @see setDbPassword()
	 */
	public function getDbPassword() {
		return $this->dbPassword;
	}

	/**
	 * Pobierz baze danych.
	 * @see getInstance()
	 * @see setDbBase()
	 */
	public function getDbBase() {
		return $this->dbBase;
	}
	
	/**
	 * Wyświetla ostatnie zapytanie.
	 * @example
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
	 * @example
	 * $db->insert("wojewodztwa", "Lubelskie", "nazwa");
	 * $lastInsertedId = $db->lastId();
	 */
	public function lastId() {
		return $this->db->insert_id;
	}

	/**
	 * Ustawia kodowanie znaków
	 * @example $db->setCharset('utf8');
	 */
	public function setCharset($names) {
		$this->db->set_charset($names);
	}
	
	/**
	 * Ustawienie tryby zwracania danych przez polecenia SELECT
	 * Dostępne 2 tryby: MysqlKs::FETCH_ASSOC - zwraca tablicę asocjacyjną (tryb domyślny)
	 * MysqlKd::FETCH_OBJECT - zwraca obiekt
	 * 
	 * @example $db->setFetchType(MysqlKs::FETCH_OBJECT);
	 */
	public function setFetchType($type) {
		$this->fetchType = $type;
	}
	
	/**
	 * @see setFetchType($type)
	 * @return typ zwracanych wartości przez polecenia select
	 */
	public function getFetchType() {
		return $this->fetchType;
	}
	
	/**
	 * Ustaw tryb debugowania
	 * Dostępne 4 tryby: NONE - brak, ECHO_MODE - debugowanie przez instrukcje echo,
	 * EXCEPTION_MODE - wyrzucanie wyjątków typu MysqlKsException, 
	 * AUTO_MODE - tryb ECHO_MODE na localhost i tryb NONE na serwerze produkcyjnym (tryb domyślny)
	 * @param $type - tryb debugowania
	 * @example setDebugMode(MysklKs::EXCEPTION_MODE)
	 */
	public function setDebugMode($type = self::AUTO_MODE) {
		if ($type == self::AUTO_MODE) {
			if (($_SERVER["HTTP_HOST"] == "localhost")) {
				$this->debugMode = self::ECHO_MODE;
			} else {
				$this->debugMode = self::NONE;
			}
			return;
		}
		$this->debugMode = $type;
	}
	
	/**
	 * Pobierz tryb debugowania
	 * @see setDebugMode
	 */
	public function getDebugMode() {
		return $this->debugMode;
	}
	
	
	
	/**
	 * Metoda ustawia błąd wykonania.
	 * @throws MysqlKsException (w trybie EXCEPTION_MODE)
	 */
	 private function showError($error) {
		switch ($this->debugMode) {
			case self::NONE: break;
			case self::ECHO_MODE: echo $error; break;
			case self::EXCEPTION_MODE: throw new MysqlKsException($error); break;
		}
	 }
	
	/**
	 * Metoda ustawia globalnie domyślną nazwę dla klucza głównego tabeli.
	 * Klucz glówny tabeli jest wykorzystywany w metodach zakończonych na ById np deleteById().
	 */
	public function setId($id) {
		$this->defaultId = $id;
	}
	
	/** 
	 * Zwraca liczbę zmodyfikowanych rekordów przez ostatnie zapytanie
	 * Wynik tej metody jest zwracany przez metody: insert, update, updateById, delete, deleteById
	 *  @example
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
	 * @example $string = $db->escape($string);
	 */
	public function escape($str) {
		if (is_null($str)) return $str;
		if ($this->isExpr($str)) {
			//$str["value"] = $this->db->real_escape_string($str["value"]);
			return $str;
		}
		return $this->db->real_escape_string($str);
	}
	
	/**
	 * Określa początek transakcji
	 * @example
	 * $db->start();
	 * ... // kilka operacji na bazie danych
	 * $db->commit();
	 */
	public function start() {
		$this->query("START TRANSACTION");
	}
	
	/**
	 * Określa koniec transakcji
	 * @see start();
	 */
	public function commit() {
		$this->db->commit();
	}
	
	/**
	 * Domyślnie wszystkie wartości przekazywane do metod są traktowane jako łąńcuchy znaków i otaczane znakami '
	 * Kiedy zachodzi potrzeba przekazania innej wartości np funkcji sql jak np. NOW() należy użyć poniższej metody
	 * Wartość null nie musi być opakowywana przez poniższą metode
	 * @example $db->insert("tabela", array(null, $db->expr("NOW()"), "jakaś nazwa"), array("id, "czas", "nazwa"));
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
	 * @example
	 * $zapytanie = "UPDATE uzytkownicy, firmy SET firmy.nazwa = 'Marex Co.' 
	 *		WHERE uzytkownicy.firma = firmy.id AND uzytkownicy.id = 5";
	 * $db->query($zapytanie);
	 */
	public function query($zapytanie) {
		// zwiększenie licznika zapytań
		// @see getQueryCount()
		self::$licznik++;
		
		// wyzerowanie cursora
		$this->cursor = null;
		
		// zapisanie aktualnego zapytania
		// @see __toSring()
		$this->zapytanie = $zapytanie;
		
		$tmp = $this->db->query($zapytanie);
		
		if ($this->db->errno) {
			$blad = str_replace(array("%error%", "%errno%"), 
				array($this->db->error, $this->db->errno), $this->komunikatZleZapytanie);
			$this->showError($blad);
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
	 * @return pojedynczy wiersz tabeli
	 * Tabela uzytkownicy wykorzystywana do przykładów
	 * ____________________________
	 * |  id  |  imie  | nazwisko |
	 * |--------------------------|
	 * |   1  | marek  |   kos    |
	 * |   2  | jarek  |   kot    |
	 * |   3  | darek  | kowalski |
	 * |--------------------------|
	 * @example
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
	 * @example
	 * $uzytkownik = $db->select("uzytkownicy", array("id", "imie"), "WHERE imie='marek'");
	 * print_r($uzytkownik);
	 * Wynik:
	 * Array
	 *(
	 *	[id] => 1
	 *	[imie] => marek
	 *)
	 *	 
	 * @example
	 * $uzytkownik = $db->select("uzytkownicy", "id", "WHERE imie='marek'");
	 * print_r($uzytkownik);
	 * Wynik:
	 * Array
	 *(
	 *	[id] => 1
	 *)
	 */
	public function select($tabela, $wartosci = "*", $dodatki = "") {
		
		$zapytanie = $this->getSelectQuery($tabela, $wartosci, $dodatki);
		$wynik = $this->selectUser($zapytanie);	
	
		return $wynik;
	}
	
	/**
	 * Metoda działa podobnie do metody select() z tą różnicą, że możemy określić wartość id
	 * czyli wartość klucza głównego tabeli który nas interesuje.
	 * Domyślną wartością dla klucza głównego jest 'id', wartość tą możemy zmienić globalnie
	 * @see setId($id)
	 * albo podać ją jednorazowo w 4 parametrze metody ($userId)
	 * @param $tabela i $wartości takie same jak w przypadku select()
	 * @param $id - wartość id jaką chcemy wyszukać
	 * @param $userId - nazwa dla klucza głównego
	 *
	 * @example
	 * $uzytkownik = $db->select("uzytkownicy", 3);
	 * Wynik zapytania przy domyślnych ustawieniach:
	 * SELECT * FROM uzytkownicy WHERE id=3
	 *
	 * @example
	 * $db->setId("uzytkownik_id")
	 * $uzytkownik = $db->select("uzytkownicy", 3);
	 * Wynik zapytania:
	 * SELECT * FROM uzytkownicy WHERE uzytkownik_id=3
	 *
	 * @example
	 * $uzytkownik = $db->select("uzytkownicy", 3, array("id", "imie"), "user_id");
	 * Wynik zapytania:
	 * SELECT * FROM uzytkownicy WHERE user_id=3
	 */
	public function selectById($tabela, $id, $wartosci = "*", $userId = null) {
		 
		$valueId = ($userId ? $userId : $this->defaultId);
		
		$id = (int)$id;
		
		if ($id < 1) {
			showError($this->komunikatZleId);
			return;
		}
		
		$dodatki = "WHERE $valueId=$id";
		return $this->select($tabela, $wartosci, $dodatki);
	}
	
	/**
	 * Metoda zwraca wszystkie pasujący rekordy w postaci tabeli indeksowanej
	 * Parametry identyczne jak w przypadku metody select()
	 * Od metody select() różni się tylko tym, że zwraca wszystkie rekordy a nie pojedynczy wiersz
	 * @example 
	 * $uzytkownicy = $db->selectFull("uzytkownicy");
	 * print_r($uzytkownicy);
	 * Wynik: 
	 *Array
	 *(
	 *	[0] => Array
	 *   (
	 *	  [id] => 1
	 *	  [imie] => marek
	 *	  [nazwisko] => kos
	 *   )
	 *
	 *[1] => Array
	 *   (
	 *	   [id] => 2
	 *	   [imie] => jarek
	 *	   [nazwisko] => kot
	 *   )
	 *
	 *[2] => Array
	 *   (
	 *	   [id] => 3
	 *	   [imie] => darek
	 *	   [nazwisko] => kowalski
	 *   )
	 *
	 *)
	 * @example
	 * $uzytkownicy = $db->selectFull("uzytkownicy", array("id", "imie"));
	 * print_r($uzytkownicy);
	 * Wynik: 
	 *Array
	 *(
	 *	[0] => Array
	 *   (
	 *	   [id] => 1
	 *	  [imie] => marek
	 *   )
	 *
	 *[1] => Array
	 *   (
	 *	   [id] => 2
	 *	   [imie] => jarek
	 *   )
	 *
	 *[2] => Array
	 *   (
	 *	   [id] => 3
	 *	   [imie] => darek
	 *   )
	 *
	 *)
	 * @example
	 * $uzytkownicy = $db->selectFull("uzytkownicy", array("id", "imie"), "WHERE id < 3");
	 * print_r($uzytkownicy);
	 * Wynik: 
	 *Array
	 *(
	 *	[0] => Array
	 *   (
	 *	   [id] => 1
	 *	  [imie] => marek
	 *   )
	 *
	 *[1] => Array
	 *   (
	 *	   [id] => 2
	 *	   [imie] => jarek
	 *   )
	 *
	 *)
	 */
	public function selectFull($tabela, $wartosci = "*", $dodatki = "") {
		$zapytanie = $this->getSelectQuery($tabela, $wartosci, $dodatki);
		return $this->selectFullUser($zapytanie);
	}
	
	/**
	 * Metoda pomocnicza korzystają z niej niektóre metody select
	 */
	private function getSelectQuery($tabela, $wartosci, $dodatki) {
		$zapytanie = "SELECT ";
	
		if (!is_array($wartosci)) $wartosci = array($wartosci);
		$wartosci = array_map(array($this, 'escape'), $wartosci);
		
		for ($i = 0; $i < count($wartosci); $i++) {
			if ($this->isExpr($wartosci[$i])) 
				$zapytanie .= $this->exprGetValue($wartosci[$i]).", ";
			else 
				$zapytanie .= "$wartosci[$i], ";
		}
		$zapytanie = rtrim($zapytanie, ", ");
		
		$zapytanie .= " FROM $tabela $dodatki";
		
		return $zapytanie;
	}
	
	/**
	 * Metoda zwraca POJEDYNCZY wynik z zapytania napisanego przez uzytkownika
	 * 
	 * @example
	 * $zapytanie = "SELECT uzytkownicy.*, firmy.id WHERE uzytkownicy.firma = firma.id AND firma.id=4";
	 * $uzytkownicy = $db->selectUser($zapytanie);
	 */
	public function selectUser($zapytanie) {
		$wynik = $this->query($zapytanie);
		
		if ($this->fetchType == self::FETCH_ASSOC)
			return $wynik->fetch_assoc();
		
		if ($this->fetchType == self::FETCH_OBJECT)
			return $wynik->fetch_object();
	}
	
	/**
	 * Metoda zwraca wszystkie wyniki w postaci tabeli indeksowanej z zapytania napisanego przez uzytkownika
	 * Różni się od metody selectUser() tylko liczbą zwracanych wierszy
	 * 
	 * @example
	 * $zapytanie = "SELECT uzytkownicy.*, firmy.id WHERE uzytkownicy.firma = firma.id";
	 * $uzytkownicy = $db->selectFullUser($zapytanie);
	 */
	public function selectFullUser($zapytanie) {
		$wynik = $this->query($zapytanie);
		$wynik3 = array();
		
		if ($this->fetchType == self::FETCH_ASSOC)
			while ($wynik2 = $wynik->fetch_assoc()) {
				$wynik3[] = $wynik2;
			}
		
		elseif ($this->fetchType == self::FETCH_OBJECT)
			while ($wynik2 = $wynik->fetch_object()) {
				$wynik3[] = $wynik2;
			}
		
		return $wynik3;
	}
	
	/**
	 * Wydajniejszy odpowiednik metody selectFull().
	 * Metoda selectFull() domyślnie zwraca tablicę wszystkich wartości
	 * w przypadku dużej liczby rekordów może to nie być wydajne pamięciowo.
	 * Wszystkie parametry takie same jak w metodzie select().
	 * 
	 * @example
	 * $cursor = $db->selectCursor("uzytkownicy", array("id", "imie"));
	 * while ($uzytkownik = $cursor->next()) {
	 * 		echo "{$uzytkownik->id} - {$uzytkownik->imie}<br />";
	 * }
	 */
	public function selectCursos($tabela, $wartosci = "*", $dodatki = "") {
		
		$zapytanie = $this->getSelectQuery($tabela, $wartosci, $dodatki);
		return $this->selectCursorUser($zapytanie);	
	}
	
	/**
	 * Wydajniejszy odpowiednik metody selectFullUser()
	 * @see selectCurcor()
	 * 
	 * @example
	 * $zapytanie = "SELECT id, imie FROM uzytkownicy";
	 * $cursor = $db->selectCursorUser($zapytanie);
	 * while ($uzytkownik = $cursor->next()) {
	 * 		echo "{$uzytkownik->id} - {$uzytkownik->imie}<br />";
	 * }
	 */
	public function selectCursosUser($zapytanie) {
		$cursor = $this->query($zapytanie);
		return $this;
	}
	
	/**
	 * Metoda do przechodzenia po rekordach.
	 * Musi być poprzedzona wywołaniem metody selectCursor() lub selectCursorUser().
	 * @see selectCursor()
	 * @see selectCursorUser()
	 */
	public function next() {
		if ($this->cursor == null) {
			$this->showError($error);
			return null;
		}
		
		if ($this->fetchType == self::FETCH_ASSOC)
			return $this->cursor->fetch_assoc();
		
		if ($this->fetchType == self::FETCH_OBJECT)
			return $this->cursor->fetch_object();
	}

	/**
	 * Metoda do wstawiania pojedynczego rekordu do bazy.
	 * 
	 * @param $tabela - tabela
	 * @param $wartosci - tablica wartości (lub pojedyncza wartość) 
	 * wstawianych do bazy w kolejności ich występowania w tablicy
	 * @param $pola - tablica lub pojedyncza wartość, określający
	 * nazwy kolumn do których mają być wpisane odpowiadające wartości
	 * @return Liczbe wstawionych rekordow czyli 0 lub 1
	 * @see expr()
	 *
	 * Metoda wstawia do tabeli $tabela wartości znajdujące się w tablicy $wartosci
	 * w kolejności ich występowania w tablicy, gdy zostanie określony parametr $pola
	 * odpowiednim wartościom z tablicy pola zostaną przyporządkowane odpowiadające
	 * wartości z tablicy $wartosci, dla kolumn nie wyszczególnionych w tablicy wartości
	 * zostaną przyporządkowane domyślne wartości z bazy danych. Kiedy parametr $pola 
	 * nie jest określony, liczba elementów tablicy $wartosci musi być równa liczbie kolumn tabeli.
	 * 
	 * @example $db->insert("uzytkownicy", array("jan, "kowalski"), array("imie", "nazwisko");
	 * @example
	 * if ($db->insert("uzytkownicy", array(null, "jan, "kowalski"))
	 * 		echo "Wpisano Jana Kowalskiego";
	 * @example $db->insert("uzytkownicy", "jan", "imie");
	 */
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
	
	/**
	 * Metoda aktualizuje rekordy gdzie odpowiednie wartości tablicy $pola
	 * równają się odpowiednim wartościom tablicy $wartosci
	 * 
	 * @return liczbe zaktualizowanych rekordów
	 * @see expr() 
	 * 
	 * @example $db->update("uzytkownicy", "Jan", "imie");
	 * @example $db->update("uzytkownicy", array("Jan", "Kowalski"), array("imie", "nazwisko"));
	 * @example $db->update("uzytkownicy", "Jan", "imie", "WHERE nazwisko='kowalski'");
	 * 	
	 */
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
	
	/**
	 * Metoda do aktualizowania rekordu po wartości klucza głównego
	 * @see update()
	 * @see setId($id)
	 * @see selectById()
	 * @see expr()
	 * 
	 * @example $db->updateById("uzytkownicy", "jan", "imie", 4);
	 * @example $db->updateById("uzytkownicy", array("jan", "kowalski"), array("imie", "nazwisko"), 4);
	 * @example $db->updateById("uzytkownicy", array("jan", "kowalski"), array("imie", "nazwisko"), 
	 * 			4, "uzytkownik_id");
	 * 
	 */
	public function updateById($tabela, $wartosci, $pola , $id, $userId = null) {
		$valueId = ($userId ? $userId : $this->defaultId);
		
		$id = (int)$id;
		
		if ($id < 1) {
			showError($this->komunikatZleId);
			return;
		}
		
		$dodatki = "WHERE $valueId = ".$id;

		return $this->update($tabela, $wartosci, $pola, $dodatki);
	}
	
	/** 
	 * Metoda do usuwania rekordow z bazy danych.
	 * Metoda usuwa rekordy gdzie odpowiednie wartości tablicy $pola równają
	 * się odpowiednim wartościom tablicy $wartosci
	 * 
	 * @param $tabela - tabela 
	 * @param $wartosci - tablica wartości lub pojedyncza wartość
	 * @param $pola - tak samo jak wyżej
	 * @return liczbe usuniętych rekordów
	 * @see expr()
	 * 
	 * @example
	 * if ($db->delete("uzytkownicy", array("jan", "kowalski"), array("imie", "nazwisko")))
	 * 		echo "Usunięto wszystkich Janów Kowalskich!";
	 * 
	 * @example 
	 * $liczbaUsunietychRekordow = $db->delete("uzytkownicy", "jan", "imie");
	 * echo "Usunięto: $liczbaUsunietychRekordow Janów";
	 */
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
	
	/**
	 * Metoda do usuwania rekordu po wartości klucza głównego
	 * 
	 * @see delete()
	 * @see setId($id)
	 * @see selectById()
	 * @see expr()
	 * 
	 * @param $tabela - tabela
	 * @param $id $userId - takie samo znaczenie jak w przypadku funkcji selectById()
	 * 
	 * @example $db->deleteById("uzytkownicy", 5);
	 * @example $db->deleteById("uzytkownicy", 5, "uzytkownik_id");
	 */
	public function deleteById($tabela, $id, $userId = null) {
		$valueId = ($userId ? $userId : $this->defaultId);
		
		$id = (int)$id;
		
		if ($id < 1) {
			showError($this->komunikatZleId);
			return;
		}
		
		return $this->delete($tabela, $id, $valueId);
	}
}
?>