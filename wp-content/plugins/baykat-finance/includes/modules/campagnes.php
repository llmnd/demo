<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_farm_campaign_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'baykat_campaigns';
}

function baykat_farm_crop_catalog_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'baykat_crop_catalog';
}

function baykat_farm_campaign_allocation_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'baykat_campaign_allocations';
}

function baykat_farm_campaign_stock_event_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'baykat_campaign_stock_events';
}

function baykat_farm_crop_catalog_get_active() {
    global $wpdb;

    $table = baykat_farm_crop_catalog_table_name();
    $cultures = $wpdb->get_results( "SELECT id, name FROM $table WHERE is_active = 1 ORDER BY name ASC" );
    if ( '' !== $wpdb->last_error ) {
        return new WP_Error( 'crop_catalog_failed', 'Impossible de charger la liste des cultures.' );
    }

    $unique_cultures = array();
    $seen_names = array();
    foreach ( (array) $cultures as $culture ) {
        $normalized_name = baykat_farm_crop_catalog_normalize_name( $culture->name );
        if ( isset( $seen_names[ $normalized_name ] ) ) {
            continue;
        }
        $seen_names[ $normalized_name ] = true;
        $unique_cultures[] = $culture;
    }

    return $unique_cultures;
}

function baykat_farm_crop_catalog_normalize_name( $name ) {
    $name = strtolower( remove_accents( trim( sanitize_text_field( $name ) ) ) );
    $name = preg_replace( '/[^a-z0-9]+/', ' ', $name );
    $name = trim( preg_replace( '/\s+/', ' ', $name ) );
    $invariant_plural_words = array( 'mais', 'pois', 'anis', 'cress' );
    $words = explode( ' ', $name );

    foreach ( $words as &$word ) {
        $ending = substr( $word, -1 );
        if ( strlen( $word ) > 4 && in_array( $ending, array( 's', 'x' ), true ) && ! in_array( $word, $invariant_plural_words, true ) ) {
            $word = substr( $word, 0, -1 );
        }
    }
    unset( $word );

    return implode( ' ', $words );
}

function baykat_farm_crop_catalog_find_duplicate( $name, $cultures ) {
    $normalized_name = baykat_farm_crop_catalog_normalize_name( $name );
    foreach ( $cultures as $culture ) {
        $normalized_existing = baykat_farm_crop_catalog_normalize_name( $culture->name );
        if ( $normalized_name === $normalized_existing ) {
            return array( 'type' => 'same', 'culture' => $culture );
        }

        if ( strlen( $normalized_name ) >= 5 && strlen( $normalized_existing ) >= 5 && levenshtein( $normalized_name, $normalized_existing ) <= 1 ) {
            return array( 'type' => 'similar', 'culture' => $culture );
        }
    }

    return null;
}

function baykat_farm_crop_catalog_seed_defaults() {
    if ( '2' === (string) get_option( 'baykat_farm_crop_catalog_seed_version', '0' ) ) {
        return;
    }

    global $wpdb;
    $table = baykat_farm_crop_catalog_table_name();
    $cultures = $wpdb->get_results( "SELECT id, name, is_active FROM $table ORDER BY name ASC" );
    if ( '' !== $wpdb->last_error ) {
        error_log( 'Baykat Finance: default crop catalog could not be loaded; seeding will retry on a later request.' );
        return;
    }

    $default_cultures = array(
        'Aubergine',
        'Arachide',
        'Carotte',
        'Choux',
        'Citron',
        'Concombre',
        'Courgette',
        'Gombo',
        'Haricot',
        'Joxote',
        'Mil',
        'Nadio',
        'Navet',
        'Oignon',
        'Patate douce',
        'Piment',
        'Poivron',
        'Pomme de terre',
        'Thioukly',
        'Tomate',
        'Blé',
        'Ñambi',
    );

    foreach ( $default_cultures as $name ) {
        if ( baykat_farm_crop_catalog_find_duplicate( $name, (array) $cultures ) ) {
            continue;
        }

        $inserted = $wpdb->insert(
            $table,
            array( 'name' => $name, 'is_active' => 1 ),
            array( '%s', '%d' )
        );
        if ( false === $inserted ) {
            error_log( 'Baykat Finance: a default crop could not be inserted; catalog seeding will retry on a later request.' );
            return;
        }
        $cultures[] = (object) array(
            'id' => (int) $wpdb->insert_id,
            'name' => $name,
            'is_active' => 1,
        );
    }

    update_option( 'baykat_farm_crop_catalog_seed_version', '2' );
    if ( '2' !== (string) get_option( 'baykat_farm_crop_catalog_seed_version', '0' ) ) {
        error_log( 'Baykat Finance: default crop catalog seed version could not be saved; seeding will retry on a later request.' );
    }
}
add_action( 'plugins_loaded', 'baykat_farm_crop_catalog_seed_defaults', 20 );

function baykat_farm_campaign_stock_get_summary( $campaign_id, $harvest_id = 0 ) {
    global $wpdb;

    $harvests_table = $wpdb->prefix . 'baykat_harvests';
    $events_table = baykat_farm_campaign_stock_event_table_name();
    $campaigns_table = baykat_farm_campaign_table_name();
    $transactions_table = baykat_finance_get_table_name();
    $harvest_record_filter = '';
    $harvest_event_filter = '';
    $allocation_harvest_filter = '';
    $harvest_args = array( (int) $campaign_id );

    if ( $harvest_id ) {
        $harvest_record_filter = ' AND id = %d';
        $harvest_event_filter = ' AND harvest_id = %d';
        $allocation_harvest_filter = ' AND allocations.harvest_id = %d';
        $harvest_args[] = (int) $harvest_id;
    }

    $harvest_totals = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(quantity), 0) AS harvested_quantity,
                    COUNT(DISTINCT CASE
                        WHEN LOWER(TRIM(unit)) IN ('kg', 'kk', 'kgs', 'kilogram', 'kilograms', 'kilogramme', 'kilogrammes') THEN 'kg'
                        ELSE LOWER(TRIM(unit))
                    END) AS unit_count,
                    MIN(unit) AS unit
             FROM $harvests_table
             WHERE campaign_id = %d$harvest_record_filter",
            $harvest_args
        )
    );
    if ( '' !== $wpdb->last_error ) {
        return new WP_Error( 'campaign_stock_harvest_lookup_failed', 'Impossible de calculer les quantités récoltées de cette campagne.' );
    }

    $campaign_harvest_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $harvests_table WHERE campaign_id = %d",
            $campaign_id
        )
    );
    if ( '' !== $wpdb->last_error ) {
        return new WP_Error( 'campaign_stock_harvest_count_failed', 'Impossible de vérifier les lots de récolte de cette campagne.' );
    }

    $campaign_crop_id = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT crop_id FROM $campaigns_table WHERE id = %d AND campaign_type = 'real'",
            $campaign_id
        )
    );
    if ( '' !== $wpdb->last_error ) {
        return new WP_Error( 'campaign_stock_crop_lookup_failed', 'Impossible de vérifier le lien entre cette campagne et sa culture technique.' );
    }

    $legacy_ambiguous_sales = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*)
             FROM $transactions_table AS transactions
             WHERE transactions.category = 'sale'
                AND transactions.type = 'income'
                AND transactions.cancelled_at IS NULL
                AND (
                    (
                        transactions.campaign_id = %d
                        AND transactions.crop_id IS NOT NULL
                        AND ( %d = 0 OR transactions.crop_id <> %d )
                    )
                    OR (
                        %d > 0
                        AND transactions.crop_id = %d
                        AND (
                            SELECT COUNT(*) FROM $campaigns_table AS linked_campaigns
                            WHERE linked_campaigns.crop_id = transactions.crop_id
                                AND linked_campaigns.campaign_type = 'real'
                        ) > 1
                    )
                )",
            $campaign_id,
            $campaign_crop_id,
            $campaign_crop_id,
            $campaign_crop_id,
            $campaign_crop_id
        )
    );
    if ( '' !== $wpdb->last_error ) {
        return new WP_Error( 'campaign_stock_legacy_sales_lookup_failed', 'Impossible de vérifier les ventes historiques dont le lot est ambigu.' );
    }

    $manual_sales = 0.0;
    if ( ! $harvest_id || 1 === $campaign_harvest_count ) {
        $manual_sales = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(transactions.quantity), 0)
                 FROM $transactions_table AS transactions
                 WHERE transactions.campaign_id = %d
                    AND transactions.category = 'sale'
                    AND transactions.type = 'income'
                    AND transactions.cancelled_at IS NULL
                    AND (
                        transactions.crop_id IS NULL
                        OR (
                            transactions.crop_id = %d
                            AND (
                                SELECT COUNT(*) FROM $campaigns_table AS linked_campaigns
                                WHERE linked_campaigns.crop_id = transactions.crop_id
                                    AND linked_campaigns.campaign_type = 'real'
                            ) = 1
                        )
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM $events_table AS events
                        WHERE events.source_transaction_id = transactions.id
                            AND events.event_type = 'sale'
                    )",
                $campaign_id,
                $campaign_crop_id
            )
        );
        if ( '' !== $wpdb->last_error ) {
            return new WP_Error( 'campaign_stock_sales_lookup_failed', 'Impossible de vérifier les ventes historiques de cette campagne.' );
        }
    }

    $events = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN event_type IN ('sale', 'loss', 'other_out') THEN quantity ELSE 0 END), 0) AS physical_out_quantity,
                COALESCE(SUM(CASE WHEN event_type = 'sale' THEN quantity ELSE 0 END), 0) AS stock_sold_quantity,
                COALESCE(SUM(CASE WHEN event_type = 'loss' THEN quantity ELSE 0 END), 0) AS loss_quantity,
                COALESCE(SUM(CASE WHEN event_type = 'other_out' THEN quantity ELSE 0 END), 0) AS other_out_quantity,
                COALESCE(SUM(CASE WHEN event_type = 'return' THEN quantity ELSE 0 END), 0) AS returned_quantity,
                COALESCE(SUM(
                    CASE
                        WHEN event_type = 'allocation' THEN quantity
                        WHEN event_type = 'release' THEN -quantity
                        WHEN event_type = 'sale' AND allocation_id IS NOT NULL THEN -quantity
                        ELSE 0
                    END
                ), 0) AS allocated_quantity
             FROM $events_table
             WHERE campaign_id = %d$harvest_event_filter",
            $harvest_args
        )
    );
    if ( '' !== $wpdb->last_error ) {
        return new WP_Error( 'campaign_stock_events_lookup_failed', 'Impossible de calculer les allocations et mouvements de stock.' );
    }

    $allocation_totals = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(
                allocations.quantity
                - COALESCE((
                    SELECT SUM(events.quantity) FROM $events_table AS events
                    WHERE events.allocation_id = allocations.id AND events.event_type = 'release'
                ), 0)
                - COALESCE((
                    SELECT SUM(events.quantity) FROM $events_table AS events
                    WHERE events.allocation_id = allocations.id AND events.event_type = 'sale'
                ), 0)
            ), 0) AS commercialization_allocated_quantity
             FROM " . baykat_farm_campaign_allocation_table_name() . " AS allocations
             WHERE allocations.campaign_id = %d
                AND allocations.destination_type = 'commercialization'$allocation_harvest_filter",
            $harvest_args
        )
    );
    if ( '' !== $wpdb->last_error ) {
        return new WP_Error( 'campaign_stock_allocations_lookup_failed', 'Impossible de calculer les quantités allouées à la commercialisation.' );
    }

    $harvested_quantity = (float) $harvest_totals->harvested_quantity;
    $sold_quantity = $manual_sales + (float) $events->stock_sold_quantity;
    $physical_quantity = $harvested_quantity
        - $manual_sales
        - (float) $events->physical_out_quantity
        + (float) $events->returned_quantity;
    $allocated_quantity = (float) $events->allocated_quantity;
    $unit_count = (int) $harvest_totals->unit_count;
    $is_consistent = $unit_count <= 1
        && $physical_quantity >= -0.0005
        && $allocated_quantity >= -0.0005
        && $allocated_quantity <= $physical_quantity + 0.0005
        && 0 === $legacy_ambiguous_sales;

    return array(
        'harvested_quantity' => $harvested_quantity,
        'sold_quantity' => $sold_quantity,
        'manual_sales_quantity' => $manual_sales,
        'legacy_ambiguous_sales' => $legacy_ambiguous_sales,
        'loss_quantity' => (float) $events->loss_quantity,
        'other_out_quantity' => (float) $events->other_out_quantity,
        'returned_quantity' => (float) $events->returned_quantity,
        'net_physical_adjustments' => (float) $events->loss_quantity
            + (float) $events->other_out_quantity
            - (float) $events->returned_quantity,
        'physical_quantity' => $physical_quantity,
        'allocated_quantity' => max( 0, $allocated_quantity ),
        'commercialization_allocated_quantity' => max( 0, (float) $allocation_totals->commercialization_allocated_quantity ),
        'available_quantity' => max( 0, $physical_quantity - $allocated_quantity ),
        'unit' => 1 === $unit_count ? baykat_farm_normalize_unit( $harvest_totals->unit ) : '',
        'unit_count' => $unit_count,
        'consistent' => $is_consistent,
    );
}

