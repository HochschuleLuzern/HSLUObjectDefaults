<#1>
<?php
/**
 * @var ilDB $db
 */
$db = $ilDB;
if (!$db->tableExists('evhk_hsluobjdef_s')) {
	$fields = array(
		'config_key' => array(
			'type' => 'text',
			'length' => 64,
		),
		'config_value' => array(
			'type' => 'text',
			'length' => 4000,
		)
	);

	$db->createTable('evhk_hsluobjdef_s', $fields);
	$db->addPrimaryKey('evhk_hsluobjdef_s', array('config_key'));
	$db->insert('evhk_hsluobjdef_s', array('config_key' => array('text', 'message'), 'config_value' => array('text', '')));
	$db->insert('evhk_hsluobjdef_s', array('config_key' => array('text', 'active'), 'config_value' => array('text', '0')));
}
?>