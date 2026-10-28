<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_farm_report_farm_ids( $user_id ) {
    $farms = baykat_farm_get_user_farms( $user_id );
    return array_map( 'intval', wp_list_pluck( $farms, 'id' ) );
}

function baykat_farm_report_csv_value( $value ) {
    $value = (string) $value;
    if ( is_numeric( $value ) ) {
        return $value;
    }
    if ( preg_match( '/^[\s]*[=+\-@]/', $value ) ) {
        return "'" . $value;
    }
    return $value;
}

function baykat_farm_export_report_csv() {
    if ( ! is_user_logged_in() || ! baykat_finance_user_can_access() ) {
        wp_die( esc_html__( 'Accès refusé.', 'baykat-finance' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'baykat_export_farm_csv' );

    global $wpdb;
    $user_id = get_current_user_id();
    $farm_ids = baykat_farm_report_farm_ids( $user_id );
    if ( ! $farm_ids ) {
        wp_die( esc_html__( 'Aucune ferme accessible à exporter.', 'baykat-finance' ), '', array( 'response' => 404 ) );
    }

    $farm_scope = implode( ', ', $farm_ids );
    $transactions_table = baykat_finance_get_table_name();
    $farms_table = baykat_farm_table_name( 'farms' );
    $parcels_table = baykat_farm_table_name( 'parcels' );
    $crops_table = baykat_farm_table_name( 'crops' );
    $today = current_time( 'Y-m-d' );
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="baykat-rapport-agricole-' . $today . '.csv"' );
    $output = fopen( 'php://output', 'w' );
    if ( false === $output ) {
        wp_die( esc_html__( 'Impossible de générer le fichier CSV.', 'baykat-finance' ), '', array( 'response' => 500 ) );
    }
    fwrite( $output, "\xEF\xBB\xBF" );
    fputcsv( $output, array( 'Enregistrement', 'Date d’opération', 'Date de saisie', 'État', 'Ferme', 'Parcelle', 'Culture', 'Détail', 'Quantité', 'Unité', 'Revenus FCFA', 'Dépenses FCFA', 'Marge FCFA' ), ';' );

    $last_id = 0;
    do {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT transactions.id, transactions.operation_date, transactions.created_at, transactions.type, transactions.category,
                        transactions.description, transactions.item_name, transactions.quantity, transactions.unit,
                        transactions.amount, transactions.cancelled_at, farms.name AS farm_name, parcels.name AS parcel_name, crops.name AS crop_name
                 FROM $transactions_table AS transactions
                 LEFT JOIN $farms_table AS farms ON farms.id = transactions.farm_id
                 LEFT JOIN $parcels_table AS parcels ON parcels.id = transactions.parcel_id
                 LEFT JOIN $crops_table AS crops ON crops.id = transactions.crop_id
                 WHERE transactions.farm_id IN ($farm_scope) AND transactions.id > %d
                 ORDER BY transactions.id ASC
                 LIMIT 500",
                $last_id
            )
        );
        foreach ( (array) $rows as $row ) {
            $is_cancelled = ! empty( $row->cancelled_at );
            $income = ! $is_cancelled && 'income' === $row->type ? (float) $row->amount : 0;
            $expense = ! $is_cancelled && 'expense' === $row->type ? (float) $row->amount : 0;
            fputcsv(
                $output,
                array_map(
                    'baykat_farm_report_csv_value',
                    array(
                        'Transaction ' . $row->category,
                        $row->operation_date,
                        $row->created_at,
                        $is_cancelled ? 'Annulée le ' . $row->cancelled_at : 'Active',
                        $row->farm_name,
                        $row->parcel_name,
                        $row->crop_name,
                        $row->description ? $row->description : $row->item_name,
                        $row->quantity,
                        $row->unit,
                        number_format( $income, 2, '.', '' ),
                        number_format( $expense, 2, '.', '' ),
                        number_format( $income - $expense, 2, '.', '' ),
                    )
                ),
                ';'
            );
            $last_id = (int) $row->id;
        }
    } while ( count( (array) $rows ) === 500 );

    $crops = $wpdb->get_results(
        "SELECT crops.id, crops.name, crops.harvested_quantity, crops.harvested_unit,
                farms.name AS farm_name, parcels.name AS parcel_name,
                COALESCE(SUM(CASE WHEN transactions.type = 'income' AND transactions.cancelled_at IS NULL THEN transactions.amount ELSE 0 END), 0) AS income,
                COALESCE(SUM(CASE WHEN transactions.type = 'expense' AND transactions.cancelled_at IS NULL THEN transactions.amount ELSE 0 END), 0) AS expense
         FROM $crops_table AS crops
         INNER JOIN $parcels_table AS parcels ON parcels.id = crops.parcel_id
         INNER JOIN $farms_table AS farms ON farms.id = parcels.farm_id
         LEFT JOIN $transactions_table AS transactions ON transactions.crop_id = crops.id
         WHERE farms.id IN ($farm_scope)
         GROUP BY crops.id, crops.name, crops.harvested_quantity, crops.harvested_unit, farms.name, parcels.name
         ORDER BY farms.name ASC, parcels.name ASC, crops.name ASC"
    );
    foreach ( (array) $crops as $crop ) {
        fputcsv(
            $output,
            array_map(
                'baykat_farm_report_csv_value',
                array(
                    'Rentabilité culture',
                    '',
                    $crop->farm_name,
                    $crop->parcel_name,
                    $crop->name,
                    '',
                    $crop->harvested_quantity,
                    $crop->harvested_unit,
                    number_format( (float) $crop->income, 2, '.', '' ),
                    number_format( (float) $crop->expense, 2, '.', '' ),
                    number_format( (float) $crop->income - (float) $crop->expense, 2, '.', '' ),
                )
            ),
            ';'
        );
    }
    fclose( $output );
    exit;
}
add_action( 'admin_post_baykat_export_farm_csv', 'baykat_farm_export_report_csv' );

