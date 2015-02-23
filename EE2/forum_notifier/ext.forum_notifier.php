<?php  

if ( ! defined('EXT')) {
    exit('Invalid file request'); }


class Forum_notifier_ext {
    
	var $settings		= array();
	
	var $name			= 'Forum notifier';
	var $version		= '2.0';
	var $description	= 'Sends forum notifications';
	var $settings_exist	= 'n';
	var $docs_url		= 'http://www.intoeetive.com';
    
	var $preferences	= array();
    
    
    var $trigger			= '';
    var $basepath			= '';
    var $use_site_profile	= FALSE;
	
	function __construct($settings = '')
	{
		$this->EE =& get_instance();
        
        $this->settings = $settings;

	}


	//
	// Add to Database
	//
	function activate_extension ()
	{
        $hooks = array(
    		array(
    			'hook'		=> 'forum_submit_post_end',
    			'method'	=> 'send_immediately',
    			'priority'	=> 10
    		),
            array(
    			'hook'		=> 'channel_entries_tagdata_end',
    			'method'	=> 'send_digest',
    			'priority'	=> 10
    		)
            
    	);
    	
        foreach ($hooks AS $hook)
    	{
    		$data = array(
        		'class'		=> __CLASS__,
        		'method'	=> $hook['method'],
        		'hook'		=> $hook['hook'],
        		'settings'	=> '',
        		'priority'	=> $hook['priority'],
        		'version'	=> $this->version,
        		'enabled'	=> 'y'
        	);
            $this->EE->db->insert('extensions', $data);
    	}	

        $sql[] = "CREATE TABLE `exp_forum_notifier` (
                `timesent` INT ( 10 ) NOT NULL
                )";
                
