<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_farm_table_name( $table ) {
    global $wpdb;

    $allowed_tables = array( 'farms', 'parcels', 'crops', 'activities' );

    if ( ! in_array( $table, $allowed_tables, true ) ) {
        return '';
    }

    return $wpdb->prefix . 'baykat_' . $table;
}

function baykat_farm_request_id( $key ) {
    if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) {
        return 0;
    }

    $value = trim( (string) wp_unslash( $_POST[ $key ] ) );

    if ( '' === $value ) {
        return 0;
    }

    if ( ! preg_match( '/^[1-9][0-9]*$/D', $value ) ) {
        return 0;
    }

    return (int) $value;
}

function baykat_farm_request_coordinates() {
    $latitude_value  = isset( $_POST['farm_latitude'] ) && is_scalar( $_POST['farm_latitude'] )
        ? trim( (string) wp_unslash( $_POST['farm_latitude'] ) )
        : '';
    $longitude_value = isset( $_POST['farm_longitude'] ) && is_scalar( $_POST['farm_longitude'] )
        ? trim( (string) wp_unslash( $_POST['farm_longitude'] ) )
        : '';

    if ( '' === $latitude_value && '' === $longitude_value ) {
        return null;
    }

    if (
        '' === $latitude_value
        || '' === $longitude_value
        || ! is_numeric( $latitude_value )
        || ! is_numeric( $longitude_value )
    ) {
        return new WP_Error( 'invalid_farm_coordinates', 'Choisissez un emplacement valide sur la carte.' );
    }

    $latitude  = (float) $latitude_value;
    $longitude = (float) $longitude_value;

    if ( $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180 ) {
        return new WP_Error( 'invalid_farm_coordinates', 'Les coordonnées de la ferme sont hors limites.' );
    }

    return array(
        'latitude'  => $latitude,
        'longitude' => $longitude,
    );
}

function baykat_farm_get_user_farms( $user_id ) {
    global $wpdb;

    $table = baykat_farm_table_name( 'farms' );
    $members_table = baykat_farm_members_table_name();

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT farms.*, CASE WHEN farms.user_id = %d THEN 'owner' ELSE members.role END AS access_role
             FROM $table AS farms
             LEFT JOIN $members_table AS members
                ON members.farm_id = farms.id AND members.member_user_id = %d
             WHERE farms.user_id = %d OR members.member_user_id = %d
             ORDER BY farms.name ASC",
            $user_id,
            $user_id,
            $user_id,
            $user_id
        )
    );
}

function baykat_farm_get_user_parcels( $user_id ) {
    global $wpdb;

    $parcels_table = baykat_farm_table_name( 'parcels' );
    $farms_table = baykat_farm_table_name( 'farms' );
    $members_table = baykat_farm_members_table_name();

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT parcels.*, farms.name AS farm_name,
                    CASE WHEN farms.user_id = %d THEN 'owner' ELSE members.role END AS access_role
             FROM $parcels_table AS parcels
             INNER JOIN $farms_table AS farms
                ON farms.id = parcels.farm_id
             LEFT JOIN $members_table AS members
                ON members.farm_id = farms.id AND members.member_user_id = %d
             WHERE farms.user_id = %d OR members.member_user_id = %d
             ORDER BY farms.name ASC, parcels.name ASC",
            $user_id,
            $user_id,
            $user_id,
            $user_id
        )
    );
}

function baykat_farm_get_user_crops( $user_id ) {
    global $wpdb;

    $crops_table = baykat_farm_table_name( 'crops' );
    $parcels_table = baykat_farm_table_name( 'parcels' );
    $farms_table = baykat_farm_table_name( 'farms' );
    $members_table = baykat_farm_members_table_name();

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT crops.*, parcels.farm_id AS farm_id, parcels.name AS parcel_name, farms.name AS farm_name,
                    CASE WHEN farms.user_id = %d THEN 'owner' ELSE members.role END AS access_role
             FROM $crops_table AS crops
             INNER JOIN $parcels_table AS parcels
                ON parcels.id = crops.parcel_id
             INNER JOIN $farms_table AS farms
                ON farms.id = parcels.farm_id
             LEFT JOIN $members_table AS members
                ON members.farm_id = farms.id AND members.member_user_id = %d
             WHERE farms.user_id = %d OR members.member_user_id = %d
             ORDER BY crops.created_at DESC",
            $user_id,
            $user_id,
            $user_id,
            $user_id
        )
    );
}

function baykat_farm_get_user_activities( $user_id ) {
    global $wpdb;

    $activities_table = baykat_farm_table_name( 'activities' );
    $parcels_table = baykat_farm_table_name( 'parcels' );
    $farms_table = baykat_farm_table_name( 'farms' );
    $members_table = baykat_farm_members_table_name();

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT activities.*, parcels.farm_id AS farm_id, parcels.name AS parcel_name, farms.name AS farm_name,
                    CASE WHEN farms.user_id = %d THEN 'owner' ELSE members.role END AS access_role
             FROM $activities_table AS activities
             INNER JOIN $parcels_table AS parcels
                ON parcels.id = activities.parcel_id
             INNER JOIN $farms_table AS farms
                ON farms.id = parcels.farm_id
             LEFT JOIN $members_table AS members
                ON members.farm_id = farms.id AND members.member_user_id = %d
             WHERE farms.user_id = %d OR members.member_user_id = %d
             ORDER BY activities.activity_date DESC, activities.created_at DESC",
            $user_id,
            $user_id,
            $user_id,
            $user_id
        )
    );
}

function baykat_farm_user_owns_farm( $farm_id, $user_id ) {
    return baykat_farm_user_can_edit_farm( $farm_id, $user_id );
}

function baykat_farm_get_user_parcel( $parcel_id, $user_id ) {
    global $wpdb;

    $table = baykat_farm_table_name( 'parcels' );
    $farms_table = baykat_farm_table_name( 'farms' );

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT parcels.* FROM $table AS parcels
             INNER JOIN $farms_table AS farms ON farms.id = parcels.farm_id
             WHERE parcels.id = %d AND (farms.user_id = %d OR EXISTS (
                SELECT 1 FROM " . baykat_farm_members_table_name() . " AS members
                WHERE members.farm_id = farms.id AND members.member_user_id = %d
             ))",
            $parcel_id,
            $user_id,
            $user_id
        )
    );
}

