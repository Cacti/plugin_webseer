# webseer

## Cacti Web Services Monitoring Plugin

This is Cacti's Web Services monitoring plugin. This plugin allows you to add
Web Site service monitoring to Cacti. You simply add service checks, the desired
service check URL, and the expected response from it's interface, as well as any
escallation required if a service check fails. The plugin records statistics
about the connection to the website, it's response, and can alert when the
status changes.

This plugin has existed for years, but up until this year, has never been made
public. It can be an asset used to address some monitoring requirements for
customers who not only monitor servers, but need to know that the Web Services
on those servers are operating as expected.

This plugin, like many others, integrates with Cacti's Maintenance or 'maint'
plugin so you can setup maintenance schedules so that known times when a service
is going to be down can be configured so that escallation does not needlessly
take place during maintenance periods.

## Installation

To install the webseer plugin, simply copy the plugin_webseer directory to
Cacti's plugins directory and rename it to simply 'webseer'. Once you have done
this, goto Cacti's Plugin Management page, Install and Enable the webseer. Once
this is complete, you can grant users permission to create service checks for
various Web Sites and Services.

## Bugs and Feature Enhancements

Bug and feature enhancements for the webseer plugin are handled in GitHub. If
you find a first search the Cacti forums for a solution before creating an issue
in GitHub.

-----------------------------------------------
Copyright (c) 2004-2023 - The Cacti Group, Inc.

