#php-json-sql#
##Documentation##

# Features

* Keine SQL-Statements in PHP
* Auswechselbarkeit der Statements
* Vereinfachung des Datenbankzugriffs
* Einfache Modifizierung der Statements
* Logging über FirePHP
* Der Programmierer braucht weniger Hintergrundwissen über die Datenbank, PHP und OOP

# Allgemeines
Beispiel für eine Ordnerstruktur
/classes/ -> Hier kommen alle wichtigen Klassen rein
    json_sql.php
    json_sql_mysql.php
    json_sql_installer.php -> Install klasse
    FirePHP.class.php -> Debugging-Klasse für PHP
/modules/ -> Hier kommen die einzelnen eigenen Module rein
/config/ -> Hier kommen die Konfigurationsdateien rein
    db.json -> Die Datenbankstruktur
    aliases.json -> Datei mit den Spaltenaliasen
    config.php -> Die Konfigurationsdatei
root-Verzeichniss
install.php ->Installationsdatei
index.php -> Die Hauptdatei

# Beispiele

## Einfaches Beispiel

Wir wollen eine Einfache Liste von Nutzern in einer Datenbank speichern. Nutzer sollen erstellt, bearbeitet und gelöscht werden können. Ein Nutzer hat eine Email, einen Namen und ein Passwort.

Als erstes definieren wir die Datenbankstruktur in der db.json:
```
{
    "users": {
        "id":        "id",
        "name":        ["string",20],
        "email":    ["string",40],
        "password":    ["string",40]
    }
}
```

Darin erstellen wir erst ein Objekt, welches alle Tabellen aufnimmt. Für jede Tabelle geben wir einen Namen als Key und die Felder als weiteres Objekt an.
Jedes Feld bekommt in diesem Objekt einen Namen und einen Datentypen zugeordnet
Mögliche Datentypen->das hier vielleicht in nen eigenen Wikieintrag