function baykat_farm_get_accessible_record( $entity, $record_id, $user_id ) {
    global $wpdb;

    $farm_table = baykat_farm_table_name( 'farms' );
    $parcel_table = baykat_farm_table_name( 'parcels' );
    $table = baykat_farm_table_name( $entity );
    if ( ! $table ) {
        return null;
    }

    if ( 'farms' === $entity ) {
        $record = $wpdb->get_row(
            $wpdb->prepare( "SELECT farms.*, farms.id AS farm_id FROM $farm_table AS farms WHERE farms.id = %d", $record_id )
        );
    } elseif ( 'parcels' === $entity ) {
        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT parcels.*, parcels.farm_id FROM $parcel_table AS parcels WHERE parcels.id = %d",
                $record_id
            )
        );
    } else {
        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT records.*, parcels.farm_id
                 FROM $table AS records
                 INNER JOIN $parcel_table AS parcels ON parcels.id = records.parcel_id
                 WHERE records.id = %d",
                $record_id
            )
        );
    }

    return $record && baykat_farm_user_can_edit_farm( $record->farm_id, $user_id )
        ? $record
        : null;
}

function baykat_farm_valid_date( $date ) {
    if ( ! is_string( $date ) || '' === $date ) {
        return false;
    }

    $parsed_date = DateTime::createFromFormat( '!Y-m-d', $date );

    return $parsed_date && $parsed_date->format( 'Y-m-d' ) === $date;
}

function baykat_farm_handle_image_upload( $field_name, $user_id ) {
    if ( ! isset( $_FILES[ $field_name ] ) ) {
        return null;
    }

    if ( ! is_array( $_FILES[ $field_name ] ) || ! isset( $_FILES[ $field_name ]['error'] ) ) {
        return new WP_Error( 'invalid_image_upload', 'Le fichier image envoyé est invalide.' );
    }

    $file = $_FILES[ $field_name ];

    if ( UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
        return null;
    }

    if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! isset( $file['tmp_name'], $file['name'], $file['size'] ) ) {
        return new WP_Error( 'image_upload_failed', 'La photo n’a pas pu être téléversée. Réessayez avec un autre fichier.' );
    }

    if ( (int) $file['size'] > 5 * MB_IN_BYTES ) {
        return new WP_Error( 'image_too_large', 'La photo doit faire 5 Mo maximum.' );
    }

    $allowed_mimes = array(
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'webp'         => 'image/webp',
    );
    $file_type = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed_mimes );

    if (
        empty( $file_type['type'] )
        || ! in_array( $file_type['type'], array_values( $allowed_mimes ), true )
        || wp_get_image_mime( $file['tmp_name'] ) !== $file_type['type']
    ) {
        return new WP_Error( 'invalid_image_type', 'Format non accepté. Choisissez une image JPG, PNG ou WebP.' );
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $attachment_id = media_handle_upload(
        $field_name,
        0,
        array(),
        array(
            'test_form' => false,
            'mimes'     => $allowed_mimes,
        )
    );

    if ( is_wp_error( $attachment_id ) ) {
        return $attachment_id;
    }

    wp_update_post(
        array(
            'ID'          => $attachment_id,
            'post_author' => $user_id,
        )
    );

    return (int) $attachment_id;
}

function baykat_farm_process_image_update( $user_id, $entity ) {
    $entities = array(
        'farm'     => 'farms',
        'parcel'   => 'parcels',
        'crop'     => 'crops',
        'activity' => 'activities',
    );

    if ( ! isset( $entities[ $entity ] ) ) {
        return null;
    }

    $action_name = 'baykat_update_' . $entity . '_image';

    if ( ! isset( $_POST[ $action_name ] ) ) {
        return null;
    }

    check_admin_referer( $action_name );

    $record_id = baykat_farm_request_id( $action_name );

    if ( 0 === $record_id ) {
        return array( 'type' => 'error', 'message' => 'L’enregistrement à modifier est invalide.' );
    }

    global $wpdb;
    $table = baykat_farm_table_name( $entities[ $entity ] );
    $record = baykat_farm_get_accessible_record( $entities[ $entity ], $record_id, $user_id );

    if ( ! $record ) {
        return array( 'type' => 'error', 'message' => 'Cet enregistrement n’existe pas ou ne vous appartient pas.' );
    }
    $image_id = baykat_farm_handle_image_upload( $entity . '_image', $user_id );

    if ( is_wp_error( $image_id ) ) {
        return array( 'type' => 'error', 'message' => $image_id->get_error_message() );
    }

    if ( ! $image_id ) {
        return array( 'type' => 'error', 'message' => 'Choisissez une photo avant de l’enregistrer.' );
    }

    $updated = $wpdb->update(
        $table,
        array( 'image_id' => $image_id ),
        array( 'id' => $record_id ),
        array( '%d' ),
        array( '%d' )
    );

    if ( false === $updated ) {
        wp_delete_attachment( $image_id, true );

        return array( 'type' => 'error', 'message' => 'La photo n’a pas pu être associée à cet enregistrement.' );
    }

    return array( 'type' => 'success', 'message' => 'Photo mise à jour.' );
}

