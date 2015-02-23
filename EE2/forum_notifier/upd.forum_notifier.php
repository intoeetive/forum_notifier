<?php

if ( ! defined('BASEPATH'))
{
    exit('Invalid file request');
}



class Forum_notifier_upd {

    var $version = '2.0';
    
    function __construct() { 
        // Make a local reference to the ExpressionEngine super object 
        $this->EE =& get_instance(); 
    } 
    
    function install() { 
 
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
            $this->EE->db->query($query);
        }
        
        return true;

        
    } 
    
    function uninstall() { 

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
            $this->EE->db->query($query);
        }

        return true;
    } 
    
    function update($current='') { 
        if ($current < 2.0) { 
            // Do your 2.0 version update queries 
        } if ($current < 3.0) { 
            // Do your 3.0 v. update queries 
        } 
        return TRUE; 
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

}
/* END */
?>