function baykat_farm_report_pdf_escape_text( $text ) {
    $encoded = iconv( 'UTF-8', 'Windows-1252//TRANSLIT', (string) $text );
    if ( false === $encoded ) {
        return new WP_Error( 'report_pdf_encoding_failed', 'Impossible d’encoder le texte du bilan PDF.' );
    }

    return str_replace(
        array( '\\', '(', ')' ),
        array( '\\\\', '\\(', '\\)' ),
        $encoded
    );
}

function baykat_farm_report_build_pdf( $title, $subtitle, $sections ) {
    $lines = array();
    foreach ( $sections as $section ) {
        $lines[] = array( 'text' => $section['heading'], 'heading' => true );
        foreach ( $section['lines'] as $line ) {
            $encoded_line = baykat_farm_report_pdf_escape_text( $line );
            if ( is_wp_error( $encoded_line ) ) {
                return $encoded_line;
            }
            $wrapped_lines = '' === $encoded_line
                ? array( '' )
                : explode( "\n", wordwrap( $encoded_line, 92, "\n", true ) );
            foreach ( $wrapped_lines as $wrapped_line ) {
                    $lines[] = array( 'text' => $wrapped_line, 'heading' => false );
            }
        }
        $lines[] = array( 'text' => '', 'heading' => false );
    }

    $pages = array_chunk( $lines, 48 );
    if ( ! $pages ) {
        $pages = array( array() );
    }
    $objects = array(
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
    );
    $page_ids = array();
    $next_id = 5;

    foreach ( $pages as $page_index => $page_lines ) {
        $page_id = $next_id++;
        $stream_id = $next_id++;
        $page_ids[] = $page_id;
        $stream = "BT\n/F2 18 Tf\n48 800 Td\n";
        $escaped_title = baykat_farm_report_pdf_escape_text( $title );
        $escaped_subtitle = baykat_farm_report_pdf_escape_text( $subtitle );
        if ( is_wp_error( $escaped_title ) || is_wp_error( $escaped_subtitle ) ) {
            return new WP_Error( 'report_pdf_encoding_failed', 'Impossible d’encoder le titre du bilan PDF.' );
        }
        $subtitle_lines = explode( "\n", wordwrap( $escaped_subtitle, 92, "\n", true ) );
        $stream .= '(' . $escaped_title . ") Tj\nET\nBT\n/F1 9 Tf\n48 782 Td\n12 TL\n";
        foreach ( $subtitle_lines as $subtitle_line ) {
            $stream .= '(' . $subtitle_line . ") Tj\nT*\n";
        }
        $stream .= "ET\n";
        $y = 758;

        foreach ( $page_lines as $line ) {
            if ( $line['heading'] ) {
                $escaped_heading = baykat_farm_report_pdf_escape_text( $line['text'] );
                if ( is_wp_error( $escaped_heading ) ) {
                    return $escaped_heading;
                }
                $stream .= "BT\n/F2 11 Tf\n48 $y Td\n(" . $escaped_heading . ") Tj\nET\n";
                $y -= 17;
                continue;
            }
            if ( '' !== $line['text'] ) {
                $stream .= "BT\n/F1 9 Tf\n48 $y Td\n(" . $line['text'] . ") Tj\nET\n";
            }
            $y -= '' === $line['text'] ? 2 : 13;
        }

        $page_number = $page_index + 1;
        $page_count = count( $pages );
        $stream .= "BT\n/F1 8 Tf\n48 30 Td\n(Page $page_number / $page_count) Tj\nET\n";
        $objects[ $page_id ] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $stream_id . ' 0 R >>';
        $objects[ $stream_id ] = '<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . 'endstream';
    }

    $kids = implode( ' ', array_map( function ( $page_id ) {
        return $page_id . ' 0 R';
    }, $page_ids ) );
    $objects[2] = '<< /Type /Pages /Kids [ ' . $kids . ' ] /Count ' . count( $page_ids ) . ' >>';
    ksort( $objects );

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = array( 0 );
    foreach ( $objects as $object_id => $object_body ) {
        $offsets[ $object_id ] = strlen( $pdf );
        $pdf .= $object_id . " 0 obj\n" . $object_body . "\nendobj\n";
    }
    $xref_offset = strlen( $pdf );
    $object_count = max( array_keys( $objects ) ) + 1;
    $pdf .= "xref\n0 $object_count\n0000000000 65535 f \n";
    for ( $object_id = 1; $object_id < $object_count; $object_id++ ) {
        $pdf .= sprintf( "%010d 00000 n \n", isset( $offsets[ $object_id ] ) ? $offsets[ $object_id ] : 0 );
    }
    $pdf .= "trailer\n<< /Size $object_count /Root 1 0 R >>\nstartxref\n$xref_offset\n%%EOF";

    return $pdf;
}