function baykat_farm_process_record_action( $user_id, $entity ) {
    $entities = array(
        'farm'     => 'farms',
        'parcel'   => 'parcels',
        'crop'     => 'crops',
        'activity' => 'activities',
    );

    if ( ! isset( $entities[ $entity ] ) ) {
        return null;
    }

    $update_action = 'baykat_update_' . $entity;
    $delete_action = 'baykat_delete_' . $entity;
    $is_update = isset( $_POST[ $update_action ] );
    $is_delete = isset( $_POST[ $delete_action ] );

    if ( ! $is_update && ! $is_delete ) {
        return null;
    }

    $action = $is_update ? $update_action : $delete_action;
    check_admin_referer( $action );
    $record_id = baykat_farm_request_id( $action . '_id' );

    if ( 0 === $record_id ) {
        return array( 'type' => 'error', 'message' => 'L’enregistrement à modifier est invalide.' );
    }

    global $wpdb;
    $table = baykat_farm_table_name( $entities[ $entity ] );
    $record = baykat_farm_get_accessible_record( $entities[ $entity ], $record_id, $user_id );

    if ( ! $record ) {
        return array( 'type' => 'error', 'message' => 'Cet enregistrement n’existe pas ou ne vous appartient pas.' );
    }
    if ( $is_delete && 'farm' === $entity && 'owner' !== baykat_farm_get_access_role( $record_id, $user_id ) ) {
        return array( 'type' => 'error', 'message' => 'Seul le propriétaire peut supprimer la ferme.' );
    }
    $record_owner_id = (int) $record->user_id;

    if ( $is_delete ) {
        $dependency_count = 0;
        $preserved_finance_count = 0;

        if ( 'farm' === $entity ) {
            $parcels_table = baykat_farm_table_name( 'parcels' );
            $transactions_table = baykat_finance_get_table_name();
            $stock_movements_table = $wpdb->prefix . 'baykat_stock_movements';
            $dependency_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT
                        (SELECT COUNT(*) FROM $parcels_table WHERE farm_id = %d)
                        + (SELECT COUNT(*) FROM $transactions_table WHERE farm_id = %d)
                        + (SELECT COUNT(*) FROM $stock_movements_table WHERE farm_id = %d OR destination_farm_id = %d)",
                    $record_id,
                    $record_id,
                    $record_id,
                    $record_id
                )
            );
        } elseif ( 'parcel' === $entity ) {
            $crops_table = baykat_farm_table_name( 'crops' );
            $activities_table = baykat_farm_table_name( 'activities' );
            $transactions_table = baykat_finance_get_table_name();
            $stock_movements_table = $wpdb->prefix . 'baykat_stock_movements';
            $dependency_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT
                        (SELECT COUNT(*) FROM $crops_table WHERE parcel_id = %d)
                        + (SELECT COUNT(*) FROM $activities_table WHERE parcel_id = %d)
                        + (SELECT COUNT(*) FROM $transactions_table WHERE parcel_id = %d)
                        + (SELECT COUNT(*) FROM $stock_movements_table WHERE parcel_id = %d OR destination_parcel_id = %d)",
                    $record_id,
                    $record_id,
                    $record_id,
                    $record_id,
                    $record_id
                )
            );
        } elseif ( 'crop' === $entity ) {
            $transactions_table = baykat_finance_get_table_name();
            $stock_movements_table = $wpdb->prefix . 'baykat_stock_movements';
            $harvests_table = $wpdb->prefix . 'baykat_harvests';
            $dependency_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT
                        (SELECT COUNT(*) FROM $transactions_table WHERE crop_id = %d)
                        + (SELECT COUNT(*) FROM $stock_movements_table WHERE crop_id = %d)
                        + (SELECT COUNT(*) FROM $harvests_table WHERE crop_id = %d)",
                    $record_id,
                    $record_id,
                    $record_id
                )
            );
        } elseif ( 'activity' === $entity ) {
            $transactions_table = baykat_finance_get_table_name();
            $stock_movements_table = $wpdb->prefix . 'baykat_stock_movements';
            $preserved_finance_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $transactions_table WHERE activity_id = %d",
                    $record_id
                )
            );
            $dependency_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $stock_movements_table WHERE activity_id = %d",
                    $record_id
                )
            );
        }

        if ( $dependency_count > 0 ) {
            $message = 'Suppression impossible : des données sont encore liées. Supprimez ou réaffectez-les avant de réessayer.';

            return array( 'type' => 'error', 'message' => $message );
        }

        if ( $preserved_finance_count > 0 ) {
            $transactions_table = baykat_finance_get_table_name();
            $unlinked = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $transactions_table SET activity_id = NULL WHERE activity_id = %d",
                    $record_id
                )
            );
            if ( false === $unlinked ) {
                return array( 'type' => 'error', 'message' => 'Les opérations financières liées n’ont pas pu être conservées. L’activité n’a pas été supprimée.' );
            }
        }

        $deleted = $wpdb->delete(
            $table,
            array( 'id' => $record_id ),
            array( '%d' )
        );

        if ( false === $deleted ) {
            return array( 'type' => 'error', 'message' => 'La suppression a échoué. Veuillez réessayer.' );
        }
        if ( 'farm' === $entity ) {
            $wpdb->delete( baykat_farm_members_table_name(), array( 'farm_id' => $record_id ), array( '%d' ) );
        }

        return array(
            'type'    => 'success',
            'message' => $preserved_finance_count > 0
                ? 'Activité supprimée. Les opérations financières ont été conservées sur la parcelle.'
                : 'Enregistrement supprimé.',
        );
    }

    $data = array();
    $formats = array();

    if ( 'farm' === $entity ) {
        $name = isset( $_POST['farm_name'] ) && is_string( $_POST['farm_name'] )
            ? sanitize_text_field( wp_unslash( $_POST['farm_name'] ) )
            : '';
        $location = isset( $_POST['farm_location'] ) && is_string( $_POST['farm_location'] )
            ? sanitize_text_field( wp_unslash( $_POST['farm_location'] ) )
            : '';
        $area_value = isset( $_POST['farm_area_ha'] ) && is_scalar( $_POST['farm_area_ha'] )
            ? trim( (string) wp_unslash( $_POST['farm_area_ha'] ) )
            : '';
        $area = '' === $area_value ? null : ( is_numeric( $area_value ) ? (float) $area_value : 0 );

        if ( '' === $name || strlen( $name ) > 190 || ( null !== $area && ( $area <= 0 || $area > 999999999.999 ) ) || strlen( $location ) > 190 ) {
            return array( 'type' => 'error', 'message' => 'Veuillez vérifier le nom, la localisation et la superficie de la ferme.' );
        }

        $coordinates = baykat_farm_request_coordinates();
        if ( is_wp_error( $coordinates ) ) {
            return array( 'type' => 'error', 'message' => $coordinates->get_error_message() );
        }

        $data = array(
            'name'      => $name,
            'location'  => $location,
            'latitude'  => $coordinates ? $coordinates['latitude'] : null,
            'longitude' => $coordinates ? $coordinates['longitude'] : null,
            'area_ha'   => $area,
        );
        $formats = array( '%s', '%s', '%f', '%f', '%f' );
    } elseif ( 'parcel' === $entity ) {
        $farm_id = baykat_farm_request_id( 'farm_id' );
        $name = isset( $_POST['parcel_name'] ) && is_string( $_POST['parcel_name'] )
            ? sanitize_text_field( wp_unslash( $_POST['parcel_name'] ) )
            : '';
        $area_value = isset( $_POST['parcel_area_m2'] ) && is_scalar( $_POST['parcel_area_m2'] )
            ? trim( (string) wp_unslash( $_POST['parcel_area_m2'] ) )
            : '';
        $area = '' === $area_value ? null : ( is_numeric( $area_value ) ? (float) $area_value : 0 );
        $status = isset( $_POST['parcel_status'] ) && is_string( $_POST['parcel_status'] )
            ? sanitize_key( wp_unslash( $_POST['parcel_status'] ) )
            : '';

        if ( ! baykat_farm_user_owns_farm( $farm_id, $user_id ) || baykat_farm_get_owner_id_for_farm( $farm_id ) !== $record_owner_id || '' === $name || strlen( $name ) > 190 || ( null !== $area && ( $area <= 0 || $area > 9999999999.99 ) ) || ! in_array( $status, array( 'planned', 'growing', 'harvested', 'paused' ), true ) ) {
            return array( 'type' => 'error', 'message' => 'Veuillez vérifier la ferme, le nom, la superficie et le statut de la parcelle.' );
        }

        if ( (int) $record->farm_id !== $farm_id ) {
            $transactions_table = baykat_finance_get_table_name();
            $transaction_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $transactions_table WHERE parcel_id = %d",
                    $record_id
                )
            );

            if ( $transaction_count > 0 ) {
                return array( 'type' => 'error', 'message' => 'Cette parcelle ne peut pas être déplacée car elle possède des transactions Finance liées.' );
            }
        }

        $data = array( 'farm_id' => $farm_id, 'name' => $name, 'area_m2' => $area, 'status' => $status );
        $formats = array( '%d', '%s', '%f', '%s' );
    } elseif ( 'crop' === $entity ) {
        $parcel_id = baykat_farm_request_id( 'parcel_id' );
        $parcel = baykat_farm_get_user_parcel( $parcel_id, $user_id );
        $name = isset( $_POST['crop_name'] ) && is_string( $_POST['crop_name'] )
            ? sanitize_text_field( wp_unslash( $_POST['crop_name'] ) )
            : '';
        $variety = isset( $_POST['crop_variety'] ) && is_string( $_POST['crop_variety'] )
            ? sanitize_text_field( wp_unslash( $_POST['crop_variety'] ) )
            : '';
        $planted_at = isset( $_POST['planted_at'] ) && is_string( $_POST['planted_at'] )
            ? sanitize_text_field( wp_unslash( $_POST['planted_at'] ) )
            : '';
        $expected_date = isset( $_POST['expected_harvest_date'] ) && is_string( $_POST['expected_harvest_date'] )
            ? sanitize_text_field( wp_unslash( $_POST['expected_harvest_date'] ) )
            : '';
        $status = isset( $_POST['crop_status'] ) && is_string( $_POST['crop_status'] )
            ? sanitize_key( wp_unslash( $_POST['crop_status'] ) )
            : '';
        $harvested_quantity = null === $record->harvested_quantity ? null : (float) $record->harvested_quantity;
        $harvested_unit = (string) $record->harvested_unit;

        if ( ! $parcel || (int) $parcel->user_id !== $record_owner_id || ! baykat_farm_user_can_edit_farm( (int) $parcel->farm_id, $user_id ) || '' === $name || strlen( $name ) > 190 || strlen( $variety ) > 190 || ( '' !== $planted_at && ! baykat_farm_valid_date( $planted_at ) ) || ( '' !== $expected_date && ! baykat_farm_valid_date( $expected_date ) ) || ! in_array( $status, array( 'planned', 'growing', 'harvested', 'paused' ), true ) ) {
            return array( 'type' => 'error', 'message' => 'Veuillez vérifier la parcelle, la culture, les dates et le statut.' );
        }

        if ( ( $harvested_quantity > 0 && ( 'harvested' !== $status || (int) $record->parcel_id !== $parcel_id ) )
            || ( 'harvested' === $status && 'harvested' !== $record->status ) ) {
            return array( 'type' => 'error', 'message' => 'Pour récolter une culture ou ajouter une nouvelle quantité, utilisez l’action « Enregistrer une récolte » sur sa fiche.' );
        }

        $transactions_table = baykat_finance_get_table_name();
        $sold_quantity_value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(quantity), 0) FROM $transactions_table WHERE crop_id = %d AND category = 'sale' AND type = 'income' AND cancelled_at IS NULL",
                $record_id
            )
        );
        if ( '' !== $wpdb->last_error ) {
            return array( 'type' => 'error', 'message' => 'Impossible de vérifier les ventes liées à cette culture. Veuillez réessayer.' );
        }
        $sold_quantity = (float) $sold_quantity_value;
        if ( $sold_quantity > 0 && ( (int) $record->parcel_id !== $parcel_id || 'harvested' !== $status || null === $harvested_quantity || round( $harvested_quantity, 3 ) < round( $sold_quantity, 3 ) || $record->harvested_unit !== $harvested_unit ) ) {
            return array( 'type' => 'error', 'message' => 'Cette culture a déjà des ventes. Conservez sa parcelle, son statut récolté, son unité et une quantité au moins égale au total vendu (' . number_format( $sold_quantity, 3, ',', ' ' ) . ').' );
        }

        $data = array(
            'parcel_id'             => $parcel_id,
            'name'                  => $name,
            'variety'               => $variety,
            'planted_at'            => '' === $planted_at ? null : $planted_at,
            'expected_harvest_date' => '' === $expected_date ? null : $expected_date,
            'harvested_quantity'    => $harvested_quantity,
            'harvested_unit'        => '' === $harvested_unit ? null : $harvested_unit,
            'status'                => $status,
        );
        $formats = array( '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s' );
    } else {
        $parcel_id = baykat_farm_request_id( 'parcel_id' );
        $parcel = baykat_farm_get_user_parcel( $parcel_id, $user_id );
        $title = isset( $_POST['activity_title'] ) && is_string( $_POST['activity_title'] )
            ? sanitize_text_field( wp_unslash( $_POST['activity_title'] ) )
            : '';
        $activity_type = isset( $_POST['activity_type'] ) && is_string( $_POST['activity_type'] )
            ? sanitize_key( wp_unslash( $_POST['activity_type'] ) )
            : '';
        $activity_date = isset( $_POST['activity_date'] ) && is_string( $_POST['activity_date'] )
            ? sanitize_text_field( wp_unslash( $_POST['activity_date'] ) )
            : '';
        $notes = isset( $_POST['activity_notes'] ) && is_string( $_POST['activity_notes'] )
            ? sanitize_textarea_field( wp_unslash( $_POST['activity_notes'] ) )
            : '';

        if ( ! $parcel || ! baykat_farm_user_can_edit_farm( (int) $parcel->farm_id, $user_id ) || (int) $parcel->user_id !== $record_owner_id || '' === $title || strlen( $title ) > 190 || ! in_array( $activity_type, array( 'sowing', 'watering', 'fertilizing', 'treatment', 'harvest', 'other' ), true ) || ! baykat_farm_valid_date( $activity_date ) ) {
            return array( 'type' => 'error', 'message' => 'Veuillez vérifier la parcelle, le titre, le type et la date de l’activité.' );
        }

        $activity_finances_table = baykat_finance_get_table_name();
        $has_finances = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $activity_finances_table WHERE activity_id = %d",
                $record_id
            )
        );
        if ( $has_finances > 0 && (int) $record->parcel_id !== $parcel_id ) {
            return array( 'type' => 'error', 'message' => 'Cette activité possède des opérations financières. Pour préserver leur rattachement, elles ne peuvent pas être déplacées vers une autre parcelle.' );
        }

        $data = array(
            'parcel_id'     => $parcel_id,
            'title'         => $title,
            'activity_type' => $activity_type,
            'activity_date' => $activity_date,
            'notes'         => $notes,
        );
        $formats = array( '%d', '%s', '%s', '%s', '%s' );
    }

    $updated = $wpdb->update(
        $table,
        $data,
        array( 'id' => $record_id ),
        $formats,
        array( '%d' )
    );

    if ( false === $updated ) {
        return array( 'type' => 'error', 'message' => 'La modification a échoué. Veuillez réessayer.' );
    }

    return array( 'type' => 'success', 'message' => 'Modifications enregistrées.' );
}

