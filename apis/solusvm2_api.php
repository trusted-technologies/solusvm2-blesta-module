<?php

require_once dirname(__FILE__) . DS . 'solusvm2_response.php';

/**
 * SolusVM 2 API
 *
 * Communicates with the SolusVM 2 master over its JSON REST API
 * ({scheme}://{host}/api/v1/) using a Bearer API token.
 *
 * @package blesta
 * @subpackage blesta.components.modules.solusvm2
 */
class Solusvm2Api
{
    /**
     * @var string The base API URL, e.g. https://solusvm2.example.com/api/v1/
     */
    private $api_url;

    /**
     * @var string The API token
     */
    private $api_token;

    /**
     * @var array Details of the last request sent
     */
    private $last_request = [];

    /**
     * Initializes the API
     *
     * @param string $host The host name of the SolusVM 2 master (with or without scheme)
     * @param string $api_token The API token generated in SolusVM 2 (Access > API Tokens)
     */
    public function __construct($host, $api_token)
    {
        $host = preg_replace('#^https?://#i', '', rtrim(trim((string)$host), '/'));

        $this->api_url = 'https://' . $host . '/api/v1/';
        $this->api_token = $api_token;
    }

    /**
     * Returns the base API URL
     *
     * @return string
     */
    public function getApiUrl()
    {
        return $this->api_url;
    }

    /**
     * Returns the details of the last request sent
     *
     * @return array
     */
    public function lastRequest()
    {
        return $this->last_request;
    }

    /**
     * Sends a request to the API
     *
     * @param string $method The HTTP method (GET, POST, PUT, PATCH, DELETE)
     * @param string $path The API path relative to /api/v1/ (e.g. "servers/1/start")
     * @param array $params Parameters to send (query string for GET, JSON body otherwise)
     * @return Solusvm2Response
     */
    public function submit($method, $path, array $params = [])
    {
        $method = strtoupper($method);
        $url = $this->api_url . ltrim($path, '/');

        if ($method == 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($method != 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode((object)$params));
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            [
                'Authorization: Bearer ' . $this->api_token,
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        );

        $verify_ssl = Configure::get('Blesta.curl_verify_ssl');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify_ssl ? 2 : 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify_ssl ? 2 : 0);

        curl_setopt($ch, CURLOPT_URL, $url);

        $this->last_request = ['url' => $url, 'method' => $method, 'args' => $params];

        $response = curl_exec($ch);
        $status_code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($response === false) {
            $response = json_encode(['message' => 'cURL error: ' . curl_error($ch)]);
            $status_code = 0;
        }

        curl_close($ch);

        return new Solusvm2Response($response, $status_code);
    }

    /**
     * Fetches all items of a paginated list resource
     *
     * @param string $path The list resource path (e.g. "plans")
     * @param array $query Optional query parameters (e.g. filters)
     * @return Solusvm2Response The first response, with its "data" replaced by the merged item list
     */
    public function listAll($path, array $query = [])
    {
        $items = [];
        $page = 1;
        $response = null;

        do {
            $response = $this->submit('GET', $path, array_merge($query, ['page' => $page]));

            if ($response->status() != 'success') {
                return $response;
            }

            $data = $response->data();
            $items = array_merge($items, (array)($data['data'] ?? []));

            $last_page = (int)($data['meta']['last_page'] ?? 1);
            $page++;
        } while ($page <= $last_page);

        return new Solusvm2Response(json_encode(['data' => $items]), 200);
    }

    //
    // Users
    //

    /**
     * Finds a SolusVM 2 user by email
     *
     * @param string $email
     * @return Solusvm2Response
     */
    public function getUserByEmail($email)
    {
        return $this->submit('GET', 'users', ['filter' => ['search' => $email]]);
    }

    /**
     * Creates a SolusVM 2 user
     *
     * @param array $params User data (email, password, status, billing_user_id, roles, limit_group_id)
     * @return Solusvm2Response
     */
    public function createUser(array $params)
    {
        return $this->submit('POST', 'users', $params);
    }

    /**
     * Updates a SolusVM 2 user
     *
     * @param int $user_id
     * @param array $params Fields to update (e.g. billing_user_id)
     * @return Solusvm2Response
     */
    public function updateUser($user_id, array $params)
    {
        return $this->submit('PUT', 'users/' . (int)$user_id, $params);
    }

    //
    // Servers
    //

    /**
     * Creates a virtual server
     *
     * @param array $params The server create request (see ServerCreateRequestBuilder in the WHMCS module)
     * @return Solusvm2Response
     */
    public function createServer(array $params)
    {
        return $this->submit('POST', 'servers', $params);
    }

    /**
     * Fetches a virtual server
     *
     * @param int $server_id
     * @return Solusvm2Response
     */
    public function getServer($server_id)
    {
        return $this->submit('GET', 'servers/' . (int)$server_id);
    }

