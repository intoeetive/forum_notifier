<?php  

if ( ! defined('BASEPATH')) {
    exit('Invalid file request'); }


class Forum_notifier {
	var $settings		= array();
	
	var $name			= 'Forum notifier';
	var $version		= '1.0';
	var $description	= 'Sends forum notifications';
	var $settings_exist	= 'n';
	var $docs_url		= 'http://www.intoeetive.com';
	var $preferences		= array();
	
    //
	// Constructor
	//
	function Forum_notifier($settings='')
	{
		$this->settings = $settings;
	}
	
	
	//
	// Add to Database
	//
	function activate_extension ()
	{
        global $DB;
        
        // -- Add edit_field_groups
        $sql[] = $DB->insert_string('exp_extensions', array('extension_id' => '',
                                                              'class'        => 'Forum_notifier',
                                                              'method'       => 'send_immediately',
                                                              'hook'         => 'forum_submit_post_end',
                                                              'settings'     => '',
                                                              'priority'     => 10,
                                                              'version'      => $this->version,
                                                              'enabled'      => 'y'));
        $sql[] = $DB->insert_string('exp_extensions', array('extension_id' => '',
                                                              'class'        => 'Forum_notifier',
                                                              'method'       => 'send_digest',
                                                              'hook'         => 'weblog_entries_tagdata_end',
                                                              'settings'     => '',
                                                              'priority'     => 10,
                                                              'version'      => $this->version,
                                                              'enabled'      => 'y'));
        
        
        $sql[] = "CREATE TABLE `exp_forum_notifier` (
                `timesent` INT ( 10 ) NOT NULL
                )";
                
