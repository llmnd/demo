<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_farm_stock_types() {
    return array(
        'entry'               => 'Entrée',
        'exit'                => 'Sortie',
        'transfer'            => 'Transfert',
        'adjustment_positive' => 'Ajustement positif',
        'adjustment_negative' => 'Ajustement négatif',
        'return'              => 'Retour',
    );
}

function baykat_farm_get_stock_movements( $user_id ) {
    global $wpdb;

    $table = $wpdb->prefix . 'baykat_stock_movements';
    $farms = baykat_farm_get_user_farms( $user_id );
    $farm_ids = array_map( 'intval', wp_list_pluck( $farms, 'id' ) );
    if ( ! $farm_ids ) {
        return array();
    }

    $visible_farm_list = implode( ', ', $farm_ids );
    $sql =
        "SELECT movement.*,
                IF(movement.farm_id IN ($visible_farm_list), 1, 0) AS source_accessible,
                IF(movement.destination_farm_id IN ($visible_farm_list), 1, 0) AS destination_accessible,
                IF(movement.farm_id IN ($visible_farm_list), farms.name, 'Autre ferme') AS farm_name,
                IF(movement.farm_id IN ($visible_farm_list), parcels.name, NULL) AS parcel_name,
                IF(movement.destination_farm_id IS NULL, NULL, IF(movement.destination_farm_id IN ($visible_farm_list), destination_farms.name, 'Autre ferme')) AS destination_farm_name,
                IF(movement.destination_farm_id IN ($visible_farm_list), destination_parcels.name, NULL) AS destination_parcel_name,
                crops.name AS crop_name, activities.title AS activity_title
         FROM $table AS movement
         INNER JOIN " . baykat_farm_table_name( 'farms' ) . " AS farms
            ON farms.id = movement.farm_id
         LEFT JOIN " . baykat_farm_table_name( 'parcels' ) . " AS parcels
            ON parcels.id = movement.parcel_id
         LEFT JOIN " . baykat_farm_table_name( 'farms' ) . " AS destination_farms
            ON destination_farms.id = movement.destination_farm_id
         LEFT JOIN " . baykat_farm_table_name( 'parcels' ) . " AS destination_parcels
            ON destination_parcels.id = movement.destination_parcel_id
         LEFT JOIN " . baykat_farm_table_name( 'crops' ) . " AS crops
            ON crops.id = movement.crop_id
         LEFT JOIN " . baykat_farm_table_name( 'activities' ) . " AS activities
            ON activities.id = movement.activity_id
         WHERE movement.farm_id IN ($visible_farm_list) OR movement.destination_farm_id IN ($visible_farm_list)
         ORDER BY movement.movement_date DESC, movement.id DESC";
    return (array) $wpdb->get_results( $sql );
}

function baykat_farm_get_stock_totals( $user_id ) {
    $totals = array();
    $input_data = baykat_finance_get_user_transactions( $user_id, 'input' );
    $visible_farms = baykat_farm_get_user_farms( $user_id );

    foreach ( (array) $input_data['transactions'] as $transaction ) {
        $key = baykat_farm_stock_key( $transaction->farm_id, $transaction->parcel_id, $transaction->item_name, $transaction->unit );
        if ( ! isset( $totals[ $key ] ) ) {
            $totals[ $key ] = baykat_farm_stock_total_row( $transaction->farm_id, $transaction->parcel_id, $transaction->farm_name, $transaction->parcel_name, $transaction->item_name, $transaction->unit );
        }
        $totals[ $key ]->total_quantity += (float) $transaction->quantity;
        $totals[ $key ]->total_cost += (float) $transaction->amount;
    }

    foreach ( baykat_farm_get_stock_movements( $user_id ) as $movement ) {
        if ( $movement->source_accessible ) {
            $source_key = baykat_farm_stock_key( $movement->farm_id, $movement->parcel_id, $movement->item_name, $movement->unit );
            if ( ! isset( $totals[ $source_key ] ) ) {
                $totals[ $source_key ] = baykat_farm_stock_total_row( $movement->farm_id, $movement->parcel_id, $movement->farm_name, $movement->parcel_name, $movement->item_name, $movement->unit );
            }

            if ( in_array( $movement->movement_type, array( 'exit', 'adjustment_negative', 'transfer' ), true ) ) {
                $totals[ $source_key ]->total_quantity -= (float) $movement->quantity;
            } else {
                $totals[ $source_key ]->total_quantity += (float) $movement->quantity;
            }
        }

        if ( 'transfer' === $movement->movement_type && $movement->destination_accessible ) {
            $destination_key = baykat_farm_stock_key( $movement->destination_farm_id, $movement->destination_parcel_id, $movement->item_name, $movement->unit );
            if ( ! isset( $totals[ $destination_key ] ) ) {
                $totals[ $destination_key ] = baykat_farm_stock_total_row( $movement->destination_farm_id, $movement->destination_parcel_id, $movement->destination_farm_name, $movement->destination_parcel_name, $movement->item_name, $movement->unit );
            }
            $totals[ $destination_key ]->total_quantity += (float) $movement->quantity;
        }
    }

    return array_values(
        array_filter(
            $totals,
            function ( $stock ) {
                return $stock->total_quantity > 0.0005;
            }
        )
    );
}

