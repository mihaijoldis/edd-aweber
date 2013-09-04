<?php
/**
 * EDD Aweber class, extension of the EDD base newsletter classs
 *
 * @copyright   Copyright (c) 2013, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0
*/

class EDD_Aweber extends EDD_Newsletter {

	/**
	 * Sets up the checkout label
	 */
	public function init() {
		global $edd_options;
		if( ! empty( $edd_options['edd_aweb_label'] ) ) {
			$this->checkout_label = trim( $edd_options['edd_aweb_label'] );
		} else {
			$this->checkout_label = 'Signup for the newsletter';
		}

	}

	/**
	 * Retrieves the lists from Mail Chimp
	 */
	public function get_lists() {

		global $edd_options;


		$lists = array();

		$aweber = $this->get_authenticated_instance();

		if ( ! is_object( $aweber ) || false === ( $secrets = get_option( 'aweber_secrets' ) ) )
			return $lists;

		$account = $aweber->getAccount( $secrets['access_key'], $secrets['access_secret'] );

		foreach ( $account->lists as $list ) {
			$lists[$list->id] = $list->name;
		}

		return (array)$lists;
	}

	/**
	 * Registers the plugin settings
	 */
	public function settings( $settings ) {

		$edd_aweb_settings = array(
			array(
				'id'   => 'edd_aweb_settings',
				'name' => '<strong>' . __( 'AWeber Settings', 'edd' ) . '</strong>',
				'desc' => __( 'Configure AWeber Integration Settings', 'edd' ),
				'type' => 'header'
			),
			array(
				'id'   => 'edd_aweb_api',
				'name' => __( 'AWeber Authorization Code', 'edd'),
				'desc' => sprintf( __( 'Enter your <a target="_new" title="Will open new window" href="%s">AWeber Authorization Code</a>', 'edd' ), 'https://auth.aweber.com/1.0/oauth/authorize_app/b4882144' ),
				'type' => 'text',
				'size' => 'regular'
			),
			array(
				'id'      => 'edd_aweb_checkout_signup',
				'name'    => __( 'Show Signup on Checkout', 'edd' ),
				'desc'    => __( 'Allow customers to signup for the list selected below during checkout?', 'edd' ),
				'type'    => 'checkbox'
			),
			array(
				'id'      => 'edd_aweb_list',
				'name'    => __( 'Choose a list', 'edd' ),
				'desc'    => __( 'Select the list you wish to subscribe buyers to', 'edd' ),
				'type'    => 'select',
				'options' => $this->get_lists()
			),
			array(
				'id'   => 'edd_aweb_label',
				'name' => __( 'Checkout Label', 'edd' ),
				'desc' => __( 'This is the text shown next to the signup option', 'edd' ),
				'type' => 'text',
				'size' => 'regular'
			)
		);

		return array_merge( $settings, $edd_aweb_settings );
	}

	/**
	 * Determines if the checkout signup option should be displayed
	 */
	public function show_checkout_signup() {
		global $edd_options;

		return ! empty( $edd_options['edd_aweb_checkout_signup'] );
	}

	/**
	 * Subscribe an email to a list
	 */
	public function subscribe_email( $user_info = array(), $list_id = false ) {

		global $edd_options;

		// Retrieve the global list ID if none is provided
		if( ! $list_id ) {
			$list_id = ! empty( $edd_options['edd_aweb_list'] ) ? $edd_options['edd_aweb_list'] : false;
			if( ! $list_id ) {
				return false;
			}
		}

		$authorization_code = isset( $edd_options['edd_aweb_api'] ) ? trim( $edd_options['edd_aweb_api'] ) : '';

		if ( strlen( $authorization_code ) > 0 ) {

			if( ! class_exists( 'AWeberAPI' ) ) {
				require_once( EDD_AWEBER_PATH . '/aweber/aweber_api.php' );
			}

			$aweber = $this->get_authenticated_instance();

			if ( ! is_object( $aweber ) || false === ( $secrets = get_option( 'aweber_secrets' ) ) )
				return false;

			try {
				$account = $aweber->getAccount( $secrets['access_key'], $secrets['access_secret'] );
				$listURL = "/accounts/{$account->id}/lists/{$list_id}";
				$list    = $account->loadFromUrl( $listURL );

				# create a subscriber
				$params         = array( 'email' => $user_info['email'] );
				$subscribers    = $list->subscribers;
				$new_subscriber = $subscribers->create( $params );

				# success!
				return true;

			} catch ( AWeberAPIException $exc ) {
				return false;
			}

		}


		return false;

	}

	public function get_authenticated_instance() {
		global $edd_options;

		$authorization_code = isset( $edd_options['edd_aweb_api'] ) ? trim( $edd_options['edd_aweb_api'] ) : '';

		$msg = '';
		if ( ! empty( $authorization_code ) ) {

			if( ! class_exists( 'AWeberAPI' ) ) {
				require_once( EDD_AWEBER_PATH . '/aweber/aweber_api.php' );
			}

			$error_code = "";

			if ( false !== get_option( 'aweber_secrets' ) ) {
				$options = get_option( 'aweber_secrets' );
				$msg = $options;
				return new AWeberAPI( $options['consumer_key'], $options['consumer_secret'] );
			} else {
				try {
					list( $consumer_key, $consumer_secret, $access_key, $access_secret ) = AWeberAPI::getDataFromAweberID( $authorization_code );
				} catch (AWeberAPIException $exc) {
					list( $consumer_key, $consumer_secret, $access_key, $access_secret ) = null;
					# make error messages customer friendly.
					$descr = $exc->message;
					$descr = preg_replace( '/http.*$/i', '', $descr );     # strip labs.aweber.com documentation url from error message
					$descr = preg_replace( '/[\.\!:]+.*$/i', '', $descr ); # strip anything following a . : or ! character
					$error_code = " ($descr)";
				} catch ( AWeberOAuthDataMissing $exc ) {
					list( $consumer_key, $consumer_secret, $access_key, $access_secret ) = null;
				} catch ( AWeberException $exc ) {
					list( $consumer_key, $consumer_secret, $access_key, $access_secret ) = null;
				}

				if ( ! $access_secret ) {
					$msg =  '<div id="aweber_access_token_failed" class="error">';
					$msg .= "Unable to connect to your AWeber Account$error_code:<br />";

					# show oauth_id if it failed and an api exception was not raised
					if ( empty( $error_code ) ) {
						$msg .= "Authorization code entered was: $authorization_code <br />";
					}

					$msg .= "Please make sure you entered the complete authorization code and try again.</div>";

					} else {
						$secrets = array(
							'consumer_key'    => $consumer_key,
							'consumer_secret' => $consumer_secret,
							'access_key'      => $access_key,
							'access_secret'   => $access_secret,
						);

				   update_option( 'aweber_secrets', $secrets );
				 }
			}
		} else {
			delete_option( 'aweber_secrets' );
		}

		$msg = isset( $msg ) ? $msg : $pluginAdminOptions;

		update_option( 'aweber_response', $msg );

	}

}