        foreach ($sql as $query)
        {
          $this->EE->db->query($query);
        }        
    
    }
    
	//
	// Change Settings
	//
	function settings()
	{
		$settings = array();

		return $settings;
	}
    
    
    // --------------------------------------------------------------------
	
	/**
	 * Uninstalls extension
	 */
	function disable_extension()
	{
		$this->EE->db->query("DELETE FROM exp_extensions WHERE class = '".__CLASS__."'");
		$this->EE->db->query("DROP TABLE exp_forum_notifier");
	}
	
	// --------------------------------------------------------------------
    
    //
    // Update Extension 
    //
    function update_extension($current = '')
    {
    	if ($current == '' OR $current == $this->version)
    	{
    		return FALSE;
    	}
    	
    	if ($current < '2.0')
    	{
    		// Upgrade from EE1 to EE2 version
            // got to change extension hooks
            $this->EE->db->query("UPDATE exp_extensions SET class = '".__CLASS__."' WHERE class = 'Forum_notifier'");
            $this->EE->db->query("UPDATE exp_extensions SET hook='channel_entries_tagdata_end' WHERE class = '".__CLASS__."' AND hook='weblog_entries_tagdata_end'");
    	}
    	
    	$this->EE->db->where('class', __CLASS__);
    	$this->EE->db->update(
    				'extensions', 
    				array('version' => $this->version)
    	);
    }
    
    
    
    //
    // 
    //
    function send_immediately( $obj, $mydata )
    {

        $this->_load_preferences();
        //get settings
        $notifyquery = $this->EE->db->query("SELECT notify_immediately FROM exp_forums WHERE forum_id=".$mydata['forum_id']);
        if ($notifyquery->row('notify_immediately')!='y')
		{
			return;
		}

      //individual settings
      $query = $this->EE->db->query("SELECT m_field_id FROM exp_member_fields
								WHERE m_field_name  = 'forum_notitications_immediately'");
			
        if ($query->num_rows() > 0)
        {
        	$field_id = $query->row('m_field_id');
        } 
        else 
        {
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
    $query = $this->EE->db->query($qstr);
    $notify_addresses = '';
			
	if ($query->num_rows() > 0)
	{
		foreach ($query->result_array() as $row)
		{
			$notify_addresses .= ','.$row['email'];
		}
	} 
    else 
    {
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
			if (eregi($this->EE->session->userdata('email'), $notify_addresses))
			{
				$notify_addresses = str_replace($this->EE->session->userdata('email'), "", $notify_addresses);				
			}
			
            $this->EE->load->helper('string');
            $notify_addresses = reduce_multiples($notify_addresses, ',', TRUE);

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
      
        $this->EE->load->library('typography');
		$this->EE->typography->initialize();
		$this->EE->typography->parse_images = FALSE;
		$this->EE->typography->highlight_code = FALSE;
		
		$title = empty($mydata['title'])?$obj->topic_metadata[$mydata['topic_id']]['title']:$mydata['title'];      
		$body = $this->EE->typography->parse_type($mydata['body'], 
										array(
												'text_format'	=> 'none',
												'html_format'	=> 'none',
												'auto_links'	=> 'n',
												'allow_img_url' => 'n'
											)
									);

        /** ----------------------------
        /**  Send notification
        /** ----------------------------*/
 
        if (is_array($notify_addresses) AND count($notify_addresses) > 0)
        {         
			$swap = array(
							'name_of_poster'	=> $this->_convert_special_chars($this->EE->session->userdata('screen_name')),
							'forum_name'		=> $this->_fetch_pref('board_label'),
							'title'				=> $title,
							'body'				=> $body,
							'topic_id'			=> $mydata['topic_id'],
							'thread_url'		=> $this->remove_session_id($this->_forum_path('/forums/viewthread/'.$mydata['topic_id'].'/')),
							'post_url'			=> (isset($mydata['post_id'])) ? $this->_forum_path('forums')."viewreply/{$mydata['post_id']}/" : $this->remove_session_id($this->_forum_path('/forums/viewthread/'.$mydata['topic_id'].'/'))
						 );
			
			$template = $this->EE->functions->fetch_email_template('forum_notifier_immediately');
			$email_tit = $this->EE->functions->var_swap($template['title'], $swap);
			$email_msg = $this->EE->functions->var_swap($template['data'], $swap);

        
			/** ----------------------------
			/**  Send email
			/** ----------------------------*/
			
			$this->EE->load->library('email');
			$this->EE->email->wordwrap = TRUE;

			// Load the text helper
			$this->EE->load->helper('text');
						
			foreach ($notify_addresses as $val)
			{			
                $this->EE->email->EE_initialize();
				//$email->from($PREFS->ini('webmaster_email'), $PREFS->ini('webmaster_name'));	
				$this->EE->email->from('ministersforum@pathwaysoflight.org', 'Ministers Forum');
				$this->EE->email->to($val); 
				//$email->reply_to($PREFS->ini('webmaster_email'));
				$this->EE->email->reply_to('ministersforum@pathwaysoflight.org');
				$this->EE->email->subject($email_tit);	
				$this->EE->email->message(entities_to_ascii($email_msg));		
				$this->EE->email->send();
			}
     }
        
        
        return;
     
	}
	

    function remove_session_id($str)
	{
		return preg_replace("#S=.+?/#", "", $str);
	} 


	/**
	 * Convert special characters
	 */
	function _convert_special_chars($str, $convert_amp = FALSE)
	{
		// If we convert &'s for strings that have typography performed on them,
		// then they will be double-converted
		if ($convert_amp === TRUE)
		{
			$str = str_replace('&', '&amp;', $str);
		}
		
		return str_replace(array('<', '>', '{', '}', '\'', '"', '?'), array('&lt;', '&gt;', '&#123;', '&#125;', '&#146;', '&quot;', '&#63;'), $str);
	}
	
	
	
    function send_digest( $tagdata )
    {

        
        $query = $this->EE->db->query("SELECT MAX(timesent) AS timesent FROM exp_forum_notifier");
    		//check whether digest was sent on closest sunday
        if ($query->num_rows()>0) {
          $daysent = $query->row('timesent');
        } else {
          $daysent = '';
        }
    
		
		if (($daysent == '' && date('D',$this->EE->localize->now)=='Sun')||($this->EE->localize->now>=($daysent+3600*24*7))) {
        if ($daysent == '' && date('D',$this->EE->localize->now)!='Sun') {
          return $tagdata;
        }
        
        //write the date to db
        $this->EE->db->query("INSERT INTO exp_forum_notifier SET timesent='".$this->EE->localize->now."'");
        
        $this->_load_preferences();
        //get settings
        $notifyquery = $this->EE->db->query("SELECT forum_id, forum_permissions, notify_digest FROM exp_forums");
        foreach ($notifyquery->result_array() as $notifyrow) {
        if ($notifyrow['notify_digest']!='y')
  			{
  				return $tagdata;
  			}
  			
  			
          
        
  			

      //individual settings
      $query = $this->EE->db->query("SELECT m_field_id FROM exp_member_fields
								WHERE m_field_name  = 'forum_notitications_digest'");
			
			if ($query->num_rows() > 0)
			{
				$field_id = $query->row('m_field_id');
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
			$query = $this->EE->db->query($qstr);
			
            $notify_addresses = '';
            
			if ($query->num_rows() > 0)
			{
				foreach ($query->result_array() as $row)
				{
					$notify_addresses .= ','.$row['email'];
				}
			} else {
        return $tagdata;
      }

		
        $notify_addresses = str_replace(' ', '', $notify_addresses);
		
        
		
        if ($notify_addresses != '')
        {         
			
			$this->EE->load->helper('string');
            $notify_addresses = reduce_multiples($notify_addresses, ',', TRUE);
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
                
        $this->EE->load->library('typography');
		$this->EE->typography->initialize();
		$this->EE->typography->parse_images = FALSE;
		$this->EE->typography->highlight_code = FALSE;
        
		$email_msg = '';
		//new topics
		//$email_msg .="SELECT topic_id, title, body FROM exp_forum_topics WHERE topic_date>='".$daysent."' AND forum_id=".$notifyrow['forum_id'];
		$getpost = $this->EE->db->query("SELECT topic_id, title, body FROM exp_forum_topics WHERE topic_date>='".$daysent."' AND forum_id=".$notifyrow['forum_id']);
		if ($getpost->num_rows()>0) {
      foreach ($getpost->result_array() as $postdata) {
      
      
		
		$title = $postdata['title'];      
		$body = $this->EE->typography->parse_type($postdata['body'], 
										array(
												'text_format'	=> 'none',
												'html_format'	=> 'none',
												'auto_links'	=> 'n',
												'allow_img_url' => 'n'
											)
									);

        /** ----------------------------
        /**  Send admin notification
        /** ----------------------------*/
        
        
        $swap = array(
							'name_of_poster'	=> $this->_convert_special_chars($this->EE->session->userdata('screen_name')),
							'forum_name'		=> $this->_fetch_pref('board_label'),
							'title'				=> $title,
							'body'				=> $body,
							'topic_id'			=> $postdata['topic_id'],
							'thread_url'		=> $this->remove_session_id($this->_forum_path('/forums/viewthread/'.$postdata['topic_id'].'/')),
							'post_url'			=> $this->remove_session_id($this->_forum_path('/forums/viewthread/'.$postdata['topic_id'].'/'))
						 );
			
			$template = $this->EE->functions->fetch_email_template('forum_notifier_digest');

			$email_msg .= $this->EE->functions->var_swap($template['data'], $swap);
        
     }
    }
    
    //new posts
    //$email_msg .="SELECT exp_forum_posts.post_id, exp_forum_posts.topic_id, exp_forum_topics.title, exp_forum_posts.body FROM exp_forum_topics, exp_forum_posts WHERE exp_forum_topics.topic_id=exp_forum_posts.topic_id AND exp_forum_posts.post_date >='".$daysent."' AND exp_forum_posts.forum_id=".$notifyrow['forum_id'];
		$getpost2 = $this->EE->db->query("SELECT exp_forum_posts.post_id, exp_forum_posts.topic_id, exp_forum_topics.title, exp_forum_posts.body FROM exp_forum_topics, exp_forum_posts WHERE exp_forum_topics.topic_id=exp_forum_posts.topic_id AND exp_forum_posts.post_date >='".$daysent."' AND exp_forum_posts.forum_id=".$notifyrow['forum_id']);
		if ($getpost2->num_rows()>0) {
      foreach ($getpost2->result_array() as $postdata) {
      
      
		
		$title = $postdata['title'];      
        
		$body = $this->EE->typography->parse_type($postdata['body'], 
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
							'name_of_poster'	=> $this->EE->session->userdata('screen_name'),
							'forum_name'		=> $this->_fetch_pref('board_label'),
							'title'				=> $title,
							'body'				=> $body,
							'topic_id'			=> $postdata['topic_id'],
							'thread_url'		=> $this->remove_session_id($this->_forum_path('/forums/viewthread/'.$postdata['topic_id'].'/')),
							'post_url'			=> $this->_forum_path('forums')."viewreply/{$postdata['post_id']}/"
						 );
			
			$template = $this->EE->functions->fetch_email_template('forum_notifier_digest');
			
			$email_msg .= $this->EE->functions->var_swap($template['data'], $swap);
        
        
     }
    }
    if ($email_msg!='') {
       $swap = array(
							'forum_name'		=> $this->_fetch_pref('board_label'),
							'email_body'		=> $email_msg
						 );
       $template = $this->EE->functions->fetch_email_template('forum_notifier_digest_wrapper');
       $email_tit = $this->EE->functions->var_swap($template['title'], $swap);
       $email_msg = $this->EE->functions->var_swap($template['data'], $swap);
       
			/** ----------------------------
			/**  Send email
			/** ----------------------------*/
			
  			$this->EE->load->library('email');
			$this->EE->email->wordwrap = TRUE;

			// Load the text helper
			$this->EE->load->helper('text');
            
            $notify_addresses = array_unique($notify_addresses);
  						
  			foreach ($notify_addresses as $val)
  			{			
                $this->EE->email->initialize();
  				//$email->from($PREFS->ini('webmaster_email'), $PREFS->ini('webmaster_name'));	
  				$this->EE->email->from('ministersforum@pathwaysoflight.org', 'Ministers Forum');
  				$this->EE->email->to($val); 
  				//$email->to('heartcry@gmail.com');
  				//$email->reply_to($PREFS->ini('webmaster_email'));
  				$this->EE->email->reply_to('ministersforum@pathwaysoflight.org');
  				$this->EE->email->subject($email_tit);	
  				$this->EE->email->message(entities_to_ascii($email_msg).$val);		
  				$this->EE->email->Send();
  			}
			}
     }
        
        
        
     
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
		if ($this->basepath == '')
		{
			$this->_forum_set_basepath();
		}
        
        $this->EE->load->helper('string');

		return reduce_double_slashes($this->basepath.$uri);
	}

	
	function _forum_set_basepath()
	{
		/* -------------------------------------------
		/*	Hidden Configuration Variable
		/*	- use_forum_url => Does the user runs their forum at a different base URL then their main site? (y/n)
		/* -------------------------------------------*/
		if ($this->EE->config->item('use_forum_url') == 'y')
		{
			$this->basepath = $this->_fetch_pref('board_forum_url');
			return;
		}
		
		// The only reason we set this is so that the session ID gets added to the URL
		// if the user is running their site in session only mode
		$this->EE->functions->template_type = 'webpage';

		$trigger = (isset($_GET['trigger'])) ? $_GET['trigger'] : $this->trigger;
		$this->basepath = $this->EE->functions->create_url($trigger).'/'; 
	}
	
	
	
    function _load_preferences($board_id='')
	{
		if ($board_id != '')
		{
			$this->EE->db->where('board_id', $board_id);
		}
		elseif ($this->EE->input->get_post('ACT') !== FALSE && $this->EE->input->get_post('board_id') !== FALSE)
		{
			$this->EE->db->where('board_id', $this->EE->input->get_post('board_id'));
		}
		else
		{
			// Means we are in a Template
			// If no board="" parameter, then we automatically
			// use the default board_id of 1
			if (isset($this->EE->TMPL) && is_object($this->EE->TMPL) && ($board_name = $this->EE->TMPL->fetch_param('board')) !== FALSE)
			{
				$this->EE->db->where('board_name', $board_name);
			}
			else
			{	
				$this->EE->db->where('board_id', 1);
			}
		}
		
		$this->EE->db->select('board_label, board_name, board_id, board_alias_id, 
							board_forum_url, board_enabled, board_default_theme, board_forum_trigger,
							board_upload_path, board_topics_perpage, board_posts_perpage, board_topic_order, 
							board_post_order, board_display_edit_date, board_hot_topic, board_max_attach_perpost, 
							board_attach_types, board_max_attach_size, board_use_img_thumbs, board_recent_poster, 
							board_recent_poster_id, board_notify_emails, board_notify_emails_topics, board_allow_php,
							board_php_stage');
		$query = $this->EE->db->get('forum_boards');

		if ($query->num_rows() == 0)
		{
			$this->EE->output->show_user_error('general', $this->EE->lang->line('forum_not_installed'));
		}

		if ($query->row('board_alias_id') != '0')
		{
			$this->_load_preferences($query->row('board_alias_id') );
			
			foreach(array('board_label', 'board_name', 'board_enabled', 'board_forum_url') as $val)
			{
				$this->preferences[$val] = $query->row($val);
			}
			
			$this->preferences['original_board_id'] = $query->row('board_id') ;
			
			return;
		}
		
		$this->preferences['original_board_id'] = $query->row('board_id') ;
				
		foreach ($query->row_array() as $key => $val)
		{
			$this->preferences[$key] = $val;
		}

		// Assign the path the member profile area
		if ($this->use_site_profile == TRUE)
		{
			$this->preferences['member_profile_path'] = $this->EE->functions->create_url($this->EE->config->item('profile_trigger').'/');
		}
		else
		{
			$this->preferences['member_profile_path'] 	= $this->_forum_path($this->EE->config->item('profile_trigger').'/');	
		}
		
		$this->preferences['board_theme_path'] 	= PATH_THEMES.'forum_themes/';
		$this->preferences['board_theme_url']	= $this->EE->config->slash_item('theme_folder_url').'forum_themes/';
	}

	


}