function baykat_farm_report_commercialization_status_label( $status ) {
    $labels = array(
        'planned' => 'Planifiée',
        'in_progress' => 'En cours',
        'completed' => 'Terminée',
        'paused' => 'En pause',
    );

    return isset( $labels[ $status ] ) ? $labels[ $status ] : 'Inconnu';
}

function baykat_farm_report_download_pdf( $filename, $title, $subtitle, $sections ) {
    $pdf = baykat_farm_report_build_pdf( $title, $subtitle, $sections );
    if ( is_wp_error( $pdf ) ) {
        wp_die( esc_html( $pdf->get_error_message() ), '', array( 'response' => 500 ) );
    }

    nocache_headers();
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
    header( 'Content-Length: ' . strlen( $pdf ) );
    echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}

function baykat_farm_export_campaign_pdf() {
    if ( ! is_user_logged_in() || ! baykat_finance_user_can_access() ) {
        wp_die( esc_html__( 'Accès refusé.', 'baykat-finance' ), '', array( 'response' => 403 ) );
    }
    $campaign_id = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0;
    if ( ! $campaign_id ) {
        wp_die( esc_html__( 'Campagne invalide.', 'baykat-finance' ), '', array( 'response' => 400 ) );
    }
    check_admin_referer( 'baykat_export_campaign_pdf_' . $campaign_id );

    $campaigns = baykat_farm_campaign_get_user_campaigns( get_current_user_id() );
    if ( is_wp_error( $campaigns ) ) {
        wp_die( esc_html( $campaigns->get_error_message() ), '', array( 'response' => 500 ) );
    }
    $campaign = null;
    foreach ( $campaigns as $candidate ) {
        if ( (int) $candidate->id === $campaign_id && 'real' === $candidate->campaign_type ) {
            $campaign = $candidate;
            break;
        }
    }
    if ( ! $campaign ) {
        wp_die( esc_html__( 'Cette campagne est introuvable ou inaccessible.', 'baykat-finance' ), '', array( 'response' => 404 ) );
    }

    global $wpdb;
    $activities_table = baykat_farm_table_name( 'activities' );
    $harvests_table = $wpdb->prefix . 'baykat_harvests';
    $transactions_table = baykat_finance_get_table_name();
    $activities = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT activity_date, title, activity_type, notes
             FROM $activities_table
             WHERE campaign_id = %d
             ORDER BY activity_date ASC, id ASC",
            $campaign_id
        )
    );
    $harvests = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT harvest_date, quantity, unit, notes
             FROM $harvests_table
             WHERE campaign_id = %d
             ORDER BY harvest_date ASC, id ASC",
            $campaign_id
        )
    );
    $transactions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT operation_date, type, amount, item_name, description, expense_category, cancelled_at
             FROM $transactions_table
             WHERE campaign_id = %d
             ORDER BY operation_date ASC, id ASC",
            $campaign_id
        )
    );
    if ( '' !== $wpdb->last_error ) {
        wp_die( esc_html__( 'Impossible de charger toutes les données du bilan de campagne.', 'baykat-finance' ), '', array( 'response' => 500 ) );
    }

    $stock = baykat_farm_campaign_stock_get_summary( $campaign_id );
    if ( is_wp_error( $stock ) ) {
        wp_die( esc_html( $stock->get_error_message() ), '', array( 'response' => 500 ) );
    }
    $income = 0;
    $expenses = 0;
    $transaction_lines = array();
    foreach ( (array) $transactions as $transaction ) {
        $is_cancelled = ! empty( $transaction->cancelled_at );
        if ( ! $is_cancelled && 'income' === $transaction->type ) {
            $income += (float) $transaction->amount;
        } elseif ( ! $is_cancelled && 'expense' === $transaction->type ) {
            $expenses += (float) $transaction->amount;
        }
        $label = 'income' === $transaction->type
            ? 'Recette'
            : baykat_finance_expense_category_label( $transaction->expense_category );
        $detail = $transaction->item_name ? $transaction->item_name : $transaction->description;
        $transaction_lines[] = sprintf(
            '%s · %s · %s · %s FCFA%s',
            $transaction->operation_date ? $transaction->operation_date : 'Date non renseignée',
            $label,
            $detail ? $detail : 'Sans détail',
            number_format_i18n( (float) $transaction->amount, 0 ),
            $is_cancelled ? ' · Annulée' : ''
        );
    }
    $result = $income - $expenses;
    $harvest_lines = array();
    foreach ( (array) $harvests as $harvest ) {
        $harvest_lines[] = sprintf(
            '%s · %s %s%s',
            $harvest->harvest_date ? $harvest->harvest_date : 'Date non renseignée',
            baykat_farm_format_quantity( $harvest->quantity ),
            baykat_farm_normalize_unit( $harvest->unit ),
            $harvest->notes ? ' · ' . $harvest->notes : ''
        );
    }
    $activity_lines = array();
    $activity_types = array(
        'sowing' => 'Semis',
        'watering' => 'Arrosage',
        'fertilizing' => 'Fertilisation',
        'treatment' => 'Traitement',
        'harvest' => 'Récolte',
        'other' => 'Autre',
    );
    foreach ( (array) $activities as $activity ) {
        $activity_lines[] = sprintf(
            '%s · %s (%s)%s',
            $activity->activity_date,
            $activity->title,
            isset( $activity_types[ $activity->activity_type ] ) ? $activity_types[ $activity->activity_type ] : $activity->activity_type,
            $activity->notes ? ' · ' . $activity->notes : ''
        );
    }
    $operation_dates = array_filter(
        array_merge(
            wp_list_pluck( (array) $activities, 'activity_date' ),
            wp_list_pluck( (array) $harvests, 'harvest_date' ),
            wp_list_pluck( (array) $transactions, 'operation_date' )
        )
    );
    sort( $operation_dates );
    $campaign_status = baykat_farm_campaign_status_label( $campaign->production_status );
    $sections = array(
        array(
            'heading' => 'IDENTITÉ ET PÉRIODE',
            'lines' => array(
                'Ferme : ' . $campaign->farm_name,
                'Parcelle : ' . $campaign->parcel_name,
                'Culture : ' . $campaign->crop_name . ( $campaign->variety ? ' · Variété : ' . $campaign->variety : '' ),
                'Surface : ' . baykat_farm_format_quantity( $campaign->area_ha, 4 ) . ' ha',
                'Début : ' . ( $campaign->planned_start_date ? $campaign->planned_start_date : 'Non renseigné' )
                    . ' · Récolte prévue : ' . ( $campaign->planned_harvest_date ? $campaign->planned_harvest_date : 'Non renseignée' ),
                'Opérations enregistrées : ' . ( $operation_dates ? reset( $operation_dates ) . ' à ' . end( $operation_dates ) : 'Aucune date renseignée' ),
                'Statut de production : ' . $campaign_status
                    . ' · Commercialisation : ' . baykat_farm_report_commercialization_status_label( $campaign->commercialization_status ),
            ),
        ),
        array(
            'heading' => 'INDICATEURS DE CAMPAGNE',
            'lines' => array(
                'Récolte enregistrée : ' . baykat_farm_format_quantity( $stock['harvested_quantity'] ) . ( $stock['unit'] ? ' ' . $stock['unit'] : '' ),
                'Quantité physique disponible : ' . baykat_farm_format_quantity( $stock['physical_quantity'] ) . ( $stock['unit'] ? ' ' . $stock['unit'] : '' ),
                'Quantité réservée/allouée : ' . baykat_farm_format_quantity( $stock['allocated_quantity'] ) . ( $stock['unit'] ? ' ' . $stock['unit'] : '' ),
                'Disponible : ' . baykat_farm_format_quantity( $stock['available_quantity'] ) . ( $stock['unit'] ? ' ' . $stock['unit'] : '' ),
                'Recettes enregistrées : ' . number_format_i18n( $income, 0 ) . ' FCFA',
                'Dépenses enregistrées : ' . number_format_i18n( $expenses, 0 ) . ' FCFA',
                'Résultat simplifié : ' . number_format_i18n( $result, 0 ) . ' FCFA',
            ),
        ),
        array( 'heading' => 'ACTIVITÉS ET TRAVAUX', 'lines' => $activity_lines ? $activity_lines : array( 'Aucune activité enregistrée.' ) ),
        array( 'heading' => 'RÉCOLTES', 'lines' => $harvest_lines ? $harvest_lines : array( 'Aucune récolte enregistrée.' ) ),
        array( 'heading' => 'RECETTES ET DÉPENSES', 'lines' => $transaction_lines ? $transaction_lines : array( 'Aucune opération financière enregistrée.' ) ),
        array(
            'heading' => 'NOTE DE LECTURE',
            'lines' => array( 'Le résultat simplifié correspond aux recettes moins les dépenses enregistrées. Il ne constitue pas un bénéfice comptable. Les ventes BAYKAT non intégrées et les anciennes opérations sans date réelle ne sont pas reconstituées dans ce bilan.' ),
        ),
    );
    $today = current_time( 'Y-m-d' );
    baykat_farm_report_download_pdf(
        'bilan-campagne-' . $campaign_id . '-' . $today . '.pdf',
        'Bilan de campagne agricole',
        $campaign->crop_name . ' · ' . $campaign->farm_name . ' / ' . $campaign->parcel_name . ' · Généré le ' . $today,
        $sections
    );
}
add_action( 'admin_post_baykat_export_campaign_pdf', 'baykat_farm_export_campaign_pdf' );

