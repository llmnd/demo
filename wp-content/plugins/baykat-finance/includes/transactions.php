<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_finance_expense_categories() {
    return array(
        'general'     => 'Autre dépense',
        'labor'       => 'Main-d’œuvre',
        'transport'   => 'Transport',
        'equipment'   => 'Matériel et équipement',
        'maintenance' => 'Entretien',
    );
}

function baykat_finance_expense_category_label( $category ) {
    $categories = baykat_finance_expense_categories();

    return isset( $categories[ $category ] ) ? $categories[ $category ] : $categories['general'];
}

/**
 * Traite l'envoi du formulaire d'ajout d'une transaction.
 *
 * @param int $user_id Identifiant de l'utilisateur courant.
 * @return array|null Message à afficher, ou null si le formulaire n'a pas été envoyé.
 */
function baykat_finance_process_transaction_submission( $user_id, $expected_category = 'general' ) {
    $is_item_request = isset( $_POST['baykat_add_finance_item'] );
    $is_activity_request = isset( $_POST['baykat_add_activity_finance'] );
    $is_item_submission = $is_item_request
        && in_array( $expected_category, array( 'input', 'sale' ), true );
    $is_activity_submission = $is_activity_request && 'general' === $expected_category;

    if ( ( $is_item_request && ! in_array( $expected_category, array( 'input', 'sale' ), true ) )
        || ( $is_activity_request && ! $is_activity_submission ) ) {
        return null;
    }

    if ( $is_item_submission ) {
        $submitted_category = isset( $_POST['finance_item_category'] ) && is_string( $_POST['finance_item_category'] )
            ? sanitize_key( wp_unslash( $_POST['finance_item_category'] ) )
            : '';

        if ( ! in_array( $submitted_category, array( 'input', 'sale' ), true ) ) {
            return array(
                'type'    => 'error',
                'message' => 'Veuillez sélectionner une catégorie valide pour cet enregistrement.',
            );
        }

        if ( $submitted_category !== $expected_category ) {
            return null;
        }
    }

    if ( ( isset( $_POST['baykat_add_transaction'] ) || $is_activity_submission ) && 'general' !== $expected_category ) {
        return null;
    }

    if ( ! $is_item_submission && ! isset( $_POST['baykat_add_transaction'] ) && ! $is_activity_submission ) {
        return null;
    }

    check_admin_referer(
        $is_item_submission
            ? 'baykat_add_finance_item'
            : ( $is_activity_submission ? 'baykat_add_activity_finance' : 'baykat_add_transaction' )
    );

    $type = $is_item_submission
        ? ( 'sale' === $expected_category ? 'income' : 'expense' )
        : (
            isset( $_POST['type'] ) && is_string( $_POST['type'] )
                ? sanitize_text_field( wp_unslash( $_POST['type'] ) )
                : ''
        );

    $category = $is_item_submission ? $expected_category : 'general';
    $expense_category = 'general';
    if ( 'expense' === $type && ! $is_item_submission ) {
        $submitted_expense_category = isset( $_POST['expense_category'] ) && is_string( $_POST['expense_category'] )
            ? sanitize_key( wp_unslash( $_POST['expense_category'] ) )
            : 'general';
        $expense_categories = baykat_finance_expense_categories();
        if ( ! isset( $expense_categories[ $submitted_expense_category ] ) ) {
            return array(
                'type'    => 'error',
                'message' => 'Veuillez choisir une catégorie de dépense valide.',
            );
        }
        $expense_category = $submitted_expense_category;
    }

    $amount_value = isset( $_POST['amount'] ) && is_scalar( $_POST['amount'] )
        ? trim( (string) wp_unslash( $_POST['amount'] ) )
        : '';
    $amount = is_numeric( $amount_value ) ? (float) $amount_value : 0;

    $description = isset( $_POST['description'] ) && is_string( $_POST['description'] )
        ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) )
        : '';

    if (
        ! in_array( $type, array( 'income', 'expense' ), true )
        || $amount < 0.01
        || $amount > 9999999999999.99
    ) {
        return array(
            'type'    => 'error',
            'message' => 'Veuillez saisir un type et un montant valides.',
        );
    }

    $item_name = null;
    $quantity = null;
    $unit = null;
    $activity_id = null;
    $activity = null;
    $parcel_id = baykat_farm_request_id( 'parcel_id' );
    $farm_id = null;

    if ( $is_activity_submission ) {
        $activity_id = baykat_farm_request_id( 'activity_id' );
        if ( 0 === $activity_id ) {
            return array(
                'type'    => 'error',
                'message' => 'L’activité sélectionnée est invalide.',
            );
        }

        global $wpdb;
        $activities_table = baykat_farm_table_name( 'activities' );
        $activity = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, parcel_id FROM $activities_table WHERE id = %d AND user_id = %d",
                $activity_id,
                $user_id
            )
        );

        if ( ! $activity ) {
            return array(
                'type'    => 'error',
                'message' => 'Cette activité n’existe pas ou ne vous appartient pas.',
            );
        }
        if ( $parcel_id && $parcel_id !== (int) $activity->parcel_id ) {
            return array(
                'type'    => 'error',
                'message' => 'Cette opération doit être rattachée à la parcelle de cette activité.',
            );
        }
        $parcel_id = (int) $activity->parcel_id;
        $item_name = isset( $_POST['item_name'] ) && is_string( $_POST['item_name'] )
            ? sanitize_text_field( wp_unslash( $_POST['item_name'] ) )
            : '';
        if ( '' === $item_name || strlen( $item_name ) > 190 ) {
            return array(
                'type'    => 'error',
                'message' => 'Donnez un nom à cette opération financière.',
            );
        }
    } elseif ( isset( $_POST['activity_id'] ) ) {
        if ( ! is_scalar( $_POST['activity_id'] ) ) {
            return array(
                'type'    => 'error',
                'message' => 'Veuillez choisir une activité valide.',
            );
        }
        $raw_activity_id = trim( (string) wp_unslash( $_POST['activity_id'] ) );
        if ( '' !== $raw_activity_id ) {
            $activity_id = baykat_farm_request_id( 'activity_id' );
            if ( 0 === $activity_id ) {
                return array(
                    'type'    => 'error',
                    'message' => 'Veuillez choisir une activité valide.',
                );
            }
        }
    }

    if ( isset( $_POST['parcel_id'] ) && ! is_scalar( $_POST['parcel_id'] ) ) {
        return array(
            'type'    => 'error',
            'message' => 'Veuillez choisir une parcelle valide.',
        );
    }

    if ( isset( $_POST['parcel_id'] ) ) {
        $raw_parcel_id = trim( (string) wp_unslash( $_POST['parcel_id'] ) );

        if ( '' !== $raw_parcel_id && 0 === $parcel_id ) {
            return array(
                'type'    => 'error',
                'message' => 'Veuillez choisir une parcelle valide.',
            );
        }
    }

    if ( 0 === $parcel_id ) {
        return array(
            'type'    => 'error',
            'message' => 'Choisissez une parcelle appartenant à une ferme avant d’enregistrer cette transaction.',
        );
    }

    $parcel = baykat_farm_get_user_parcel( $parcel_id, $user_id );

    if ( ! $parcel ) {
        return array(
            'type'    => 'error',
            'message' => 'La parcelle sélectionnée ne vous appartient pas.',
        );
    }
    $farm_id = (int) $parcel->farm_id;

    if ( $activity_id && ! $is_activity_submission ) {
        global $wpdb;
        $activities_table = baykat_farm_table_name( 'activities' );
        $activity_parcel_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT parcel_id FROM $activities_table WHERE id = %d AND user_id = %d",
                $activity_id,
                $user_id
            )
        );
        if ( ! $activity_parcel_id || (int) $activity_parcel_id !== $parcel_id ) {
            return array(
                'type'    => 'error',
                'message' => 'L’activité doit appartenir à la parcelle choisie.',
            );
        }
    }

    if ( $is_item_submission ) {
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

        if (
            '' === $item_name
            || strlen( $item_name ) > 190
            || $quantity < 0.001
            || $quantity > 999999999999.999
            || '' === $unit
            || strlen( $unit ) > 40
        ) {
            return array(
                'type'    => 'error',
                'message' => 'Veuillez renseigner un nom, une quantité, une unité et un montant valides.',
            );
        }
    }

    global $wpdb;

    $inserted = $wpdb->insert(
        baykat_finance_get_table_name(),
        array(
            'user_id'     => $user_id,
            'type'        => $type,
            'category'    => $category,
            'amount'      => $amount,
            'description' => $description,
            'item_name'   => $item_name,
            'quantity'    => $quantity,
            'unit'        => $unit,
            'expense_category' => $expense_category,
            'farm_id'     => $farm_id,
            'parcel_id'   => $parcel_id,
            'activity_id' => $activity_id,
        ),
        array( '%d', '%s', '%s', '%f', '%s', '%s', '%f', '%s', '%s', '%d', '%d', '%d' )
    );

    if ( false === $inserted ) {
        return array(
            'type'    => 'error',
            'message' => 'La transaction n’a pas pu être enregistrée.',
        );
    }

    return array(
        'type'    => 'success',
        'message' => $is_activity_submission
            ? 'Finance associée à l’activité.'
            : ( $is_item_submission
                ? ( 'sale' === $category ? 'Vente enregistrée avec succès.' : 'Intrant enregistré avec succès.' )
                : 'Transaction enregistrée avec succès.' ),
    );
}

