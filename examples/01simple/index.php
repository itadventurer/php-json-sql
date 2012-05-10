<?php
	/*
	* Konfigurtaionsdatei, erzeugt Datenbankobjekt($db)
	*/
	define('ROOTDIR','');
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