function baykat_farm_process_farm_submission( $user_id ) {
    if ( ! isset( $_POST['baykat_add_farm'] ) ) {
        return null;
    }

    check_admin_referer( 'baykat_add_farm' );

    $name = isset( $_POST['farm_name'] ) && is_string( $_POST['farm_name'] )
        ? sanitize_text_field( wp_unslash( $_POST['farm_name'] ) )
        : '';
    $location = isset( $_POST['farm_location'] ) && is_string( $_POST['farm_location'] )
        ? sanitize_text_field( wp_unslash( $_POST['farm_location'] ) )
        : '';
    $area_value = isset( $_POST['farm_area_ha'] ) && is_scalar( $_POST['farm_area_ha'] )
        ? trim( (string) wp_unslash( $_POST['farm_area_ha'] ) )
        : '';
    $area = '' === $area_value ? null : ( is_numeric( $area_value ) ? (float) $area_value : 0 );
    $coordinates = baykat_farm_request_coordinates();

    if (
        '' === $name
        || strlen( $name ) > 190
        || strlen( $location ) > 190
        || ( null !== $area && ( $area <= 0 || $area > 999999999.999 ) )
    ) {
        return array( 'type' => 'error', 'message' => 'Veuillez saisir un nom de ferme, une localisation et une superficie valides.' );
    }

    if ( is_wp_error( $coordinates ) ) {
        return array( 'type' => 'error', 'message' => $coordinates->get_error_message() );
    }

    $image_id = baykat_farm_handle_image_upload( 'farm_image', $user_id );

    if ( is_wp_error( $image_id ) ) {
        return array( 'type' => 'error', 'message' => $image_id->get_error_message() );
    }

    global $wpdb;
    $inserted = $wpdb->insert(
        baykat_farm_table_name( 'farms' ),
        array(
            'user_id'  => $user_id,
            'name'     => $name,
            'location' => $location,
            'latitude' => $coordinates ? $coordinates['latitude'] : null,
            'longitude' => $coordinates ? $coordinates['longitude'] : null,
            'area_ha'  => $area,
            'image_id' => $image_id,
        ),
        array( '%d', '%s', '%s', '%f', '%f', '%f', '%d' )
    );

    if ( false === $inserted ) {
        if ( $image_id ) {
            wp_delete_attachment( $image_id, true );
        }

        return array( 'type' => 'error', 'message' => 'La ferme n’a pas pu être enregistrée.' );
    }

    return array( 'type' => 'success', 'message' => 'Ferme enregistrée.' );
}

