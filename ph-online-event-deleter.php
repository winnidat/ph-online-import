<?php

class PhOnlineEventDeleter{
	
	private $log = "";
	private $numDeleted = 0;

  function __construct() {
		
	}
	
	private function logLine($line) {
		$this->log .= $line."\r\n";
		echo $line."<br>";
	}
	
	private function saveLog() {
		update_option( "ph-online-delete-log", $this->log);
	}
	
	public function getLog() {
		return get_option( "ph-online-delete-log" );
	}
	
	public function delete(){
	  
	  $time = strtotime("-1 year", time());
	  $date = date("Y-m-d", $time);
		
		$this->logLine("DELETION START");
		$this->logLine(date("c"));
		$this->logLine("EVENTS OLDER THAN 1 YEAR (= ".$date.")");
		$this->logLine("-------");
		
	  		
		$events = tribe_get_events(array("posts_per_page" => -1));
				
		foreach($events as $e){
			
			$this->logLine("-------");
			
			$this->logLine("START DELETION OF EVENT ".$e->ID);

			$this->deleteEvent($e->ID);
		}

		$this->logLine("-------");
		$this->logLine("DELETION END");
		$this->logLine("EVENTS DELETED: ".$this->numDeleted);
		$this->logLine(date("c"));
		$this->logLine("-------");
		
		$this->saveLog();
		
	}
	
	private function deleteEvent($id){
		
		$event_id = tribe_delete_event($id, true);
		
		if($event_id !== false){
			$this->logLine("EVENT DELETED ".$id);
			$this->numDeleted++;
		}else{
			$this->logLine("EVENT NOT DELETED, ERROR ".$id);
		}
		
		
		return $event_id;
		
	}
}