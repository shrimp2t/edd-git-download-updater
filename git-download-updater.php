<?php
/*
Plugin Name: Easy Digital Downloads - Git Update Downloads
Plugin URI: http://famethemes.com
Description: Update Download files and changelog directly from GitHub
Version: 99.00.01
Author: FameThemes, Shrimp2t
Author URI: http://famethemes.com
*/

if ( ! defined( 'EDD_GIT_PLUGIN_DIR' ) ) {
	define( 'EDD_GIT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'EDD_GIT_PLUGIN_URL' ) ) {
	define( 'EDD_GIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'EDD_GIT_VERSION' ) ) {
	define( 'EDD_GIT_VERSION', '1.0.4' );
}

class EDD_GIT_Download_Updater {

	/*
	 * Store our git Username. This will be used to login to the desired repo.
	 */
	public $username;

	/*
	 * Store our git Password. This wil be used to login to the desired repo.
	 */
	public $password;

	/*
	 * Store our git repo name
	 */
	public $git_repo;

	/*
	 * Store our desired version #.
	 */
	public $version;

	/*
	 * Store our download's "version" number if Licensing is installed
	 */
	public $sl_version;

	/*
	 * Store our git Repo URL
	 */
	public $url;

	/*
	 * Store our destination filename
	 */
	public $file_name;

	/*
	 * Store our temporary dir name
	 */
	public $tmp_dir;

	/*
	 * Store our newly unzipped folder name
	 */
	public $sub_dir;

	/*
	 * Store the id of the download we're updating
	 */
	public $download_id;

	/*
	 * Store our EDD upload dir information
	 */
	public $edd_dir;

	/*
	 * Store the current file key for our download
	 */
	public $file_key;

	/*
	 * Store our errors
	 */
	public $errors;

	/*
	 * Store our folder name
	 */
	public $folder_name;

	/*
	 * Store our source (either bitbucket or github)
	 */
	public $source;

	/*
	 * Store our changelog
	 */
	public $changelog = '';

	/*
	 * Get things up and running.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function __construct() {
		global $post;

		// Bail if the zip extension hasn't been loaded.
		if ( ! class_exists('ZipArchive') ) {
			$this->register_zip_archive_notice();
			return false;
		}

		// Include our ajax class.
		require_once( EDD_GIT_PLUGIN_DIR . 'classes/ajax.php' );
		$this->ajax = new EDD_GIT_Download_Updater_Ajax( $this );

		// Include our admin class.
		require_once( EDD_GIT_PLUGIN_DIR . 'classes/admin.php' );
		$this->admin = new EDD_GIT_Download_Updater_Admin( $this );

		// Include our file processing class.
		require_once( EDD_GIT_PLUGIN_DIR . 'classes/process-file.php' );
		$this->process_file = new EDD_GIT_Download_Updater_Process_File( $this );

		// Include our repo interaction class.
		require_once( EDD_GIT_PLUGIN_DIR . 'classes/repos.php' );
		$this->repos = new EDD_GIT_Download_Updater_Repos( $this );

		if ( class_exists( 'EDD_License' ) ) {
			$license = new EDD_License( __FILE__, 'Git Download Updater', EDD_GIT_VERSION, 'WP Ninjas' );
		}

		add_action( 'init', array( $this, 'trigger_webhook' ) );
	}

	function trigger_webhook(){
        if ( isset( $_GET['git_act'] ) && $_GET['git_act'] == 'fetch_release' ) {
            $id = isset($_GET['id']) ? absint($_GET['id']) : false;
            $nonce = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : false;
            $this->webhook_fetch( $id, $nonce );
        }
    }
    /**
     * @see https://developer.github.com/v3/activity/events/types/#releaseevent
     *
     * @param $id
     * @param $nonce
     */
    function webhook_fetch( $id, $nonce  ){

        $post = get_post( $id );
        if ( ! $post || get_post_type( $post ) != 'download' ) {
            wp_send_json_error( 'No item found.' );
        }

        $_nonce =  get_post_meta( $post->ID, '_git_download_updater_nonce', true );
        if ( ! $nonce ||  $nonce != $_nonce ) {
            wp_send_json_error( 'Security check' );
        }

        $github_data = false;
        if ( isset( $_POST['payload'] ) ) {
            $github_data = json_decode( wp_unslash( $_POST['payload'] ), true );
        }

        if (  ! $github_data || ! isset( $github_data['action'] ) || $github_data['action'] != 'published' ) {
            wp_send_json_error( 'No action' );
        }

        if ( ! isset( $github_data['release'] ) ||  ! is_array( $github_data['release'] )  ) {
            wp_send_json_error( 'No release found.' );
        }

        $release = wp_parse_args( $github_data['release'], array(
            'tag_name' => '',
            'zipball_url' => '',
        ) );

        $repository = wp_parse_args( $github_data['repository'], array(
            'name' =>   '',
            'full_name' => '',
            'html_url'  => '',
        ) );


        //----------------------------
        $post_id = $post->ID;
        $file_id = isset( $_GET['file_id'] ) ? absint( $_GET['file_id'] ) : 0;
        $files  = get_post_meta( $post_id, 'edd_download_files', true );
        if ( ! is_array( $files ) ) {
            $files = array();
        }

        $current_file_data = isset( $files[ $file_id ] ) ? $files[ $file_id ] : array();
        $current_file_data = wp_parse_args( $current_file_data, array(
            'attachment_id' => '',
            'file' => '',
            'git_url' =>  '',
            'git_version' => '',
            'name' => '',
            'git_folder_name' => '',
            'condition' => ''
        ) );

        $version =  $release['tag_name'];
        $repo_url = $repository['html_url'];
        $key = $file_id;
        $this->condition = isset ( $current_file_data['condition'] ) ? $current_file_data['condition'] : 'all';

        $folder_name = '';
        $file_name   = '';

        $new_zip = $this->process_file->process( $post_id, $version, $repo_url, $key, $folder_name, $file_name );
        $file_name = empty( $file_name ) ? basename( $new_zip['path'] ) : $file_name;

        $current_file_data['file']          = $new_zip['url'];
        $current_file_data['git_version']   = $release['tag_name'];
        $current_file_data['name']          = $file_name;
        $current_file_data['attachment_id'] = 0;

        $files[ $file_id ] = $current_file_data;
        update_post_meta( $post_id, 'edd_download_files',  $files );

        update_post_meta( $post_id, '_edd_sl_version', $this->sl_version );
        if ( $this->changelog ) {
            update_post_meta( $post_id, '_edd_sl_changelog', $this->changelog );
        }

        wp_send_json_success( array(
            'current' => $current_file_data,
            'version' =>  $this->sl_version,
            'changelog' =>  $this->changelog,
        ) );

        die();
    }

	/**
	 * Register ZipArchive notice
	 *
	 * @since  1.0.3
	 * @return void
	 */
	private function register_zip_archive_notice() {
		add_action( 'admin_notices', array( $this, 'display_zip_archive_notice' ) );
	}

	/**
	 * Display an admin notice if the ZipArchive class is not available
	 *
	 * @since  1.0.3
	 * @return void
	 */
	public function display_zip_archive_notice() {
		?>
		<div class="notice notice-error">
			<p><?php _e( 'The ZipArchive PHP class is required to use the Easy Digital Downloads - Git Update Downloads extension.', 'edd-git' ); ?></p>
		</div>
		<?php
	}

} // End EDD_GIT_Download_Updater class

// Get the download updater class started
function edd_git_download_updater() {
	$EDD_GIT_Download_Updater = new EDD_GIT_Download_Updater();
}

// Instantiate our main class
add_action( 'plugins_loaded', 'edd_git_download_updater', 99 );
