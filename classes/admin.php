<?php
/**
 * Outputs our admin page HTML
 *
 * @package EDD Git Downloader
 * @since  1.0
 */

class EDD_GIT_Download_Updater_Admin
{
	var $instance = '';

	function __construct( $instance )
	{

		$this->instance = $instance;
		 // Add our settings to the EDD Extensions tab
		add_filter( 'edd_settings_sections_extensions', array( $this, 'register_subsection' )     );
		add_filter( 'edd_settings_extensions'         , array( $this, 'edd_extensions_settings' ) );


		add_filter( 'sanitize_post_meta_edd_download_files' , array( $this, 'sanitize_post_meta_edd_download_files' ), 35, 3 );
		add_filter( 'edd_file_row_args' , array( $this, 'edd_file_row_args' ), 35, 2 );
		// Add our init action that adds/removes git download boxes.
		add_action( 'admin_head', array( $this, 'init' ) );

		// Save our Use Git setting.
		add_action( 'save_post', array( $this, 'save_post' ), 9999 );

		/* GitHub */

		// Add our GitHub description hook.
		add_action( 'edd_git_gh_desc', array( $this, 'gh_desc' ) );

		// Add our GitHub Authorization button hook.
		add_action( 'edd_git_gh_authorize_button', array( $this, 'gh_authorize_button' ) );

		// Add our JS to the EDD Extensions settings page.
		add_action( 'admin_print_scripts-post.php', array( $this, 'admin_js' ) );
		add_action( 'admin_print_scripts-post-new.php', array( $this, 'admin_js' ) );

		// Add our CSS to the EDD Extensions settings page.
		add_action( 'admin_print_styles-post.php', array( $this, 'admin_css' ) );
		add_action( 'admin_print_styles-post-new.php', array( $this, 'admin_css' ) );

		/* BitBucket */

		// Add our BitBucket description hook.
		add_action( 'edd_git_bb_desc', array( $this, 'bb_desc' ) );

		// Add webhook url
        add_action( 'edd_meta_box_files_fields', array( $this, 'webhook_info' ), 35 );
	}

	function webhook_info( $post_id ){

	    $nonce = get_post_meta( $post_id, '_git_download_updater_nonce', true );
	    if ( ! $nonce ) {
	        $nonce = wp_create_nonce( '_git_download_updater_nonce' );
	        update_post_meta( $post_id, '_git_download_updater_nonce', $nonce );
        }

        $url = home_url('/');
	    $url = add_query_arg( array( 'git_act' => 'fetch_release', 'nonce' => $nonce, 'id' => $post_id, 'file_id' => '' ), $url );
        ?>
        <p>
            <strong>Github Webhoook</strong><br/>
            <span class="description">You can set <code>file_id</code> to any file id you want to fetch, default 0.</span><br/>
            <input  class="widefat" autocomplete="off" value="<?php echo esc_url( $url ); ?>" readonly size="50" type="text">
        </p>
        <?php
    }

	function edd_file_row_args( $args, $old_args = array() ){
        $old_args = wp_parse_args( $old_args, array(
            'index'              => null,
            'name'              => null,
            'file'              => null,
            'condition'         => null,
            'attachment_id'     => null,
            'thumbnail_size'     => null,
            'git_url'           => null,
            'git_folder_name'   => null,
            'git_version'       => null
        ) );

        return array_merge( $old_args, $args );
    }

	/**
	 * Register the Git Updater subsection
	 *
	 * @since  1.0.3
	 * @param  array $sections The Existing subsections in the Extensions tab
	 * @return array           Sections with our subsection added
	 */
	public function register_subsection( $sections ) {

		// Note the array key here of 'ck-settings'
		$sections['edd-git'] = __( 'Git Updater', 'example-edd-extension' );

		return $sections;

	}

	/*
	 * Add our default git settings to the Extensions tab
	 *
	 * @since 1.0
	 * @return array $extensions
	 */

	public function edd_extensions_settings( $extensions ) {

		$this->admin_js();

		$git_settings = array (
			'gh_begin' => array(
				'id'    => 'gh_begin',
				'name'  => __( 'GitHub Updater', 'edd-git' ),
				'desc'  => '',
				'type'  => 'header',
				'std'   => '',
			),

			'gh_desc' => array(
				'id'    => 'git_gh_desc',
				'name'  => '',
				'desc'  => '',
				'type'  => 'hook',
				'std'   => '',
			),
			'gh_clientid' => array(
				'id'    => 'gh_clientid',
				'name'  => __( 'Client ID', 'edd-git' ),
				'desc'  => '',
				'type'  => 'text',
				'std'   => ''
			),
			'gh_clientsecret' => array(
				'id'    => 'gh_clientsecret',
				'name'  => __( 'Client Secret', 'edd-git' ),
				'desc'  => '',
				'type'  => 'text',
				'std'   => ''
			),
			'gh_authorize_button' => array(
				'id'    => 'git_gh_authorize_button',
				'name'  => '',
				'desc'  => '',
				'type'  => 'hook',
				'std'   => ''
			),
			'bb_begin' => array(
				'id'    => 'bb_begin',
				'name'  => __( 'BitBucket Updater', 'edd-git' ),
				'desc'  => '',
				'type'  => 'header',
				'std'   => '',
			),
			'bb_desc' => array(
				'id'    => 'git_bb_desc',
				'name'  => '',
				'desc'  => '',
				'type'  => 'hook',
				'std'   => '',
			),
		);


		if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
			// Use the previously noted array key as an array key again and next your settings
			$git_settings = array( 'edd-git' => $git_settings );
		}

