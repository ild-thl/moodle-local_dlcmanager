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

        $json_data = json_decode($moochubMetadata, true); // Decode JSON string to array

        // Check the HTTP status code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode >= 400) {
            // Check if the data contains a key error
            if (array_key_exists('errors', $json_data)) {
                $error = "Error fetching Moochub metadata for course ID " . $data->courseid . ". Found errors in json data: " . json_encode($json_data['errors']);
                curl_close($ch);
                throw new \moodle_exception($error);
            }
            // Otherwise, throw a generic error
            $error = "Failed to fetch Moochub metadata: HTTP status code " . $httpCode . " URL: " . $moochubUrl;
            curl_close($ch);
            throw new \moodle_exception($error);
        }

        curl_close($ch);

        // Check that the JSON data is valid and contains the expected keys
        if (!is_array($json_data) || !array_key_exists('data', $json_data) || !array_key_exists('links', $json_data)) {
            $error = "Invalid JSON data fetched from Moochub: " . $moochubMetadata;
            throw new \moodle_exception($error);
        }

        // Prepare data for Laravel API
        $endpoint = get_config('local_dlcmanager', 'api_base_url') . 'digitaloffers/moochub';
        $postData = [
            'external_id' => $data->uuid,
            'metadata' => $json_data,
        ];

        // Send data to Laravel service
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData)); // Encode array to JSON
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Origin: ' . $CFG->wwwroot, // hub api enpoint expects origin header to check if request is from authorized source
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === FALSE || $httpCode >= 400) {
            $error = "Failed to send data to Laravel service: " . curl_error($ch) . " (HTTP code: $httpCode)" . " (Response: $response)";
            curl_close($ch);
            throw new \moodle_exception($error);
        } else {
            $response_data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($response_data['status']) || $response_data['status'] !== 'success') {
                $error = "Failed to send data to Laravel service: Invalid response received. (HTTP code: $httpCode)" . " (Response: $response)";
                curl_close($ch);
                throw new \moodle_exception($error);
            }
            mtrace("Data sent to Laravel service successfully: " . $response);
        }

        curl_close($ch);

        mtrace("send_course_metadata finished");
    }
}
