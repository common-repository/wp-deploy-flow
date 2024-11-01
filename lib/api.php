<?php

namespace WP_Deploy_Flow {

	if (defined('WP_CLI') && WP_CLI) WP_CLI::add_command( 'deploy', 'WP_Deploy_Flow' );

	use phpseclib\Net\SSH2;

	class API {

		protected static $_env;

		public static $allowed_constants = array(
			'locked',
			'path',
			'url',
			'db_host',
			'db_user',
			'db_port',
			'db_name',
			'db_password',
			'ssh_db_host',
			'ssh_db_user',
			'ssh_db_path',
			'ssh_host',
			'ssh_user',
			'ssh_password',
			'ssh_port',
			'excludes',
			'path_owner'
		);

		public static $required_constants = array(
			'path',
			'url',
			'db_host',
			'db_user',
			'db_name',
			'db_password'
		);

		public static $excludes = array(
			'.git',
			'.sass-cache',
			'wp-content/cache',
			'wp-content/_wpremote_backups',
			'wp-config.php',
			'node_modules',
			'.htaccess',
			'wp-deploy-flow',
			'debug.log'
		);

		/**
		 * Gets a list of all environments, those defined as constants preceded by DEPLOY_
		 * @return array  List of environments
		 */
		public static function environments() {
			$environment_names = preg_filter('/^DEPLOY_([a-z]+).+/i', '$1', array_keys(get_defined_constants()));
			$environment_names = array_unique($environment_names);
			$environments = array();
			foreach($environment_names as $environment_name) {
				$environment = (object) array();
				$environment->name = $environment_name;
				foreach(self::$allowed_constants as $constant) {
					$constant_name = 'DEPLOY_' . $environment_name . '_' . strtoupper($constant);
					if(defined($constant_name)) {
						$environment->$constant = constant($constant_name);
					} else {
						$environment->$constant = false;
					}
				}
				$environments[] = $environment;
			}
			return $environments;
		}

		/**
	   * Push local to remote, both system and database
	   *
	   * @synopsis <environment> [--dry-run] [--files-only]
	   */
		public function push( $args = array(), $flags = array() ) {
	    $this->_push_command($args, $flags);
		}

		/**
	   * Pull local from remote, both system and database
	   *
	   * @synopsis <environment> [--dry-run] [--files-only]
	   */
		public function pull( $args = array(), $flags = array() ) {
	    $this->_pull_command($args, $flags);
		}

	  protected function _push_command($args, $flags) {
	    $this->params = self::_prepare_and_extract( $args );
	    $this->flags = $flags;
	    extract($this->params);

			if ( $locked === true ) {
				$error_message = "$env environment is locked, you cannot push to it";
				if (defined('WP_CLI') && WP_CLI) {
					return WP_CLI::error( $error_message );
				} else {
					return new WP_Error('locked', $error_message);
				}
			}
	    require 'pusher.php';

			$pusher = new Pusher($this->params, $this->flags);

	    $pusher->push();
		}

	  protected function _pull_command($args, $flags) {
	    $this->params = self::_prepare_and_extract( $args );
	    $this->flags = $flags;
	    extract($this->params);

			if ( $locked === true ) {
				$error_message = ENVIRONMENT . ' env is locked, you can not pull to your local copy';
				if (defined('WP_CLI') && WP_CLI) {
					return WP_CLI::error( $error_message );
				} else {
					return new WP_Error('locked', $error_message);
				}
			}

	    require 'puller.php';

			$puller = new Puller($this->params, $this->flags);

			$puller->pull();

		}

