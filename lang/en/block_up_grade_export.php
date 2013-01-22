<?php

$string['pluginname'] = 'Grade export';
$string['pluginname_desc'] = 'This plugin will export grades from an external Oracle database from custom queries';

// Block Strings
$string['build_query'] = 'Create query';
$string['list_queries'] = 'Manage queries';
$string['build_export'] = 'Create export';
$string['list_exports'] = 'Manage exports';
$string['delete_query'] = 'Delete query';
$string['export'] = 'Export grades';
$string['no_permission'] = 'You do not have permission to build an export query';

// Capabilities
$string['up_grade_export:canbuildquery'] = 'Allows users to build queries to import grades';
$string['up_grade_export:canexport'] = 'Allows users to export grades from built queries';

// Settings
$string['host'] = 'Host';
$string['host_desc'] = 'The host (if different) to authenticate with';

$string['username'] = 'Username';
$string['username_desc'] = 'The username name (if any) to authenticate with';

$string['password'] = 'Password';
$string['password_desc'] = 'The password (if any) to authenticate with';

$string['cron_target'] = 'Cron target';
$string['cron_target_desc'] = 'Set a cron time interval at the desired time to run automated exports.';

$string['cron_interval'] = 'Cron interval';
$string['cron_interval_desc'] = 'Set a cron interval in seconds.';

// Query export
$string['can_pull'] = 'Cannot pull grades for a non-existent grade item.';
$string['export_to'] = 'Export {$a->name} to {$a->table}';
$string['export_success'] = 'Successfully exported the grades';
$string['export_failed'] = 'Export failed for {$a->fullname} with grade {$a->finalgrade}';
$string['last_export'] = 'Last export occurred on <em>{$a->date}</em>: {$a->status}';

// Query list
$string['deleted'] = '(Deleted)';
$string['no_queries'] = 'No queries created. Continue to create one.';
$string['query_failed'] = 'Query could not be saved';
$string['query_delete_failed'] = 'Query could not be deleted';
$string['query_updated'] = 'Query was successfully updated';
$string['query_created'] = 'Query was successfully created';
$string['query_deleted'] = 'Query was successfully deleted';

// Query builder
$string['no_query'] = 'Could not find that query.';
$string['externalid'] = 'External ID';
$string['automated'] = 'Automated';
$string['select_grade'] = 'Select a grade:';
$string['clear_course'] = 'Clear selection';

// Query delete
$string['delete_confirm'] = 'Deleting a query is an irrevocable action. Are you sure you want to remove this query?';