function baykat_farm_geocode_location() {
    if ( ! is_user_logged_in() || ! baykat_finance_user_can_access() ) {
        wp_send_json_error( array( 'message' => 'Vous n’êtes pas autorisé à rechercher un emplacement.' ), 403 );
    }

    check_ajax_referer( 'baykat_farm_geocode', 'nonce' );

    $query = isset( $_POST['query'] ) && is_string( $_POST['query'] )
        ? sanitize_text_field( wp_unslash( $_POST['query'] ) )
        : '';
    $query = trim( $query );

    if ( strlen( $query ) < 3 || strlen( $query ) > 190 ) {
        wp_send_json_error( array( 'message' => 'Saisissez au moins 3 caractères pour rechercher une adresse.' ), 400 );
    }

    $cache_key = 'baykat_farm_geocode_' . md5( strtolower( $query ) );
    $results   = get_transient( $cache_key );

    if ( false === $results ) {
        $last_request = (float) get_transient( 'baykat_farm_nominatim_last_request' );

        if ( $last_request && microtime( true ) - $last_request < 1 ) {
            wp_send_json_error( array( 'message' => 'Patientez une seconde avant de lancer une autre recherche.' ), 429 );
        }

        set_transient( 'baykat_farm_nominatim_last_request', microtime( true ), 3 );
        $endpoint = apply_filters(
            'baykat_farm_nominatim_endpoint',
            'https://nominatim.openstreetmap.org/search'
        );
        $url = add_query_arg(
            array(
                'format'         => 'jsonv2',
                'limit'          => 5,
                'addressdetails' => 1,
                'q'              => $query,
            ),
            $endpoint
        );
        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 8,
                'headers' => array(
                    'User-Agent' => 'Baykat Finance; ' . home_url( '/' ) . '; contact: ' . get_option( 'admin_email' ),
                    'Referer'    => home_url( '/' ),
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => 'Le service de recherche OpenStreetMap est momentanément indisponible. Réessayez dans un instant.' ), 502 );
        }

        if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            wp_send_json_error( array( 'message' => 'OpenStreetMap n’a pas pu traiter cette recherche pour le moment.' ), 502 );
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $decoded ) ) {
            wp_send_json_error( array( 'message' => 'La réponse du service de recherche est invalide.' ), 502 );
        }

        $results = array();

        foreach ( $decoded as $result ) {
            if (
                ! isset( $result['lat'], $result['lon'], $result['display_name'] )
                || ! is_numeric( $result['lat'] )
                || ! is_numeric( $result['lon'] )
            ) {
                continue;
            }

            $latitude  = (float) $result['lat'];
            $longitude = (float) $result['lon'];

            if ( $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180 ) {
                continue;
            }

            $results[] = array(
                'latitude'  => $latitude,
                'longitude' => $longitude,
                'label'     => sanitize_text_field( $result['display_name'] ),
            );
        }

        set_transient( $cache_key, $results, 30 * DAY_IN_SECONDS );
    }

    wp_send_json_success( $results );
}
add_action( 'wp_ajax_baykat_farm_geocode', 'baykat_farm_geocode_location' );
function baykat_farm_process_parcel_submission( $user_id ) {
    if ( ! isset( $_POST['baykat_add_parcel'] ) ) {
        return null;
    }

    check_admin_referer( 'baykat_add_parcel' );

    $farm_id = baykat_farm_request_id( 'farm_id' );
    $name = isset( $_POST['parcel_name'] ) && is_string( $_POST['parcel_name'] )
        ? sanitize_text_field( wp_unslash( $_POST['parcel_name'] ) )
        : '';
    $area_value = isset( $_POST['parcel_area_m2'] ) && is_scalar( $_POST['parcel_area_m2'] )
        ? trim( (string) wp_unslash( $_POST['parcel_area_m2'] ) )
        : '';
    $area = '' === $area_value ? null : ( is_numeric( $area_value ) ? (float) $area_value : 0 );
    $status = isset( $_POST['parcel_status'] ) && is_string( $_POST['parcel_status'] )
        ? sanitize_key( wp_unslash( $_POST['parcel_status'] ) )
        : '';
    $allowed_statuses = array( 'planned', 'growing', 'harvested', 'paused' );

    if (
        ! baykat_farm_user_owns_farm( $farm_id, $user_id )
        || '' === $name
        || strlen( $name ) > 190
        || ( null !== $area && ( $area <= 0 || $area > 9999999999.99 ) )
        || ! in_array( $status, $allowed_statuses, true )
    ) {
        return array( 'type' => 'error', 'message' => 'Veuillez vérifier la ferme, le nom, la superficie et le statut de la parcelle.' );
    }

    $image_id = baykat_farm_handle_image_upload( 'parcel_image', $user_id );

    if ( is_wp_error( $image_id ) ) {
        return array( 'type' => 'error', 'message' => $image_id->get_error_message() );
    }

    global $wpdb;
    $inserted = $wpdb->insert(
        baykat_farm_table_name( 'parcels' ),
        array(
            'user_id' => baykat_farm_get_owner_id_for_farm( $farm_id ),
            'farm_id' => $farm_id,
            'name'    => $name,
            'area_m2' => $area,
            'status'  => $status,
            'image_id' => $image_id,
        ),
        array( '%d', '%d', '%s', '%f', '%s', '%d' )
    );

    if ( false === $inserted ) {
        if ( $image_id ) {
            wp_delete_attachment( $image_id, true );
        }

        return array( 'type' => 'error', 'message' => 'La parcelle n’a pas pu être enregistrée.' );
    }

    return array( 'type' => 'success', 'message' => 'Parcelle enregistrée.' );
}

