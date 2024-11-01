<?php

namespace local_dlcmanager\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Adhoc task to send course deletion information to an external service.
 */
class send_course_deleted extends \core\task\adhoc_task {

    /**
     * Creates an instance of the send_course_deleted task.
     *
     * @param int $courseid The ID of the course.
     * @param string $uuid The UUID of the course.
     * @return self The instance of the task.
     */
    public static function instance(int $courseid, string $uuid): self {
        $task = new self();
        $task->set_custom_data((object) [
            'courseid' => $courseid,
            'uuid' => $uuid
        ]);

        return $task;
    }

    /**
     * Executes the task to send course deletion information to an external service.
     *
     * This method sends a DELETE request to the configured Laravel service endpoint
     * with the course UUID to notify the service of the course deletion.
     *
     * @throws \moodle_exception If the request to the external service fails.
     */
    public function execute() {
        mtrace("send_course_deleted started");
        $data = $this->get_custom_data();

        // Send DELETE request to Laravel service
        $endpoint = get_config('local_dlcmanager', 'api_base_url') . 'digitaloffers/' . $data->uuid;
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === FALSE || $httpCode >= 400) {
            $error = "Failed to send data to Laravel service: " . curl_error($ch);
            curl_close($ch);
            throw new \moodle_exception($error);
        } else {
            mtrace("Delete request sent to Laravel service successfully: " . $response);
        }

        curl_close($ch);

        mtrace("send_course_deleted finished");
    }
}