function baykat_farm_campaign_get_allocations( $campaign_id ) {
    global $wpdb;

    $allocations_table = baykat_farm_campaign_allocation_table_name();
    $harvests_table = $wpdb->prefix . 'baykat_harvests';
    $events_table = baykat_farm_campaign_stock_event_table_name();
    $allocations = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT allocations.*, harvests.harvest_date,
                    COALESCE((
                        SELECT SUM(events.quantity) FROM $events_table AS events
                        WHERE events.allocation_id = allocations.id AND events.event_type = 'release'
                    ), 0) AS released_quantity,
                    COALESCE((
                        SELECT SUM(events.quantity) FROM $events_table AS events
                        WHERE events.allocation_id = allocations.id AND events.event_type = 'sale'
                    ), 0) AS sold_quantity
             FROM $allocations_table AS allocations
             INNER JOIN $harvests_table AS harvests ON harvests.id = allocations.harvest_id
             WHERE allocations.campaign_id = %d
             ORDER BY allocations.created_at DESC, allocations.id DESC",
            $campaign_id
        )
    );
    if ( '' !== $wpdb->last_error ) {
        return new WP_Error( 'campaign_allocation_lookup_failed', 'Impossible de charger l’historique des allocations.' );
    }

    foreach ( $allocations as $allocation ) {
        $allocation->remaining_quantity = max(
            0,
            (float) $allocation->quantity - (float) $allocation->released_quantity - (float) $allocation->sold_quantity
        );
    }

    return $allocations;
}

function baykat_farm_campaign_get_stock_events( $campaign_id ) {
    global $wpdb;

    $events_table = baykat_farm_campaign_stock_event_table_name();
    $harvests_table = $wpdb->prefix . 'baykat_harvests';
    $events = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT events.id, events.event_type, events.quantity, events.notes, events.created_at,
                    harvests.harvest_date
             FROM $events_table AS events
             INNER JOIN $harvests_table AS harvests ON harvests.id = events.harvest_id
             WHERE events.campaign_id = %d
             ORDER BY events.created_at DESC, events.id DESC
             LIMIT 30",
            $campaign_id
        )
    );
    if ( '' !== $wpdb->last_error ) {
        return new WP_Error( 'campaign_stock_history_failed', 'Impossible de charger l’historique du stock récolté.' );
    }

    return $events;
}

function baykat_farm_campaign_stock_event_insert( $event ) {
    global $wpdb;

    return $wpdb->insert(
        baykat_farm_campaign_stock_event_table_name(),
        $event,
        array( '%d', '%d', '%d', '%s', '%f', '%s', '%d', '%s', '%s', '%d' )
    );
}

function baykat_farm_campaign_stock_request_key( $key ) {
    if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
        return '';
    }

    $value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );

    return preg_match( '/^[a-f0-9-]{36}$/i', $value ) ? $value : '';
}

function baykat_farm_campaign_stock_can_edit( $campaign_id, $harvest_id, $user_id ) {
    global $wpdb;

    $campaigns_table = baykat_farm_campaign_table_name();
    $harvests_table = $wpdb->prefix . 'baykat_harvests';
    $record = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT campaigns.id, campaigns.campaign_type, campaigns.farm_id, campaigns.parcel_id, campaigns.crop_id,
                    campaigns.production_status, campaigns.commercialization_status
             FROM $campaigns_table AS campaigns
             INNER JOIN $harvests_table AS harvests
                ON harvests.campaign_id = campaigns.id AND harvests.id = %d
             WHERE campaigns.id = %d AND campaigns.archived_at IS NULL
             FOR UPDATE",
            $harvest_id,
            $campaign_id
        )
    );
    if ( '' !== $wpdb->last_error ) {
        return new WP_Error( 'campaign_stock_access_lookup_failed', 'Impossible de vérifier la campagne et le lot de récolte sélectionnés.' );
    }
    if ( ! $record || 'real' !== $record->campaign_type ) {
        return new WP_Error( 'campaign_stock_link_invalid', 'Le stock récolté doit appartenir à une campagne réelle active.' );
    }
    if ( ! baykat_farm_user_can_edit_farm( (int) $record->farm_id, $user_id ) ) {
        return new WP_Error( 'campaign_stock_access_denied', 'Votre rôle ne permet pas de modifier le stock de cette ferme.' );
    }

    $validated_campaign_id = baykat_farm_campaign_validate_link(
        $campaign_id,
        (int) $record->parcel_id,
        $user_id,
        (int) $record->crop_id
    );
    if ( is_wp_error( $validated_campaign_id ) ) {
        return $validated_campaign_id;
    }

    return $record;
}

function baykat_farm_process_campaign_stock_action( $user_id ) {
    $actions = array(
        'baykat_create_campaign_allocation' => 'allocation',
        'baykat_release_campaign_allocation' => 'release',
        'baykat_record_campaign_stock_adjustment' => 'adjustment',
    );
    $action_key = '';
    foreach ( $actions as $post_key => $action ) {
        if ( isset( $_POST[ $post_key ] ) ) {
            $action_key = $post_key;
            break;
        }
    }
    if ( '' === $action_key ) {
        return null;
    }

    check_admin_referer( $action_key );
    global $wpdb;

    if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
        return array( 'type' => 'error', 'message' => 'Impossible de démarrer la mise à jour du stock récolté. Veuillez réessayer.' );
    }

    $idempotency_key = baykat_farm_campaign_stock_request_key( 'stock_action_key' );
    if ( '' === $idempotency_key ) {
        $wpdb->query( 'ROLLBACK' );
        return array( 'type' => 'error', 'message' => 'La clé de sécurité de cette opération est invalide. Rechargez la page et réessayez.' );
    }

    if ( 'allocation' === $actions[ $action_key ] ) {
        $campaign_id = baykat_farm_request_id( 'campaign_id' );
        $harvest_id = baykat_farm_request_id( 'harvest_id' );
        $quantity = baykat_farm_campaign_decimal_input( 'stock_quantity' );
        $destination_type = isset( $_POST['destination_type'] ) && is_string( $_POST['destination_type'] )
            ? sanitize_key( wp_unslash( $_POST['destination_type'] ) )
            : '';
        $destination_label = isset( $_POST['destination_label'] ) && is_string( $_POST['destination_label'] )
            ? sanitize_text_field( wp_unslash( $_POST['destination_label'] ) )
            : '';
        $notes = isset( $_POST['stock_notes'] ) && is_string( $_POST['stock_notes'] )
            ? sanitize_textarea_field( wp_unslash( $_POST['stock_notes'] ) )
            : '';

        if (
            ! $campaign_id
            || ! $harvest_id
            || null === $quantity
            || $quantity < 0.001
            || $quantity > 999999999999.999
            || ! in_array( $destination_type, array( 'commercialization', 'other' ), true )
            || ( 'other' === $destination_type && '' === $destination_label )
            || strlen( $destination_label ) > 190
            || strlen( $notes ) > 2000
        ) {
            $wpdb->query( 'ROLLBACK' );
            return array( 'type' => 'error', 'message' => 'Vérifiez la quantité, le lot récolté et la destination de l’allocation.' );
        }

        $record = baykat_farm_campaign_stock_can_edit( $campaign_id, $harvest_id, $user_id );
        if ( is_wp_error( $record ) ) {
            $wpdb->query( 'ROLLBACK' );
            return array( 'type' => 'error', 'message' => $record->get_error_message() );
        }
        if ( 'completed' !== $record->production_status || 'in_progress' !== $record->commercialization_status ) {
            $wpdb->query( 'ROLLBACK' );
            return array( 'type' => 'error', 'message' => 'Terminez la phase Production et démarrez la phase Commercialisation avant de réserver une récolte.' );
        }

        $campaign_stock = baykat_farm_campaign_stock_get_summary( $campaign_id );
        $harvest_stock = baykat_farm_campaign_stock_get_summary( $campaign_id, $harvest_id );
        if ( is_wp_error( $campaign_stock ) || is_wp_error( $harvest_stock ) ) {
            $wpdb->query( 'ROLLBACK' );
            $stock_error = is_wp_error( $campaign_stock ) ? $campaign_stock : $harvest_stock;
            return array( 'type' => 'error', 'message' => $stock_error->get_error_message() );
        }
        if (
            ! $campaign_stock['consistent']
            || ! $harvest_stock['consistent']
            || '' === $campaign_stock['unit']
            || strtolower( $campaign_stock['unit'] ) !== strtolower( $harvest_stock['unit'] )
        ) {
            $wpdb->query( 'ROLLBACK' );
            return array( 'type' => 'error', 'message' => 'Le stock de cette campagne présente des unités incompatibles ou un solde incohérent. Aucune allocation n’a été créée.' );
        }
        $allocatable_quantity = min( $campaign_stock['available_quantity'], $harvest_stock['available_quantity'] );
        if ( $quantity > $allocatable_quantity + 0.0005 ) {
            $wpdb->query( 'ROLLBACK' );
            return array( 'type' => 'error', 'message' => 'La quantité dépasse le stock libre disponible (' . baykat_farm_format_quantity( $allocatable_quantity ) . ' ' . $campaign_stock['unit'] . ').' );
        }

        $inserted = $wpdb->insert(
            baykat_farm_campaign_allocation_table_name(),
            array(
                'campaign_id' => $campaign_id,
                'harvest_id' => $harvest_id,
                'destination_type' => $destination_type,
                'destination_label' => '' === $destination_label ? null : $destination_label,
                'quantity' => $quantity,
                'notes' => $notes,
                'idempotency_key' => $idempotency_key,
                'created_by' => (int) $user_id,
            ),
            array( '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%d' )
        );
        if ( false === $inserted ) {
            $wpdb->query( 'ROLLBACK' );
            return array( 'type' => 'error', 'message' => 'Cette allocation n’a pas pu être enregistrée ou cette demande a déjà été traitée.' );
        }
        $allocation_id = (int) $wpdb->insert_id;
        $event_inserted = baykat_farm_campaign_stock_event_insert(
            array(
                'campaign_id' => $campaign_id,
                'harvest_id' => $harvest_id,
                'allocation_id' => $allocation_id,
                'event_type' => 'allocation',
                'quantity' => $quantity,
                'external_order_id' => null,
                'source_transaction_id' => null,
                'idempotency_key' => 'allocation:' . $idempotency_key,
                'notes' => $notes,
                'recorded_by' => (int) $user_id,
            )
        );
        if ( false === $event_inserted || false === $wpdb->query( 'COMMIT' ) ) {
            $wpdb->query( 'ROLLBACK' );
            return array( 'type' => 'error', 'message' => 'L’allocation n’a pas pu être confirmée dans l’historique du stock.' );
        }

        return array( 'type' => 'success', 'message' => 'Allocation enregistrée. Elle réserve le stock libre sans diminuer le stock physique ni constituer une vente.' );
    }

    if ( 'release' === $actions[ $action_key ] ) {
        $allocation_id = baykat_farm_request_id( 'allocation_id' );
        $quantity = baykat_farm_campaign_decimal_input( 'stock_quantity' );
        $notes = isset( $_POST['stock_notes'] ) && is_string( $_POST['stock_notes'] )
            ? sanitize_textarea_field( wp_unslash( $_POST['stock_notes'] ) )
            : '';
        if ( ! $allocation_id || null === $quantity || $quantity < 0.001 || strlen( $notes ) > 2000 ) {
            $wpdb->query( 'ROLLBACK' );
            return array( 'type' => 'error', 'message' => 'Vérifiez la quantité à libérer pour cette allocation.' );
        }

        $allocations_table = baykat_farm_campaign_allocation_table_name();
        $campaigns_table = baykat_farm_campaign_table_name();
        $allocation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT allocations.*, campaigns.farm_id, campaigns.parcel_id, campaigns.crop_id, campaigns.campaign_type, campaigns.archived_at
                 FROM $allocations_table AS allocations
                 INNER JOIN $campaigns_table AS campaigns ON campaigns.id = allocations.campaign_id
                 WHERE allocations.id = %d
                 FOR UPDATE",
                $allocation_id
            )
        );
        if ( '' !== $wpdb->last_error || ! $allocation ) {
            $wpdb->query( 'ROLLBACK' );
            return array( 'type' => 'error', 'message' => 'Cette allocation est introuvable ou ne peut pas être vérifiée.' );
        }
        if (
            'real' !== $allocation->campaign_type
            || ! empty( $allocation->archived_at )
            || ! baykat_farm_user_can_edit_farm( (int) $allocation->farm_id, $user_id )
        ) {
            $wpdb->query( 'ROLLBACK' );
            return array( 'type' => 'error', 'message' => 'Votre rôle ne permet pas de modifier cette allocation.' );
        }
        $validated_campaign_id = baykat_farm_campaign_validate_link(
            (int) $allocation->campaign_id,
            (int) $allocation->parcel_id,
            $user_id,
            (int) $allocation->crop_id
        );
        if ( is_wp_error( $validated_campaign_id ) ) {
            $wpdb->query( 'ROLLBACK' );
            return array( 'type' => 'error', 'message' => $validated_campaign_id->get_error_message() );
        }

        $events_table = baykat_farm_campaign_stock_event_table_name();
        $allocation_events = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN event_type = 'release' THEN quantity ELSE 0 END), 0) AS released_quantity,
                    COALESCE(SUM(CASE WHEN event_type = 'sale' THEN quantity ELSE 0 END), 0) AS sold_quantity
                 FROM $events_table
                 WHERE allocation_id = %d",
                $allocation_id
            )
        );
        if ( '' !== $wpdb->last_error ) {
            $wpdb->query( 'ROLLBACK' );
            return array( 'type' => 'error', 'message' => 'Impossible de vérifier les quantités déjà libérées ou sorties.' );
        }
        $remaining_quantity = max(
            0,
            (float) $allocation->quantity - (float) $allocation_events->released_quantity - (float) $allocation_events->sold_quantity
        );
        if ( $quantity > $remaining_quantity + 0.0005 ) {
            $wpdb->query( 'ROLLBACK' );
            return array( 'type' => 'error', 'message' => 'La quantité dépasse le solde réservé de cette allocation (' . baykat_farm_format_quantity( $remaining_quantity ) . ').' );
        }

        $event_inserted = baykat_farm_campaign_stock_event_insert(
            array(
                'campaign_id' => (int) $allocation->campaign_id,
                'harvest_id' => (int) $allocation->harvest_id,
                'allocation_id' => $allocation_id,
                'event_type' => 'release',
                'quantity' => $quantity,
                'external_order_id' => null,
                'source_transaction_id' => null,
                'idempotency_key' => 'release:' . $idempotency_key,
                'notes' => $notes,
                'recorded_by' => (int) $user_id,
            )
        );
        if ( false === $event_inserted || false === $wpdb->query( 'COMMIT' ) ) {
            $wpdb->query( 'ROLLBACK' );
            return array( 'type' => 'error', 'message' => 'La quantité n’a pas pu être libérée de cette allocation.' );
        }

        return array( 'type' => 'success', 'message' => 'Quantité libérée. Le stock physique est inchangé et la quantité peut être allouée à nouveau.' );
    }

    $campaign_id = baykat_farm_request_id( 'campaign_id' );
    $harvest_id = baykat_farm_request_id( 'harvest_id' );
    $quantity = baykat_farm_campaign_decimal_input( 'stock_quantity' );
    $event_type = isset( $_POST['stock_event_type'] ) && is_string( $_POST['stock_event_type'] )
        ? sanitize_key( wp_unslash( $_POST['stock_event_type'] ) )
        : '';
    $notes = isset( $_POST['stock_notes'] ) && is_string( $_POST['stock_notes'] )
        ? sanitize_textarea_field( wp_unslash( $_POST['stock_notes'] ) )
        : '';
    if ( ! $campaign_id || ! $harvest_id || null === $quantity || $quantity < 0.001 || ! in_array( $event_type, array( 'loss', 'other_out' ), true ) || '' === $notes || strlen( $notes ) > 2000 ) {
        $wpdb->query( 'ROLLBACK' );
        return array( 'type' => 'error', 'message' => 'Une quantité, un lot et un motif valide sont requis pour corriger le stock physique.' );
    }

    $record = baykat_farm_campaign_stock_can_edit( $campaign_id, $harvest_id, $user_id );
    if ( is_wp_error( $record ) ) {
        $wpdb->query( 'ROLLBACK' );
        return array( 'type' => 'error', 'message' => $record->get_error_message() );
    }
    $campaign_stock = baykat_farm_campaign_stock_get_summary( $campaign_id );
    $harvest_stock = baykat_farm_campaign_stock_get_summary( $campaign_id, $harvest_id );
    if ( is_wp_error( $campaign_stock ) || is_wp_error( $harvest_stock ) ) {
        $wpdb->query( 'ROLLBACK' );
        $stock_error = is_wp_error( $campaign_stock ) ? $campaign_stock : $harvest_stock;
        return array( 'type' => 'error', 'message' => $stock_error->get_error_message() );
    }
    $adjustable_quantity = min( $campaign_stock['available_quantity'], $harvest_stock['available_quantity'] );
    if (
        ! $campaign_stock['consistent']
        || ! $harvest_stock['consistent']
        || $quantity > $adjustable_quantity + 0.0005
    ) {
        $wpdb->query( 'ROLLBACK' );
        return array( 'type' => 'error', 'message' => 'La correction dépasse le stock physique libre (' . baykat_farm_format_quantity( $adjustable_quantity ) . ' ' . $campaign_stock['unit'] . '). Libérez d’abord toute quantité réservée concernée.' );
    }

    $event_inserted = baykat_farm_campaign_stock_event_insert(
        array(
            'campaign_id' => $campaign_id,
            'harvest_id' => $harvest_id,
            'allocation_id' => null,
            'event_type' => $event_type,
            'quantity' => $quantity,
            'external_order_id' => null,
            'source_transaction_id' => null,
            'idempotency_key' => $event_type . ':' . $idempotency_key,
            'notes' => $notes,
            'recorded_by' => (int) $user_id,
        )
    );
    if ( false === $event_inserted || false === $wpdb->query( 'COMMIT' ) ) {
        $wpdb->query( 'ROLLBACK' );
        return array( 'type' => 'error', 'message' => 'Le mouvement physique n’a pas pu être enregistré.' );
    }

    return array(
        'type' => 'success',
        'message' => 'Mouvement physique enregistré. Le stock restant a été recalculé à partir du journal.',
    );
}

