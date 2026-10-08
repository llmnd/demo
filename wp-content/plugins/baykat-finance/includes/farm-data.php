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

function baykat_farm_get_user_farms( $user_id ) {
    global $wpdb;

    $table = baykat_farm_table_name( 'farms' );

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY name ASC",
            $user_id
        )
    );
}

function baykat_farm_get_user_parcels( $user_id ) {
    global $wpdb;

    $parcels_table = baykat_farm_table_name( 'parcels' );
    $farms_table = baykat_farm_table_name( 'farms' );

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT parcels.*, farms.name AS farm_name
             FROM $parcels_table AS parcels
             INNER JOIN $farms_table AS farms
                ON farms.id = parcels.farm_id AND farms.user_id = parcels.user_id
             WHERE parcels.user_id = %d
             ORDER BY farms.name ASC, parcels.name ASC",
            $user_id
        )
    );
}

function baykat_farm_get_user_crops( $user_id ) {
    global $wpdb;

    $crops_table = baykat_farm_table_name( 'crops' );
    $parcels_table = baykat_farm_table_name( 'parcels' );
    $farms_table = baykat_farm_table_name( 'farms' );

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT crops.*, parcels.name AS parcel_name, farms.name AS farm_name
             FROM $crops_table AS crops
             INNER JOIN $parcels_table AS parcels
                ON parcels.id = crops.parcel_id AND parcels.user_id = crops.user_id
             INNER JOIN $farms_table AS farms
                ON farms.id = parcels.farm_id AND farms.user_id = crops.user_id
             WHERE crops.user_id = %d
             ORDER BY crops.created_at DESC",
            $user_id
        )
    );
}

function baykat_farm_get_user_activities( $user_id ) {
    global $wpdb;

    $activities_table = baykat_farm_table_name( 'activities' );
    $parcels_table = baykat_farm_table_name( 'parcels' );
    $farms_table = baykat_farm_table_name( 'farms' );

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT activities.*, parcels.name AS parcel_name, farms.name AS farm_name
             FROM $activities_table AS activities
             INNER JOIN $parcels_table AS parcels
                ON parcels.id = activities.parcel_id AND parcels.user_id = activities.user_id
             INNER JOIN $farms_table AS farms
                ON farms.id = parcels.farm_id AND farms.user_id = activities.user_id
             WHERE activities.user_id = %d
             ORDER BY activities.activity_date DESC, activities.created_at DESC",
            $user_id
        )
    );
}

function baykat_farm_user_owns_farm( $farm_id, $user_id ) {
    global $wpdb;

    $table = baykat_farm_table_name( 'farms' );

    return (bool) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM $table WHERE id = %d AND user_id = %d",
            $farm_id,
            $user_id
        )
    );
}

