=== WP Deploy Flow ===
Contributors: tylershuster
Tags: cli, deployment
Requires at least: 4.6
Tested up to: 4.7
Stable tag: trunk
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This adds functionality that allows developers to pull or push their entire site or only the files from a variety of remote environments.

== Description ==

This is a plugin to manage deployment of WordPress sites to one or multiple servers, whether development, staging or production. Add, remove, push to, or pull from environments under Tools -> Deploy.

Requires:
rsync
If using ssh and not the command line, must use key-based authentication

Add the following constants to your wp-config.php or add them via the admin interface.

(ENV can be any name of your choosing for your remote environment)

`DEPLOY_[ENV]_DB_HOST`
`DEPLOY_[ENV]_DB_USER`
`DEPLOY_[ENV]_DB_NAME`
`DEPLOY_[ENV]_DB_PORT`
`DEPLOY_[ENV]_DB_PASSWORD`
* Database dsn for the environment
* _Mandatory_: Yes except for port (default 3306)

`DEPLOY_[ENV]_SSH_DB_HOST`
`DEPLOY_[ENV]_SSH_DB_USER`
`DEPLOY_[ENV]_SSH_DB_PATH`
`DEPLOY_[ENV]_SSH_DB_PORT`
* If you need to connect to the destination database through SSH (you probably do)
* _Mandatory_: No, port defaults to 22

`DEPLOY_[ENV]_SSH_HOST`
`DEPLOY_[ENV]_SSH_USER`
`DEPLOY_[ENV]_SSH_PORT`
* SSH host to sync with Rsync
* _Mandatory_: No, port defaults to 22

`DEPLOY_[ENV]_PATH`
* Server path for the environment (used to reconfigure the Wordpress database)
* _Mandatory_: Yes

`DEPLOY_[ENV]_URL`
* Url of the Wordpress install for this environment (used to reconfigure the Wordpress database)
* _Mandatory_: Yes

`DEPLOY_[ENV]_EXCLUDES`
* Add files to exclude from rsync. List must be separated buy semicolons.
* _Mandatory_: No

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/plugin-name` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress


== Changelog ==

== Upgrade Notice ==

== Screenshots ==
