<?php
/*
 Plugin Name: PH Online Import Austria
 Version: 0.1
 Author: Fabian Pimminger
 Author URI: http://fabianpimminger.com
 */

// This is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This software is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
* Wordpress PH Online Import
*
* @package    PH Online Import Austria
* @copyright  2016 Fabian Pimminger
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
* @author     Fabian Pimminger @link http://fabianpimminger.com
* @version    1.2
*/

require_once("ph-online-event-importer-austria.php");
require_once("ph-online-event-deleter.php");
require_once("Encoding.php");
require_once ("ph-online-token.php");

class PhOnlineImportPlugin{

  private static $instance;
  private $url;
  private $path;
  private $basename;
	private $prefix;
	private $importer;
	private $deleter;

  public static function get_instance() {
    if( null == self::$instance ) {
      self::$instance = new PhOnlineImportPlugin();
    }

    return self::$instance;
  }

  private function __construct() {


		$this->basename = plugin_basename( __FILE__ );
		$this->url      = plugin_dir_url( __FILE__ );
		$this->path     = plugin_dir_path( __FILE__ );
		
		$this->plugin_classes();
		$this->init();
		$this->hooks();
       
                
  }

	public function plugin_classes() {
		$this->importer = new PhOnlineEventImporterAustria();
		$this->deleter = new PhOnlineEventDeleter();
	}

	public function init() {
	}
	  
	public function hooks() {
  	add_action( 'init', array($this, 'init'));		
		add_action('cron_event', array($this, 'import_events'));
	  if(current_user_can("import")){
			add_action('parse_request', array($this, 'parse_request'));
			add_filter('query_vars', array($this, 'query_vars'));
			add_action( 'admin_menu', array($this, 'register_admin_page') );
		}

	}

	public static function activate() {
		wp_schedule_event(strtotime('tomorrow 03:30'), 'daily', 'cron_event');
		
		flush_rewrite_rules();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook('cron_event');		
	}
	
	public function schedule_import_now() {
		wp_schedule_single_event( time(), 'cron_event' );	
		wp_cron();	
	}

	
	public function import_events() {
		$this->set_last_import_time();
		
		echo "<pre>";
		$this->importer->import();
		echo "</pre>";

		die();

	}
	
	private function delete_old_events() {
		echo "<pre>";
		$this->deleter->delete();
		echo "</pre>";

		die();

	}
	
	public function parse_request($wp) {
	    if (array_key_exists('ph-online-import', $wp->query_vars) && $wp->query_vars['ph-online-import'] == 'true') {
				$this->import_events();
	    }
	
	    if (array_key_exists('ph-online-delete', $wp->query_vars) && $wp->query_vars['ph-online-delete'] == 'true') {
				$this->delete_old_events();
	    }
  }
    
  public function query_vars($vars) {
    $vars[] = 'ph-online-import';
		$vars[] = 'ph-online-delete';
    return $vars;
	}
	
	public function register_admin_page(){
		add_management_page( "Event Import Log", "Event Import Log", "manage_options", "ph-online-import-log", array($this, 'page'));
		add_management_page( "Start Event Import", "Start Event Import", "manage_options", "ph-online-start-import", array($this, 'page_start'));
	}
	
	public function get_last_import_time(){
		
		$time = get_option('ph_online_last_import');
		
		if($time !== false){
			return intval($time);
		}
		
		return 0;	
	}

	public function set_last_import_time(){
		return update_option('ph_online_last_import', time());	
	}
		
	public function page(){
		echo "<div class='wrap'>";
		$cron_jobs = wp_next_scheduled( 'cron_event' );
		
		echo "<h1>Next scheduled Import</h1><pre>";
		if($cron_jobs !== false){
			echo date("c", $cron_jobs);
		}else{
			echo "No Import scheduled";
		}
		echo "</pre>";
		
		echo"<h1>Log of last Import</h1><pre>";
		echo $this->importer->getLog();
		echo "</pre></div>";
	}

	public function page_start(){
		echo "<div class='wrap'>";
		
		echo "<h1>Status</h1>";
						
		if($this->get_last_import_time() + 900 < time()){			
			if(isset($_POST["ph_online_start_event_import"])){
				
				//Remove comment for debugging in the next line
				$this->importer->import();
				// Comment out next line for debugging
		        //$this->schedule_import_now();		
				echo "<pre>Event-Import wurde gestartet. Ergebnisse gibt es in ungefähr 15 Minuten auf dieser Seite: <a href='".home_url("/wp-admin/tools.php?page=ph-online-import-log")."'>Status-Ansicht</a></pre>";
				
			}else{			
				echo '<form method="post" action="tools.php?page=ph-online-start-import">';
			
				echo submit_button("Import starten", "primary", "ph_online_start_event_import");
			
				echo '</form>';
			}
		}else{
			echo "<pre>Import nur alle 15 Minuten moeglich.\nZur <a href='".home_url("/wp-admin/tools.php?page=ph-online-import-log")."'>Status-Ansicht</a></pre>";
		}		
		echo "</div>";
	}

}

register_activation_hook( __FILE__, array( "PhOnlineImportPlugin", 'activate' ) );
register_deactivation_hook( __FILE__, array( "PhOnlineImportPlugin", 'deactivate' ) );    

add_action( 'plugins_loaded', array( 'PhOnlineImportPlugin', 'get_instance' ) );