		return array_merge( $extensions, $git_settings );

	}

	/**
	 * Check to see if our field metabox sections should be removed.
	 * @since  1.0
	 * @return void
	 */
	public function init() {
        $this->register_git_section();
	}

	function register_git_section () {
        add_action( 'edd_download_file_table_row', array( $this, 'add_github_repo' ), 15, 3 );
	}

	function  add_github_repo( $post_id, $key, $args ){
        $edd_settings = get_option( 'edd_settings' );
        $gh_access_token = isset ( $edd_settings['gh_access_token'] ) ? $edd_settings['gh_access_token'] : '';

        if ( empty ( $gh_access_token ) ) {
            return ;
        }

        $defaults = array(
            'name'              => null,
            'file'              => null,
            'condition'         => null,
            'attachment_id'     => null,
            'git_url'           => null,
            'git_folder_name'   => null,
            'git_version'       => null
        );
        $args  = wp_parse_args( $args, $defaults );
        $repos = $this->instance->repos->fetch_repos( );
        echo '<div class="edd_git_wrapper">';
        echo '<select class="git-repo" name="edd_download_files['.$key.'][git_url]">';
	        $this->output_repo_options( $repos, $args['git_url'] );
        echo '</select>';
        ?>
        <select name="edd_download_files[<?php echo $key; ?>][git_version]" style="min-width:125px; display: none;" class="git-tag">
            <?php if ( $args['git_url'] ) { ?>
            <option value="<?php echo esc_attr( $args['git_version'] ); ?>"><?php echo esc_html( $args['git_version'] ); ?></option>
            <?php } ?>
        </select>
        <button type="button" class="git-reload-tags button button-secondary dashicons dashicons-update"></button>
        <span class="spinner"></span>
        <button class="edd_git_load_file button button-secondary" style="display: none;"  type="button">Fetch</button>
        <?php
        echo '</div>';
    }

    function sanitize_post_meta_edd_download_files( $files, $met_id, $type = 'post' ){
        return $files;
    }

	 /*
	 * Include our JS.
	 *
	 * @since 1.1
	 * @return void
	 */

	public function admin_js() {
		global $pagenow, $post, $typenow;

		if ( ( 'edit.php' == $pagenow && isset ( $_REQUEST['page'] ) && 'edd-settings' == $_REQUEST['page'] && isset ( $_REQUEST['tab'] ) && 'extensions' == $_REQUEST['tab'] ) || 'download' == $typenow ) {
			if ( is_object( $post ) ) {
				$post_id = $post->ID;
			} else {
				$post_id = '';
			}
			wp_enqueue_script( 'jquery-select2', EDD_GIT_PLUGIN_URL . 'assets/js/select2.min.js', array( 'jquery' ) );
			wp_enqueue_script( 'edd-git-updater', EDD_GIT_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ) );
			wp_localize_script( 'edd-git-updater', 'gitUpdater', array( 'pluginURL' => EDD_GIT_PLUGIN_URL, 'useGit' => get_post_meta( $post_id, '_edd_download_use_git', true ), 'currentGitUrl' => $this->instance->repos->get_current_repo_url( $post_id ), 'currentTag' => $this->instance->repos->get_current_tag( $post_id ) ) );
		}
	}

	/*
	 * Include our CSS.
	 *
	 * @since 1.1
	 * @return void
	 */

	public function admin_css() {
		global $pagenow, $typenow;

		if ( ( 'edit.php' == $pagenow && isset ( $_REQUEST['page'] ) && 'edd-settings' == $_REQUEST['page'] && isset ( $_REQUEST['tab'] ) && 'extensions' == $_REQUEST['tab'] ) || 'download' == $typenow ) {
			wp_enqueue_style( 'jquery-select2', EDD_GIT_PLUGIN_URL . 'assets/css/select2.min.css' );
			wp_enqueue_style( 'edd-git-updater', EDD_GIT_PLUGIN_URL . 'assets/css/admin.css' );
		}
	}

	/*
	 * Output our GitHub Authorize Button
	 *
	 * @since 1.1
	 * @return void
	 */

	public function gh_authorize_button() {
		$edd_settings = get_option( 'edd_settings' );
		$gh_access_token = isset ( $edd_settings['gh_access_token'] ) ? $edd_settings['gh_access_token'] : '';

		if ( ! empty ( $gh_access_token ) ) {
			$connected = '';
			$disconnected = 'display:none;';
		} else {
			$connected = 'display:none;';
			$disconnected = '';
		}

		$html = '<a href="#" style="display:block;float:left;' . $connected . '" id="edd-github-disconnect" class="button-secondary edd-git-github-connected">' . __( 'Disconnect From GitHub', 'edd-git' ) . '</a>';
		$html .= '<a href="#" style="display:block;float:left;' . $disconnected .' " id="edd-github-auth" class="button-secondary edd-git-github-disconnected">' . __( 'Authorize With GitHub', 'edd-git' ) .'</a>';
		$html .= '<span style="float:left" class="spinner" id="edd-git-github-spinner"></span>';
		echo $html;
	}

	/*
	 * Display our BitBucket description
	 *
	 * @since 1.1
	 * @return void
	 */
	public function bb_desc() {

		if ( ! current_user_can( 'manage_options') ) {
			return;
		}

		$html = '<p>Currently, BitBucket does not support downloading files via OAuth.</p>
		<p class="howto">There is a <a href="https://bitbucket.org/site/master/issue/7592/download-source-from-private-repo-via-api">feature request thread</a> asking BitBucket to include this ability.</p>
		<p>The lack of OAuth support means that we are forced to use basic authentication using a username and password defined within your wp-config.php file.</p>
		<br>';

		if ( defined( 'EDD_GIT_BB_USER' ) && defined( 'EDD_GIT_BB_PASSWORD' ) ) {
			$html .= '<p>Username: ' . EDD_GIT_BB_USER .'</p><p>Pasword: **********</p>';
		} else {
			$html .= '<p>Please use the <code>EDD_GIT_BB_USER</code> and <code>EDD_GIT_BB_PASSWORD</code> constants within your wp-config.php file to set your BitBucket credentials.</p>';
		}

		echo $html;
	}

	/*
	 * Output our GitHub description text
	 *
	 * @since 1.1
	 * @return void
	 */

	public function gh_desc() {
		$edd_settings = edd_get_settings();
		$gh_access_token = isset ( $edd_settings['gh_access_token'] ) ? $edd_settings['gh_access_token'] : '';

		if ( ! empty ( $gh_access_token ) ) {
			$connected = '';
			$disconnected = 'style="display:none;"';
		} else {
			$connected = 'style="display:none;"';
			$disconnected = '';
		}

		$html = '<span class="edd-git-github-connected" ' . $connected . ' ><p>Connected to GitHub.</p></span>';
		$html .= '<span class="edd-git-github-disconnected" ' . $disconnected . ' ><p>Updating from private repositories requires a one-time application setup and authorization. These steps will not need to be repeated for other sites once you receive your access token.</p>
				<p>Follow these steps:</p>
				<ol>
					<li><a href="https://github.com/settings/applications/new" target="_blank">Create an application</a> with the <strong>Main URL</strong> and <strong>Callback URL</strong> both set to <code>' . get_bloginfo( 'url' ) . '</code></li>
					<li>Copy the <strong>Client ID</strong> and <strong>Client Secret</strong> from your <a href="https://github.com/settings/applications" target="_blank">application details</a> into the fields below.</li>
					<li>Authorize with GitHub.</li>
				</ol></span>';
		echo $html;
	}


	/**
	 * Take our repo array and return our HTML options
	 * @since  1.0
	 * @param  array  $repos
	 * @param  string  $current_repo URL of our current repo.
	 * @return void
	 */
	public function output_repo_options( $repos, $current_repo = '' ) {
		?>
		<option value="" data-slug=""><?php _e( 'Select a repo', 'edd-git' ); ?></option>
		<?php
		$owner = '';
		foreach ( $repos as $source => $rs ) {
			foreach ( $rs as $url => $repo ) {
				if ( is_int( $url ) ) {
				   if ( isset ( $repo['open'] ) ) {
					$owner = $repo['open'];
					?>
					<optgroup label="<?php echo $owner; ?>">
				   <?php
				   } else {
					?>
					</optgroup>
					<?php
				   }
				} else {
					?>
					<option value="<?php echo $url; ?>" <?php selected( $url, $current_repo ); ?> data-source="<?php echo $source; ?>" data-slug="<?php echo $repo; ?>"><?php echo $repo; ?></option>
					<?php
				}
			}
		}
	}

}
