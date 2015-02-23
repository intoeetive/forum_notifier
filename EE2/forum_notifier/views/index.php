<?=form_open('C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=forum_notifier'.AMP.'method=update');?>


<?php
//var_dump($data);
foreach ($data as $d)
{
    $this->table->set_template($cp_pad_table_template);
    $this->table->set_heading(
        array('data' => $d->header, 'style' => 'width:50%;'),
        array('data' => lang('forum_notifier_setting_immediately'), 'style' => 'width:20%;'),
		array('data' => lang('forum_notifier_setting_digest'), 'style' => 'width:20%;')
    );
    
    $rows = $d->rows;
    foreach ($rows as $row)
    {
    	$this->table->add_row($row);
    }
    
    echo $this->table->generate();
    
    $this->table->clear();
}
?>

<p><?=form_submit('submit', lang('submit'), 'class="submit"')?></p>

<?php
form_close();

