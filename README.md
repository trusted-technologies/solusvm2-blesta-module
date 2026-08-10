# SolusVM 2 module for Blesta

A Blesta provisioning module for **SolusVM 2**. It talks to the SolusVM 2 JSON REST API (`/api/v1/`) using Bearer token authentication.

The official Blesta module for SolusVM only supports the legacy SolusVM v1 XML API and does not work with SolusVM 2. This module was built to fill that gap.

## Requirements

- Blesta 5.x or later
- PHP 7.4 or later
- A SolusVM 2 master with an API token (Admin role)

## Installation

1. Download or clone this repository.
2. Copy the module files into your Blesta installation at:

   ```
   components/modules/solusvm2/
   ```

3. Log in to the Blesta admin panel.
4. Go to **Settings > Company > Modules > Available**.
5. Find **SolusVM 2** and click **Install**.

## Hostname generation

On the client order form the hostname field is prefilled with a random name like `velvet-timber.trust-me.host`. The domain suffix is taken from the Blesta company hostname (configured under **Settings > Company > General**), stripping the leading subdomain. For example, if Blesta runs on `blesta.trust-me.host`, the suffix becomes `trust-me.host`.

To override this, set `Solusvm2.hostname.default_domain` in `config/solusvm2.php`.

## Adding a SolusVM 2 server

1. Go to **Settings > Company > Modules > Manage**.
2. Click **Add Server** next to **SolusVM 2**.
3. Fill in the form:
   - **Server Label** — any friendly name.
   - **Host** — the hostname of your SolusVM 2 master, e.g. `solusvm.example.com`. Do not include `https://`.
   - **API Token** — create one in SolusVM 2 under **Access > API Tokens**. The token must belong to a user with the **Admin** role.

## Package settings

When creating or editing a package that uses the SolusVM 2 module, you can configure:

- **Plan** — the SolusVM 2 plan assigned to the service.
- **Location** — the data center location.
- **Operating System** — fixed by admin or chosen by the client during order.
- **Application** — optional one-click application (Plesk, WordPress, etc.).
- **Cloud-init user data** — raw cloud-config pasted into the package.
- **Backups / Snapshots** — enable or disable.
- **User role / Limit group / SSH key** — SolusVM 2 account defaults.

## Configurable options

The module maps Blesta config options to a SolusVM 2 custom plan:

| Option | Unit | API field |
|--------|------|-----------|
| `memory` | MB | `memory` (converted to bytes) |
| `disk` | GB | `disk` |
| `vcpu` | count | `vcpu` |
| `swap` | MB | `swap` (converted to bytes) |
| `traffic` | GB/month | `traffic` |
| `extra_ips` | count | `additional_ip_count` |

Changes to these options trigger a resize when the service is edited.

## Service tabs

Both the admin and client service pages provide the same tabs:

- **Actions** — boot, shutdown, power off, reboot, reinstall, change hostname, reset root password.
- **Stats** — server status, primary IP, plan specs, traffic usage.
- **Console** — browser-based noVNC console.

## Welcome email

The module registers email tags for service welcome emails:

- `{service.solusvm2_server_id}`
- `{service.solusvm2_hostname}`
- `{service.solusvm2_main_ip_address}`
- `{service.solusvm2_root_password}`
- `{service.solusvm2_os}`
- `{service.solusvm2_application}`
- `{service.solusvm2_plan}`

## Known limitations

- Backups and snapshots are exposed through package flags, but there is no service tab yet for managing them manually.
- `application_data` is not collected per service. One-click applications that require extra input fields may fail with a `422` validation error.
- 360 Monitoring, vGPU, disaster recovery, and additional disks are not supported.
- Only English (`en_us`) is included.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Support and contributions

This is a community module. Bug reports and pull requests are welcome at:

https://github.com/trusted-technologies/solusvm2-blesta-module