function baykat_farm_get_returnable_stock( $user_id ) {
    global $wpdb;

    $editable_farms = baykat_farm_filter_editable_records( baykat_farm_get_user_farms( $user_id ) );
    $farm_ids = array_map( 'intval', wp_list_pluck( $editable_farms, 'id' ) );
    if ( ! $farm_ids ) {
        return array();
    }

    $movements_table = $wpdb->prefix . 'baykat_stock_movements';
    $farms_table = baykat_farm_table_name( 'farms' );
    $parcels_table = baykat_farm_table_name( 'parcels' );
    $activities_table = baykat_farm_table_name( 'activities' );
    $farm_scope = implode( ', ', $farm_ids );

    return (array) $wpdb->get_results(
        "SELECT movement.farm_id, IFNULL(movement.parcel_id, 0) AS parcel_id,
                movement.activity_id, movement.item_name, movement.unit,
                SUM(CASE WHEN movement.movement_type = 'exit' THEN movement.quantity
                         WHEN movement.movement_type = 'return' THEN -movement.quantity ELSE 0 END) AS returnable_quantity,
                farms.name AS farm_name, parcels.name AS parcel_name, activities.title AS activity_title
         FROM $movements_table AS movement
         INNER JOIN $farms_table AS farms ON farms.id = movement.farm_id
         LEFT JOIN $parcels_table AS parcels ON parcels.id = movement.parcel_id
         INNER JOIN $activities_table AS activities ON activities.id = movement.activity_id
         WHERE movement.farm_id IN ($farm_scope)
            AND movement.movement_type IN ('exit', 'return')
         GROUP BY movement.farm_id, movement.parcel_id, movement.activity_id,
                  movement.item_name, movement.unit, farms.name, parcels.name, activities.title
         HAVING returnable_quantity > 0.0005
         ORDER BY farms.name ASC, parcels.name ASC, activities.title ASC, movement.item_name ASC"
    );
}

function baykat_farm_get_stock_history( $user_id ) {
    $input_data = baykat_finance_get_user_transactions( $user_id, 'input' );
    $history = array();
    foreach ( (array) $input_data['transactions'] as $transaction ) {
        $transaction->movement_type = 'entry';
        $transaction->movement_date = substr( $transaction->created_at, 0, 10 );
        $transaction->notes = $transaction->description;
        $transaction->destination_farm_name = '';
        $transaction->destination_parcel_name = '';
        $history[] = $transaction;
    }

    $history = array_merge( $history, baykat_farm_get_stock_movements( $user_id ) );
    usort(
        $history,
        function ( $first, $second ) {
            $date_order = strcmp( $second->movement_date, $first->movement_date );
            return $date_order ? $date_order : strcmp( $second->created_at, $first->created_at );
        }
    );

    return $history;
}

function baykat_farm_stock_key( $farm_id, $parcel_id, $item_name, $unit ) {
    return implode(
        '|',
        array(
            (int) $farm_id,
            (int) $parcel_id,
            strtolower( trim( $item_name ) ),
            strtolower( trim( $unit ) ),
        )
    );
}

function baykat_farm_stock_total_row( $farm_id, $parcel_id, $farm_name, $parcel_name, $item_name, $unit ) {
    return (object) array(
        'farm_id'       => (int) $farm_id,
        'parcel_id'     => $parcel_id ? (int) $parcel_id : 0,
        'farm_name'     => $farm_name ? $farm_name : 'Ferme',
        'parcel_name'   => $parcel_name ? $parcel_name : 'Stock de la ferme',
        'item_name'     => $item_name,
        'unit'          => $unit,
        'total_quantity' => 0.0,
        'total_cost'    => 0.0,
    );
}

