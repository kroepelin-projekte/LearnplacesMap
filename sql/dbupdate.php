<#1>
<?php
global $DIC;
$db = $DIC->database();
$map_table_name = 'kpg_lmap_map';
if (!$db->tableExists($map_table_name)) {
    $db->createTable($map_table_name, [
        'id' => [
            'type' => 'integer',
            'length' => '4',
            'notnull' => true,
        ],
        'mode' => [
            'type' => 'text',
            'length' => '255',
            'notnull' => true,
        ],
        'title' => [
            'type' => 'text',
            'length' => '500',
            'notnull' => true,
        ],
        'description' => [
            'type' => 'text',
            'length' => '500',
            'notnull' => true,
        ],
    ]);

    if (!$db->sequenceExists($map_table_name)) {
        $db->createSequence($map_table_name);
    }
}

$tour_table_name = 'kpg_lmap_tour';
if (!$db->tableExists($tour_table_name)) {
    $db->createTable($tour_table_name, [
        'id' => [
            'type' => 'integer',
            'length' => '4',
            'notnull' => true,
        ],
        'map_id' => [
            'type' => 'integer',
            'length' => '4',
            'notnull' => true,
        ],
        'learnplace_ref_id' => [
            'type' => 'integer',
            'length' => '4',
            'notnull' => true,
        ],
        'position' => [
            'type' => 'integer',
            'length' => '4',
            'notnull' => true,
        ],
    ]);

    if (!$db->sequenceExists($tour_table_name)) {
        $db->createSequence($tour_table_name);
    }
}
?>