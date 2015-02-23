<?php

/*
=====================================================
 Forum notifier for ExpressionEngine - CP
-----------------------------------------------------
 http://www.intoeetive.com/
-----------------------------------------------------
 Copyright (c) 2009 Yuriy Salimovskiy
-----------------------------------------------------
 Last edited: 21.05.2009 11:53:17
=====================================================
*/

class Forum_notifier_CP {

    var $version        = '1.0';
    
    
    // -------------------------
    //  Constructor
    // -------------------------
    
    function Forum_notifier_CP( $switch = TRUE )
    {
        global $IN;
        
        if ($switch)
        {
            switch($IN->GBL('P'))
            {
                 case 'settings'            :    $this->forum_notifier_settings();
                     break;    
                 case 'update'            :    $this->forum_notifier_settings_update();
                     break;
                 case 'digest'             :    $this->forum_notifier_digestform();
                     break;
                 case 'send'             :    $this->forum_notifier_digestsend();
                     break;
                 default                :    $this->forum_notifier_home();
                     break;
            }
        }
    }
    // END
    
    
    // ----------------------------------------
    //  Module Homepage
    // ----------------------------------------

    function forum_notifier_home()
    {
        global $DSP, $LANG;        
        
        // -------------------------------------------------------
        //  HTML Title and Navigation Crumblinks
        // -------------------------------------------------------
        
        $DSP->title = $LANG->line('forum_notifier_module_name');
        $DSP->crumb = $DSP->anchor(BASE.
                                   AMP.'C=modules'.
                                   AMP.'M=forum_notifier',
                                   $LANG->line('forum_notifier_module_name'));                                    
        
        // -------------------------------------------------------
        //  Page Heading
        // -------------------------------------------------------
        
        $DSP->body .= $DSP->heading($LANG->line('forum_notifier_module_name'));   
        
        // -------------------------------------------------------
        //  Main Menu Links - Add Fortune or View Fortunes
        // -------------------------------------------------------
        
        $DSP->body .= $DSP->qdiv('itemWrapper', $DSP->heading($DSP->anchor(BASE.
                                                                           AMP.'C=modules'.
                                                                           AMP.'M=forum_notifier'.
                                                                           AMP.'P=settings',
                                                                           $LANG->line('forum_notifier_settings')),
                                                                           5));
                                                                           
        $DSP->body .= $DSP->qdiv('itemWrapper', $DSP->heading($DSP->anchor(BASE.
                                                                           AMP.'C=modules'.
                                                                           AMP.'M=forum_notifier'.
                                                                           AMP.'P=digest',
                                                                           $LANG->line('forum_notifier_digest')),
                                                                           5));
                                                                                                                                  
    }
    // END
    
    
    function forum_notifier_settings()
    {
    
      global $IN, $DB, $LANG, $DSP;
    
      
      $DSP->title = $LANG->line('forum_notifier_module_name');
        $DSP->crumb = $DSP->anchor(BASE.
                                   AMP.'C=modules'.
                                   AMP.'M=forum_notifier',
                                   $LANG->line('forum_notifier_module_name'));                                    
        
        // -------------------------------------------------------
        //  Page Heading
        // -------------------------------------------------------
        
        $DSP->body .= $DSP->heading($LANG->line('forum_notifier_settings'));   
      
        
        $query = $DB->query("SELECT * FROM exp_forums ORDER BY forum_order");
$r = '';
		/** -------------------------------------
		/**  Build the Forum Display
		/** -------------------------------------*/
		
		  
		$r .= $DSP->form_open(array('action' => 'C=modules'.AMP.'M=forum_notifier'.AMP.'P=update'));	
$r .= $DSP->table_open(array('class' => 'tableBorder', 'width' => '100%'));
$ct = 0;
			foreach ($query->result as $row)
			{

        if ($row['forum_is_cat'] == 'y')
				{
					// Don't mess with the order of these items or the world will come to an end!

		$r .= $DSP->table_row(array(
									array(
											'text'	=> $row['forum_name'].(($row['forum_description'] == '') ? '' : $DSP->qdiv('altLink', $row['forum_description'])),
											'width'	=> '60%',
											'class'	=> 'tableHeading'
										),
									array(
											'text'	=> $LANG->line('forum_notifier_setting_immediately'),
											'width'	=> '20%',
											'class'	=> 'tableHeading'
										),
									array(
											'text'	=> $LANG->line('forum_notifier_setting_digest'),
											'width'	=> '20%',
											'class'	=> 'tableHeading'
										)
								)
							);
        }
				else
				{
					switch ($row['forum_status'])
		{
			case 'o' : $status = $DSP->qdiv('highlight_alt', '');
				break;
			case 'c' : $status = $DSP->qdiv('highlight', '');
				break;
			case 'a' : $status = $DSP->qdiv('highlight_alt2', '');
				break;
		}
		
		
		$class = ($ct++ % 2) ? 'tableCellOne' : 'tableCellTwo';
		
		$r .= $DSP->table_row(array(
									array(
											'text'	=> $DSP->qdiv('defaultBold', $row['forum_name']).$DSP->qdiv('default', $row['forum_description'].$DSP->input_hidden('forum_id['.$row['forum_id'].']', $row['forum_id'])),
											'class'	=> $class
										),
									array(
											'text'	=> $DSP->input_checkbox('notify_immediately['.$row['forum_id'].']', 'y', ($row['notify_immediately']=='y') ? 'y' : ''),
											'class'	=> $class
										),
									array(
											'text'	=> $DSP->input_checkbox('notify_digest['.$row['forum_id'].']', 'y', ($row['notify_digest']=='y') ? 'y' : ''),
											'class'	=> $class
										)

									)
							);	
				}
				
			}
			
			
			
			$r .= $DSP->table_close();
			$r .= $DSP->qdiv('itemWrapperTop', 
								 $DSP->input_submit($LANG->line('forum_notifier_update'))).
					  $DSP->form_close();
    $DSP->body .= $r;
    }
    




function forum_notifier_settings_update()
    {
    
      global $IN, $DB, $LANG, $DSP;
    
      
      $DSP->title = $LANG->line('forum_notifier_module_name');
        $DSP->crumb = $DSP->anchor(BASE.
                                   AMP.'C=modules'.
                                   AMP.'M=forum_notifier',
                                   $LANG->line('forum_notifier_module_name'));                                    
        
        // -------------------------------------------------------
        //  Page Heading
        // -------------------------------------------------------
        
        $DSP->body .= $DSP->heading($LANG->line('forum_notifier_settings'));   
      
        
        $query = $DB->query("SELECT * FROM exp_forums ORDER BY forum_order");
$r = '';
		/** -------------------------------------
		/**  Build the Forum Display
		/** -------------------------------------*/

	foreach ($_POST['forum_id'] as $key=>$value)
	{
    if (isset($_POST['notify_immediately'][$key]))
    {
     $sql[] = "UPDATE `exp_forums` 
               SET notify_immediately='y'
               WHERE forum_id='".$key."';";
    } else {
      $sql[] = "UPDATE `exp_forums` 
               SET notify_immediately='n'
               WHERE forum_id='".$key."';";
    }
    if (isset($_POST['notify_digest'][$key]))
    {
     $sql[] = "UPDATE `exp_forums` 
               SET notify_digest='y'
               WHERE forum_id='".$key."';";
    } else {
      $sql[] = "UPDATE `exp_forums` 
               SET notify_digest='n'
               WHERE forum_id='".$key."';";
    }
  }
  
 
    
        foreach ($sql as $query)
        {
            //echo $query;
            $DB->query($query);
            $success = TRUE;
        }
  
	if (isset($success)) {
    $DSP->body .= $DSP->qdiv('defaultBold', $LANG->line('forum_notifier_settings_saved'));
  }
  $DSP->body .= $this->forum_notifier_settings();
    }
































	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
//---------------------------------------------------
//	Notification of New Forum Post
//--------------------------------------------------

function forum_notifier_immediately_message_title()
{
return <<<EOF
Someone just posted in {forum_name}
EOF;
}

function forum_notifier_immediately_message()
{
return <<<EOF
{name_of_poster} just submitted a new post in {forum_name}

The title of the thread is:
{title}

The post can be found at:
{post_url}

Title: {title}
Message:{body}
EOF;
}

function forum_notifier_digest_message_title()
{
return <<<EOF
--not used --
EOF;
}

function forum_notifier_digest_message()
{
return <<<EOF

Title: {title}
Message: {body}
The post can be found at:
{post_url}

EOF;
}

function forum_notifier_digest_wrapper_title()
{
return <<<EOF
Digest of posts in {forum_name}
EOF;
}

function forum_notifier_digest_wrapper()
{
return <<<EOF
Here is the digest of new posts in {forum_name}

EOF;
}
/* END */		
	
	
	
    
    // ----------------------------------------
    //  Module installer
    // ----------------------------------------

