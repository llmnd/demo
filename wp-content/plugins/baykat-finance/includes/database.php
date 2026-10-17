<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BAYKAT_FINANCE_DB_VERSION', '10' );

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
        expense_category VARCHAR(30) NOT NULL DEFAULT 'general',
        farm_id BIGINT UNSIGNED NULL,
        parcel_id BIGINT UNSIGNED NULL,
        activity_id BIGINT UNSIGNED NULL,
        crop_id BIGINT UNSIGNED NULL,
        cancelled_at DATETIME NULL,
        cancelled_by BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY farm_id (farm_id),
        KEY parcel_id (parcel_id),
        KEY activity_id (activity_id),
        KEY crop_id (crop_id)
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
        latitude DECIMAL(10,7) NULL,
        longitude DECIMAL(10,7) NULL,
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
        boundary LONGTEXT NULL,
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
        harvested_quantity DECIMAL(15,3) NULL,
        harvested_unit VARCHAR(40) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'growing',
        image_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY parcel_id (parcel_id)
    ) $charset_collate;";

    $members_table = $wpdb->prefix . 'baykat_farm_members';
    $sql[] = "CREATE TABLE $members_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        farm_id BIGINT UNSIGNED NOT NULL,
        owner_user_id BIGINT UNSIGNED NOT NULL,
        member_user_id BIGINT UNSIGNED NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'reader',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY farm_member (farm_id, member_user_id),
        KEY owner_user_id (owner_user_id),
        KEY member_user_id (member_user_id)
    ) $charset_collate;";

    $stock_movements_table = $wpdb->prefix . 'baykat_stock_movements';
    $harvests_table = $wpdb->prefix . 'baykat_harvests';
    $sql[] = "CREATE TABLE $stock_movements_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        farm_id BIGINT UNSIGNED NOT NULL,
        parcel_id BIGINT UNSIGNED NULL,
        destination_farm_id BIGINT UNSIGNED NULL,
        destination_parcel_id BIGINT UNSIGNED NULL,
        crop_id BIGINT UNSIGNED NULL,
        activity_id BIGINT UNSIGNED NULL,
        movement_type VARCHAR(30) NOT NULL,
        item_name VARCHAR(190) NOT NULL,
        quantity DECIMAL(15,3) NOT NULL,
        unit VARCHAR(40) NOT NULL,
        notes TEXT NULL,
        movement_date DATE NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY farm_id (farm_id),
        KEY parcel_id (parcel_id),
        KEY crop_id (crop_id),
        KEY movement_date (movement_date)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE $harvests_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        crop_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        recorded_by BIGINT UNSIGNED NOT NULL,
        quantity DECIMAL(15,3) NOT NULL,
        unit VARCHAR(40) NOT NULL,
        harvest_date DATE NULL,
        notes TEXT NULL,
        legacy_key BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY legacy_key (legacy_key),
        KEY crop_id (crop_id),
        KEY harvest_date (harvest_date)
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

    $crops_table = $wpdb->prefix . 'baykat_crops';
    $legacy_harvest_import = $wpdb->query(
        $wpdb->prepare(
            "INSERT IGNORE INTO $harvests_table
                (crop_id, user_id, recorded_by, quantity, unit, harvest_date, notes, legacy_key)
             SELECT id, user_id, user_id, harvested_quantity, harvested_unit, NULL, %s, id
             FROM $crops_table
             WHERE status = 'harvested' AND harvested_quantity > 0 AND harvested_unit IS NOT NULL AND harvested_unit <> ''",
            'Quantité récoltée enregistrée avant le journal des récoltes.'
        )
    );
    if ( false === $legacy_harvest_import ) {
        error_log( 'Baykat Finance: legacy harvest import failed; it will retry on a later request.' );
        return;
    }

    $required_columns = array(
        $table_name       => array( 'category', 'item_name', 'quantity', 'expense_category', 'farm_id', 'parcel_id', 'activity_id', 'crop_id', 'cancelled_at', 'cancelled_by' ),
        $farms_table      => array( 'user_id', 'name', 'location', 'latitude', 'longitude', 'area_ha', 'image_id' ),
        $parcels_table    => array( 'user_id', 'farm_id', 'name', 'area_m2', 'boundary', 'status', 'image_id' ),
        $crops_table      => array( 'user_id', 'parcel_id', 'name', 'variety', 'planted_at', 'expected_harvest_date', 'harvested_quantity', 'harvested_unit', 'status', 'image_id' ),
        $activities_table => array( 'user_id', 'parcel_id', 'title', 'activity_type', 'activity_date', 'notes', 'image_id' ),
        $members_table    => array( 'farm_id', 'owner_user_id', 'member_user_id', 'role' ),
        $stock_movements_table => array( 'movement_type', 'item_name', 'quantity', 'unit', 'movement_date', 'destination_farm_id', 'destination_parcel_id' ),
        $harvests_table => array( 'crop_id', 'user_id', 'recorded_by', 'quantity', 'unit', 'harvest_date', 'notes', 'legacy_key' ),
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
