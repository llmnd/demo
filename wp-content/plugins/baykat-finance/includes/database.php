<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BAYKAT_FINANCE_DB_VERSION', '18' );

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

    $installed_version = get_option( 'baykat_finance_db_version', '0' );
    $table_name      = baykat_finance_get_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = array(
        "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        type VARCHAR(20) NOT NULL,
        category VARCHAR(30) NOT NULL DEFAULT 'general',
        amount DECIMAL(15,2) NOT NULL,
        operation_date DATE NULL,
        description TEXT NULL,
        item_name VARCHAR(190) NULL,
        quantity DECIMAL(15,3) NULL,
        unit VARCHAR(40) NULL,
        expense_category VARCHAR(30) NOT NULL DEFAULT 'general',
        farm_id BIGINT UNSIGNED NULL,
        parcel_id BIGINT UNSIGNED NULL,
        activity_id BIGINT UNSIGNED NULL,
        crop_id BIGINT UNSIGNED NULL,
        campaign_id BIGINT UNSIGNED NULL,
        cancelled_at DATETIME NULL,
        cancelled_by BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY operation_date (operation_date),
        KEY farm_id (farm_id),
        KEY parcel_id (parcel_id),
        KEY activity_id (activity_id),
        KEY crop_id (crop_id),
        KEY campaign_id (campaign_id)
    ) $charset_collate;",
    );

    $farms_table = $wpdb->prefix . 'baykat_farms';
    $parcels_table = $wpdb->prefix . 'baykat_parcels';
    $crops_table = $wpdb->prefix . 'baykat_crops';
    $activities_table = $wpdb->prefix . 'baykat_activities';
    $campaigns_table = $wpdb->prefix . 'baykat_campaigns';
    $crop_catalog_table = $wpdb->prefix . 'baykat_crop_catalog';

    $sql[] = "CREATE TABLE $farms_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(190) NOT NULL,
        location VARCHAR(190) NULL,
        latitude DECIMAL(10,7) NULL,
        longitude DECIMAL(10,7) NULL,
        area_ha DECIMAL(12,3) NULL,
        image_id BIGINT UNSIGNED NULL,
        archived_at DATETIME NULL,
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
        archived_at DATETIME NULL,
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
    $campaign_allocations_table = $wpdb->prefix . 'baykat_campaign_allocations';
    $campaign_stock_events_table = $wpdb->prefix . 'baykat_campaign_stock_events';
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
        campaign_id BIGINT UNSIGNED NULL,
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
        KEY campaign_id (campaign_id),
        KEY harvest_date (harvest_date)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE $campaign_allocations_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id BIGINT UNSIGNED NOT NULL,
        harvest_id BIGINT UNSIGNED NOT NULL,
        destination_type VARCHAR(30) NOT NULL,
        destination_label VARCHAR(190) NULL,
        quantity DECIMAL(15,3) NOT NULL,
        notes TEXT NULL,
        idempotency_key VARCHAR(190) NOT NULL,
        created_by BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY idempotency_key (idempotency_key),
        KEY campaign_id (campaign_id),
        KEY harvest_id (harvest_id)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE $campaign_stock_events_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id BIGINT UNSIGNED NOT NULL,
        harvest_id BIGINT UNSIGNED NOT NULL,
        allocation_id BIGINT UNSIGNED NULL,
        event_type VARCHAR(20) NOT NULL,
        quantity DECIMAL(15,3) NOT NULL,
        external_order_id VARCHAR(190) NULL,
        source_transaction_id BIGINT UNSIGNED NULL,
        idempotency_key VARCHAR(190) NOT NULL,
        notes TEXT NULL,
        recorded_by BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY source_transaction_id (source_transaction_id),
        UNIQUE KEY idempotency_key (idempotency_key),
        KEY campaign_id (campaign_id),
        KEY harvest_id (harvest_id),
        KEY allocation_id (allocation_id),
        KEY event_type (event_type),
        KEY external_order_id (external_order_id)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE $activities_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        parcel_id BIGINT UNSIGNED NOT NULL,
        campaign_id BIGINT UNSIGNED NULL,
        title VARCHAR(190) NOT NULL,
        activity_type VARCHAR(30) NOT NULL,
        activity_date DATE NOT NULL,
        notes TEXT NULL,
        image_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY parcel_id (parcel_id),
        KEY campaign_id (campaign_id),
        KEY activity_date (activity_date)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE $campaigns_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        campaign_type VARCHAR(20) NOT NULL DEFAULT 'real',
        farm_id BIGINT UNSIGNED NULL,
        parcel_id BIGINT UNSIGNED NULL,
        crop_id BIGINT UNSIGNED NULL,
        crop_reference_id BIGINT UNSIGNED NULL,
        source_simulation_id BIGINT UNSIGNED NULL,
        crop_name VARCHAR(190) NOT NULL,
        variety VARCHAR(190) NULL,
        area_ha DECIMAL(12,4) NOT NULL,
        planned_start_date DATE NULL,
        planned_harvest_date DATE NULL,
        expected_yield_kg_ha DECIMAL(15,3) NULL,
        expected_cost DECIMAL(15,2) NULL,
        expected_price_per_kg DECIMAL(15,2) NULL,
        production_status VARCHAR(20) NOT NULL DEFAULT 'planned',
        commercialization_status VARCHAR(20) NOT NULL DEFAULT 'planned',
        archived_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY farm_id (farm_id),
        KEY parcel_id (parcel_id),
        KEY crop_id (crop_id),
        KEY crop_reference_id (crop_reference_id),
        KEY source_simulation_id (source_simulation_id),
        KEY campaign_type (campaign_type)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE $crop_catalog_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(190) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY is_active (is_active),
        KEY name (name)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    foreach ( $sql as $table_sql ) {
        dbDelta( $table_sql );
    }

    $crops_table = $wpdb->prefix . 'baykat_crops';
    if ( version_compare( (string) $installed_version, '14', '<' ) ) {
        $legacy_crops = $wpdb->get_results(
            "SELECT crops.id, crops.user_id, crops.harvested_quantity, crops.harvested_unit
             FROM $crops_table AS crops
             WHERE crops.status = 'harvested'
                AND crops.harvested_quantity > 0
                AND crops.harvested_unit IS NOT NULL
                AND crops.harvested_unit <> ''
                AND NOT EXISTS (
                    SELECT 1 FROM $harvests_table AS existing_harvest
                    WHERE existing_harvest.crop_id = crops.id
                )"
        );
        if ( '' !== $wpdb->last_error ) {
            error_log( 'Baykat Finance: legacy harvest lookup failed; it will retry on a later request.' );
            return;
        }
        foreach ( (array) $legacy_crops as $legacy_crop ) {
            $legacy_harvest_import = $wpdb->insert(
                $harvests_table,
                array(
                    'crop_id'     => (int) $legacy_crop->id,
                    'user_id'     => (int) $legacy_crop->user_id,
                    'recorded_by' => (int) $legacy_crop->user_id,
                    'quantity'    => (float) $legacy_crop->harvested_quantity,
                    'unit'        => $legacy_crop->harvested_unit,
                    'notes'       => 'Quantité récoltée enregistrée avant le journal des récoltes.',
                    'legacy_key'  => (int) $legacy_crop->id,
                ),
                array( '%d', '%d', '%d', '%f', '%s', '%s', '%d' )
            );
            if ( false === $legacy_harvest_import ) {
                error_log( 'Baykat Finance: legacy harvest import failed; it will retry on a later request.' );
                return;
            }
        }
    }

    $required_columns = array(
        $table_name       => array( 'category', 'item_name', 'quantity', 'expense_category', 'operation_date', 'farm_id', 'parcel_id', 'activity_id', 'crop_id', 'campaign_id', 'cancelled_at', 'cancelled_by' ),
        $farms_table      => array( 'user_id', 'name', 'location', 'latitude', 'longitude', 'area_ha', 'image_id', 'archived_at' ),
        $parcels_table    => array( 'user_id', 'farm_id', 'name', 'area_m2', 'boundary', 'status', 'image_id', 'archived_at' ),
        $crops_table      => array( 'user_id', 'parcel_id', 'name', 'variety', 'planted_at', 'expected_harvest_date', 'harvested_quantity', 'harvested_unit', 'status', 'image_id' ),
        $activities_table => array( 'user_id', 'parcel_id', 'campaign_id', 'title', 'activity_type', 'activity_date', 'notes', 'image_id' ),
        $campaigns_table => array( 'user_id', 'campaign_type', 'farm_id', 'parcel_id', 'crop_id', 'crop_reference_id', 'source_simulation_id', 'crop_name', 'variety', 'area_ha', 'planned_start_date', 'planned_harvest_date', 'expected_yield_kg_ha', 'expected_cost', 'expected_price_per_kg', 'production_status', 'commercialization_status', 'archived_at' ),
        $crop_catalog_table => array( 'name', 'is_active', 'created_at' ),
        $members_table    => array( 'farm_id', 'owner_user_id', 'member_user_id', 'role' ),
        $stock_movements_table => array( 'movement_type', 'item_name', 'quantity', 'unit', 'movement_date', 'destination_farm_id', 'destination_parcel_id' ),
        $harvests_table => array( 'crop_id', 'campaign_id', 'user_id', 'recorded_by', 'quantity', 'unit', 'harvest_date', 'notes', 'legacy_key' ),
        $campaign_allocations_table => array( 'campaign_id', 'harvest_id', 'destination_type', 'destination_label', 'quantity', 'notes', 'idempotency_key', 'created_by', 'created_at' ),
        $campaign_stock_events_table => array( 'campaign_id', 'harvest_id', 'allocation_id', 'event_type', 'quantity', 'external_order_id', 'source_transaction_id', 'idempotency_key', 'notes', 'recorded_by', 'created_at' ),
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

    if ( version_compare( (string) $installed_version, '16', '<' ) ) {
        $harvest_campaign_backfill = $wpdb->query(
            "UPDATE $harvests_table AS harvests
             INNER JOIN (
                SELECT crop_id, MIN(id) AS campaign_id
                FROM $campaigns_table
                WHERE campaign_type = 'real' AND crop_id IS NOT NULL
                GROUP BY crop_id
                HAVING COUNT(*) = 1
             ) AS unique_campaigns ON unique_campaigns.crop_id = harvests.crop_id
             SET harvests.campaign_id = unique_campaigns.campaign_id
             WHERE harvests.campaign_id IS NULL"
        );
        if ( false === $harvest_campaign_backfill ) {
            error_log( 'Baykat Finance: unambiguous harvest campaign links could not be migrated; it will retry on a later request.' );
            return;
        }
    }

    if ( version_compare( (string) $installed_version, '17', '<' ) ) {
        $transaction_campaign_backfill = $wpdb->query(
            "UPDATE $table_name AS transactions
             INNER JOIN (
                SELECT crop_id, MIN(id) AS campaign_id
                FROM $campaigns_table
                WHERE campaign_type = 'real' AND crop_id IS NOT NULL
                GROUP BY crop_id
                HAVING COUNT(*) = 1
             ) AS unique_campaigns ON unique_campaigns.crop_id = transactions.crop_id
             SET transactions.campaign_id = unique_campaigns.campaign_id
             WHERE transactions.campaign_id IS NULL"
        );
        if ( false === $transaction_campaign_backfill ) {
            error_log( 'Baykat Finance: unambiguous transaction campaign links could not be migrated; it will retry on a later request.' );
            return;
        }
    }

    if ( version_compare( (string) $installed_version, '14', '<' ) ) {
        $activity_campaign_backfill = $wpdb->query(
        "UPDATE $table_name AS transactions
         INNER JOIN $activities_table AS activities ON activities.id = transactions.activity_id
         SET transactions.campaign_id = activities.campaign_id
         WHERE transactions.campaign_id IS NULL AND activities.campaign_id IS NOT NULL"
        );
        if ( false === $activity_campaign_backfill ) {
            error_log( 'Baykat Finance: activity campaign links could not be migrated; it will retry on a later request.' );
            return;
        }

        $crop_campaign_backfill = $wpdb->query(
        "UPDATE $table_name AS transactions
         INNER JOIN (
            SELECT crop_id, MIN(id) AS campaign_id
            FROM $campaigns_table
            WHERE campaign_type = 'real' AND crop_id IS NOT NULL
            GROUP BY crop_id
            HAVING COUNT(*) = 1
         ) AS unique_campaigns ON unique_campaigns.crop_id = transactions.crop_id
         SET transactions.campaign_id = unique_campaigns.campaign_id
         WHERE transactions.campaign_id IS NULL"
        );
        if ( false === $crop_campaign_backfill ) {
            error_log( 'Baykat Finance: crop campaign links could not be migrated; it will retry on a later request.' );
            return;
        }

        $unit_tables = array(
            $table_name => 'unit',
            $crops_table => 'harvested_unit',
            $harvests_table => 'unit',
            $stock_movements_table => 'unit',
        );
        foreach ( $unit_tables as $unit_table => $unit_column ) {
            $normalized = $wpdb->query(
                "UPDATE $unit_table
                 SET $unit_column = 'kg'
                 WHERE LOWER(TRIM($unit_column)) IN ('kk', 'kg', 'kgs', 'kilogram', 'kilograms', 'kilogramme', 'kilogrammes')"
            );
            if ( false === $normalized ) {
                error_log( 'Baykat Finance: unit normalization failed; it will retry on a later request.' );
                return;
            }
        }

        $harvest_totals = $wpdb->get_results(
            "SELECT crop_id, MIN(unit) AS unit, COUNT(DISTINCT unit) AS unit_count,
                    SUM(quantity) AS harvested_quantity
             FROM $harvests_table
             GROUP BY crop_id"
        );
        if ( '' !== $wpdb->last_error ) {
            error_log( 'Baykat Finance: harvest total reconciliation failed; it will retry on a later request.' );
            return;
        }

        foreach ( (array) $harvest_totals as $harvest_total ) {
            if ( 1 !== (int) $harvest_total->unit_count ) {
                error_log( 'Baykat Finance: a crop has harvest records with incompatible units; its stored total was not changed.' );
                continue;
            }

            $updated = $wpdb->update(
                $crops_table,
                array(
                    'harvested_quantity' => (float) $harvest_total->harvested_quantity,
                    'harvested_unit'     => $harvest_total->unit,
                ),
                array( 'id' => (int) $harvest_total->crop_id ),
                array( '%f', '%s' ),
                array( '%d' )
            );
            if ( false === $updated ) {
                error_log( 'Baykat Finance: a stored crop harvest total could not be reconciled; it will retry on a later request.' );
                return;
            }
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
