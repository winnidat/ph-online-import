<?php
use \ForceUTF8\Encoding;
use \PHOnlineToken\Token;

class PhOnlineEventImporter{
	
    
	private $log = "";
	private $numUpdated = 0;
	private $numCreated = 0;
	private $categories = array("Online-Seminar", "eLecture", "eWorkshop");
	private $importrun = 0;
	private $importStartedTimestamp = 0;
	private $token = null;

  function __construct() {
	   $this->getToken();
	}
	
	private function getToken (){
	    $this->token = \PHOnlineToken\Token::GetToken();
	}
	
	private function logLine($line) {
		$this->log .= $line."\r\n";
		echo $line."<br>";
	}
	
	private function saveLog() {
		update_option( "ph-online-import-log", $this->log);
	}
	
	public function getLog() {
		return get_option( "ph-online-import-log" );
	}
	
	public function setLongerExecutionTime(){
		ini_set('max_execution_time', 1200);
	}
	
	public function import(){
		$this->importid = uniqid();
		$this->importStartedTimestamp = time();
		
		$this->setLongerExecutionTime();

		
		$this->logLine("IMPORT START");
		$this->logLine(date("c", current_time( 'timestamp' )));
		
		$fromtime = strtotime("today -100 days");
		$untiltime = strtotime("today +1 year");
		
		$url = "https://www.ph-online.ac.at/ph-bgldj/ws/webservice_v1.0/xcal/organization/courses/xml?token=". $this->token ."&timeMode=absolute&orgUnitID=15304&fromDate=".date("Ymd", $fromtime)."&untilDate=".date("Ymd", $untiltime);		

		$this->logLine("URL: ".$url);
		$this->logLine("-------");

		
		//$url = plugin_dir_path( __FILE__ )."sample2.xml";
		
		$content = Encoding::toUTF8($this->getUrl($url));
		
		$xml = simplexml_load_string($content);

		
		//$xml = $this->loadXML($url);
				
		$events = $xml->xpath('/xCal:iCalendar/xCal:vcalendar/xCal:vevent');
		
		foreach($events as $e){
			$event = $e->children('http://campusonline.xcal.at/');
			$uid = (string)$event->uid;
			
			$this->logLine("-------");
			
			$this->logLine("START IMPORT OF EVENT ".$uid);
			
			
			if(!$this->is_private_event($event->summary) && $this->is_fixed_event($event->status) && $this->in_category((string)$event->categories->item)){				
				preg_match("/\d+$/i", $event->description["altrep"], $match);
				if(count($match) == 1){
					$courseID = $match[0];
					
					$singleEvent = $this->loadEvent($courseID);
					
					if($singleEvent){
												
						$data = $this->makeEventData($event, $singleEvent);
												
						$event_id = $this->createOrUpdateEvent($courseID, $data);
						
						if($event_id !== false){
							$this->logLine("SUCCESSFUL IMPORT OF EVENT ".$uid);
						}else{
							$this->logLine("FAILED IMPORT OF EVENT ".$uid);							
						}
												
					}
				}				
			}else{
				$this->logLine("EVENT IS PRIVATE, NOT FIXED OR NOT A CATEGORY ".$event->summary);							
			}							
		}

		$this->logLine("-------");
		$this->logLine("IMPORT END");
		$this->logLine("EVENTS CREATED: ".$this->numCreated);
		$this->logLine("EVENTS UPDATED: ".$this->numUpdated);
		$this->logLine("IMPORT END");
		$this->logLine(date("c"));
		$this->logLine("-------");
		
		$this->saveLog();
		
	}
	
	private function loadXML($url){
		$this->logLine("Load XML: ".$url);

		$content = $this->getUrl($url);
		
		$xml = simplexml_load_string($content);
		
		return $xml;
	}
	
