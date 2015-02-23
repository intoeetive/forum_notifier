<?php

/*
=====================================================
 Forum notifier for ExpressionEngine - Output
-----------------------------------------------------
 http://www.intoeetive.com/
-----------------------------------------------------
 Copyright (c) 2009 Yuriy Salimovskiy
-----------------------------------------------------
 Last edited: 21.05.2009 11:53:17
=====================================================
*/

if ( ! defined('BASEPATH'))
{
    exit('Invalid file request');
}


class Forum_notifier {

    var $return_data	= ''; 						// Bah!

    /** ----------------------------------------
    /**  Constructor
    /** ----------------------------------------*/

    function __construct()
    {        
    	$this->EE =& get_instance(); 
    }
    /* END */


}
// END CLASS
?>