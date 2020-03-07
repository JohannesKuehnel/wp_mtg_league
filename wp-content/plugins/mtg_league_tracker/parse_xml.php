<?php
class Player {
  public $dci = "";
  public $name = "";
  public $rank = 0;
  public $points = 0;
}

function parse_tournament($file) {
  $xml = simplexml_load_file($file);
  
  if($xml === false) {
    return false;
  }

  $players = array();
  $disqualifiedPlayers = array();

  if(count($xml->event) > 0) {
    // XML format with rounds
    $format = (string) $xml->event[0]['PlayFormat'];
    if(empty($format))
      $format = (string) $xml->event[0]['format'];
    $format = strtolower($format) === strtolower("Modern") ? "Modern" : "Legacy";
    $date = (string) $xml->event[0]['startdate'];

    // get standings first
    $participants = $xml->event[0]->participation->children();
    foreach ($participants as $element) {
      if($element->getName() == "role" && $element['cd'] == "PL" && $element['type'] == "PA") {
        foreach ($element->children() as $player) {
          $tmp = new Player();
          $tmp->dci = (string)$player['person'];
          $tmp->rank = (int)$player['seq'];
          $players[$tmp->dci] = $tmp;
        }
      } elseif ($element->getName() == "role" && $element['cd'] == "PL" && $element['type'] == "DQ") {
        foreach ($element->children() as $player) {
          $disqualifiedPlayers[] = (string)$player['person'];
        }
      }
    }

    if(count($players) > 0) {
      // get player names
      foreach ($participants as $player) {
        if($player->getName() == "person") {
          if(isset($players[(string)$player['id']]->rank)) {
            $players[(string)$player['id']]->name = utf8_encode($player['last']).' '.utf8_encode($player['first']);
          }
          
        }
      }

      // get player points (by looking through rounds and matches)
      $rounds = $xml->event[0]->matches->children();
      foreach ($rounds as $round) {
        $roundMatches = $round->children();
        foreach ($roundMatches as $key => $match) {
          $playerOne = (string)$match['person'];
          $playerTwo = (string)$match['opponent'];
          if((int)$match['outcome'] === 1) {
            if (!in_array($playerOne, $disqualifiedPlayers)) $players[$playerOne]->points += 3;
          } else if ((int)$match['outcome'] === 2) {
            if (!in_array($playerOne, $disqualifiedPlayers)) $players[$playerOne]->points += 1;
            if (!in_array($playerTwo, $disqualifiedPlayers)) $players[$playerTwo]->points += 1;
          }
        }
      }
    }

  } else {
    // XML format with standings only
    $format = null;
    $date = null;

    $participants = $xml->children();
    foreach ($participants as $element) {
      $tmp = new Player();
      $tmp->dci = (string)$element['DCI'];
      $tmp->rank = (int)$element['Rank'];
      $tmp->points = (int)$element['MatchPoints'];
      $tmp->name = str_replace(",", "", (string)$element['Name']);
      $players[$tmp->dci] = $tmp;
    }
  }

  return array(
      'players' => $players, 
      'format' => $format, 
      'date' => $date
    );
}

?>