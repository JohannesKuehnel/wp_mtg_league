<?php
include("database.php");

$conn = new mysqli($host, $username, $password, $database);

if (isset($_GET['format'])) {
    $format = $_GET['format'];
} else {
    $format = 'Legacy';
}

if (isset($_GET['season'])) {
    $season = $_GET['season'];
} else {
    $season = date('Y');
}

$sql = "SELECT players.name, players.dci, SUM(results.points) as points FROM players, results, tournaments WHERE players.dci = results.player_dci AND tournaments.tournament_id = results.tournament_id AND tournaments.format = '" . $format . "' AND YEAR(date) = " . $season . " GROUP BY players.name ORDER BY points DESC, name ASC";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    // output data of each row
    echo "<table>\n";
    echo "<tr><th>Name</th><th>DCI #</th><th>Points</th></tr>\n";
    while($row = $result->fetch_assoc()) {
        echo "<tr><td>" . utf8_encode($row["name"]). "</td><td>" . $row["dci"]. "</td><td>" . $row["points"]. "</td></tr>\n";
    }
    echo "</table>\n";
} else {
    echo "Keine Turniere hinterlegt\n";
    $conn->close();
    return;
}

echo "<br />\n";

$sql = "SELECT name, `date` FROM tournaments WHERE tournaments.format = '" . $format . "' AND YEAR(date) = " . $season . " ORDER BY `date` ASC";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    // output data of each row
    echo "Folgende Turniere wurden berücksichtigt: <br />\n";
    echo "<ul>\n";
    while($row = $result->fetch_assoc()) {
        echo "<li>" . utf8_encode($row["name"]). " " . $row["date"]. "</li>\n";
    }
    echo "</ul>\n";
} else {
    echo "Keine Turniere hinterlegt\n";
}

$conn->close();

?>