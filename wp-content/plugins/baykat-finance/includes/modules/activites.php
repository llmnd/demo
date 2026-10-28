<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baykat_farm_render_activites( $process_actions = true ) {
    $user_id = get_current_user_id();
    $message = null;

    if ( $process_actions ) {
        $message = baykat_farm_process_archive_submission( $user_id );
        if ( ! $message ) {
            $message = baykat_farm_process_record_action( $user_id, 'activity' );
        }
        if ( ! $message ) {
            $message = baykat_farm_process_activity_submission( $user_id );
        }
        if ( ! $message ) {
            $message = baykat_farm_process_image_update( $user_id, 'activity' );
        }
        if ( ! $message ) {
            $message = baykat_finance_process_transaction_submission( $user_id );
        }
    }
    $parcels = baykat_farm_get_user_parcels( $user_id );
    $editable_parcels = baykat_farm_filter_editable_records( $parcels );
    $activities = baykat_farm_get_user_activities( $user_id );
    $activity_finances = baykat_finance_get_activity_transactions( $user_id );
    $campaigns = baykat_farm_campaign_get_user_campaigns( $user_id );
    $campaigns_error = is_wp_error( $campaigns );
    if ( $campaigns_error ) {
        $campaigns = array();
    }
    $editable_campaigns = array_values(
        array_filter(
            $campaigns,
            function ( $campaign ) use ( $user_id ) {
                return 'real' === $campaign->campaign_type
                    && baykat_farm_user_can_edit_farm( (int) $campaign->farm_id, $user_id );
            }
        )
    );
    $activity_types = array(
        'sowing'      => 'Semis',
        'watering'    => 'Arrosage',
        'fertilizing' => 'Fertilisation',
        'treatment'   => 'Traitement',
        'harvest'     => 'Récolte',
        'other'       => 'Autre',
    );
    ?>
    <div class="baykat-farm-content">
        <header class="baykat-farm-section-heading">
            <span class="baykat-farm-eyebrow">Journal agricole</span>
            <h2>Activités</h2>
            <p>Consignez les opérations réalisées sur chacune de vos parcelles.</p>
        </header>
        <?php if ( $message ) : ?>
            <div class="baykat-farm-notice baykat-farm-notice-<?php echo esc_attr( $message['type'] ); ?>" role="<?php echo 'error' === $message['type'] ? 'alert' : 'status'; ?>"><?php echo esc_html( $message['message'] ); ?></div>
        <?php endif; ?>
        <?php if ( $campaigns_error ) : ?>
            <div class="baykat-farm-notice baykat-farm-notice-error" role="alert">Impossible de charger les campagnes. Les activités ne pourront pas être rattachées à une campagne pour le moment.</div>
        <?php endif; ?>

        <?php if ( $editable_parcels ) : ?>
            <section class="baykat-farm-panel baykat-farm-add-panel" aria-labelledby="baykat-farm-activity-form-title">
                <details class="baykat-farm-add-form">
                    <summary id="baykat-farm-activity-form-title"><span class="baykat-farm-eyebrow">Nouvelle opération</span><strong>Ajouter une activité</strong><span class="baykat-farm-add-form-icon" aria-hidden="true">+</span></summary>
                    <form class="baykat-farm-form baykat-farm-activity-form" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'baykat_add_activity', '_wpnonce' ); ?>
                    <p class="baykat-farm-add-form-description">Choisissez la parcelle concernée, décrivez l’opération et datez-la. La dépense associée est facultative.</p>
                    <div class="baykat-farm-field baykat-farm-field-wide">
                        <label for="baykat_activity_parcel">Parcelle</label>
                        <select name="parcel_id" id="baykat_activity_parcel" required>
                            <option value="">Choisir une parcelle</option>
                            <?php foreach ( $editable_parcels as $parcel ) : ?>
                                <option value="<?php echo esc_attr( $parcel->id ); ?>"><?php echo esc_html( $parcel->farm_name . ' — ' . $parcel->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="baykat-farm-field baykat-farm-field-wide">
                        <label for="baykat_activity_campaign">Campagne réelle</label>
                        <select name="campaign_id" id="baykat_activity_campaign" data-activity-campaign-select required>
                            <option value="">Aucune campagne</option>
                            <?php foreach ( $editable_campaigns as $campaign ) : ?>
                                <option value="<?php echo esc_attr( $campaign->id ); ?>" data-parcel-id="<?php echo esc_attr( $campaign->parcel_id ); ?>">
                                    <?php echo esc_html( $campaign->crop_name . ' — ' . $campaign->farm_name . ' / ' . $campaign->parcel_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_activity_type">Type d’activité</label>
                        <select name="activity_type" id="baykat_activity_type" required>
                            <?php foreach ( $activity_types as $slug => $label ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_activity_title">Titre</label>
                        <input type="text" name="activity_title" id="baykat_activity_title" maxlength="190" placeholder="Ex. : Arrosage de la matinée" required>
                    </div>
                    <div class="baykat-farm-field">
                        <label for="baykat_activity_date">Date</label>
                        <input type="date" name="activity_date" id="baykat_activity_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
                    </div>
                    <div class="baykat-farm-field baykat-farm-field-wide">
                        <label for="baykat_activity_notes">Notes (facultatif)</label>
                        <textarea name="activity_notes" id="baykat_activity_notes" rows="3"></textarea>
                    </div>
                    <div class="baykat-farm-activity-finance-entry baykat-farm-field-wide">
                        <div>
                            <strong>Dépense de l’activité</strong>
                            <p>Enregistrez éventuellement une dépense liée à cette activité. Les revenus se saisissent dans les finances et se rattachent à la campagne.</p>
                        </div>
                        <div class="baykat-farm-field">
                            <label for="baykat_activity_finance_amount">Montant (FCFA)</label>
                            <input type="number" name="activity_finance_amount" id="baykat_activity_finance_amount" min="0.01" step="0.01" inputmode="decimal" placeholder="Laisser vide si aucune opération">
                        </div>
                        <div class="baykat-farm-field" data-activity-finance-category-field>
                            <label for="baykat_activity_finance_category">Nature de la dépense</label>
                            <select name="activity_finance_category" id="baykat_activity_finance_category">
                                <?php foreach ( baykat_finance_expense_categories() as $slug => $label ) : ?>
                                    <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( 'labor', $slug ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="baykat-farm-field baykat-farm-field-wide">
                            <label for="baykat_activity_finance_note">Détail financier (facultatif)</label>
                            <input type="text" name="activity_finance_note" id="baykat_activity_finance_note" maxlength="190" placeholder="Ex. : Paiement de la main-d’œuvre">
                        </div>
                    </div>
                    <?php baykat_farm_render_image_field( 'activity_image', 'Photo de l’activité' ); ?>
                    <button class="baykat-finance-button" type="submit" name="baykat_add_activity" value="1">Enregistrer l’activité</button>
                    </form>
                </details>
            </section>
        <?php elseif ( ! $parcels ) : ?>
            <section class="baykat-farm-panel baykat-farm-empty-state"><p>Créez d’abord une ferme et une parcelle pour enregistrer une activité.</p></section>
        <?php else : ?>
            <p class="baykat-farm-empty-state">Votre rôle actuel autorise uniquement la consultation des activités partagées avec vous.</p>
        <?php endif; ?>

        <section class="baykat-farm-panel" aria-labelledby="baykat-farm-activity-list-title">
            <header class="baykat-farm-panel-heading"><span class="baykat-farm-eyebrow">Journal</span><h3 id="baykat-farm-activity-list-title">Activités enregistrées</h3></header>
            <?php if ( $activities ) : ?>
                <?php baykat_farm_render_record_filters( 'baykat-farm-activity-list', 'Rechercher une activité', $activity_types, 'Filtrer par activité' ); ?>
                <div class="baykat-farm-record-grid baykat-farm-visual-grid" id="baykat-farm-activity-list">
                    <?php foreach ( $activities as $activity ) : ?>
                        <article
                            class="baykat-farm-record-card baykat-farm-visual-card baykat-farm-activity-card"
                            data-farm-record-card
                            data-activity-id="<?php echo esc_attr( $activity->id ); ?>"
                            data-farm-id="<?php echo esc_attr( $activity->farm_id ); ?>"
                            data-parcel-id="<?php echo esc_attr( $activity->parcel_id ); ?>"
                            data-search-text="<?php echo esc_attr( $activity->title . ' ' . $activity->activity_date . ' ' . $activity->farm_name . ' ' . $activity->parcel_name . ' ' . ( isset( $activity_types[ $activity->activity_type ] ) ? $activity_types[ $activity->activity_type ] : $activity->activity_type ) . ' ' . $activity->campaign_crop_name . ' ' . $activity->notes ); ?>"
                            data-farm-record-filter-value="<?php echo esc_attr( $activity->activity_type ); ?>"
                        >
                            <?php echo baykat_farm_render_image( $activity->image_id, 'medium_large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <div class="baykat-farm-visual-card-body">
                                <span class="baykat-farm-eyebrow"><?php echo esc_html( $activity->activity_date ); ?></span>
                                <h4><?php echo esc_html( $activity->title ); ?></h4>
                                <div class="baykat-farm-card-meta">
                                    <span class="baykat-farm-status"><?php echo esc_html( isset( $activity_types[ $activity->activity_type ] ) ? $activity_types[ $activity->activity_type ] : $activity->activity_type ); ?></span>
                                    <span><?php echo esc_html( $activity->farm_name . ' — ' . $activity->parcel_name ); ?></span>
                                    <?php if ( ! empty( $activity->campaign_crop_name ) ) : ?>
                                        <span>Campagne : <?php echo esc_html( $activity->campaign_crop_name ); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ( $activity->notes ) : ?>
                                    <p class="baykat-farm-card-dates"><?php echo esc_html( $activity->notes ); ?></p>
                                <?php endif; ?>
                                <?php
                                $activity_finance = isset( $activity_finances[ (int) $activity->id ] )
                                    ? $activity_finances[ (int) $activity->id ]
                                    : array( 'transactions' => array(), 'income' => 0, 'expense' => 0 );
                                $activity_profit = $activity_finance['income'] - $activity_finance['expense'];
                                ?>
                                <details class="baykat-farm-activity-finances">
                                    <summary>
                                        <span>Finances et rentabilité</span>
                                        <strong class="<?php echo $activity_profit < 0 ? 'is-negative' : ( $activity_profit > 0 ? 'is-positive' : '' ); ?>">
                                            <?php echo esc_html( number_format( $activity_profit, 0, ',', ' ' ) ); ?> FCFA
                                        </strong>
                                    </summary>
                                    <div class="baykat-farm-activity-finances-content">
                                        <div class="baykat-farm-activity-finance-totals">
                                            <span><?php echo esc_html( number_format_i18n( count( $activity_finance['transactions'] ) ) ); ?> opérations</span>
                                            <span>Revenus : <?php echo esc_html( number_format( $activity_finance['income'], 0, ',', ' ' ) ); ?> FCFA</span>
                                            <span>Dépenses : <?php echo esc_html( number_format( $activity_finance['expense'], 0, ',', ' ' ) ); ?> FCFA</span>
                                        </div>
                                        <?php if ( $activity_finance['transactions'] ) : ?>
                                            <ul class="baykat-farm-activity-finance-list">
                                                <?php foreach ( $activity_finance['transactions'] as $finance ) : ?>
                                                    <li>
                                                        <span class="baykat-farm-activity-finance-indicator <?php echo 'income' === $finance->type ? 'is-income' : 'is-expense'; ?>" aria-hidden="true"></span>
                                                        <span class="baykat-farm-activity-finance-copy">
                                                            <strong><?php echo esc_html( $finance->item_name ? $finance->item_name : ( $finance->description ? $finance->description : ( 'income' === $finance->type ? 'Revenu' : 'Dépense' ) ) ); ?></strong>
                                                            <small>
                                                                <?php
                                                                echo esc_html(
                                                                    'income' === $finance->type
                                                                        ? 'Revenu'
                                                                        : baykat_finance_expense_category_label( $finance->expense_category )
                                                                );
                                                                ?>
                                                                · Opération : <?php echo esc_html( ! empty( $finance->operation_date ) ? $finance->operation_date : 'date non renseignée' ); ?>
                                                                · Saisie : <?php echo esc_html( mysql2date( 'j M Y · H:i', $finance->created_at ) ); ?>
                                                            </small>
                                                            <?php if ( $finance->description && $finance->item_name ) : ?>
                                                                <small class="baykat-farm-activity-finance-note"><?php echo esc_html( $finance->description ); ?></small>
                                                            <?php endif; ?>
                                                            <?php if ( ! empty( $finance->cancelled_at ) ) : ?>
                                                                <small class="baykat-finance-cancelled-label">Vente annulée le <?php echo esc_html( $finance->cancelled_at ); ?></small>
                                                            <?php endif; ?>
                                                        </span>
                                                        <strong class="baykat-farm-activity-finance-amount <?php echo 'income' === $finance->type ? 'is-income' : 'is-expense'; ?>">
                                                            <?php echo 'income' === $finance->type ? '+' : '−'; ?><?php echo esc_html( number_format( (float) $finance->amount, 0, ',', ' ' ) ); ?> FCFA
                                                        </strong>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else : ?>
                                            <p class="baykat-farm-empty-state">Aucune opération financière n’est encore liée à cette activité.</p>
                                        <?php endif; ?>
                                        <?php if ( in_array( $activity->access_role, array( 'owner', 'manager' ), true ) ) : ?>
                                        <form class="baykat-farm-activity-finance-form" method="post">
                                            <?php wp_nonce_field( 'baykat_add_activity_finance', '_wpnonce' ); ?>
                                            <input type="hidden" name="activity_id" value="<?php echo esc_attr( $activity->id ); ?>">
                                            <div class="baykat-farm-field">
                                                <label for="baykat_activity_finance_category_<?php echo esc_attr( $activity->id ); ?>">Nature</label>
                                                <select name="expense_category" id="baykat_activity_finance_category_<?php echo esc_attr( $activity->id ); ?>">
                                                    <?php foreach ( baykat_finance_expense_categories() as $slug => $label ) : ?>
                                                        <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="baykat-farm-field">
                                                <label for="baykat_activity_finance_name_<?php echo esc_attr( $activity->id ); ?>">Opération</label>
                                                <input type="text" name="item_name" id="baykat_activity_finance_name_<?php echo esc_attr( $activity->id ); ?>" maxlength="190" placeholder="Ex. : Paiement des ouvriers" required>
                                            </div>
                                            <div class="baykat-farm-field">
                                                <label for="baykat_activity_finance_amount_<?php echo esc_attr( $activity->id ); ?>">Montant (FCFA)</label>
                                                <input type="number" name="amount" id="baykat_activity_finance_amount_<?php echo esc_attr( $activity->id ); ?>" min="0.01" step="0.01" inputmode="decimal" required>
                                            </div>
                                            <div class="baykat-farm-field">
                                                <label for="baykat_activity_operation_date_<?php echo esc_attr( $activity->id ); ?>">Date réelle de l’opération</label>
                                                <input type="date" name="operation_date" id="baykat_activity_operation_date_<?php echo esc_attr( $activity->id ); ?>" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
                                            </div>
                                            <div class="baykat-farm-field baykat-farm-field-wide">
                                                <label for="baykat_activity_finance_note_<?php echo esc_attr( $activity->id ); ?>">Note (facultatif)</label>
                                                <textarea name="description" id="baykat_activity_finance_note_<?php echo esc_attr( $activity->id ); ?>" rows="2" placeholder="Détail ou justificatif à retrouver plus tard"></textarea>
                                            </div>
                                            <button class="baykat-finance-button" type="submit" name="baykat_add_activity_finance" value="1">Ajouter à cette activité</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </details>
                                <?php if ( in_array( $activity->access_role, array( 'owner', 'manager' ), true ) ) : ?>
                                <?php baykat_farm_render_image_update_form( 'activity', $activity->id, $activity->image_id ? 'Changer la photo' : 'Ajouter une photo' ); ?>
                                <details class="baykat-farm-record-actions">
                                    <summary>Modifier l’activité</summary>
                                    <form class="baykat-farm-record-form" method="post">
                                        <?php wp_nonce_field( 'baykat_update_activity', '_wpnonce' ); ?>
                                        <input type="hidden" name="baykat_update_activity_id" value="<?php echo esc_attr( $activity->id ); ?>">
                                        <div class="baykat-farm-field">
                                            <label for="baykat_activity_edit_parcel_<?php echo esc_attr( $activity->id ); ?>">Parcelle</label>
                                            <select name="parcel_id" id="baykat_activity_edit_parcel_<?php echo esc_attr( $activity->id ); ?>" required>
                                                <?php foreach ( $editable_parcels as $parcel ) : ?>
                                                    <option value="<?php echo esc_attr( $parcel->id ); ?>" <?php selected( (int) $activity->parcel_id, (int) $parcel->id ); ?>><?php echo esc_html( $parcel->farm_name . ' — ' . $parcel->name ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_activity_edit_campaign_<?php echo esc_attr( $activity->id ); ?>">Campagne réelle</label>
                                            <select name="campaign_id" id="baykat_activity_edit_campaign_<?php echo esc_attr( $activity->id ); ?>" data-activity-campaign-select required>
                                                <option value="">Aucune campagne</option>
                                                <?php foreach ( $editable_campaigns as $campaign ) : ?>
                                                    <option value="<?php echo esc_attr( $campaign->id ); ?>" data-parcel-id="<?php echo esc_attr( $campaign->parcel_id ); ?>" <?php selected( (int) $activity->campaign_id, (int) $campaign->id ); ?>>
                                                        <?php echo esc_html( $campaign->crop_name . ' — ' . $campaign->farm_name . ' / ' . $campaign->parcel_name ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_activity_edit_type_<?php echo esc_attr( $activity->id ); ?>">Type d’activité</label>
                                            <select name="activity_type" id="baykat_activity_edit_type_<?php echo esc_attr( $activity->id ); ?>" required>
                                                <?php foreach ( $activity_types as $slug => $label ) : ?>
                                                    <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $activity->activity_type, $slug ); ?>><?php echo esc_html( $label ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_activity_edit_title_<?php echo esc_attr( $activity->id ); ?>">Titre</label>
                                            <input type="text" name="activity_title" id="baykat_activity_edit_title_<?php echo esc_attr( $activity->id ); ?>" maxlength="190" value="<?php echo esc_attr( $activity->title ); ?>" required>
                                        </div>
                                        <div class="baykat-farm-field">
                                            <label for="baykat_activity_edit_date_<?php echo esc_attr( $activity->id ); ?>">Date</label>
                                            <input type="date" name="activity_date" id="baykat_activity_edit_date_<?php echo esc_attr( $activity->id ); ?>" value="<?php echo esc_attr( $activity->activity_date ); ?>" required>
                                        </div>
                                        <div class="baykat-farm-field baykat-farm-field-wide">
                                            <label for="baykat_activity_edit_notes_<?php echo esc_attr( $activity->id ); ?>">Notes</label>
                                            <textarea name="activity_notes" id="baykat_activity_edit_notes_<?php echo esc_attr( $activity->id ); ?>" rows="3"><?php echo esc_textarea( $activity->notes ); ?></textarea>
                                        </div>
                                        <button class="baykat-finance-button" type="submit" name="baykat_update_activity" value="1">Enregistrer les modifications</button>
                                    </form>
                                    <form class="baykat-farm-delete-form" method="post">
                                        <?php wp_nonce_field( 'baykat_delete_activity', '_wpnonce' ); ?>
                                        <input type="hidden" name="baykat_delete_activity_id" value="<?php echo esc_attr( $activity->id ); ?>">
                                        <button class="baykat-finance-button baykat-farm-delete-button" type="submit" name="baykat_delete_activity" value="1">Supprimer l’activité</button>
                                    </form>
                                </details>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="baykat-farm-empty-state">Aucune activité enregistrée pour le moment.</p>
            <?php endif; ?>
        </section>
    </div>
    <?php
}
