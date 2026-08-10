<?php

// Polling interval for how often to refresh page content after an action is performed on pages that support it
// Note: Set to number of milliseconds (1000 = 1 second)
Configure::set('Solusvm2.page_refresh_rate_fast', '5000');

// Polling interval for how often to refresh page content on pages that support it
// Note: Set to number of milliseconds (1000 = 1 second)
Configure::set('Solusvm2.page_refresh_rate', '30000');

// Default domain suffix for auto-generated hostnames on the client order form
// (a hostname like "amber-fox.trust-me.host" is prefilled when the client leaves the field empty)
Configure::set('Solusvm2.hostname.default_domain', 'trust-me.host');

// Email templates
Configure::set('Solusvm2.email_templates', [
    'en_us' => [
        'lang' => 'en_us',
        'text' => 'Thank you for ordering {service.solusvm2_plan}, details below:

Hostname: {service.solusvm2_hostname}
IP Address: {service.solusvm2_main_ip_address}
Root Password: {service.solusvm2_root_password}

SolusVM 2 Panel: https://{module.host}

Thank you for your business!',
        'html' => '<p>Thank you for ordering {service.solusvm2_plan}, details below:</p>
<p>Hostname: {service.solusvm2_hostname}<br />IP Address: {service.solusvm2_main_ip_address}<br />Root Password: {service.solusvm2_root_password}</p>
<p>SolusVM 2 Panel: https://{module.host}</p>
<p>Thank you for your business!</p>'
    ]
]);