		protected static function _prepare_and_extract( $args ) {
			$out = array();
			self::$_env = $args[0];
			$errors = self::_validate_config();
			if ( $errors !== true ) {
				foreach ( $errors as $error ) {
					if (defined('WP_CLI') && WP_CLI) {
						WP_Cli::error( $error );
					} else {
						new WP_Error('error', $error);
						error_log($error);
					}
				}
				return false;
			}
			$out = self::config_constants_to_array();
			$out['env'] = self::$_env;
			$out['db_user'] = escapeshellarg( $out['db_user'] );
			$out['db_host'] = escapeshellarg( $out['db_host'] );
			$out['db_password'] = escapeshellarg( $out['db_password'] );
			$out['ssh_port'] = ( isset($out['ssh_port']) ) ? intval( $out['ssh_port']) : 22;
	    $out['excludes'] = explode(':', $out['excludes']);
			foreach(self::$allowed_constants as $constant) {
				// Set it to false so that the key exists but the value does not
				if(!isset($out[$constant])) $out[$constant] = false;
			}
			return $out;
		}

		protected static function _validate_config() {
			$errors = array();
			foreach ( self::$required_constants as $suffix ) {
				$required_constant = self::config_constant( $suffix );
				if ( ! defined( $required_constant ) ) {
					$errors[] = "$required_constant is not defined";
				}
			}
			if ( count( $errors ) == 0 ) return true;
			return $errors;
		}

		public static function config_constant( $suffix ) {
			return strtoupper( 'DEPLOY_' . self::$_env . '_' . $suffix );
		}

		protected static function config_constants_to_array() {
			$out = array();
			foreach ( self::$allowed_constants as $suffix ) {
				$out[$suffix] = defined( self::config_constant( $suffix ) ) ? constant( self::config_constant( $suffix ) ) : null;
			}
			return $out;
		}

		private static function _trim_url( $url ) {

			/** In case scheme relative URI is passed, e.g., //www.google.com/ */
			$url = trim( $url, '/' );

			/** If scheme not included, prepend it */
			if ( ! preg_match( '#^http(s)?://#', $url ) ) {
				$url = 'http://' . $url;
			}

			$url_parts = parse_url( $url );

			/** Remove www. */
			$domain = preg_replace( '/^www\./', '', $url_parts['host'] );

			return $domain;
		}

		/**
		 * Help function for this command
		 */
		public static function help() {
			if (defined('WP_CLI') && WP_CLI) {
				WP_CLI::line( <<<EOB

EOB
				);
			} else {
				echo 'No help for you' . PHP_EOL;
			}
	  }

		public static function ajax_interface () {
			$action = filter_var($_POST['action'], FILTER_SANITIZE_STRING);
			$environment = filter_var($_POST['environment'], FILTER_SANITIZE_STRING);

			if(in_array($environment, API::environments())) wp_die("Invalid environment '$environment'");

			if($_POST['ssh_password']) {
				$password = filter_var($_POST['ssh_password'], FILTER_SANITIZE_STRING);
				define("DEPLOY_{$environment}_SSH_PASSWORD", $password);
			}

			$flags = array();

			$files_only = filter_var($_POST['files_only'], FILTER_SANITIZE_STRING);
			$dry_run = filter_var($_POST['dry_run'], FILTER_SANITIZE_STRING);

			$flags['files-only'] = $files_only === 'true' ? true : false;
			$flags['dry-run'] = $dry_run === 'false' ? false : true; // Default to dry run

			$deployer = new API();

			switch($action) {
				case 'deploy_push':
					$deployer->push(array($environment), $flags);
				break;
				case 'deploy_pull':
					$environments = API::get_environments();
					ob_start();
					$deployer->pull(array($environment), $flags);
					$output = ob_get_clean();
					update_option('deploy_environments', $environments);
					wp_send_json(array(
						'output' => $output,
						'environments' => $environments
					));
				break;
				default:
					echo "incorrect command entered";
					var_dump($_POST);
				break;
			}
		}

