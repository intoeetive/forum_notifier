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

class Forum_notifier_mcp {

    var $version        = '2.0';
    
    
    // -------------------------
    //  Constructor
    // -------------------------
    function __construct() { 
        // Make a local reference to the ExpressionEngine super object 
        $this->EE =& get_instance(); 

    } 
 
    // ----------------------------------------
    //  Module Homepage
    // ----------------------------------------

    function index()
    {
        return $this->settings();    
    }
    
    function settings()
    {
    
        $this->EE->load->helper('form');
        $this->EE->load->library('table');
        
        $this->EE->cp->set_variable('cp_page_title', lang('forum_notifier_module_name'));

        $query = $this->EE->db->query("SELECT * FROM exp_forums ORDER BY forum_order");

		$vars = array();
        $i = 0;

		foreach ($query->result_array() as $row)
		{

            if ($row['forum_is_cat'] == 'y')
            {
                $i++;
                $vars['data'][$i]->header = $row['forum_name'];
            }
            else
			{
					
                $vars['data'][$i]->rows[] = array(
	               $row['forum_name'].form_hidden('forum_id['.$row['forum_id'].']', $row['forum_id']),
                   form_checkbox('notify_immediately['.$row['forum_id'].']', 'y', ($row['notify_immediately']=='y') ? true : false),
                   form_checkbox('notify_digest['.$row['forum_id'].']', 'y', ($row['notify_digest']=='y') ? true : false)
				);	
			}
			
        }
        
        return $this->EE->load->view('index', $vars, TRUE);

    }
    




    function update()
    {

 
        $this->EE->cp->set_variable('cp_page_title', lang('forum_notifier_module_name'));

        
        $query = $this->EE->db->query("SELECT * FROM exp_forums ORDER BY forum_order");

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
            $this->EE->db->query($query);
            $success = TRUE;
        }
  
    	if (isset($success)) {
            $this->EE->session->set_flashdata('message_success', $this->EE->lang->line('forum_notifier_settings_saved'));
        }
        
        $this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=forum_notifier'.AMP.'method=settings');

    }





}
// END CLASS
