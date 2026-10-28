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
        'commercialization' => 'Commercialisation',
    );
}

function baykat_finance_expense_category_label( $category ) {
    $categories = baykat_finance_expense_categories();

    return isset( $categories[ $category ] ) ? $categories[ $category ] : $categories['general'];
}

function baykat_finance_process_sale_cancellation( $user_id ) {
    if ( ! isset( $_POST['baykat_cancel_sale'] ) ) {
        return null;
    }

    check_admin_referer( 'baykat_cancel_sale' );
    $transaction_id = baykat_farm_request_id( 'transaction_id' );
    if ( ! $transaction_id ) {
        return array( 'type' => 'error', 'message' => 'La vente à annuler est invalide.' );
    }

    global $wpdb;
    $table_name = baykat_finance_get_table_name();
    $transaction = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, farm_id, category, type, cancelled_at
             FROM $table_name
             WHERE id = %d",
            $transaction_id
        )
    );
    if ( '' !== $wpdb->last_error ) {
        return array( 'type' => 'error', 'message' => 'Impossible de vérifier la vente à annuler. Veuillez réessayer.' );
    }
    if ( ! $transaction || 'sale' !== $transaction->category || 'income' !== $transaction->type ) {
        return array( 'type' => 'error', 'message' => 'Cette vente n’existe pas ou ne peut pas être annulée.' );
    }
    if ( ! empty( $transaction->cancelled_at ) ) {
        return array( 'type' => 'error', 'message' => 'Cette vente a déjà été annulée.' );
    }
    if ( ! baykat_farm_user_can_edit_farm( (int) $transaction->farm_id, $user_id ) ) {
        return array( 'type' => 'error', 'message' => 'Votre rôle sur cette ferme ne permet pas d’annuler cette vente.' );
    }

    $updated = $wpdb->query(
        $wpdb->prepare(
            "UPDATE $table_name SET cancelled_at = %s, cancelled_by = %d WHERE id = %d AND cancelled_at IS NULL",
            current_time( 'mysql' ),
            $user_id,
            $transaction_id
        )
    );
    if ( false === $updated ) {
        return array( 'type' => 'error', 'message' => 'La vente n’a pas pu être annulée. Veuillez réessayer.' );
    }
    if ( 0 === $updated ) {
        return array( 'type' => 'error', 'message' => 'Cette vente a déjà été annulée.' );
    }

    return array( 'type' => 'success', 'message' => 'Vente annulée. Elle reste dans l’historique et sa quantité est de nouveau disponible.' );
}

function baykat_finance_get_harvestable_crops( $user_id ) {
    global $wpdb;

    $crops = baykat_farm_filter_editable_records( baykat_farm_get_user_crops( $user_id ) );
    if ( ! $crops ) {
        return array();
    }

    $crop_ids = array_map( 'intval', wp_list_pluck( $crops, 'id' ) );
    $transactions_table = baykat_finance_get_table_name();
    $sold_quantities = $wpdb->get_results(
        "SELECT crop_id, COALESCE(SUM(quantity), 0) AS sold_quantity
         FROM $transactions_table
         WHERE crop_id IN (" . implode( ', ', $crop_ids ) . ")
            AND category = 'sale' AND type = 'income' AND cancelled_at IS NULL
         GROUP BY crop_id"
    );
    if ( '' !== $wpdb->last_error ) {
        return new WP_Error( 'baykat_sale_balance_unavailable', 'Impossible de calculer les récoltes déjà vendues.' );
    }

    $sold_by_crop = array();
    foreach ( (array) $sold_quantities as $sold ) {
        $sold_by_crop[ (int) $sold->crop_id ] = (float) $sold->sold_quantity;
    }

    return array_values(
        array_filter(
            array_map(
                function ( $crop ) use ( $sold_by_crop ) {
                    $harvested_quantity = isset( $crop->harvested_quantity ) ? (float) $crop->harvested_quantity : 0;
                    $sold_quantity = isset( $sold_by_crop[ (int) $crop->id ] ) ? $sold_by_crop[ (int) $crop->id ] : 0;
                    $crop->available_harvest_quantity = max( 0, $harvested_quantity - $sold_quantity );
                    $crop->harvest_sale_unavailable_reason = '';

                    if ( 'harvested' !== $crop->status ) {
                        return null;
                    }
                    if ( $harvested_quantity <= 0 ) {
                        $crop->harvest_sale_unavailable_reason = 'quantité récoltée manquante';
                    } elseif ( empty( $crop->harvested_unit ) ) {
                        $crop->harvest_sale_unavailable_reason = 'unité de récolte manquante';
                    } elseif ( $crop->available_harvest_quantity <= 0.0005 ) {
                        $crop->harvest_sale_unavailable_reason = 'récolte déjà entièrement vendue';
                    }

                    return $crop;
                },
                $crops
            )
        )
    );
}

