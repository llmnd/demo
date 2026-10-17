<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Traite les soumissions du workspace agricole.
 */
function baykat_farm_process_workspace_submissions( $user_id ) {
    $processors = array(
        array( 'baykat_farm_process_sharing_submission', array( $user_id ) ),
        array( 'baykat_farm_process_record_action', array( $user_id, 'farm' ) ),
        array( 'baykat_farm_process_farm_submission', array( $user_id ) ),
        array( 'baykat_farm_process_image_update', array( $user_id, 'farm' ) ),
        array( 'baykat_farm_process_record_action', array( $user_id, 'parcel' ) ),
        array( 'baykat_farm_process_parcel_submission', array( $user_id ) ),
        array( 'baykat_farm_process_image_update', array( $user_id, 'parcel' ) ),
        array( 'baykat_farm_process_record_action', array( $user_id, 'crop' ) ),
        array( 'baykat_farm_process_crop_submission', array( $user_id ) ),
        array( 'baykat_farm_process_crop_harvest', array( $user_id ) ),
        array( 'baykat_farm_process_image_update', array( $user_id, 'crop' ) ),
        array( 'baykat_farm_process_record_action', array( $user_id, 'activity' ) ),
        array( 'baykat_farm_process_activity_submission', array( $user_id ) ),
        array( 'baykat_farm_process_image_update', array( $user_id, 'activity' ) ),
        array( 'baykat_finance_process_transaction_submission', array( $user_id ) ),
        array( 'baykat_finance_process_transaction_submission', array( $user_id, 'input' ) ),
        array( 'baykat_finance_process_transaction_submission', array( $user_id, 'sale' ) ),
        array( 'baykat_finance_process_sale_cancellation', array( $user_id ) ),
    );
    $messages = array();

    foreach ( $processors as $processor ) {
        $message = call_user_func_array( $processor[0], $processor[1] );
        if ( $message ) {
            $messages[] = $message;
        }
    }

    foreach ( $messages as $message ) {
        if ( 'error' === $message['type'] ) {
            return $message;
        }
    }

    return $messages ? $messages[0] : null;
}

/**
 * Récupère un identifiant de ferme parent depuis n'importe quel nommage de clé.
 */
function baykat_farm_resolve_parent_farm_id( $item ) {
    if ( ! is_object( $item ) ) {
        return 0;
    }
    $keys = array( 'farm_id', 'farmId', 'farm', 'parent_farm_id', 'parent_id' );
    foreach ( $keys as $key ) {
        if ( isset( $item->$key ) && $item->$key ) {
            return (int) $item->$key;
        }
    }
    return 0;
}

function baykat_farm_render_location_picker( $farm = null ) {
    $farm_id   = $farm ? (int) $farm->id : 0;
    $field_id  = $farm_id ? 'baykat_farm_edit_location_' . $farm_id : 'baykat_farm_location';
    $latitude  = $farm && isset( $farm->latitude ) ? (float) $farm->latitude : '';
    $longitude = $farm && isset( $farm->longitude ) ? (float) $farm->longitude : '';
    ?>
    <div class="baykat-farm-field baykat-farm-field-wide">
        <label for="<?php echo esc_attr( $field_id ); ?>">Localisation</label>
        <div class="baykat-farm-location-search">
            <input type="search" name="farm_location" id="<?php echo esc_attr( $field_id ); ?>" maxlength="190" placeholder="Adresse, village ou commune" value="<?php echo esc_attr( $farm ? $farm->location : '' ); ?>">
            <button class="baykat-finance-button" type="button" data-geocode-search>Rechercher</button>
        </div>
        <small class="baykat-farm-location-privacy">La recherche envoie le lieu saisi à OpenStreetMap. Les tuiles de la carte sont également chargées depuis OpenStreetMap.</small>
        <div class="baykat-farm-geocode-results" data-geocode-results hidden></div>
    </div>
    <div class="baykat-farm-field baykat-farm-field-wide">
        <span class="baykat-farm-field-label">Emplacement précis</span>
        <div
            class="baykat-farm-location-picker"
            data-farm-location-picker
            data-latitude="<?php echo esc_attr( $latitude ); ?>"
            data-longitude="<?php echo esc_attr( $longitude ); ?>"
            role="application"
            aria-label="Carte pour choisir l’emplacement de la ferme"
            tabindex="0"
        ></div>
        <p class="baykat-farm-location-status" data-location-status role="status"><?php echo $farm && null !== $farm->latitude ? 'Déplacez l’épingle si nécessaire pour ajuster l’emplacement.' : 'Recherchez un lieu, puis cliquez sur la carte pour placer l’épingle.'; ?></p>
        <button class="baykat-farm-location-clear" type="button" data-clear-farm-location>Retirer l’épingle</button>
        <input type="hidden" name="farm_latitude" value="<?php echo esc_attr( $latitude ); ?>" data-farm-latitude>
        <input type="hidden" name="farm_longitude" value="<?php echo esc_attr( $longitude ); ?>" data-farm-longitude>
    </div>
    <?php
}

/**
 * Tableau de bord principal — refonte v4 (cartes égalisées, style Baykat).
 */
