<?php

namespace local_dlcmanager\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Adhoc task to send course metadata to an external service.
 */
class send_course_metadata extends \core\task\adhoc_task {

    /**
     * Creates an instance of the send_course_metadata task.
     *
     * @param int $courseid The ID of the course.
     * @param string $uuid The UUID of the course.
     * @return self The instance of the task.
     */
    public static function instance(int $courseid, string $uuid): self {
        $task = new self();
        $task->set_custom_data((object) [
            'courseid' => $courseid,
            'uuid' => $uuid,
        ]);

        return $task;
    }

    /**
     * Executes the task to send course metadata to an external service.
     *
     * This method fetches the course metadata from the Moochub endpoint and sends
     * it to the configured Laravel service endpoint.
     *
     * @throws \moodle_exception If the request to the external service fails.
     */
    public function execute() {
        global $CFG;
        mtrace("send_course_metadata started");
        $data = $this->get_custom_data();

        // Fetch Moochub metadata
        $moochubUrl = $CFG->wwwroot . '/local/ildmeta/get_moochub_courses.php?id=' . $data->courseid;

        // Initialize cURL session
        $ch = curl_init($moochubUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects

        // Execute cURL request
        $moochubMetadata = curl_exec($ch);
        if ($moochubMetadata === FALSE) {
            $error = "Failed to fetch Moochub metadata: " . curl_error($ch);
            curl_close($ch);
            throw new \moodle_exception($error);
        }

        curl_close($ch);

        // Prepare data for Laravel API
        $endpoint = get_config('local_dlcmanager', 'api_base_url') . 'digitaloffers/moochub';
        $postData = [
            'external_id' => $data->uuid,
            'metadata' => json_decode($moochubMetadata, true), // Decode JSON string to array
        ];

        // Send data to Laravel service
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData)); // Encode array to JSON
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === FALSE || $httpCode >= 400) {
            $error = "Failed to send data to Laravel service: " . curl_error($ch) . " (HTTP code: $httpCode)" . " (Response: $response)";
            curl_close($ch);
            throw new \moodle_exception($error);
        } else {
            mtrace("Data sent to Laravel service successfully: " . $response);
        }

        curl_close($ch);

        mtrace("send_course_metadata finished");
    }
}