/**
 * Traite l'envoi du formulaire d'ajout d'une transaction.
 *
 * @param int $user_id Identifiant de l'utilisateur courant.
 * @return array|null Message à afficher, ou null si le formulaire n'a pas été envoyé.
 */
function baykat_finance_process_transaction_submission( $user_id, $expected_category = 'general' ) {
    global $wpdb;

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
        if ( 'sale' === $expected_category ) {
            return array(
                'type' => 'error',
                'message' => 'La saisie manuelle des ventes est suspendue jusqu’à l’intégration officielle avec BAYKAT.',
            );
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
    if ( $is_activity_submission && 'expense' !== $type ) {
        return array( 'type' => 'error', 'message' => 'Une activité peut porter un coût, mais ses revenus doivent être enregistrés comme recette distincte.' );
    }

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
    $submitted_campaign_id = 0;
    if ( isset( $_POST['campaign_id'] ) ) {
        if ( ! is_scalar( $_POST['campaign_id'] ) ) {
            return array( 'type' => 'error', 'message' => 'Veuillez choisir une campagne valide.' );
        }
        $raw_campaign_id = trim( (string) wp_unslash( $_POST['campaign_id'] ) );
        if ( '' !== $raw_campaign_id ) {
            $submitted_campaign_id = baykat_farm_request_id( 'campaign_id' );
            if ( ! $submitted_campaign_id ) {
                return array( 'type' => 'error', 'message' => 'Veuillez choisir une campagne valide.' );
            }
        }
    }
    $campaign_id = $submitted_campaign_id;
    $activity_campaign_id = 0;
    $operation_date = isset( $_POST['operation_date'] ) && is_string( $_POST['operation_date'] )
        ? sanitize_text_field( wp_unslash( $_POST['operation_date'] ) )
        : '';

    $description = isset( $_POST['description'] ) && is_string( $_POST['description'] )
        ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) )
        : '';

    if (
        ! in_array( $type, array( 'income', 'expense' ), true )
        || $amount < 0.01
        || $amount > 9999999999999.99
        || ! baykat_farm_valid_date( $operation_date )
    ) {
        return array(
            'type'    => 'error',
            'message' => 'Veuillez saisir un type, un montant et une date d’opération valides.',
        );
    }

    $item_name = null;
    $quantity = null;
    $unit = null;
    $crop_id = null;
    $activity_id = null;
    $activity = null;
    $parcel_id = baykat_farm_request_id( 'parcel_id' );
    $farm_id = null;
    $submitted_farm_id = baykat_farm_request_id( 'farm_id' );

    if ( $is_item_submission && 'sale' === $category ) {
        $crop_id = baykat_farm_request_id( 'crop_id' );
        if ( ! $crop_id ) {
            return array( 'type' => 'error', 'message' => 'Choisissez une culture récoltée avec une quantité encore disponible avant d’enregistrer une vente.' );
        }
        $sale_crop_parcel_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT parcel_id FROM " . baykat_farm_table_name( 'crops' ) . " WHERE id = %d",
                $crop_id
            )
        );
        if ( ! $sale_crop_parcel_id ) {
            return array( 'type' => 'error', 'message' => 'La culture sélectionnée n’existe pas.' );
        }
        $parcel_id = (int) $sale_crop_parcel_id;
    }

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
                "SELECT id, parcel_id, campaign_id FROM $activities_table WHERE id = %d",
                $activity_id
            )
        );
        if ( '' !== $wpdb->last_error ) {
            return array( 'type' => 'error', 'message' => 'Impossible de vérifier l’activité sélectionnée. Veuillez réessayer.' );
        }

        $activity_parcel = $activity ? baykat_farm_get_user_parcel( (int) $activity->parcel_id, $user_id ) : null;
        if ( ! $activity || ! $activity_parcel || ! baykat_farm_user_owns_farm( (int) $activity_parcel->farm_id, $user_id ) ) {
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
        $activity_campaign_id = (int) $activity->campaign_id;
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

    $parcel = null;
    if ( $parcel_id ) {
        $parcel = baykat_farm_get_user_parcel( $parcel_id, $user_id );
        if ( ! $parcel ) {
            return array( 'type' => 'error', 'message' => 'La parcelle sélectionnée ne vous appartient pas.' );
        }
        $farm_id = (int) $parcel->farm_id;
        if ( $submitted_farm_id && $submitted_farm_id !== $farm_id ) {
            return array( 'type' => 'error', 'message' => 'La ferme sélectionnée ne correspond pas à cette parcelle.' );
        }
    } elseif ( 'expense' === $type && ! $is_item_submission && $submitted_farm_id ) {
        $farm_id = $submitted_farm_id;
    } else {
        return array( 'type' => 'error', 'message' => 'Choisissez une parcelle, ou une ferme pour enregistrer une charge commune.' );
    }

    if ( ! baykat_farm_user_can_edit_farm( $farm_id, $user_id ) ) {
        return array( 'type' => 'error', 'message' => 'Votre rôle sur cette ferme ne permet pas d’ajouter des opérations.' );
    }

    if ( $activity_id && ! $is_activity_submission ) {
        global $wpdb;
        $activities_table = baykat_farm_table_name( 'activities' );
        $activity_record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT parcel_id, campaign_id FROM $activities_table WHERE id = %d",
                $activity_id
            )
        );
        if ( '' !== $wpdb->last_error ) {
            return array( 'type' => 'error', 'message' => 'Impossible de vérifier l’activité sélectionnée. Veuillez réessayer.' );
        }
        if ( ! $activity_record || (int) $activity_record->parcel_id !== $parcel_id || ! baykat_farm_user_owns_farm( (int) $parcel->farm_id, $user_id ) ) {
            return array(
                'type'    => 'error',
                'message' => 'L’activité doit appartenir à la parcelle choisie.',
            );
        }
        $activity_campaign_id = (int) $activity_record->campaign_id;
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
            ? baykat_farm_normalize_unit( wp_unslash( $_POST['unit'] ) )
            : '';

        if (
            ( ! ( 'sale' === $category && $is_item_submission ) && ( '' === $item_name || strlen( $item_name ) > 190 ) )
            || $quantity < 0.001
            || $quantity > 999999999999.999
            || ( ! ( 'sale' === $category && $is_item_submission ) && ( '' === $unit || strlen( $unit ) > 40 ) )
        ) {
            return array(
                'type'    => 'error',
                'message' => 'Veuillez renseigner un nom, une quantité, une unité et un montant valides.',
            );
        }
    }

    if ( isset( $_POST['crop_id'] ) && is_scalar( $_POST['crop_id'] ) ) {
        $raw_crop_id = trim( (string) wp_unslash( $_POST['crop_id'] ) );
        if ( '' !== $raw_crop_id ) {
            $crop_id = baykat_farm_request_id( 'crop_id' );
            $crop = $crop_id
                ? $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT crops.id, crops.parcel_id, crops.name, crops.status,
                                COALESCE(
                                    (SELECT SUM(harvests.quantity)
                                     FROM " . $wpdb->prefix . "baykat_harvests AS harvests
                                     WHERE harvests.crop_id = crops.id),
                                    crops.harvested_quantity,
                                    0
                                ) AS harvested_quantity,
                                crops.harvested_unit
                         FROM " . baykat_farm_table_name( 'crops' ) . " AS crops
                         WHERE crops.id = %d",
                        $crop_id
                    )
                )
                : null;
            if ( ! $crop || (int) $crop->parcel_id !== $parcel_id ) {
                return array( 'type' => 'error', 'message' => 'La culture doit appartenir à la parcelle choisie.' );
            }
            if ( $is_item_submission && 'sale' === $category ) {
                if ( 'harvested' !== $crop->status || (float) $crop->harvested_quantity <= 0 || empty( $crop->harvested_unit ) ) {
                    return array( 'type' => 'error', 'message' => 'La vente doit être rattachée à une culture récoltée avec une quantité et une unité renseignées.' );
                }

                $sold_quantity_value = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COALESCE(SUM(quantity), 0) FROM " . baykat_finance_get_table_name() . "
                         WHERE crop_id = %d AND category = 'sale' AND type = 'income' AND cancelled_at IS NULL",
                        $crop_id
                    )
                );
                if ( '' !== $wpdb->last_error ) {
                    return array( 'type' => 'error', 'message' => 'Impossible de vérifier la quantité déjà vendue. Veuillez réessayer.' );
                }
                $sold_quantity = (float) $sold_quantity_value;
                $available_quantity = (float) $crop->harvested_quantity - $sold_quantity;
                if ( round( $quantity, 3 ) > round( $available_quantity, 3 ) ) {
                    return array(
                        'type'    => 'error',
                        'message' => 'La quantité vendue dépasse la récolte restante (' . number_format( max( 0, $available_quantity ), 3, ',', ' ' ) . ' ' . $crop->harvested_unit . ').',
                    );
                }
                $item_name = $crop->name;
                $unit = baykat_farm_normalize_unit( $crop->harvested_unit );
            }
        }
    } elseif ( isset( $_POST['crop_id'] ) ) {
        return array( 'type' => 'error', 'message' => 'Veuillez choisir une culture valide.' );
    }

    if ( $activity_campaign_id ) {
        if ( $submitted_campaign_id && $submitted_campaign_id !== $activity_campaign_id ) {
            return array( 'type' => 'error', 'message' => 'La transaction doit rester rattachée à la campagne de son activité.' );
        }
        $campaign_id = $activity_campaign_id;
    }

    if ( ! $campaign_id && $crop_id ) {
        $crop_campaigns = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM " . baykat_farm_campaign_table_name() . " WHERE crop_id = %d AND campaign_type = 'real' AND archived_at IS NULL ORDER BY id ASC",
                $crop_id
            )
        );
        if ( '' !== $wpdb->last_error ) {
            return array( 'type' => 'error', 'message' => 'Impossible de vérifier la campagne de cette culture. Veuillez réessayer.' );
        }
        if ( count( $crop_campaigns ) > 1 ) {
            return array( 'type' => 'error', 'message' => 'Cette culture historique est liée à plusieurs campagnes. Sélectionnez explicitement la campagne concernée.' );
        }
        $campaign_id = $crop_campaigns ? (int) $crop_campaigns[0] : 0;
    }

    if ( $campaign_id ) {
        $validated_campaign_id = baykat_farm_campaign_validate_link( $campaign_id, $parcel_id, $user_id, $crop_id );
        if ( is_wp_error( $validated_campaign_id ) ) {
            return array( 'type' => 'error', 'message' => $validated_campaign_id->get_error_message() );
        }
        $campaign_id = $validated_campaign_id;
    }

    if ( $parcel_id && ! $campaign_id ) {
        return array( 'type' => 'error', 'message' => 'Rattachez cette opération à une campagne réelle. Seules les dépenses communes peuvent rester au niveau de la ferme.' );
    }

    global $wpdb;

    $inserted = $wpdb->insert(
        baykat_finance_get_table_name(),
        array(
            'user_id'     => $parcel ? (int) $parcel->user_id : baykat_farm_get_owner_id_for_farm( $farm_id ),
            'type'        => $type,
            'category'    => $category,
            'amount'      => $amount,
            'operation_date' => $operation_date,
            'description' => $description,
            'item_name'   => $item_name,
            'quantity'    => $quantity,
            'unit'        => $unit,
            'expense_category' => $expense_category,
            'farm_id'     => $farm_id,
            'parcel_id'   => $parcel_id ? $parcel_id : null,
            'activity_id' => $activity_id,
            'crop_id'     => $crop_id,
            'campaign_id' => $campaign_id ? $campaign_id : null,
        ),
        array( '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%f', '%s', '%s', '%d', '%d', '%d', '%d', '%d' )
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
    $crops_table = baykat_farm_table_name( 'crops' );
    $campaigns_table = baykat_farm_campaign_table_name();
    $visible_farms = baykat_farm_get_user_farms( $user_id );
    $visible_farm_ids = array_map( 'intval', wp_list_pluck( $visible_farms, 'id' ) );
    $farm_scope_sql = $visible_farm_ids
        ? ' OR transactions.farm_id IN (' . implode( ', ', $visible_farm_ids ) . ')'
        : '';

    if ( null === $category ) {
        $totals_sql = $wpdb->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN transactions.type = 'income' AND transactions.cancelled_at IS NULL THEN transactions.amount ELSE 0 END), 0) AS total_income,
                COALESCE(SUM(CASE WHEN transactions.type = 'expense' AND transactions.cancelled_at IS NULL THEN transactions.amount ELSE 0 END), 0) AS total_expense
             FROM $table_name AS transactions
             WHERE transactions.user_id = %d$farm_scope_sql",
            $user_id
        );
        $transactions_sql = $wpdb->prepare(
            "SELECT transactions.*, parcels.name AS parcel_name, farms.name AS farm_name,
                    activities.title AS activity_title, crops.name AS crop_name, campaigns.crop_name AS campaign_crop_name
             FROM $table_name AS transactions
             LEFT JOIN $parcels_table AS parcels
                ON parcels.id = transactions.parcel_id AND parcels.user_id = transactions.user_id
             LEFT JOIN $farms_table AS farms
                ON farms.id = transactions.farm_id AND farms.user_id = transactions.user_id
             LEFT JOIN $activities_table AS activities
                ON activities.id = transactions.activity_id AND activities.user_id = transactions.user_id
             LEFT JOIN $crops_table AS crops
                ON crops.id = transactions.crop_id AND crops.user_id = transactions.user_id
             LEFT JOIN $campaigns_table AS campaigns
                ON campaigns.id = transactions.campaign_id AND campaigns.campaign_type = 'real'
             WHERE transactions.user_id = %d$farm_scope_sql
             ORDER BY transactions.created_at DESC",
            $user_id
        );
    } else {
        $totals_sql = $wpdb->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN transactions.type = 'income' AND transactions.cancelled_at IS NULL THEN transactions.amount ELSE 0 END), 0) AS total_income,
                COALESCE(SUM(CASE WHEN transactions.type = 'expense' AND transactions.cancelled_at IS NULL THEN transactions.amount ELSE 0 END), 0) AS total_expense
             FROM $table_name AS transactions
             WHERE (transactions.user_id = %d$farm_scope_sql) AND transactions.category = %s",
            $user_id,
            $category
        );
        $transactions_sql = $wpdb->prepare(
            "SELECT transactions.*, parcels.name AS parcel_name, farms.name AS farm_name,
                    activities.title AS activity_title, crops.name AS crop_name, campaigns.crop_name AS campaign_crop_name
             FROM $table_name AS transactions
             LEFT JOIN $parcels_table AS parcels
                ON parcels.id = transactions.parcel_id AND parcels.user_id = transactions.user_id
             LEFT JOIN $farms_table AS farms
                ON farms.id = transactions.farm_id AND farms.user_id = transactions.user_id
             LEFT JOIN $activities_table AS activities
                ON activities.id = transactions.activity_id AND activities.user_id = transactions.user_id
             LEFT JOIN $crops_table AS crops
                ON crops.id = transactions.crop_id AND crops.user_id = transactions.user_id
             LEFT JOIN $campaigns_table AS campaigns
                ON campaigns.id = transactions.campaign_id AND campaigns.campaign_type = 'real'
             WHERE (transactions.user_id = %d$farm_scope_sql) AND transactions.category = %s
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
    $farms = baykat_farm_get_user_farms( $user_id );
    $farm_ids = array_map( 'intval', wp_list_pluck( $farms, 'id' ) );
    $farm_scope_sql = $farm_ids
        ? ' OR transactions.farm_id IN (' . implode( ', ', $farm_ids ) . ')'
        : '';
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT transactions.*
             FROM $transactions_table AS transactions
             INNER JOIN $activities_table AS activities
                ON activities.id = transactions.activity_id AND activities.user_id = transactions.user_id
             WHERE transactions.user_id = %d$farm_scope_sql
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
        if ( empty( $transaction->cancelled_at ) ) {
            $activity_finances[ $activity_id ][ $transaction->type ] += (float) $transaction->amount;
        }
    }

    return $activity_finances;
}