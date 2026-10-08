<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BAYKAT_FINANCE_DB_VERSION', '4' );

/**
 * Retourne le nom de la table des transactions.
 */
function baykat_finance_get_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'baykat_transactions';
}

/**
 * Crée la table des transactions lors de l'activation du plugin.
 */
function baykat_finance_activate() {
    global $wpdb;

    $table_name      = baykat_finance_get_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = array(
        "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        type VARCHAR(20) NOT NULL,
        category VARCHAR(30) NOT NULL DEFAULT 'general',
        amount DECIMAL(15,2) NOT NULL,
        description TEXT NULL,
        item_name VARCHAR(190) NULL,
        quantity DECIMAL(15,3) NULL,
        unit VARCHAR(40) NULL,
        farm_id BIGINT UNSIGNED NULL,
        parcel_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY farm_id (farm_id),
        KEY parcel_id (parcel_id)
    ) $charset_collate;",
    );

    $farms_table = $wpdb->prefix . 'baykat_farms';
    $parcels_table = $wpdb->prefix . 'baykat_parcels';
    $crops_table = $wpdb->prefix . 'baykat_crops';
    $activities_table = $wpdb->prefix . 'baykat_activities';

    $sql[] = "CREATE TABLE $farms_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(190) NOT NULL,
        location VARCHAR(190) NULL,
        area_ha DECIMAL(12,3) NULL,
        image_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE $parcels_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        farm_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(190) NOT NULL,
        area_m2 DECIMAL(12,2) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'planned',
        image_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY farm_id (farm_id)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE $crops_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        parcel_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(190) NOT NULL,
        variety VARCHAR(190) NULL,
        planted_at DATE NULL,
        expected_harvest_date DATE NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'growing',
        image_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY parcel_id (parcel_id)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE $activities_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        parcel_id BIGINT UNSIGNED NOT NULL,
        title VARCHAR(190) NOT NULL,
        activity_type VARCHAR(30) NOT NULL,
        activity_date DATE NOT NULL,
        notes TEXT NULL,
        image_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY parcel_id (parcel_id),
        KEY activity_date (activity_date)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    foreach ( $sql as $table_sql ) {
        dbDelta( $table_sql );
    }

    $required_columns = array(
        $table_name       => array( 'category', 'item_name', 'quantity', 'farm_id', 'parcel_id' ),
        $farms_table      => array( 'user_id', 'name', 'location', 'area_ha', 'image_id' ),
        $parcels_table    => array( 'user_id', 'farm_id', 'name', 'area_m2', 'status', 'image_id' ),
        $crops_table      => array( 'user_id', 'parcel_id', 'name', 'variety', 'planted_at', 'expected_harvest_date', 'status', 'image_id' ),
        $activities_table => array( 'user_id', 'parcel_id', 'title', 'activity_type', 'activity_date', 'notes', 'image_id' ),
    );

    foreach ( $required_columns as $table => $columns ) {
        $existing_table = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
        );

        if ( $existing_table !== $table ) {
            error_log( 'Baykat Finance: database schema migration did not complete; it will retry on a later request.' );
            return;
        }

        $existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM $table", 0 );

        if ( array_diff( $columns, (array) $existing_columns ) ) {
            error_log( 'Baykat Finance: database schema migration did not complete; it will retry on a later request.' );
            return;
        }
    }

    update_option( 'baykat_finance_db_version', BAYKAT_FINANCE_DB_VERSION );
}

/**
 * Applique le schéma courant aux installations déjà actives.
 */
function baykat_finance_maybe_upgrade_database() {
    if ( BAYKAT_FINANCE_DB_VERSION !== get_option( 'baykat_finance_db_version' ) ) {
        baykat_finance_activate();
    }
}
