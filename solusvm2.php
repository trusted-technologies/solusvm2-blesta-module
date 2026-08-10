<?php

use Blesta\Core\Util\Validate\Server;

/**
 * SolusVM 2 Module
 *
 * Provisions virtual servers on a SolusVM 2 master over its JSON REST API.
 *
 * @package blesta
 * @subpackage blesta.components.modules.solusvm2
 */
class Solusvm2 extends Module
{
    /**
     * @var array Encrypted service field names
     */
    private $encrypted_fields = ['solusvm2_root_password'];

    /**
     * Initializes the module
     */
    public function __construct()
    {
        // Load configuration required by this module
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        // Load components required by this module
        Loader::loadComponents($this, ['Input']);

        // Load the language required by this module
        Language::loadLang('solusvm2', null, dirname(__FILE__) . DS . 'language' . DS);

        // Load config
        Configure::load('solusvm2', dirname(__FILE__) . DS . 'config' . DS);
    }

    /**
     * Performs any necessary bootstraping actions. Sets Input errors on
     * failure, preventing the module from being added.
     */
    public function install()
    {
        $errors = [];

        // Ensure the the system meets the requirements for this module
        if (!extension_loaded('curl')) {
            $errors['curl'] = ['required' => Language::_('Solusvm2.!error.curl_required', true)];
        }

        if (!empty($errors)) {
            $this->Input->setErrors($errors);
        }
    }

    /**
     * Attempts to validate service info. This is the top-level error checking method. Sets Input errors on failure.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of user supplied info to satisfy the request
     * @return bool True if the service validates, false otherwise. Sets Input errors when false.
     */
    public function validateService($package, array $vars = null)
    {
        // Set rules
        $rules = [
            'solusvm2_hostname' => [
                'format' => [
                    'pre_format' => [[$this, 'replaceText'], '', '/^\s*www\./i'],
                    'rule' => [[$this, 'validateHostName'], true],
                    'message' => Language::_('Solusvm2.!error.solusvm2_hostname.format', true)
                ]
            ],
            'solusvm2_server_id' => [
                'format' => [
                    'if_set' => true,
                    'rule' => ['matches', '/^[0-9]+$/'],
                    'message' => Language::_('Solusvm2.!error.solusvm2_server_id.format', true)
                ]
            ]
        ];

        // The OS (or application) must be given if it can be set by the client
        if (isset($package->meta->set_os) && $package->meta->set_os == 'client') {
            $rules['solusvm2_os'] = [
                'valid' => [
                    'rule' => [
                        [$this, 'validateOsChoice'],
                        ($vars['solusvm2_application'] ?? null),
                        $package->module_row,
                        $package->module_group
                    ],
                    'message' => Language::_('Solusvm2.!error.solusvm2_os.valid', true)
                ]
            ];
        } elseif (
            isset($package->meta->set_application)
            && $package->meta->set_application == 'client'
            && !empty($vars['solusvm2_application'])
        ) {
            // The OS comes from the package, only validate the application chosen by the client
            $rules['solusvm2_application'] = [
                'valid' => [
                    'rule' => [
                        [$this, 'validateApplication'],
                        $package->module_row,
                        $package->module_group
                    ],
                    'message' => Language::_('Solusvm2.!error.solusvm2_application.valid', true)
                ]
            ];
        }

        // Server ID is not required on add
        if (empty($vars['solusvm2_server_id'])) {
            unset($rules['solusvm2_server_id']);
        }

        $this->Input->setRules($rules);
        return $this->Input->validates($vars);
    }

    /**
     * Attempts to validate an existing service against a set of service info updates. Sets Input errors on failure.
     *
     * @param stdClass $service A stdClass object representing the service to validate for editing
     * @param array $vars An array of user-supplied info to satisfy the request
     * @return bool True if the service update validates or false otherwise. Sets Input errors when false.
     */
    public function validateServiceEdit($service, array $vars = null)
    {
        // Set rules
        $rules = [
            'solusvm2_hostname' => [
                'format' => [
                    'if_set' => true,
                    'pre_format' => [[$this, 'replaceText'], '', '/^\s*www\./i'],
                    'rule' => [[$this, 'validateHostName'], true],
                    'message' => Language::_('Solusvm2.!error.solusvm2_hostname.format', true)
                ]
            ],
            'solusvm2_server_id' => [
                'format' => [
                    'if_set' => true,
                    'rule' => ['matches', '/^[0-9]+$/'],
                    'message' => Language::_('Solusvm2.!error.solusvm2_server_id.format', true)
                ]
            ]
        ];

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);

        // The OS must be valid if changed
        if (
            isset($service_fields->solusvm2_os)
            && isset($vars['solusvm2_os'])
            && $service_fields->solusvm2_os != $vars['solusvm2_os']
        ) {
            $rules['solusvm2_os'] = [
                'valid' => [
                    'rule' => [
                        [$this, 'validateOs'],
                        $service->package->module_row,
                        $service->package->module_group
                    ],
                    'message' => Language::_('Solusvm2.!error.solusvm2_os.valid', true)
                ]
            ];
        }

