<?php

$to_handlers = function($event) {
    return array(
        'handlerfile' => '/blocks/up_grade_export/events.php',
        'handlerfunction' => array('up_grade_handler', $event),
        'schedule' => 'instant',
    );
};

$events = array(
    'exported_grades',
    'query_deleted',
    'query_updated',
);

$handlers = array_combine($events, array_map($to_handlers, $events));
