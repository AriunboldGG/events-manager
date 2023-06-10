<?php
class EM_Calendar extends EM_Object {
	
	public static function init(){
		//nothing to init anymore
	}
	
	public static function get( $args ){
	
		global $wpdb, $wp_rewrite;
	 	
		$calendar_array = array();
		$calendar_array['cells'] = array();

		$args = apply_filters('em_calendar_get_args', $args);
		$original_args = $args;
		$args = self::get_default_search($args);
		$full = $args['full']; //For ZDE, don't delete pls
		//figure out what month to look for, if we need to
		if( empty($args['month']) && is_array($args['scope']) ){
			//if a scope is supplied, figure out the month/year we're after, which will be between these two dates.
			$EM_DateTime = new EM_DateTime($args['scope'][0]);
			$scope_start = $EM_DateTime->getTimestamp();
			$scope_end = $EM_DateTime->modify($args['scope'][1])->getTimestamp();
			$EM_DateTime->setTimestamp( $scope_start + ($scope_end - $scope_start)/2 );
			$month = $args['month'] = $EM_DateTime->format('n');
			$year = $args['year'] = $EM_DateTime->format('Y');
		}else{
			//if month/year supplied, we use those or later on default to current month/year
			$month = $args['month']; 
			$year = $args['year'];
		}
		$long_events = $args['long_events'];
		$limit = $args['limit']; //limit arg will be used per day and not for events search
		
		$start_of_week = get_option('start_of_week');
		
		if( !(is_numeric($month) && $month <= 12 && $month > 0) )   {
			$month = date('m', current_time('timestamp')); 
		}
		if( !( is_numeric($year) ) ){
			$year = date('Y', current_time('timestamp'));
		}  
		  
		// Get the first day of the month 
		$month_start = mktime(0,0,0,$month, 1, $year);
		$calendar_array['month_start'] = $month_start;
		
		// Get friendly month name 		
		$month_name = date('M',$month_start);
		// Figure out which day of the week 
		// the month starts on. 
		$month_start_day = date('D', $month_start);
	  
	  	switch($month_start_day){ 
			case "Sun": $offset = 0; break; 
			case "Mon": $offset = 1; break; 
			case "Tue": $offset = 2; break; 
			case "Wed": $offset = 3; break; 
			case "Thu": $offset = 4; break; 
			case "Fri": $offset = 5; break; 
			case "Sat": $offset = 6; break;
		}       
		//We need to go back to the WP defined day when the week started, in case the event day is near the end
		$offset -= $start_of_week;
		if($offset<0)
			$offset += 7;
		
		// determine how many days are in the last month.
		$month_last = $month-1;
		$month_next = $month+1;
		$year_last = $year; 
		$year_next = $year;
		
		if($month == 1) { 
		   $month_last = 12;
		   $year_last = $year -1;
		}elseif($month == 12){
			$month_next = 1;
			$year_next = $year + 1; 
		}
		$calendar_array['month_next'] = $month_next;
		$calendar_array['month_last'] = $month_last;
		$calendar_array['year_last'] = $year_last;
		$calendar_array['year_next'] = $year_next;
		
		$num_days_last = self::days_in_month($month_last, $year_last);
		 
		// determine how many days are in the current month. 
		$num_days_current = self::days_in_month($month, $year);
		// Build an array for the current days 
		// in the month 
		for($i = 1; $i <= $num_days_current; $i++){ 
		   $num_days_array[] = mktime(0,0,0,$month, $i, $year); 
		}
		// Build an array for the number of days 
		// in last month 
		for($i = 1; $i <= $num_days_last; $i++){ 
		    $num_days_last_array[] = mktime(0,0,0,$month_last, $i, $year_last); 
		}
		// If the $offset from the starting day of the 
		// week happens to be Sunday, $offset would be 0, 
		// so don't need an offset correction. 
	
		if($offset > 0){ 
		    $offset_correction = array_slice($num_days_last_array, -$offset, $offset); 
		    $new_count = array_merge($offset_correction, $num_days_array); 
		    $offset_count = count($offset_correction); 
		} else { // The else statement is to prevent building the $offset array. 
		    $offset_count = 0; 
		    $new_count = $num_days_array;
		}
		// count how many days we have with the two 
		// previous arrays merged together 
		$current_num = count($new_count); 
	
		// Since we will have 5 HTML table rows (TR) 
		// with 7 table data entries (TD) 
		// we need to fill in 35 TDs 
		// so, we will have to figure out 
		// how many days to appened to the end 
		// of the final array to make it 35 days. 	
		if( !empty($args['number_of_weeks']) && is_numeric($args['number_of_weeks']) ){
			$num_weeks = $args['number_of_weeks'];
		}elseif($current_num > 35){ 
			$num_weeks = 6;
		}else{ 
			$num_weeks = 5; 
		}
		$outset = ($num_weeks * 7) - $current_num;
		// Outset Correction 
		for($i = 1; $i <= $outset; $i++){ 
		   $new_count[] = mktime(0,0,0,$month_next, $i, $year_next);  
		}
		// Now let's "chunk" the $all_days array 
		// into weeks. Each week has 7 days 
		// so we will array_chunk it into 7 days. 
		$weeks = array_chunk($new_count, 7);    
		  
		//Get an array of arguments that don't include default valued args
		$link_args = self::get_query_args($args);
		$link_args['ajaxCalendar'] = 1;
		$previous_url = esc_url_raw(add_query_arg( array_merge($link_args, array('mo'=>$month_last, 'yr'=>$year_last)) ));
		$next_url = esc_url_raw(add_query_arg( array_merge($link_args, array('mo'=>$month_next, 'yr'=>$year_next)) ));
		
	 	$weekdays = array('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday');
	 	// if(!empty($args['full'])) {
 		    if( get_option('dbem_full_calendar_abbreviated_weekdays') ) $weekdays = array('Sun','Mon','Tue','Wed','Thu','Fri','Sat');
 			$day_initials_length =  get_option('dbem_full_calendar_initials_length');
 		// } else {
 		//     if ( get_option('dbem_small_calendar_abbreviated_weekdays') ) $weekdays = array('Sun','Mon','Tue','Wed','Thu','Fri','Sat');
	 	// 	$day_initials_length = get_option('dbem_small_calendar_initials_length');
 		// }
 		
		for( $n = 0; $n < $start_of_week; $n++ ) {   
			$last_day = array_shift($weekdays);     
			$weekdays[]= $last_day;
		}
	   
		$days_initials_array = array();
		//translate day names, some languages may have special circumstances
		if( $day_initials_length == 1 && in_array(EM_ML::$current_language, array('zh_CN', 'zh_TW')) ){
			//Chinese single initial day names are different
			$days_initials_array = array('日','一','二','三','四','五','六');
		}else{
			//all other languages
			foreach($weekdays as $weekday) {
				$days_initials_array[] = esc_html(self::translate_and_trim($weekday, $day_initials_length));
			}
		}
		
		$calendar_array['links'] = array( 'previous_url'=>$previous_url, 'next_url'=>$next_url);
		$calendar_array['row_headers'] = $days_initials_array;
		
		// Now we break each key of the array  
		// into a week and create a new table row for each 
		// week with the days of that week in the table data 
	  
		$i = 0;
		$current_date = date('Y-m-d', current_time('timestamp'));
		$week_count = 0;
		foreach ( $weeks as $week ) {
			foreach ( $week as $d ) {
				$date = date('Y-m-d', $d);
				$calendar_array['cells'][$date] = array('date'=>$d, 'events'=>array(), 'events_count'=>0); //set it up so we have the exact array of dates to be filled
				if ($i < $offset_count) { //if it is PREVIOUS month
					$calendar_array['cells'][$date]['type'] = 'pre';
				}
				if (($i >= $offset_count) && ($i < ($num_weeks * 7) - $outset)) { // if it is THIS month
					if ( $current_date == $date ){	
						$calendar_array['cells'][$date]['type'] = 'today';
					}
				} elseif (($outset > 0)) { //if it is NEXT month
					if (($i >= ($num_weeks * 7) - $outset)) {
						$calendar_array['cells'][$date]['type'] = 'post';
					}
				}
				$i ++;
			}
			$week_count++;
		}
		
		//query the database for events in this time span with $offset days before and $outset days after this month to account for these cells in the calendar
		$scope_datetime_start = new EM_DateTime("{$year}-{$month}-1");
		$scope_datetime_end = new EM_DateTime($scope_datetime_start->format('Y-m-t'));
		$scope_datetime_start->sub('P'.$offset.'D');
		$scope_datetime_end->add('P'.$outset.'D');
		//we have two methods here, one for high-volume event sites i.e. many thousands of events per month, and another for thousands or less per month.
		$args['array'] = true; //we're getting an array first to avoid extra queries during object creation
		unset($args['month']);
		unset($args['year']);
		unset($args['limit']); //limits in the events search won't help
		if( defined('EM_CALENDAR_OPT') && EM_CALENDAR_OPT ){
			//here we loop through each day, query that specific date, and then compile a list of event objects
			//in this mode the count will never be accurate, we're grabing at most (31 + 14 days) * (limit + 1) events to reduce memory loads
			$args['limit'] = $limit + 1;
			$scope_datetime_loop = $scope_datetime_start->format('U');
			$events = array();
			while( $scope_datetime_loop <= $scope_datetime_end->format('U') ){
				$args['scope'] = date('Y-m-d', $scope_datetime_loop);
				foreach( EM_Events::get($args) as $event ){
					$events[$event['event_id']] = $event;
				}
				$scope_datetime_loop += (86400); //add a day
			}
		}else{
			//just load all the events for this time-range
			$args['scope'] = array( $scope_datetime_start->format('Y-m-d'), $scope_datetime_end->format('Y-m-d'));
			if ( defined( 'ICT_PORTAL_MAIN' ) ) {
				$events = array();

				$tw_cache_created = 0;
				
				// Sub Sites Posts
				$tw_get_subsite_urls = tw_get_subsite_urls();
				foreach( $tw_get_subsite_urls as $tw_get_subsite_url ) {
					$tw_cache_expire_time = 60 * 60 * 1;
					$tw_cache_name        = 'tw_all_sites_calendar_latest_events_' . esc_sql( esc_url( $tw_get_subsite_url ) ) . ( ! empty( $args['scope'] ) && is_array( $args['scope'] ) ? serialize( $args['scope'] ) : '' );
					$tw_cache_data        = get_transient( $tw_cache_name );
					$tw_cache_data        = $tw_cache_data && is_array( $tw_cache_data ) ? $tw_cache_data : array();
					$tw_cache_not_expired = get_transient( $tw_cache_name . '_not_expired' );
					
					$crr_new_data  = array();

					if ( $tw_cache_not_expired ) {
						$crr_new_data = $tw_cache_data;
					} else {
						if ( $tw_cache_created > 10 ) {
							$crr_new_data = $tw_cache_data;
						} else {
							$tw_cache_created++;
						
							$response = wp_remote_get( trailingslashit( $tw_get_subsite_url ) . 'wp-json/ict/v1/events', array(
								'timeout' => 1,
								'body' => array(
										'args' => $args
								)
							) );
							if ( ! is_wp_error( $response ) && is_array( $response ) && ! empty( $response['response'] ) && ! empty( $response['response']['code'] ) && intval( $response['response']['code'] ) === 200  ) {
								if ( ! empty( $response['body'] ) ) {
									$response_body = json_decode( $response['body'], true );

									if ( ! empty( $response_body ) && isset( $response_body['result'] ) ) {
										$crr_new_data = $response_body['result'];
										set_transient( $tw_cache_name                 , $crr_new_data, $tw_cache_expire_time * 100 );
										set_transient( $tw_cache_name . '_not_expired', true         , $tw_cache_expire_time );
									}
								}
							} else {
								// Set old data, Connection Failed
								$crr_new_data = $tw_cache_data;
							}
						}
					}
					
					// Merge Datas
					$events = array_merge( $events, $crr_new_data );
				}
			} else {
				$events = EM_Events::get($args);
			}
		}
		//back to what it was
		$args['month'] = $month; 
		$args['year'] = $year;
		$args['limit'] = $limit; 
	
		$event_format = get_option('dbem_full_calendar_event_format'); 
		$event_title_format = get_option('dbem_small_calendar_event_title_format');
		$event_title_separator_format = get_option('dbem_small_calendar_event_title_separator');
		
		$eventful_days= array();
		$eventful_days_count = array();
		if($events){
			//Go through the events and slot them into the right d-m index
			foreach($events as $event) {
				$event = apply_filters('em_calendar_output_loop_start', $event);
				if( $long_events ){
					//If $long_events is set then show a date as eventful if there is an multi-day event which runs during that day
					$event_start = new EM_DateTime($event['event_start_date'], $event['event_timezone']);
					$event_end = new EM_DateTime($event['event_end_date'], $event['event_timezone']);
					if( $event_end->getTimestamp() > $scope_datetime_end->getTimestamp() ) $event_end = $scope_datetime_end;
					while( $event_start->getTimestamp() <= $event_end->getTimestamp() ){ //we loop until the last day of our time-range, not the end date of the event, which could be in a year
						//Ensure date is within event dates and also within the limits of events to show per day, if so add to eventful days array
						$event_eventful_date = $event_start->getDate();
						if( empty($eventful_days_count[$event_eventful_date]) || !$limit || $eventful_days_count[$event_eventful_date] < $limit ){
                            if ( defined( 'ICT_PORTAL_MAIN' ) ) {
                                //now we know this is an event that'll be used, convert it to an object
                                if( empty($eventful_days[$event_eventful_date]) || !is_array($eventful_days[$event_eventful_date]) ) $eventful_days[$event_eventful_date] = array();
                                //add event to array with a corresponding timestamp for sorting of times including long and all-day events
                                $event_ts_marker = ($event['event_all_day']) ? 0 : (int) $event_start->getTimestamp();
                                while( !empty($eventful_days[$event_eventful_date][$event_ts_marker]) ){
                                    $event_ts_marker++; //add a second
                                }
                                $eventful_days[$event_eventful_date][$event_ts_marker] = $event;
                            } else {
                                //now we know this is an event that'll be used, convert it to an object
                                $EM_Event = EM_MS_GLOBAL ? em_get_event($event['post_id'], $event['blog_id']) : $EM_Event = em_get_event($event['post_id'], 'post_id');
                                
                                if( empty($eventful_days[$event_eventful_date]) || !is_array($eventful_days[$event_eventful_date]) ) $eventful_days[$event_eventful_date] = array();
                                //add event to array with a corresponding timestamp for sorting of times including long and all-day events
                                $event_ts_marker = ($EM_Event->event_all_day) ? 0 : (int) $event_start->getTimestamp();
                                while( !empty($eventful_days[$event_eventful_date][$event_ts_marker]) ){
                                    $event_ts_marker++; //add a second
                                }
                                $eventful_days[$event_eventful_date][$event_ts_marker] = $EM_Event;
                            }
						}
						//count events for that day
						$eventful_days_count[$event_eventful_date] = empty($eventful_days_count[$event_eventful_date]) ? 1 : $eventful_days_count[$event_eventful_date]+1;
						$event_start->add('P1D');
					}
				}else{
					//Only show events on the day that they start
					$event_eventful_date = $event['event_start_date'];
					if( empty($eventful_days_count[$event_eventful_date]) || !$limit || $eventful_days_count[$event_eventful_date] < $limit ){
						$EM_Event = EM_MS_GLOBAL ? em_get_event($event['post_id'], $event['blog_id']) : em_get_event($event['post_id'], 'post_id');
						if( empty($eventful_days[$event_eventful_date]) || !is_array($eventful_days[$event_eventful_date]) ) $eventful_days[$event_eventful_date] = array();
						//add event to array with a corresponding timestamp for sorting of times including long and all-day events
						$event_ts_marker = ($EM_Event->event_all_day) ? 0 : (int) $EM_Event->start()->getTimestamp();
						while( !empty($eventful_days[$event_eventful_date][$event_ts_marker]) ){
							$event_ts_marker++; //add a second
						}
						$eventful_days[$event_eventful_date][$event_ts_marker] = $EM_Event;
					}
					//count events for that day
					$eventful_days_count[$event['event_start_date']] = empty($eventful_days_count[$event['event_start_date']]) ? 1 : $eventful_days_count[$event['event_start_date']]+1;
				}
			}
		}
		//generate a link argument string containing event search only
		$day_link_args = self::get_query_args( array_intersect_key($original_args, EM_Events::get_post_search($args, true) ));
		//get event link 
		if( get_option("dbem_events_page") > 0 ){
			$event_page_link = get_permalink(get_option("dbem_events_page")); //PAGE URI OF EM
		}else{
			if( $wp_rewrite->using_permalinks() ){
				$event_page_link = trailingslashit(home_url()).EM_POST_TYPE_EVENT_SLUG.'/'; //don't use EM_URI here, since ajax calls this before EM_URI is defined.
			}else{
			    //not needed atm anyway, but we use esc_url later on, in case you're wondering ;) 
				$event_page_link = add_query_arg(array('post_type'=>EM_POST_TYPE_EVENT), home_url()); //don't use EM_URI here, since ajax calls this before EM_URI is defined.
			}
		}
		$event_page_link_parts = explode('?', $event_page_link); //in case we have other plugins (e.g. WPML) adding querystring params to the end 
		foreach($eventful_days as $day_key => $events) {
			if( array_key_exists($day_key, $calendar_array['cells']) ){
				//Get link title for this date
				$events_titles = array();
				foreach($events as $event) {
					if( !$limit || count($events_titles) < $limit ){
                        if ( defined( 'ICT_PORTAL_MAIN' ) ) {
                            $events_titles[] = $event['event_name'];
                        } else {
                            $events_titles[] = $event->output($event_title_format);
                        }
					}else{
						$events_titles[] = get_option('dbem_display_calendar_events_limit_msg');
						break;
					}
				}
				$calendar_array['cells'][$day_key]['link_title'] = implode( $event_title_separator_format, $events_titles);
							
				//Get the link to this calendar day
				if( $eventful_days_count[$day_key] > 1 || !get_option('dbem_calendar_direct_links')  ){
					if( $wp_rewrite->using_permalinks() && !defined('EM_DISABLE_PERMALINKS') ){
						$calendar_array['cells'][$day_key]['link'] = trailingslashit($event_page_link_parts[0]).$day_key."/";
						if( !empty($event_page_link_parts[1]) ) $calendar_array['cells'][$day_key]['link'] .= '?' . $event_page_link_parts[1];
    					//add query vars to end of link
    					if( !empty($day_link_args) ){
    						$calendar_array['cells'][$day_key]['link'] = esc_url_raw(add_query_arg($day_link_args, $calendar_array['cells'][$day_key]['link']));
    					}
					}else{
    					$day_link_args['calendar_day'] = $day_key;
						$calendar_array['cells'][$day_key]['link'] = esc_url_raw(add_query_arg($day_link_args, $event_page_link));
					}
				}else{
					foreach($events as $EM_Event){
                        $calendar_array['cells'][$day_key]['link'] =  defined( 'ICT_PORTAL_MAIN' ) ? '' : $EM_Event->get_permalink();
					}
				}
				//Add events to array
				$calendar_array['cells'][$day_key]['events_count'] = $eventful_days_count[$day_key];
				$calendar_array['cells'][$day_key]['events'] = $events;
			}
		}
        
		return apply_filters('em_calendar_get',$calendar_array, $args);
	}
	