function baykat_farm_get_stock_balance( $user_id, $farm_id, $parcel_id, $item_name, $unit ) {
    $key = baykat_farm_stock_key( $farm_id, $parcel_id, $item_name, $unit );
    foreach ( baykat_farm_get_stock_totals( $user_id ) as $stock ) {
        if ( $key === baykat_farm_stock_key( $stock->farm_id, $stock->parcel_id, $stock->item_name, $stock->unit ) ) {
            return (float) $stock->total_quantity;
        }
    }
    return 0.0;
}

function baykat_farm_process_stock_movement( $user_id ) {
    if ( ! isset( $_POST['baykat_add_stock_movement'] ) ) {
        return null;
    }
    check_admin_referer( 'baykat_add_stock_movement' );

    $type = isset( $_POST['movement_type'] ) && is_string( $_POST['movement_type'] )
        ? sanitize_key( wp_unslash( $_POST['movement_type'] ) )
        : '';
    $farm_id = baykat_farm_request_id( 'farm_id' );
    $parcel_id = baykat_farm_request_id( 'parcel_id' );
    $destination_farm_id = baykat_farm_request_id( 'destination_farm_id' );
    $destination_parcel_id = baykat_farm_request_id( 'destination_parcel_id' );
    $item_name = isset( $_POST['item_name'] ) && is_string( $_POST['item_name'] )
        ? sanitize_text_field( wp_unslash( $_POST['item_name'] ) )
        : '';
    $quantity_value = isset( $_POST['quantity'] ) && is_scalar( $_POST['quantity'] )
        ? trim( (string) wp_unslash( $_POST['quantity'] ) )
        : '';
    $quantity = is_numeric( $quantity_value ) ? (float) $quantity_value : 0;
    $unit = isset( $_POST['unit'] ) && is_string( $_POST['unit'] )
        ? sanitize_text_field( wp_unslash( $_POST['unit'] ) )
        : '';
    $notes = isset( $_POST['movement_notes'] ) && is_string( $_POST['movement_notes'] )
        ? sanitize_textarea_field( wp_unslash( $_POST['movement_notes'] ) )
        : '';
    $date = isset( $_POST['movement_date'] ) && is_string( $_POST['movement_date'] )
        ? sanitize_text_field( wp_unslash( $_POST['movement_date'] ) )
        : '';
    $farm = baykat_farm_user_owns_farm( $farm_id, $user_id );
    $parcel = $parcel_id ? baykat_farm_get_user_parcel( $parcel_id, $user_id ) : null;

    if (
        ! isset( baykat_farm_stock_types()[ $type ] )
        || ! $farm
        || ( $parcel_id && ( ! $parcel || (int) $parcel->farm_id !== $farm_id ) )
        || '' === $item_name
        || strlen( $item_name ) > 190
        || $quantity < 0.001
        || $quantity > 999999999999.999
        || '' === $unit
        || strlen( $unit ) > 40
        || ! baykat_farm_valid_date( $date )
    ) {
        return array( 'type' => 'error', 'message' => 'Vérifiez le type de mouvement, l’emplacement, l’intrant, la quantité, l’unité et la date.' );
    }

    if ( 'transfer' === $type ) {
        $destination_farm = baykat_farm_user_owns_farm( $destination_farm_id, $user_id );
        $destination_parcel = $destination_parcel_id
            ? baykat_farm_get_user_parcel( $destination_parcel_id, $user_id )
            : null;
        if ( ! $destination_farm || ( $destination_parcel_id && ( ! $destination_parcel || (int) $destination_parcel->farm_id !== $destination_farm_id ) ) ) {
            return array( 'type' => 'error', 'message' => 'Choisissez un emplacement de destination auquel vous avez accès.' );
        }
        if ( $farm_id === $destination_farm_id && $parcel_id === $destination_parcel_id ) {
            return array( 'type' => 'error', 'message' => 'La destination doit être différente de l’emplacement d’origine.' );
        }
    } elseif ( $destination_farm_id || $destination_parcel_id ) {
        return array( 'type' => 'error', 'message' => 'La destination ne s’applique qu’à un transfert.' );
    }

    $crop_id = baykat_farm_request_id( 'crop_id' );
    $activity_id = baykat_farm_request_id( 'activity_id' );
    if ( $crop_id ) {
        global $wpdb;
        $crop = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, parcel_id FROM " . baykat_farm_table_name( 'crops' ) . " WHERE id = %d",
                $crop_id
            )
        );
        $crop_parcel = $crop ? baykat_farm_get_user_parcel( (int) $crop->parcel_id, $user_id ) : null;
        if ( ! $crop_parcel || (int) $crop_parcel->farm_id !== $farm_id || ( $parcel_id && (int) $crop->parcel_id !== $parcel_id ) ) {
            return array( 'type' => 'error', 'message' => 'La culture choisie n’appartient pas à cet emplacement.' );
        }
    }
    if ( $activity_id ) {
        global $wpdb;
        $activity = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, parcel_id FROM " . baykat_farm_table_name( 'activities' ) . " WHERE id = %d",
                $activity_id
            )
        );
        $activity_parcel = $activity ? baykat_farm_get_user_parcel( (int) $activity->parcel_id, $user_id ) : null;
        if ( ! $activity_parcel || (int) $activity_parcel->farm_id !== $farm_id || ( $parcel_id && (int) $activity->parcel_id !== $parcel_id ) ) {
            return array( 'type' => 'error', 'message' => 'L’activité choisie n’appartient pas à cet emplacement.' );
        }
    }

    if ( 'return' === $type && $activity_id ) {
        $movements_table = $wpdb->prefix . 'baykat_stock_movements';
        $used_quantity = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(CASE WHEN movement_type = 'exit' THEN quantity WHEN movement_type = 'return' THEN -quantity ELSE 0 END), 0)
                 FROM $movements_table
                 WHERE activity_id = %d AND farm_id = %d AND IFNULL(parcel_id, 0) = %d
                    AND item_name = %s AND unit = %s",
                $activity_id,
                $farm_id,
                $parcel_id ? $parcel_id : null,
                $item_name,
                $unit
            )
        );
        if ( $used_quantity + 0.0005 < $quantity ) {
            return array( 'type' => 'error', 'message' => 'La quantité retournée dépasse la quantité sortie pour cette activité.' );
        }
    }
    if ( 'return' === $type && ! $activity_id ) {
        return array( 'type' => 'error', 'message' => 'Associez le retour à l’activité de sortie pour vérifier la quantité utilisée.' );
    }

    if ( in_array( $type, array( 'exit', 'transfer', 'adjustment_negative' ), true )
        && baykat_farm_get_stock_balance( $user_id, $farm_id, $parcel_id, $item_name, $unit ) + 0.0005 < $quantity ) {
        return array( 'type' => 'error', 'message' => 'Le stock disponible est insuffisant pour ce mouvement.' );
    }

    global $wpdb;
    if ( 'entry' === $type ) {
        $amount_value = isset( $_POST['amount'] ) && is_scalar( $_POST['amount'] )
            ? trim( (string) wp_unslash( $_POST['amount'] ) )
            : '';
        $amount = is_numeric( $amount_value ) ? (float) $amount_value : 0;
        if ( $amount < 0.01 || $amount > 9999999999999.99 ) {
            return array( 'type' => 'error', 'message' => 'Saisissez le coût total de l’approvisionnement (au moins 0,01 FCFA).' );
        }
        $owner_id = baykat_farm_get_owner_id_for_farm( $farm_id );
        $inserted = $wpdb->insert(
            baykat_finance_get_table_name(),
            array(
                'user_id'     => $owner_id,
                'type'        => 'expense',
                'category'    => 'input',
                'amount'      => $amount,
                'description' => $notes,
                'item_name'   => $item_name,
                'quantity'    => $quantity,
                'unit'        => $unit,
                'expense_category' => 'general',
                'farm_id'     => $farm_id,
                'parcel_id'   => $parcel_id ? $parcel_id : null,
                'activity_id' => $activity_id ? $activity_id : null,
                'crop_id'     => $crop_id ? $crop_id : null,
                'created_at'  => $date . ' 12:00:00',
            ),
            array( '%d', '%s', '%s', '%f', '%s', '%s', '%f', '%s', '%s', '%d', '%d', '%d', '%d', '%s' )
        );
    } else {
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'baykat_stock_movements',
            array(
                'user_id'                => $user_id,
                'farm_id'                => $farm_id,
                'parcel_id'              => $parcel_id ? $parcel_id : null,
                'destination_farm_id'    => $destination_farm_id ? $destination_farm_id : null,
                'destination_parcel_id'  => $destination_parcel_id ? $destination_parcel_id : null,
                'crop_id'                => $crop_id ? $crop_id : null,
                'activity_id'            => $activity_id ? $activity_id : null,
                'movement_type'          => $type,
                'item_name'              => $item_name,
                'quantity'               => $quantity,
                'unit'                   => $unit,
                'notes'                  => $notes,
                'movement_date'          => $date,
            ),
            array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s' )
        );
    }

    return false === $inserted
        ? array( 'type' => 'error', 'message' => 'Le mouvement de stock n’a pas pu être enregistré.' )
        : array( 'type' => 'success', 'message' => 'Mouvement de stock enregistré.' );
}
