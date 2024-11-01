<?php

namespace WP_Deploy_Flow {

  use phpseclib\Net\SSH2;

  use phpseclib\Net\SCP;

  class Pusher {

    public function __construct($params, $flags) {
      $this->params = $params;
      $this->flags = $flags;
    }

    public function push () {
      if(!$this->flags['files-only']) {
        $this->_dump_database();
        if($this->params['ssh_db_host']) {
          $this->_import_database_through_ssh();
        } else {
          $this->_import_database_locally();
        }
        unlink(__DIR__ . '/dump.sql');
      }

      $this->_transfer_files();

      $this->_commands_post_push();
    }

    protected function _dump_database () {
      require "srdb.class.php";
      extract($this->params);
      $local_db_user = constant('DB_USER');
      $local_db_name = constant('DB_NAME');
      $local_db_host = constant('DB_HOST');
      $local_db_password = constant('DB_PASSWORD');

      $siteurl = get_option( 'siteurl' );
      $searchreplaces = array($siteurl => $url, untrailingslashit( ABSPATH ) => untrailingslashit( $path ));

      $command = "mysqldump -u $local_db_user -p$local_db_password $local_db_name > " . __DIR__ . "/db_bak.sql";

      echo "<span class='line'>" . $command . "</span>";

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

      $sr = new \icit_srdb(array(
      'host' => constant('DB_HOST'),
      'user' => constant('DB_USER'),
      'pass' => constant('DB_PASSWORD'),
      'name' => constant('DB_NAME'),
      'dry_run' => false
      ));
      $siteurl = get_option( 'siteurl' );
      $searchreplaces = array($siteurl => $url, untrailingslashit( ABSPATH ) => untrailingslashit( $path ));

      foreach($searchreplaces as $search => $replace) {
        $replacements = $sr->replacer($search, $replace);
      }

      $command = "mysqldump -u $local_db_user -p$local_db_password $local_db_name > " . __DIR__ . "/dump.sql";

      echo "<span class='line'>" . $command . "</span>";

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

      $command = "mysql --user=$local_db_user --host=$local_db_host --password=$local_db_password --database=$local_db_name < " . __DIR__ . "/db_bak.sql";

      echo "<span class='line'>" . $command . "</span>";
      $result = SendCommandWithPassword::exec($command, constant('DB_PASSWORD'));

    }

    protected function _import_database_through_ssh () {
      extract( $this->params );
      $db_password = str_replace(array("'", '"'), '', $db_password);
      $db_user = str_replace(array("'", '"'), '', $db_user);
      $db_host = str_replace(array("'", '"'), '', $db_host);
      $db_name = str_replace(array("'", '"'), '', $db_name);
      $ssh = new SSH2($ssh_host);
      if(!$ssh->login($ssh_db_user, $ssh_password)) {
        exit("Login failed with user $ssh_db_user and password $ssh_password");
      }
      $scp = new SCP($ssh);
      $scp->put("$ssh_db_path/dump.sql", __DIR__ . '/dump.sql', SCP::SOURCE_LOCAL_FILE);
      $command = "cd $ssh_db_path";
      echo "<span class='line'>SSH: " . $command . "</span>";
      $ssh->exec($command);
      $command = "mysql --user='$db_user' --password='$db_password' --host='$db_host' --database='$db_name' < '$ssh_db_path/dump.sql'";
      echo "<span class='line'>SSH: " . $command . "</span>";
      echo $ssh->exec($command);
      $command = "rm $ssh_db_path/dump.sql";
      echo "<span class='line'>SSH: " . $command . "</span>";
      $ssh->exec($command);
    }

    protected function _import_database_locally () {
      extract( $this->params );
      $command = "mysql --user=$db_user --password=$db_password --host=$db_host $db_name < " . __DIR__ . "/dump.sql";
      echo "<span class='line'>" . $command . "</span>";
    }

    protected function _transfer_files () {
      extract($this->params);
      $ssh = new SSH2($ssh_host);
      if(!$ssh->login($ssh_db_user, $ssh_password)) {
        exit("Login failed with user $ssh_db_user and password $ssh_password");
      }
      $remote_path = "$path/";
      $local_path = ABSPATH;
      $excludes = array_merge(
      $excludes,
      API::$excludes
      );
      if(!$ssh_host) {
        // in case the destination env is in a subfolder of the source env, we exclude the relative path to the destination to avoid infinite loop
        $local_remote_path = realpath($remote_path);
        if($local_remote_path) {
          $local_path = realpath($local_path) . '/';
          $local_remote_path = str_replace($local_path . '/', '', $local_remote_path);
          $excludes[]= $local_remote_path;
          $remote_path = realpath($remote_path). '/';
        }
      }

      $excludes = array_reduce( $excludes, function($acc, $value) { if($value) {$acc.= "--exclude \"$value\" ";} return $acc; } );

      $rsync_flags = $this->flags['dry-run'] ? '-avzn' : '-avz';

      if ( $ssh_host ) {
        $command = "rsync -avz -e 'ssh -p $ssh_port' --chmod=Du=rwx,Dg=rx,Do=rx,Fu=rw,Fg=r,Fo=r $local_path $ssh_user@$ssh_host:$remote_path $excludes";
      } else {
        $command = "rsync -avz $local_path $remote_path $excludes";
      }
      echo "<span class='line'>" . $command . "</span>";
      echo exec($command);
      $command = "cd $ssh_db_path";
      echo "<span class='line'>" . $command . "</span>";
      $ssh->exec($command);
      $command = "chown -R $path_owner:$path_owner $ssh_db_path";
      echo "<span class='line'>" . $command . "</span>";
      echo $ssh->exec($command);
      $command = "wp rewrite flush --allow-root";
      echo "<span class='line'>" . $command . "</span>";
      $ssh->exec($command);
    }



    protected function _commands_post_push() {
      extract( $this->params );
      $const = strtoupper( $env ) . '_POST_SCRIPT';
      if ( defined( $const ) ) {
        $ssh = new SSH2($ssh_host);
        if(!$ssh->login($ssh_db_user, $ssh_password)) {
          exit("Login failed with user $ssh_db_user and password $ssh_password");
        }
        $subcommand = constant( $const );
        $ssh->exec($subcommand);
      }
    }

  }

}