	public static function output($args = array(), $wrapper = true) {
		//Let month and year REQUEST override for non-JS users
		$args['limit'] = !empty($args['limit']) ? $args['limit'] : get_option('dbem_display_calendar_events_limit'); //limit arg will be used per day and not for events search
		if( !empty($_REQUEST['mo']) || !empty($args['mo']) ){
			$args['month'] = !empty( $_REQUEST['mo'] ) ? $_REQUEST['mo']:$args['mo'];	
		}
		if( !empty($_REQUEST['yr']) || !empty($args['yr']) ){
			$args['year'] = (!empty($_REQUEST['yr'])) ? $_REQUEST['yr']:$args['yr'];
		}
		$calendar_array  = self::get($args);
		$template = (!empty($args['full'])) ? 'templates/calendar-full.php':'templates/calendar-small.php';
		ob_start();
		em_locate_template($template, true, array('calendar'=>$calendar_array,'args'=>$args));
		if($wrapper){
			$calendar = '<div id="em-calendar-'.rand(100,200).'" class="em-calendar-wrapper">'.ob_get_clean().'</div>';
		}else{
			$calendar = ob_get_clean();
		}
		return apply_filters('em_calendar_output', $calendar, $args);
	}


	public static function days_in_month($month, $year) {
		return date('t', mktime(0,0,0,$month,1,$year));
	}
	 