		public static function add_environment () {
			$environment = array();
			$environment_name = filter_var($_POST['environment_name'], FILTER_SANITIZE_STRING);
			if(!$environment_name) wp_die('Environment must be named');
			$environment_name = strtoupper($environment_name);
			foreach($_POST as $constant => $value) {
				$constant = filter_var($constant, FILTER_SANITIZE_STRING);
				if(in_array($constant, self::$allowed_constants)) {
					$environment[$constant] = $value;
				}
			}
			$db_environments = self::get_environments();
			if($db_environments[$environment_name]) wp_die('Environment already defined');
			$db_environments[$environment_name] = $environment;
			$changed = self::set_environments($db_environments);
			wp_send_json(array('changed' => $changed));
		}

		public static function remove_environment () {
			$environment_name = filter_var($_POST['environment_name'], FILTER_SANITIZE_STRING);
			$db_environments = self::get_environments();
			$db_environments[$environment_name] = null;
			$changed = self::set_environments($db_environments);
			wp_send_json(array('changed' => $changed));
		}

		public static function init_constants () {
			$db_environments = self::get_environments();
			if(count($db_environments) > 0) {
				foreach($db_environments as $environment_name => $environment_options) {
					$environment_name = strtoupper($environment_name);
					foreach($environment_options as $constant => $value) {
						if(in_array($constant, self::$allowed_constants)) {
							$constant = strtoupper($constant);
							define("DEPLOY_{$environment_name}_{$constant}", $value);
						}
					}
				}
			}
		}

		public static function get_environments () {
			$environments = array();
			if(is_array(get_option('deploy_environments'))) {
				$environments = get_option('deploy_environments');
			}
			return $environments;
		}

		public static function set_environments ($environments) {
			return update_option('deploy_environments', $environments);
		}

		public static function unload_script($hook) {
			// Disable the session timeout notification on the deployment page,
			// as it interrupts the flow and may cause it to fail
			if($hook != 'tools_page_deployment') {
				return;
			}
			remove_action('admin_enqueue_scripts', 'wp_auth_check_load');
		}

		public static function submenu_page_callback () {
			ob_start();
			?>
				<div class="wrap">
					<h2>Deployment</h2>
					<p>Here is a list of the environments configured for deployment. Push or pull to any of them.</p>
					<p>Do not re-login until alerted that the deployment is completed.</p>
					<ul id="environments">
						<?php foreach(API::environments() as $environment): ?>
							<li data-environment="<?php echo $environment->name; ?>">
								<h3><?php echo $environment->name; ?></h3>
								<input type="button" class="remove_environment" value="Remove <?php echo strtolower($environment->name); ?> environment">
								<table>
									<?php foreach(API::$allowed_constants as $constant) {
										if($environment->$constant): ?>
										<tr>
											<td><?php echo strtoupper($constant); ?></td>
											<td data-constant="<?php echo strtoupper($constant); ?>"><?php echo $environment->$constant; ?></td>
										</tr>
										<?php endif;
									} ?>
								</table>
								<form>

								</form>
								<!-- <label for="files_only">
									Only transfer files
									<input type="checkbox" name="files_only">
								</label> -->
								<label for="dry_run">
									Test commands only (will create dumps but not affect databases)
									<input type="checkbox" name="dry_run">
								</label>
								<input type="button" value="Push" name="push">
								<input type="button" value="Pull" name="pull">
							</li>
						<?php endforeach; ?>
					</ul>
					<div id="loader">
						<span></span>
						<span></span>
						<span></span>
						<span></span>
						<span></span>
						<span></span>
						<span></span>
						<span></span>
						<span></span>
						<span></span>
						<span></span>
						<span></span>
						<span></span>
						<span></span>
						<span></span>
						<span></span>
						<span></span>
						<span></span>
						<span></span>
						<span></span>
					</div>
					<pre id="output"></pre>
					<form id="add_environment">
						<legend><h3>Add Environment</h3></legend>
						<table>
							<tr>
								<td>Environment Name</td>
								<td><input type="text" name="environment_name"></td>
							</tr>
							<?php foreach(API::$allowed_constants as $constant): ?>
								<tr>
									<td><label for="<?php echo $constant; ?>"><?php echo $constant; ?><?php if(in_array($constant, API::$required_constants)) echo '*'; ?></label></td>
									<td><input type="text" name="<?php echo $constant ?>" <?php if(in_array($constant, API::$required_constants)) echo 'required'; ?>></td>
								</tr>
							<?php endforeach; ?>
						</table>
						<input type="submit" value="Add Environment">
					</form>
				</div>
				<script>
				var remote = true;
				</script>
			<?php
			echo ob_get_clean();
		}