function baykat_farm_crop_catalog_register_admin_page() {
    add_submenu_page(
        'baykat-finance',
        'Référentiel des cultures',
        'Cultures de référence',
        'manage_options',
        'baykat-crop-catalog',
        'baykat_farm_crop_catalog_render_admin_page'
    );
}
add_action( 'admin_menu', 'baykat_farm_crop_catalog_register_admin_page' );

function baykat_farm_crop_catalog_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Vous n’avez pas l’autorisation d’accéder à cette page.', 'baykat-finance' ) );
    }

    global $wpdb;
    $table = baykat_farm_crop_catalog_table_name();
    $notice = null;
    if ( isset( $_POST['baykat_add_crop_reference'] ) ) {
        check_admin_referer( 'baykat_add_crop_reference' );
        $name = isset( $_POST['crop_reference_name'] ) && is_string( $_POST['crop_reference_name'] )
            ? sanitize_text_field( wp_unslash( $_POST['crop_reference_name'] ) )
            : '';
        if ( '' === $name || strlen( $name ) > 190 ) {
            $notice = array( 'type' => 'error', 'text' => 'Saisissez un nom de culture valide.' );
        } else {
            $existing_cultures = $wpdb->get_results( "SELECT id, name, is_active FROM $table ORDER BY name ASC" );
            if ( '' !== $wpdb->last_error ) {
                $notice = array( 'type' => 'error', 'text' => 'La vérification du référentiel a échoué.' );
            } else {
                $duplicate = baykat_farm_crop_catalog_find_duplicate( $name, $existing_cultures );
                if ( $duplicate && 'same' === $duplicate['type'] ) {
                    if ( ! $duplicate['culture']->is_active ) {
                        $updated = $wpdb->update( $table, array( 'is_active' => 1 ), array( 'id' => (int) $duplicate['culture']->id ), array( '%d' ), array( '%d' ) );
                        $notice = false === $updated
                            ? array( 'type' => 'error', 'text' => 'La culture existante n’a pas pu être réactivée.' )
                            : array( 'type' => 'success', 'text' => 'Cette culture existe déjà sous le nom canonique « ' . $duplicate['culture']->name . ' » et a été réactivée.' );
                    } else {
                        $notice = array( 'type' => 'error', 'text' => 'Cette culture existe déjà sous le nom canonique « ' . $duplicate['culture']->name . ' ». Réutilisez-la au lieu de créer un doublon.' );
                    }
                } elseif ( $duplicate ) {
                    $notice = array( 'type' => 'error', 'text' => 'Ce nom est très proche de « ' . $duplicate['culture']->name . ' ». Vérifiez le référentiel BAYKAT avant d’ajouter une autre culture.' );
                } else {
                    $inserted = $wpdb->insert( $table, array( 'name' => $name, 'is_active' => 1 ), array( '%s', '%d' ) );
                    $notice = false === $inserted
                        ? array( 'type' => 'error', 'text' => 'La culture n’a pas pu être ajoutée.' )
                        : array( 'type' => 'success', 'text' => 'Culture ajoutée au référentiel.' );
                }
            }
        }
    } elseif ( isset( $_POST['baykat_toggle_crop_reference'] ) ) {
        $reference_id = baykat_farm_request_id( 'crop_reference_id' );
        check_admin_referer( 'baykat_toggle_crop_reference_' . $reference_id );
        $is_active = isset( $_POST['crop_reference_active'] ) && '1' === $_POST['crop_reference_active'] ? 1 : 0;
        $updated = $reference_id
            ? $wpdb->update( $table, array( 'is_active' => $is_active ), array( 'id' => $reference_id ), array( '%d' ), array( '%d' ) )
            : false;
        $notice = false === $updated
            ? array( 'type' => 'error', 'text' => 'Le statut de cette culture n’a pas pu être modifié.' )
            : array( 'type' => 'success', 'text' => $is_active ? 'Culture réactivée.' : 'Culture désactivée. Les campagnes historiques restent inchangées.' );
    }

    $cultures = $wpdb->get_results( "SELECT id, name, is_active FROM $table ORDER BY name ASC" );
    if ( '' !== $wpdb->last_error ) {
        $cultures = array();
        $notice = array( 'type' => 'error', 'text' => 'Impossible de charger le référentiel des cultures.' );
    }
    ?>
    <div class="wrap">
        <h1>Référentiel des cultures</h1>
        <p>Les cultures sont des choix officiels de référence, pas des plantations. Ajoutez ici uniquement les libellés validés par BAYKAT : les campagnes ne proposent aucune saisie libre. La vérification ignore les majuscules, accents, espaces, ponctuation, pluriels simples en « s » ou « x », et bloque aussi les noms très proches. Désactiver une culture ne modifie pas les campagnes déjà créées.</p>
        <?php if ( $notice ) : ?>
            <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['text'] ); ?></p></div>
        <?php endif; ?>
        <form method="post">
            <?php wp_nonce_field( 'baykat_add_crop_reference' ); ?>
            <label for="crop_reference_name">Ajouter une culture</label>
            <input id="crop_reference_name" name="crop_reference_name" type="text" maxlength="190" required>
            <button class="button button-primary" type="submit" name="baykat_add_crop_reference" value="1">Ajouter au référentiel</button>
        </form>
        <h2>Cultures enregistrées</h2>
        <?php if ( $cultures ) : ?>
            <table class="widefat striped">
                <thead><tr><th>Culture</th><th>Statut</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ( $cultures as $culture ) : ?>
                        <tr>
                            <td><?php echo esc_html( $culture->name ); ?></td>
                            <td><?php echo $culture->is_active ? 'Active' : 'Désactivée'; ?></td>
                            <td>
                                <form method="post">
                                    <?php wp_nonce_field( 'baykat_toggle_crop_reference_' . (int) $culture->id ); ?>
                                    <input type="hidden" name="crop_reference_id" value="<?php echo esc_attr( $culture->id ); ?>">
                                    <input type="hidden" name="crop_reference_active" value="<?php echo $culture->is_active ? '0' : '1'; ?>">
                                    <button class="button" type="submit" name="baykat_toggle_crop_reference" value="1"><?php echo $culture->is_active ? 'Désactiver' : 'Réactiver'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>Aucune culture enregistrée. Ajoutez d’abord les cultures officielles validées par BAYKAT. Aucune campagne ne peut être créée avant que le référentiel soit renseigné.</p>
        <?php endif; ?>
    </div>
    <?php
}

function baykat_farm_campaign_get_user_campaigns( $user_id ) {
    global $wpdb;

    $table = baykat_farm_campaign_table_name();
    $farms = baykat_farm_table_name( 'farms' );
    $parcels = baykat_farm_table_name( 'parcels' );
    $crops = baykat_farm_table_name( 'crops' );
    $transactions = baykat_finance_get_table_name();
    $activities = baykat_farm_table_name( 'activities' );
    $harvests = $wpdb->prefix . 'baykat_harvests';
    $members = $wpdb->prefix . 'baykat_farm_members';

    $campaigns = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT campaigns.*, farms.name AS farm_name, parcels.name AS parcel_name, farms.user_id AS farm_owner_id,
                    crops.harvested_unit,
                    COALESCE((
                        SELECT SUM(transactions.amount)
                        FROM $transactions AS transactions
                        WHERE transactions.campaign_id = campaigns.id
                            AND transactions.type = 'expense'
                            AND transactions.cancelled_at IS NULL
                    ), 0) AS actual_expenses,
                    COALESCE((
                        SELECT SUM(transactions.amount)
                        FROM $transactions AS transactions
                        WHERE transactions.campaign_id = campaigns.id
                            AND transactions.type = 'expense'
                            AND transactions.cancelled_at IS NULL
                            AND transactions.expense_category <> 'commercialization'
                    ), 0) AS production_expenses,
                    COALESCE((
                        SELECT SUM(transactions.amount)
                        FROM $transactions AS transactions
                        WHERE transactions.campaign_id = campaigns.id
                            AND transactions.type = 'income'
                            AND transactions.cancelled_at IS NULL
                    ), 0) AS actual_income,
                    (
                        SELECT COUNT(*)
                        FROM $transactions AS transactions
                        WHERE transactions.campaign_id = campaigns.id
                            AND transactions.cancelled_at IS NULL
                    ) AS transaction_count,
                    (
                        SELECT COUNT(*)
                        FROM $activities AS activities
                        WHERE activities.campaign_id = campaigns.id
                    ) AS activity_count,
                    COALESCE((
                        SELECT SUM(harvests.quantity)
                        FROM $harvests AS harvests
                        WHERE harvests.campaign_id = campaigns.id
                            AND LOWER(TRIM(harvests.unit)) IN ('kg', 'kk', 'kgs', 'kilogram', 'kilograms', 'kilogramme', 'kilogrammes')
                    ), 0) AS harvested_kg,
                    (
                        SELECT COUNT(DISTINCT CASE
                            WHEN LOWER(TRIM(harvests.unit)) IN ('kg', 'kk', 'kgs', 'kilogram', 'kilograms', 'kilogramme', 'kilogrammes') THEN 'kg'
                            ELSE LOWER(TRIM(harvests.unit))
                        END)
                        FROM $harvests AS harvests
                        WHERE harvests.campaign_id = campaigns.id
                    ) AS harvest_unit_count,
                    (
                        SELECT COUNT(*)
                        FROM $harvests AS harvests
                        WHERE harvests.campaign_id = campaigns.id
                    ) AS harvest_count,
                    COALESCE((
                        SELECT SUM(harvests.quantity)
                        FROM $harvests AS harvests
                        WHERE harvests.campaign_id = campaigns.id
                    ), 0) AS harvested_quantity
             FROM $table AS campaigns
             LEFT JOIN $farms AS farms ON farms.id = campaigns.farm_id
             LEFT JOIN $parcels AS parcels ON parcels.id = campaigns.parcel_id
             LEFT JOIN $crops AS crops ON crops.id = campaigns.crop_id
             WHERE campaigns.archived_at IS NULL
               AND (campaigns.campaign_type = 'simulation' OR (farms.archived_at IS NULL AND parcels.archived_at IS NULL))
               AND (
                    (campaigns.campaign_type = 'simulation' AND campaigns.user_id = %d)
                    OR (
                        campaigns.campaign_type = 'real'
                        AND (
                            farms.user_id = %d
                            OR EXISTS (
                                SELECT 1 FROM $members AS members
                                WHERE members.farm_id = campaigns.farm_id
                                    AND members.member_user_id = %d
                            )
                        )
                    )
               )
             ORDER BY campaigns.created_at DESC, campaigns.id DESC",
            $user_id,
            $user_id,
            $user_id
        )
    );
    if ( '' !== $wpdb->last_error ) {
        return new WP_Error( 'campaign_list_failed', 'Impossible de charger les campagnes. Veuillez réessayer.' );
    }

    return $campaigns;
}