	public static function translate_and_trim($string, $length = 1) {
	    if( $length > 0 ){
			if(function_exists('mb_substr')){ //fix for diacritic calendar names
			    return mb_substr(translate($string), 0, $length, 'UTF-8');
			}else{ 
	    		return substr(translate($string), 0, $length); 
	    	}
	    }
	    return translate($string);
	}  
	
	/**
	 * Gets all the EM-supported search arguments and removes the ones that aren't the default in the $args array. Returns the arguments that have non-default values.
	 * @param array $args
	 * @return array
	 */
	public static function get_query_args( $args ){
		unset($args['month']); unset($args['year']);
		$default_args = self::get_default_search(array());
		foreach($default_args as $arg_key => $arg_value){
			if( !isset($args[$arg_key]) ){
				unset($args[$arg_key]);				
			}else{
			    //check that argument doesn't match default
    		    $arg = array($args[$arg_key], $arg_value);
    		    foreach($arg as $k => $v){
        		    if( is_string($v) || is_numeric($v) ){
        		        //strings must be typecast to avoid false positive for something like 'string' == 0
        		        $arg[$k] = (string) $v;
        		    }elseif( is_bool($v) ){
        		        $arg[$k] = $v ? '1':'0';
        		    }
    		    }
			    if( $arg[0] == $arg[1] ){
			        //argument same as default so it's not needed in link
    				unset($args[$arg_key]);	
    		    }
			}
		}
		//clean up post type conflicts in a URL
		if( !empty($args['event']) ){
			$args['event_id'] = $args['event'];
			unset($args['event']);
		}
		if( !empty($args['location']) ){
			$args['location_id'] = $args['location'];
			unset($args['location']);
		}
		return $args;
	}
	