		public static function submenu_page_register () {
			add_submenu_page(
				'tools.php',
				'Deployment',
				'Deploy',
				'manage_options',
				'deployment',
				array('WP_Deploy_Flow\API', 'submenu_page_callback')
			);
		}

		public static function admin_enqueue_scripts ($hook) {
			if($hook != 'tools_page_deployment') {
				return;
			}
			wp_register_style('wp_deploy_flow_admin_css', plugins_url('../assets/css/admin.css', __FILE__), false, '1.0.0');
			wp_enqueue_style('wp_deploy_flow_admin_css');

			wp_register_script('wp_deploy_flow_admin_js', plugins_url('../assets/js/admin.js', __FILE__), false, '1.0.0');
			wp_enqueue_script('wp_deploy_flow_admin_js');
		}

	}

	class ExecResult {
		public $returnValue;
		public $stdoutBuffer;
		public $stderrBuffer;
	}

	class SendCommandWithPassword {
		public static function exec($proc, $password) {
			$cwd = getcwd();
			$env = [];

			$pipes = null; // will get filled by proc_open()

			$result = new ExecResult();

			$processHandle = proc_open(
				$proc,
				[
					0 => ['pipe', 'r'], // read/write is from child process's perspective
					1 => ['pipe', 'w'],
					2 => ['pipe', 'w']
				],
				$pipes,
				$cwd,
				$env);

				$stdin = $pipes[0];
				$stdout = $pipes[1];
				$stderr = $pipes[2];

				fwrite($stdin, $password);
				fclose($stdin);

				stream_set_blocking($stdout, false);
				stream_set_blocking($stderr, false);

				$outEof = false;
				$errEof = false;

				do {
					$read = [ $stdout, $stderr ]; // [1]
					$write = null; // [1]
					$except = null; // [1]

					// [1] need to be as variables because only vars can be passed by reference

					stream_select(
						$read,
						$write,
						$except,
						1, // seconds
						0); // microseconds

						$outEof = $outEof || feof($stdout);
						$errEof = $errEof || feof($stderr);

						if (!$outEof) {
							$result->stdoutBuffer .= fgets($stdout);
						}

						if (!$errEof) {
							$result->stderrBuffer .= fgets($stderr);
						}
					} while(!$outEof || !$errEof);

					fclose($stdout);
					fclose($stderr);

					$result->returnValue = proc_close($processHandle);

					return $result;
				}

			}

	add_action('wp_ajax_deploy_push', array('WP_Deploy_Flow\API', 'ajax_interface'));
	add_action('wp_ajax_deploy_pull', array('WP_Deploy_Flow\API', 'ajax_interface'));
	add_action('wp_ajax_deploy_add_environment', array('WP_Deploy_Flow\API', 'add_environment'));
	add_action('wp_ajax_deploy_remove_environment', array('WP_Deploy_Flow\API', 'remove_environment'));

	add_action('init', array('WP_Deploy_Flow\API', 'init_constants'));

	add_action('admin_enqueue_scripts', array('WP_Deploy_Flow\API', 'unload_script'), 1);
	add_action('admin_enqueue_scripts', array('WP_Deploy_Flow\API', 'admin_enqueue_scripts'), 1);

	add_action('admin_menu', array('WP_Deploy_Flow\API', 'submenu_page_register'));

}
