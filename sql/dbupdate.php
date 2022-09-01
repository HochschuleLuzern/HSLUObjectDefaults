<#1>
<?php
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
<#2>
<?php
$db = $ilDB;
$result = $db->query('SELECT config_value FROM evhk_hsluobjdef_s WHERE config_key = "video_types"');
if ($result->numRows() == 0) {
    $db->insert('evhk_hsluobjdef_s', array('config_key' => array('text', 'video_types'), 'config_value' => array('text', 'mp4,mkv,flv')));
}
?>
<#3>
<?php
$db = $ilDB;
$result = $db->query('SELECT config_value FROM evhk_hsluobjdef_s WHERE config_key = "audio_types"');
if ($result->numRows() == 0) {
    $db->insert('evhk_hsluobjdef_s', array('config_key' => array('text', 'audio_types'), 'config_value' => array('text', 'mp3,flac,aac')));
}

$result = $db->query('SELECT config_value FROM evhk_hsluobjdef_s WHERE config_key = "message"');
if ($result->numRows() > 0) {
    $db->manipulateF('DELETE from evhk_hsluobjdef_s WHERE config_key = %s', ['string'], ['message']);
}

$result = $db->query('SELECT config_value FROM evhk_hsluobjdef_s WHERE config_key = "active"');
if ($result->numRows() > 0) {
    $db->manipulateF('DELETE from evhk_hsluobjdef_s WHERE config_key = %s', ['string'], ['active']);
}
?>