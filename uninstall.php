<?php
/**
 * Disinstallazione pulita: rimuove le opzioni del plugin (anche in ogni sito di un Multisite).
 *
 * @package LU3G_Chiusure
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Cancella le opzioni del sito corrente.
 */
function lu3g_chiusure_uninstall_site() {
	delete_option( 'lu3g_chiusure_closures' );
	delete_option( 'lu3g_chiusure_settings' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $lu3g_chiusure_site ) {
		switch_to_blog( $lu3g_chiusure_site );
		lu3g_chiusure_uninstall_site();
		restore_current_blog();
	}
} else {
	lu3g_chiusure_uninstall_site();
}