function baykat_farm_export_farm_pdf() {
    if ( ! is_user_logged_in() || ! baykat_finance_user_can_access() ) {
        wp_die( esc_html__( 'Accès refusé.', 'baykat-finance' ), '', array( 'response' => 403 ) );
    }
    $farm_id = isset( $_GET['farm_id'] ) ? absint( $_GET['farm_id'] ) : 0;
    if ( ! $farm_id ) {
        wp_die( esc_html__( 'Ferme invalide.', 'baykat-finance' ), '', array( 'response' => 400 ) );
    }
    check_admin_referer( 'baykat_export_farm_pdf_' . $farm_id );

    $farm = null;
    foreach ( baykat_farm_get_user_farms( get_current_user_id() ) as $candidate ) {
        if ( (int) $candidate->id === $farm_id ) {
            $farm = $candidate;
            break;
        }
    }
    if ( ! $farm ) {
        wp_die( esc_html__( 'Cette ferme est introuvable ou inaccessible.', 'baykat-finance' ), '', array( 'response' => 404 ) );
    }

    global $wpdb;
    $transactions_table = baykat_finance_get_table_name();
    $activities_table = baykat_farm_table_name( 'activities' );
    $parcels_table = baykat_farm_table_name( 'parcels' );
    $harvests_table = $wpdb->prefix . 'baykat_harvests';
    $campaigns_table = baykat_farm_campaign_table_name();
    $transactions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT operation_date, type, amount, item_name, description, expense_category, cancelled_at
             FROM $transactions_table
             WHERE farm_id = %d
             ORDER BY operation_date ASC, id ASC",
            $farm_id
        )
    );
    $activities = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT activities.activity_date, activities.title, activities.activity_type, activities.notes, parcels.name AS parcel_name
             FROM $activities_table AS activities
             INNER JOIN $parcels_table AS parcels ON parcels.id = activities.parcel_id
             WHERE parcels.farm_id = %d
             ORDER BY activities.activity_date ASC, activities.id ASC",
            $farm_id
        )
    );
    $harvests = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT harvests.harvest_date, harvests.quantity, harvests.unit, harvests.notes, campaigns.crop_name, parcels.name AS parcel_name
             FROM $harvests_table AS harvests
             INNER JOIN $campaigns_table AS campaigns ON campaigns.id = harvests.campaign_id AND campaigns.campaign_type = 'real'
             INNER JOIN $parcels_table AS parcels ON parcels.id = campaigns.parcel_id
             WHERE campaigns.farm_id = %d
             ORDER BY harvests.harvest_date ASC, harvests.id ASC",
            $farm_id
        )
    );
    $campaigns = baykat_farm_campaign_get_user_campaigns( get_current_user_id() );
    if ( is_wp_error( $campaigns ) ) {
        wp_die( esc_html( $campaigns->get_error_message() ), '', array( 'response' => 500 ) );
    }
    if ( '' !== $wpdb->last_error ) {
        wp_die( esc_html__( 'Impossible de charger les données du bilan de ferme.', 'baykat-finance' ), '', array( 'response' => 500 ) );
    }
    $income = 0;
    $expenses = 0;
    $transaction_lines = array();
    foreach ( (array) $transactions as $transaction ) {
        $is_cancelled = ! empty( $transaction->cancelled_at );
        if ( ! $is_cancelled && 'income' === $transaction->type ) {
            $income += (float) $transaction->amount;
        } elseif ( ! $is_cancelled && 'expense' === $transaction->type ) {
            $expenses += (float) $transaction->amount;
        }
        $transaction_lines[] = sprintf(
            '%s · %s · %s · %s FCFA%s',
            $transaction->operation_date ? $transaction->operation_date : 'Date non renseignée',
            'income' === $transaction->type ? 'Recette' : baykat_finance_expense_category_label( $transaction->expense_category ),
            $transaction->item_name ? $transaction->item_name : ( $transaction->description ? $transaction->description : 'Sans détail' ),
            number_format_i18n( (float) $transaction->amount, 0 ),
            $is_cancelled ? ' · Annulée' : ''
        );
    }
    $activity_types = array(
        'sowing' => 'Semis',
        'watering' => 'Arrosage',
        'fertilizing' => 'Fertilisation',
        'treatment' => 'Traitement',
        'harvest' => 'Récolte',
        'other' => 'Autre',
    );
    $activity_lines = array();
    foreach ( (array) $activities as $activity ) {
        $activity_lines[] = sprintf(
            '%s · %s · %s · %s%s',
            $activity->activity_date,
            $activity->parcel_name,
            isset( $activity_types[ $activity->activity_type ] ) ? $activity_types[ $activity->activity_type ] : $activity->activity_type,
            $activity->title,
            $activity->notes ? ' · ' . $activity->notes : ''
        );
    }
    $harvest_lines = array();
    foreach ( (array) $harvests as $harvest ) {
        $harvest_lines[] = sprintf(
            '%s · %s / %s · %s %s%s',
            $harvest->harvest_date ? $harvest->harvest_date : 'Date non renseignée',
            $harvest->crop_name,
            $harvest->parcel_name,
            baykat_farm_format_quantity( $harvest->quantity ),
            baykat_farm_normalize_unit( $harvest->unit ),
            $harvest->notes ? ' · ' . $harvest->notes : ''
        );
    }
    $campaign_lines = array();
    foreach ( $campaigns as $campaign ) {
        if ( 'real' !== $campaign->campaign_type || (int) $campaign->farm_id !== $farm_id ) {
            continue;
        }
        $campaign_stock = baykat_farm_campaign_stock_get_summary( (int) $campaign->id );
        if ( is_wp_error( $campaign_stock ) ) {
            wp_die( esc_html( $campaign_stock->get_error_message() ), '', array( 'response' => 500 ) );
        }
        $campaign_harvest = 1 === (int) $campaign_stock['unit_count']
            ? baykat_farm_format_quantity( $campaign_stock['harvested_quantity'] ) . ' ' . $campaign_stock['unit']
            : 'Unités mixtes ou quantité non disponible';
        $campaign_lines[] = sprintf(
            '%s · %s · %s · Récolte : %s · Recettes : %s FCFA · Dépenses : %s FCFA · Résultat simplifié : %s FCFA',
            $campaign->crop_name,
            $campaign->parcel_name,
            baykat_farm_campaign_status_label( $campaign->production_status ),
            $campaign_harvest,
            number_format_i18n( (float) $campaign->actual_income, 0 ),
            number_format_i18n( (float) $campaign->actual_expenses, 0 ),
            number_format_i18n( (float) $campaign->actual_income - (float) $campaign->actual_expenses, 0 )
        );
    }
    $operation_dates = array_filter(
        array_merge(
            wp_list_pluck( (array) $transactions, 'operation_date' ),
            wp_list_pluck( (array) $activities, 'activity_date' ),
            wp_list_pluck( (array) $harvests, 'harvest_date' )
        )
    );
    sort( $operation_dates );
    $today = current_time( 'Y-m-d' );
    $sections = array(
        array(
            'heading' => 'IDENTITÉ DE LA FERME',
            'lines' => array(
                'Ferme : ' . $farm->name,
                'Localisation : ' . ( $farm->location ? $farm->location : 'Non renseignée' ),
                'Surface déclarée : ' . ( $farm->area_ha ? baykat_farm_format_quantity( $farm->area_ha, 4 ) . ' ha' : 'Non renseignée' ),
                'Période couverte : ' . ( $operation_dates ? reset( $operation_dates ) . ' à ' . end( $operation_dates ) : 'Aucune date d’opération renseignée' ),
            ),
        ),
        array(
            'heading' => 'SYNTHÈSE FINANCIÈRE',
            'lines' => array(
                'Recettes enregistrées : ' . number_format_i18n( $income, 0 ) . ' FCFA',
                'Dépenses enregistrées : ' . number_format_i18n( $expenses, 0 ) . ' FCFA',
                'Résultat simplifié : ' . number_format_i18n( $income - $expenses, 0 ) . ' FCFA',
            ),
        ),
        array( 'heading' => 'CAMPAGNES RÉELLES', 'lines' => $campaign_lines ? $campaign_lines : array( 'Aucune campagne réelle enregistrée pour cette ferme.' ) ),
        array( 'heading' => 'ACTIVITÉS ET TRAVAUX', 'lines' => $activity_lines ? $activity_lines : array( 'Aucune activité enregistrée.' ) ),
        array( 'heading' => 'RÉCOLTES', 'lines' => $harvest_lines ? $harvest_lines : array( 'Aucune récolte rattachée à une campagne réelle.' ) ),
        array( 'heading' => 'JOURNAL FINANCIER', 'lines' => $transaction_lines ? $transaction_lines : array( 'Aucune opération financière enregistrée.' ) ),
        array( 'heading' => 'NOTE DE LECTURE', 'lines' => array( 'Le résultat simplifié correspond aux recettes moins les dépenses enregistrées. Il ne constitue pas un bénéfice comptable. Les ventes BAYKAT non intégrées et les anciennes opérations sans date réelle ne sont pas reconstituées dans ce bilan.' ) ),
    );
    baykat_farm_report_download_pdf(
        'bilan-ferme-' . $farm_id . '-' . $today . '.pdf',
        'Bilan agricole de la ferme',
        $farm->name . ' · Généré le ' . $today,
        $sections
    );
}
add_action( 'admin_post_baykat_export_farm_pdf', 'baykat_farm_export_farm_pdf' );