function baykat_farm_get_crop_harvests( $crop_id ) {
    global $wpdb;

    $harvests_table = $wpdb->prefix . 'baykat_harvests';
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, recorded_by, quantity, unit, harvest_date, notes, created_at
             FROM $harvests_table
             WHERE crop_id = %d
             ORDER BY harvest_date DESC, id DESC",
            $crop_id
        )
    );
}

function baykat_farm_process_crop_harvest( $user_id ) {
    if ( ! isset( $_POST['baykat_record_harvest'] ) ) {
        return null;
    }

    check_admin_referer( 'baykat_record_harvest' );
    $crop_id = baykat_farm_request_id( 'crop_id' );
    $quantity_value = isset( $_POST['harvest_quantity'] ) && is_scalar( $_POST['harvest_quantity'] )
        ? trim( (string) wp_unslash( $_POST['harvest_quantity'] ) )
        : '';
    $quantity = is_numeric( $quantity_value ) ? (float) $quantity_value : 0;
    $unit = isset( $_POST['harvest_unit'] ) && is_string( $_POST['harvest_unit'] )
        ? trim( sanitize_text_field( wp_unslash( $_POST['harvest_unit'] ) ) )
        : '';
    $harvest_date = isset( $_POST['harvest_date'] ) && is_string( $_POST['harvest_date'] )
        ? sanitize_text_field( wp_unslash( $_POST['harvest_date'] ) )
        : '';
    $notes = isset( $_POST['harvest_notes'] ) && is_string( $_POST['harvest_notes'] )
        ? sanitize_textarea_field( wp_unslash( $_POST['harvest_notes'] ) )
        : '';

    if ( ! $crop_id || $quantity < 0.001 || $quantity > 999999999999.999 || '' === $unit || strlen( $unit ) > 40 || ! baykat_farm_valid_date( $harvest_date ) || strlen( $notes ) > 2000 ) {
        return array( 'type' => 'error', 'message' => 'Veuillez saisir une quantité, une unité et une date de récolte valides.' );
    }

    global $wpdb;
    $crops_table = baykat_farm_table_name( 'crops' );
    $parcels_table = baykat_farm_table_name( 'parcels' );
    $harvests_table = $wpdb->prefix . 'baykat_harvests';
    $transactions_table = baykat_finance_get_table_name();

    if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
        return array( 'type' => 'error', 'message' => 'Impossible de démarrer l’enregistrement de la récolte. Veuillez réessayer.' );
    }

    $crop = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT crops.id, crops.user_id, crops.parcel_id, crops.harvested_quantity, crops.harvested_unit,
                    parcels.farm_id
             FROM $crops_table AS crops
             INNER JOIN $parcels_table AS parcels ON parcels.id = crops.parcel_id
             WHERE crops.id = %d
             FOR UPDATE",
            $crop_id
        )
    );
    if ( '' !== $wpdb->last_error ) {
        $wpdb->query( 'ROLLBACK' );
        return array( 'type' => 'error', 'message' => 'Impossible de charger cette culture. Veuillez réessayer.' );
    }
    if ( ! $crop ) {
        $wpdb->query( 'ROLLBACK' );
        return array( 'type' => 'error', 'message' => 'Cette culture n’existe pas.' );
    }
    if ( ! baykat_farm_user_can_edit_farm( (int) $crop->farm_id, $user_id ) ) {
        $wpdb->query( 'ROLLBACK' );
        return array( 'type' => 'error', 'message' => 'Votre rôle sur cette ferme ne permet pas d’enregistrer une récolte.' );
    }
    if ( ! empty( $crop->harvested_unit ) && strtolower( $crop->harvested_unit ) !== strtolower( $unit ) ) {
        $wpdb->query( 'ROLLBACK' );
        return array( 'type' => 'error', 'message' => 'Utilisez la même unité que pour les récoltes précédentes (' . $crop->harvested_unit . ').' );
    }
    if ( ! empty( $crop->harvested_unit ) ) {
        $unit = $crop->harvested_unit;
    }

    $current_quantity = (float) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM $harvests_table WHERE crop_id = %d",
            $crop_id
        )
    );
    if ( '' !== $wpdb->last_error ) {
        $wpdb->query( 'ROLLBACK' );
        return array( 'type' => 'error', 'message' => 'Impossible de vérifier les récoltes précédentes. Veuillez réessayer.' );
    }
    $sold_quantity = (float) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM $transactions_table
             WHERE crop_id = %d AND category = 'sale' AND type = 'income' AND cancelled_at IS NULL",
            $crop_id
        )
    );
    if ( '' !== $wpdb->last_error ) {
        $wpdb->query( 'ROLLBACK' );
        return array( 'type' => 'error', 'message' => 'Impossible de vérifier les récoltes et ventes précédentes. Veuillez réessayer.' );
    }

    $total_quantity = round( $current_quantity + $quantity, 3 );
    if ( $total_quantity > 999999999999.999 ) {
        $wpdb->query( 'ROLLBACK' );
        return array( 'type' => 'error', 'message' => 'Le cumul des récoltes dépasse la quantité maximale autorisée.' );
    }
    if ( $total_quantity + 0.0005 < $sold_quantity ) {
        $wpdb->query( 'ROLLBACK' );
        return array( 'type' => 'error', 'message' => 'La quantité récoltée cumulée ne peut pas être inférieure aux ventes déjà enregistrées.' );
    }

    $inserted = $wpdb->insert(
        $harvests_table,
        array(
            'crop_id'     => $crop_id,
            'user_id'     => (int) $crop->user_id,
            'recorded_by' => (int) $user_id,
            'quantity'    => $quantity,
            'unit'        => $unit,
            'harvest_date' => $harvest_date,
            'notes'       => $notes,
        ),
        array( '%d', '%d', '%f', '%s', '%s', '%s' )
    );
    if ( false === $inserted ) {
        $wpdb->query( 'ROLLBACK' );
        return array( 'type' => 'error', 'message' => 'La récolte n’a pas pu être ajoutée au journal. Veuillez réessayer.' );
    }

    $updated = $wpdb->update(
        $crops_table,
        array(
            'harvested_quantity' => $total_quantity,
            'harvested_unit'     => $unit,
            'status'             => 'harvested',
        ),
        array( 'id' => $crop_id ),
        array( '%f', '%s', '%s' ),
        array( '%d' )
    );
    if ( false === $updated || false === $wpdb->query( 'COMMIT' ) ) {
        $wpdb->query( 'ROLLBACK' );
        return array( 'type' => 'error', 'message' => 'La récolte a été saisie, mais la culture n’a pas pu être mise à jour. Veuillez réessayer.' );
    }

    return array( 'type' => 'success', 'message' => 'Récolte ajoutée : ' . number_format( $quantity, 3, ',', ' ' ) . ' ' . $unit . '. Total récolté : ' . number_format( $total_quantity, 3, ',', ' ' ) . ' ' . $unit . '.' );
}

