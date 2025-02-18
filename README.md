# wp-deploy-flow

Created by [Arnaud Sellenet](http://demental.info) and maintained by [tyler](https://tyler.shuster.house)@[pacificsky.co](https://pacificsky.co)

## Dependencies

* Wordpress
* rsync
* Key-based authentication

##  Install - Usage

Check here : https://blog.pacificsky.co/a-wordpress-deploy-workflow-678e83b534a1

### Setting up environments

You can create as many environments as you want, all the environments must be setup in your wp-config.php file, with prefixed constants.

For example if you want to create a staging environment, create all the necessary constants to configure it such as : STAGING_DB_HOST, STAGING_DB_USER, STAGING_URL and so on .... And copy all those configuration constants to all the other environments you want to interact with.

### Available constants:


#### DEPLOY_[ENV]_DB_HOST / USER / NAME / PORT / PASSWORD
* Database dsn for the environment
* _Mandatory_: Yes except for port (default 3306)

#### DEPLOY_[ENV]_SSH_DB_HOST / USER / PATH / PORT
* If you need to connect to the destination database through SSH
* _Mandatory_: No, port defaults to 22

#### DEPLOY_[ENV]_SSH_HOST / USER / PORT
* SSH host to sync with Rsync
* _Mandatory_: No, port defaults to 22

#### DEPLOY_[ENV]_PATH
* Server path for the environment (used to reconfigure the Wordpress database)
* _Mandatory_: Yes

#### DEPLOY_[ENV]_URL
* Url of the Wordpress install for this environment (used to reconfigure the Wordpress database)
* _Mandatory_: Yes

#### DEPLOY_[ENV]_EXCLUDES
* Add files to exclude from rsync (a good idea is - temporarily I hope - to remove .htaccess to avoid manual flush rewrite). List must be separated buy semicolons.
* _Mandatory_: No


### Local deployment

wp-deploy-flow command is a nice tool to have a draft copy of your website, play with your draft, do whatever mistake and roll back from production to staging, or for preparing a big update and deploy in a snap.
Although it's best to have separate servers for each environments, you still can have your draft environment on the same HTTP server, in a subfolder or a subdomain.
For same-server environments, the configuration is much simpler : you just need to fill the PATH, URL, DB_HOST / USER / NAME / PASSWORD for each environment, SSH will not be used in this case.
If one environment is in a subfolder of the other, it will be automatically excluded from rsync copy to avoid infinite recursion.

### Usage

wp-deploy-flow includes:
* four subcommands : `pull`,  `pull_files`, `push` and `push_files`
* one flag : `--dry-run` as you can guess this flag allows you to see what SSH commands will be executed before actually launching them.

All subcommands have the same signature :

```
wp deploy <subcommand> <environment> [--dry-run]
```
