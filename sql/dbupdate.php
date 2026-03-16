<#1>
<?php
if (!$ilDB->tableExists('ui_uihk_acrm_settings')) {
    $fields = [
        'setting_key' => [
            'type' => 'text',
            'length' => 64,
            'notnull' => true,
        ],
        'setting_value' => [
            'type' => 'clob',
            'notnull' => false,
        ],
    ];
    $ilDB->createTable('ui_uihk_acrm_settings', $fields);
}
if (!$ilDB->primaryExistsByFields('ui_uihk_acrm_settings', ['setting_key'])) {
    $ilDB->addPrimaryKey('ui_uihk_acrm_settings', ['setting_key']);
}
?>

<#2>
<?php
if (!$ilDB->tableExists('ui_uihk_acrm_activity')) {
    $fields = [
        'user_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true,
        ],
        'course_ref_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true,
        ],
        'first_seen' => [
            'type' => 'timestamp',
            'notnull' => true,
        ],
        'last_seen' => [
            'type' => 'timestamp',
            'notnull' => true,
        ],
    ];
    $ilDB->createTable('ui_uihk_acrm_activity', $fields);
}
if (!$ilDB->primaryExistsByFields('ui_uihk_acrm_activity', ['user_id', 'course_ref_id'])) {
    $ilDB->addPrimaryKey('ui_uihk_acrm_activity', ['user_id', 'course_ref_id']);
}
if (!$ilDB->indexExistsByFields('ui_uihk_acrm_activity', ['course_ref_id', 'last_seen'])) {
    $ilDB->addIndex('ui_uihk_acrm_activity', ['course_ref_id', 'last_seen'], 'i1');
}
?>

<#3>
<?php
if (!$ilDB->tableExists('ui_uihk_acrm_dispatch')) {
    $fields = [
        'user_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true,
        ],
        'rule_key' => [
            'type' => 'text',
            'length' => 128,
            'notnull' => true,
        ],
        'sent_count' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true,
            'default' => 0,
        ],
        'last_sent' => [
            'type' => 'timestamp',
            'notnull' => false,
        ],
        'last_reason' => [
            'type' => 'text',
            'length' => 32,
            'notnull' => false,
        ],
    ];
    $ilDB->createTable('ui_uihk_acrm_dispatch', $fields);
}
if (!$ilDB->primaryExistsByFields('ui_uihk_acrm_dispatch', ['user_id', 'rule_key'])) {
    $ilDB->addPrimaryKey('ui_uihk_acrm_dispatch', ['user_id', 'rule_key']);
}
?>

<#4>
<?php
if (!$ilDB->tableExists('ui_uihk_acrm_optout')) {
    $fields = [
        'user_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true,
        ],
        'course_ref_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true,
        ],
        'created_at' => [
            'type' => 'timestamp',
            'notnull' => true,
        ],
    ];
    $ilDB->createTable('ui_uihk_acrm_optout', $fields);
}
if (!$ilDB->primaryExistsByFields('ui_uihk_acrm_optout', ['user_id', 'course_ref_id'])) {
    $ilDB->addPrimaryKey('ui_uihk_acrm_optout', ['user_id', 'course_ref_id']);
}
?>


<#5>
<?php
if (!$ilDB->tableExists('ui_uihk_acrm_crule')) {
    $fields = [
        'course_ref_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true,
        ],
        'active' => [
            'type' => 'integer',
            'length' => 1,
            'notnull' => true,
            'default' => 0,
        ],
        'delay_days' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true,
            'default' => 5,
        ],
        'repeat_every_days' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true,
            'default' => 5,
        ],
        'max_reminders' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true,
            'default' => 3,
        ],
        'allow_opt_out' => [
            'type' => 'integer',
            'length' => 1,
            'notnull' => true,
            'default' => 1,
        ],
        'subject' => [
            'type' => 'clob',
            'notnull' => false,
        ],
        'body' => [
            'type' => 'clob',
            'notnull' => false,
        ],
    ];
    $ilDB->createTable('ui_uihk_acrm_crule', $fields);
}
if (!$ilDB->primaryExistsByFields('ui_uihk_acrm_crule', ['course_ref_id'])) {
    $ilDB->addPrimaryKey('ui_uihk_acrm_crule', ['course_ref_id']);
}
?>