    function Forum_notifier_module_install()
    {
        global $DB;        
        
        $sql[] = "INSERT INTO exp_modules (module_id,
                                           module_name,
                                           module_version,
                                           has_cp_backend)
                                           VALUES
                                           ('',
                                           'Forum_notifier',
                                           '$this->version',
                                           'y')";
                                           
        $sql[] = "ALTER TABLE `exp_forums` 
                  ADD COLUMN notify_immediately char(1) DEFAULT 'n'
                    AFTER forum_use_http_auth;";
                    
        $sql[] = "ALTER TABLE `exp_forums` 
                  ADD COLUMN notify_digest char(1) DEFAULT 'n'
                    AFTER forum_use_http_auth;";
                    
        $sql[] = "INSERT INTO exp_specialty_templates(template_id, site_id, template_name, data_title, template_data) 
					  VALUES ('', '1', 'forum_notifier_immediately', '".addslashes(trim($this->forum_notifier_immediately_message_title()))."', '".addslashes($this->forum_notifier_immediately_message())."');";
					  
				$sql[] = "INSERT INTO exp_specialty_templates(template_id, site_id, template_name, data_title, template_data) 
					  VALUES ('', '1', 'forum_notifier_digest', '".addslashes(trim($this->forum_notifier_digest_message_title()))."', '".addslashes($this->forum_notifier_digest_message())."');";	  
					  
