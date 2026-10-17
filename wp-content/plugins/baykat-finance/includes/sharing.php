<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_farm_members_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'baykat_farm_members';
}

function baykat_farm_get_access_role( $farm_id, $user_id ) {
    global $wpdb;

    $farms_table = baykat_farm_table_name( 'farms' );
    if ( ! $farm_id || ! $user_id ) {
        return '';
    }

    $farm_owner_id = $wpdb->get_var(
        $wpdb->prepare( "SELECT user_id FROM $farms_table WHERE id = %d", $farm_id )
    );
    if ( (int) $farm_owner_id === (int) $user_id ) {
        return 'owner';
    }

    $members_table = baykat_farm_members_table_name();
    return (string) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT role FROM $members_table WHERE farm_id = %d AND member_user_id = %d",
            $farm_id,
            $user_id
        )
    );
}

function baykat_farm_user_can_view_farm( $farm_id, $user_id ) {
    return in_array( baykat_farm_get_access_role( $farm_id, $user_id ), array( 'owner', 'manager', 'reader' ), true );
}

function baykat_farm_user_can_edit_farm( $farm_id, $user_id ) {
    return in_array( baykat_farm_get_access_role( $farm_id, $user_id ), array( 'owner', 'manager' ), true );
}

function baykat_farm_filter_editable_records( $records ) {
    return array_values(
        array_filter(
            (array) $records,
            function ( $record ) {
                return isset( $record->access_role ) && in_array( $record->access_role, array( 'owner', 'manager' ), true );
            }
        )
    );
}

function baykat_farm_get_owner_id_for_farm( $farm_id ) {
    global $wpdb;

    $farms_table = baykat_farm_table_name( 'farms' );
    return (int) $wpdb->get_var(
        $wpdb->prepare( "SELECT user_id FROM $farms_table WHERE id = %d", $farm_id )
    );
}

function baykat_farm_process_sharing_submission( $user_id ) {
    global $wpdb;

    $members_table = baykat_farm_members_table_name();
    $farms_table = baykat_farm_table_name( 'farms' );

    if ( isset( $_POST['baykat_share_farm'] ) ) {
        check_admin_referer( 'baykat_share_farm' );
        $farm_id = baykat_farm_request_id( 'farm_id' );
        $email = isset( $_POST['member_email'] ) && is_string( $_POST['member_email'] )
            ? sanitize_email( wp_unslash( $_POST['member_email'] ) )
            : '';
        $role = isset( $_POST['member_role'] ) && is_string( $_POST['member_role'] )
            ? sanitize_key( wp_unslash( $_POST['member_role'] ) )
            : '';
        $owner_id = $farm_id
            ? (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT user_id FROM $farms_table WHERE id = %d AND user_id = %d",
                    $farm_id,
                    $user_id
                )
            )
            : 0;
        $member = is_email( $email ) ? get_user_by( 'email', $email ) : false;

        if ( ! $owner_id || ! $member || (int) $member->ID === (int) $user_id || ! in_array( $role, array( 'manager', 'reader' ), true ) ) {
            return array( 'type' => 'error', 'message' => 'Choisissez une ferme qui vous appartient, un compte WordPress existant et un rôle valide.' );
        }

        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $members_table WHERE farm_id = %d AND member_user_id = %d",
                $farm_id,
                $member->ID
            )
        );
        if ( $existing_id ) {
            $saved = $wpdb->update(
                $members_table,
                array( 'role' => $role ),
                array( 'id' => $existing_id ),
                array( '%s' ),
                array( '%d' )
            );
        } else {
            $saved = $wpdb->insert(
                $members_table,
                array(
                    'farm_id'       => $farm_id,
                    'owner_user_id' => $user_id,
                    'member_user_id' => (int) $member->ID,
                    'role'          => $role,
                ),
                array( '%d', '%d', '%d', '%s' )
            );
        }

        return false === $saved
            ? array( 'type' => 'error', 'message' => 'Le partage n’a pas pu être enregistré.' )
            : array( 'type' => 'success', 'message' => 'Accès à la ferme enregistré.' );
    }

    if ( isset( $_POST['baykat_remove_farm_member'] ) ) {
        check_admin_referer( 'baykat_remove_farm_member' );
        $farm_id = baykat_farm_request_id( 'farm_id' );
        $member_id = baykat_farm_request_id( 'member_user_id' );
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE members FROM $members_table AS members
                 INNER JOIN $farms_table AS farms ON farms.id = members.farm_id
                 WHERE members.farm_id = %d AND members.member_user_id = %d AND farms.user_id = %d",
                $farm_id,
                $member_id,
                $user_id
            )
        );

        return false === $deleted
            ? array( 'type' => 'error', 'message' => 'L’accès n’a pas pu être retiré.' )
            : array( 'type' => 'success', 'message' => 'Accès retiré.' );
    }

    return null;
}

function baykat_farm_get_members( $farm_id, $owner_user_id ) {
    global $wpdb;

    $members_table = baykat_farm_members_table_name();
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT members.member_user_id, members.role, users.display_name, users.user_email
             FROM $members_table AS members
             INNER JOIN {$wpdb->users} AS users ON users.ID = members.member_user_id
             WHERE members.farm_id = %d AND members.owner_user_id = %d
             ORDER BY users.display_name ASC",
            $farm_id,
            $owner_user_id
        )
    );
}
