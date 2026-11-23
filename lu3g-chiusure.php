<?php
/**
 * Plugin Name:       LU3G Chiusure
 * Plugin URI:        https://github.com/lu3gofficial/lu3g-chiusure
 * Description:       Chiusure dell'attività (estive, natalizie, Pasqua, ponti, straordinarie) mostrate ai visitatori con un popup leggero, compatibile con la cache delle pagine, e nei dati strutturati di Google.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            LU3G Agenzia Web
 * Author URI:        https://lu3g.it
 * License:           GPL-2.0-or-later
 * Text Domain:       lu3g-chiusure
 * Domain Path:       /languages
 * Update URI:        https://github.com/lu3gofficial/lu3g-chiusure
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LU3G_CHIUSURE_VERSION', '1.0.0' );
define( 'LU3G_CHIUSURE_FILE', __FILE__ );
define( 'LU3G_CHIUSURE_PATH', plugin_dir_path( __FILE__ ) );
define( 'LU3G_CHIUSURE_URL', plugin_dir_url( __FILE__ ) );

// Aggiornamenti da GitHub (file generato da lu3gofficial/lu3g-updater, non modificarlo a mano).
require_once LU3G_CHIUSURE_PATH . 'includes/class-updater.php';
LU3G_Chiusure_Updater::init( __FILE__ );

// Avvio: caricamento automatico delle classi e aggancio degli hook.
require_once LU3G_CHIUSURE_PATH . 'includes/class-plugin.php';
LU3G_Chiusure_Plugin::instance();