        $this->Input->setRules($rules);
        return $this->Input->validates($vars);
    }

    /**
     * Adds the service to the remote server. Sets Input errors on failure,
     * preventing the service from being added.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of user supplied info to satisfy the request
     * @param stdClass $parent_package A stdClass object representing the parent
     *  service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent
     *  service of the service being added (if the current service is an addon
     *  service and parent service has already been provisioned)
     * @param string $status The status of the service being added. These include:
     *  - active
     *  - canceled
     *  - pending
     *  - suspended
     * @return array A numerically indexed array of meta fields to be stored for this service containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function addService(
        $package,
        array $vars = null,
        $parent_package = null,
        $parent_service = null,
        $status = 'pending'
    ) {
        // Load the API
        $row = $this->getModuleRow();
        $api = $this->getApi($row->meta->host, $row->meta->api_token);

        // Get the fields for the service
        $params = $this->getFieldsFromInput($vars, $package);

        // Validate the service-specific fields
        $this->validateService($package, $vars);

        if ($this->Input->errors()) {
            return;
        }

        // Since validating the service rules does not update data in pre/post formatting,
        // re-apply the formatting changes manually
        if (isset($vars['solusvm2_hostname'])) {
            $vars['solusvm2_hostname'] = strtolower($this->replaceText($vars['solusvm2_hostname'], '', '/^\s*www\./i'));
            $params['name'] = $vars['solusvm2_hostname'];
            $params['fqdns'] = [$vars['solusvm2_hostname']];
        }

        $response = null;
        $user_id = null;

        // Only provision the service if 'use_module' is true
        if ($vars['use_module'] == 'true') {
            if (!empty($vars['solusvm2_server_id'])) {
                // Attach an existing server: verify it exists
                $this->log(
                    $row->meta->host . '|servers/' . (int)$vars['solusvm2_server_id'],
                    serialize([]),
                    'input',
                    true
                );
                $response = $this->parseResponse($api->getServer((int)$vars['solusvm2_server_id']), $row);
            } else {
                // Sync the SolusVM 2 user for this client
                $user_id = $this->syncUser(($vars['client_id'] ?? 0), $row, $package);

                if (!$this->Input->errors()) {
                    $params['user'] = $user_id;

                    $masked_params = $params;
                    $masked_params['password'] = '***';

                    // Create the virtual server
                    $this->log($row->meta->host . '|servers', serialize($masked_params), 'input', true);
                    $response = $this->parseResponse($api->createServer($params), $row);
                }
            }

            if ($this->Input->errors()) {
                return;
            }
        }

        $server_data = ($response['data'] ?? []);

        // Determine the primary IPv4 address from the create response (may be empty while provisioning)
        $main_ip = null;
        if (!empty($server_data['ip_addresses']['ipv4'][0]['ip'])) {
            $main_ip = $server_data['ip_addresses']['ipv4'][0]['ip'];
        }

        // Return service fields
        $fields = [
            [
                'key' => 'solusvm2_server_id',
                'value' => ($server_data['id'] ?? (!empty($vars['solusvm2_server_id']) ? $vars['solusvm2_server_id'] : null)),
                'encrypted' => 0
            ],
            [
                'key' => 'solusvm2_user_id',
                'value' => ($server_data['user']['id'] ?? $user_id),
                'encrypted' => 0
            ],
            [
                'key' => 'solusvm2_hostname',
                'value' => $params['name'],
                'encrypted' => 0
            ],
            [
                'key' => 'solusvm2_main_ip_address',
                'value' => $main_ip,
                'encrypted' => 0
            ],
            [
                'key' => 'solusvm2_root_password',
                'value' => $params['password'],
                'encrypted' => 1
            ],
            [
                'key' => 'solusvm2_os',
                'value' => ($params['os'] ?? null),
                'encrypted' => 0
            ],
            [
                'key' => 'solusvm2_application',
                'value' => ($params['application'] ?? null),
                'encrypted' => 0
            ],
            [
                'key' => 'solusvm2_plan',
                'value' => $params['plan'],
                'encrypted' => 0
            ]
        ];

        // Ensure all available encrypted fields are set to be encrypted
        foreach ($fields as &$field) {
            if (in_array($field['key'], $this->encrypted_fields)) {
                $field['encrypted'] = 1;
            }
        }

        return $fields;
    }

    /**
     * Edits the service on the remote server. Sets Input errors on failure,
     * preventing the service from being edited.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $vars An array of user supplied info to satisfy the request
     * @param stdClass $parent_package A stdClass object representing the parent
     *  service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent
     *  service of the service being edited (if the current service is an addon service)
     * @return array A numerically indexed array of meta fields to be stored for this service containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function editService($package, $service, array $vars = [], $parent_package = null, $parent_service = null)
    {
        $row = $this->getModuleRow($package->module_row);
        $api = $this->getApi($row->meta->host, $row->meta->api_token);

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);

        // Validate the service-specific fields
        $this->validateServiceEdit($service, $vars);

        if ($this->Input->errors()) {
            return;
        }

        $server_id = !empty($vars['solusvm2_server_id'])
            ? $vars['solusvm2_server_id']
            : ($service_fields->solusvm2_server_id ?? null);
        $new_root_password = null;
        $main_ip = ($service_fields->solusvm2_main_ip_address ?? null);

        // Since validating the service rules does not update data in pre/post formatting,
        // re-apply the formatting changes manually
        if (isset($vars['solusvm2_hostname'])) {
            $vars['solusvm2_hostname'] = strtolower($this->replaceText($vars['solusvm2_hostname'], '', '/^\s*www\./i'));
        }

        // Only perform actions on the remote server if 'use_module' is true
        if (($vars['use_module'] ?? 'false') == 'true' && $server_id) {
            // Change the hostname
            if (
                !empty($vars['solusvm2_hostname'])
                && $vars['solusvm2_hostname'] != ($service_fields->solusvm2_hostname ?? '')
            ) {
                $data = ['name' => $vars['solusvm2_hostname'], 'fqdns' => [$vars['solusvm2_hostname']]];

                $this->log($row->meta->host . '|servers/' . $server_id . ' (hostname)', serialize($data), 'input', true);
                $this->parseResponse($api->updateServer($server_id, $data), $row);
            }

            // Reinstall the server
            if (
                !$this->Input->errors()
                && !empty($vars['confirm_reinstall'])
                && (!empty($vars['solusvm2_os']) || !empty($vars['solusvm2_application']))
            ) {
                if (!empty($vars['solusvm2_application'])) {
                    $data = [
                        'application' => (int)$vars['solusvm2_application'],
                        'application_data' => new stdClass()
                    ];
                } else {
                    $data = ['os' => (int)$vars['solusvm2_os']];
                }

                if (!empty($vars['solusvm2_user_data'])) {
                    $data['user_data'] = str_replace("\r\n", "\n", $vars['solusvm2_user_data']);
                } elseif (!empty($package->meta->user_data)) {
                    $data['user_data'] = str_replace("\r\n", "\n", $package->meta->user_data);
                }

                if (!empty($vars['solusvm2_ssh_keys']) && is_array($vars['solusvm2_ssh_keys'])) {
                    $data['ssh_keys'] = array_values(array_filter(array_map('intval', $vars['solusvm2_ssh_keys'])));
                }

                $this->log($row->meta->host . '|servers/' . $server_id . '/reinstall', serialize($data), 'input', true);
                $this->parseResponse($api->reinstallServer($server_id, $data), $row);
            }

            // Reset the root password
            if (!$this->Input->errors() && !empty($vars['solusvm2_reset_password'])) {
                $this->log(
                    $row->meta->host . '|servers/' . $server_id . '/reset_password',
                    serialize([]),
                    'input',
                    true
                );
                $response = $this->parseResponse($api->resetServerPassword($server_id), $row);

                if (!$this->Input->errors() && !empty($response['data']['password'])) {
                    $new_root_password = $response['data']['password'];
                }
            }

            // Update the server limits and additional IPs when config options change
            if (!$this->Input->errors() && isset($vars['configoptions'])) {
                $main_ip = $this->updateServiceOptions($package, $service_fields, $vars, $row, $api) ?? $main_ip;
            }

            if ($this->Input->errors()) {
                return;
            }
        }

        // Return all service fields
        $fields = [
            [
                'key' => 'solusvm2_server_id',
                'value' => $server_id,
                'encrypted' => 0
            ],
            [
                'key' => 'solusvm2_user_id',
                'value' => ($service_fields->solusvm2_user_id ?? null),
                'encrypted' => 0
            ],
            [
                'key' => 'solusvm2_hostname',
                'value' => ($vars['solusvm2_hostname'] ?? ($service_fields->solusvm2_hostname ?? null)),
                'encrypted' => 0
            ],
            [
                'key' => 'solusvm2_main_ip_address',
                'value' => $main_ip,
                'encrypted' => 0
            ],
            [
                'key' => 'solusvm2_root_password',
                'value' => ($new_root_password ?? ($service_fields->solusvm2_root_password ?? null)),
                'encrypted' => 1
            ],
            [
                'key' => 'solusvm2_os',
                'value' => ($vars['solusvm2_os'] ?? ($service_fields->solusvm2_os ?? null)),
                'encrypted' => 0
            ],
            [
                'key' => 'solusvm2_application',
                'value' => ($vars['solusvm2_application'] ?? ($service_fields->solusvm2_application ?? null)),
                'encrypted' => 0
            ],
            [
                'key' => 'solusvm2_plan',
                'value' => ($package->meta->plan ?? ($service_fields->solusvm2_plan ?? null)),
                'encrypted' => 0
            ]
        ];

        // Ensure all available encrypted fields are set to be encrypted
        foreach ($fields as &$field) {
            if (in_array($field['key'], $this->encrypted_fields)) {
                $field['encrypted'] = 1;
            }
        }

        return $fields;
    }

    /**
     * Updates the server limits (custom plan) and additional IP addresses based on config options.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service_fields The service fields as an object
     * @param array $vars An array of user supplied info
     * @param stdClass $row The module row
     * @param Solusvm2Api $api The API instance
     * @return string|null The primary IPv4 address if it was refreshed, null otherwise
     */
    private function updateServiceOptions($package, $service_fields, $vars, $row, $api)
    {
        $server_id = $service_fields->solusvm2_server_id ?? null;
        if (!$server_id) {
            return null;
        }

        $this->loadLib('solusvm2_service_options');
        $options = new Solusvm2ServiceOptions(($vars['configoptions'] ?? []));

        // Resize the server when any plan-related option is set
        $custom_plan = $options->getCustomPlan();

        // Enable snapshots for the server when set on the package
        if (isset($package->meta->snapshot_enabled) && $package->meta->snapshot_enabled == '1') {
            $custom_plan['is_snapshots_enabled'] = true;
        }

        if ($custom_plan) {
            $resize = [
                'plan_id' => (int)(($service_fields->solusvm2_plan ?? null) ?: ($package->meta->plan ?? 0)),
                'preserve_disk' => !isset($custom_plan['params']['disk']),
                'custom_plan' => $custom_plan,
                'backup_settings' => $this->getBackupSettings($package)
            ];

            $this->log($row->meta->host . '|servers/' . $server_id . '/resize', serialize($resize), 'input', true);
            $this->parseResponse($api->resizeServer($server_id, $resize), $row);
        }

        // Sync the number of additional IPv4 addresses
        $extra_ips = $options->getAdditionalIpCount();
        if ($extra_ips === null || $this->Input->errors()) {
            return null;
        }

        // Fetch the current IPs of the server
        $this->log($row->meta->host . '|servers/' . $server_id, serialize([]), 'input', true);
        $server = $this->parseResponse($api->getServer($server_id), $row);

        if ($this->Input->errors()) {
            return null;
        }

        $ipv4 = array_values((array)($server['data']['ip_addresses']['ipv4'] ?? []));
        $extra = [];
        foreach ($ipv4 as $ip) {
            if (!empty($ip['is_primary'])) {
                continue;
            }
            $extra[] = $ip;
        }

        // If the API does not flag a primary IP, treat the first one as primary
        if (count($extra) == count($ipv4) && count($ipv4) > 1) {
            $extra = array_slice($ipv4, 1);
        }

        $current_count = count($extra);
        if ($extra_ips > $current_count) {
            $data = ['count' => ($extra_ips - $current_count)];
            $this->log($row->meta->host . '|servers/' . $server_id . '/ips', serialize($data), 'input', true);
            $this->parseResponse($api->createAdditionalIps($server_id, $extra_ips - $current_count), $row);
        } elseif ($extra_ips < $current_count) {
            $ids = array_slice(array_column($extra, 'id'), $extra_ips);
            $this->log($row->meta->host . '|servers/' . $server_id . '/ips (delete)', serialize($ids), 'input', true);
            $this->parseResponse($api->deleteAdditionalIps($server_id, $ids), $row);
        }

        return ($server['data']['ip_addresses']['ipv4'][0]['ip'] ?? null);
    }

    /**
     * Cancels the service on the remote server. Sets Input errors on failure,
     * preventing the service from being canceled.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent
     *  service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent
     *  service of the service being canceled (if the current service is an addon service)
     * @return mixed null to maintain the current service meta data, or an array of meta fields
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function cancelService($package, $service, $parent_package = null, $parent_service = null)
    {
        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);

        if (empty($service_fields->solusvm2_server_id)) {
            return null;
        }

        $row = $this->getModuleRow($package->module_row);
        $api = $this->getApi($row->meta->host, $row->meta->api_token);

        $this->log(
            $row->meta->host . '|servers/' . $service_fields->solusvm2_server_id . ' (delete)',
            serialize([]),
            'input',
            true
        );
        $this->parseResponse($api->deleteServer($service_fields->solusvm2_server_id), $row);

        return null;
    }

    /**
     * Suspends the service on the remote server. Sets Input errors on failure,
     * preventing the service from being suspended.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent
     *  service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent
     *  service of the service being suspended (if the current service is an addon service)
     * @return mixed null to maintain the current service meta data, or an array of meta fields
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function suspendService($package, $service, $parent_package = null, $parent_service = null)
    {
        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);

        if (empty($service_fields->solusvm2_server_id)) {
            return null;
        }

        $row = $this->getModuleRow($package->module_row);
        $api = $this->getApi($row->meta->host, $row->meta->api_token);

        $this->log(
            $row->meta->host . '|servers/' . $service_fields->solusvm2_server_id . '/suspend',
            serialize([]),
            'input',
            true
        );
        $this->parseResponse($api->serverAction($service_fields->solusvm2_server_id, 'suspend'), $row);

        return null;
    }

    /**
     * Unsuspends the service on the remote server. Sets Input errors on failure,
     * preventing the service from being unsuspended.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent
     *  service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent
     *  service of the service being unsuspended (if the current service is an addon service)
     * @return mixed null to maintain the current service meta data, or an array of meta fields
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function unsuspendService($package, $service, $parent_package = null, $parent_service = null)
    {
        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);

        if (empty($service_fields->solusvm2_server_id)) {
            return null;
        }

        $row = $this->getModuleRow($package->module_row);
        $api = $this->getApi($row->meta->host, $row->meta->api_token);

        $this->log(
            $row->meta->host . '|servers/' . $service_fields->solusvm2_server_id . '/resume',
            serialize([]),
            'input',
            true
        );
        $this->parseResponse($api->serverAction($service_fields->solusvm2_server_id, 'resume'), $row);

        return null;
    }

    /**
     * Allows the module to perform an action when the service is ready to renew.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent
     *  service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent
     *  service of the service being renewed (if the current service is an addon service)
     * @return mixed null to maintain the current service meta data, or an array of meta fields
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function renewService($package, $service, $parent_package = null, $parent_service = null)
    {
        // Nothing to do
        return null;
    }

    /**
     * Updates the package for the service on the remote server. Sets Input errors on failure,
     * preventing the service's package from being changed.
     *
     * @param stdClass $package_from A stdClass object representing the current package
     * @param stdClass $package_to A stdClass object representing the new package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent
     *  service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent
     *  service of the service being changed (if the current service is an addon service)
     * @return mixed null to maintain the current service meta data, or an array of meta fields
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function changeServicePackage($package_from, $package_to, $service, $parent_package = null, $parent_service = null)
    {
        // Nothing to do if the plan has not changed
        if (($package_from->meta->plan ?? null) == ($package_to->meta->plan ?? null)) {
            return null;
        }

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);

        if (empty($service_fields->solusvm2_server_id)) {
            return null;
        }

        $row = $this->getModuleRow($package_to->module_row);
        $api = $this->getApi($row->meta->host, $row->meta->api_token);

        // Resize the server to the new plan
        $resize = [
            'plan_id' => (int)$package_to->meta->plan,
            'preserve_disk' => true,
            'backup_settings' => $this->getBackupSettings($package_to)
        ];

        if (isset($package_to->meta->snapshot_enabled) && $package_to->meta->snapshot_enabled == '1') {
            $resize['custom_plan'] = ['is_snapshots_enabled' => true];
        }

        $this->log(
            $row->meta->host . '|servers/' . $service_fields->solusvm2_server_id . '/resize',
            serialize($resize),
            'input',
            true
        );
        $this->parseResponse($api->resizeServer($service_fields->solusvm2_server_id, $resize), $row);

        if ($this->Input->errors()) {
            return;
        }

        return [
            [
                'key' => 'solusvm2_plan',
                'value' => $package_to->meta->plan,
                'encrypted' => 0
            ]
        ];
    }

    /**
     * Validates input data when attempting to add a package, returns the meta
     * data to save when adding a package. Performs any action required to add
     * the package on the remote server. Sets Input errors on failure,
     * preventing the package from being added.
     *
     * @param array An array of key/value pairs used to add the package
     * @return array A numerically indexed array of meta fields to be stored for this package containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function addPackage(array $vars = null)
    {
        $this->Input->setRules($this->getPackageRules($vars));

        $meta = [];
        if ($this->Input->validates($vars)) {
            // Return all package meta fields
            foreach ($vars['meta'] as $key => $value) {
                $meta[] = [
                    'key' => $key,
                    'value' => $value,
                    'encrypted' => 0
                ];
            }
        }

        return $meta;
    }

    /**
     * Validates input data when attempting to edit a package, returns the meta
     * data to save when editing a package. Performs any action required to edit
     * the package on the remote server. Sets Input errors on failure,
     * preventing the package from being edited.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array An array of key/value pairs used to edit the package
     * @return array A numerically indexed array of meta fields to be stored for this package containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function editPackage($package, array $vars = null)
    {
        $this->Input->setRules($this->getPackageRules($vars));

        $meta = [];
        if ($this->Input->validates($vars)) {
            // Return all package meta fields
            foreach ($vars['meta'] as $key => $value) {
                $meta[] = [
                    'key' => $key,
                    'value' => $value,
                    'encrypted' => 0
                ];
            }
        }

        return $meta;
    }

    /**
     * Deletes the package on the remote server. Sets Input errors on failure,
     * preventing the package from being deleted.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function deletePackage($package)
    {
        // Nothing to do
        return null;
    }

    /**
     * Returns all fields used when adding/editing a package, including any
     * javascript to execute when the page is rendered with these fields.
     *
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containing the fields to render
     *  as well as any additional HTML markup to include
     */
    public function getPackageFields($vars = null)
    {
        Loader::loadHelpers($this, ['Form', 'Html']);

        // Fetch reference data from the selected server
        $module_row = $this->getModuleRowByServer(
            ($vars->module_row ?? 0),
            ($vars->module_group ?? '')
        );

        $plans = [];
        $locations = [];
        $os_images = [];
        $applications = [];
        $roles = [];
        $limit_groups = [];
        $ssh_keys = [];

        if ($module_row) {
            $plans = $this->getPlans($module_row);
            $locations = $this->getLocations($module_row);
            $os_images = $this->getOsImages($module_row);
            $applications = $this->getApplications($module_row);
            $roles = $this->getRoles($module_row);
            $limit_groups = $this->getLimitGroups($module_row);
            $ssh_keys = $this->getSshKeys($module_row);
        }

        $fields = new ModuleFields();

        // Show/hide the OS and application fields depending on who is allowed to choose them
        $fields->setHtml('
            <script type="text/javascript">
                $(document).ready(function() {
                    toggleSolusvm2Os();
                    toggleSolusvm2Application();

                    $("input[name=\'meta[set_os]\']").change(function() {
                        toggleSolusvm2Os();
                    });

                    $("input[name=\'meta[set_application]\']").change(function() {
                        toggleSolusvm2Application();
                    });

                    function toggleSolusvm2Os() {
                        if ($("input[name=\'meta[set_os]\']:checked").val() == "admin")
                            $("#solusvm2_os").parent("li").show();
                        else
                            $("#solusvm2_os").parent("li").hide();
                    }

                    function toggleSolusvm2Application() {
                        if ($("input[name=\'meta[set_application]\']:checked").val() == "admin")
                            $("#solusvm2_application").parent("li").show();
                        else
                            $("#solusvm2_application").parent("li").hide();
                    }
                });
            </script>
        ');

        // Set the plan as a selectable option
        $plan = $fields->label(Language::_('Solusvm2.package_fields.plan', true), 'solusvm2_plan');
        $plan->attach(
            $fields->fieldSelect(
                'meta[plan]',
                ['' => Language::_('Solusvm2.please_select', true)] + $plans,
                ($vars->meta['plan'] ?? null),
                ['id' => 'solusvm2_plan']
            )
        );
        $plan->attach($fields->tooltip(Language::_('Solusvm2.package_fields.tooltip.plan', true)));
        $fields->setField($plan);
        unset($plan);

        // Set the location as a selectable option
        $location = $fields->label(Language::_('Solusvm2.package_fields.location', true), 'solusvm2_location');
        $location->attach(
            $fields->fieldSelect(
                'meta[location]',
                ['' => Language::_('Solusvm2.please_select_none', true)] + $locations,
                ($vars->meta['location'] ?? null),
                ['id' => 'solusvm2_location']
            )
        );
        $fields->setField($location);
        unset($location);

        // Set field whether the client or the admin may choose the operating system
        $set_os = $fields->label(
            Language::_('Solusvm2.package_fields.set_os', true),
            'solusvm2_client_set_os'
        );
        $admin_set_os = $fields->label(
            Language::_('Solusvm2.package_fields.admin_set_os', true),
            'solusvm2_admin_set_os'
        );
        $client_set_os = $fields->label(
            Language::_('Solusvm2.package_fields.client_set_os', true),
            'solusvm2_client_set_os'
        );
        $set_os->attach(
            $fields->fieldRadio(
                'meta[set_os]',
                'client',
                ($vars->meta['set_os'] ?? 'client') == 'client',
                ['id' => 'solusvm2_client_set_os', 'class' => 'solusvm2_set_os'],
                $client_set_os
            )
        );
        $set_os->attach(
            $fields->fieldRadio(
                'meta[set_os]',
                'admin',
                ($vars->meta['set_os'] ?? null) == 'admin',
                ['id' => 'solusvm2_admin_set_os', 'class' => 'solusvm2_set_os'],
                $admin_set_os
            )
        );
        $fields->setField($set_os);
        unset($set_os, $admin_set_os, $client_set_os);

        // Set operating systems that the admin may choose from
        $os = $fields->label(Language::_('Solusvm2.package_fields.os', true), 'solusvm2_os');
        $os->attach(
            $fields->fieldSelect(
                'meta[os]',
                ['' => Language::_('Solusvm2.please_select', true)] + $os_images,
                ($vars->meta['os'] ?? null),
                ['id' => 'solusvm2_os']
            )
        );
        $fields->setField($os);
        unset($os);

        // Set field whether applications are used and who may choose them
        $set_application_value = ($vars->meta['set_application']
            ?? (!empty($vars->meta['application']) ? 'admin' : 'none'));
        $set_application = $fields->label(
            Language::_('Solusvm2.package_fields.set_application', true),
            'solusvm2_no_set_application'
        );
        $none_set_application = $fields->label(
            Language::_('Solusvm2.package_fields.no_set_application', true),
            'solusvm2_no_set_application'
        );
        $admin_set_application = $fields->label(
            Language::_('Solusvm2.package_fields.admin_set_application', true),
            'solusvm2_admin_set_application'
        );
        $client_set_application = $fields->label(
            Language::_('Solusvm2.package_fields.client_set_application', true),
            'solusvm2_client_set_application'
        );
        $set_application->attach(
            $fields->fieldRadio(
                'meta[set_application]',
                'none',
                $set_application_value == 'none',
                ['id' => 'solusvm2_no_set_application', 'class' => 'solusvm2_set_application'],
                $none_set_application
            )
        );
        $set_application->attach(
            $fields->fieldRadio(
                'meta[set_application]',
                'admin',
                $set_application_value == 'admin',
                ['id' => 'solusvm2_admin_set_application', 'class' => 'solusvm2_set_application'],
                $admin_set_application
            )
        );
        $set_application->attach(
            $fields->fieldRadio(
                'meta[set_application]',
                'client',
                $set_application_value == 'client',
                ['id' => 'solusvm2_client_set_application', 'class' => 'solusvm2_set_application'],
                $client_set_application
            )
        );
        $fields->setField($set_application);
        unset($set_application, $none_set_application, $admin_set_application, $client_set_application);

        // Set applications that may be installed instead of a plain OS
        $application = $fields->label(Language::_('Solusvm2.package_fields.application', true), 'solusvm2_application');
        $application->attach(
            $fields->fieldSelect(
                'meta[application]',
                ['' => Language::_('Solusvm2.please_select_none', true)] + $applications,
                ($vars->meta['application'] ?? null),
                ['id' => 'solusvm2_application']
            )
        );
        $application->attach($fields->tooltip(Language::_('Solusvm2.package_fields.tooltip.application', true)));
        $fields->setField($application);
        unset($application);

        // Set the cloud-init user data
        $user_data = $fields->label(Language::_('Solusvm2.package_fields.user_data', true), 'solusvm2_user_data');
        $user_data->attach(
            $fields->fieldTextarea(
                'meta[user_data]',
                ($vars->meta['user_data'] ?? null),
                ['id' => 'solusvm2_user_data']
            )
        );
        $fields->setField($user_data);
        unset($user_data);

        // Set whether backups are enabled
        $backup_enabled = $fields->label(
            Language::_('Solusvm2.package_fields.backup_enabled', true),
            'solusvm2_backup_enabled'
        );
        $backup_enabled->attach(
            $fields->fieldCheckbox(
                'meta[backup_enabled]',
                '1',
                ($vars->meta['backup_enabled'] ?? '0') == '1',
                ['id' => 'solusvm2_backup_enabled'],
                $fields->label(Language::_('Solusvm2.package_fields.backup_enabled', true), 'solusvm2_backup_enabled')
            )
        );
        $fields->setField($backup_enabled);
        unset($backup_enabled);

        // Set whether snapshots are enabled
        $snapshot_enabled = $fields->label(
            Language::_('Solusvm2.package_fields.snapshot_enabled', true),
            'solusvm2_snapshot_enabled'
        );
        $snapshot_enabled->attach(
            $fields->fieldCheckbox(
                'meta[snapshot_enabled]',
                '1',
                ($vars->meta['snapshot_enabled'] ?? '0') == '1',
                ['id' => 'solusvm2_snapshot_enabled'],
                $fields->label(
                    Language::_('Solusvm2.package_fields.snapshot_enabled', true),
                    'solusvm2_snapshot_enabled'
                )
            )
        );
        $fields->setField($snapshot_enabled);
        unset($snapshot_enabled);

        // Set the role of created users
        $role = $fields->label(Language::_('Solusvm2.package_fields.role', true), 'solusvm2_role');
        $role->attach(
            $fields->fieldSelect(
                'meta[role]',
                ['' => Language::_('Solusvm2.please_select_none', true)] + $roles,
                ($vars->meta['role'] ?? null),
                ['id' => 'solusvm2_role']
            )
        );
        $fields->setField($role);
        unset($role);

        // Set the limit group of created users
        $limit_group = $fields->label(Language::_('Solusvm2.package_fields.limit_group', true), 'solusvm2_limit_group');
        $limit_group->attach(
            $fields->fieldSelect(
                'meta[limit_group]',
                ['' => Language::_('Solusvm2.please_select_none', true)] + $limit_groups,
                ($vars->meta['limit_group'] ?? null),
                ['id' => 'solusvm2_limit_group']
            )
        );
        $fields->setField($limit_group);
        unset($limit_group);

        // Set the SSH key to deploy on created servers
        $ssh_key = $fields->label(Language::_('Solusvm2.package_fields.ssh_key', true), 'solusvm2_ssh_key');
        $ssh_key->attach(
            $fields->fieldSelect(
                'meta[ssh_key]',
                ['' => Language::_('Solusvm2.please_select_none', true)] + $ssh_keys,
                ($vars->meta['ssh_key'] ?? null),
                ['id' => 'solusvm2_ssh_key']
            )
        );
        $fields->setField($ssh_key);
        unset($ssh_key);

        return $fields;
    }

    /**
     * Returns the rendered view of the manage module page
     *
     * @param mixed $module A stdClass object representing the module and its rows
     * @param array $vars An array of post data submitted to or on the manage
     *  module page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the manager module page
     */
    public function manageModule($module, array &$vars)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('manage', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'solusvm2' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        $this->view->set('module', $module);

        return $this->view->fetch();
    }

    /**
     * Returns the rendered view of the add module row page
     *
     * @param array $vars An array of post data submitted to or on the add
     *  module row page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the add module row page
     */
    public function manageAddRow(array &$vars)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('add_row', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'solusvm2' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        // Fetch module
        Loader::loadModels($this, ['ModuleManager']);
        $module = $this->ModuleManager->getByClass(
            \Illuminate\Support\Str::snake(get_class($this)),
            Configure::get('Blesta.company_id')
        );
        $module = ($module[0] ?? []);
        $this->view->set('module', (object)$module);
        $this->view->set('vars', (object)$vars);

        return $this->view->fetch();
    }

    /**
     * Returns the rendered view of the edit module row page
     *
     * @param stdClass $module_row The stdClass representation of the existing module row
     * @param array $vars An array of post data submitted to or on the edit
     *  module row page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the edit module row page
     */
    public function manageEditRow($module_row, array &$vars)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('edit_row', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'solusvm2' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        if (empty($vars)) {
            $vars = $module_row->meta;
        }

        // Fetch module
        Loader::loadModels($this, ['ModuleManager']);
        $module = $this->ModuleManager->getByClass(
            \Illuminate\Support\Str::snake(get_class($this)),
            Configure::get('Blesta.company_id')
        );
        $module = ($module[0] ?? []);
        $this->view->set('module', (object)$module);
        $this->view->set('vars', (object)$vars);

        return $this->view->fetch();
    }

    /**
     * Adds the module row on the remote server. Sets Input errors on failure,
     * preventing the row from being added.
     *
     * @param array $vars An array of module info to add
     * @return array A numerically indexed array of meta fields for the module row containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     */
    public function addModuleRow(array &$vars)
    {
        $meta_fields = ['server_name', 'host', 'api_token'];
        $encrypted_fields = ['api_token'];

        $this->Input->setRules($this->getRowRules($vars));

        // Validate module row
        if ($this->Input->validates($vars)) {
            $vars['host'] = strtolower(preg_replace('#^https?://#i', '', rtrim(trim($vars['host']), '/')));

            // Build the meta data for this row
            $meta = [];
            foreach ($vars as $key => $value) {
                if (in_array($key, $meta_fields)) {
                    $meta[] = [
                        'key' => $key,
                        'value' => $value,
                        'encrypted' => in_array($key, $encrypted_fields) ? 1 : 0
                    ];
                }
            }

            return $meta;
        }
    }

    /**
     * Edits the module row on the remote server. Sets Input errors on failure,
     * preventing the row from being updated.
     *
     * @param stdClass $module_row The stdClass representation of the existing module row
     * @param array $vars An array of module info to update
     * @return array A numerically indexed array of meta fields for the module row containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     */
    public function editModuleRow($module_row, array &$vars)
    {
        // Same as adding
        return $this->addModuleRow($vars);
    }

    /**
     * Deletes the module row on the remote server. Sets Input errors on failure,
     * preventing the row from being deleted.
     *
     * @param stdClass $module_row The stdClass representation of the existing module row
     */
    public function deleteModuleRow($module_row)
    {
        // Nothing to do
        return null;
    }

    /**
     * Returns an array of available service delegation order methods. The module
     * will determine how each method is defined.
     *
     * @return array An array of order methods in key/value pairs where the
     *  key is the type to be stored for the group and value is the name for that option
     * @see Module::selectModuleRow()
     */
    public function getGroupOrderOptions()
    {
        return ['first' => Language::_('Solusvm2.order_options.first', true)];
    }

    /**
     * Determines which module row should be attempted when a service is provisioned
     * for the given group based upon the order method set for that group.
     *
     * @return int The module row ID to attempt to add the service with
     * @see Module::getGroupOrderOptions()
     */
    public function selectModuleRow($module_group_id)
    {
        if (!isset($this->ModuleManager)) {
            Loader::loadModels($this, ['ModuleManager']);
        }

        $group = $this->ModuleManager->getGroup($module_group_id);

        if ($group) {
            switch ($group->add_order) {
                default:
                case 'first':
                    foreach ($group->rows as $row) {
                        return $row->id;
                    }

                    break;
            }
        }
        return 0;
    }

    /**
     * Returns all fields to display to an admin attempting to add a service with the module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containing the fields to render as well
     *  as any additional HTML markup to include
     */
    public function getAdminAddFields($package, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        $fields = new ModuleFields();

        // Create the hostname field
        $host_name = $fields->label(Language::_('Solusvm2.service_fields.hostname', true), 'solusvm2_hostname');
        $host_name->attach(
            $fields->fieldText(
                'solusvm2_hostname',
                ($vars->solusvm2_hostname ?? null),
                ['id' => 'solusvm2_hostname']
            )
        );
        $host_name->attach($fields->tooltip(Language::_('Solusvm2.service_fields.tooltip.hostname', true)));
        $fields->setField($host_name);

        // Create the OS/application fields (only those the client may choose)
        $this->addOsApplicationFields($fields, $package, $vars);

        // Create the server id field
        $server_id = $fields->label(Language::_('Solusvm2.service_fields.server_id', true), 'solusvm2_server_id');
        $server_id->attach(
            $fields->fieldText(
                'solusvm2_server_id',
                ($vars->solusvm2_server_id ?? null),
                ['id' => 'solusvm2_server_id']
            )
        );
        $server_id->attach($fields->tooltip(Language::_('Solusvm2.service_fields.tooltip.server_id', true)));
        $fields->setField($server_id);

        return $fields;
    }

    /**
     * Returns all fields to display to a client attempting to add a service with the module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containing the fields to render as well
     *  as any additional HTML markup to include
     */
    public function getClientAddFields($package, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        $fields = new ModuleFields();

        // Create the hostname field
        $host_name = $fields->label(Language::_('Solusvm2.service_fields.hostname', true), 'solusvm2_hostname');
        $host_name->attach(
            $fields->fieldText(
                'solusvm2_hostname',
                ($vars->solusvm2_hostname ?? $vars->domain ?? $this->generateHostname()),
                ['id' => 'solusvm2_hostname']
            )
        );
        $host_name->attach($fields->tooltip(Language::_('Solusvm2.service_fields.tooltip.hostname', true)));
        $fields->setField($host_name);

        // Create the OS/application fields (only those the client may choose)
        $this->addOsApplicationFields($fields, $package, $vars);

        return $fields;
    }

    /**
     * Returns all fields to display to an admin attempting to edit a service with the module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containing the fields to render as well
     *  as any additional HTML markup to include
     */
    public function getAdminEditFields($package, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        $fields = new ModuleFields();

        // Create the server id field
        $server_id = $fields->label(Language::_('Solusvm2.service_fields.server_id', true), 'solusvm2_server_id');
        $server_id->attach(
            $fields->fieldText(
                'solusvm2_server_id',
                ($vars->solusvm2_server_id ?? null),
                ['id' => 'solusvm2_server_id']
            )
        );
        $server_id->attach($fields->tooltip(Language::_('Solusvm2.service_fields.tooltip.server_id', true)));
        $fields->setField($server_id);

        return $fields;
    }

    /**
     * Adds the OS and/or application select fields to the given fields object,
     * including a tab switcher when both are selectable
     *
     * @param ModuleFields $fields The fields object to add the fields to
     * @param stdClass $package A stdClass object representing the selected package
     * @param stdClass $vars A stdClass object representing a set of post fields
     */
    private function addOsApplicationFields($fields, $package, $vars)
    {
        $set_os = ($package->meta->set_os ?? 'client');
        $set_application = ($package->meta->set_application
            ?? (!empty($package->meta->application) ? 'admin' : 'none'));

        if ($set_os != 'client' && $set_application != 'client') {
            return;
        }

        $module_row = $this->getModuleRowByServer(
            ($package->module_row ?? 0),
            ($package->module_group ?? '')
        );

        // Create the OS field (only if the client may choose the OS)
        if ($set_os == 'client') {
            $os = $fields->label(Language::_('Solusvm2.service_fields.os', true), 'solusvm2_os');
            $os->attach(
                $fields->fieldSelect(
                    'solusvm2_os',
                    $this->getOsImages($module_row),
                    ($vars->solusvm2_os ?? null),
                    ['id' => 'solusvm2_os']
                )
            );
            $fields->setField($os);
        }

        // Create the application field (only if the client may choose the application)
        if ($set_application == 'client') {
            $application = $fields->label(
                Language::_('Solusvm2.service_fields.application', true),
                'solusvm2_application'
            );
            $application->attach(
                $fields->fieldSelect(
                    'solusvm2_application',
                    ['' => Language::_('Solusvm2.please_select', true)] + $this->getApplications($module_row),
                    ($vars->solusvm2_application ?? null),
                    ['id' => 'solusvm2_application']
                )
            );
            $fields->setField($application);
        }

        // Add a tab switcher between the OS and the application when both are selectable
        if ($set_os == 'client' && $set_application == 'client') {
            $fields->setHtml('
                <script type="text/javascript">
                    $(document).ready(function() {
                        var osField = $("#solusvm2_os").closest("li, .form-group");
                        var appField = $("#solusvm2_application").closest("li, .form-group");
                        if (!osField.length || !appField.length)
                            return;

                        var tabs = $("<ul class=\"nav nav-tabs\" style=\"margin-bottom:15px;\">"
                            + "<li class=\"active\"><a href=\"#\" data-solusvm2-tab=\"os\">'
                            . Language::_('Solusvm2.service_fields.os', true) . '</a></li>"
                            + "<li><a href=\"#\" data-solusvm2-tab=\"application\">'
                            . Language::_('Solusvm2.service_fields.application', true) . '</a></li>"
                            + "</ul>");
                        osField.before(tabs);

                        function switchSolusvm2Tab(name) {
                            tabs.find("li").removeClass("active");
                            tabs.find("a[data-solusvm2-tab=\'" + name + "\']").parent().addClass("active");

                            $("#solusvm2_os").prop("disabled", name != "os");
                            $("#solusvm2_application").prop("disabled", name != "application");
                            osField.toggle(name == "os");
                            appField.toggle(name == "application");
                        }

                        tabs.find("a").on("click", function(e) {
                            e.preventDefault();
                            switchSolusvm2Tab($(this).data("solusvm2-tab"));
                        });

                        switchSolusvm2Tab(' . (empty($vars->solusvm2_application) ? '"os"' : '"application"') . ');
                    });
                </script>
            ');
        }
    }

    /**
     * Returns the HTML to display for the admin service info page
     *
     * @param stdClass $service A stdClass object representing the service
     * @param stdClass $package A stdClass object representing the service's package
     * @return string HTML content containing information to display when viewing the service info page
     */
    public function getAdminServiceInfo($service, $package)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('admin_service_info', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'solusvm2' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Html']);

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $module_row = $this->getModuleRow(($package->module_row ?? '0'));
        $server = $this->getServerInfo(($service_fields->solusvm2_server_id ?? null), $module_row);

        $this->view->set('module_row', $module_row);
        $this->view->set('package', $package);
        $this->view->set('service', $service);
        $this->view->set('service_fields', $service_fields);
        $this->view->set('stats', $this->getServerStats($service_fields, $module_row, $server));

        return $this->view->fetch() . $this->getServiceActionsView($service, $package, $server, false);
    }

    /**
     * Returns the HTML to display for the client service info page
     *
     * @param stdClass $service A stdClass object representing the service
     * @param stdClass $package A stdClass object representing the service's package
     * @return string HTML content containing information to display when viewing the service info page
     */
    public function getClientServiceInfo($service, $package)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('client_service_info', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'solusvm2' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Html']);

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $module_row = $this->getModuleRow(($package->module_row ?? '0'));
        $server = $this->getServerInfo(($service_fields->solusvm2_server_id ?? null), $module_row);

        $this->view->set('module_row', $module_row);
        $this->view->set('package', $package);
        $this->view->set('service', $service);
        $this->view->set('service_fields', $service_fields);
        $this->view->set('stats', $this->getServerStats($service_fields, $module_row, $server));

        return $this->view->fetch();
    }

    /**
     * Returns HTML content to display in the default service management view
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param stdClass $service A stdClass object representing the current service
     * @return string HTML content to display on the service management page
     */
    public function getClientManagementContent($package, $service)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('client_service_info', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'solusvm2' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Html']);

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $module_row = $this->getModuleRow(($package->module_row ?? '0'));
        $server = $this->getServerInfo(($service_fields->solusvm2_server_id ?? null), $module_row);

        $this->view->set('module_row', $module_row);
        $this->view->set('package', $package);
        $this->view->set('service', $service);
        $this->view->set('service_fields', $service_fields);
        $this->view->set('stats', $this->getServerStats($service_fields, $module_row, $server));

        return $this->view->fetch() . $this->getServiceActionsView($service, $package, $server, true);
    }

    /**
     * Returns all tabs to display to an admin when managing a service whose
     * package uses this module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @return array An array of tabs in the format of method => title.
     *  Example: array('methodName' => "Title", 'methodName2' => "Title2")
     */
    public function getAdminTabs($package)
    {
        return [
            'tabBoot' => Language::_('Solusvm2.tab_boot', true),
            'tabReinstall' => Language::_('Solusvm2.tab_reinstall', true),
            'tabConsole' => Language::_('Solusvm2.tab_console', true)
        ];
    }

    /**
     * Returns all tabs to display to a client when managing a service whose
     * package uses this module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @return array An array of tabs in the format of method => array('name' => Title, 'icon' => Icon).
     */
    public function getClientTabs($package)
    {
        return [
            'tabClientBoot' => [
                'name' => Language::_('Solusvm2.tab_client_boot', true),
                'icon' => 'fas fa-life-ring'
            ],
            'tabClientReinstall' => [
                'name' => Language::_('Solusvm2.tab_client_reinstall', true),
                'icon' => 'fas fa-sync-alt'
            ],
            'tabClientConsole' => [
                'name' => Language::_('Solusvm2.tab_client_console', true),
                'icon' => 'fas fa-terminal'
            ]
        ];
    }

    /**
     * Renders the service actions box for the client or admin service info page
     *
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $package A stdClass object representing the current package
     * @param bool $client True to render the client view, false for the admin view
     * @return string The rendered HTML for the actions box
     */
    private function getServiceActionsView($service, $package, $server = null, $client = true)
    {
        $view_name = $client ? 'client_service_actions' : 'admin_service_actions';

        // Preserve the caller's view so we can safely reuse $this->view for helpers
        $previous_view = $this->view ?? null;

        $this->view = new View($view_name, 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'solusvm2' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        // Get the service fields and server status
        $service_fields = $this->serviceFieldsToObject($service->fields);
        if (!$server) {
            $module_row = $this->getModuleRow(($package->module_row ?? '0'));
            $server = $this->getServerInfo(($service_fields->solusvm2_server_id ?? null), $module_row);
        }

        $this->view->set('status', ($server->status ?? null));
        $this->view->set('service_fields', $service_fields);
        $this->view->set('service_id', $service->id);
        $this->view->set('client_id', $service->client_id);
        $this->view->set('vars', (object)[
            'hostname' => ($service_fields->solusvm2_hostname ?? null)
        ]);

        $output = $this->view->fetch();
        $this->view = $previous_view;

        return $output;
    }

    /**
     * Redirects to the main service page after an action is performed
     *
     * @param stdClass $service A stdClass object representing the current service
     * @param bool $client True if redirecting in the client interface, false otherwise
     */
    private function redirectToService($service, $client = true)
    {
        if ($client) {
            $url = $this->base_uri . 'services/manage/' . ($service->id ?? '') . '/';
        } else {
            $url = $this->base_uri .
                'clients/servicetab/' .
                ($service->client_id ?? '') .
                '/' .
                ($service->id ?? '') .
                '/';
        }

        header('Location: ' . $url);
        exit;
    }

    /**
     * Determines whether the request should be redirected back to the service page
     *
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param bool $client True if the request came from the client interface, false otherwise
     * @return bool True if the request should be redirected, false otherwise
     */
    private function shouldRedirectAfterAction(array $get = null, array $post = null, $client = true)
    {
        $get_key = $client ? '2' : '3';
        if (!array_key_exists($get_key, (array)$get)) {
            return false;
        }

        $action = $get[$get_key];

        // Power actions always redirect after being handled
        if (in_array($action, ['boot', 'reboot', 'shutdown', 'poweroff'])) {
            return true;
        }

        // Form submissions only redirect when there were no validation errors
        if (in_array($action, ['hostname', 'password', 'reinstall']) && !empty($post) && !$this->Input->errors()) {
            return true;
        }

        return false;
    }

    /**
     * Actions tab (boot, reboot, shutdown, etc.)
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabActions($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $this->view = new View('tab_actions', 'default');
        $this->view->base_uri = $this->base_uri;

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $module_row = $this->getModuleRow($package->module_row);

        // Get the available operating systems and applications
        $os_images = $this->getOsImages($module_row);
        $applications = $this->getApplications($module_row);

        // Perform the actions
        $vars = $this->actionsTab($package, $service, $os_images, $applications, false, $get, $post);

        // Redirect power actions and successful form submissions back to the service page
        if ($this->shouldRedirectAfterAction($get, $post, false)) {
            $this->redirectToService($service, false);
        }

        // Set default vars
        if (empty($vars)) {
            $vars = [
                'os' => ($service_fields->solusvm2_os ?? null),
                'application' => ($service_fields->solusvm2_application ?? null),
                'hostname' => ($service_fields->solusvm2_hostname ?? null)
            ];
        }

        // Fetch the server status
        $this->view->set('server', $this->getServerInfo(($service_fields->solusvm2_server_id ?? null), $module_row));
        $this->view->set('os_images', $os_images);
        $this->view->set('applications', $applications);
        $this->view->set('service_fields', $service_fields);
        $this->view->set('vars', (object)$vars);
        $this->view->set('client_id', $service->client_id);
        $this->view->set('service_id', $service->id);

        $this->view->set('view', $this->view->view);
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'solusvm2' . DS);
        return $this->view->fetch();
    }

    /**
     * Client Actions tab (boot, reboot, shutdown, etc.)
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientActions($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $this->view = new View('tab_client_actions', 'default');
        $this->view->base_uri = $this->base_uri;

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $module_row = $this->getModuleRow($package->module_row);

        // Get the available operating systems and applications
        $os_images = $this->getOsImages($module_row);
        $applications = $this->getApplications($module_row);

        // Perform the actions
        $vars = $this->actionsTab($package, $service, $os_images, $applications, true, $get, $post);

        // Redirect power actions and successful form submissions back to the service page
        if ($this->shouldRedirectAfterAction($get, $post, true)) {
            $this->redirectToService($service, true);
        }

        // Set default vars
        if (empty($vars)) {
            $vars = [
                'os' => ($service_fields->solusvm2_os ?? null),
                'application' => ($service_fields->solusvm2_application ?? null),
                'hostname' => ($service_fields->solusvm2_hostname ?? null)
            ];
        }

        // Fetch the server status
        $this->view->set('server', $this->getServerInfo(($service_fields->solusvm2_server_id ?? null), $module_row));
        $this->view->set('os_images', $os_images);
        $this->view->set('applications', $applications);
        $this->view->set('service_fields', $service_fields);
        $this->view->set('vars', (object)$vars);
        $this->view->set('client_id', $service->client_id);
        $this->view->set('service_id', $service->id);

        $this->view->set('view', $this->view->view);
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'solusvm2' . DS);
        return $this->view->fetch();
    }

    /**
     * Handles data for the actions tab in the client and admin interfaces
     * @see Solusvm2::tabActions() and Solusvm2::tabClientActions()
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $os_images An array of operating systems (version ID => name)
     * @param array $applications An array of applications (ID => name)
     * @param bool $client True if the action is being performed by the client, false otherwise
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @return array An array of vars for the template
     */
    private function actionsTab($package, $service, $os_images, $applications, $client = false, array $get = null, array $post = null)
    {
        $vars = [];

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $module_row = $this->getModuleRow($package->module_row);

        $get_key = '3';
        if ($client) {
            $get_key = '2';
        }

        // Perform actions
        if (array_key_exists($get_key, (array)$get)) {
            switch ($get[$get_key]) {
                case 'boot':
                case 'reboot':
                case 'shutdown':
                case 'poweroff':
                    if (
                        !$this->performAction(
                            $get[$get_key],
                            ($service_fields->solusvm2_server_id ?? null),
                            $module_row
                        )
                    ) {
                        $this->Input->setErrors(
                            ['api' => ['internal' => Language::_('Solusvm2.!error.api.internal', true)]]
                        );
                    } else {
                        $this->setMessage('success', Language::_('Solusvm2.!success.' . $get[$get_key], true));
                    }
                    break;
                case 'hostname':
                    // Show the hostname section
                    $this->view->set('hostname', true);

                    if (!empty($post)) {
                        $rules = [
                            'hostname' => [
                                'format' => [
                                    'pre_format' => [[$this, 'replaceText'], '', '/^\s*www\./i'],
                                    'rule' => [[$this, 'validateHostName'], true],
                                    'message' => Language::_('Solusvm2.!error.solusvm2_hostname.format', true)
                                ]
                            ]
                        ];

                        // Validate the hostname and update it
                        $this->Input->setRules($rules);
                        if ($this->Input->validates($post)) {
                            // Update the service hostname
                            Loader::loadModels($this, ['Services']);
                            $this->Services->edit(
                                $service->id,
                                ['solusvm2_hostname' => strtolower($post['hostname'])]
                            );

                            if (($errors = $this->Services->errors())) {
                                $this->Input->setErrors($errors);
                            } else {
                                $this->setMessage('success', Language::_('Solusvm2.!success.hostname', true));
                            }

                            // Do not show the hostname section again
                            $this->view->set('hostname', false);
                        }

                        $vars = $post;
                    }
                    break;
                case 'password':
                    // Show the root password section
                    $this->view->set('password', true);

                    if (!empty($post)) {
                        // Reset the root password on the server
                        Loader::loadModels($this, ['Services']);
                        $this->Services->edit($service->id, ['solusvm2_reset_password' => 1]);

                        if (($errors = $this->Services->errors())) {
                            $this->Input->setErrors($errors);
                        } else {
                            $this->setMessage('success', Language::_('Solusvm2.!success.password', true));
                        }

                        // Do not show the password section again
                        $this->view->set('password', false);
                    }
                    break;
                case 'reinstall':
                    // Show the reinstall section
                    $this->view->set('reinstall', true);

                    if (!empty($post)) {
                        $rules = [
                            'confirm' => [
                                'valid' => [
                                    'rule' => ['compares', '==', '1'],
                                    'message' => Language::_('Solusvm2.!error.solusvm2_confirm.valid', true)
                                ]
                            ]
                        ];

                        // An application is reinstalled instead of the OS when selected
                        if (!empty($post['application'])) {
                            $rules['application'] = [
                                'valid' => [
                                    'rule' => ['array_key_exists', $applications],
                                    'message' => Language::_('Solusvm2.!error.solusvm2_application.valid', true)
                                ]
                            ];
                        } else {
                            $rules['os'] = [
                                'valid' => [
                                    'rule' => ['array_key_exists', $os_images],
                                    'message' => Language::_('Solusvm2.!error.solusvm2_os.valid', true)
                                ]
                            ];
                        }

                        // Validate the OS/application and perform the reinstallation
                        $this->Input->setRules($rules);
                        if ($this->Input->validates($post)) {
                            // Update the service OS/application
                            Loader::loadModels($this, ['Services']);
                            $this->Services->edit(
                                $service->id,
                                [
                                    'solusvm2_os' => ($post['os'] ?? ''),
                                    'solusvm2_application' => ($post['application'] ?? ''),
                                    'confirm_reinstall' => true
                                ]
                            );

                            if (($errors = $this->Services->errors())) {
                                $this->Input->setErrors($errors);
                            } else {
                                $this->setMessage('success', Language::_('Solusvm2.!success.reinstall', true));
                            }

                            // Do not show the reinstall section again
                            $this->view->set('reinstall', false);
                        }

                        $vars = $post;
                    }
                    break;
                default:
                    break;
            }
        }

        return $vars;
    }

    /**
     * Performs a power action on the virtual server.
     *
     * @param string $action The action to perform (i.e. "boot", "reboot", "shutdown", "poweroff")
     * @param int $server_id The virtual server ID
     * @param stdClass $module_row An stdClass object representing a single server
     * @return bool True if the action was performed successfully, false otherwise
     */
    private function performAction($action, $server_id, $module_row)
    {
        if (empty($server_id) || !$module_row) {
            return false;
        }

        // Map the UI action to the API endpoint
        $actions = [
            'boot' => ['start', []],
            'shutdown' => ['stop', []],
            'poweroff' => ['stop', ['force' => true]],
            'reboot' => ['restart', []]
        ];

        if (!isset($actions[$action])) {
            return false;
        }

        list($endpoint, $params) = $actions[$action];

        $api = $this->getApi($module_row->meta->host, $module_row->meta->api_token);

        try {
            $this->log(
                $module_row->meta->host . '|servers/' . (int)$server_id . '/' . $endpoint,
                serialize($params),
                'input',
                true
            );
            $response = $this->parseResponse($api->serverAction($server_id, $endpoint, $params), $module_row);

            return $response !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Builds a normalized statistics object for a virtual server.
     *
     * @param stdClass $service_fields The service fields as an object
     * @param stdClass $module_row An stdClass object representing the module row
     * @param mixed $server An optional pre-fetched server info object
     * @return stdClass The normalized server statistics
     */
    private function getServerStats($service_fields, $module_row, $server = null)
    {
        if (!$server) {
            $server = $this->getServerInfo(($service_fields->solusvm2_server_id ?? null), $module_row);
        }

        $stats = new stdClass();
        $stats->status = ($server->status ?? null);
        $stats->is_suspended = !empty($server->is_suspended);
        $stats->is_processing = !empty($server->is_processing);

        $stats->ips = [];
        foreach ((array)($server->ip_addresses->ipv4 ?? []) as $ip) {
            if (!empty($ip->ip)) {
                $stats->ips[] = $ip->ip;
            }
        }
        foreach ((array)($server->ip_addresses->ipv6 ?? []) as $ip) {
            $ipv6 = ($ip->ip ?? ($ip->primary_ip ?? null));
            if (!empty($ipv6)) {
                $stats->ips[] = $ipv6;
            }
        }

        // Plan specification
        $stats->plan = ($server->plan->name ?? null);
        $stats->vcpu = ($server->plan->params->vcpu ?? null);
        $stats->memory = isset($server->plan->params->ram)
            ? $this->convertBytesToString($server->plan->params->ram)
            : null;
        $stats->disk = ($server->plan->params->disk ?? null);

        // Traffic usage
        $usage_in = ($server->usage->network->incoming->value ?? null);
        $usage_out = ($server->usage->network->outgoing->value ?? null);
        $stats->traffic_in = ($usage_in !== null ? $this->convertBytesToString($usage_in) : null);
        $stats->traffic_out = ($usage_out !== null ? $this->convertBytesToString($usage_out) : null);
        $stats->traffic_total = ($usage_in !== null || $usage_out !== null
            ? $this->convertBytesToString((int)$usage_in + (int)$usage_out)
            : null);

        $traffic_limit = ($server->plan->limits->network_total_traffic ?? null);
        $stats->traffic_limit = ($traffic_limit && !empty($traffic_limit->is_enabled))
            ? $traffic_limit->limit . ' ' . ($traffic_limit->unit ?? 'GB')
            : null;

        return $stats;
    }

    /**
     * Console tab (VNC)
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabConsole($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $view = $this->consoleTab($package, $service);
        return $view->fetch();
    }

    /**
     * Client Console tab (VNC)
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientConsole($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $view = $this->consoleTab($package, $service, true);
        return $view->fetch();
    }

    /**
     * Boot & Rescue tab (boot mode and root password reset)
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabBoot($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $view = $this->bootTab($package, $service, false, $post);
        return $view->fetch();
    }

    /**
     * Client Boot & Rescue tab (boot mode and root password reset)
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientBoot($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $view = $this->bootTab($package, $service, true, $post);
        return $view->fetch();
    }

    /**
     * Builds the data for the admin/client Boot & Rescue tabs
     * @see Solusvm2::tabBoot() and Solusvm2::tabClientBoot()
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param bool $client True if the tab is rendered for the client area, false otherwise
     * @param array $post Any POST parameters
     * @return View A template view to be rendered
     */
    private function bootTab($package, $service, $client = false, array $post = null)
    {
        $template = ($client ? 'tab_client_boot' : 'tab_boot');

        $this->view = new View($template, 'default');

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $module_row = $this->getModuleRow($package->module_row);

        $server_id = ($service_fields->solusvm2_server_id ?? null);

        $boot = new stdClass();
        $boot->mode = 'disk';
        $boot->iso_image_id = null;
        $iso_images = [];
        $new_password = null;

        if ($server_id && $module_row) {
            $api = $this->getApi($module_row->meta->host, $module_row->meta->api_token);

            // Fetch the current boot settings
            $this->log($module_row->meta->host . '|servers/' . (int)$server_id . '/boot', serialize([]), 'input', true);
            $boot_response = $this->parseResponse($api->getServerBoot($server_id), $module_row);
            if (!$this->Input->errors() && !empty($boot_response['data'])) {
                $boot->mode = ($boot_response['data']['mode'] ?? 'disk');
                $boot->iso_image_id = ($boot_response['data']['iso_image_id'] ?? null);
            }

            // Fetch the available ISO images
            $iso_images = $this->getIsoImages($module_row);

            // Apply boot mode change
            if (!empty($post['boot_mode'])) {
                $data = ['mode' => $post['boot_mode']];
                if ($post['boot_mode'] == 'iso' && !empty($post['iso_image_id'])) {
                    $data['iso_image_id'] = (int)$post['iso_image_id'];
                }

                $this->log(
                    $module_row->meta->host . '|servers/' . (int)$server_id . '/boot',
                    serialize($data),
                    'input',
                    true
                );
                $this->parseResponse($api->setServerBoot($server_id, $data), $module_row);

                if (!$this->Input->errors()) {
                    $this->setMessage('success', Language::_('Solusvm2.!success.boot_mode', true));
                    $boot->mode = $data['mode'];
                    $boot->iso_image_id = ($data['iso_image_id'] ?? null);
                }
            }

            // Reset the root password
            if (!$this->Input->errors() && !empty($post['reset_password'])) {
                Loader::loadModels($this, ['Services']);
                $this->Services->edit($service->id, ['solusvm2_reset_password' => 1]);

                if (($errors = $this->Services->errors())) {
                    $this->Input->setErrors($errors);
                } else {
                    $this->setMessage('success', Language::_('Solusvm2.!success.password', true));
                }
            }
        }

        $this->view->set('boot', $boot);
        $this->view->set('iso_images', $iso_images);
        $this->view->set('service_fields', $service_fields);
        $this->view->set('client_id', $service->client_id);
        $this->view->set('service_id', $service->id);

        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'solusvm2' . DS);
        return $this->view;
    }

    /**
     * Reinstall tab (OS/application selection, password reset, SSH keys, user data)
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabReinstall($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $view = $this->reinstallTab($package, $service, false, $post);
        return $view->fetch();
    }

    /**
     * Client Reinstall tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientReinstall($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $view = $this->reinstallTab($package, $service, true, $post);
        return $view->fetch();
    }

    /**
     * Builds the data for the admin/client Reinstall tabs
     * @see Solusvm2::tabReinstall() and Solusvm2::tabClientReinstall()
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param bool $client True if the tab is rendered for the client area, false otherwise
     * @param array $post Any POST parameters
     * @return View A template view to be rendered
     */
    private function reinstallTab($package, $service, $client = false, array $post = null)
    {
        $template = ($client ? 'tab_client_reinstall' : 'tab_reinstall');

        $this->view = new View($template, 'default');

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $module_row = $this->getModuleRow($package->module_row);

        $server_id = ($service_fields->solusvm2_server_id ?? null);
        $os_images = [];
        $applications = [];
        $ssh_keys = [];
        $vars = [];

        if ($module_row) {
            $os_images = $this->getOsImages($module_row);
            $applications = $this->getApplications($module_row);

            if ($server_id) {
                $api = $this->getApi($module_row->meta->host, $module_row->meta->api_token);

                // Fetch SSH keys
                $this->log($module_row->meta->host . '|ssh_keys', serialize([]), 'input', true);
                $ssh_response = $this->parseResponse($api->listSshKeys(), $module_row);
                if (!$this->Input->errors() && !empty($ssh_response['data'])) {
                    foreach ($ssh_response['data'] as $key) {
                        $ssh_keys[$key['id']] = ($key['name'] ?? $key['public_key']);
                    }
                }
            }
        }

        // Add a new SSH key
        if (!empty($post['action']) && $post['action'] == 'add_ssh_key' && $server_id && $module_row) {
            $rules = [
                'ssh_key_name' => [
                    'empty' => [
                        'rule' => ['isEmpty'],
                        'negate' => true,
                        'message' => Language::_('Solusvm2.!error.ssh_key_name.empty', true)
                    ]
                ],
                'ssh_key_public_key' => [
                    'empty' => [
                        'rule' => ['isEmpty'],
                        'negate' => true,
                        'message' => Language::_('Solusvm2.!error.ssh_key_public_key.empty', true)
                    ],
                    'format' => [
                        'rule' => [[$this, 'validateSshPublicKey']],
                        'message' => Language::_('Solusvm2.!error.ssh_key_public_key.format', true)
                    ]
                ]
            ];

            $this->Input->setRules($rules);
            if ($this->Input->validates($post)) {
                $api = $this->getApi($module_row->meta->host, $module_row->meta->api_token);
                $this->log(
                    $module_row->meta->host . '|ssh_keys (create)',
                    serialize(['name' => $post['ssh_key_name']]),
                    'input',
                    true
                );
                $this->parseResponse(
                    $api->createSshKey([
                        'name' => $post['ssh_key_name'],
                        'public_key' => $post['ssh_key_public_key']
                    ]),
                    $module_row
                );

                if (!$this->Input->errors()) {
                    $this->setMessage('success', Language::_('Solusvm2.!success.ssh_key_added', true));
                    $vars = [];
                } else {
                    $vars = $post;
                }
            } else {
                $vars = $post;
            }
        }

        // Perform reinstall
        if (!empty($post['action']) && $post['action'] == 'reinstall' && $server_id && $module_row) {
            $rules = [
                'confirm' => [
                    'valid' => [
                        'rule' => ['compares', '==', '1'],
                        'message' => Language::_('Solusvm2.!error.solusvm2_confirm.valid', true)
                    ]
                ]
            ];

            if (!empty($post['server_type']) && $post['server_type'] == 'application') {
                $rules['application'] = [
                    'valid' => [
                        'rule' => ['array_key_exists', $applications],
                        'message' => Language::_('Solusvm2.!error.solusvm2_application.valid', true)
                    ]
                ];
            } else {
                $rules['os'] = [
                    'valid' => [
                        'rule' => ['array_key_exists', $os_images],
                        'message' => Language::_('Solusvm2.!error.solusvm2_os.valid', true)
                    ]
                ];
            }

            $this->Input->setRules($rules);
            if ($this->Input->validates($post)) {
                $data = [
                    'use_module' => 'true',
                    'confirm_reinstall' => true,
                    'solusvm2_os' => (!empty($post['server_type']) && $post['server_type'] == 'application' ? '' : ($post['os'] ?? '')),
                    'solusvm2_application' => (!empty($post['server_type']) && $post['server_type'] == 'application' ? ($post['application'] ?? '') : ''),
                    'solusvm2_reset_password' => !empty($post['reset_password']) ? 1 : 0,
                    'solusvm2_user_data' => ($post['user_data'] ?? '')
                ];

                if (!empty($post['ssh_keys']) && is_array($post['ssh_keys'])) {
                    $data['solusvm2_ssh_keys'] = $post['ssh_keys'];
                }

                Loader::loadModels($this, ['Services']);
                $this->Services->edit($service->id, $data);

                if (($errors = $this->Services->errors())) {
                    $this->Input->setErrors($errors);
                    $vars = $post;
                } else {
                    $this->setMessage('success', Language::_('Solusvm2.!success.reinstall', true));
                    $vars = [];
                }
            } else {
                $vars = $post;
            }
        }

        $this->view->set('os_images', $os_images);
        $this->view->set('applications', $applications);
        $this->view->set('ssh_keys', $ssh_keys);
        $this->view->set('service_fields', $service_fields);
        $this->view->set('vars', (object)$vars);
        $this->view->set('client_id', $service->client_id);
        $this->view->set('service_id', $service->id);

        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'solusvm2' . DS);
        return $this->view;
    }

    /**
     * Builds the data for the admin/client console tabs
     * @see Solusvm2::tabConsole() and Solusvm2::tabClientConsole()
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param bool $client True if the tab is rendered for the client area, false otherwise
     * @return View A template view to be rendered
     */
    private function consoleTab($package, $service, $client = false)
    {
        $template = ($client ? 'tab_client_console' : 'tab_console');

        $this->view = new View($template, 'default');
        $this->view->base_uri = $this->base_uri;

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $module_row = $this->getModuleRow($package->module_row);

        $server_id = ($service_fields->solusvm2_server_id ?? null);

        $console = new stdClass();
        $console->vnc_password = null;
        $console->ws_url = null;

        if ($server_id && $module_row) {
            $api = $this->getApi($module_row->meta->host, $module_row->meta->api_token);

            // Fetch the VNC password
            $this->log($module_row->meta->host . '|servers/' . (int)$server_id, serialize([]), 'input', true);
            $server = $this->parseResponse($api->getServer($server_id), $module_row);

            if (!$this->Input->errors() && $server) {
                $console->vnc_password = ($server['data']['settings']['vnc_password']
                    ?? ($server['data']['settings']['vnc']['password'] ?? null));

                // Bring the VNC console up
                $this->log($module_row->meta->host . '|servers/' . (int)$server_id . '/vnc_up', serialize([]), 'input', true);
                $vnc = $this->parseResponse($api->vncUp($server_id), $module_row);

                $url = ($vnc['url'] ?? ($vnc['data']['url'] ?? null));
                if (!$this->Input->errors() && $url) {
                    if (preg_match('#^wss?://#', $url)) {
                        $console->ws_url = $url;
                    } else {
                        $console->ws_url = 'wss://' . $module_row->meta->host . '/vnc?url=' . rawurlencode($url);
                    }
                }
            }
        }

        $this->view->set('console', $console);
        $this->view->set('service_fields', $service_fields);

        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'solusvm2' . DS);
        $this->view->view_dir = $this->base_uri . 'components/modules/solusvm2/views/default/';
        return $this->view;
    }

    /**
     * Fetches the virtual server info from the API as an object.
     * API errors are ignored (an empty object is returned instead).
     *
     * @param int $server_id The virtual server ID
     * @param stdClass $module_row An stdClass object representing the module row
     * @return stdClass The server data
     */
    private function getServerInfo($server_id, $module_row)
    {
        $server = new stdClass();

        if (empty($server_id) || !$module_row) {
            return $server;
        }

        $api = $this->getApi($module_row->meta->host, $module_row->meta->api_token);

        try {
            $this->log($module_row->meta->host . '|servers/' . (int)$server_id, serialize([]), 'input', true);
            $response = $this->parseResponse($api->getServer($server_id), $module_row, true);

            if (!empty($response['data'])) {
                $server = json_decode(json_encode($response['data']));
            }
        } catch (\Throwable $e) {
            // Nothing to do
        }

        return $server;
    }

    /**
     * Synchronizes the Blesta client with a SolusVM 2 user. May set Input errors on failure.
     *
     * @param int $client_id The Blesta client ID
     * @param stdClass $row The module row
     * @param stdClass $package A stdClass object representing the selected package
     * @return int|null The SolusVM 2 user ID, or null on failure
     */
    private function syncUser($client_id, $row, $package)
    {
        $api = $this->getApi($row->meta->host, $row->meta->api_token);

        // Fetch the Blesta client
        Loader::loadModels($this, ['Clients']);
        $client = $this->Clients->get($client_id);

        $email = ($client->email ?? '');
        if (empty($email)) {
            $this->Input->setErrors(['api' => ['internal' => Language::_('Solusvm2.!error.api.internal', true)]]);
            return null;
        }

        // Find an existing SolusVM 2 user by email
        $this->log($row->meta->host . '|users (search)', serialize(['email' => $email]), 'input', true);
        $response = $this->parseResponse($api->getUserByEmail($email), $row);

        if ($this->Input->errors()) {
            return null;
        }

        $users = array_values((array)($response['data'] ?? []));
        if (!empty($users[0]['id'])) {
            $user_id = (int)$users[0]['id'];

            // Link the user to this Blesta client
            if (($users[0]['billing_user_id'] ?? null) != $client_id) {
                $data = ['billing_user_id' => (string)$client_id];
                $this->log($row->meta->host . '|users/' . $user_id, serialize($data), 'input', true);
                $this->parseResponse($api->updateUser($user_id, $data), $row, true);
            }

            return $user_id;
        }

        // Create a new SolusVM 2 user
        $params = [
            'email' => $email,
            'password' => $this->generatePassword(),
            'status' => 'active',
            'billing_user_id' => (string)$client_id
        ];

        if (!empty($package->meta->role)) {
            $params['roles'] = [(int)$package->meta->role];
        }

        if (!empty($package->meta->limit_group)) {
            $params['limit_group_id'] = (int)$package->meta->limit_group;
        }

        $masked_params = $params;
        $masked_params['password'] = '***';
        $this->log($row->meta->host . '|users', serialize($masked_params), 'input', true);
        $response = $this->parseResponse($api->createUser($params), $row);

        if ($this->Input->errors()) {
            return null;
        }

        return (int)($response['data']['id'] ?? 0);
    }

    /**
     * Gets the fields for the virtual server create request from the input vars
     *
     * @param array $vars An array of user supplied info to satisfy the request
     * @param stdClass $package A stdClass object representing the selected package
     * @return array The server create request parameters
     */
    private function getFieldsFromInput(array $vars, $package)
    {
        $hostname = strtolower($this->replaceText(($vars['solusvm2_hostname'] ?? ''), '', '/^\s*www\./i'));

        $params = [
            'name' => $hostname,
            'plan' => (int)($package->meta->plan ?? 0),
            'password' => $this->generatePassword()
        ];

        if (!empty($hostname)) {
            $params['fqdns'] = [$hostname];
        }

        if (!empty($package->meta->location)) {
            $params['location'] = (int)$package->meta->location;
        }

        // Application or plain OS installation
        $set_application = ($package->meta->set_application
            ?? (!empty($package->meta->application) ? 'admin' : 'none'));
        $application = null;
        if ($set_application == 'admin' && !empty($package->meta->application)) {
            $application = (int)$package->meta->application;
        } elseif ($set_application == 'client' && !empty($vars['solusvm2_application'])) {
            $application = (int)$vars['solusvm2_application'];
        }

        if ($application) {
            $params['application'] = $application;
            // ponytail: application_data is not collected; applications with required fields
            // will fail validation, add a per-service field mapping when one is needed
            $params['application_data'] = new stdClass();
        } else {
            // Set the OS, either from the package or the client input
            $os = null;
            if (isset($package->meta->set_os) && $package->meta->set_os == 'client') {
                $os = ($vars['solusvm2_os'] ?? null);
            } elseif (!empty($package->meta->os)) {
                $os = $package->meta->os;
            }

            if ($os) {
                $params['os'] = (int)$os;
            }

            if (!empty($package->meta->user_data)) {
                $params['user_data'] = str_replace("\r\n", "\n", $package->meta->user_data);
            }
        }

        // Set the SSH keys to deploy
        if (!empty($package->meta->ssh_key)) {
            $params['ssh_keys'] = [(int)$package->meta->ssh_key];
        }

        // Set the backup schedule
        $params['backup_settings'] = $this->getBackupSettings($package);

        // Set any config options
        $this->loadLib('solusvm2_service_options');
        $options = new Solusvm2ServiceOptions((array)($vars['configoptions'] ?? []));

        $custom_plan = $options->getCustomPlan();
        if ($custom_plan) {
            $params['custom_plan'] = $custom_plan;
        }

        // Enable snapshots for the server when set on the package
        if (isset($package->meta->snapshot_enabled) && $package->meta->snapshot_enabled == '1') {
            $params['custom_plan']['is_snapshots_enabled'] = true;
        }

        $extra_ips = $options->getAdditionalIpCount();
        if ($extra_ips !== null) {
            $params['additional_ip_count'] = $extra_ips;
        }

        return $params;
    }

    /**
     * Builds the backup_settings request data for the given package
     *
     * @param stdClass $package A stdClass object representing the package
     * @return array
     */
    private function getBackupSettings($package)
    {
        return [
            'enabled' => (isset($package->meta->backup_enabled) && $package->meta->backup_enabled == '1'),
            'schedule' => [
                'type' => 'daily',
                'time' => ['hour' => 0, 'minutes' => 0]
            ]
        ];
    }

    /**
     * Initializes the API and returns an instance of that object
     *
     * @param string $host The host of the SolusVM 2 master
     * @param string $api_token The API token of the SolusVM 2 master
     * @return Solusvm2Api The Solusvm2Api instance
     */
    private function getApi($host, $api_token)
    {
        Loader::load(dirname(__FILE__) . DS . 'apis' . DS . 'solusvm2_api.php');

        return new Solusvm2Api($host, $api_token);
    }

    /**
     * Retrieves the module row for the given module row ID, or the first
     * row of the given module group
     *
     * @param int $module_row The module row ID
     * @param string $module_group The module group (optional, default "")
     * @return stdClass An stdClass object representing the module row
     */
    private function getModuleRowByServer($module_row, $module_group = '')
    {
        // Fetch the module row available for this package
        $row = null;
        if ($module_group == '') {
            if ($module_row > 0) {
                $row = $this->getModuleRow((int)$module_row);
            }
        } else {
            // Fetch the 1st server from the list of servers in the selected group
            $rows = $this->getModuleRows($module_group);

            if (isset($rows[0])) {
                $row = $rows[0];
            }
            unset($rows);
        }

        // Fall back to the first module row available
        if (!$row) {
            $rows = $this->getModuleRows();
            if (isset($rows[0])) {
                $row = $rows[0];
            }
            unset($rows);
        }

        return $row;
    }

    /**
     * Parses the API response: logs the raw output and sets Input errors on failure.
     *
     * @param Solusvm2Response $response The API response
     * @param stdClass $module_row An stdClass object representing the module row
     * @param bool $ignore_error True to ignore API errors (they are logged but not set on Input)
     * @return array|null The decoded response body on success, null on failure
     */
    private function parseResponse(Solusvm2Response $response, $module_row = null, $ignore_error = false)
    {
        $success = ($response->status() == 'success');

        // Log the raw response with passwords masked
        $masked_output = preg_replace(
            '/("(?:password|vnc_password)"\s*:\s*")[^"]*(")/i',
            '$1***$2',
            (string)$response->raw()
        );
        $this->log(($module_row->meta->host ?? ''), $masked_output, 'output', $success);

        if (!$success) {
            if (!$ignore_error) {
                $errors = $response->errors();
                $this->Input->setErrors(
                    [
                        'api' => [
                            'response' => $errors
                                ? implode(' ', $errors)
                                : Language::_('Solusvm2.!error.api.internal', true)
                        ]
                    ]
                );
            }

            return null;
        }

        return $response->data();
    }

    /**
     * Fetches a paginated reference list from the API. API errors are ignored.
     *
     * @param stdClass $module_row An stdClass object representing the module row
     * @param string $method The API list method to call (e.g. "listPlans")
     * @return array The list of items
     */
    private function fetchList($module_row, $method)
    {
        if (!$module_row) {
            return [];
        }

        $api = $this->getApi($module_row->meta->host, $module_row->meta->api_token);

        try {
            $this->log($module_row->meta->host . '|' . $method, serialize([]), 'input', true);
            $response = $this->parseResponse($api->{$method}(), $module_row, true);

            return (array)($response['data'] ?? []);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Fetches the available plans as an ID => name map
     *
     * @param stdClass $module_row An stdClass object representing the module row
     * @return array
     */
    private function getPlans($module_row)
    {
        $plans = [];
        foreach ($this->fetchList($module_row, 'listPlans') as $plan) {
            $name = $plan['name'];
            if (!empty($plan['virtualization_type'])) {
                $name .= ' (' . strtoupper($plan['virtualization_type']) . ')';
            }
            $plans[$plan['id']] = $name;
        }

        return $plans;
    }

    /**
     * Fetches the available locations as an ID => name map
     *
     * @param stdClass $module_row An stdClass object representing the module row
     * @return array
     */
    private function getLocations($module_row)
    {
        $locations = [];
        foreach ($this->fetchList($module_row, 'listLocations') as $location) {
            $locations[$location['id']] = $location['name'];
        }

        return $locations;
    }

    /**
     * Fetches the available operating systems as a version ID => name map
     *
     * @param stdClass $module_row An stdClass object representing the module row
     * @return array
     */
    private function getOsImages($module_row)
    {
        $os_images = [];
        foreach ($this->fetchList($module_row, 'listOsImages') as $image) {
            foreach ((array)($image['versions'] ?? []) as $version) {
                $os_images[$version['id']] = trim(
                    $image['name'] . ' ' . ($version['version'] ?? ($version['name'] ?? ''))
                );
            }
        }

        return $os_images;
    }

    /**
     * Fetches the available applications as an ID => name map
     *
     * @param stdClass $module_row An stdClass object representing the module row
     * @return array
     */
    private function getApplications($module_row)
    {
        $applications = [];
        foreach ($this->fetchList($module_row, 'listApplications') as $application) {
            $applications[$application['id']] = $application['name'];
        }

        return $applications;
    }

    /**
     * Fetches the available ISO images as an ID => name map
     *
     * @param stdClass $module_row An stdClass object representing the module row
     * @return array
     */
    private function getIsoImages($module_row)
    {
        $iso_images = [];
        foreach ($this->fetchList($module_row, 'listIsoImages') as $iso_image) {
            $iso_images[$iso_image['id']] = $iso_image['name'];
        }

        return $iso_images;
    }

    /**
     * Fetches the available user roles as an ID => name map
     *
     * @param stdClass $module_row An stdClass object representing the module row
     * @return array
     */
    private function getRoles($module_row)
    {
        $roles = [];
        foreach ($this->fetchList($module_row, 'listRoles') as $role) {
            $roles[$role['id']] = $role['name'];
        }

        return $roles;
    }

    /**
     * Fetches the available limit groups as an ID => name map
     *
     * @param stdClass $module_row An stdClass object representing the module row
     * @return array
     */
    private function getLimitGroups($module_row)
    {
        $limit_groups = [];
        foreach ($this->fetchList($module_row, 'listLimitGroups') as $limit_group) {
            $limit_groups[$limit_group['id']] = $limit_group['name'];
        }

        return $limit_groups;
    }

    /**
     * Fetches the available SSH keys as an ID => name map
     *
     * @param stdClass $module_row An stdClass object representing the module row
     * @return array
     */
    private function getSshKeys($module_row)
    {
        $ssh_keys = [];
        foreach ($this->fetchList($module_row, 'listSshKeys') as $ssh_key) {
            $ssh_keys[$ssh_key['id']] = $ssh_key['name'];
        }

        return $ssh_keys;
    }

    /**
     * Retrieves a list of rules for validating adding/editing a module row
     *
     * @param array $vars A list of input vars
     * @return array A list of rules
     */
    private function getRowRules(array &$vars)
    {
        return [
            'server_name' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Solusvm2.!error.server_name.empty', true)
                ]
            ],
            'host' => [
                'format' => [
                    'pre_format' => function ($host) {
                        return preg_replace('#^https?://#i', '', rtrim(trim((string)$host), '/'));
                    },
                    'rule' => [[$this, 'validateHostName']],
                    'message' => Language::_('Solusvm2.!error.host.format', true)
                ]
            ],
            'api_token' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Solusvm2.!error.api_token.empty', true)
                ]
            ]
        ];
    }

    /**
     * Retrieves a list of rules for validating adding/editing a package
     *
     * @param array $vars A list of input vars
     * @return array A list of rules
     */
    private function getPackageRules(array $vars = null)
    {
        $rules = [
            'meta[plan]' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Solusvm2.!error.meta[plan].empty', true)
                ]
            ],
            'meta[set_os]' => [
                'format' => [
                    'rule' => ['in_array', ['admin', 'client']],
                    'message' => Language::_('Solusvm2.!error.meta[set_os].format', true)
                ]
            ],
            'meta[set_application]' => [
                'format' => [
                    'rule' => ['in_array', ['none', 'admin', 'client']],
                    'message' => Language::_('Solusvm2.!error.meta[set_application].format', true)
                ]
            ]
        ];

        // Whether an application is set in the package (also true for legacy packages without set_application)
        $application_set = (!empty($vars['meta']['application'])
            && (($vars['meta']['set_application']
                ?? (!empty($vars['meta']['application']) ? 'admin' : 'none')) == 'admin'));

        // An OS must be given for this package when set by the admin and no application is selected
        if (($vars['meta']['set_os'] ?? 'client') == 'admin' && !$application_set) {
            $rules['meta[os]'] = [
                'empty' => [
                    'rule' => ['in_array', ['', '--none--']],
                    'negate' => true,
                    'message' => Language::_('Solusvm2.!error.meta[os].empty', true)
                ]
            ];
        }

        return $rules;
    }

    /**
     * Validates that the given hostname is valid
     *
     * @param string $host_name The host name to validate
     * @param bool $require_fqdn True to require a FQDN (e.g. host.domain.com),
     *  or false for a partial name (e.g. domain.com) (optional, default false)
     * @return bool True if the hostname is valid, false otherwise
     */
    public function validateHostName($host_name, $require_fqdn = false)
    {
        if ($require_fqdn) {
            if (strlen($host_name) > 255) {
                return false;
            }

            $octet = '([a-z0-9]|[a-z0-9][a-z0-9\-]{0,61}[a-z0-9])';
            $nested_octet = '(\.' . $octet . ')';
            $hostname_regex = '/^' . $octet . $nested_octet . $nested_octet . '+$/i';

            $valid = $this->Input->matches($host_name, $hostname_regex);
        } else {
            $validator = new Server();
            $valid = $validator->isDomain($host_name) || $validator->isIp($host_name);
        }

        return $valid;
    }

    /**
     * Validates that the given string looks like an SSH public key
     *
     * @param string $key The SSH public key to validate
     * @return bool True if the key appears valid, false otherwise
     */
    public function validateSshPublicKey($key)
    {
        $key = trim((string)$key);
        if (empty($key)) {
            return false;
        }

        $parts = explode(' ', $key, 3);
        $types = ['ssh-rsa', 'ssh-ed25519', 'ssh-dss', 'ecdsa-sha2-nistp256', 'ecdsa-sha2-nistp384', 'ecdsa-sha2-nistp521'];

        return in_array($parts[0], $types) && !empty($parts[1]);
    }

    /**
     * Validates whether the given OS is a valid OS image version for this server
     *
     * @param int $os The OS image version ID
     * @param string $module_row The server module row
     * @param string $module_group The server module group (optional, default "")
     * @return bool True if the OS is valid, false otherwise
     */
    public function validateOs($os, $module_row, $module_group = '')
    {
        // Fetch the module row
        $row = $this->getModuleRowByServer($module_row, $module_group);
        $os_images = $this->getOsImages($row);

        return array_key_exists($os, $os_images);
    }

    /**
     * Validates an OS/application choice: the application wins when set,
     * otherwise the OS must be a valid OS image version
     *
     * @param int $os The OS image version ID
     * @param int $application The application ID (optional)
     * @param string $module_row The server module row
     * @param string $module_group The server module group (optional, default "")
     * @return bool True if the choice is valid, false otherwise
     */
    public function validateOsChoice($os, $application, $module_row, $module_group = '')
    {
        if (!empty($application)) {
            return $this->validateApplication($application, $module_row, $module_group);
        }

        return $this->validateOs($os, $module_row, $module_group);
    }

    /**
     * Validates whether the given application is a valid application for this server
     *
     * @param int $application The application ID
     * @param string $module_row The server module row
     * @param string $module_group The server module group (optional, default "")
     * @return bool True if the application is valid, false otherwise
     */
    public function validateApplication($application, $module_row, $module_group = '')
    {
        // Fetch the module row
        $row = $this->getModuleRowByServer($module_row, $module_group);
        $applications = $this->getApplications($row);

        return array_key_exists($application, $applications);
    }

    /**
     * Performs text replacement on the given text matching the given regex
     *
     * @param string $text The string to perform replacement on
     * @param string $replacement The replacement text to use
     * @param string $regex A valid PCRE pattern
     * @return string The updated text
     */
    public function replaceText($text, $replacement, $regex)
    {
        return preg_replace($regex, $replacement, $text);
    }

    /**
     * Generates a password of the given length from numbers, upper and lower case characters
     *
     * @param int $length The length of the password (optional, default 15)
     * @return string The generated password
     */
    private function generatePassword($length = 15)
    {
        $sets = ['0123456789', 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'];
        $per_set = intdiv($length, count($sets));

        $password = '';
        foreach ($sets as $set) {
            for ($i = 0; $i < $per_set; $i++) {
                $password .= $set[random_int(0, strlen($set) - 1)];
            }
        }

        // Pad the remainder with alphanumeric characters
        $all = implode('', $sets);
        while (strlen($password) < $length) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }

    /**
     * Generates a random hostname in the form of "word1-word2.domain"
     *
     * @return string The generated hostname
     */
    private function generateHostname()
    {
        $adjectives = [
            'amber', 'brave', 'calm', 'clever', 'crimson', 'delta', 'eager', 'ember', 'falcon', 'fleet', 'gentle',
            'golden', 'happy', 'harbor', 'ivory', 'jolly', 'keen', 'lively', 'lunar', 'mellow', 'noble', 'ocean',
            'proud', 'quick', 'quiet', 'rapid', 'river', 'silver', 'solar', 'steady', 'swift', 'velvet'
        ];
        $nouns = [
            'anchor', 'arrow', 'aspen', 'breeze', 'bridge', 'brook', 'cascade', 'cedar', 'cliff', 'cloud', 'comet',
            'creek', 'drift', 'field', 'forest', 'fox', 'glade', 'hawk', 'heron', 'island', 'lake', 'lark', 'maple',
            'meadow', 'otter', 'pearl', 'pine', 'ridge', 'sparrow', 'stone', 'timber', 'willow'
        ];

        $domain = trim((string)Configure::get('Solusvm2.hostname.default_domain'));
        if ($domain === '') {
            $domain = $this->getCompanyDomain();
        }

        $hostname = $adjectives[array_rand($adjectives)] . '-' . $nouns[array_rand($nouns)];

        return ($domain !== '' ? $hostname . '.' . $domain : $hostname);
    }

    /**
     * Returns the root domain of the Blesta company hostname.
     * Strips the leading subdomain (e.g. "blesta.trust-me.host" -> "trust-me.host").
     *
     * @return string The root domain, or an empty string if it cannot be determined
     */
    private function getCompanyDomain()
    {
        $company = Configure::get('Blesta.company');
        $hostname = '';

        if (is_object($company) && isset($company->hostname)) {
            $hostname = $company->hostname;
        }

        if ($hostname === '' && isset($_SERVER['HTTP_HOST'])) {
            $hostname = $_SERVER['HTTP_HOST'];
        }

        $hostname = strtolower(trim($hostname));
        $hostname = preg_replace('/^www\./i', '', $hostname);

        $parts = explode('.', $hostname);
        if (count($parts) > 2) {
            array_shift($parts);
        }

        return implode('.', $parts);
    }

    /**
     * Converts bytes to a string representation including the type
     *
     * @param int $bytes The number of bytes
     * @return string A formatted amount including the type (B, KB, MB, GB)
     */
    private function convertBytesToString($bytes)
    {
        $step = 1024;
        $unit = 'B';

        $bytes = (int)$bytes;
        if (($value = number_format($bytes / ($step * $step * $step), 2)) >= 1) {
            $unit = 'GB';
        } elseif (($value = number_format($bytes / ($step * $step), 2)) >= 1) {
            $unit = 'MB';
        } elseif (($value = number_format($bytes / $step, 2)) >= 1) {
            $unit = 'KB';
        } else {
            $value = $bytes;
        }

        return Language::_('Solusvm2.!bytes.value', true, $value, $unit);
    }

    /**
     * Loads a library class
     *
     * @param string $command The filename of the class to load
     */
    private function loadLib($command)
    {
        Loader::load(dirname(__FILE__) . DS . 'lib' . DS . $command . '.php');
    }
}
