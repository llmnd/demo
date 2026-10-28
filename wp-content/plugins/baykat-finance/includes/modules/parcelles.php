<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_farm_render_parcel_campaign_section( $campaigns ) {
    ?>
    <details class="baykat-farm-workspace-section" id="baykat-workspace-campaigns" open>
        <summary>
            <span class="baykat-farm-workspace-summary">
                <strong>Campagnes</strong>
                <small>Productions rattachées à cette parcelle</small>
            </span>
            <span class="baykat-farm-workspace-count"><?php echo esc_html( number_format_i18n( count( $campaigns ) ) ); ?></span>
        </summary>
        <div class="baykat-farm-workspace-content baykat-farm-parcel-campaigns">
            <?php foreach ( $campaigns as $campaign ) : ?>
                <article
                    id="baykat-workspace-campaign-<?php echo esc_attr( $campaign->id ); ?>"
                    class="baykat-farm-parcel-campaign"
                    data-farm-record-card
                    data-parcel-id="<?php echo esc_attr( $campaign->parcel_id ); ?>"
                >
                    <div>
                        <span class="baykat-farm-eyebrow"><?php echo esc_html( baykat_farm_campaign_status_label( $campaign->production_status ) ); ?></span>
                        <h4><?php echo esc_html( $campaign->crop_name ); ?></h4>
                        <p>
                            <?php echo esc_html( baykat_farm_format_quantity( $campaign->area_ha, 4 ) ); ?> ha
                            <?php if ( $campaign->variety ) : ?> · <?php echo esc_html( $campaign->variety ); ?><?php endif; ?>
                            <?php if ( $campaign->planned_start_date ) : ?> · Début : <?php echo esc_html( mysql2date( 'd/m/Y', $campaign->planned_start_date ) ); ?><?php endif; ?>
                        </p>
                    </div>
                    <a class="baykat-farm-parcel-campaign-link" href="<?php echo esc_url( add_query_arg( 'module', 'campagnes', get_permalink() ) . '#baykat-campaign-record-' . absint( $campaign->id ) ); ?>">Consulter la campagne</a>
                </article>
            <?php endforeach; ?>
            <p class="baykat-farm-empty-state baykat-farm-parcel-campaign-empty" data-parcel-campaign-empty hidden>Aucune campagne n’est encore rattachée à cette parcelle.</p>
            <a class="baykat-farm-parcel-campaign-create" href="<?php echo esc_url( add_query_arg( 'module', 'campagnes', get_permalink() ) . '#baykat-campaign-create-title' ); ?>">Créer une campagne</a>
        </div>
    </details>
    <?php
}