function baykat_farm_render_dashboard() {
    $user_id = get_current_user_id();
    $message = baykat_farm_process_workspace_submissions( $user_id );

    $farms        = baykat_farm_get_user_farms( $user_id );
    $parcels      = baykat_farm_get_user_parcels( $user_id );
    $crops        = baykat_farm_get_user_crops( $user_id );
    $activities   = baykat_farm_get_user_activities( $user_id );
    $transactions = baykat_finance_get_user_transactions( $user_id );
    $stocks       = baykat_farm_get_stock_totals( $user_id );

    $total_area = 0;
    foreach ( $farms as $farm ) {
        $total_area += (float) $farm->area_ha;
    }

    $growing_crops = array_filter( $crops, function ( $crop ) {
        return 'growing' === $crop->status;
    } );

    $today = current_time( 'Y-m-d' );
    $upcoming_harvests = array_filter( $crops, function ( $crop ) use ( $today ) {
        return ! empty( $crop->expected_harvest_date )
            && $crop->expected_harvest_date >= $today
            && 'harvested' !== $crop->status;
    } );
    usort( $upcoming_harvests, function ( $first, $second ) {
        return strcmp( $first->expected_harvest_date, $second->expected_harvest_date );
    } );

    $activity_types = array(
        'sowing'      => 'Semis',
        'watering'    => 'Arrosage',
        'fertilizing' => 'Fertilisation',
        'treatment'   => 'Traitement',
        'harvest'     => 'Récolte',
        'other'       => 'Autre',
    );

    $current_user = wp_get_current_user();

    // Image hero : priorité à la première ferme, sinon ambiance Baykat
    $hero_image = '';
    if ( ! empty( $farms ) && ! empty( $farms[0]->image_id ) ) {
        $hero_image = wp_get_attachment_image_url( $farms[0]->image_id, 'full' );
    }

    // Pré-calculs par ferme
    $parcels_by_farm = array();
    foreach ( $parcels as $p ) {
        $fid = baykat_farm_resolve_parent_farm_id( $p );
        if ( $fid > 0 ) {
            if ( ! isset( $parcels_by_farm[ $fid ] ) ) {
                $parcels_by_farm[ $fid ] = array();
            }
            $parcels_by_farm[ $fid ][] = $p;
        }
    }

    $crops_by_farm = array();
    foreach ( $crops as $c ) {
        $fid = baykat_farm_resolve_parent_farm_id( $c );
        if ( $fid > 0 ) {
            if ( ! isset( $crops_by_farm[ $fid ] ) ) {
                $crops_by_farm[ $fid ] = array();
            }
            $crops_by_farm[ $fid ][] = $c;
        }
    }

    $balance_value = isset( $transactions['balance'] ) ? (float) $transactions['balance'] : 0;
    $all_tx_count  = count( $transactions['transactions'] );
    ?>
    <div class="baykat-farm-content baykat-farm-v4">

        <!-- ============================= HERO IMMERSIF ============================= -->
        <header class="baykat-farm-hero<?php echo $hero_image ? ' has-image' : ' is-default'; ?>"
            <?php if ( $hero_image ) : ?>
                style="--baykat-hero-image:url('<?php echo esc_url( $hero_image ); ?>');"
            <?php endif; ?>>
            <div class="baykat-farm-hero-overlay" aria-hidden="true"></div>
            <div class="baykat-farm-hero-grain" aria-hidden="true"></div>

            <div class="baykat-farm-hero-inner">
                <span class="baykat-farm-eyebrow">Tableau de bord · <?php echo esc_html( date_i18n( 'd/m/Y' ) ); ?></span>
                <h1 class="baykat-farm-hero-title">
                    Bonjour <em><?php echo esc_html( $current_user->display_name ); ?></em>
                </h1>
                <p class="baykat-farm-hero-sub">
                    <?php
                    if ( count( $farms ) > 1 ) {
                        echo esc_html( 'Vos ' . number_format_i18n( count( $farms ) ) . ' exploitations sous un même ciel.' );
                    } elseif ( ! empty( $farms ) ) {
                        echo esc_html( 'Voici l\'état de ' . $farms[0]->name . ' aujourd\'hui.' );
                    } else {
                        echo esc_html( 'Créez votre première exploitation pour commencer.' );
                    }
                    ?>
                </p>

                <div class="baykat-farm-hero-actions">
                    <!-- Bouton style "Vendre sur BAYKAT" -->
                    <a class="baykat-farm-sell-button" href="#baykat-farm-add-title" data-open-management-target="baykat-farm-add-title">
                        <span class="baykat-farm-sell-button-label">＋ Ajouter une ferme</span>
                        <span class="baykat-farm-sell-button-plus" aria-hidden="true">+</span>
                    </a>
                    <button class="baykat-farm-stats-button" type="button" data-open-stats-modal>
                        <span aria-hidden="true">▦</span> Statistiques &amp; gestion
                    </button>
                </div>
            </div>

            <a class="baykat-farm-scroll-hint" href="#baykat-farm-daily" aria-label="Voir le quotidien de vos exploitations">
                <span></span>
            </a>
        </header>

        <?php if ( $message ) : ?>
            <div class="baykat-farm-notice baykat-farm-notice-<?php echo esc_attr( $message['type'] ); ?>"
                 role="<?php echo 'error' === $message['type'] ? 'alert' : 'status'; ?>">
                <?php echo esc_html( $message['message'] ); ?>
            </div>
        <?php endif; ?>

        <button class="baykat-farm-daily-button" id="baykat-farm-daily" type="button" data-open-daily-modal>
            <span class="baykat-farm-daily-button-icon" aria-hidden="true">◷</span>
            <span><strong>Le quotidien de vos exploitations</strong><small>Récoltes, journal et finances</small></span>
            <span class="baykat-farm-daily-button-arrow" aria-hidden="true">→</span>
        </button>
        <template data-dashboard-daily-template>
            <div class="baykat-farm-dashboard-overview">

            <section class="baykat-farm-panel baykat-farm-dashboard-list" aria-labelledby="baykat-farm-upcoming-title">
                <header class="baykat-farm-panel-heading">
                    <span class="baykat-farm-eyebrow">Calendrier</span>
                    <h3 id="baykat-farm-upcoming-title">Prochaines récoltes</h3>
                </header>
                <div class="baykat-farm-dashboard-list-body">
                    <?php if ( $upcoming_harvests ) : ?>
                        <ul class="baykat-farm-timeline">
                            <?php foreach ( array_slice( $upcoming_harvests, 0, 4 ) as $crop ) :
                                $days = (int) floor( ( strtotime( $crop->expected_harvest_date ) - strtotime( $today ) ) / DAY_IN_SECONDS );
                                $farm_label   = isset( $crop->farm_name )   ? $crop->farm_name   : 'Ferme';
                                $parcel_label = isset( $crop->parcel_name ) ? $crop->parcel_name : 'Parcelle';
                            ?>
                                <li>
                                    <span class="baykat-farm-timeline-dot is-harvest" aria-hidden="true"></span>
                                    <span class="baykat-farm-dashboard-list-copy">
                                        <strong><?php echo esc_html( $crop->name ); ?></strong>
                                        <small><?php echo esc_html( $farm_label . ' · ' . $parcel_label ); ?></small>
                                    </span>
                                    <span class="baykat-farm-dashboard-badge">
                                        <?php
                                        if ( $days <= 0 ) {
                                            echo 'Aujourd\'hui';
                                        } elseif ( $days < 30 ) {
                                            echo esc_html( $days . ' j' );
                                        } else {
                                            echo esc_html( date_i18n( 'd/m', strtotime( $crop->expected_harvest_date . ' 12:00:00' ) ) );
                                        }
                                        ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="baykat-farm-empty-state">Aucune récolte planifiée pour le moment.</p>
                    <?php endif; ?>
                </div>
                <a class="baykat-farm-dashboard-more" href="#baykat-workspace-crops" data-open-management-target="baykat-workspace-crops">Voir toutes les cultures <span aria-hidden="true">→</span></a>
            </section>

            <section class="baykat-farm-panel baykat-farm-dashboard-list" aria-labelledby="baykat-farm-recent-activities-title">
                <header class="baykat-farm-panel-heading">
                    <span class="baykat-farm-eyebrow">Journal</span>
                    <h3 id="baykat-farm-recent-activities-title">Activités récentes</h3>
                </header>
                <div class="baykat-farm-dashboard-list-body">
                    <?php if ( $activities ) : ?>
                        <ul class="baykat-farm-timeline">
                            <?php foreach ( array_slice( $activities, 0, 4 ) as $activity ) :
                                $parcel_label = isset( $activity->parcel_name ) ? $activity->parcel_name : '';
                                $type_label   = isset( $activity_types[ $activity->activity_type ] ) ? $activity_types[ $activity->activity_type ] : $activity->activity_type;
                                $meta         = trim( $type_label . ( $parcel_label ? ' · ' . $parcel_label : '' ) );
                            ?>
                                <li>
                                    <span class="baykat-farm-timeline-dot" aria-hidden="true"></span>
                                    <span class="baykat-farm-dashboard-list-copy">
                                        <strong><?php echo esc_html( $activity->title ); ?></strong>
                                        <small><?php echo esc_html( $meta ); ?></small>
                                    </span>
                                    <time datetime="<?php echo esc_attr( $activity->activity_date ); ?>"><?php echo esc_html( date_i18n( 'd/m', strtotime( $activity->activity_date . ' 12:00:00' ) ) ); ?></time>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="baykat-farm-empty-state">Les activités de votre ferme apparaîtront ici.</p>
                    <?php endif; ?>
                </div>
                <a class="baykat-farm-dashboard-more" href="#baykat-workspace-activities" data-open-management-target="baykat-workspace-activities">Voir le journal <span aria-hidden="true">→</span></a>
            </section>

            <section class="baykat-farm-panel baykat-farm-dashboard-list" aria-labelledby="baykat-farm-recent-finances-title">
                <header class="baykat-farm-panel-heading">
                    <span class="baykat-farm-eyebrow">Finances</span>
                    <h3 id="baykat-farm-recent-finances-title">Dernières transactions</h3>
                </header>
                <div class="baykat-farm-dashboard-list-body">
                    <?php if ( ! empty( $transactions['transactions'] ) ) : ?>
                        <ul class="baykat-farm-timeline">
                            <?php foreach ( array_slice( $transactions['transactions'], 0, 4 ) as $transaction ) :
                                $is_cancelled = ! empty( $transaction->cancelled_at );
                                $is_income    = 'income' === $transaction->type;
                                $farm_label   = isset( $transaction->farm_name )   ? $transaction->farm_name   : '';
                                $parcel_label = isset( $transaction->parcel_name ) ? $transaction->parcel_name : '';
                                $meta         = trim( $farm_label . ( $farm_label && $parcel_label ? ' · ' : '' ) . $parcel_label );
                            ?>
                                <li>
                                    <span class="baykat-farm-timeline-dot <?php echo $is_income ? 'is-income' : 'is-expense'; ?>" aria-hidden="true"></span>
                                    <span class="baykat-farm-dashboard-list-copy">
                                        <strong><?php echo esc_html( $transaction->description ? $transaction->description : ( $is_income ? 'Revenu' : 'Dépense' ) ); ?></strong>
                                        <?php if ( $is_cancelled ) : ?>
                                            <small class="baykat-finance-cancelled-label">Vente annulée</small>
                                        <?php endif; ?>
                                        <?php if ( $meta ) : ?>
                                            <small><?php echo esc_html( $meta ); ?></small>
                                        <?php endif; ?>
                                    </span>
                                    <span class="baykat-farm-dashboard-amount<?php echo $is_income ? ' is-income' : ' is-expense'; ?>"><?php echo esc_html( number_format( $is_cancelled ? 0 : (float) $transaction->amount, 0, ',', ' ' ) ); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="baykat-farm-empty-state">Aucune transaction n'a encore été enregistrée.</p>
                    <?php endif; ?>
                </div>
                <a class="baykat-farm-dashboard-more" href="#baykat-workspace-finances" data-open-management-target="baykat-workspace-finances">Voir les finances <span aria-hidden="true">→</span></a>
            </section>
            </div>
        </template>

        <!-- ============================= CARTE DES FERMES ============================= -->
        <?php if ( $farms ) : ?>
        <section class="baykat-farm-showcase" aria-labelledby="baykat-farm-showcase-title">
            <header class="baykat-farm-showcase-heading">
                <div>
                    <span class="baykat-farm-eyebrow">Vos exploitations</span>
                    <h3 id="baykat-farm-showcase-title">Vos fermes sur la carte</h3>
                </div>
                <div class="baykat-farm-showcase-intro">
                    <a class="baykat-farm-dashboard-more" href="#baykat-workspace-farms" data-open-management-target="baykat-workspace-farms">Gérer les fermes <span aria-hidden="true">→</span></a>
                </div>
            </header>
            <?php
            $map_farms = array();
            foreach ( $farms as $farm ) {
                if ( ! isset( $farm->latitude, $farm->longitude ) ) {
                    continue;
                }

                $map_farms[] = array(
                    'id'        => (int) $farm->id,
                    'name'      => $farm->name,
                    'location'  => $farm->location,
                    'latitude'  => (float) $farm->latitude,
                    'longitude' => (float) $farm->longitude,
                );
            }
            ?>
            <div class="baykat-farm-overview-map<?php echo $map_farms ? ' has-locations' : ''; ?>" data-farm-overview-map data-farms="<?php echo esc_attr( wp_json_encode( $map_farms ) ); ?>">
                <div class="baykat-farm-overview-map-canvas" data-farm-overview-map-canvas aria-label="Carte des emplacements de vos fermes"></div>
                <?php if ( count( $map_farms ) > 1 ) : ?>
                    <div class="baykat-farm-map-toolbar">
                        <button type="button" class="baykat-farm-map-reset" data-reset-farm-map>
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M8 4H4v4m0-4 6 6m6 10h4v-4m0 4-6-6"></path>
                            </svg>
                            <span>Vue d’ensemble</span>
                        </button>
                    </div>
                <?php endif; ?>
                <p class="baykat-farm-overview-map-error" data-farm-overview-map-error hidden>La carte n’a pas pu se charger. Vérifiez votre connexion puis rechargez la page.</p>
                <div class="baykat-farm-overview-map-empty" data-farm-overview-map-empty<?php echo $map_farms ? ' hidden' : ''; ?>>
                    <span aria-hidden="true">⌖</span>
                    <strong>Aucune ferme n’est encore positionnée</strong>
                    <p>Modifiez une ferme pour rechercher son adresse et placer son épingle.</p>
                    <a href="#baykat-workspace-farms" data-open-management-target="baykat-workspace-farms">Positionner une ferme <span aria-hidden="true">→</span></a>
                </div>
            </div>
            <div class="baykat-farm-showcase-grid">
                <?php foreach ( array_slice( $farms, 0, 3 ) as $farm ) :
                    $farm_parcels = isset( $parcels_by_farm[ $farm->id ] ) ? $parcels_by_farm[ $farm->id ] : array();
                    $farm_crops   = isset( $crops_by_farm[ $farm->id ] ) ? $crops_by_farm[ $farm->id ] : array();
                    $growing      = count( array_filter( $farm_crops, function ( $crop ) { return 'growing' === $crop->status; } ) );
                ?>
                    <article class="baykat-farm-showcase-card">
                        <a class="baykat-farm-showcase-link" href="#baykat-workspace-farms" data-open-farm-modal="<?php echo esc_attr( $farm->id ); ?>" aria-label="<?php echo esc_attr( 'Voir les informations de ' . $farm->name ); ?>">
                            <div class="baykat-farm-showcase-media">
                                <?php if ( ! empty( $farm->image_id ) ) : ?>
                                    <?php echo wp_get_attachment_image( $farm->image_id, 'large', false, array( 'loading' => 'lazy', 'alt' => esc_attr( $farm->name ) ) ); ?>
                                <?php else : ?>
                                    <div class="baykat-farm-card-image-placeholder"><span>BAYKAT</span></div>
                                <?php endif; ?>
                                <div class="baykat-farm-showcase-shade" aria-hidden="true"></div>
                                <div class="baykat-farm-showcase-head">
                                    <h4><?php echo esc_html( $farm->name ); ?></h4>
                                    <p><?php echo esc_html( $farm->location ? $farm->location : 'Votre coin de terre' ); ?></p>
                                </div>
                            </div>
                            <div class="baykat-farm-showcase-stats">
                                <span>
                                    <strong><?php echo esc_html( number_format( (float) $farm->area_ha, 1, ',', ' ' ) ); ?></strong>
                                    <small>ha</small>
                                </span>
                                <span>
                                    <strong><?php echo esc_html( number_format_i18n( count( $farm_parcels ) ) ); ?></strong>
                                    <small>parcelles</small>
                                </span>
                                <span>
                                    <strong><?php echo esc_html( number_format_i18n( $growing ) ); ?></strong>
                                    <small>en culture</small>
                                </span>
                            </div>
                        </a>
                        <?php if ( ! isset( $farm->latitude, $farm->longitude ) ) : ?>
                            <a class="baykat-farm-showcase-position" href="#baykat-workspace-farms" data-open-management-target="baykat-workspace-farms">
                                <span aria-hidden="true">＋</span> Positionner cette ferme
                            </a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <template data-dashboard-workspace-template>
        <details class="baykat-farm-workspace-section" id="baykat-workspace-parcels">
            <summary>
                <span class="baykat-farm-workspace-summary">
                    <strong>Parcelles</strong>
                    <small>Surfaces, statuts et rattachement à vos fermes</small>
                </span>
                <span class="baykat-farm-workspace-count"><?php echo esc_html( number_format_i18n( count( $parcels ) ) ); ?></span>
            </summary>
            <div class="baykat-farm-workspace-content"><?php baykat_farm_render_parcelles( false ); ?></div>
        </details>

        <details class="baykat-farm-workspace-section" id="baykat-workspace-crops">
            <summary>
                <span class="baykat-farm-workspace-summary">
                    <strong>Cultures</strong>
                    <small>Plantations, variétés et calendrier des récoltes</small>
                </span>
                <span class="baykat-farm-workspace-count"><?php echo esc_html( number_format_i18n( count( $crops ) ) ); ?></span>
            </summary>
            <div class="baykat-farm-workspace-content"><?php baykat_farm_render_cultures( false ); ?></div>
        </details>

        <details class="baykat-farm-workspace-section" id="baykat-workspace-activities">
            <summary>
                <span class="baykat-farm-workspace-summary">
                    <strong>Activités</strong>
                    <small>Journal des travaux réalisés sur vos parcelles</small>
                </span>
                <span class="baykat-farm-workspace-count"><?php echo esc_html( number_format_i18n( count( $activities ) ) ); ?></span>
            </summary>
            <div class="baykat-farm-workspace-content"><?php baykat_farm_render_activites( false ); ?></div>
        </details>

        <details class="baykat-farm-workspace-section" id="baykat-workspace-finances">
            <summary>
                <span class="baykat-farm-workspace-summary">
                    <strong>Finances</strong>
                    <small>Revenus, dépenses, achats d'intrants et ventes</small>
                </span>
                <span class="baykat-farm-workspace-count"><?php echo esc_html( number_format_i18n( $all_tx_count ) ); ?></span>
            </summary>
            <div class="baykat-farm-workspace-content baykat-farm-workspace-finances">
                <section class="baykat-farm-modal-finance-overview" aria-label="Résumé général des finances de la ferme">
                    <h3>Résumé général</h3>
                    <div class="baykat-finance-cards baykat-farm-modal-finance-summary">
                        <article class="baykat-finance-card baykat-finance-card-balance">
                            <span class="baykat-finance-card-label">Solde</span>
                            <strong class="baykat-finance-card-value" data-farm-finance-total="balance"><?php echo esc_html( number_format( $transactions['balance'], 0, ',', ' ' ) ); ?> FCFA</strong>
                        </article>
                        <article class="baykat-finance-card baykat-finance-card-income">
                            <span class="baykat-finance-card-label">Revenus</span>
                            <strong class="baykat-finance-card-value" data-farm-finance-total="income"><?php echo esc_html( number_format( $transactions['total_income'], 0, ',', ' ' ) ); ?> FCFA</strong>
                        </article>
                        <article class="baykat-finance-card baykat-finance-card-expense">
                            <span class="baykat-finance-card-label">Dépenses</span>
                            <strong class="baykat-finance-card-value" data-farm-finance-total="expense"><?php echo esc_html( number_format( $transactions['total_expense'], 0, ',', ' ' ) ); ?> FCFA</strong>
                        </article>
                    </div>
                    <p>Ce résumé regroupe les revenus et dépenses généraux, les ventes et les achats d’intrants de cette ferme.</p>
                </section>
                <details class="baykat-farm-workspace-subsection" id="baykat-workspace-finance-general">
                    <summary>Autres revenus et dépenses <span>hors intrants et ventes</span></summary>
                    <?php echo baykat_finance_render_frontend_page( 'all', false, true, 'general' ); // phpcs:ignore ?>
                </details>
                <details class="baykat-farm-workspace-subsection" id="baykat-workspace-finance-input">
                    <summary>Achats et intrants <span><?php echo esc_html( number_format_i18n( count( baykat_finance_get_user_transactions( $user_id, 'input' )['transactions'] ) ) ); ?></span></summary>
                    <?php echo baykat_farm_render_finance_item_section( 'input', false ); // phpcs:ignore ?>
                </details>
                <details class="baykat-farm-workspace-subsection" id="baykat-workspace-finance-sale">
                    <summary>Ventes <span><?php echo esc_html( number_format_i18n( count( baykat_finance_get_user_transactions( $user_id, 'sale' )['transactions'] ) ) ); ?></span></summary>
                    <?php echo baykat_farm_render_finance_item_section( 'sale', false ); // phpcs:ignore ?>
                </details>
            </div>
        </details>

        <details class="baykat-farm-workspace-section" id="baykat-workspace-stocks">
            <summary>
                <span class="baykat-farm-workspace-summary">
                    <strong>Stocks</strong>
                    <small>Quantités d'intrants et coûts cumulés par parcelle</small>
                </span>
                <span class="baykat-farm-workspace-count"><?php echo esc_html( number_format_i18n( count( $stocks ) ) ); ?></span>
            </summary>
            <div class="baykat-farm-workspace-content"><?php baykat_farm_render_stocks(); ?></div>
        </details>

        <details class="baykat-farm-workspace-section" id="baykat-workspace-farms">
            <summary>
                <span class="baykat-farm-workspace-summary">
                    <strong>Mes fermes</strong>
                    <small>Gérer les informations, photos et exploitations</small>
                </span>
                <span class="baykat-farm-workspace-count"><?php echo esc_html( number_format_i18n( count( $farms ) ) ); ?></span>
            </summary>

            <section class="baykat-farm-panel baykat-farm-add-panel" aria-labelledby="baykat-farm-add-heading">
                <details class="baykat-farm-add-form">
                    <summary id="baykat-farm-add-title">
                        <span class="baykat-farm-eyebrow">Nouvelle exploitation</span>
                        <strong id="baykat-farm-add-heading">Ajouter une ferme</strong>
                        <span class="baykat-farm-add-form-icon" aria-hidden="true">+</span>
                    </summary>
                    <form class="baykat-farm-form" method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'baykat_add_farm', '_wpnonce' ); ?>
                        <input type="hidden" name="baykat_add_farm" value="1">
                        <p class="baykat-farm-add-form-description">Commencez par les informations essentielles. La localisation et la superficie sont facultatives et pourront être complétées plus tard.</p>
                        <div class="baykat-farm-field">
                            <label for="baykat_farm_name">Nom de la ferme</label>
                            <input type="text" name="farm_name" id="baykat_farm_name" maxlength="190" required>
                        </div>
                        <?php baykat_farm_render_location_picker(); ?>
                        <div class="baykat-farm-field">
                            <label for="baykat_farm_area_ha">Superficie (hectares, facultatif)</label>
                            <input type="number" name="farm_area_ha" id="baykat_farm_area_ha" min="0.001" step="0.001">
                        </div>
                        <?php baykat_farm_render_image_field( 'farm_image', 'Photo de la ferme' ); ?>
                        <button class="baykat-finance-button" type="submit">Enregistrer la ferme</button>
                    </form>
                </details>
            </section>

            <section class="baykat-farm-panel" aria-labelledby="baykat-farm-list-title">
                <header class="baykat-farm-panel-heading">
                    <span class="baykat-farm-eyebrow">Mes exploitations</span>
                    <h3 id="baykat-farm-list-title">Fermes enregistrées</h3>
                </header>
                <?php if ( $farms ) : ?>
                    <?php baykat_farm_render_record_filters( 'baykat-farm-farm-list', 'Rechercher une ferme' ); ?>
                    <div class="baykat-farm-record-grid baykat-farm-visual-grid" id="baykat-farm-farm-list">
                        <?php foreach ( $farms as $farm ) : ?>
                            <article
                                id="baykat-farm-record-<?php echo esc_attr( $farm->id ); ?>"
                                class="baykat-farm-record-card baykat-farm-visual-card"
                                data-farm-record-card
                                data-farm-id="<?php echo esc_attr( $farm->id ); ?>"
                                data-search-text="<?php echo esc_attr( $farm->name . ' ' . $farm->location . ' ' . $farm->area_ha ); ?>"
                            >
                                <?php echo baykat_farm_render_image( $farm->image_id, 'medium_large' ); // phpcs:ignore ?>
                                <div class="baykat-farm-visual-card-body">
                                    <h4><?php echo esc_html( $farm->name ); ?></h4>
                                    <p><?php echo esc_html( $farm->location ? $farm->location : 'Localisation non renseignée' ); ?></p>
                                    <?php if ( null !== $farm->area_ha ) : ?>
                                        <span><?php echo esc_html( number_format( (float) $farm->area_ha, 3, ',', ' ' ) ); ?> ha</span>
                                    <?php endif; ?>
                                    <p class="baykat-farm-member-role">Votre rôle : <?php echo esc_html( 'owner' === $farm->access_role ? 'Propriétaire' : ( 'manager' === $farm->access_role ? 'Gestionnaire' : 'Lecteur' ) ); ?></p>
                                    <button class="baykat-farm-open-record" type="button" data-open-farm-modal="<?php echo esc_attr( $farm->id ); ?>">Voir la ferme</button>
                                    <?php if ( in_array( $farm->access_role, array( 'owner', 'manager' ), true ) ) : ?>
                                        <?php baykat_farm_render_image_update_form( 'farm', $farm->id, $farm->image_id ? 'Changer la photo' : 'Ajouter une photo' ); ?>
                                    <?php endif; ?>
                                    <?php if ( in_array( $farm->access_role, array( 'owner', 'manager' ), true ) ) : ?>
                                    <details class="baykat-farm-record-actions">
                                        <summary>Modifier la ferme</summary>
                                        <form class="baykat-farm-record-form" method="post">
                                            <?php wp_nonce_field( 'baykat_update_farm', '_wpnonce' ); ?>
                                            <input type="hidden" name="baykat_update_farm_id" value="<?php echo esc_attr( $farm->id ); ?>">
                                            <div class="baykat-farm-field">
                                                <label for="baykat_farm_edit_name_<?php echo esc_attr( $farm->id ); ?>">Nom</label>
                                                <input type="text" name="farm_name" id="baykat_farm_edit_name_<?php echo esc_attr( $farm->id ); ?>" maxlength="190" value="<?php echo esc_attr( $farm->name ); ?>" required>
                                            </div>
                                            <?php baykat_farm_render_location_picker( $farm ); ?>
                                            <div class="baykat-farm-field">
                                                <label for="baykat_farm_edit_area_<?php echo esc_attr( $farm->id ); ?>">Superficie (ha)</label>
                                                <input type="number" name="farm_area_ha" id="baykat_farm_edit_area_<?php echo esc_attr( $farm->id ); ?>" min="0.001" step="0.001" value="<?php echo esc_attr( $farm->area_ha ); ?>">
                                            </div>
                                            <button class="baykat-finance-button" type="submit" name="baykat_update_farm" value="1">Enregistrer les modifications</button>
                                        </form>
                                    <?php if ( 'owner' === $farm->access_role ) : ?>
                                        <form class="baykat-farm-delete-form" method="post">
                                            <?php wp_nonce_field( 'baykat_delete_farm', '_wpnonce' ); ?>
                                            <input type="hidden" name="baykat_delete_farm_id" value="<?php echo esc_attr( $farm->id ); ?>">
                                            <button class="baykat-finance-button baykat-farm-delete-button" type="submit" name="baykat_delete_farm" value="1">Supprimer la ferme</button>
                                        </form>
                                    <?php endif; ?>
                                    </details>
                                    <?php endif; ?>
                                    <?php if ( 'owner' === $farm->access_role ) : ?>
                                        <?php $farm_members = baykat_farm_get_members( $farm->id, $user_id ); ?>
                                        <details class="baykat-farm-record-actions baykat-farm-members">
                                            <summary>Partager la ferme</summary>
                                            <form class="baykat-farm-record-form" method="post">
                                                <?php wp_nonce_field( 'baykat_share_farm', '_wpnonce' ); ?>
                                                <input type="hidden" name="farm_id" value="<?php echo esc_attr( $farm->id ); ?>">
                                                <div class="baykat-farm-field">
                                                    <label for="baykat_farm_member_email_<?php echo esc_attr( $farm->id ); ?>">Adresse e-mail d’un compte WordPress existant</label>
                                                    <input type="email" name="member_email" id="baykat_farm_member_email_<?php echo esc_attr( $farm->id ); ?>" required>
                                                    <span class="baykat-farm-field-help">Le rôle Gestionnaire peut modifier la ferme; le rôle Lecteur peut seulement consulter ses données.</span>
                                                </div>
                                                <div class="baykat-farm-field">
                                                    <label for="baykat_farm_member_role_<?php echo esc_attr( $farm->id ); ?>">Rôle</label>
                                                    <select name="member_role" id="baykat_farm_member_role_<?php echo esc_attr( $farm->id ); ?>">
                                                        <option value="manager">Gestionnaire — peut modifier</option>
                                                        <option value="reader">Lecteur — consultation uniquement</option>
                                                    </select>
                                                </div>
                                                <button class="baykat-finance-button" type="submit" name="baykat_share_farm" value="1">Accorder l’accès</button>
                                            </form>
                                            <?php if ( $farm_members ) : ?>
                                                <ul>
                                                    <?php foreach ( $farm_members as $member ) : ?>
                                                        <li>
                                                            <?php echo esc_html( $member->display_name . ' (' . $member->user_email . ') — ' . ( 'manager' === $member->role ? 'Gestionnaire' : 'Lecteur' ) ); ?>
                                                            <form class="baykat-farm-delete-form" method="post">
                                                                <?php wp_nonce_field( 'baykat_remove_farm_member', '_wpnonce' ); ?>
                                                                <input type="hidden" name="farm_id" value="<?php echo esc_attr( $farm->id ); ?>">
                                                                <input type="hidden" name="member_user_id" value="<?php echo esc_attr( $member->member_user_id ); ?>">
                                                                <button class="baykat-finance-button baykat-farm-delete-button" type="submit" name="baykat_remove_farm_member" value="1">Retirer</button>
                                                            </form>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </details>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="baykat-farm-empty-state">Aucune ferme enregistrée. Ajoutez votre première ferme ci-dessus.</p>
                <?php endif; ?>
            </section>
        </details>
        </template>

        <dialog class="baykat-farm-dashboard-modal" data-farm-dashboard-dialog aria-labelledby="baykat-farm-modal-title">
            <div class="baykat-farm-dashboard-modal-shell">
                <header class="baykat-farm-dashboard-modal-header">
                    <div>
                        <span class="baykat-farm-eyebrow">Ferme BAYKAT</span>
                        <h2 id="baykat-farm-modal-title" data-dashboard-modal-title>Statistiques &amp; gestion</h2>
                    </div>
                    <button class="baykat-farm-modal-close" type="button" data-close-dashboard-modal aria-label="Fermer la fenêtre" autofocus>×</button>
                </header>
                <div class="baykat-farm-dashboard-modal-body" data-dashboard-modal-body></div>
                <template data-dashboard-stats-template>
                    <div class="baykat-farm-stats-grid">
                        <article><span>Fermes</span><strong><?php echo esc_html( number_format_i18n( count( $farms ) ) ); ?></strong></article>
                        <article><span>Surface totale</span><strong><?php echo esc_html( number_format( $total_area, 2, ',', ' ' ) ); ?><small> ha</small></strong></article>
                        <article><span>Parcelles</span><strong><?php echo esc_html( number_format_i18n( count( $parcels ) ) ); ?></strong></article>
                        <article><span>Cultures en cours</span><strong><?php echo esc_html( number_format_i18n( count( $growing_crops ) ) ); ?></strong></article>
                        <article><span>Récoltes à venir</span><strong><?php echo esc_html( number_format_i18n( count( $upcoming_harvests ) ) ); ?></strong></article>
                        <article><span>Activités au journal</span><strong><?php echo esc_html( number_format_i18n( count( $activities ) ) ); ?></strong></article>
                        <article><span>Transactions</span><strong><?php echo esc_html( number_format_i18n( $all_tx_count ) ); ?></strong></article>
                        <article class="is-finance"><span>Solde actuel</span><strong><?php echo esc_html( number_format( $balance_value, 0, ',', ' ' ) ); ?><small> F</small></strong></article>
                    </div>
                    <?php if ( $farms ) : ?>
                        <section class="baykat-farm-stats-farms" aria-labelledby="baykat-farm-stats-farms-title">
                            <h3 id="baykat-farm-stats-farms-title">Détail par ferme</h3>
                            <ul>
                                <?php foreach ( $farms as $farm ) :
                                    $farm_parcels = isset( $parcels_by_farm[ $farm->id ] ) ? $parcels_by_farm[ $farm->id ] : array();
                                    $farm_crops   = isset( $crops_by_farm[ $farm->id ] ) ? $crops_by_farm[ $farm->id ] : array();
                                    $farm_growing = count( array_filter( $farm_crops, function ( $crop ) { return 'growing' === $crop->status; } ) );
                                ?>
                                    <li><strong><?php echo esc_html( $farm->name ); ?></strong><span><?php echo esc_html( number_format( (float) $farm->area_ha, 2, ',', ' ' ) ); ?> ha · <?php echo esc_html( number_format_i18n( count( $farm_parcels ) ) ); ?> parcelles · <?php echo esc_html( number_format_i18n( $farm_growing ) ); ?> cultures en cours</span></li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endif; ?>
                </template>
                <template data-dashboard-workspace-template-modal>
                    <section class="baykat-farm-modal-workspace" aria-label="Gestion de l’exploitation">
                        <header class="baykat-farm-modal-workspace-heading">
                            <span class="baykat-farm-eyebrow">Gestion de l’exploitation</span>
                            <h3>Gérer les données de votre ferme</h3>
                            <p>Ajoutez et retrouvez ici vos parcelles, cultures, activités, finances et stocks.</p>
                        </header>
                        <div data-dashboard-workspace-target></div>
                    </section>
                </template>
            </div>
        </dialog>
    </div>
    <?php
}