function baykat_farm_render_reports() {
    $user_id = get_current_user_id();
    $farms = baykat_farm_get_user_farms( $user_id );
    ?>
    <div class="baykat-farm-content">
        <header class="baykat-farm-section-heading">
            <span class="baykat-farm-eyebrow">Téléchargements</span>
            <h2>Rapports et exports</h2>
            <p>Téléchargez un bilan PDF par ferme ou conservez l’export CSV détaillé pour vos tableurs.</p>
        </header>
        <?php if ( $farms ) : ?>
            <section class="baykat-farm-panel">
                <h3>Rapport agricole complet</h3>
                <p>Le CSV comprend les transactions accessibles et un résumé de rentabilité par culture.</p>
                <a class="baykat-finance-button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=baykat_export_farm_csv' ), 'baykat_export_farm_csv' ) ); ?>">Télécharger le rapport CSV</a>
            </section>
            <section class="baykat-farm-panel">
                <h3>Bilans PDF par ferme</h3>
                <p>Chaque bilan récapitule la synthèse financière, les campagnes réelles et le journal financier. Le résultat est un indicateur simplifié, pas un bénéfice comptable.</p>
                <ul class="baykat-farm-report-download-list">
                    <?php foreach ( $farms as $farm ) : ?>
                        <?php
                        $pdf_url = add_query_arg(
                            array(
                                'action' => 'baykat_export_farm_pdf',
                                'farm_id' => (int) $farm->id,
                            ),
                            admin_url( 'admin-post.php' )
                        );
                        ?>
                        <li class="baykat-farm-report-download-item">
                            <strong><?php echo esc_html( $farm->name ); ?></strong>
                            <a class="baykat-finance-button" href="<?php echo esc_url( wp_nonce_url( $pdf_url, 'baykat_export_farm_pdf_' . (int) $farm->id ) ); ?>">
                                <span aria-hidden="true">↓</span>
                                Télécharger le bilan PDF
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php else : ?>
            <p class="baykat-farm-empty-state">Aucune ferme accessible à exporter.</p>
        <?php endif; ?>
    </div>
    <?php
}