function baykat_farm_process_crop_submission( $user_id ) {
    if ( ! isset( $_POST['baykat_add_crop'] ) ) {
        return null;
    }

    check_admin_referer( 'baykat_add_crop' );

    $parcel_id = baykat_farm_request_id( 'parcel_id' );
    $parcel = baykat_farm_get_user_parcel( $parcel_id, $user_id );
    $name = isset( $_POST['crop_name'] ) && is_string( $_POST['crop_name'] )
        ? sanitize_text_field( wp_unslash( $_POST['crop_name'] ) )
        : '';
    $variety = isset( $_POST['crop_variety'] ) && is_string( $_POST['crop_variety'] )
        ? sanitize_text_field( wp_unslash( $_POST['crop_variety'] ) )
        : '';
    $planted_at = isset( $_POST['planted_at'] ) && is_string( $_POST['planted_at'] )
        ? sanitize_text_field( wp_unslash( $_POST['planted_at'] ) )
        : '';
    $expected_date = isset( $_POST['expected_harvest_date'] ) && is_string( $_POST['expected_harvest_date'] )
        ? sanitize_text_field( wp_unslash( $_POST['expected_harvest_date'] ) )
        : '';
    $status = isset( $_POST['crop_status'] ) && is_string( $_POST['crop_status'] )
        ? sanitize_key( wp_unslash( $_POST['crop_status'] ) )
        : '';
    $allowed_statuses = array( 'planned', 'growing', 'paused' );

    if (
        ! $parcel
        || ! baykat_farm_user_can_edit_farm( (int) $parcel->farm_id, $user_id )
        || '' === $name
        || strlen( $name ) > 190
        || ( '' !== $variety && strlen( $variety ) > 190 )
        || ( '' !== $planted_at && ! baykat_farm_valid_date( $planted_at ) )
        || ( '' !== $expected_date && ! baykat_farm_valid_date( $expected_date ) )
        || ! in_array( $status, $allowed_statuses, true )
    ) {
        return array( 'type' => 'error', 'message' => 'Veuillez vérifier la parcelle, la culture, les dates et le statut.' );
    }

    $image_id = baykat_farm_handle_image_upload( 'crop_image', $user_id );

    if ( is_wp_error( $image_id ) ) {
        return array( 'type' => 'error', 'message' => $image_id->get_error_message() );
    }

    global $wpdb;
    $inserted = $wpdb->insert(
        baykat_farm_table_name( 'crops' ),
        array(
            'user_id'               => (int) $parcel->user_id,
            'parcel_id'             => $parcel_id,
            'name'                  => $name,
            'variety'               => $variety,
            'planted_at'            => '' === $planted_at ? null : $planted_at,
            'expected_harvest_date' => '' === $expected_date ? null : $expected_date,
            'harvested_quantity'    => null,
            'harvested_unit'        => null,
            'status'                => $status,
            'image_id'              => $image_id,
        ),
        array( '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d' )
    );

    if ( false === $inserted ) {
        if ( $image_id ) {
            wp_delete_attachment( $image_id, true );
        }

        return array( 'type' => 'error', 'message' => 'La culture n’a pas pu être enregistrée.' );
    }

    return array( 'type' => 'success', 'message' => 'Culture enregistrée.' );
}