/**
 * Récupère les totaux et les transactions d'un utilisateur.
 *
 * @param int $user_id Identifiant de l'utilisateur courant.
 * @return array Données affichées sur le tableau de bord.
 */
function baykat_finance_get_user_transactions( $user_id, $category = null ) {

    global $wpdb;

    $table_name = baykat_finance_get_table_name();
    $parcels_table = baykat_farm_table_name( 'parcels' );
    $farms_table = baykat_farm_table_name( 'farms' );
    $activities_table = baykat_farm_table_name( 'activities' );

    if ( null === $category ) {
        $totals_sql = $wpdb->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN transactions.type = 'income' THEN transactions.amount ELSE 0 END), 0) AS total_income,
                COALESCE(SUM(CASE WHEN transactions.type = 'expense' THEN transactions.amount ELSE 0 END), 0) AS total_expense
             FROM $table_name AS transactions
             WHERE transactions.user_id = %d",
            $user_id
        );
        $transactions_sql = $wpdb->prepare(
            "SELECT transactions.*, parcels.name AS parcel_name, farms.name AS farm_name,
                    activities.title AS activity_title
             FROM $table_name AS transactions
             LEFT JOIN $parcels_table AS parcels
                ON parcels.id = transactions.parcel_id AND parcels.user_id = transactions.user_id
             LEFT JOIN $farms_table AS farms
                ON farms.id = transactions.farm_id AND farms.user_id = transactions.user_id
             LEFT JOIN $activities_table AS activities
                ON activities.id = transactions.activity_id AND activities.user_id = transactions.user_id
             WHERE transactions.user_id = %d
             ORDER BY transactions.created_at DESC",
            $user_id
        );
    } else {
        $totals_sql = $wpdb->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN transactions.type = 'income' THEN transactions.amount ELSE 0 END), 0) AS total_income,
                COALESCE(SUM(CASE WHEN transactions.type = 'expense' THEN transactions.amount ELSE 0 END), 0) AS total_expense
             FROM $table_name AS transactions
             WHERE transactions.user_id = %d AND transactions.category = %s",
            $user_id,
            $category
        );
        $transactions_sql = $wpdb->prepare(
            "SELECT transactions.*, parcels.name AS parcel_name, farms.name AS farm_name,
                    activities.title AS activity_title
             FROM $table_name AS transactions
             LEFT JOIN $parcels_table AS parcels
                ON parcels.id = transactions.parcel_id AND parcels.user_id = transactions.user_id
             LEFT JOIN $farms_table AS farms
                ON farms.id = transactions.farm_id AND farms.user_id = transactions.user_id
             LEFT JOIN $activities_table AS activities
                ON activities.id = transactions.activity_id AND activities.user_id = transactions.user_id
             WHERE transactions.user_id = %d AND transactions.category = %s
             ORDER BY transactions.created_at DESC",
            $user_id,
            $category
        );
    }

    $totals = $wpdb->get_row( $totals_sql );
    $transactions = $wpdb->get_results( $transactions_sql );

    $total_income = $totals
        ? (float) $totals->total_income
        : 0;

    $total_expense = $totals
        ? (float) $totals->total_expense
        : 0;

    return array(
        'total_income'  => $total_income,
        'total_expense' => $total_expense,
        'balance'       => $total_income - $total_expense,
        'transactions'  => $transactions,
    );
}

function baykat_finance_get_activity_transactions( $user_id ) {
    global $wpdb;

    $transactions_table = baykat_finance_get_table_name();
    $activities_table = baykat_farm_table_name( 'activities' );
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT transactions.*
             FROM $transactions_table AS transactions
             INNER JOIN $activities_table AS activities
                ON activities.id = transactions.activity_id AND activities.user_id = transactions.user_id
             WHERE transactions.user_id = %d
             ORDER BY transactions.created_at DESC, transactions.id DESC",
            $user_id
        )
    );
    $activity_finances = array();

    foreach ( (array) $results as $transaction ) {
        $activity_id = (int) $transaction->activity_id;
        if ( ! isset( $activity_finances[ $activity_id ] ) ) {
            $activity_finances[ $activity_id ] = array(
                'transactions' => array(),
                'income'       => 0,
                'expense'      => 0,
            );
        }

        $activity_finances[ $activity_id ]['transactions'][] = $transaction;
        $activity_finances[ $activity_id ][ $transaction->type ] += (float) $transaction->amount;
    }

    return $activity_finances;
}