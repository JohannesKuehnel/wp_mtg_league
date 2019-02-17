<?php
class Player {
	public $dci = "";
	public $name = "";
	public $rank = 0;
}

function parse_tournament($file) {
	if(file_exists($file)) {
		$xml = simplexml_load_file($file);
	} else {
		return false;
	}
	
	if($xml === false) {
		return false;
	}

	$players = array();

	if(count($xml->event) > 0) {
		// XML format with rounds
		$format = (string) $xml->event[0]['PlayFormat'];
		if(empty($format))
			$format = (string) $xml->event[0]['format'];
		$format = strtolower($format) === strtolower("Modern") ? "Modern" : "Legacy";
		$date = (string) $xml->event[0]['startdate'];

		$participants = $xml->event[0]->participation->children();
		foreach ($participants as $element) {
			if($element->getName() == "role" && $element['cd'] == "PL" && $element['type'] == "PA") {
				foreach ($element->children() as $player) {
					$tmp = new Player();
					$tmp->dci = (string)$player['person'];
					$tmp->rank = (int)$player['seq'];
					$players[$tmp->dci] = $tmp;
				}
				break;
			}
		}

		if(count($players) > 0) {
			foreach ($participants as $player) {
				if($player->getName() == "person") {
					if(isset($players[(string)$player['id']]->rank)) {
						$players[(string)$player['id']]->name = $player['last'].' '.$player['first'];
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