function baykat_farm_process_activity_submission( $user_id ) {
    if ( ! isset( $_POST['baykat_add_activity'] ) ) {
        return null;
    }

    check_admin_referer( 'baykat_add_activity' );

    $parcel_id = baykat_farm_request_id( 'parcel_id' );
    $parcel = baykat_farm_get_user_parcel( $parcel_id, $user_id );
    $title = isset( $_POST['activity_title'] ) && is_string( $_POST['activity_title'] )
        ? sanitize_text_field( wp_unslash( $_POST['activity_title'] ) )
        : '';
    $activity_type = isset( $_POST['activity_type'] ) && is_string( $_POST['activity_type'] )
        ? sanitize_key( wp_unslash( $_POST['activity_type'] ) )
        : '';
    $activity_date = isset( $_POST['activity_date'] ) && is_string( $_POST['activity_date'] )
        ? sanitize_text_field( wp_unslash( $_POST['activity_date'] ) )
        : '';
    $notes = isset( $_POST['activity_notes'] ) && is_string( $_POST['activity_notes'] )
        ? sanitize_textarea_field( wp_unslash( $_POST['activity_notes'] ) )
        : '';
    $finance_amount_value = isset( $_POST['activity_finance_amount'] ) && is_scalar( $_POST['activity_finance_amount'] )
        ? trim( (string) wp_unslash( $_POST['activity_finance_amount'] ) )
        : '';
    $finance_amount = '' === $finance_amount_value
        ? null
        : ( is_numeric( $finance_amount_value ) ? (float) $finance_amount_value : 0 );
    $finance_type = isset( $_POST['activity_finance_type'] ) && is_string( $_POST['activity_finance_type'] )
        ? sanitize_key( wp_unslash( $_POST['activity_finance_type'] ) )
        : 'expense';
    $finance_category = isset( $_POST['activity_finance_category'] ) && is_string( $_POST['activity_finance_category'] )
        ? sanitize_key( wp_unslash( $_POST['activity_finance_category'] ) )
        : 'labor';
    $finance_note = isset( $_POST['activity_finance_note'] ) && is_string( $_POST['activity_finance_note'] )
        ? sanitize_text_field( wp_unslash( $_POST['activity_finance_note'] ) )
        : '';
    $allowed_types = array( 'sowing', 'watering', 'fertilizing', 'treatment', 'harvest', 'other' );
    $allowed_finance_types = array( 'income', 'expense' );
    $allowed_expense_categories = array_keys( baykat_finance_expense_categories() );

    if (
        ! $parcel
        || ! baykat_farm_user_can_edit_farm( (int) $parcel->farm_id, $user_id )
        || '' === $title
        || strlen( $title ) > 190
        || ! in_array( $activity_type, $allowed_types, true )
        || ! baykat_farm_valid_date( $activity_date )
        || ( isset( $_POST['activity_finance_amount'] ) && ! is_scalar( $_POST['activity_finance_amount'] ) )
        || ( isset( $_POST['activity_finance_type'] ) && ! is_scalar( $_POST['activity_finance_type'] ) )
        || ( isset( $_POST['activity_finance_category'] ) && ! is_scalar( $_POST['activity_finance_category'] ) )
        || ( isset( $_POST['activity_finance_note'] ) && ! is_scalar( $_POST['activity_finance_note'] ) )
        || ( null !== $finance_amount && ( $finance_amount < 0.01 || $finance_amount > 9999999999999.99 ) )
        || ( null !== $finance_amount && ! in_array( $finance_type, $allowed_finance_types, true ) )
        || ( null !== $finance_amount && 'expense' === $finance_type && ! in_array( $finance_category, $allowed_expense_categories, true ) )
        || strlen( $finance_note ) > 190
    ) {
        return array( 'type' => 'error', 'message' => 'Vérifiez les informations de l’activité et, si renseigné, le montant et la nature de son opération financière.' );
    }

    $image_id = baykat_farm_handle_image_upload( 'activity_image', $user_id );

    if ( is_wp_error( $image_id ) ) {
        return array( 'type' => 'error', 'message' => $image_id->get_error_message() );
    }

    global $wpdb;
    $inserted = $wpdb->insert(
        baykat_farm_table_name( 'activities' ),
        array(
            'user_id'       => (int) $parcel->user_id,
            'parcel_id'     => $parcel_id,
            'title'         => $title,
            'activity_type' => $activity_type,
            'activity_date' => $activity_date,
            'notes'         => $notes,
            'image_id'      => $image_id,
        ),
        array( '%d', '%d', '%s', '%s', '%s', '%s', '%d' )
    );

    if ( false === $inserted ) {
        if ( $image_id ) {
            wp_delete_attachment( $image_id, true );
        }

        return array( 'type' => 'error', 'message' => 'L’activité n’a pas pu être enregistrée.' );
    }

    $activity_id = (int) $wpdb->insert_id;

    if ( null !== $finance_amount ) {
        $finance_inserted = $wpdb->insert(
            baykat_finance_get_table_name(),
            array(
                'user_id'          => (int) $parcel->user_id,
                'type'             => $finance_type,
                'category'         => 'general',
                'amount'           => $finance_amount,
                'description'      => $finance_note,
                'item_name'        => '' !== $finance_note ? $finance_note : $title,
                'expense_category' => 'expense' === $finance_type ? $finance_category : 'general',
                'farm_id'          => (int) $parcel->farm_id,
                'parcel_id'        => $parcel_id,
                'activity_id'      => $activity_id,
            ),
            array( '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%d', '%d' )
        );

        if ( false === $finance_inserted ) {
            $activity_deleted = $wpdb->delete(
                baykat_farm_table_name( 'activities' ),
                array( 'id' => $activity_id, 'user_id' => (int) $parcel->user_id ),
                array( '%d', '%d' )
            );
            if ( $image_id ) {
                wp_delete_attachment( $image_id, true );
            }

            return array(
                'type'    => 'error',
                'message' => false === $activity_deleted || 0 === $activity_deleted
                    ? 'La finance n’a pas pu être enregistrée et l’activité n’a pas pu être annulée. Vérifiez votre journal avant de réessayer.'
                    : 'L’opération financière n’a pas pu être enregistrée; l’activité a été annulée. Réessayez.',
            );
        }
    }

    return array(
        'type'    => 'success',
        'message' => null !== $finance_amount
            ? 'Activité et opération financière enregistrées.'
            : 'Activité enregistrée.',
    );
}