    /**
     * Deletes a virtual server
     *
     * @param int $server_id
     * @return Solusvm2Response
     */
    public function deleteServer($server_id)
    {
        return $this->submit('DELETE', 'servers/' . (int)$server_id);
    }

    /**
     * Updates a virtual server (hostname, boot mode, etc.)
     *
     * @param int $server_id
     * @param array $params Fields to update (e.g. name, fqdns, boot_mode)
     * @return Solusvm2Response
     */
    public function updateServer($server_id, array $params)
    {
        return $this->submit('PATCH', 'servers/' . (int)$server_id, $params);
    }

    /**
     * Performs a power/state action on a virtual server
     *
     * @param int $server_id
     * @param string $action One of: start, stop, restart, suspend, resume
     * @param array $params Optional action parameters (e.g. ['force' => true] for stop)
     * @return Solusvm2Response
     */
    public function serverAction($server_id, $action, array $params = [])
    {
        return $this->submit('POST', 'servers/' . (int)$server_id . '/' . $action, $params);
    }

    /**
     * Reinstalls a virtual server
     *
     * @param int $server_id
     * @param array $params The reinstall request (os, or application + application_data,
     *  plus optional user_data, ssh_keys, password)
     * @return Solusvm2Response
     */
    public function reinstallServer($server_id, array $params)
    {
        return $this->submit('POST', 'servers/' . (int)$server_id . '/reinstall', $params);
    }

    /**
     * Resizes a virtual server (delayed)
     *
     * @param int $server_id
     * @param array $params The resize request (plan_id, preserve_disk, custom_plan, backup_settings)
     * @return Solusvm2Response
     */
    public function resizeServer($server_id, array $params)
    {
        return $this->submit('POST', 'servers/' . (int)$server_id . '/resize', array_merge($params, ['delayed' => true]));
    }

    /**
     * Resets the root password of a virtual server
     *
     * @param int $server_id
     * @return Solusvm2Response Response data contains the new password
     */
    public function resetServerPassword($server_id)
    {
        return $this->submit('POST', 'servers/' . (int)$server_id . '/reset_password');
    }

    /**
     * Brings up the VNC console of a virtual server
     *
     * @param int $server_id
     * @return Solusvm2Response Response data contains the console URL
     */
    public function vncUp($server_id)
    {
        return $this->submit('POST', 'servers/' . (int)$server_id . '/vnc_up');
    }

    /**
     * Adds additional IP addresses to a virtual server
     *
     * @param int $server_id
     * @param int $count The number of IPs to add
     * @return Solusvm2Response
     */
    public function createAdditionalIps($server_id, $count)
    {
        return $this->submit(
            'POST',
            'servers/' . (int)$server_id . '/ips',
            ['count' => (int)$count, 'delayed' => true]
        );
    }

    /**
     * Removes IP addresses from a virtual server
     *
     * @param int $server_id
     * @param array $ids The IP address IDs to remove
     * @return Solusvm2Response
     */
    public function deleteAdditionalIps($server_id, array $ids)
    {
        return $this->submit(
            'DELETE',
            'servers/' . (int)$server_id . '/ips',
            ['ids' => array_values($ids), 'delayed' => true]
        );
    }

    /**
     * Fetches usage statistics of a virtual server
     *
     * @param string $type One of: cpu, network, disks, memory
     * @param string $uuid The UUID of the virtual server
     * @return Solusvm2Response
     */
    public function getUsage($type, $uuid)
    {
        return $this->submit('GET', 'usage/' . $type . '/' . $uuid);
    }

    //
    // Reference lists
    //

    public function listPlans()
    {
        return $this->listAll('plans');
    }

    public function listOsImages()
    {
        return $this->listAll('os_images');
    }

    public function listLocations()
    {
        return $this->listAll('locations');
    }

    public function listApplications()
    {
        return $this->listAll('applications');
    }

    public function listRoles()
    {
        return $this->listAll('roles');
    }

    public function listLimitGroups()
    {
        return $this->listAll('limit_groups');
    }

    public function listSshKeys()
    {
        return $this->listAll('ssh_keys');
    }

    public function listProjects()
    {
        return $this->listAll('projects');
    }

    public function listIsoImages()
    {
        return $this->listAll('iso_images');
    }

    /**
     * Fetches the boot settings of a virtual server
     *
     * @param int $server_id
     * @return Solusvm2Response
     */
    public function getServerBoot($server_id)
    {
        return $this->submit('GET', 'servers/' . (int)$server_id . '/boot');
    }

    /**
     * Sets the boot mode of a virtual server
     *
     * @param int $server_id
     * @param array $params Boot settings (mode: disk|rescue|iso, iso_image_id)
     * @return Solusvm2Response
     */
    public function setServerBoot($server_id, array $params)
    {
        return $this->submit('POST', 'servers/' . (int)$server_id . '/boot', $params);
    }
}
