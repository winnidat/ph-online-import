<?php
use \ForceUTF8\Encoding;
use \PHOnlineToken\Token;


class PhOnlineEventImporterAustria{
	
	private $log = "";
	private $numUpdated = 0;
	private $numCreated = 0;
	private $categories = array("Online-Seminar", "eLecture", "eWorkshop");
	private $importrun = 0;
	private $importStartedTimestamp = 0;
	private $token = null;
	private $url_token = null;
	private $url_ects = null;
	private $ph = null;



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
		ini_set('max_execution_time', 700000);
	}
	
	public function import($token){

		$tok = $this->token[$token];
		$this->ph = $tok['ph'];
		$this->url_token = $tok['url2']; 
		$this->url_ects = $tok['url3'];
		$this->startImport($tok['url1'],$tok['timemode']);
	}

	public function startImport($token,$timemode){

		$this->importid = uniqid();
		$this->importStartedTimestamp = time();
		
		$this->setLongerExecutionTime();

		
		$this->logLine("IMPORT START");
		$this->logLine(date("c", current_time( 'timestamp' )));
		

		switch ($timemode){
			case "":
			$fromtime = strtotime("today -100 days");
			$untiltime = strtotime("today +1000 days");
			break;
			case "big_first_half":
			$fromtime = strtotime("today -100 days");
			$untiltime = strtotime("today +300 days");
			break;	
			case "big_second_half":
			$fromtime = strtotime("today +299 days");
			$untiltime = strtotime("today +700 days");
			break;	
			case "first_half":
			$fromtime = strtotime("today -100 days");
			$untiltime = strtotime("today +100 days");
			break;
			case "second_half":
			$fromtime = strtotime("today +99 days");
			$untiltime = strtotime("today +200 days");
			break;
			case "third_half":
			$fromtime = strtotime("today +199 days");
			$untiltime = strtotime("today +350 days");
			break;
			case "fourth_half":
			$fromtime = strtotime("today +349 days");
			$untiltime = strtotime("today +500 days");
			break;
			case "fifth_half":
			$fromtime = strtotime("today +349 days");
			$untiltime = strtotime("today +500 days");
			break;
                        case "sixth_half":
			$fromtime = strtotime("today +349 days");
			$untiltime = strtotime("today +500 days");
			break;
			case "first_quarter":
			$fromtime = strtotime("today -50 days");
			$untiltime = strtotime("today +25 days");
			break;
			case "second_quarter":
			$fromtime = strtotime("today +24 days");
			$untiltime = strtotime("today +100 days");
			break;
			case "third_quarter":
			$fromtime = strtotime("today +99 days");
			$untiltime = strtotime("today +175 days");
			break;
			case "fourth_quarter":
			$fromtime = strtotime("today +174 days");
			$untiltime = strtotime("today +250 days");
			break;
	        case "fifth_quarter":
			$fromtime = strtotime("today +249 days");
			$untiltime = strtotime("today +320 days");
			break;
			case "sixth_quarter":
			$fromtime = strtotime("today +319 days");
			$untiltime = strtotime("today +410 days");
			break;
			case "seventh_quarter":
			$fromtime = strtotime("today +409 days");
			$untiltime = strtotime("today +500 days");
			break;
			case "eight_quarter":
			$fromtime = strtotime("today +499 days");
			$untiltime = strtotime("today +600 days");
			break;
		}




		$url = $token ."&fromDate=".date("Ymd", $fromtime)."&untilDate=".date("Ymd", $untiltime);		

		$this->logLine("URL: ".$url);
		$this->logLine("-------");


	    //$url = plugin_dir_path( __FILE__ )."sample2.xml";

		//localxml
		//$xml = simplexml_load_string(file_get_contents($url)); 
		//$content = Encoding::toUTF8($this->getUrl($url));
		$xml = simplexml_load_string($this->getUrl($url));
		//$xml->save("sample.xml");

		//$xml = $this->loadXML($url);

		$events = $xml->xpath('/xCal:iCalendar/xCal:vcalendar/xCal:vevent');
        $oldcourseid = -9999;
		foreach($events as $e){
			$event = $e->children('http://campusonline.xcal.at/');
			$uid = (string)$event->uid;

			$this->logLine("-------");

			$this->logLine("START IMPORT OF EVENT ".$uid);
		    $add_condition=TRUE;
		    if($this->ph == "PH Burgenland"){
		    	if ((string)$event->location == "Online-Plattform Virtuelle PH"){
		    		$add_condition = false;
		    	}
		    	else {
		    		$add_condition = true;
		    	}
		    	$this->logLine($add_conditon);
		    }

			if(strpos((string)$event->description,'digi') !== FALSE && $add_condition/* !$this->is_private_event($event->summary) && $this->is_fixed_event($event->status) && $this->in_category((string)$event->categories->item)*/){				
				preg_match("/\d+$/i", $event->description["altrep"], $match);
				if(count($match) == 1){
					$courseID = $match[0];
                    if($oldcourseid != $courseID) {
					$oldcourseid = $courseID;

					$singleEvent = $this->loadEvent($courseID);

					if(((string)$singleEvent->course->courseDescription != "<![CDATA[ k.A. ]]>") && ((string)$singleEvent->course->courseDescription != "<![CDATA[ kA ]]>") ){
						$this->logLine("hmmm ");							

						$data = $this->makeEventData($event, $singleEvent);
                        						$this->logLine("hmmm ");						
						if($data !== false){
						$this->logLine("hmmm ");							

							$event_id = $this->createOrUpdateEvent($courseID, $data);
							if($event_id !== false){
								$this->logLine("SUCCESSFUL IMPORT OF EVENT ".$uid);
							}else{
								$this->logLine("FAILED IMPORT OF EVENT ".$uid);							
							}
						}
					}
				}
				}				
			}else{
				$this->logLine("EVENT IS PRIVATE, NOT FIXED OR NOT A CATEGORY ".$event->summary);							
			}							
		}

		$this->logLine("--------");
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

	private function get_ects($course_id){
		$html = file_get_contents("https://www.ph-online.ac.at/ph-bgld/wbLv.wbShowStellungInStp?pStpSpNr=".$course_id);
		libxml_use_internal_errors(true);
		$doc = new DOMDocument;
		$doc->loadHTML($html);
		$xpath = new DOMXPath($doc);
		$items = $xpath->query("//td[@class=' C']//span[@class='bold']");
		$ects = $items->item(0)->nodeValue;
		libxml_use_internal_errors(false);
		return $ects;
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

	private function get_venue($name) {

		$venue_id = null;
		$venue = get_page_by_title($name, OBJECT, 'tribe_venue');

		if($venue == null){
			$venue_id = tribe_create_venue(array("Venue" => $name));	
		}else{
			$venue_id = $venue->ID;
		}

		return $venue_id;
	}

	private function loadEvent($id){

		if(!is_numeric($id)){

			return false;

		}
		//wp xml
		$xml = $this->loadXML($this->url_token."&courseID=".$id);
		//local xml
		//$xml = simplexml_load_string(file_get_contents(utf8_encode($this->url_token."&courseID=".$id)));
		return $xml;

	}

	private function makeEventData($event, $singleEvent) {

		$data = array();
		$startTimestamp = strtotime((string)$event->dtstart);
		$endTimestamp = strtotime((string)$event->dtend);
		$post_content = (string)$singleEvent->course->courseDescription;
		//tagtester
		//$post_content .= "[digikomp A]"." "."[digikomp H]"." "."[digitag online]"; 

		//categories
		$extracat = array();
		$tags = array();

		preg_match("/\[(.*?digikomp.*?)\]/iu", $post_content, $digikomp);
		if (count($digikomp) == 0){
			return false;
		}
		else{
			$digik = str_replace('[','',str_replace(']',',',$digikomp[1]));
			$matches = explode(',', $digik);
			foreach($matches as $match){
				switch (ltrim($match)) {
				//cats
					case "digikomp A":
					$extracat[] = $this->get_tax("A – Digitale Kompetenzen und informatische Bildung");
					break;
					case "digikomp B":
					$extracat[] = $this->get_tax("B – Digital Leben");
					break;
					case "digikomp C":
					$extracat[] = $this->get_tax("C – Digital Materialien gestalten");
					break;
					case "digikomp D":
					$extracat[] = $this->get_tax("D – Digital Lehren und Lernen"); 
					break;
					case "digikomp E":
					$extracat[] = $this->get_tax("E – Digital Lehren und Lernen im Fach");
					break;
					case "digikomp F":
					$extracat[] = $this->get_tax("F – Digital Verwalten");
					break;
					case "digikomp G":
					$extracat[] = $this->get_tax("G – Digitale Schulgemeinschaft");
					break;
					case "digikomp H":
					$extracat[] = $this->get_tax("H – Digital-inklusive Professionsentwicklung");
					break;
					case "Reihe: DigiFD":
					$extracat[] = $this->get_tax("H – Digital-inklusive Professionsentwicklung");
					break;

				//tags
					case "digitag online":
					$tags[] = "online";
					break;
					case "digitag präsenz":
					$tags[] = "präsenz";
					break;
					case "digitag blended":
					$tags[] = "blended learning";
					break;
				}

			}	
			$post_content = str_replace("[".$digikomp[1]."]", "", $post_content);
		}
		$cats = $extracat;
		$data["tags"] = $tags; 



		$organizer = array();
		$organizer["OrganizerID"] = "";
		$organizer_id = $this->get_organizer($this->ph);
		$organizer["OrganizerID"][] = $organizer_id;

		$venue = array();
		$venue["Venue"] = (string)$event->location;
		$venue["VenueID"]  = $this->get_venue($venue["Venue"]); 

		foreach($singleEvent->course->contacts->person as $person){
			$givenName = (string)$person->name->given;
			$familyName = (string)$person->name->family;
			$data["meta_input"]["instructors"] .= $comma.$givenName." ".$familyName; 
			$comma = ", ";	
		}		

		$data["meta_input"]["importid"] = $this->importid;		
		$data["post_status"] = "publish";
		$data["post_title"] = (string)$singleEvent->course->courseName->text;
		$data["tax_input"] = array("tribe_events_cat" => $cats);

		$data["Venue"] = $venue;		
		$data["post_content"] = nl2br($post_content);

		$data["meta_input"]["course_order"] = (string)$singleEvent->course->courseCode;
		$data["meta_input"]["course_id"] = (string)$singleEvent->course->courseID;
		$data["meta_input"]["ects"] = $this->get_ects($data["meta_input"]["course_id"]);
		$data["meta_input"]["summary"] = (string)$event->summary;
		$data["meta_input"]["status"] = (string)$event->status;
		$data["meta_input"]["lvurl"] = (string)$event->description["altrep"];
		$data["meta_input"]["course_prerequisites"] = (string)$singleEvent->course->recommendedPrerequisites;
		$data["meta_input"]["last_import"] = (string)time();
		$data["EventStartDate"] = date("Y-m-d", $startTimestamp);
		$data["EventEndDate"] = date("Y-m-d", $startTimestamp);
		$data["meta_input"]["start_date"]= date("Y-m-d", $startTimestamp);
		$data["meta_input"]["end_date"]= date("Y-m-d", $endTimestamp);
		$data["Organizer"] = $organizer;

		if($catName == "eLecture"){
			$data["EventStartHour"] = date("H", $startTimestamp);
			$data["EventStartMinute"] = date("i", $startTimestamp);
			$data["EventEndHour"] = date("H", $startTimestamp);
			$data["EventEndMinute"] = date("i", $startTimestamp);		
		}else{
			$data["EventAllDay"] = true;			
		}



		if($catName === "eLecture"){
			$data["meta_input"]["course_room"] = $this->get_course_room((string)$singleEvent->course->admissionInfo->admissionDescription);
		}

		$data["post_content"] = $this->contentForEvent($singleEvent, $catName, $data);

		return $data;
	}



	private function makeEventTimeData($data, $wp_event_id) {


		$newData = $data;

		$startTimestamp = strtotime($data["meta_input"]["start_date"]);
		$endTimestamp = strtotime($data["meta_input"]["end_date"]);

		$newData["meta_input"]["start_date"] = $data["meta_input"]["start_date"];
		$newData["meta_input"]["end_date"] = $data["meta_input"]["end_date"];

		$newStartTimestamp = strtotime(get_post_meta($wp_event_id, "_EventStartDate", true));
		$newEndTimestamp = strtotime(get_post_meta($wp_event_id, "end_date", true));
		$this->logLine("custom keys". $newEndTimestamp);

		if($newStartTimestamp < $startTimestamp){
			$newData["EventStartDate"] =  date("Y-m-d", $newStartTimestamp);
			$newData["EventEndDate"] = date("Y-m-d", $newStartTimestamp);
			$newData["meta_input"]["start_date"] = date("Y-m-d", $newStartTimestamp);
		}

		if($newEndTimestamp > $endTimestamp){
			$newData["meta_input"]["end_date"] = date("Y-m-d", $newEndTimestamp);
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
					"key" => "lvurl",
					"value" => $data["meta_input"]["lvurl"]
					)
				),
			'post_type' => 'tribe_events',
			'post_status' => 'publish',
			'posts_per_page' => -1
			);
		
		$this->logLine(" ID:".$courseID." meta:".$data["meta_input"]["summary"]);
		$events = get_posts($args);	
        $this->logLine("Events:".count($events)." ID:".$courseID);
        if($data["meta_input"]["status"]=="abgesagt" || $data["meta_input"]["status"]=="verschoben"){
			$this->deleteEvent($events[0]->ID);
		}
		else{
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
		}

		return $event_id; 

	}

	private function createEvent($data){

		$event_id = tribe_create_event($data);

		if($event_id !== false){
			wp_set_post_tags( $event_id, $data["tags"], false );
			$this->updateTerms($event_id, $data);

			$this->logLine("EVENT CREATED ".$event_id);
		}

		return $event_id;

	}	

	private function updateEvent($id, $data){

		$event_id = tribe_update_event($id, $data);
		if($event_id !== false){
			wp_set_post_tags( $event_id, $data["tags"], false );	
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
		$hasObjectives = false;
		$hasAdmission = false;
		$hasRecommendedPrerquisites = false;
		$learningObjectives = $this->linkify((string)$event->course->learningObjectives);
		$admission = $this->linkify((string)$event->course->admissionInfo->admissionDescription);
		$recommendedPrerequisites = $this->linkify($data["meta_input"]["course_prerequisites"]);
		if(!ctype_space($admission) && $admission !=""){
			$hasAdmission = true;
			$this->logLine("hasObjectives");
		}
		if(!ctype_space($recommendedPrerquisites) && $recommendedPrerquisites !=""){
			$hasRecommendedPrerquisites = true;
			$this->logLine("hasObjectives");
		}
		if(!ctype_space($learningObjectives) && $learningObjectives !=""){
			$hasObjectives = true;
			$this->logLine("hasObjectives");
		}
		$this->logLine("hasObjectives: ".$hasObjectives."hasAdmission: ".$hasAdmission."hasRecommendedPrerquisites :".$hasRecommendedPrerquisites);

		$post_content = $this->linkify($data["post_content"]);

		switch ($category) {
					
				default:

				$content ='

				<div class="buttonsright">
					[button link="'.$data["meta_input"]["lvurl"].'" color="silver" newwindow="yes"]Zur Anmeldung (Ph-Online)[/button]
					<br>
					<div class="clearfix"></div>';
					if(!is_null($data["meta_input"]["course_room"])){
						$content .='<a style="margin-left: 15px;" href="'.$data["meta_input"]["course_room"].'" target="_blank"><img class="alignnone size-full wp-image-2059" src="http://onlinecampus-server.at/vphneu/wp-content/uploads/2016/03/zum-lernraum.png" alt="Zum virtuellen Lernraum" width="238" height="39" /></a>
					</div>
					<div class="clearfix"></div>';
				}
				

				$content .= "Referent_innen: ".$data["meta_input"]["instructors"]. "-ref- <br>";
				
				if (isset($data["meta_input"]["ects"])){
					$content .= "<b>ECTS</b>: ".$data["meta_input"]["ects"]. "<br>";
				}
				if ($hasAdmission && $hasObjectives && $hasRecommendedPrerquisites)
				{
					$content .=	$post_content.'


					[tabs slidertype="top tabs"] [tabcontainer] [tabtext]Teilnahmekriterien & Info [/tabtext] [tabtext]Lernziele[/tabtext] [tabtext]Voraussetzungen[/tabtext] [/tabcontainer] [tabcontent] [tab]'.$admission.'[/tab] [tab]'.$learningObjectives.'[/tab] [tab] '.$recommendedPrerequisites.'[/tab] [/tabcontent] [/tabs]

					';
				}

				else {

					$content .=	$post_content.'
                    
                    ';
                    if($hasObjectives || $hasAdmission  || $hasRecommendedPrerquisites){
                    $content .='[tabs slidertype="top tabs"] [tabcontainer]';
                    	if($hasAdmission){
                    		$content .='[tabtext]Teilnahmekriterien & Info [/tabtext]';
                    	}
                    	if($hasObjectives){
                    		$content .='[tabtext]Lernziele[/tabtext]';
                    	}
                    	if($hasRecommendedPrerquisites){
                    		$content .='[tabtext]Voraussetzungen[/tabtext]';
                    	}
                    	$content .='[/tabcontainer] [tabcontent]';
                    	if($hasAdmission){
                    		$content .='[tab]'.$admission.'[/tab] ';
                    	}
                    	if($hasObjectives){
                    		$content .='[tab]'.$learningObjectives.'[/tab] ';
                    	}                    	
                    	if($hasRecommendedPrerquisites){
                    		$content .='[tab] '.$recommendedPrerequisites.'[/tab]';
                    	}
                    	$content.='[/tabcontent] [/tabs]'; 
					}
				}

				
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