	/**
	 * DEPRECATED - use EM_Calendar::get_query_args() instead and manipulate the array.
	 * Left only to prevent 3rd party add-ons from potentially breaking if they use this
	 * Helper function to create a link querystring from array which contains arguments with only values that aren't defuaults. 
	 */
	public static function get_link_args($args = array(), $html_entities=true){
	    $args = self::get_query_args($args);
		$qs_array = array();
		foreach($args as $key => $value){
			if(is_array($value)){
				$value = implode(',',$value);
			}
			$qs_array[] = "$key=".urlencode($value);
		}
		return ($html_entities) ? implode('&amp;', $qs_array) : implode('&', $qs_array);
	}
		
	
	/* 
	 * Adds custom calendar search defaults
	 * @param array $array_or_defaults may be the array to override defaults
	 * @param array $array
	 * @return array
	 * @uses EM_Object#get_default_search()
	 */
	public static function get_default_search( $array_or_defaults = array(), $array = array() ){
		//These defaults aren't for db queries, but flags for what to display in calendar output
		$defaults = array( 
			'recurring' => false, //we don't initially look for recurring events only events and recurrences of recurring events
			'full' => 0, //Will display a full calendar with event names
			'long_events' => 0, //Events that last longer than a day
			'scope' => false,
			'status' => 1, //approved events only
			'town' => false,
			'state' => false,
			'country' => false,
			'region' => false,
			'blog' => get_current_blog_id(),
			'orderby' => get_option('dbem_display_calendar_orderby'),
			'order' => get_option('dbem_display_calendar_order'),
			'number_of_weeks' => false, //number of weeks to be displayed in the calendar
		    'limit' => get_option('dbem_display_calendar_events_limit'),
			'post_id' => false
		);
		//sort out whether defaults were supplied or just the array of search values
		if( empty($array) ){
			$array = $array_or_defaults;
		}else{
			$defaults = array_merge($defaults, $array_or_defaults);
		}
		$defaults['long_events'] = !empty($array['full']) ? get_option('dbem_full_calendar_long_events') : get_option('dbem_small_calendar_long_events');
		//specific functionality
		if(is_multisite()){
			global $bp;
			if( !is_main_site() && !array_key_exists('blog',$array) ){
				//not the main blog, force single blog search
				$array['blog'] = get_current_blog_id();
			}elseif( empty($array['blog']) && get_site_option('dbem_ms_global_events') ) {
				$array['blog'] = false;
			}
		}
		$atts = parent::get_default_search($defaults, $array);
		$atts['full'] = ($atts['full']==true) ? 1:0;
		$atts['long_events'] = ($atts['long_events']==true) ? 1:0;
		return apply_filters('em_calendar_get_default_search', $atts, $array, $defaults);
	}
} 
add_action('init', array('EM_Calendar', 'init'));