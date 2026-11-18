<?php
/**
 * Removes the plugin settings and the email log when the plugin is deleted from the Plugins screen.
 *
 * @package AGRSE_Staging_Guard
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'agrse_sg_settings' );
delete_option( 'agrse_sg_mail_log' );
