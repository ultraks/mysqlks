<!DOCTYPE html>
<html lang="pl">
<head>
<meta http-equiv="Content-type" content="text/html; charset=UTF-8" />
</head>
<body>
<?php
/*
 * Na początku w pliku mysql.php należy wpisać swoje parametry połączenia z bazą linie [30-33].
 * W tym przykładzie skorzystam z bazy danych o następujących kolumnach:
 * _____________________________________________________
 * |  id(PK)  |  nazwisko(varchar)  |  czas(datetime)  |
 * -----------------------------------------------------  
 * W przykładach fragmentami wypisywane są wyniki wywołań metod
 */
include 'mysql.php';

$db = MysqlKs::getInstance();
$tabela = "uzytkownicy";


echo "1.insert, lastId<br />\n";
$db->insert($tabela, array(null, "nowak", $db->expr("NOW()")));
$db->insert($tabela, array(null, "kowalski", "2013-11-10 11:12"));

if ($db->insert($tabela, array("darek", $db->expr("NOW()")), array("nazwisko", "czas")))
	echo "Wstawiono rekord";
else 
	echo "Nie udało się wstawić rekordu :(";

echo "<br />\n";
echo "Ostatnio wstawiony klucz glowny: {$db->lastId()}<br />\n";


echo "2.selectFull, selectFullUser<br />\n";
$uzytkownicy = $db->selectFull("uzytkownicy");
print_r($uzytkownicy);

$uzytkownicy = $db->selectFullUser("SELECT nazwisko FROM uzytkownicy");
print_r($uzytkownicy);

echo "3.select, setFetchType, selectById, selectUser:<br />\n";
$uzytkownik = $db->select("uzytkownicy", "nazwisko", "WHERE nazwisko='nowak'");
echo $uzytkownik["nazwisko"]."<br />\n";

$db->setFetchType(MysqlKs::FETCH_OBJECT);
$uzytkownik = $db->selectById("uzytkownicy", 2, "*");
echo $uzytkownik->czas."<br />\n";

$uzytkownik = $db->selectUser("SELECT id FROM uzytkownicy WHERE nazwisko='darek'");
echo $uzytkownik->id."<br />\n";

echo "4.selectCursor, selectCursorUser, next<br />\n";
$cursor = $db->selectCursor("uzytkownicy", array("id", "nazwisko"));
while ($uzytkownik = $cursor->next()) {
	echo "{$uzytkownik->id} - {$uzytkownik->nazwisko}<br />";
}

$cursor = $db->selectCursorUser("SELECT id, nazwisko FROM uzytkownicy");
while ($uzytkownik = $cursor->next()) {
	echo "{$uzytkownik->id} - {$uzytkownik->nazwisko}<br />";
}

echo "4. setDebugMode, updateById, update, __toString<br />\n";
$db->setDebugMode(MysqlKs::EXCEPTION_MODE);
try {
	// Niepoprawne zapytanie, brak kolumny imie
	$db->selectFull($tabela, "imie");
	
} catch (MysqlKsException $e) {
	echo $e->getMessage()."<br />\n";
}
echo "Zmienionych rekordów: ".$db->updateById($tabela, "nowak2", "nazwisko", 1)."<br />\n";
$db->setDebugMode(MysqlKs::ECHO_MODE);
$db->next();
echo "<br />\n";
echo "Zmienionych rekordów: ".$db->update($tabela, $db->expr("NOW()"), "czas")."<br />\n";
echo "Ostatnie zapytanie: ".$db;

echo "5. delete, deleteById, getQueryCount<br />\n";
echo "Usuniętych rekordów: ".$db->deleteById($tabela, 1)."<br />\n";
$db->setDebugMode(MysqlKs::ECHO_MODE);
// sprowokowanie błedu
$db->next();
echo "<br />\n";
echo "Usuniętych rekordów: ".$db->delete($tabela, "kowalski" ,"nazwisko")."<br />\n";
echo "Liczba wszystkich zapytań: ".$db->getQueryCount()."<br />\n";

echo "Zobacz w kodzie klasy metody: setCharset, setId, getAffectedRows, escape, start, commit, query."


/*
WYNIK działania skryptu:
1.insert, lastId
Wstawiono rekord
Ostatnio wstawiony klucz glowny: 3
2.selectFull, selectFullUser
Array
(
    [0] => Array
        (
            [id] => 1
            [nazwisko] => nowak
            [czas] => 2013-11-07 16:07:05
        )

    [1] => Array
        (
            [id] => 2
            [nazwisko] => kowalski
            [czas] => 2013-11-10 11:12:00
        )

    [2] => Array
        (
            [id] => 3
            [nazwisko] => darek
            [czas] => 2013-11-07 16:07:05
        )

)
Array
(
    [0] => Array
        (
            [nazwisko] => nowak
        )

    [1] => Array
        (
            [nazwisko] => kowalski
        )

    [2] => Array
        (
            [nazwisko] => darek
        )

)
3.select, setFetchType, selectById, selectUser:
nowak
2013-11-10 11:12:00
3
4.selectCursor, selectCursorUser, next
1 - nowak
2 - kowalski
3 - darek
1 - nowak
2 - kowalski
3 - darek
4. setDebugMode, updateById, update, __toString
Błąd w zapytaniu: Unknown column 'imie' in 'field list'
Zmienionych rekordów: 1
Niepoprawne wywołanie metody next(), powinno być poprzedzone wywołaniem metody selectCursor(), selectCursorUser() lub next().
Zmienionych rekordów: 1
Ostatnie zapytanie: UPDATE uzytkownicy SET czas = NOW() 
5. delete, deleteById, getQueryCount
Usuniętych rekordów: 1
Niepoprawne wywołanie metody next(), powinno być poprzedzone wywołaniem metody selectCursor(), selectCursorUser() lub next().
Usuniętych rekordów: 1
Liczba wszystkich zapytań: 15
Zobacz w kodzie klasy metody: setCharset, setId, getAffectedRows, escape, start, commit, query.
 */
?>
</body>
</html>