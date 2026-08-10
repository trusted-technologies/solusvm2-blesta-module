<?php

/**
 * SolusVM 2 Service Options
 *
 * Maps Blesta package config options to SolusVM 2 API request parameters
 * (custom_plan and additional_ip_count).
 *
 * Supported option names (case-insensitive):
 * - memory: RAM in MB, overrides the plan
 * - disk: Disk space in GB, overrides the plan
 * - vcpu: Number of vCPUs, overrides the plan
 * - swap: Swap in MB, overrides the plan
 * - traffic: Total traffic limit per month in GB, overrides the plan
 * - extra_ips: Number of additional IPv4 addresses
 *
 * @package blesta
 * @subpackage blesta.components.modules.solusvm2
 */
class Solusvm2ServiceOptions
{
    /**
     * @var array Normalized config options (lowercase name => value)
     */
    private $options = [];

    /**
     * Initializes the service options
     *
     * @param array $config_options A name => value map of Blesta config options
     */
    public function __construct(array $config_options)
    {
        foreach ($config_options as $name => $value) {
            if (is_scalar($value)) {
                $this->options[strtolower(trim($name))] = trim($value);
            }
        }
    }

    /**
     * Builds the custom_plan request data from the config options
     *
     * @return array|null The custom_plan data, or null if no plan options are set
     */
    public function getCustomPlan()
    {
        $custom_plan = [];

        if (($memory = $this->getNumeric('memory')) !== null) {
            // MB to bytes
            $custom_plan['params']['ram'] = $memory * 1024 * 1024;
        }

        if (($disk = $this->getNumeric('disk')) !== null) {
            // GB
            $custom_plan['params']['disk'] = $disk;
        }

        if (($vcpu = $this->getNumeric('vcpu')) !== null) {
            $custom_plan['params']['vcpu'] = $vcpu;
        }

        if (($swap = $this->getNumeric('swap')) !== null) {
            // MB to bytes
            $custom_plan['params']['swap'] = $swap * 1024 * 1024;
        }

        if (($traffic = $this->getNumeric('traffic')) !== null) {
            // GB per month
            $custom_plan['limits']['network_total_traffic'] = ['limit' => $traffic];
        }

        return !empty($custom_plan) ? $custom_plan : null;
    }

    /**
     * Returns the number of additional IP addresses from the config options
     *
     * @return int|null
     */
    public function getAdditionalIpCount()
    {
        return $this->getNumeric('extra_ips');
    }

    /**
     * Fetches a positive numeric option value
     *
     * @param string $name The option name
     * @return int|null
     */
    private function getNumeric($name)
    {
        if (isset($this->options[$name]) && is_numeric($this->options[$name]) && (int)$this->options[$name] > 0) {
            return (int)$this->options[$name];
        }

        return null;
    }
}