function baykat_farm_campaign_validate_link( $campaign_id, $parcel_id, $user_id, $crop_id = 0 ) {
    if ( ! $campaign_id ) {
        return 0;
    }

    global $wpdb;
    $campaign = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT campaigns.id, campaigns.campaign_type, campaigns.farm_id, campaigns.parcel_id, campaigns.crop_id
             FROM " . baykat_farm_campaign_table_name() . " AS campaigns
             INNER JOIN " . baykat_farm_table_name( 'farms' ) . " AS farms ON farms.id = campaigns.farm_id
             INNER JOIN " . baykat_farm_table_name( 'parcels' ) . " AS parcels ON parcels.id = campaigns.parcel_id
             WHERE campaigns.id = %d AND campaigns.archived_at IS NULL
                AND farms.archived_at IS NULL AND parcels.archived_at IS NULL",
            $campaign_id
        )
    );
    if ( '' !== $wpdb->last_error ) {
        return new WP_Error( 'campaign_lookup_failed', 'Impossible de vérifier la campagne sélectionnée. Veuillez réessayer.' );
    }
    if (
        ! $campaign
        || 'real' !== $campaign->campaign_type
        || (int) $campaign->parcel_id !== (int) $parcel_id
        || ! baykat_farm_user_can_edit_farm( (int) $campaign->farm_id, $user_id )
        || ( $crop_id && (int) $campaign->crop_id !== (int) $crop_id )
    ) {
        return new WP_Error( 'campaign_link_invalid', 'La campagne doit être réelle, modifiable et rattachée à la même parcelle et culture.' );
    }

    return (int) $campaign->id;
}

function baykat_farm_campaign_decimal_input( $key ) {
    if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) {
        return null;
    }

    $value = trim( (string) wp_unslash( $_POST[ $key ] ) );

    if ( '' === $value || ! is_numeric( $value ) ) {
        return null;
    }

    return (float) $value;
}

function baykat_farm_campaign_create( $user_id, $source_simulation = null ) {
    if ( ! isset( $_POST['baykat_add_campaign'] ) && ! isset( $_POST['baykat_create_from_simulation'] ) ) {
        return null;
    }

    $is_conversion = isset( $_POST['baykat_create_from_simulation'] );
    check_admin_referer( $is_conversion ? 'baykat_create_from_simulation' : 'baykat_add_campaign' );

    $campaign_type = $is_conversion ? 'real' : (
        isset( $_POST['campaign_type'] ) && is_string( $_POST['campaign_type'] )
            ? sanitize_key( wp_unslash( $_POST['campaign_type'] ) )
            : ''
    );
    if ( ! in_array( $campaign_type, array( 'real', 'simulation' ), true ) ) {
        return array( 'type' => 'error', 'message' => 'Choisissez un type de campagne valide.' );
    }

    $source_id = $is_conversion ? baykat_farm_request_id( 'source_simulation_id' ) : 0;
    if ( $is_conversion ) {
        global $wpdb;
        $source_simulation = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . baykat_farm_campaign_table_name() . " WHERE id = %d AND user_id = %d AND campaign_type = 'simulation' AND archived_at IS NULL",
                $source_id,
                $user_id
            )
        );
        if ( ! $source_simulation || '' !== $wpdb->last_error ) {
            return array( 'type' => 'error', 'message' => 'La simulation sélectionnée est introuvable.' );
        }
    }

    if ( $is_conversion ) {
        $crop_name = $source_simulation->crop_name;
        $crop_reference_id = empty( $source_simulation->crop_reference_id ) ? null : (int) $source_simulation->crop_reference_id;
        if ( ! $crop_reference_id ) {
            $catalog = baykat_farm_crop_catalog_get_active();
            if ( is_wp_error( $catalog ) ) {
                return array( 'type' => 'error', 'message' => $catalog->get_error_message() );
            }
            $matching_crop = baykat_farm_crop_catalog_find_duplicate( $crop_name, $catalog );
            if ( ! $matching_crop || 'same' !== $matching_crop['type'] ) {
                return array( 'type' => 'error', 'message' => 'Cette ancienne simulation ne correspond pas à une culture active du référentiel BAYKAT. Ajoutez ou réactivez d’abord la culture canonique, puis créez une nouvelle simulation avec ce choix.' );
            }
            $crop_reference_id = (int) $matching_crop['culture']->id;
            $crop_name = $matching_crop['culture']->name;
        } else {
            global $wpdb;
            $catalog_crop = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT id, name FROM ' . baykat_farm_crop_catalog_table_name() . ' WHERE id = %d AND is_active = 1',
                    $crop_reference_id
                )
            );
            if ( '' !== $wpdb->last_error ) {
                return array( 'type' => 'error', 'message' => 'Impossible de vérifier la culture liée à cette simulation.' );
            }
            if ( ! $catalog_crop ) {
                return array( 'type' => 'error', 'message' => 'La culture de cette simulation n’est plus active. Réactivez-la dans le référentiel BAYKAT avant de la convertir.' );
            }
            $crop_name = $catalog_crop->name;
        }
        $variety = $source_simulation->variety;
        $area_ha = (float) $source_simulation->area_ha;
        $start_date = $source_simulation->planned_start_date;
        $harvest_date = $source_simulation->planned_harvest_date;
    } else {
        $crop_choice = isset( $_POST['campaign_crop_reference_id'] ) && is_scalar( $_POST['campaign_crop_reference_id'] )
            ? trim( (string) wp_unslash( $_POST['campaign_crop_reference_id'] ) )
            : '';
        $crop_reference_id = ctype_digit( $crop_choice ) ? (int) $crop_choice : 0;
        $crop_name = '';
        if ( ! $crop_reference_id ) {
            return array( 'type' => 'error', 'message' => 'Choisissez une culture du référentiel BAYKAT. Si elle manque, elle doit d’abord y être ajoutée par un administrateur.' );
        }
        global $wpdb;
        $crop_name = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT name FROM ' . baykat_farm_crop_catalog_table_name() . ' WHERE id = %d AND is_active = 1',
                $crop_reference_id
            )
        );
        if ( '' !== $wpdb->last_error ) {
            return array( 'type' => 'error', 'message' => 'Impossible de vérifier la culture choisie.' );
        }
        if ( ! $crop_name ) {
            return array( 'type' => 'error', 'message' => 'Choisissez une culture active dans le référentiel BAYKAT.' );
        }
        $variety = isset( $_POST['campaign_variety'] ) && is_string( $_POST['campaign_variety'] )
            ? sanitize_text_field( wp_unslash( $_POST['campaign_variety'] ) )
            : '';
        $area_ha = baykat_farm_campaign_decimal_input( 'campaign_area_ha' );
        $start_date = isset( $_POST['campaign_start_date'] ) && is_string( $_POST['campaign_start_date'] )
            ? sanitize_text_field( wp_unslash( $_POST['campaign_start_date'] ) )
            : '';
        $harvest_date = isset( $_POST['campaign_harvest_date'] ) && is_string( $_POST['campaign_harvest_date'] )
            ? sanitize_text_field( wp_unslash( $_POST['campaign_harvest_date'] ) )
            : '';
    }

    $expected_yield = $is_conversion
        ? ( null === $source_simulation->expected_yield_kg_ha ? null : (float) $source_simulation->expected_yield_kg_ha )
        : ( 'simulation' === $campaign_type ? baykat_farm_campaign_decimal_input( 'expected_yield_kg_ha' ) : null );
    $expected_cost = $is_conversion
        ? ( null === $source_simulation->expected_cost ? null : (float) $source_simulation->expected_cost )
        : ( 'simulation' === $campaign_type ? baykat_farm_campaign_decimal_input( 'expected_cost' ) : null );
    $expected_price = $is_conversion
        ? ( null === $source_simulation->expected_price_per_kg ? null : (float) $source_simulation->expected_price_per_kg )
        : ( 'simulation' === $campaign_type ? baykat_farm_campaign_decimal_input( 'expected_price_per_kg' ) : null );
    $expected_available_funds = $is_conversion
        ? ( null === $source_simulation->expected_available_funds ? null : (float) $source_simulation->expected_available_funds )
        : ( 'simulation' === $campaign_type ? baykat_farm_campaign_decimal_input( 'expected_available_funds' ) : null );
    $expected_tools_needed = $is_conversion
        ? (string) $source_simulation->expected_tools_needed
        : ( 'simulation' === $campaign_type && isset( $_POST['expected_tools_needed'] ) && is_string( $_POST['expected_tools_needed'] )
            ? sanitize_textarea_field( wp_unslash( $_POST['expected_tools_needed'] ) )
            : '' );
    $expected_tools_available = $is_conversion
        ? (string) $source_simulation->expected_tools_available
        : ( 'simulation' === $campaign_type && isset( $_POST['expected_tools_available'] ) && is_string( $_POST['expected_tools_available'] )
            ? sanitize_textarea_field( wp_unslash( $_POST['expected_tools_available'] ) )
            : '' );

    if (
        '' === $crop_name
        || strlen( $crop_name ) > 190
        || strlen( $variety ) > 190
        || null === $area_ha
        || $area_ha <= 0
        || $area_ha > 99999999
        || ( '' !== $start_date && ! baykat_farm_valid_date( $start_date ) )
        || ( '' !== $harvest_date && ! baykat_farm_valid_date( $harvest_date ) )
        || ( '' !== $start_date && '' !== $harvest_date && $harvest_date < $start_date )
        || ( 'simulation' === $campaign_type && ( null === $expected_yield || null === $expected_cost || null === $expected_price || null === $expected_available_funds || $expected_yield <= 0 || $expected_cost < 0 || $expected_price <= 0 || $expected_available_funds < 0 ) )
        || ( null !== $expected_yield && $expected_yield > 999999999999.999 )
        || $expected_cost > 999999999999.99
        || ( null !== $expected_price && $expected_price > 999999999999.999 )
        || ( null !== $expected_available_funds && $expected_available_funds > 999999999999.99 )
        || strlen( $expected_tools_needed ) > 4000
        || strlen( $expected_tools_available ) > 4000
        || count( preg_split( '/\r\n|\r|\n/', trim( $expected_tools_needed ), -1, PREG_SPLIT_NO_EMPTY ) ) > 40
        || count( preg_split( '/\r\n|\r|\n/', trim( $expected_tools_available ), -1, PREG_SPLIT_NO_EMPTY ) ) > 40
    ) {
        return array( 'type' => 'error', 'message' => 'Vérifiez la culture, la superficie, les dates et les hypothèses de la simulation.' );
    }

    $farm_id = null;
    $parcel_id = null;
    $crop_id = null;
    $production_status = 'planned';
    if ( 'real' === $campaign_type ) {
        $farm_id = baykat_farm_request_id( 'campaign_farm_id' );
        $parcel_id = baykat_farm_request_id( 'campaign_parcel_id' );
        $parcel = baykat_farm_get_user_parcel( $parcel_id, $user_id );
        if (
            ! $parcel
            || (int) $parcel->farm_id !== $farm_id
            || ! baykat_farm_user_can_edit_farm( $farm_id, $user_id )
            || ( null !== $parcel->area_m2 && $area_ha > ( (float) $parcel->area_m2 / 10000 ) + 0.0001 )
        ) {
            return array( 'type' => 'error', 'message' => 'Choisissez une ferme et une parcelle autorisées, et vérifiez que la superficie cultivée ne dépasse pas celle de la parcelle.' );
        }

        global $wpdb;
        $crop_inserted = $wpdb->insert(
            baykat_farm_table_name( 'crops' ),
            array(
                'user_id'               => (int) $parcel->user_id,
                'parcel_id'             => $parcel_id,
                'name'                  => $crop_name,
                'variety'               => $variety,
                'planted_at'            => '' === $start_date ? null : $start_date,
                'expected_harvest_date' => '' === $harvest_date ? null : $harvest_date,
                'status'                => 'planned',
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
        );
        if ( false === $crop_inserted ) {
            return array( 'type' => 'error', 'message' => 'La culture réelle n’a pas pu être créée.' );
        }
        $crop_id = (int) $wpdb->insert_id;
    }

    global $wpdb;
    $table = baykat_farm_campaign_table_name();
    $inserted = $wpdb->insert(
        $table,
        array(
            'user_id'                    => $user_id,
            'campaign_type'              => $campaign_type,
            'farm_id'                    => $farm_id,
            'parcel_id'                  => $parcel_id,
            'crop_id'                    => $crop_id,
            'crop_reference_id'          => $crop_reference_id,
            'source_simulation_id'       => $source_id ? $source_id : null,
            'crop_name'                  => $crop_name,
            'variety'                    => $variety,
            'area_ha'                    => $area_ha,
            'planned_start_date'         => '' === $start_date ? null : $start_date,
            'planned_harvest_date'       => '' === $harvest_date ? null : $harvest_date,
            'expected_yield_kg_ha'       => $expected_yield,
            'expected_cost'              => $expected_cost,
            'expected_price_per_kg'      => $expected_price,
            'expected_available_funds'   => $expected_available_funds,
            'expected_tools_needed'      => '' === $expected_tools_needed ? null : $expected_tools_needed,
            'expected_tools_available'   => '' === $expected_tools_available ? null : $expected_tools_available,
            'production_status'          => $production_status,
            'commercialization_status'  => 'planned',
        ),
        array( '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s' )
    );

    if ( false === $inserted ) {
        if ( $crop_id ) {
            $wpdb->delete( baykat_farm_table_name( 'crops' ), array( 'id' => $crop_id ), array( '%d' ) );
        }
        return array( 'type' => 'error', 'message' => 'La campagne n’a pas pu être enregistrée.' );
    }

    return array(
        'type' => 'success',
        'message' => 'simulation' === $campaign_type
            ? 'Simulation enregistrée. Elle reste distincte des campagnes réelles.'
            : 'Campagne réelle créée. La simulation source est conservée uniquement comme prévision.',
    );
}

