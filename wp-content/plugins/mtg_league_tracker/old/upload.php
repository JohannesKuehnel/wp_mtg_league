<?php
include("parse_xml.php");
include("database.php");

if(isset($_FILES["fileToUpload"])) {

	// Create connection
	$conn = new mysqli($host, $username, $password, $database);

	// Check connection
	if ($conn->connect_error) {
	    die("Connection failed: " . $conn->connect_error);
	} 

	$point_schema = array(
			array(50, 35, 25, 25, 15, 15, 15, 15),
			array(60, 45, 30, 30, 20, 20, 20, 20),
			array(70, 55, 35, 35, 25, 25, 25, 25),
		);

	$target_dir = "uploads/";
	$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
	$uploadOk = 1;
	$fileType = pathinfo($target_file, PATHINFO_EXTENSION);

	if($fileType != "xml") {
		echo "Sorry, only XML files are allowed.";
		$uploadOk = 0;
	}

	if ($_FILES["fileToUpload"]["size"] > 500000) {
		echo "Sorry, your file is too large.";
		$uploadOk = 0;
	}

	if($uploadOk) {
		if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
			
			$tournament = parse_tournament($target_file);
			$city = $_POST['city'];
			if(isset($_POST['date']) && !empty($_POST['date']) && $tournament['date'] == null)
				$date = date('Y-m-d', strtotime($_POST['date']));
			else
				$date = date('Y-m-d', strtotime($tournament['date']));
			if(isset($_POST['format']) && !empty($_POST['format']) && $tournament['format'] == null)
				$format = $_POST['format'];
			else
				$format = $tournament['format'];
			if($_POST['tournamentName'] && !empty($_POST['tournamentName']))
				$tournamentName = $_POST['tournamentName'];
			else
				$tournamentName = $format . " Open " . $city;
			
			$players = $tournament['players'];

			$sql = "INSERT INTO tournaments (`date`, name, format, city) VALUES ('" . $date . "', '" . utf8_decode($tournamentName) . "', '" . $format . "', '" . utf8_decode($city) . "')";
			if ($conn->query($sql) !== TRUE) {
			    echo "Error: " . $sql . "<br>" . $conn->error;
			    break;
			}
			$tournament_id = $conn->insert_id;

			$schema_index = count($players) <= 16 ? 0 : (count($players) <= 32 ? 1 : 2);

			//TODO: save to database
			foreach ($players as $player) {
				$player->points = $player->rank <= 8 ? $point_schema[$schema_index][$player->rank - 1] : 5;
				$sql = "INSERT INTO players (dci, name) VALUES (" . $player->dci . ", '" . utf8_decode($player->name) . "')";
				$conn->query($sql);
				$sql = "INSERT INTO results (player_dci, tournament_id, rank, points) VALUES (" . $player->dci . ", " . $tournament_id . ", " . $player->rank . ", " . $player->points . ")";
				$conn->query($sql);
			}

			$json = '{"tournamentName": ' . json_encode($tournamentName) . ', "date": ' . json_encode($date) . ', "format": ' . json_encode($format);
			$json .= ', "players": [';
			$i = 1;
			foreach ($players as $player) {
				$json .= json_encode($player);
				if($i++ < count($players)) {
					$json .= ",";
				}
			}
			echo utf8_encode($json."]}");
		} else {
			$uploadOk = 0;
		}
	}

	if(!$uploadOk) {
		echo "Upload failed";
	}
	$conn->close();
} else {
	echo "Nope.";
}
?>