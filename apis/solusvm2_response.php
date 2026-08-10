<?php

/**
 * SolusVM 2 API Response
 *
 * @package blesta
 * @subpackage blesta.components.modules.solusvm2
 */
class Solusvm2Response
{
    /**
     * @var string The raw response body
     */
    private $raw;

    /**
     * @var int The HTTP status code
     */
    private $status_code;

    /**
     * @var array|null The JSON-decoded response body
     */
    private $data;

    /**
     * Initializes the response
     *
     * @param string $raw The raw response body
     * @param int $status_code The HTTP status code
     */
    public function __construct($raw, $status_code = 0)
    {
        $this->raw = $raw;
        $this->status_code = (int)$status_code;

        $decoded = json_decode((string)$raw, true);
        $this->data = is_array($decoded) ? $decoded : null;
    }

    /**
     * Returns the status of the response
     *
     * @return string "success" or "error"
     */
    public function status()
    {
        return ($this->status_code >= 200 && $this->status_code < 300 && $this->data !== null)
            ? 'success'
            : 'error';
    }

    /**
     * Returns the HTTP status code
     *
     * @return int
     */
    public function statusCode()
    {
        return $this->status_code;
    }

    /**
     * Returns the JSON-decoded response body
     *
     * @return array|null
     */
    public function data()
    {
        return $this->data;
    }

    /**
     * Returns a flat list of error messages from the response, or false if none
     *
     * @return array|bool A numerically indexed list of error messages, false if the
     *  response does not represent an error
     */
    public function errors()
    {
        if ($this->status() == 'success') {
            return false;
        }

        $errors = [];

        // Transport-level error (e.g. curl failure, non-JSON body)
        if ($this->data === null) {
            $errors[] = !empty($this->raw) ? $this->raw : 'Empty or invalid API response';
            return $errors;
        }

        if (!empty($this->data['message'])) {
            $errors[] = $this->data['message'];
        }

        // Laravel-style validation errors: {"errors": {"field": ["msg", ...]}}
        if (!empty($this->data['errors']) && is_array($this->data['errors'])) {
            foreach ($this->data['errors'] as $field => $messages) {
                foreach ((array)$messages as $message) {
                    $errors[] = (is_string($field) ? $field . ': ' : '') . $message;
                }
            }
        }

        if (empty($errors)) {
            $errors[] = 'Unknown API error (HTTP ' . $this->status_code . ')';
        }

        return $errors;
    }

    /**
     * Returns the raw response body
     *
     * @return string
     */
    public function raw()
    {
        return $this->raw;
    }
}
