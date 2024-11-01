<?php

namespace WP_Deploy_Flow {

  use phpseclib\Net\SSH2;

  use phpseclib\Net\SCP;

  class Puller {

    public function __construct($params, $flags) {
      $this->params = $params;
      $this->flags = $flags;
    }

    public function pull() {

      if(!$this->flags['files-only']) {
        if($this->params['ssh_db_host']) {
          $this->_import_database_through_ssh();
        } else {
          $this->_import_database_locally();
        }

        $this->_dump_database();

        unlink(__DIR__ . '/dump.sql');
      }

      $this->_transfer_files();
    }

    protected function _import_database_through_ssh() {
      extract($this->params);
      $ssh = new SSH2($ssh_host);
      $command = '';
      $dist_path = constant(API::config_constant('path'));
      if(!$ssh->login($ssh_user, $ssh_password)) {
        exit("Login failed with user $ssh_user and password $ssh_password");
      }
      $scp = new SCP($ssh);
      $db_user = trim($db_user, "'");
      $command = "cd $dist_path";
      echo '<span class="line">' . "SSH: " . $command . '</span>';
      $ssh->exec($command);
      $command = "mysqldump -u $db_user -p $db_name > $dist_path/dump.sql\n";
      echo '<span class="line">' . "SSH: " . $command . '</span>';
      $ssh->write($command);
      $output = $ssh->read("/.+password.+/");
      echo $output;
      if(preg_match("/assword/", $output)) {
        echo "Entering password";
        $ssh->write(trim($db_password, "'") . "\n");
        echo $ssh->read('.+');
      }
      echo '<span class="line">' . "SCP: " . "$dist_path/dump.sql" . " to " . __DIR__ . '/dump.sql' . '</span>';
      $scp->get("$dist_path/dump.sql", __DIR__ . '/dump.sql');
      $command = "rm dump.sql";
      echo '<span class="line">' . "SSH: " . $command . '</span>';
      $ssh->exec($command);
    }

    protected function _import_database_locally () {
      extract($this->params);
      $command = "mysqldump -u $db_user -p$db_password $db_name --host=$db_host:$db_port > dump.sql";
      echo '<span class="line">' . $command . '</span>';
      exec($command);
    }

    protected function _dump_database () {
      require "srdb.class.php";
      global $wpdb;
      $environments = API::get_environments();
      $option_id = $wpdb->get_col("SELECT option_id FROM {$wpdb->prefix}options WHERE option_name = 'deploy_environments'")[0];
      var_dump($option_id);
      extract($this->params);
      $local_db_user = constant('DB_USER');
      $local_db_name = constant('DB_NAME');
      $local_db_host = constant('DB_HOST');
      $local_db_password = constant('DB_PASSWORD');
      // Open a process for interactively entering the password
      $command = "mysqldump -u $local_db_user -p$local_db_password $local_db_name > " . __DIR__ . "/db_bk.sql";
      echo '<span class="line">' . $command . '</span>';
      $descriptors = array(
        0 => array("pipe", "r"),
        1 => array("pipe", "w")
      );
      $process = proc_open($command, $descriptors, $pipes);
      if(is_resource($process)) {
        fwrite($pipes[0], constant('DB_PASSWORD'));
        fclose($pipes[0]);
        proc_close($process);
      }
      if(!$this->flags['dry-run']) {
        // I don't like sending passwords via command line but I get an error otherwise
        // It's not terrible though. The password has to be in a file in plaintext anyway
        $command = "mysql --user=$local_db_user --host=$local_db_host --password=$local_db_password --database=$local_db_name < " . __DIR__ . "/dump.sql";
        echo '<span class="line">' . $command . '</span>';
        $result = SendCommandWithPassword::exec($command, constant('DB_PASSWORD'));
        $sr = new \icit_srdb(array(
        'host' => constant('DB_HOST'),
        'user' => constant('DB_USER'),
        'pass' => constant('DB_PASSWORD'),
        'name' => constant('DB_NAME'),
        'dry_run' => false
        ));
        $siteurl = get_option( 'siteurl' );
        $searchreplaces = array($url => $siteurl, untrailingslashit( $path ) => untrailingslashit( ABSPATH ));

        foreach($searchreplaces as $search => $replace) {
          echo '<span class="line">' . "Replacing $search with $replace" . '</span>';
          $replacements = $sr->replacer($search, $replace);
        }

        $active_plugins = get_option('active_plugins');

        $active_plugins[] = "wp-deploy-flow/deploy.php";

        update_option('active_plugins', $active_plugins);

        // Keep the environments from before
        $environments = esc_sql(serialize($environments));

        $command = "mysql -u $local_db_user -p$local_db_password $local_db_name -e '" . "REPLACE into {$wpdb->prefix}options (option_id, option_name, option_value) values($option_id, \"deploy_environments\", \"$environments\")" . "'";

        echo $command;

        echo shell_exec($command);

      }
    }

    protected function _transfer_files () {
      extract( $this->params );

      $dir = wp_upload_dir();
      $dist_path  = constant( API::config_constant( 'path' ) ) . '/';
      $remote_path = $dist_path;
      $local_path = ABSPATH;

      $excludes = array_merge(
      $excludes,
      API::$excludes
      );

      if(!$ssh_host) {
        // in case the source env is in a subfolder of the destination env, we exclude the relative path to the source to avoid infinite loop
        $remote_local_path = realpath($local_path);
        if($remote_local_path) {
          $remote_path = realpath($remote_path);
          $remote_local_path = str_replace($remote_path . '/', '', $remote_local_path);
          $excludes[]= $remote_local_path;
        }
      }

      $excludes = array_reduce( $excludes, function($acc, $value) { if($value) {$acc.= "--exclude \"$value\" ";} return $acc; } );

      $rsync_flags = $this->flags['dry-run'] ? '-avzn' : '-avz';

      if ( $ssh_host ) {
        $rsync_command = "rsync $rsync_flags -e 'ssh -p $ssh_port' $ssh_user@$ssh_host:$remote_path. $local_path $excludes";
      } else {
        $rsync_command = "rsync $rsync_flags $remote_path/. $local_path $excludes";
      }

      echo "<span class='line'>executing `$rsync_command`</span>";

      echo exec($rsync_command);

      flush_rewrite_rules();
    }
  }

}
