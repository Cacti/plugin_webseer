# webseer

This is Cacti's Web Services monitoring plugin. This plugin allows you to add
Web Site service monitoring to Cacti. You simply add service checks, the desired
service check URL, and the expected response from it's interface, as well as any
escallation required if a service check fails. The plugin records statistics
about the connection to the website, it's response, and can alert when the
status changes.

This plugin, like many others, integrates with Cacti's Maintenance or 'maint'
plugin so you can setup maintenance schedules so that known times when a service
is going to be down can be configured so that escallation does not needlessly
take place during maintenance periods.

## Versions and compatibility

This README describes the `develop` source branch.

| Code | Plugin version | Minimum Cacti declared in `INFO` |
| --- | --- | --- |
| [Source branch](https://github.com/Cacti/plugin_webseer/blob/develop/INFO) | 3.3 | 1.2.29 |
| [Published v3.2](https://github.com/Cacti/plugin_webseer/releases/tag/v3.2) | 3.2 | 1.2.24 |

Use the published release’s assets and tagged documentation when installing that
package. Branch code can include later changes, even when its version number
matches the release. Cacti’s own PHP and database requirements also apply.

Install this plugin in **`<cacti-root>/plugins/webseer`**, using the directory
name `webseer` rather than `plugin_webseer`.

See [CHANGELOG.md](https://github.com/Cacti/plugin_webseer/blob/develop/CHANGELOG.md) for the version history.

## Cacti compatibility

If you are running a version of Cacti below 1.2.31, please add the function
below to the `applySkin()` function in `include/layout.js` to enable the Cancel
buttons on forms to work:

```js
$(document).off('click.cactiReturnTo', '.cactiReturnTo')
    .on('click.cactiReturnTo', '.cactiReturnTo', function(event) {
        event.preventDefault();
        cactiReturnTo($(this).attr('data-url'));
    });
```

## Installation

To install the webseer plugin, simply copy the plugin_webseer directory to
Cacti's plugins directory and rename it to simply 'webseer'. Once you have done
this, goto Cacti's Plugin Management page, Install and Enable the webseer. Once
this is complete, you can grant users permission to create service checks for
various Web Sites and Services.

Service checks are managed under **Management > Service Checks**. Grant the
**Web Service Check Admin** realm to users who need to create or edit service
checks, servers and proxies.

## Remote collection

Additional servers can be registered on the Servers page. A registered server
reports back to the main installation through `remote.php`, which accepts
heartbeat and result requests only from the IP address of a registered server.

## Upgrading

`poller_webseer.php` runs as a long-lived background process, so a running copy
keeps the old code in memory across a plugin upgrade. After upgrading the plugin
files, restart the webseer background process (stop the running
`poller_webseer.php`; Cacti's poller relaunches it on the next cycle) so it
picks up the new code. On upgrade the plugin also prunes its own bundled
development-only files (for example the `tests/` directory) from the installed
tree.

## Bugs and Feature Enhancements

Bug and feature enhancements for the webseer plugin are handled in GitHub. If
you find a first search the Cacti forums for a solution before creating an issue
in GitHub.

-----------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.

