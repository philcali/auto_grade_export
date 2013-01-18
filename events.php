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
        $history->queryid = $data->query->id;
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
     * Cleans up history table upon query deletion
     *
     * @param query_connector $query
     */
    public static function query_deleted($query) {
        $query->wipe_history();
        return true;
    }

    /**
     * Cleans up history if the query itemid changed
     *
     * @param mixed $data {old_query, new_query}
     */
    public static function query_updated($data) {
        if ($data->old_query->itemid != $data->new_query->itemid or
            $data->old_query->externalid != $data->new_query->externalid)  {
            $data->old_query->wipe_history();
        }

        return true;
    }
}