        foreach ($sql as $query)
        {
          $DB->query($query);
        }        
    
    }
    
	//
	// Change Settings
	//
	function settings()
	{
		$settings = array();

		// Complex:
		// [variable_name] => array(type, values, default value)
		// variable_name => short name for setting and used as the key for language file variable
		// type:  t - textarea, r - radio buttons, s - select, ms - multiselect, f - function calls
		// values:  can be array (r, s, ms), string (t), function name (f)
		// default:  name of array member, string, nothing
		//
		// Simple:
		// [variable_name] => 'Butter'
		// Text input, with 'Butter' as the default.

		return $settings;
	}
    
    
    // --------------------------------------------------------------------
	
	/**
	 * Uninstalls extension
	 */
	function disable_extension()
	{
		global $DB;
		$DB->query("DELETE FROM exp_extensions WHERE class = '".__CLASS__."'");
		$DB->query("DROP TABLE exp_forum_notifier");
	}
	
	// --------------------------------------------------------------------
    
    //
    // Update Extension (by FTP)
    //
    function update_extension($current = '')
    {
        global $DB;
        
        if ($current == '' OR $current == $this->version)
        {
            return FALSE;
        }
        
        $DB->query("UPDATE exp_extensions SET version = '".$DB->escape_str($this->version)."' WHERE class = 'Forum_notifier'");
    }
    
    
    
    //
    // 
    //
    function send_immediately( $obj, $mydata )
    {
        global $DB, $EXT, $LANG, $SESS, $REGX, $FNS, $PREFS;

$this->_load_preferences();
        //get settings
        $notifyquery = $DB->query("SELECT notify_immediately FROM exp_forums WHERE forum_id=".$mydata['forum_id']);
        if ($notifyquery->row['notify_immediately']!='y')
  			{
  				return;
  			}

      //individual settings
      $query = $DB->query("SELECT m_field_id FROM exp_member_fields
								WHERE m_field_name  = 'forum_notitications_immediately'");
			
			if ($query->num_rows > 0)
			{
				$field_id = $query->row['m_field_id'];
			} else {
        return;
      }

      //get groups

      $permissions = (empty($obj->topic_metadata))?$obj->forum_metadata[$mydata['forum_id']]['forum_permissions']:$obj->topic_metadata[$mydata['topic_id']]['forum_permissions'];
      $permissions = unserialize($permissions);
      $permissions_array = explode('|', trim($permissions['can_view_hidden'], '|'));  

      
      $addsql  = '';
      foreach ($permissions_array as $key=>$groupid) 
      {
        if ($groupid!='')
        {
          if ($addsql=='') 
          {
            $addsql .= " AND exp_members.group_id=".$groupid;
          } else {
            $addsql .= " OR exp_members.group_id=".$groupid;
          }   
        }     
      }
      if ($addsql=='') {
        return;
      }
      $qstr = "SELECT email from exp_members, exp_member_data 
								WHERE exp_members.member_id = exp_member_data.member_id 
								AND exp_member_data.m_field_id_".$field_id."  != 'No'
                AND exp_member_data.m_field_id_".$field_id."  != 'no'".$addsql;
			$query = $DB->query($qstr);
			
			if ($query->num_rows > 0)
			{
				foreach ($query->result as $row)
				{
					$notify_addresses .= ','.$row['email'];
				}
			} else {
        return;
      }

		
        $notify_addresses = str_replace(' ', '', $notify_addresses);
		
        
        /** ----------------------------------------
        /**  Remove Current User Email
        /** ----------------------------------------*/

		// We don't want to send an admin notification if the person
		// leaving the comment is an admin in the notification list
		
    if ($notify_addresses != '')
        {         
			if (eregi($SESS->userdata('email'), $notify_addresses))
			{
				$notify_addresses = str_replace($SESS->userdata('email'), "", $notify_addresses);				
			}
			
			$notify_addresses = $REGX->remove_extra_commas($notify_addresses);
		}
		
        /** ----------------------------
        /**  Strip duplicate emails
        /** ----------------------------*/
        
        // And while we're at it, create an array
                
        if ($notify_addresses != '')
        {         
			$notify_addresses = array_unique(explode(",", $notify_addresses));
		}		
		
        /** ----------------------------------------
        /**  Instantiate Typography class
        /** ----------------------------------------*/
      
        if ( ! class_exists('Typography'))
        {
            require PATH_CORE.'core.typography'.EXT;
        }
                
        $TYPE = new Typography(0); 
		$TYPE->highlight_code = FALSE;
		
		$title = empty($mydata['title'])?$obj->topic_metadata[$mydata['topic_id']]['title']:$mydata['title'];      
		$body = $TYPE->parse_type($mydata['body'], 
									   array(
												'text_format'   => 'none',
												'html_format'   => 'none',
												'auto_links'    => 'n',
												'allow_img_url' => 'n'
											)
									);

        /** ----------------------------
        /**  Send admin notification
        /** ----------------------------*/
 
        if (is_array($notify_addresses) AND count($notify_addresses) > 0)
        {         
			$swap = array(
							'name_of_poster'	=> $SESS->userdata('screen_name'),
							'forum_name'		=> $this->_fetch_pref('board_label'),
							'title'				=> $title,
							'body'				=> $body,
							'topic_id'			=> $mydata['topic_id'],
							'thread_url'		=> $FNS->remove_session_id($this->_forum_path('/forums/viewthread/'.$mydata['topic_id'].'/')),
							'post_url'			=> (isset($mydata['post_id'])) ? $this->_forum_path('forums')."viewreply/{$mydata['post_id']}/" : $FNS->remove_session_id($this->_forum_path('/forums/viewthread/'.$mydata['topic_id'].'/'))
						 );
			
			$template = $FNS->fetch_email_template('forum_notifier_immediately');
			$email_tit = $FNS->var_swap($template['title'], $swap);
			$email_msg = $FNS->var_swap($template['data'], $swap);
        
			/** ----------------------------
			/**  Send email
			/** ----------------------------*/
			
			if ( ! class_exists('EEmail'))
			{
				require PATH_CORE.'core.email'.EXT;
			}
			
			$email = new EEmail;
			$email->wordwrap = true;
						
			foreach ($notify_addresses as $val)
			{			
        $email->initialize();
				//$email->from($PREFS->ini('webmaster_email'), $PREFS->ini('webmaster_name'));	
				$email->from('ministersforum@pathwaysoflight.org', 'Ministers Forum');
				$email->to($val); 
				//$email->reply_to($PREFS->ini('webmaster_email'));
				$email->reply_to('ministersforum@pathwaysoflight.org');
				$email->subject($email_tit);	
				$email->message($REGX->entities_to_ascii($email_msg));		
				$email->Send();
			}
     }
        
        
        return;
     
	}
	
	
	
	
function send_digest( $tagdata )
    {
      global $DB, $EXT, $LANG, $SESS, $REGX, $FNS, $PREFS, $LOC;
        
    $query = $DB->query("SELECT MAX(timesent) AS timesent FROM exp_forum_notifier");
		//check whether digest was sent on closest sunday
		if ($query->num_rows>0) {
      $daysent = $query->row['timesent'];
    } else {
      $daysent = '';
    }
    
		
		if (($daysent == '' && date('D',$LOC->now)=='Sun')||($LOC->now>=($daysent+3600*24*7))) {
        if ($daysent == '' && date('D',$LOC->now)!='Sun') {
          return $tagdata;
        }
        
        $this->_load_preferences();
        //get settings
        $notifyquery = $DB->query("SELECT forum_id, forum_permissions, notify_digest FROM exp_forums");
        foreach ($notifyquery->result as $notifyrow) {
        if ($notifyrow['notify_digest']!='y')
  			{
  				return $tagdata;
  			}
  			
  			
          
        
  			

      //individual settings
      $query = $DB->query("SELECT m_field_id FROM exp_member_fields
								WHERE m_field_name  = 'forum_notitications_digest'");
			
			if ($query->num_rows > 0)
			{
				$field_id = $query->row['m_field_id'];
			} else {
        return $tagdata;
      }

      //get groups

      $permissions = $notifyrow['forum_permissions'];
      $permissions = unserialize($permissions);
      $permissions_array = explode('|', trim($permissions['can_view_hidden'], '|'));  

      
      $addsql  = '';
      foreach ($permissions_array as $key=>$groupid) 
      {
        if ($groupid!='')
        {
          if ($addsql=='') 
          {
            $addsql .= " AND exp_members.group_id=".$groupid;
          } else {
            $addsql .= " OR exp_members.group_id=".$groupid;
          }   
        }     
      }
      if ($addsql=='') {
        return $tagdata;
      }
      $qstr = "SELECT email from exp_members, exp_member_data 
								WHERE exp_members.member_id = exp_member_data.member_id 
								AND (exp_member_data.m_field_id_".$field_id."  = 'Yes'
                OR exp_member_data.m_field_id_".$field_id."  = 'yes')".$addsql;
			$query = $DB->query($qstr);
			
			if ($query->num_rows > 0)
			{
				foreach ($query->result as $row)
				{
					$notify_addresses .= ','.$row['email'];
				}
			} else {
        return $tagdata;
      }

		
        $notify_addresses = str_replace(' ', '', $notify_addresses);
		
        
		
    if ($notify_addresses != '')
        {         
			
			$notify_addresses = $REGX->remove_extra_commas($notify_addresses);
		}
		
        /** ----------------------------
        /**  Strip duplicate emails
        /** ----------------------------*/
        
        // And while we're at it, create an array
                
        if ($notify_addresses != '')
        {         
			$notify_addresses = array_unique(explode(",", $notify_addresses));
		}		
		
        /** ----------------------------------------
        /**  Instantiate Typography class
        /** ----------------------------------------*/
     
     if (is_array($notify_addresses) AND count($notify_addresses) > 0)
        {   
      
        if ( ! class_exists('Typography'))
        {
            require PATH_CORE.'core.typography'.EXT;
        }
                
        $TYPE = new Typography(0); 
		$TYPE->highlight_code = FALSE;
		$email_msg = '';
		//new topics
		//$email_msg .="SELECT topic_id, title, body FROM exp_forum_topics WHERE topic_date>='".$daysent."' AND forum_id=".$notifyrow['forum_id'];
		$getpost = $DB->query("SELECT topic_id, title, body FROM exp_forum_topics WHERE topic_date>='".$daysent."' AND forum_id=".$notifyrow['forum_id']);
		if ($getpost->num_rows>0) {
      foreach ($getpost->result as $postdata) {
      
      
		
		$title = $postdata['title'];      
		$body = $TYPE->parse_type($postdata['body'], 
									   array(
												'text_format'   => 'none',
												'html_format'   => 'none',
												'auto_links'    => 'n',
												'allow_img_url' => 'n'
											)
									);

        /** ----------------------------
        /**  Send admin notification
        /** ----------------------------*/
 
              
			$swap = array(
							'name_of_poster'	=> $SESS->userdata('screen_name'),
							'forum_name'		=> $this->_fetch_pref('board_label'),
							'title'				=> $title,
							'body'				=> $body,
							'topic_id'			=> $postdata['topic_id'],
							'thread_url'		=> $FNS->remove_session_id($this->_forum_path('/forums/viewthread/'.$mydata['topic_id'].'/')),
							'post_url'			=> $FNS->remove_session_id($this->_forum_path('/forums/viewthread/'.$postdata['topic_id'].'/'))
						 );
			
			$template = $FNS->fetch_email_template('forum_notifier_digest');
			
			$email_msg .= $FNS->var_swap($template['data'], $swap);
        
        
     }
    }
    
    //new posts
    //$email_msg .="SELECT exp_forum_posts.post_id, exp_forum_posts.topic_id, exp_forum_topics.title, exp_forum_posts.body FROM exp_forum_topics, exp_forum_posts WHERE exp_forum_topics.topic_id=exp_forum_posts.topic_id AND exp_forum_posts.post_date >='".$daysent."' AND exp_forum_posts.forum_id=".$notifyrow['forum_id'];
		$getpost2 = $DB->query("SELECT exp_forum_posts.post_id, exp_forum_posts.topic_id, exp_forum_topics.title, exp_forum_posts.body FROM exp_forum_topics, exp_forum_posts WHERE exp_forum_topics.topic_id=exp_forum_posts.topic_id AND exp_forum_posts.post_date >='".$daysent."' AND exp_forum_posts.forum_id=".$notifyrow['forum_id']);
		if ($getpost2->num_rows>0) {
      foreach ($getpost2->result as $postdata) {
      
      
		
		$title = $postdata['title'];      
		$body = $TYPE->parse_type($postdata['body'], 
									   array(
												'text_format'   => 'none',
												'html_format'   => 'none',
												'auto_links'    => 'n',
												'allow_img_url' => 'n'
											)
									);

        /** ----------------------------
        /**  Send admin notification
        /** ----------------------------*/
 
      
			$swap = array(
							'name_of_poster'	=> $SESS->userdata('screen_name'),
							'forum_name'		=> $this->_fetch_pref('board_label'),
							'title'				=> $title,
							'body'				=> $body,
							'topic_id'			=> $postdata['topic_id'],
							'thread_url'		=> $FNS->remove_session_id($this->_forum_path('/forums/viewthread/'.$mydata['topic_id'].'/')),
							'post_url'			=> $this->_forum_path('forums')."viewreply/{$postdata['post_id']}/"
						 );
			
			$template = $FNS->fetch_email_template('forum_notifier_digest');
			
			$email_msg .= $FNS->var_swap($template['data'], $swap);
        
        
     }
    }
    if ($email_msg!='') {
       $swap = array(
							'forum_name'		=> $this->_fetch_pref('board_label'),
							'email_body'		=> $email_msg
						 );
       $template = $FNS->fetch_email_template('forum_notifier_digest_wrapper');
       $email_tit = $FNS->var_swap($template['title'], $swap);
       $email_msg = $FNS->var_swap($template['data'], $swap);
       
			/** ----------------------------
			/**  Send email
			/** ----------------------------*/
			
  			if ( ! class_exists('EEmail'))
  			{
  				require PATH_CORE.'core.email'.EXT;
  			}
  			
  			$email = new EEmail;
  			$email->wordwrap = true;
  						
  			foreach ($notify_addresses as $val)
  			{			
          $email->initialize();
  				//$email->from($PREFS->ini('webmaster_email'), $PREFS->ini('webmaster_name'));	
  				$email->from('ministersforum@pathwaysoflight.org', 'Ministers Forum');
  				$email->to($val); 
  				//$email->to('heartcry@gmail.com');
  				//$email->reply_to($PREFS->ini('webmaster_email'));
  				$email->reply_to('ministersforum@pathwaysoflight.org');
  				$email->subject($email_tit);	
  				$email->message($REGX->entities_to_ascii($email_msg).$val);		
  				$email->Send();
  			}
			}
     }
        
        //write the date to db
        $DB->query("INSERT INTO exp_forum_notifier SET timesent='".$LOC->now."'");
        
     
     }
     }
     return $tagdata;
	}	
	
	
	
	
	
	
	function _fetch_pref($which)
    {
		return ( ! isset($this->preferences[$which])) ? '' : $this->preferences[$which];
    }
	
	
	function _forum_path($uri = '')
	{
		global $FNS;
		
		if ($this->basepath == '')
		{
			$this->_forum_set_basepath();
		}

		return $FNS->remove_double_slashes($this->basepath.$uri.'/');
	}

	
	function _forum_set_basepath()
	{
		global $FNS, $PREFS;

		/* -------------------------------------------
		/*	Hidden Configuration Variable
		/*	- use_forum_url => Does the user runs their forum at a different base URL then their main site? (y/n)
        /* -------------------------------------------*/
		if ($PREFS->ini('use_forum_url') == 'y')
		{
			$this->basepath = $this->_fetch_pref('board_forum_url');
			return;
		}
    	
    	// The only reason we set this is so that the session ID gets added to the URL
    	// if the user is running their site in session only mode
    	$FNS->template_type = 'webpage';

		$trigger = (isset($_GET['trigger'])) ? $_GET['trigger'] : $this->trigger;
		$this->basepath = $FNS->create_url($trigger).'/';
	}
	
	
	
    function _load_preferences($board_id='')
    {
		global $DB, $PREFS, $FNS, $TMPL, $IN;
		
		if ($board_id != '')
		{
			$sql = "board_id = '".$DB->escape_str($board_id)."'";
		}
		elseif ($IN->GBL('ACT') !== FALSE && $IN->GBL('board_id') !== FALSE)
		{
			$sql = "board_id = '".$DB->escape_str($IN->GBL('board_id'))."'";
		}
		else
		{
			// Means we are in a Template
			// If no board="" parameter, then we automatically
			// use the default board_id of 1
			
			if (is_object($TMPL) && ($board_name = $TMPL->fetch_param('board')) !== FALSE)
			{
				$sql = "board_name = '".$DB->escape_str($board_name)."'";
			}
			else
			{	
				$sql = "board_id = '1'";
			}
		}
		
        $query = $DB->query("SELECT board_label, board_name, board_id, board_alias_id, 
        					board_forum_url, board_enabled, board_default_theme, board_forum_trigger,
        					board_upload_path, board_topics_perpage, board_posts_perpage, board_topic_order, 
        					board_post_order, board_display_edit_date, board_hot_topic, board_max_attach_perpost, 
        					board_attach_types, board_max_attach_size, board_use_img_thumbs, board_recent_poster, 
        					board_recent_poster_id, board_notify_emails, board_notify_emails_topics, board_allow_php, board_php_stage 
        					FROM exp_forum_boards
        					WHERE ".$sql);

		if ($query->num_rows == 0)
		{
			exit('Forum does not appear to be installed');
		}

		if ($query->row['board_alias_id'] != '0')
		{
			$this->_load_preferences($query->row['board_alias_id']);
			
			foreach(array('board_label', 'board_name', 'board_enabled', 'board_forum_url') as $val)
			{
				$this->preferences[$val] = $query->row[$val];
			}
			
			$this->preferences['original_board_id'] = $query->row['board_id'];
			
			return;
		}
		
		$this->preferences['original_board_id'] = $query->row['board_id'];
		        
        foreach ($query->row as $key => $val)
        {
        	$this->preferences[$key] = $val;
        }

        // Assign the path the member profile area
        
        if ($this->use_site_profile == TRUE)
        {
        	$this->preferences['member_profile_path'] = $FNS->create_url($PREFS->ini('profile_trigger').'/');
        }
        else
        {
			$this->preferences['member_profile_path'] 	= $this->_forum_path($PREFS->ini('profile_trigger').'/');   
        }
        
		$this->preferences['board_theme_path'] 	= PATH_THEMES.'forum_themes/';
		$this->preferences['board_theme_url']	= $PREFS->ini('theme_folder_url', 1).'forum_themes/';
    }

	


}

?>