function baykat_farm_campaign_update_phase( $user_id ) {
    if ( ! isset( $_POST['baykat_update_campaign_phase'] ) ) {
        return null;
    }

    check_admin_referer( 'baykat_update_campaign_phase' );
    $campaign_id = baykat_farm_request_id( 'campaign_id' );
    $phase = isset( $_POST['campaign_phase'] ) && is_string( $_POST['campaign_phase'] )
        ? sanitize_key( wp_unslash( $_POST['campaign_phase'] ) )
        : '';
    $status = isset( $_POST['phase_status'] ) && is_string( $_POST['phase_status'] )
        ? sanitize_key( wp_unslash( $_POST['phase_status'] ) )
        : '';

    if ( ! $campaign_id || ! in_array( $phase, array( 'production', 'commercialization' ), true ) || ! in_array( $status, array( 'planned', 'in_progress', 'completed', 'paused' ), true ) ) {
        return array( 'type' => 'error', 'message' => 'Le statut de phase sélectionné est invalide.' );
    }

    global $wpdb;
    $table = baykat_farm_campaign_table_name();
    $campaign = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, campaign_type, farm_id, production_status, commercialization_status FROM $table WHERE id = %d AND archived_at IS NULL",
            $campaign_id
        )
    );
    if ( ! $campaign || '' !== $wpdb->last_error || 'real' !== $campaign->campaign_type || ! baykat_farm_user_can_edit_farm( (int) $campaign->farm_id, $user_id ) ) {
        return array( 'type' => 'error', 'message' => 'Cette campagne réelle est introuvable ou non modifiable.' );
    }
    if ( 'commercialization' === $phase && 'completed' !== $campaign->production_status ) {
        return array( 'type' => 'error', 'message' => 'Terminez la phase de production avant de démarrer la commercialisation.' );
    }
    if ( 'production' === $phase && 'completed' !== $status && 'planned' !== $campaign->commercialization_status ) {
        return array( 'type' => 'error', 'message' => 'La phase de production ne peut plus être réouverte après le démarrage de la commercialisation.' );
    }

    $column = 'production' === $phase ? 'production_status' : 'commercialization_status';
    $updated = $wpdb->update(
        $table,
        array( $column => $status ),
        array( 'id' => $campaign_id ),
        array( '%s' ),
        array( '%d' )
    );
    if ( false === $updated ) {
        return array( 'type' => 'error', 'message' => 'Le statut de la phase n’a pas pu être mis à jour.' );
    }

    return array( 'type' => 'success', 'message' => 'Statut de phase mis à jour.' );
}