function baykat_farm_get_user_parcel( $parcel_id, $user_id ) {
    global $wpdb;

    $table = baykat_farm_table_name( 'parcels' );

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d",
            $parcel_id,
            $user_id
        )
    );
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
    $record = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM $table WHERE id = %d AND user_id = %d",
            $record_id,
            $user_id
        )
    );

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
        array(
            'id'      => $record_id,
            'user_id' => $user_id,
        ),
        array( '%d' ),
        array( '%d', '%d' )
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
    $record = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d",
            $record_id,
            $user_id
        )
    );

    if ( ! $record ) {
        return array( 'type' => 'error', 'message' => 'Cet enregistrement n’existe pas ou ne vous appartient pas.' );
    }

    if ( $is_delete ) {
        $dependency_count = 0;

        if ( 'farm' === $entity ) {
            $parcels_table = baykat_farm_table_name( 'parcels' );
            $transactions_table = baykat_finance_get_table_name();
            $dependency_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT
                        (SELECT COUNT(*) FROM $parcels_table WHERE farm_id = %d AND user_id = %d)
                        + (SELECT COUNT(*) FROM $transactions_table WHERE farm_id = %d AND user_id = %d)",
                    $record_id,
                    $user_id,
                    $record_id,
                    $user_id
                )
            );
        } elseif ( 'parcel' === $entity ) {
            $crops_table = baykat_farm_table_name( 'crops' );
            $activities_table = baykat_farm_table_name( 'activities' );
            $transactions_table = baykat_finance_get_table_name();
            $dependency_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT
                        (SELECT COUNT(*) FROM $crops_table WHERE parcel_id = %d AND user_id = %d)
                        + (SELECT COUNT(*) FROM $activities_table WHERE parcel_id = %d AND user_id = %d)
                        + (SELECT COUNT(*) FROM $transactions_table WHERE parcel_id = %d AND user_id = %d)",
                    $record_id,
                    $user_id,
                    $record_id,
                    $user_id,
                    $record_id,
                    $user_id
                )
            );
        }

        if ( $dependency_count > 0 ) {
            $message = 'Suppression impossible : des données sont encore liées. Supprimez ou réaffectez-les avant de réessayer.';

            return array( 'type' => 'error', 'message' => $message );
        }

        $deleted = $wpdb->delete(
            $table,
            array(
                'id'      => $record_id,
                'user_id' => $user_id,
            ),
            array( '%d', '%d' )
        );

        if ( false === $deleted ) {
            return array( 'type' => 'error', 'message' => 'La suppression a échoué. Veuillez réessayer.' );
        }

        return array( 'type' => 'success', 'message' => 'Enregistrement supprimé.' );
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

        $data = array( 'name' => $name, 'location' => $location, 'area_ha' => $area );
        $formats = array( '%s', '%s', '%f' );
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

        if ( ! baykat_farm_user_owns_farm( $farm_id, $user_id ) || '' === $name || strlen( $name ) > 190 || ( null !== $area && ( $area <= 0 || $area > 9999999999.99 ) ) || ! in_array( $status, array( 'planned', 'growing', 'harvested', 'paused' ), true ) ) {
            return array( 'type' => 'error', 'message' => 'Veuillez vérifier la ferme, le nom, la superficie et le statut de la parcelle.' );
        }

        if ( (int) $record->farm_id !== $farm_id ) {
            $transactions_table = baykat_finance_get_table_name();
            $transaction_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $transactions_table WHERE parcel_id = %d AND user_id = %d",
                    $record_id,
                    $user_id
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

        if ( ! $parcel || '' === $name || strlen( $name ) > 190 || strlen( $variety ) > 190 || ( '' !== $planted_at && ! baykat_farm_valid_date( $planted_at ) ) || ( '' !== $expected_date && ! baykat_farm_valid_date( $expected_date ) ) || ! in_array( $status, array( 'planned', 'growing', 'harvested', 'paused' ), true ) ) {
            return array( 'type' => 'error', 'message' => 'Veuillez vérifier la parcelle, la culture, les dates et le statut.' );
        }

        $data = array(
            'parcel_id'             => $parcel_id,
            'name'                  => $name,
            'variety'               => $variety,
            'planted_at'            => '' === $planted_at ? null : $planted_at,
            'expected_harvest_date' => '' === $expected_date ? null : $expected_date,
            'status'                => $status,
        );
        $formats = array( '%d', '%s', '%s', '%s', '%s', '%s' );
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

        if ( ! $parcel || '' === $title || strlen( $title ) > 190 || ! in_array( $activity_type, array( 'sowing', 'watering', 'fertilizing', 'treatment', 'harvest', 'other' ), true ) || ! baykat_farm_valid_date( $activity_date ) ) {
            return array( 'type' => 'error', 'message' => 'Veuillez vérifier la parcelle, le titre, le type et la date de l’activité.' );
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
        array(
            'id'      => $record_id,
            'user_id' => $user_id,
        ),
        $formats,
        array( '%d', '%d' )
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

    if (
        '' === $name
        || strlen( $name ) > 190
        || ( null !== $area && ( $area <= 0 || $area > 999999999.999 ) )
    ) {
        return array( 'type' => 'error', 'message' => 'Veuillez saisir un nom de ferme et une superficie valides.' );
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
            'area_ha'  => $area,
            'image_id' => $image_id,
        ),
        array( '%d', '%s', '%s', '%f', '%d' )
    );

    if ( false === $inserted ) {
        if ( $image_id ) {
            wp_delete_attachment( $image_id, true );
        }

        return array( 'type' => 'error', 'message' => 'La ferme n’a pas pu être enregistrée.' );
    }

    return array( 'type' => 'success', 'message' => 'Ferme enregistrée.' );
}

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
            'user_id' => $user_id,
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
    $allowed_statuses = array( 'planned', 'growing', 'harvested', 'paused' );

    if (
        ! $parcel
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
            'user_id'               => $user_id,
            'parcel_id'             => $parcel_id,
            'name'                  => $name,
            'variety'               => $variety,
            'planted_at'            => '' === $planted_at ? null : $planted_at,
            'expected_harvest_date' => '' === $expected_date ? null : $expected_date,
            'status'                => $status,
            'image_id'              => $image_id,
        ),
        array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d' )
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
    $allowed_types = array( 'sowing', 'watering', 'fertilizing', 'treatment', 'harvest', 'other' );

    if (
        ! $parcel
        || '' === $title
        || strlen( $title ) > 190
        || ! in_array( $activity_type, $allowed_types, true )
        || ! baykat_farm_valid_date( $activity_date )
    ) {
        return array( 'type' => 'error', 'message' => 'Veuillez vérifier la parcelle, le titre, le type et la date de l’activité.' );
    }

    $image_id = baykat_farm_handle_image_upload( 'activity_image', $user_id );

    if ( is_wp_error( $image_id ) ) {
        return array( 'type' => 'error', 'message' => $image_id->get_error_message() );
    }

    global $wpdb;
    $inserted = $wpdb->insert(
        baykat_farm_table_name( 'activities' ),
        array(
            'user_id'       => $user_id,
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

    return array( 'type' => 'success', 'message' => 'Activité enregistrée.' );
}

function baykat_farm_get_stock_totals( $user_id ) {
    global $wpdb;

    $table = baykat_finance_get_table_name();

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT transactions.farm_id, COALESCE(farms.name, 'Finances générales') AS farm_name,
                    transactions.item_name, transactions.unit,
                    SUM(transactions.quantity) AS total_quantity,
                    SUM(transactions.amount) AS total_cost
             FROM $table AS transactions
             LEFT JOIN " . baykat_farm_table_name( 'farms' ) . " AS farms
                ON farms.id = transactions.farm_id AND farms.user_id = transactions.user_id
             WHERE transactions.user_id = %d AND transactions.category = %s
             GROUP BY transactions.farm_id, farms.name, transactions.item_name, transactions.unit
             ORDER BY farm_name ASC, transactions.item_name ASC, transactions.unit ASC",
            $user_id,
            'input'
        )
    );
}
