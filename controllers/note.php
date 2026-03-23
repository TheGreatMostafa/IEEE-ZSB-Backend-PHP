<?php

$config = require 'config.php';
$db = new Database($config['database']);

$currentUserID = 1;

$note = $db->query('SELECT * from notes where id = :id', [
    'id' => $_GET['id']
])->findOrFail();

authorize($note['user_id'] === $currentUserID);

$heading = "Note #" . $note['id'] . ": " . $note['body'];

require "views/note.view.php";