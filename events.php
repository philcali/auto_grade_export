<?php

/**
 * Internal event handler to log exports and background clean up
 */
abstract class up_grade_handler {
    /**
     * Grades have been exported
     *
     * $param stdClass $data
     */
    public static function exported_grades($data) {
        global $DB;

        $history = new stdClass;
        $history->exportid = $data->export->id;
        $history->success = empty($data->errors);
        $history->timestamp = time();
        $history->userid = $data->userid ?: null;

        $history->id = $DB->insert_record('block_up_export_history', $history);

        foreach ($data->results as $result) {
            $export_item = new stdClass;
            $export_item->historyid = $history->id;
            $export_item->userid = $result->userid;
            $export_item->grade = $result->finalgrade;

            $export_item->id = $DB->insert_record('block_up_export_items', $export_item);
        }

        return true;
    }

    /**
     * Cleans up history table upon export deletion
     *
     * @param query_connector $query
     */
    public static function export_deleted($export) {
        $export->wipe_history();
        return true;
    }

    /**
     * Cleans up history if the export changed
     *
     * @param mixed $data {old_export, new_export}
     */
    public static function export_updated($data) {
        if ($data->old_export->itemid != $data->new_export->itemid or
            $data->old_export->queryid != $data->new_export->queryid)  {
            $data->old_export->wipe_history();
        }

        return true;
    }
}
