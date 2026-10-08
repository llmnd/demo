<?php
/**
 * Plugin Name: Baykat Finance
 * Description: Module de gestion financière pour Baykat.
 * Version: 1.0.0
 * Author: Lamine
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BAYKAT_FINANCE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BAYKAT_FINANCE_URL', plugin_dir_url( __FILE__ ) );

require_once BAYKAT_FINANCE_DIR . 'includes/database.php';
require_once BAYKAT_FINANCE_DIR . 'includes/permissions.php';
require_once BAYKAT_FINANCE_DIR . 'includes/transactions.php';
require_once BAYKAT_FINANCE_DIR . 'includes/farm-data.php';
require_once BAYKAT_FINANCE_DIR . 'includes/components/media.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/finance-page.php';
require_once BAYKAT_FINANCE_DIR . 'includes/modules/dashboard.php';
require_once BAYKAT_FINANCE_DIR . 'includes/modules/parcelles.php';
require_once BAYKAT_FINANCE_DIR . 'includes/modules/cultures.php';
require_once BAYKAT_FINANCE_DIR . 'includes/modules/activites.php';
require_once BAYKAT_FINANCE_DIR . 'includes/modules/finances.php';
require_once BAYKAT_FINANCE_DIR . 'includes/modules/stocks.php';
require_once BAYKAT_FINANCE_DIR . 'includes/components/navigation.php';
require_once BAYKAT_FINANCE_DIR . 'includes/farm-page.php';
require_once BAYKAT_FINANCE_DIR . 'admin/dashboard.php';
register_activation_hook( __FILE__, 'baykat_finance_activate' );
add_action( 'plugins_loaded', 'baykat_finance_maybe_upgrade_database' );