function baykat_farm_render_parcelles( $process_actions = true ) {
    $user_id = get_current_user_id();
    $message = null;

    if ( $process_actions ) {
        $message = baykat_farm_process_archive_submission( $user_id );
        if ( ! $message ) {
            $message = baykat_farm_process_record_action( $user_id, 'parcel' );
        }
        if ( ! $message ) {
            $message = baykat_farm_process_parcel_submission( $user_id );
        }
        if ( ! $message ) {
            $message = baykat_farm_process_image_update( $user_id, 'parcel' );
        }
    }
    $farms = baykat_farm_get_user_farms( $user_id );
    $editable_farms = baykat_farm_filter_editable_records( $farms );
    $parcels = baykat_farm_get_user_parcels( $user_id );
    $parcel_campaigns = array();
    if ( $process_actions ) {
        $parcel_campaigns = baykat_farm_campaign_get_user_campaigns( $user_id );
        if ( is_wp_error( $parcel_campaigns ) ) {
            if ( ! $message ) {
                $message = array( 'type' => 'error', 'message' => $parcel_campaigns->get_error_message() );
            }
            $parcel_campaigns = array();
        }
        $parcel_campaigns = array_values(
            array_filter(
                $parcel_campaigns,
                function ( $campaign ) {
                    return 'real' === $campaign->campaign_type;
                }
            )
        );
    }
    $parcel_farm_filters = array();
    foreach ( (array) $parcels as $parcel ) {
        $parcel_farm_filters[ (int) $parcel->farm_id ] = $parcel->farm_name;
    }
    $has_multiple_parcel_farms = count( $parcel_farm_filters ) > 1;
    ?>
    <div class="baykat-farm-content">
        <?php if ( $message ) : ?>
            <div class="baykat-farm-notice baykat-farm-notice-<?php echo esc_attr( $message['type'] ); ?>" role="<?php echo 'error' === $message['type'] ? 'alert' : 'status'; ?>"><?php echo esc_html( $message['message'] ); ?></div>
        <?php endif; ?>

        <?php if ( $editable_farms ) : ?>
            <section class="baykat-farm-panel baykat-farm-add-panel" aria-labelledby="baykat-farm-parcel-form-heading">
                <details class="baykat-farm-add-form">
                    <summary id="baykat-farm-parcel-form-title"><span class="baykat-farm-eyebrow">Nouvelle parcelle</span><strong id="baykat-farm-parcel-form-heading">Ajouter une parcelle</strong><span class="baykat-farm-add-form-icon" aria-hidden="true">+</span></summary>
                    <form class="baykat-farm-form" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'baykat_add_parcel', '_wpnonce' ); ?>
                    <p class="baykat-farm-add-form-description">Choisissez d’abord la ferme, puis donnez un nom simple à la parcelle. Sa superficie et sa photo peuvent être ajoutées plus tard.</p>
                    <div class="baykat-farm-field">
                        <label for="baykat_parcel_farm">Ferme</label>
                        <select name="farm_id" id="baykat_parcel_farm" required>
                            <option value="">Choisir une ferme</option>
                            <?php foreach ( $editable_farms as $farm ) : ?>
                                <option value="<?php echo esc_attr( $farm->id ); ?>"><?php echo esc_html( $farm->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_parcel_name">Nom de la parcelle</label>
                        <input type="text" name="parcel_name" id="baykat_parcel_name" maxlength="190" placeholder="Ex. : Champ près du puits" required>
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_parcel_area">Superficie (m², facultatif)</label>
                        <input type="number" name="parcel_area_m2" id="baykat_parcel_area" min="0.01" step="0.01">
                    </div>
                    <?php baykat_farm_render_image_field( 'parcel_image', 'Photo de la parcelle' ); ?>
                    <button class="baykat-finance-button" type="submit" name="baykat_add_parcel" value="1">Enregistrer la parcelle</button>
                    </form>
                </details>
            </section>
        <?php elseif ( ! $farms ) : ?>
            <section class="baykat-farm-panel baykat-farm-empty-state">
                <p>Ajoutez d'abord une ferme dans la <a href="<?php echo esc_url( add_query_arg( 'module', 'vue-ensemble', get_permalink() ) ); ?>">Vue d'ensemble</a> pour pouvoir créer ses parcelles.</p>
            </section>
        <?php else : ?>
            <p class="baykat-farm-empty-state">Votre rôle actuel autorise uniquement la consultation des fermes partagées avec vous.</p>
        <?php endif; ?>

        <section class="baykat-farm-panel" aria-labelledby="baykat-farm-parcel-list-title">
            <header class="baykat-farm-panel-heading"><span class="baykat-farm-eyebrow">Votre terrain</span><h3 id="baykat-farm-parcel-list-title">Parcelles enregistrées</h3></header>
            <p class="baykat-farm-parcel-intro"><?php echo $has_multiple_parcel_farms ? 'Chaque parcelle appartient à une ferme. Recherchez par nom ou filtrez par ferme pour retrouver rapidement le bon terrain.' : 'Chaque parcelle est rattachée à sa ferme. Utilisez la recherche pour retrouver rapidement un terrain par son nom.'; ?></p>
            <?php if ( $parcels ) : ?>
                <?php baykat_farm_render_record_filters( 'baykat-farm-parcel-list', 'Rechercher une parcelle', $has_multiple_parcel_farms ? $parcel_farm_filters : array(), 'Filtrer par ferme', true ); ?>
                <div class="baykat-farm-record-grid baykat-farm-visual-grid baykat-farm-parcel-grid" id="baykat-farm-parcel-list">
                    <?php foreach ( $parcels as $parcel ) : ?>
                        <article
                            id="baykat-parcel-record-<?php echo esc_attr( $parcel->id ); ?>"
                            class="baykat-farm-record-card baykat-farm-visual-card baykat-farm-parcel-card<?php echo $parcel->image_id ? ' has-image' : ''; ?>"
                            data-farm-record-card
                            data-parcel-record-card
                            data-parcel-farm-id="<?php echo esc_attr( $parcel->farm_id ); ?>"
                            data-parcel-id="<?php echo esc_attr( $parcel->id ); ?>"
                            data-farm-record-filter-value="<?php echo esc_attr( $parcel->farm_id ); ?>"
                            data-search-text="<?php echo esc_attr( $parcel->farm_name . ' ' . $parcel->name . ' ' . ( null === $parcel->area_m2 ? '' : $parcel->area_m2 ) ); ?>"
                        >
                            <?php if ( $parcel->image_id ) : ?>
                                <?php echo baykat_farm_render_image( $parcel->image_id, 'medium', 'baykat-farm-parcel-image' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php endif; ?>
                            <div class="baykat-farm-visual-card-body">
                                <span class="baykat-farm-eyebrow">Ferme · <?php echo esc_html( $parcel->farm_name ); ?></span>
                                <h4><?php echo esc_html( $parcel->name ); ?></h4>
                                <div class="baykat-farm-card-meta">
                                    <?php if ( null === $parcel->area_m2 ) : ?>
                                        <span class="baykat-farm-parcel-area-missing">Superficie à renseigner</span>
                                    <?php else : ?>
                                        <span class="baykat-farm-parcel-area-label">Superficie</span>
                                        <strong><?php echo esc_html( baykat_farm_format_quantity( $parcel->area_m2, 2 ) ); ?> m²</strong>
                                    <?php endif; ?>
                                </div>
                                <button class="baykat-farm-open-record" type="button" data-open-parcel-modal="<?php echo esc_attr( $parcel->id ); ?>">Ouvrir les détails</button>
                                <?php if ( in_array( $parcel->access_role, array( 'owner', 'manager' ), true ) ) : ?>
                                <?php baykat_farm_render_image_update_form( 'parcel', $parcel->id, $parcel->image_id ? 'Changer la photo' : 'Ajouter une photo' ); ?>
                                <details class="baykat-farm-record-actions">
                                    <summary>Modifier la parcelle</summary>
                                    <form class="baykat-farm-record-form" method="post">
                                        <?php wp_nonce_field( 'baykat_update_parcel', '_wpnonce' ); ?>
                                        <input type="hidden" name="baykat_update_parcel_id" value="<?php echo esc_attr( $parcel->id ); ?>">
                                        <div class="baykat-farm-field">
                                            <label for="baykat_parcel_edit_farm_<?php echo esc_attr( $parcel->id ); ?>">Ferme</label>
                                            <select name="farm_id" id="baykat_parcel_edit_farm_<?php echo esc_attr( $parcel->id ); ?>" required>
                                                <?php foreach ( $editable_farms as $farm ) : ?>
                                                    <option value="<?php echo esc_attr( $farm->id ); ?>" <?php selected( (int) $parcel->farm_id, (int) $farm->id ); ?>><?php echo esc_html( $farm->name ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_parcel_edit_name_<?php echo esc_attr( $parcel->id ); ?>">Nom</label>
                                            <input type="text" name="parcel_name" id="baykat_parcel_edit_name_<?php echo esc_attr( $parcel->id ); ?>" maxlength="190" value="<?php echo esc_attr( $parcel->name ); ?>" required>
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_parcel_edit_area_<?php echo esc_attr( $parcel->id ); ?>">Superficie (m²)</label>
                                            <input type="number" name="parcel_area_m2" id="baykat_parcel_edit_area_<?php echo esc_attr( $parcel->id ); ?>" min="0.01" step="0.01" value="<?php echo esc_attr( $parcel->area_m2 ); ?>">
                                        </div>
                                        <button class="baykat-finance-button" type="submit" name="baykat_update_parcel" value="1">Enregistrer les modifications</button>
                                    </form>
                                    <form class="baykat-farm-delete-form" method="post">
                                        <?php wp_nonce_field( 'baykat_delete_parcel', '_wpnonce' ); ?>
                                        <input type="hidden" name="baykat_delete_parcel_id" value="<?php echo esc_attr( $parcel->id ); ?>">
                                        <button class="baykat-finance-button baykat-farm-delete-button" type="submit" name="baykat_delete_parcel" value="1">Supprimer la parcelle</button>
                                    </form>
                                    <?php baykat_farm_render_archive_action( 'parcel', (int) $parcel->id, 'archive', 'Archiver la parcelle' ); ?>
                                </details>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="baykat-farm-empty-state">Aucune parcelle enregistrée pour le moment.</p>
            <?php endif; ?>
        </section>
    </div>
    <?php if ( $process_actions ) : ?>
        <template data-dashboard-workspace-template>
            <?php baykat_farm_render_parcel_campaign_section( $parcel_campaigns ); ?>
        </template>
        <dialog class="baykat-farm-dashboard-modal" data-farm-dashboard-dialog aria-labelledby="baykat-farm-modal-title">
            <div class="baykat-farm-dashboard-modal-shell">
                <header class="baykat-farm-dashboard-modal-header">
                    <div>
                        <span class="baykat-farm-eyebrow">Détail de la parcelle</span>
                        <h2 id="baykat-farm-modal-title" data-dashboard-modal-title>Parcelle</h2>
                    </div>
                    <button class="baykat-farm-modal-close" type="button" data-close-dashboard-modal aria-label="Fermer la fenêtre" autofocus>×</button>
                </header>
                <div class="baykat-farm-dashboard-modal-body" data-dashboard-modal-body></div>
                <template data-dashboard-workspace-template-modal>
                    <section class="baykat-farm-modal-workspace" aria-label="Informations de la parcelle">
                        <header class="baykat-farm-modal-workspace-heading">
                            <span class="baykat-farm-eyebrow">Gestion de la parcelle</span>
                            <h3>Campagnes liées</h3>
                            <p>Consultez les productions rattachées à cette parcelle.</p>
                        </header>
                        <div data-dashboard-workspace-target></div>
                    </section>
                </template>
            </div>
        </dialog>
    <?php endif; ?>
    <?php
}