				$sql[] = "INSERT INTO exp_specialty_templates(template_id, site_id, template_name, data_title, template_data) 
					  VALUES ('', '1', 'forum_notifier_digest_wrapper', '".addslashes(trim($this->forum_notifier_digest_wrapper_title()))."', '".addslashes($this->forum_notifier_digest_wrapper())."');";	  
    
        foreach ($sql as $query)
        {
            $DB->query($query);
        }
        
        return true;
    }
    // END
    
    
    // ----------------------------------------
    //  Module de-installer
    // ----------------------------------------

    function Forum_notifier_module_deinstall()
    {
        global $DB;    

        $sql[] = "DELETE FROM exp_modules
                  WHERE module_name = 'Forum_notifier'";
                  
        $sql[] = "ALTER TABLE `exp_forums` 
                  DROP COLUMN notify_digest;";
                  
        $sql[] = "ALTER TABLE `exp_forums` 
                  DROP COLUMN notify_immediately;";   
         
        $sql[] = "DELETE FROM exp_specialty_templates
                  WHERE template_name='forum_notifier_digest';";	          
                  
        $sql[] = "DELETE FROM exp_specialty_templates
                  WHERE template_name='forum_notifier_immediately';";	          
        
        $sql[] = "DELETE FROM exp_specialty_templates
                  WHERE template_name='forum_notifier_digest_wrapper';";

        foreach ($sql as $query)
        {
            $DB->query($query);
        }

        return true;
    }
    // END



}
// END CLASS
?> 