Name     |       SQL-Entsprechung         | Bemerkungen
---------|--------------------------------|-----------------
id       | INT PRIMARY KEY AUTO_INCREMENT | sollte für alle ids benutzt werden
int      | INT                            | Ein ganz normaler integer
date     | DATE                           | Ein Datum
datetime | DATETIME                       | Ein Datum mit Zeit
bool     | BOOL                           |
boolean  | BOOL                           | Entspricht bool
text     | TEXT                           | Text (fast) ohne Längenbegrenzung, wird nicht indexiert
json     | TEXT                           | Übergeben wird ein JSON-Objekt, dieses wird als Text in der Datenbank gespeichert
["string",123]  |  VARCHAR(123)           | der Zweite Eintrag gibt die Maximallänge an
["foreign","tabelle"] | INT               | Eine Verknüpfung zu einer anderen Tabelle (Wird zur Zeit als einfacher int ausgewertet

Wir wollen nicht wissen, wo sich eine Spalte befindet, und wie die Beziehungen zwischen den Tabellen sind, das soll die Klasse für uns machen. Aber dafür müssen wir eine weitere Datei anlegen, die man im Entwicklungsprozess noch öfter erweitern wird.

Die Datei heißt alias.json und definiert Aliase für die Tabellenspalten, damit wir statt "user.name" einfach "Name"  schreiben können und die Klasse sofort weiß, welche Tabelle sie dafür abfragen muss:
```
{
    "UserId":            {"table":"users","field":"id"},
    "UserName":            {"table":"users","field":"name"},
    "UserPassword":        {"table":"users","field":"password"},
    "UserEmail":        {"table":"users","field":"email"}
}
```

Der Aufbau dieser Datei ist ganz einfach: Wir haben ein Hauptobjekt, welches alle Aliase enthält. Zu jedem Alias definieren wir zu welcher Tabelle er gehört, und welche Spalte er ansprechen soll.

Wir haben nun die Datenbankstruktur erstellt. Jetzt müssen wir noch eine Datenbank erzeugen, mit der wir danach arbeiten wollen.
Am einfachsten ist es, wenn wir eine SQLite-Datenbank erzeugen: (Du kannst auch einfach eine MySQL-DB erzeugen, da musst du nur einige wenige Sachen anpassen)
Um unsere Konfiguration zentral zu speichern, legen wir zuerst eine config.php  im Ordner config/ an:
`/config/config.php`
```php
<?php
include_once 'classes/json_sql.php';
include_once 'classes/src/json_sql_mysql.php';
include_once 'classes/src/sqlException.php';
$dbh= new PDO("sqlite:db.sdb");//Erzeugt ein SQLite PDO-Objekt
$db= new jsonSqlMysql(file_get_contents('config/db.json'), file_get_contents('config/aliases.json'),$dbh);//Erzeugt das JSON-Mysql-Objekt
$db->set_debug(true,'classes/FirePHP.class.php');//aktiviert den Debugmodus
?>
```

In den ersten Zeilen binden wir die Klassen für die Datenbank ein. Danach erstellen wir ein neues PDO-Objekt, welches die db.sdb als Datenbank benutzt (Diese Zeile sollte angepasst werden, wenn du nicht SQLite nutzt). In der nächsten Zeile erzeugen wir unser Datenbankobjekt, welches als Parameter das JSON-Datenbankobjekt, das JSON-Aliasobjekt und das PDO-Datenbankobjekt. Um das Debugging anzuschalten rufst du die set_debug-Funktion mit dem ersten Parameter true (schaltet Debugging an) und dem Pfad zur FirePHP-Klasse als zweiten Parameter. Wenn du nicht debuggen möchtest, solltest du die Zeile einfach auskommentieren.

Nun kommen wir zum eigentlichen Installationsprozess:

Erstellen wir eine Datei install.php:

`/install.php`
```php
<?php
include_once 'config/config.php';
include_once 'classes/src/json_sql_installer.php';
$install= new jsonSqlInstaller(json_decode(file_get_contents('config/db.json'),$dbh),true); // Das True bei MySQL weglassen!
$install->install();
?>
```

Hier binden wir zuerst unsere Konfigurationsdatei und Installationsklasse ein. Danach erstellen wir ein neues Installationsobjekt und rufen die Install()-Funktion auf.

Wenn alles gut gelaufen ist, sollte in der ersten Zeile etwas in der Art stehen:
`Database created`

Wenn nicht, steht dort das Error-Objekt von PDO stehen, daraus wird ersichtlich, was der Fehler ist.
Nun legen wir eine neue php-Datei in unserem Webverzeichnis an. Diese nennen wir index.php. Dies ist absofort dieHauptdatei unseres kleinen Projektes.
Inhalt der index.php:

```php
<?php
    /*
    * Konfigurtaionsdatei, erzeugt Datenbankobjekt($db)
    */
    include_once 'config/config.php';
    ?>
<!DOCTYPE HTML>
<html>
    <head>
        <title>Ein erstes kleines Datenbankprojekt</title>
    </head>
    <body>
        <h1>Mein erstes kleines Datenbankprojekt mit der JSON-SQL-Klasse</h1>
        <hr><br>
        <form method="post" action="index.php?action=insert_data">
            <p>Neuen Datensatz einfügen:</p>
            <label for="username">Name</label><br/>
            <input type="text" value="mmustermann" name="username" id="username" /><br/>
            <label for="email">Email</label><br/>
            <input type="text" value="foo@example.com" name="email" id="email" /><br/>
            <label for="password">Passwort</label> (Wird im Klartext gespeichert)<br/>
            <input type="password" value="g3h31m" name="password" id="password" /><br/>
            <input type="submit" name="Button" value="Abschicken">
        </form>
        <?php
        /*
        * Bis hierher das HTML-Grundgerüst der Seite.
        * Es gibt einen Button zum einfügen eines neuen (im Quelltext festgelegten Datensatzes), und einem zum Abrufen aller Datensätzte in unserer kleinen Datenbank.
        * Im folgenden muss die Funtkionaltiät der beiden Buttons implementiert werden
        */
        if (isset($_GET['action'])) {//operationen machen
            switch($_GET['action']){
                case 'insert_data':
                    $query = '{"type":"insert",
                        "UserName":"'.$_POST['username'].'",
                        "UserPassword":"'.$_POST['email'].'",
                        "UserEmail":"'.$_POST['password'].'"
                    }';
                    $query = json_decode($query);
                    $db->query($query);
                    break;
                case 'delete':
                    $query='{"type":"delete",
                    "table":"users",
                    "where":[{
                        "field":"UserId",
                        "op":"=",
                        "value":'.$_GET['id'].'
                    }]}';
                    $query = json_decode($query);
                    $db->query($query);
            }
        }
            $query = '{"type":"select","what":["UserId","UserName","UserPassword","UserEmail"]}';
            $query = json_decode($query);
            $werte= $db->query($query);
            echo'<table>';
            $i= 0;
            //Header
            echo '<tr>';
            foreach($werte[0] as $key=>$foo) {
                echo'<th>'.$key.'</th>';//Tabellenkopf                
            }
            echo '<th>Operationen</th>';
            echo '</tr>';
            
            foreach($werte as $key => $val){
                echo '<tr>';
                foreach($val as $key => $value){
                    echo'<td>'.$value.'</td>';//Tabelleninhalt
                }
                echo '<td><a href="index.php?action=delete&id='.$val["UserId"].'">delete</a> </td>';
                echo '</tr>';
            }
            echo '</table>';
        ?>
    </body>
</html>
```