function baykat_farm_render_campaigns() {
    $user_id = get_current_user_id();
    $message = baykat_farm_process_campaign_stock_action( $user_id );
    if ( ! $message ) {
        $message = baykat_farm_process_crop_harvest( $user_id );
    }
    if ( ! $message ) {
        $message = baykat_farm_process_archive_submission( $user_id );
    }
    if ( ! $message ) {
        $message = baykat_farm_campaign_update_phase( $user_id );
    }
    if ( ! $message ) {
        $message = baykat_farm_campaign_create( $user_id );
    }

    $farms = baykat_farm_get_user_farms( $user_id );
    $editable_farms = baykat_farm_filter_editable_records( $farms );
    $parcels = baykat_farm_filter_editable_records( baykat_farm_get_user_parcels( $user_id ) );
    $crop_catalog = baykat_farm_crop_catalog_get_active();
    if ( is_wp_error( $crop_catalog ) ) {
        if ( ! $message ) {
            $message = array( 'type' => 'error', 'message' => $crop_catalog->get_error_message() );
        }
        $crop_catalog = array();
    }
    $campaigns = baykat_farm_campaign_get_user_campaigns( $user_id );
    if ( is_wp_error( $campaigns ) ) {
        if ( ! $message ) {
            $message = array( 'type' => 'error', 'message' => $campaigns->get_error_message() );
        }
        $campaigns = array();
    }
    $simulations = array_values(
        array_filter(
            $campaigns,
            function ( $campaign ) {
                return 'simulation' === $campaign->campaign_type;
            }
        )
    );
    $campaigns = array_values(
        array_filter(
            $campaigns,
            function ( $campaign ) {
                return 'real' === $campaign->campaign_type;
            }
        )
    );
    ?>
    <div class="baykat-farm-content">
        <header class="baykat-farm-section-heading">
            <span class="baykat-farm-eyebrow">Planifier et suivre</span>
            <h2>Campagnes</h2>
            <p>Une campagne correspond à une culture, une parcelle et une saison. Suivez d’abord la production, puis le stock récolté et sa commercialisation.</p>
        </header>
        <?php if ( $message ) : ?>
            <div class="baykat-farm-notice baykat-farm-notice-<?php echo esc_attr( $message['type'] ); ?>" role="<?php echo 'error' === $message['type'] ? 'alert' : 'status'; ?>"><?php echo esc_html( $message['message'] ); ?></div>
        <?php endif; ?>

        <section class="baykat-farm-panel baykat-farm-add-panel" aria-labelledby="baykat-campaign-create-title">
            <details class="baykat-farm-add-form">
                <summary id="baykat-campaign-create-title"><span class="baykat-farm-eyebrow">Commencer un suivi</span><strong>Créer une campagne</strong><span class="baykat-farm-add-form-icon" aria-hidden="true">+</span></summary>
                <p class="baykat-campaign-form-intro">Choisissez une culture du référentiel BAYKAT. Si elle manque, un administrateur doit d’abord l’ajouter afin que toutes les campagnes réutilisent le même nom canonique.</p>
                <?php if ( ! $crop_catalog ) : ?>
                    <p class="baykat-farm-notice baykat-farm-notice-error" role="alert">Aucune culture BAYKAT n’est encore active. Le référentiel doit être renseigné avant de créer une campagne ou une simulation.</p>
                <?php endif; ?>
                <form class="baykat-farm-form" method="post">
                    <?php wp_nonce_field( 'baykat_add_campaign', '_wpnonce' ); ?>
                    <div class="baykat-farm-field baykat-farm-field-wide">
                        <label for="baykat_campaign_type">Type</label>
                        <select name="campaign_type" id="baykat_campaign_type" required>
                            <option value="simulation">Simulation — tester un projet</option>
                            <option value="real">Campagne réelle — suivre une production</option>
                        </select>
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_campaign_crop_reference">Culture BAYKAT</label>
                        <select name="campaign_crop_reference_id" id="baykat_campaign_crop_reference" required <?php disabled( ! $crop_catalog ); ?>>
                            <option value="">Choisir une culture</option>
                            <?php foreach ( $crop_catalog as $culture ) : ?>
                                <option value="<?php echo esc_attr( $culture->id ); ?>"><?php echo esc_html( $culture->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_campaign_variety">Variété (facultatif)</label>
                        <input type="text" name="campaign_variety" id="baykat_campaign_variety" maxlength="190">
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_campaign_area_ha">Superficie (ha)</label>
                        <input type="number" name="campaign_area_ha" id="baykat_campaign_area_ha" min="0.0001" step="0.0001" required>
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_campaign_start_date">Début prévu (facultatif)</label>
                        <input type="date" name="campaign_start_date" id="baykat_campaign_start_date">
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_campaign_harvest_date">Récolte prévue (facultatif)</label>
                        <input type="date" name="campaign_harvest_date" id="baykat_campaign_harvest_date">
                    </div>
                    <div class="baykat-farm-field baykat-campaign-simulation-field">
                        <label for="baykat_expected_yield">Rendement attendu (kg/ha)</label>
                        <input type="number" name="expected_yield_kg_ha" id="baykat_expected_yield" min="0.001" step="0.001">
                    </div>
                    <div class="baykat-farm-field baykat-campaign-simulation-field">
                        <label for="baykat_expected_cost">Coûts prévus (FCFA)</label>
                        <input type="number" name="expected_cost" id="baykat_expected_cost" min="0" step="0.01">
                    </div>
                    <div class="baykat-farm-field baykat-campaign-simulation-field">
                        <label for="baykat_expected_price">Prix de vente espéré (FCFA/kg)</label>
                        <input type="number" name="expected_price_per_kg" id="baykat_expected_price" min="0.01" step="0.01">
                    </div>
                    <div class="baykat-farm-field baykat-campaign-simulation-field">
                        <label for="baykat_expected_available_funds">Fonds déjà disponibles (FCFA)</label>
                        <input type="number" name="expected_available_funds" id="baykat_expected_available_funds" min="0" step="0.01" value="0">
                        <small>Trésorerie ou financement déjà confirmé pour ce projet.</small>
                    </div>
                    <div class="baykat-farm-field baykat-farm-field-wide baykat-campaign-simulation-field">
                        <label for="baykat_expected_tools_needed">Outils et intrants à prévoir (un élément par ligne)</label>
                        <textarea name="expected_tools_needed" id="baykat_expected_tools_needed" rows="3" maxlength="4000" placeholder="Ex. matériel d’irrigation, semences, outils de préparation"></textarea>
                    </div>
                    <div class="baykat-farm-field baykat-farm-field-wide baykat-campaign-simulation-field">
                        <label for="baykat_expected_tools_available">Outils et intrants déjà disponibles (un élément par ligne)</label>
                        <textarea name="expected_tools_available" id="baykat_expected_tools_available" rows="3" maxlength="4000" placeholder="Listez ce que vous possédez ou avez déjà sécurisé"></textarea>
                    </div>
                    <section class="baykat-campaign-quote-preview baykat-campaign-simulation-field" data-simulation-quote-preview aria-live="polite" hidden>
                        <div class="baykat-campaign-quote-heading">
                            <div><span class="baykat-farm-eyebrow">Estimation basée sur vos hypothèses</span><h3>Aperçu de votre devis agricole</h3></div>
                            <span class="baykat-campaign-quote-badge">Calcul déterministe</span>
                        </div>
                        <p class="baykat-campaign-quote-explainer">Ce calcul ne remplace pas un itinéraire technique validé : le rendement, les coûts et le prix viennent de vos saisies. Modifiez-les pour tester d’autres scénarios.</p>
                        <div class="baykat-campaign-quote-metrics">
                            <div><span>Récolte estimée</span><strong data-quote-harvest>—</strong></div>
                            <div><span>Recettes estimées</span><strong data-quote-revenue>—</strong></div>
                            <div><span>Résultat simplifié</span><strong data-quote-result>—</strong></div>
                            <div><span>Financement à mobiliser</span><strong data-quote-funding-gap>—</strong></div>
                        </div>
                        <div class="baykat-campaign-quote-analysis" data-quote-analysis></div>
                        <div class="baykat-campaign-quote-thresholds">
                            <p><span>Prix minimum pour couvrir le budget</span><strong data-quote-break-even-price>—</strong></p>
                            <p><span>Rendement minimum au prix saisi</span><strong data-quote-break-even-yield>—</strong></p>
                        </div>
                        <div class="baykat-campaign-quote-tools">
                            <h4>Outils et intrants</h4>
                            <p data-quote-tools-summary>Précisez les besoins pour établir votre checklist.</p>
                            <ul data-quote-tools-missing></ul>
                        </div>
                    </section>
                    <div class="baykat-farm-field baykat-campaign-real-field baykat-farm-field-wide" hidden>
                        <label for="baykat_campaign_farm">Ferme</label>
                        <select name="campaign_farm_id" id="baykat_campaign_farm">
                            <option value="">Choisir une ferme</option>
                            <?php foreach ( $editable_farms as $farm ) : ?>
                                <option value="<?php echo esc_attr( $farm->id ); ?>"><?php echo esc_html( $farm->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="baykat-farm-field baykat-campaign-real-field baykat-farm-field-wide" hidden>
                        <label for="baykat_campaign_parcel">Parcelle</label>
                        <select name="campaign_parcel_id" id="baykat_campaign_parcel">
                            <option value="">Choisir une parcelle</option>
                            <?php foreach ( $parcels as $parcel ) : ?>
                                <option value="<?php echo esc_attr( $parcel->id ); ?>" data-farm-id="<?php echo esc_attr( $parcel->farm_id ); ?>"><?php echo esc_html( $parcel->farm_name . ' — ' . $parcel->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="baykat-finance-button" type="submit" name="baykat_add_campaign" value="1" <?php disabled( ! $crop_catalog ); ?>>Enregistrer</button>
                </form>
            </details>
        </section>

        <section class="baykat-farm-panel" aria-labelledby="baykat-campaign-list-title">
            <header class="baykat-farm-panel-heading">
                <div><span class="baykat-farm-eyebrow">Votre activité agricole</span><h3 id="baykat-campaign-list-title">Mes campagnes réelles</h3></div>
                <?php if ( $campaigns ) : ?><span class="baykat-campaign-count"><?php echo esc_html( count( $campaigns ) . ( 1 === count( $campaigns ) ? ' suivi' : ' suivis' ) ); ?></span><?php endif; ?>
            </header>
            <?php if ( $campaigns ) : ?>
                <div class="baykat-campaign-grid">
                    <?php foreach ( $campaigns as $campaign ) : ?>
                        <?php
                        $campaign_harvests = baykat_farm_get_campaign_harvests( (int) $campaign->id );
                        $stock_summary = baykat_farm_campaign_stock_get_summary( (int) $campaign->id );
                        $allocations = baykat_farm_campaign_get_allocations( (int) $campaign->id );
                        $stock_events = baykat_farm_campaign_get_stock_events( (int) $campaign->id );
                        $actual_income = (float) $campaign->actual_income;
                        $actual_expenses = (float) $campaign->actual_expenses;
                        $production_expenses = (float) $campaign->production_expenses;
                        $actual_result = $actual_income - $actual_expenses;
                        $actual_margin = $actual_income > 0 ? ( $actual_result / $actual_income ) * 100 : null;
                        $harvest_unit = baykat_farm_normalize_unit( $campaign->harvested_unit );
                        $has_consistent_harvest = (int) $campaign->harvest_count > 0
                            && 1 === (int) $campaign->harvest_unit_count
                            && '' !== $harvest_unit;
                        $actual_harvest = $has_consistent_harvest
                            ? baykat_farm_format_quantity( $campaign->harvested_quantity ) . ' ' . $harvest_unit
                            : 'Non disponible';
                        $actual_yield = $has_consistent_harvest && 'kg' === $harvest_unit
                            ? baykat_farm_format_quantity( (float) $campaign->harvested_kg / (float) $campaign->area_ha ) . ' kg/ha'
                            : 'Non disponible';
                        $production_cost_per_kg = $has_consistent_harvest
                            && 'kg' === $harvest_unit
                            && (float) $campaign->harvested_kg > 0
                                ? number_format_i18n( $production_expenses / (float) $campaign->harvested_kg, 2 ) . ' FCFA/kg'
                                : 'Non disponible';
                        $matching_simulations = array_values(
                            array_filter(
                                $simulations,
                                function ( $simulation ) use ( $campaign ) {
                                    return ( (int) $campaign->source_simulation_id > 0 && (int) $campaign->source_simulation_id === (int) $simulation->id )
                                        || ( ! empty( $campaign->crop_reference_id ) && (int) $campaign->crop_reference_id === (int) $simulation->crop_reference_id )
                                        || ( 0 === strcasecmp( trim( $campaign->crop_name ), trim( $simulation->crop_name ) ) );
                                }
                            )
                        );
                        ?>
                        <article id="baykat-campaign-record-<?php echo esc_attr( $campaign->id ); ?>" class="baykat-campaign-card">
                            <header class="baykat-campaign-card-heading">
                                <span class="baykat-campaign-type">Campagne réelle</span>
                                <h4><?php echo esc_html( $campaign->crop_name ); ?></h4>
                                <p class="baykat-campaign-context"><?php echo esc_html( $campaign->variety ? 'Variété ' . $campaign->variety : 'Variété non précisée' ); ?> <span aria-hidden="true">·</span> <?php echo esc_html( baykat_farm_format_quantity( $campaign->area_ha, 4 ) ); ?> ha</p>
                                <p class="baykat-campaign-context"><?php echo esc_html( $campaign->farm_name . ' / ' . $campaign->parcel_name ); ?></p>
                            </header>
                            <?php if ( $matching_simulations ) : ?>
                                <?php
                                $comparable_simulation_id = (int) $campaign->source_simulation_id;
                                if ( ! $comparable_simulation_id ) {
                                    $comparable_simulation_id = (int) $matching_simulations[0]->id;
                                }
                                ?>
                                <button
                                    class="baykat-campaign-compare-button"
                                    type="button"
                                    data-open-campaign-comparison="<?php echo esc_attr( $campaign->id ); ?>"
                                    data-source-simulation-id="<?php echo esc_attr( $comparable_simulation_id ); ?>"
                                    data-campaign-name="<?php echo esc_attr( $campaign->crop_name ); ?>"
                                    data-campaign-crop-name="<?php echo esc_attr( $campaign->crop_name ); ?>"
                                    data-campaign-crop-reference="<?php echo esc_attr( (int) $campaign->crop_reference_id ); ?>"
                                    data-campaign-area="<?php echo esc_attr( (float) $campaign->area_ha ); ?>"
                                    data-campaign-harvest="<?php echo esc_attr( $actual_harvest ); ?>"
                                    data-campaign-yield="<?php echo esc_attr( $has_consistent_harvest && 'kg' === $harvest_unit ? (float) $campaign->harvested_kg / (float) $campaign->area_ha : '' ); ?>"
                                    data-campaign-expenses="<?php echo esc_attr( $actual_expenses ); ?>"
                                    data-campaign-income="<?php echo esc_attr( $actual_income ); ?>"
                                    data-campaign-result="<?php echo esc_attr( $actual_result ); ?>"
                                >Comparer au prévisionnel</button>
                            <?php else : ?>
                                <button class="baykat-campaign-compare-button" type="button" data-create-comparison-simulation>Créer une simulation pour comparer</button>
                            <?php endif; ?>
                                <div class="baykat-campaign-overview">
                                    <div class="baykat-campaign-overview-heading">
                                        <div><span class="baykat-farm-eyebrow">Données clés de production</span><strong><?php echo esc_html( baykat_farm_format_quantity( $campaign->area_ha, 4 ) ); ?> ha cultivés</strong></div>
                                        <span class="baykat-campaign-status baykat-campaign-status-<?php echo esc_attr( $campaign->production_status ); ?>"><?php echo esc_html( baykat_farm_campaign_status_label( $campaign->production_status ) ); ?></span>
                                    </div>
                                    <div class="baykat-campaign-dashboard">
                                        <div><span>Surface cultivée</span><strong><?php echo esc_html( baykat_farm_format_quantity( $campaign->area_ha, 4 ) ); ?> ha</strong></div>
                                        <div><span>Récolte enregistrée</span><strong><?php echo esc_html( $actual_harvest ); ?></strong></div>
                                        <div><span>Rendement obtenu</span><strong><?php echo esc_html( $actual_yield ); ?></strong></div>
                                        <div><span>Résultat simplifié</span><strong><?php echo esc_html( number_format_i18n( $actual_result, 0 ) ); ?> FCFA</strong></div>
                                    </div>
                                </div>
                                <details class="baykat-campaign-more">
                                    <summary>Voir le détail financier et les indicateurs</summary>
                                    <div class="baykat-campaign-dashboard">
                                        <div><span>Dépenses totales suivies</span><strong><?php echo esc_html( number_format_i18n( $actual_expenses, 0 ) ); ?> FCFA</strong></div>
                                        <div><span>Coût de production par kg</span><strong><?php echo esc_html( $production_cost_per_kg ); ?></strong></div>
                                        <div><span>Revenus enregistrés</span><strong><?php echo esc_html( number_format_i18n( $actual_income, 0 ) ); ?> FCFA</strong></div>
                                        <div><span>Marge sur revenus</span><strong><?php echo null === $actual_margin ? 'Non disponible' : esc_html( number_format_i18n( $actual_margin, 1 ) . ' %' ); ?></strong></div>
                                        <div><span>Éléments suivis</span><strong><?php echo esc_html( (int) $campaign->activity_count . ' activité(s) · ' . (int) $campaign->transaction_count . ' opération(s)' ); ?></strong></div>
                                    </div>
                                    <p class="baykat-campaign-dashboard-note">Le résultat est un indicateur simplifié, pas un bénéfice comptable. Les calculs utilisent uniquement les opérations rattachées à cette campagne. Les dépenses de commercialisation sont exclues du coût de production par kg.</p>
                                </details>
                                <section class="baykat-campaign-stock" aria-label="Stock récolté et allocations">
                                    <div class="baykat-campaign-section-heading"><div><span class="baykat-farm-eyebrow">Après la récolte</span><h5>Stock et destinations</h5></div><p>Une réservation met du stock de côté; elle ne constitue pas une vente.</p></div>
                                    <?php if ( is_wp_error( $stock_summary ) ) : ?>
                                        <div class="baykat-farm-notice baykat-farm-notice-error" role="alert"><?php echo esc_html( $stock_summary->get_error_message() ); ?></div>
                                    <?php elseif ( is_wp_error( $campaign_harvests ) ) : ?>
                                        <div class="baykat-farm-notice baykat-farm-notice-error" role="alert"><?php echo esc_html( $campaign_harvests->get_error_message() ); ?></div>
                                    <?php elseif ( ! $campaign_harvests ) : ?>
                                        <p class="baykat-farm-empty-state">Aucune récolte réelle enregistrée. Les prévisions de campagne ne créent pas de stock.</p>
                                    <?php elseif ( ! $stock_summary['consistent'] ) : ?>
                                        <div class="baykat-farm-notice baykat-farm-notice-error" role="alert">
                                            <?php
                                            echo esc_html(
                                                $stock_summary['legacy_ambiguous_sales'] > 0
                                                    ? 'Le stock ne peut pas être alloué : le rattachement campagne/culture de certaines ventes historiques n’est pas vérifiable. Elles ne sont pas redistribuées automatiquement.'
                                                    : 'Le stock ne peut pas être alloué : vérifiez les unités, les récoltes, les ventes historiques et les mouvements enregistrés.'
                                            );
                                            ?>
                                        </div>
                                    <?php else : ?>
                                        <div class="baykat-campaign-stock-grid">
                                            <div><span>Récolté au total</span><strong><?php echo esc_html( baykat_farm_format_quantity( $stock_summary['harvested_quantity'] ) . ' ' . $stock_summary['unit'] ); ?></strong></div>
                                            <div class="baykat-campaign-stock-available"><span>Libre et disponible</span><strong><?php echo esc_html( baykat_farm_format_quantity( $stock_summary['available_quantity'] ) . ' ' . $stock_summary['unit'] ); ?></strong></div>
                                            <div><span>Réservé pour la commercialisation</span><strong><?php echo esc_html( baykat_farm_format_quantity( $stock_summary['commercialization_allocated_quantity'] ) . ' ' . $stock_summary['unit'] ); ?></strong></div>
                                            <div><span>Réservé pour autre destination</span><strong><?php echo esc_html( baykat_farm_format_quantity( max( 0, $stock_summary['allocated_quantity'] - $stock_summary['commercialization_allocated_quantity'] ) ) . ' ' . $stock_summary['unit'] ); ?></strong></div>
                                            <div><span>Vendu / sorties de vente enregistrées</span><strong><?php echo esc_html( baykat_farm_format_quantity( $stock_summary['sold_quantity'] ) . ' ' . $stock_summary['unit'] ); ?></strong></div>
                                            <div><span>Sorties nettes (pertes incluses)</span><strong><?php echo esc_html( baykat_farm_format_quantity( $stock_summary['net_physical_adjustments'] ) . ' ' . $stock_summary['unit'] ); ?></strong></div>
                                            <div><span>Stock physique restant</span><strong><?php echo esc_html( baykat_farm_format_quantity( $stock_summary['physical_quantity'] ) . ' ' . $stock_summary['unit'] ); ?></strong></div>
                                            <?php if ( (float) $stock_summary['manual_sales_quantity'] > 0 ) : ?>
                                                <div><span>Anciennes ventes manuelles (non annulées)</span><strong><?php echo esc_html( baykat_farm_format_quantity( $stock_summary['manual_sales_quantity'] ) . ' ' . $stock_summary['unit'] ); ?></strong></div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ( ! is_wp_error( $campaign_harvests ) && (float) $stock_summary['manual_sales_quantity'] > 0 && count( $campaign_harvests ) > 1 ) : ?>
                                            <p class="baykat-campaign-dashboard-note">Les ventes manuelles historiques sont rattachées à la campagne, pas à une récolte précise. Elles sont déduites une seule fois du stock total de la campagne et ne sont pas attribuées rétroactivement à un lot.</p>
                                        <?php endif; ?>
                                        <?php if ( is_wp_error( $campaign_harvests ) ) : ?>
                                            <div class="baykat-farm-notice baykat-farm-notice-error" role="alert"><?php echo esc_html( $campaign_harvests->get_error_message() ); ?></div>
                                        <?php elseif ( baykat_farm_user_can_edit_farm( (int) $campaign->farm_id, $user_id ) ) : ?>
                                            <details class="baykat-campaign-stock-action">
                                                <summary>Réserver du stock</summary>
                                                <p class="baykat-campaign-action-help">Indiquez le lot récolté, la quantité et sa destination. Une réservation n’est pas une annonce ni une vente.</p>
                                                <?php if ( 'completed' !== $campaign->production_status || 'in_progress' !== $campaign->commercialization_status ) : ?>
                                                    <p class="baykat-farm-empty-state">Pour réserver du stock, terminez d’abord la production, puis passez la commercialisation à « En cours ».</p>
                                                <?php elseif ( ! is_wp_error( $campaign_harvests ) && $campaign_harvests && (float) $stock_summary['available_quantity'] > 0 ) : ?>
                                                    <form method="post" class="baykat-farm-form">
                                                        <?php wp_nonce_field( 'baykat_create_campaign_allocation', '_wpnonce' ); ?>
                                                        <input type="hidden" name="campaign_id" value="<?php echo esc_attr( $campaign->id ); ?>">
                                                        <input type="hidden" name="stock_action_key" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>">
                                                        <div class="baykat-farm-field baykat-farm-field-wide">
                                                            <label for="baykat_allocation_harvest_<?php echo esc_attr( $campaign->id ); ?>">Récolte source</label>
                                                            <select name="harvest_id" id="baykat_allocation_harvest_<?php echo esc_attr( $campaign->id ); ?>" required>
                                                                <option value="">Choisir un lot récolté</option>
                                                                <?php foreach ( $campaign_harvests as $harvest ) : ?>
                                                                    <option value="<?php echo esc_attr( $harvest->id ); ?>"><?php echo esc_html( ( $harvest->harvest_date ? $harvest->harvest_date : 'Date non renseignée' ) . ' — ' . baykat_farm_format_quantity( $harvest->quantity ) . ' ' . $harvest->unit ); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="baykat-farm-field">
                                                            <label for="baykat_allocation_quantity_<?php echo esc_attr( $campaign->id ); ?>">Quantité à réserver (<?php echo esc_html( $stock_summary['unit'] ); ?>)</label>
                                                            <input type="number" name="stock_quantity" id="baykat_allocation_quantity_<?php echo esc_attr( $campaign->id ); ?>" min="0.001" max="<?php echo esc_attr( $stock_summary['available_quantity'] ); ?>" step="0.001" required>
                                                        </div>
                                                        <div class="baykat-farm-field">
                                                            <label for="baykat_allocation_destination_<?php echo esc_attr( $campaign->id ); ?>">Destination</label>
                                                            <select name="destination_type" id="baykat_allocation_destination_<?php echo esc_attr( $campaign->id ); ?>" data-stock-destination-select required>
                                                                <option value="commercialization">Commercialisation</option>
                                                                <option value="other">Autre destination</option>
                                                            </select>
                                                        </div>
                                                        <div class="baykat-farm-field baykat-farm-field-wide">
                                                            <label for="baykat_allocation_label_<?php echo esc_attr( $campaign->id ); ?>">Précision sur la destination (requise pour « Autre »)</label>
                                                            <input type="text" name="destination_label" id="baykat_allocation_label_<?php echo esc_attr( $campaign->id ); ?>" data-stock-destination-label maxlength="190">
                                                        </div>
                                                        <div class="baykat-farm-field baykat-farm-field-wide">
                                                            <label for="baykat_allocation_notes_<?php echo esc_attr( $campaign->id ); ?>">Notes (facultatif)</label>
                                                            <textarea name="stock_notes" id="baykat_allocation_notes_<?php echo esc_attr( $campaign->id ); ?>" maxlength="2000" rows="2"></textarea>
                                                        </div>
                                                        <button class="baykat-finance-button" type="submit" name="baykat_create_campaign_allocation" value="1">Confirmer la réservation</button>
                                                    </form>
                                                <?php else : ?>
                                                    <p class="baykat-farm-empty-state">Aucun stock récolté libre à réserver.</p>
                                                <?php endif; ?>
                                            </details>
                                            <?php if ( ! is_wp_error( $campaign_harvests ) && $campaign_harvests && (float) $stock_summary['physical_quantity'] > 0 ) : ?>
                                                <details class="baykat-campaign-stock-action">
                                                    <summary>Déclarer une perte ou une sortie</summary>
                                                    <p class="baykat-campaign-action-help">À utiliser seulement si une quantité a réellement quitté le stock (perte, détérioration ou autre motif). Un motif est obligatoire.</p>
                                                    <form method="post" class="baykat-farm-form">
                                                        <?php wp_nonce_field( 'baykat_record_campaign_stock_adjustment', '_wpnonce' ); ?>
                                                        <input type="hidden" name="campaign_id" value="<?php echo esc_attr( $campaign->id ); ?>">
                                                        <input type="hidden" name="stock_action_key" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>">
                                                        <div class="baykat-farm-field baykat-farm-field-wide">
                                                            <label for="baykat_stock_event_harvest_<?php echo esc_attr( $campaign->id ); ?>">Récolte concernée</label>
                                                            <select name="harvest_id" id="baykat_stock_event_harvest_<?php echo esc_attr( $campaign->id ); ?>" required>
                                                                <option value="">Choisir un lot récolté</option>
                                                                <?php foreach ( $campaign_harvests as $harvest ) : ?>
                                                                    <option value="<?php echo esc_attr( $harvest->id ); ?>"><?php echo esc_html( ( $harvest->harvest_date ? $harvest->harvest_date : 'Date non renseignée' ) . ' — ' . baykat_farm_format_quantity( $harvest->quantity ) . ' ' . $harvest->unit ); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="baykat-farm-field">
                                                            <label for="baykat_stock_event_type_<?php echo esc_attr( $campaign->id ); ?>">Type de sortie</label>
                                                            <select name="stock_event_type" id="baykat_stock_event_type_<?php echo esc_attr( $campaign->id ); ?>" required>
                                                                <option value="loss">Perte physique</option>
                                                                <option value="other_out">Autre sortie justifiée</option>
                                                            </select>
                                                        </div>
                                                        <div class="baykat-farm-field">
                                                            <label for="baykat_stock_event_quantity_<?php echo esc_attr( $campaign->id ); ?>">Quantité (<?php echo esc_html( $stock_summary['unit'] ); ?>)</label>
                                                            <input type="number" name="stock_quantity" id="baykat_stock_event_quantity_<?php echo esc_attr( $campaign->id ); ?>" min="0.001" max="<?php echo esc_attr( $stock_summary['available_quantity'] ); ?>" step="0.001" required>
                                                        </div>
                                                        <div class="baykat-farm-field baykat-farm-field-wide">
                                                            <label for="baykat_stock_event_notes_<?php echo esc_attr( $campaign->id ); ?>">Motif obligatoire</label>
                                                            <textarea name="stock_notes" id="baykat_stock_event_notes_<?php echo esc_attr( $campaign->id ); ?>" maxlength="2000" rows="2" required></textarea>
                                                        </div>
                                                        <button class="baykat-finance-button" type="submit" name="baykat_record_campaign_stock_adjustment" value="1">Confirmer la sortie du stock</button>
                                                    </form>
                                                </details>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ( is_wp_error( $allocations ) ) : ?>
                                            <div class="baykat-farm-notice baykat-farm-notice-error" role="alert"><?php echo esc_html( $allocations->get_error_message() ); ?></div>
                                        <?php elseif ( $allocations ) : ?>
                                            <details class="baykat-campaign-allocation-disclosure">
                                                <summary>Voir les allocations enregistrées <span><?php echo esc_html( count( $allocations ) ); ?></span></summary>
                                                <div class="baykat-campaign-allocation-list">
                                                    <?php foreach ( $allocations as $allocation ) : ?>
                                                        <article>
                                                            <div>
                                                                <strong><?php echo esc_html( 'commercialization' === $allocation->destination_type ? 'Commercialisation' : ( $allocation->destination_label ? $allocation->destination_label : 'Autre destination' ) ); ?></strong>
                                                                <span><?php echo esc_html( ( $allocation->harvest_date ? $allocation->harvest_date : 'Date non renseignée' ) . ' · Réservé : ' . baykat_farm_format_quantity( $allocation->quantity ) . ' ' . $stock_summary['unit'] . ' · Restant : ' . baykat_farm_format_quantity( $allocation->remaining_quantity ) . ' ' . $stock_summary['unit'] ); ?></span>
                                                                <?php if ( $allocation->notes ) : ?><p><?php echo esc_html( $allocation->notes ); ?></p><?php endif; ?>
                                                            </div>
                                                            <?php if ( $allocation->remaining_quantity > 0.0005 && baykat_farm_user_can_edit_farm( (int) $campaign->farm_id, $user_id ) ) : ?>
                                                                <details class="baykat-campaign-release">
                                                                    <summary>Modifier ou libérer cette réservation</summary>
                                                                    <p class="baykat-campaign-action-help">Libérer une quantité la rend de nouveau disponible. Cela ne modifie pas le stock physique et n’enregistre pas une vente.</p>
                                                                    <form method="post" class="baykat-campaign-release-form">
                                                                        <?php wp_nonce_field( 'baykat_release_campaign_allocation', '_wpnonce' ); ?>
                                                                        <input type="hidden" name="allocation_id" value="<?php echo esc_attr( $allocation->id ); ?>">
                                                                        <input type="hidden" name="stock_action_key" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>">
                                                                        <label for="baykat_release_quantity_<?php echo esc_attr( $allocation->id ); ?>">Quantité à libérer (<?php echo esc_html( $stock_summary['unit'] ); ?>)</label>
                                                                        <input type="number" name="stock_quantity" id="baykat_release_quantity_<?php echo esc_attr( $allocation->id ); ?>" min="0.001" max="<?php echo esc_attr( $allocation->remaining_quantity ); ?>" step="0.001" placeholder="Maximum : <?php echo esc_attr( baykat_farm_format_quantity( $allocation->remaining_quantity ) ); ?>" required>
                                                                        <button class="baykat-finance-button" type="submit" name="baykat_release_campaign_allocation" value="1">Confirmer la libération</button>
                                                                    </form>
                                                                </details>
                                                            <?php endif; ?>
                                                        </article>
                                                    <?php endforeach; ?>
                                                </div>
                                            </details>
                                        <?php endif; ?>
                                        <?php if ( is_wp_error( $stock_events ) ) : ?>
                                            <div class="baykat-farm-notice baykat-farm-notice-error" role="alert"><?php echo esc_html( $stock_events->get_error_message() ); ?></div>
                                        <?php elseif ( $stock_events ) : ?>
                                            <details class="baykat-campaign-stock-history">
                                                <summary>Historique des mouvements du stock</summary>
                                                <ul>
                                                    <?php
                                                    $stock_event_labels = array(
                                                        'allocation' => 'Réservation',
                                                        'release' => 'Libération',
                                                        'sale' => 'Sortie pour vente',
                                                        'loss' => 'Perte physique',
                                                        'return' => 'Retour confirmé',
                                                        'other_out' => 'Autre sortie',
                                                    );
                                                    foreach ( $stock_events as $stock_event ) :
                                                    ?>
                                                        <li>
                                                            <strong><?php echo esc_html( isset( $stock_event_labels[ $stock_event->event_type ] ) ? $stock_event_labels[ $stock_event->event_type ] : 'Mouvement' ); ?></strong>
                                                            <span><?php echo esc_html( baykat_farm_format_quantity( $stock_event->quantity ) . ' ' . $stock_summary['unit'] . ' · ' . $stock_event->created_at ); ?></span>
                                                            <?php if ( $stock_event->notes ) : ?><p><?php echo esc_html( $stock_event->notes ); ?></p><?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </details>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </section>
                                <?php if ( is_wp_error( $campaign_harvests ) ) : ?>
                                    <div class="baykat-farm-notice baykat-farm-notice-error" role="alert"><?php echo esc_html( $campaign_harvests->get_error_message() ); ?></div>
                                <?php else : ?>
                                    <details class="baykat-campaign-harvest">
                                        <summary>Récoltes <span><?php echo esc_html( count( $campaign_harvests ) . ( 1 === count( $campaign_harvests ) ? ' lot enregistré' : ' lots enregistrés' ) ); ?></span></summary>
                                        <?php if ( baykat_farm_user_can_edit_farm( (int) $campaign->farm_id, $user_id ) ) : ?>
                                            <p class="baykat-campaign-action-help">Chaque enregistrement correspond à un lot récolté. Le total de ces lots alimente le stock de la campagne.</p>
                                            <form method="post" class="baykat-farm-form">
                                                <?php wp_nonce_field( 'baykat_record_harvest', '_wpnonce' ); ?>
                                                <input type="hidden" name="campaign_id" value="<?php echo esc_attr( $campaign->id ); ?>">
                                                <div class="baykat-farm-field">
                                                    <label for="baykat_harvest_quantity_<?php echo esc_attr( $campaign->id ); ?>">Quantité récoltée</label>
                                                    <input type="number" name="harvest_quantity" id="baykat_harvest_quantity_<?php echo esc_attr( $campaign->id ); ?>" min="0.001" step="0.001" required>
                                                </div>
                                                <div class="baykat-farm-field">
                                                    <label for="baykat_harvest_unit_<?php echo esc_attr( $campaign->id ); ?>">Unité</label>
                                                    <input type="text" name="harvest_unit" id="baykat_harvest_unit_<?php echo esc_attr( $campaign->id ); ?>" value="kg" maxlength="40" required>
                                                </div>
                                                <div class="baykat-farm-field">
                                                    <label for="baykat_harvest_date_<?php echo esc_attr( $campaign->id ); ?>">Date de récolte</label>
                                                    <input type="date" name="harvest_date" id="baykat_harvest_date_<?php echo esc_attr( $campaign->id ); ?>" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
                                                </div>
                                                <div class="baykat-farm-field baykat-farm-field-wide">
                                                    <label for="baykat_harvest_notes_<?php echo esc_attr( $campaign->id ); ?>">Notes (facultatif)</label>
                                                    <textarea name="harvest_notes" id="baykat_harvest_notes_<?php echo esc_attr( $campaign->id ); ?>" maxlength="2000" rows="2"></textarea>
                                                </div>
                                                <button class="baykat-finance-button" type="submit" name="baykat_record_harvest" value="1">Enregistrer la récolte</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ( $campaign_harvests ) : ?>
                                            <ul class="baykat-campaign-harvest-list">
                                                <?php foreach ( $campaign_harvests as $harvest ) : ?>
                                                    <li>
                                                        <strong><?php echo esc_html( baykat_farm_format_quantity( $harvest->quantity ) . ' ' . $harvest->unit ); ?></strong>
                                                        <span><?php echo esc_html( $harvest->harvest_date ? $harvest->harvest_date : 'Date non renseignée' ); ?></span>
                                                        <?php if ( $harvest->notes ) : ?><p><?php echo esc_html( $harvest->notes ); ?></p><?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else : ?>
                                            <p class="baykat-farm-empty-state">Aucune récolte enregistrée pour cette campagne.</p>
                                        <?php endif; ?>
                                    </details>
                                <?php endif; ?>
                                <section class="baykat-campaign-phase-section" aria-label="Étapes de la campagne">
                                    <div class="baykat-campaign-section-heading"><div><span class="baykat-farm-eyebrow">Parcours de la campagne</span><h5>Deux étapes</h5></div><p>La commercialisation s’ouvre après la production.</p></div>
                                    <div class="baykat-campaign-phases">
                                    <?php foreach ( array( 'production' => 'Production', 'commercialization' => 'Commercialisation' ) as $phase => $label ) : ?>
                                        <?php $phase_status = 'production' === $phase ? $campaign->production_status : $campaign->commercialization_status; ?>
                                        <div class="baykat-campaign-phase-card baykat-campaign-phase-<?php echo esc_attr( $phase_status ); ?>">
                                            <span class="baykat-campaign-phase-step"><?php echo 'production' === $phase ? 'Étape 1' : 'Étape 2'; ?></span>
                                            <h6><?php echo esc_html( $label ); ?></h6>
                                            <strong><?php echo esc_html( baykat_farm_campaign_status_label( $phase_status ) ); ?></strong>
                                            <p class="baykat-campaign-phase-description"><?php echo 'production' === $phase ? 'Activités, dépenses et récoltes.' : 'Réservation du stock et suivi de sa mise en vente.'; ?></p>
                                            <?php if ( 'commercialization' === $phase && 'completed' !== $campaign->production_status ) : ?>
                                                <p class="baykat-campaign-dashboard-note">La commercialisation sera activable après la fin de la production.</p>
                                            <?php elseif ( 'commercialization' === $phase ) : ?>
                                                <p class="baykat-campaign-dashboard-note">Statut géré ici; pas encore synchronisé avec les annonces ou ventes BAYKAT.</p>
                                            <?php endif; ?>
                                            <?php if ( baykat_farm_user_can_edit_farm( (int) $campaign->farm_id, $user_id ) && ( 'production' === $phase || 'completed' === $campaign->production_status ) ) : ?>
                                                <form method="post">
                                                    <?php wp_nonce_field( 'baykat_update_campaign_phase', '_wpnonce' ); ?>
                                                    <input type="hidden" name="campaign_id" value="<?php echo esc_attr( $campaign->id ); ?>">
                                                    <input type="hidden" name="campaign_phase" value="<?php echo esc_attr( $phase ); ?>">
                                                    <select name="phase_status" aria-label="Statut — <?php echo esc_attr( $label ); ?>">
                                                        <?php foreach ( array( 'planned', 'in_progress', 'completed', 'paused' ) as $status ) : ?>
                                                            <option value="<?php echo esc_attr( $status ); ?>" <?php selected( $phase_status, $status ); ?>><?php echo esc_html( baykat_farm_campaign_status_label( $status ) ); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button class="baykat-finance-button" type="submit" name="baykat_update_campaign_phase" value="1">Mettre à jour</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                </section>
                            <?php baykat_farm_render_archive_action( 'campaign', (int) $campaign->id, 'archive', 'Archiver la campagne' ); ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="baykat-farm-empty-state">Aucune campagne. Vous pouvez d’abord préparer une simulation, même sans ferme enregistrée.</p>
            <?php endif; ?>
        </section>
    </div>
    <?php if ( $simulations ) : ?>
        <dialog class="baykat-campaign-comparison" data-campaign-comparison-dialog aria-labelledby="baykat-campaign-comparison-title">
            <div class="baykat-campaign-comparison-content">
                <header class="baykat-campaign-comparison-heading">
                    <div>
                        <span class="baykat-farm-eyebrow">Analyse de campagne</span>
                        <h2 id="baykat-campaign-comparison-title">Réel et prévisionnel</h2>
                        <p data-comparison-campaign-title></p>
                    </div>
                    <button class="baykat-campaign-comparison-close" type="button" data-close-campaign-comparison aria-label="Fermer la comparaison">&times;</button>
                </header>
                <label class="baykat-campaign-comparison-select-label" for="baykat-campaign-comparison-select">Simulation à comparer</label>
                <select id="baykat-campaign-comparison-select" data-comparison-simulation-select>
                    <?php foreach ( $simulations as $simulation ) : ?>
                        <?php
                        $forecast_harvest = (float) $simulation->area_ha * (float) $simulation->expected_yield_kg_ha;
                        $forecast_income  = $forecast_harvest * (float) $simulation->expected_price_per_kg;
                        $forecast_result  = $forecast_income - (float) $simulation->expected_cost;
                        ?>
                        <option
                            value="<?php echo esc_attr( $simulation->id ); ?>"
                            data-crop-name="<?php echo esc_attr( $simulation->crop_name ); ?>"
                            data-crop-reference="<?php echo esc_attr( (int) $simulation->crop_reference_id ); ?>"
                            data-area="<?php echo esc_attr( (float) $simulation->area_ha ); ?>"
                            data-harvest="<?php echo esc_attr( $forecast_harvest ); ?>"
                            data-yield="<?php echo esc_attr( (float) $simulation->expected_yield_kg_ha ); ?>"
                            data-expenses="<?php echo esc_attr( (float) $simulation->expected_cost ); ?>"
                            data-income="<?php echo esc_attr( $forecast_income ); ?>"
                            data-result="<?php echo esc_attr( $forecast_result ); ?>"
                            data-available-funds="<?php echo esc_attr( null === $simulation->expected_available_funds ? '' : (float) $simulation->expected_available_funds ); ?>"
                            data-tools-needed="<?php echo esc_attr( (string) $simulation->expected_tools_needed ); ?>"
                            data-tools-available="<?php echo esc_attr( (string) $simulation->expected_tools_available ); ?>"
                        ><?php echo esc_html( $simulation->crop_name . ' · ' . baykat_farm_format_quantity( $simulation->area_ha, 4 ) . ' ha' . ( $simulation->planned_start_date ? ' · ' . $simulation->planned_start_date : '' ) ); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="baykat-campaign-comparison-table-wrap">
                    <table class="baykat-campaign-comparison-table">
                        <thead>
                            <tr><th scope="col">Indicateur</th><th scope="col">Réel enregistré</th><th scope="col">Prévisionnel</th></tr>
                        </thead>
                        <tbody>
                            <tr><th scope="row">Surface</th><td data-comparison-real-area></td><td data-comparison-forecast-area></td></tr>
                            <tr><th scope="row">Récolte totale</th><td data-comparison-real-harvest></td><td data-comparison-forecast-harvest></td></tr>
                            <tr><th scope="row">Rendement</th><td data-comparison-real-yield></td><td data-comparison-forecast-yield></td></tr>
                            <tr><th scope="row">Dépenses suivies</th><td data-comparison-real-expenses></td><td data-comparison-forecast-expenses></td></tr>
                            <tr><th scope="row">Recettes</th><td data-comparison-real-income></td><td data-comparison-forecast-income></td></tr>
                            <tr><th scope="row">Résultat simplifié</th><td data-comparison-real-result></td><td data-comparison-forecast-result></td></tr>
                            <tr><th scope="row">Financement à mobiliser</th><td data-comparison-real-funding></td><td data-comparison-forecast-funding></td></tr>
                        </tbody>
                    </table>
                </div>
                <section class="baykat-campaign-comparison-tools">
                    <h3>Checklist déclarée pour cette simulation</h3>
                    <div>
                        <div><h4>Besoins déclarés</h4><ul data-comparison-tools-needed></ul></div>
                        <div><h4>Déjà disponible</h4><ul data-comparison-tools-available></ul></div>
                        <div><h4>À mobiliser</h4><ul data-comparison-tools-missing></ul></div>
                    </div>
                </section>
                <p class="baykat-campaign-comparison-note">Les données réelles reflètent les opérations enregistrées à ce jour. Le prévisionnel reprend les hypothèses de la simulation. Le résultat est un indicateur simplifié, pas un résultat comptable.</p>
                <?php if ( $editable_farms && $parcels ) : ?>
                    <details class="baykat-campaign-comparison-convert">
                        <summary>Transformer cette simulation en campagne réelle</summary>
                        <p>Choisissez la ferme et la parcelle. Les chiffres prévisionnels resteront des hypothèses et ne seront pas enregistrés comme récoltes ou dépenses réelles.</p>
                        <form method="post" class="baykat-farm-form">
                            <?php wp_nonce_field( 'baykat_create_from_simulation', '_wpnonce' ); ?>
                            <input type="hidden" name="source_simulation_id" value="" data-comparison-source-simulation>
                            <input type="hidden" name="campaign_type" value="real">
                            <label>Ferme
                                <select name="campaign_farm_id" data-comparison-farm required>
                                    <option value="">Choisir une ferme</option>
                                    <?php foreach ( $editable_farms as $farm ) : ?>
                                        <option value="<?php echo esc_attr( $farm->id ); ?>"><?php echo esc_html( $farm->name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Parcelle
                                <select name="campaign_parcel_id" data-comparison-parcel required>
                                    <option value="">Choisir une parcelle</option>
                                    <?php foreach ( $parcels as $parcel ) : ?>
                                        <option value="<?php echo esc_attr( $parcel->id ); ?>" data-farm-id="<?php echo esc_attr( $parcel->farm_id ); ?>"><?php echo esc_html( $parcel->farm_name . ' — ' . $parcel->name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button class="baykat-finance-button" type="submit" name="baykat_create_from_simulation" value="1">Créer la campagne réelle</button>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
        </dialog>
    <?php endif; ?>
    <script>
    (() => {
        const typeSelect = document.getElementById("baykat_campaign_type");
        const form = typeSelect?.closest("form");
        if (!form) return;
        const realFields = form.querySelectorAll(".baykat-campaign-real-field");
        const simulationFields = form.querySelectorAll(".baykat-campaign-simulation-field");
        const farmSelect = document.getElementById("baykat_campaign_farm");
        const parcelSelect = document.getElementById("baykat_campaign_parcel");
        const updateMode = () => {
            const real = typeSelect.value === "real";
            realFields.forEach((field) => {
                field.hidden = !real;
                field.querySelectorAll("select").forEach((select) => { select.required = real; });
            });
            simulationFields.forEach((field) => {
                field.hidden = real;
                field.querySelectorAll("input").forEach((input) => { input.required = !real; });
            });
        };
        const updateParcels = () => {
            if (!farmSelect || !parcelSelect) return;
            Array.from(parcelSelect.options).forEach((option) => {
                if (!option.value) return;
                option.hidden = option.dataset.farmId !== farmSelect.value;
            });
            if (parcelSelect.selectedOptions[0]?.hidden) parcelSelect.value = "";
        };
        typeSelect.addEventListener("change", updateMode);
        farmSelect?.addEventListener("change", updateParcels);
        updateMode();
        updateParcels();
        document.querySelectorAll("[data-campaign-farm]").forEach((select) => {
            const parcelSelect = select.closest("form").querySelector("[data-campaign-parcel]");
            const update = () => {
                Array.from(parcelSelect.options).forEach((option) => {
                    if (option.value) option.hidden = option.dataset.farmId !== select.value;
                });
                if (parcelSelect.selectedOptions[0]?.hidden) parcelSelect.value = "";
            };
            select.addEventListener("change", update);
            update();
        });
        document.querySelectorAll("[data-stock-destination-select]").forEach((select) => {
            const label = select.closest("form").querySelector("[data-stock-destination-label]");
            const update = () => {
                if (label) label.required = select.value === "other";
            };
            select.addEventListener("change", update);
            update();
        });
    })();
    </script>
    <?php
}

function baykat_farm_campaign_status_label( $status ) {
    $labels = array(
        'planned'     => 'Planifiée',
        'in_progress' => 'En cours',
        'completed'   => 'Terminée',
        'paused'      => 'En pause',
    );

    return isset( $labels[ $status ] ) ? $labels[ $status ] : 'Inconnu';
}