	private function getUrl($url){
		if (!function_exists('curl_init')){ 
		    die('CURL is not installed!');
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}
	
	private function is_private_event($lvcode) {
		if(preg_match("/(.+)(PLG|QeL|QOS|BDeL)(.+)/", $lvcode)){
			return true;
		}else{
			return false;
		}
	}
	
	private function in_category($cat){
		if(in_array($cat, $this->categories)){
			return true;
		} else {
			return false;
		}
	}
	
	private function is_fixed_event($status) {
		if($status == "fix"){
			return true;
		}else{
			return false;
		}
	}
	
	private function get_course_room($text) {
		preg_match('/(https?:\/\/(?:www\.|(?!www))[^\s\.]+\.[^\s]{2,}|www\.[^\s]+\.[^\s]{2,})/', $text, $match);		
		//preg_match("/http:\/\/virtuelle-ph.adobeconnect.com\/[^\s]*/i", $text, $match);
		if(count($match) >= 1){
			return $match[0];
		} else {
			return null;
		}
		
		
	}
	
	private function get_tax($name) {

		$name = sanitize_text_field($name);
		
		$term_id = false;
				
		$term = get_term_by("name", $name, 'tribe_events_cat');
		
		if($term !== false){
			$term_id = $term->term_id;
		}else{
			$term = wp_insert_term( $name, "tribe_events_cat");
			$term_id = $term["term_id"];
		}
		
		return $term_id;
	}


	private function get_organizer($name) {
		
		$organizer_id = false;
		
		$organizer = get_page_by_title($name, OBJECT, 'tribe_organizer');
				
		if($organizer == null){
			$organizer_id = tribe_create_organizer(array("Organizer" => $name));
			
			$post = get_post($organizer_id);
			
			wp_update_post( array("ID" => $organizer_id, "post_content" => "[insert page='referent_innen/".sanitize_title($name)."' display='content']"));
						
		}else{
			$organizer_id = $organizer->ID;
		}
		
		return $organizer_id;
	}
	
	private function loadEvent($id){
		
		if(!is_numeric($id)){
			
			return false;
			
		}
		
		$xml = $this->loadXML("https://www.ph-online.ac.at/ph-bgldj/ws/webservice_v1.0/cdm/course/xml?token=". $this->token ."&courseID=".$id);
				
		return $xml;

	}
	
	private function makeEventData($event, $singleEvent) {
		
		$data = array();

		$startTimestamp = strtotime((string)$event->dtstart);
		$endTimestamp = strtotime((string)$event->dtend);
		
		$post_content = (string)$singleEvent->course->courseDescription;
		
		$catName = (string)$event->categories->item;
		$cat = $this->get_tax($catName);
		
		$this->logLine("Cat-Name: ".$catName);
		$this->logLine("Cat-ID: ".$cat);
			
		// Special Categories
		
		preg_match("/\[(Online\-Programm|Themenschwerpunkt):\s*([\w\s\p{L}]*)\]/iu", $post_content, $specialCatMatch);
		$extracat = array();
			
		if(count($specialCatMatch) == 3) {
			$extracat[] = $this->get_tax(trim($specialCatMatch[1])." ".trim($specialCatMatch[2]));
			$post_content = str_replace($specialCatMatch[0], "", $post_content);
		}		
		
		$cats = array_merge(array($cat), $extracat);
				
		$organizer = array();
		$organizer["OrganizerID"] = array();
		
		foreach($singleEvent->course->contacts->person as $person){
			$givenName = (string)$person->name->given;
			$familyName = (string)$person->name->family;
		
			$organizer_id = $this->get_organizer($givenName. " ".$familyName );
			$organizer["OrganizerID"][] = $organizer_id;
		}		
													
		$data["meta_input"]["importid"] = $this->importid;		
		$data["post_status"] = "publish";
		$data["post_title"] = (string)$singleEvent->course->courseName->text;
		$data["tax_input"] = array("tribe_events_cat" => $cats);		
		$data["post_content"] = $post_content;
		$data["meta_input"]["course_order"] = (string)$singleEvent->course->courseCode;
		$data["meta_input"]["course_id"] = (string)$singleEvent->course->courseID;
		$data["meta_input"]["summary"] = (string)$event->summary;
		$data["meta_input"]["lvurl"] = (string)$event->description["altrep"];
		$data["meta_input"]["course_prerequisites"] = (string)$singleEvent->course->recommendedPrerequisites;
		$data["meta_input"]["last_import"] = (string)time();
		$data["EventStartDate"] = date("Y-m-d", $startTimestamp);
		$data["EventEndDate"] = date("Y-m-d", $endTimestamp);
		
		if($catName == "eLecture"){
			$data["EventStartHour"] = date("H", $startTimestamp);
			$data["EventStartMinute"] = date("i", $startTimestamp);
			$data["EventEndHour"] = date("H", $endTimestamp);
			$data["EventEndMinute"] = date("i", $endTimestamp);		
		}else{
			$data["EventAllDay"] = true;			
		}
		
		$data["Organizer"] = $organizer;
		
		if($catName === "eLecture"){
			$data["meta_input"]["course_room"] = $this->get_course_room((string)$singleEvent->course->admissionInfo->admissionDescription);
		}
		
		$data["post_content"] = $this->contentForEvent($singleEvent, $catName, $data);

		return $data;
	}
	
	private function makeEventTimeData($data, $wp_event_id) {
		
		
		$newData = array();

		$startTimestamp = strtotime($data["EventStartDate"]);
		$endTimestamp = strtotime($data["EventEndDate"]);
		
		$newData["EventStartDate"] = $data["EventStartDate"];
		$newData["EventEndDate"] = $data["EventEndDate"];
		
		$newStartTimestamp = strtotime(get_post_meta($wp_event_id, "_EventStartDate", true));
		$newEndTimestamp = strtotime(get_post_meta($wp_event_id, "_EventEndDate", true));
		
		if($newStartTimestamp < $startTimestamp){
			$newData["EventStartDate"] = date("Y-m-d", $newStartTimestamp);
		}

		if($newEndTimestamp > $endTimestamp){
			$newData["EventEndDate"] = date("Y-m-d", $newEndTimestamp);
		}

		$newData["EventAllDay"] = true;			
		
		return $newData;
	}
	
	private function createOrUpdateEvent($courseID, $data){
		
		$event_id = false;
		
		$args = array(
		    'meta_query' => array(
			    	'relation' => 'AND',
		        array(
		            'key' => 'course_id',
		            'value' => $courseID
		        ),
		        array(
			        "key" => "summary",
			        "value" => $data["meta_input"]["summary"]
		        )
		    ),
		    'post_type' => 'tribe_events',
		    'post_status' => 'publish',
		    'posts_per_page' => -1
		);
		$events = get_posts($args);	
		
		if(count($events) < 1){
			$event_id = $this->createEvent($data);
			$this->numCreated++;
		}else{
			if(get_post_meta($events[0]->ID, "importid", true) == $this->importid){
				
				$this->logLine("UPDATE ONLY TIME OF EVENT ".$event_id);
				$data = $this->makeEventTimeData($data, $events[0]->ID);
				$event_id = $this->updateEvent($events[0]->ID, $data);
				
			}else{

				if(strtotime(get_post_meta($events[0]->ID, "_EventStartDate", true)) <= $this->importStartedTimestamp){
						$this->logLine("EVENT ALREADY BEGUN, NOT UPDATED");
						return $events[0]->ID;
				}
				
				$this->logLine("UPDATE WHOLE EVENT ".$event_id);
				$event_id = $this->updateEvent($events[0]->ID, $data);
				$this->numUpdated++;	
			}
		}
		
		return $event_id; 
		
	}
	
	private function createEvent($data){
				
		$event_id = tribe_create_event($data);
				
		if($event_id !== false){
			
			$this->updateTerms($event_id, $data);
								
			$this->logLine("EVENT CREATED ".$event_id);
		}
		
		return $event_id;
		
	}	
	
	private function updateEvent($id, $data){
		
		$event_id = tribe_update_event($id, $data);
		
		if($event_id !== false){

			$this->updateTerms($event_id, $data);

			$this->logLine("EVENT UPDATED ".$event_id);
		}
		
		return $event_id;
		
	}

	private function updateTerms($event_id, $data){
		if(isset($event_id) && $event_id !== false && isset($data["tax_input"]["tribe_events_cat"])){
			wp_set_object_terms($event_id, $data["tax_input"]["tribe_events_cat"], "tribe_events_cat");
				$this->logLine("Categories updated");
		}else{
			$this->logLine("No Categories to set");
		}
	}
	
	private function contentForEvent($event, $category, $data) {
		
		$content = "";
		
		$learningObjectives = $this->linkify((string)$event->course->learningObjectives);
		$admission = $this->linkify((string)$event->course->admissionInfo->admissionDescription);
		$recommendedPrerequisites = $this->linkify($data["meta_input"]["course_prerequisites"]);
		$post_content = $this->linkify($data["post_content"]);
				
		switch ($category) {
		    case "eLecture":
		    
					$content ='<img class="alignleft wp-image-354 size-full" src="http://onlinecampus-server.at/vphneu/wp-content/uploads/2016/03/logo_electures_RGB_500px.jpg" alt="Symbolbild für Veranstaltungskategorie eLectures" width="210" height="71" />
						
						<div class="buttonsright">
						[button link="'.$data["meta_input"]["lvurl"].'" color="silver" newwindow="yes"]<img class="alignnone size-full wp-image-1960" src="http://onlinecampus-server.at/vphneu/wp-content/uploads/2016/03/zur-anmeldung.png" alt="Zur Anmeldung (PH Online)" width="263" height="50" />[/button]

						<div class="clearfix"></div>
						<a style="margin-left: 15px;" href="'.$data["meta_input"]["course_room"].'" target="_blank"><img class="alignnone size-full wp-image-2059" src="http://onlinecampus-server.at/vphneu/wp-content/uploads/2016/03/zum-lernraum.png" alt="Zum virtuellen Lernraum" width="238" height="39" /></a>
						</div>
						<div class="clearfix"></div>
						
						'.$post_content.'
						
						
						[tabs slidertype="top tabs"] [tabcontainer] [tabtext]Teilnahmekriterien & Info [/tabtext] [tabtext]Lernziele[/tabtext] [tabtext]Voraussetzungen[/tabtext] [/tabcontainer] [tabcontent] [tab]'.$admission.'[/tab] [tab]'.$learningObjectives.'[/tab] [tab] '.$recommendedPrerequisites.'[/tab] [/tabcontent] [/tabs]
						
					';

		        break;
		    case "Online-Seminar":

					$content ='<img class="alignleft wp-image-354 size-full" src="http://onlinecampus-server.at/vphneu/wp-content/uploads/2016/03/Logo_KOS_500px_RGB-transparent.png" alt="Symbolbild für Veranstaltungskategorie Online Seminar" width="210" height="71" />
						
						<div class="buttonsright">
						[button link="'.$data["meta_input"]["lvurl"].'" color="silver" newwindow="yes"]<img class="alignnone size-full wp-image-1960" src="http://onlinecampus-server.at/vphneu/wp-content/uploads/2016/03/zur-anmeldung.png" alt="Zur Anmeldung (PH Online)" width="263" height="50" />[/button]
						</div>
						
						
						'.$post_content.'
						
						
						[tabs slidertype="top tabs"] [tabcontainer] [tabtext]Teilnahmekriterien & Info [/tabtext] [tabtext]Lernziele[/tabtext] [tabtext]Voraussetzungen[/tabtext] [/tabcontainer] [tabcontent] [tab]'.$admission.'[/tab] [tab]'.$learningObjectives.'[/tab] [tab] '.$recommendedPrerequisites.'[/tab] [/tabcontent] [/tabs]
						
					';

		        break;
		    case "eWorkshop":


					$content ='<div class="buttonsright">
						[button link="'.$data["meta_input"]["lvurl"].'" color="silver" newwindow="yes"]<img class="alignnone size-full wp-image-1960" src="http://onlinecampus-server.at/vphneu/wp-content/uploads/2016/03/zur-anmeldung.png" alt="Zur Anmeldung (PH Online)" width="263" height="50" />[/button]
						</div>
						
						
						'.$post_content.'
						
						
						[tabs slidertype="top tabs"] [tabcontainer] [tabtext]Teilnahmekriterien & Info [/tabtext] [tabtext]Lernziele[/tabtext] [tabtext]Voraussetzungen[/tabtext] [/tabcontainer] [tabcontent] [tab]'.$admission.'[/tab] [tab]'.$learningObjectives.'[/tab] [tab] '.$recommendedPrerequisites.'[/tab] [/tabcontent] [/tabs]
						
						';


		        break;
		        
		       default:
		       
		       	$content = $post_content;
		       	break;
		}
		
		return Encoding::toUTF8($content);
				
		
	}
	
	private function varDumpToString($var) {
    ob_start();
    var_dump($var);
    $result = ob_get_clean();
    return $result;
	}
	
	private function linkify($value, $protocols = array('http', 'https'), array $attributes = array()){
        // Link attributes
        $attr = '';
        foreach ($attributes as $key => $val) {
            $attr = ' ' . $key . '="' . htmlentities($val) . '"';
        }
        
        $links = array();
        
        // Extract existing links and tags
        $value = preg_replace_callback('~(<a .*?>.*?</a>|<.*?>)~i', function ($match) use (&$links) { return '<' . array_push($links, $match[1]) . '>'; }, $value);
        
        // Extract text links for each protocol
        foreach ((array)$protocols as $protocol) {
            switch ($protocol) {
                case 'http':
                case 'https':   $value = preg_replace_callback('~(?:(https?)://([^\s<]+)|(www\.[^\s<]+?\.[^\s<]+))(?<![\.,:])~i', function ($match) use ($protocol, &$links, $attr) { if ($match[1]) $protocol = $match[1]; $link = $match[2] ?: $match[3]; return '<' . array_push($links, "<a $attr href=\"$protocol://$link\">$link</a>") . '>'; }, $value); break;
                case 'mail':    $value = preg_replace_callback('~([^\s<]+?@[^\s<]+?\.[^\s<]+)(?<![\.,:])~', function ($match) use (&$links, $attr) { return '<' . array_push($links, "<a $attr href=\"mailto:{$match[1]}\">{$match[1]}</a>") . '>'; }, $value); break;
                case 'twitter': $value = preg_replace_callback('~(?<!\w)[@#](\w++)~', function ($match) use (&$links, $attr) { return '<' . array_push($links, "<a $attr href=\"https://twitter.com/" . ($match[0][0] == '@' ? '' : 'search/%23') . $match[1]  . "\">{$match[0]}</a>") . '>'; }, $value); break;
                default:        $value = preg_replace_callback('~' . preg_quote($protocol, '~') . '://([^\s<]+?)(?<![\.,:])~i', function ($match) use ($protocol, &$links, $attr) { return '<' . array_push($links, "<a $attr href=\"$protocol://{$match[1]}\">{$match[1]}</a>") . '>'; }, $value); break;
            }
        }
        
        // Insert all link
        return preg_replace_callback('/<(\d+)>/', function ($match) use (&$links) { return $links[$match[1] - 1]; }, $value);
    }

}
