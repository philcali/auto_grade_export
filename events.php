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
        if ($data->old_export->itemid != $data->new_export->itemid) {
            $data->old_export->wipe_history();
        }

        return true;
    }

    /**
     * Cleans up orphaned fields if the query changes
     *
     * @param mixed $data {old_query, new_query}
     */
    public static function query_updated($data) {
        global $DB;

        foreach ($data->old_query->get_fields() as $field) {
            foreach ($data->new_query->get_fields() as $inner_field) {
                // The new query has this field, skip it
                if ($field->id === $inner_field->id) continue 2;
            }

            // This field was not found... delete it
            $DB->delete_records('block_up_export_fields', array('id' => $field->id));
        }

        return true;
    }

    /**
     * Place holder for query cleanup
     */
    public static function query_deleted($query) {
        global $DB;
        $DB->delete_records('block_up_export_entry', array('queryid' => $query->id));
        return true;
    }

    /**
     * Instantiate an Oracle query
     *
     * @param query_exporter
     */
    public static function oracle_query_entry(query_exporter $exporter) {
        $query = oracle_query::get(array('id' => $exporter->entry->queryid));
        $exporter->set_query($query);
        return true;
    }

    /**
     * Loads this plugins default export type
     *
     * @param $data->types
     */
    public static function export_entry_types($data) {
        $data->types['oracle_query'] = get_string('query_sql', 'block_up_grade_export');
        return true;
    }
}
