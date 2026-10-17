<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_sale = 'sale' === $category;
$item_label = $is_sale ? 'Produit vendu' : 'Intrant';
$amount_label = $is_sale ? 'Montant total de la vente' : 'Coût total';
$summary_label = $is_sale ? 'Total des ventes' : 'Coût total des intrants';
$field_suffix = sanitize_html_class( $category );
$form_title_id = 'baykat-finance-item-form-title-' . $field_suffix;
?>
<div class="baykat-finance-page baykat-finance-item-page">
    <div class="baykat-finance-header">
        <h1><?php echo esc_html( $page_title ); ?></h1>
        <p>
            <?php if ( $is_sale ) : ?>
                Enregistrez les produits vendus, leurs quantités et les montants encaissés.
            <?php elseif ( $is_sale && $sale_crops_error ) : ?>
                <div class="baykat-finance-section-heading">
                    <span class="baykat-finance-eyebrow">Revenu agricole</span>
                    <h2 id="<?php echo esc_attr( $form_title_id ); ?>">Vérification des récoltes indisponible</h2>
                    <p>La vente est désactivée pour éviter d’enregistrer une quantité incorrecte. Veuillez recharger la page ou réessayer plus tard.</p>
                </div>
            <?php elseif ( $is_sale && empty( $sale_crops ) ) : ?>
                <div class="baykat-finance-section-heading">
                    <span class="baykat-finance-eyebrow">Revenu agricole</span>
                    <h2 id="<?php echo esc_attr( $form_title_id ); ?>">Aucune récolte disponible à vendre</h2>
                    <p>Une vente doit correspondre à une culture récoltée avec une quantité et une unité enregistrées.</p>
                </div>
                <div class="baykat-farm-notice" role="status">
                    <p>Pour apparaître ici, une culture doit avoir le statut « Récoltée », une quantité récoltée supérieure à zéro et son unité (par exemple 25 kg). Les ventes déjà saisies sont déduites automatiquement de cette quantité.</p>
                    <a class="baykat-finance-button" href="<?php echo esc_url( add_query_arg( 'module', 'cultures', get_permalink() ) . '#baykat-farm-crop-form-title' ); ?>">Consulter les cultures</a>
                </div>
            <?php else : ?>
                Enregistrez les intrants achetés, leurs quantités et les coûts associés.
            <?php endif; ?>
        </p>
    </div>

    <?php if ( $message ) : ?>
        <div
            class="baykat-finance-message baykat-finance-message-<?php echo esc_attr( $message['type'] ); ?>"
            role="<?php echo 'error' === $message['type'] ? 'alert' : 'status'; ?>"
            aria-live="polite"
        >
            <?php echo esc_html( $message['message'] ); ?>
        </div>
    <?php endif; ?>

    <div class="baykat-finance-cards baykat-finance-cards-single">
        <div class="baykat-finance-card<?php echo $is_sale ? ' baykat-finance-card-income' : ' baykat-finance-card-expense'; ?>">
            <span class="baykat-finance-card-label"><?php echo esc_html( $summary_label ); ?></span>
            <strong class="baykat-finance-card-value" data-farm-finance-total="<?php echo esc_attr( $is_sale ? 'income' : 'expense' ); ?>">
                <?php echo esc_html( number_format( $is_sale ? $finance_data['total_income'] : $finance_data['total_expense'], 0, ',', ' ' ) ); ?>
                FCFA
            </strong>
        </div>
        <div class="baykat-finance-card">
            <span class="baykat-finance-card-label">Enregistrements</span>
            <strong class="baykat-finance-card-value" data-farm-finance-total="count"><?php echo esc_html( number_format_i18n( count( $finance_data['transactions'] ) ) ); ?></strong>
        </div>
    </div>

    <section class="baykat-finance-form" aria-labelledby="<?php echo esc_attr( $form_title_id ); ?>">
        <?php if ( empty( $editable_parcels ) && ! empty( $parcels ) ) : ?>
            <div class="baykat-farm-notice" role="status">
                Votre rôle actuel autorise uniquement la consultation de ces enregistrements.
            </div>
        <?php elseif ( empty( $parcels ) ) : ?>
            <div class="baykat-finance-section-heading">
                <span class="baykat-finance-eyebrow"><?php echo $is_sale ? 'Revenu agricole' : 'Dépense agricole'; ?></span>
                <h2 id="<?php echo esc_attr( $form_title_id ); ?>">Nouvel enregistrement</h2>
                <p>Le montant sera ajouté automatiquement aux totaux Finance.</p>
            </div>
            <div class="baykat-farm-notice baykat-farm-notice-error" role="status">
                <p>
                    <?php if ( $farms ) : ?>
                        Ajoutez une parcelle à votre ferme avant d’enregistrer un intrant ou une vente.
                    <?php else : ?>
                        Créez d’abord une ferme, puis une parcelle pour lui rattacher vos enregistrements.
                    <?php endif; ?>
                </p>
                <a
                    class="baykat-finance-button"
                    href="<?php echo esc_url(
                        add_query_arg( 'module', $farms ? 'parcelles' : 'vue-ensemble', get_permalink() )
                        . ( $farms ? '#baykat-farm-parcel-form-title' : '#baykat-farm-add-title' )
                    ); ?>"
                >
                    <?php echo $farms ? 'Ajouter une parcelle' : 'Créer une ferme'; ?>
                </a>
            </div>
        <?php else : ?>
        <details class="baykat-farm-add-form baykat-farm-finance-add-form">
            <summary id="<?php echo esc_attr( $form_title_id ); ?>"><span class="baykat-farm-eyebrow"><?php echo $is_sale ? 'Revenu agricole' : 'Dépense agricole'; ?></span><strong>Nouvel enregistrement</strong><span class="baykat-farm-add-form-icon" aria-hidden="true">+</span></summary>
            <p class="baykat-farm-add-form-description"><?php echo $is_sale ? 'Choisissez une récolte disponible; son produit, son unité et sa parcelle seront repris automatiquement.' : 'Décrivez l’intrant acheté, sa quantité et son coût. Le montant sera ajouté aux dépenses Finance.'; ?></p>
            <form class="baykat-finance-form-content baykat-finance-item-form" method="post">
            <?php wp_nonce_field( 'baykat_add_finance_item', '_wpnonce' ); ?>
            <input type="hidden" name="finance_item_category" value="<?php echo esc_attr( $category ); ?>">

            <?php if ( $is_sale ) : ?>
            <div class="baykat-finance-field baykat-finance-field-wide">
                <label for="baykat_crop_id_<?php echo esc_attr( $field_suffix ); ?>">Culture récoltée à vendre</label>
                <select name="crop_id" id="baykat_crop_id_<?php echo esc_attr( $field_suffix ); ?>" data-finance-sale-crop required>
                    <option value="">Choisir une récolte disponible</option>
                    <?php foreach ( $sale_crops as $crop ) : ?>
                        <option
                            value="<?php echo esc_attr( $crop->id ); ?>"
                            data-parcel-id="<?php echo esc_attr( $crop->parcel_id ); ?>"
                            data-product="<?php echo esc_attr( $crop->name ); ?>"
                            data-unit="<?php echo esc_attr( $crop->harvested_unit ); ?>"
                            data-available="<?php echo esc_attr( $crop->available_harvest_quantity ); ?>"
                            data-parcel-label="<?php echo esc_attr( $crop->farm_name . ' — ' . $crop->parcel_name ); ?>"
                            <?php disabled( '' !== $crop->harvest_sale_unavailable_reason ); ?>
                        >
                            <?php
                            echo esc_html(
                                $crop->farm_name . ' — ' . $crop->parcel_name . ' — ' . $crop->name
                                . ( $crop->harvest_sale_unavailable_reason
                                    ? ' (non vendable : ' . $crop->harvest_sale_unavailable_reason . ')'
                                    : ' (' . number_format_i18n( $crop->available_harvest_quantity, 3 ) . ' ' . $crop->harvested_unit . ' disponibles)' )
                            );
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="baykat-finance-field-help" data-sale-availability aria-live="polite">Choisissez la culture pour afficher la quantité récoltée encore disponible.</span>
            </div>
            <?php endif; ?>

            <div class="baykat-finance-field">
                <label for="baykat_item_name_<?php echo esc_attr( $field_suffix ); ?>"><?php echo esc_html( $item_label ); ?></label>
                <input
                    type="text"
                    name="item_name"
                    id="baykat_item_name_<?php echo esc_attr( $field_suffix ); ?>"
                    maxlength="190"
                    placeholder="<?php echo $is_sale ? 'Ex. : Tomates' : 'Ex. : Semences'; ?>"
                    required
                    <?php echo $is_sale ? 'readonly data-sale-product' : ''; ?>
                >
            </div>

            <div class="baykat-finance-field">
                <label for="baykat_quantity_<?php echo esc_attr( $field_suffix ); ?>">Quantité</label>
                <input
                    type="number"
                    name="quantity"
                    id="baykat_quantity_<?php echo esc_attr( $field_suffix ); ?>"
                    data-finance-quantity
                    min="0.001"
                    step="0.001"
                    inputmode="decimal"
                    placeholder="Ex. : 10"
                    required
                    <?php echo $is_sale ? 'data-sale-quantity' : ''; ?>
                >
            </div>

            <div class="baykat-finance-field">
                <label for="baykat_unit_<?php echo esc_attr( $field_suffix ); ?>">Unité</label>
                <input
                    type="text"
                    name="unit"
                    id="baykat_unit_<?php echo esc_attr( $field_suffix ); ?>"
                    maxlength="40"
                    placeholder="Ex. : kg, sac, litre, unité"
                    required
                    <?php echo $is_sale ? 'readonly data-sale-unit' : ''; ?>
                >
            </div>

            <?php if ( $is_sale ) : ?>
            <input type="hidden" name="parcel_id" data-finance-parcel-select>
            <div class="baykat-finance-field baykat-finance-field-wide">
                <span class="baykat-finance-field-label">Parcelle de la récolte</span>
                <span data-sale-parcel-label>La parcelle sera renseignée automatiquement.</span>
            </div>
            <?php else : ?>
            <div class="baykat-finance-field">
                <label for="baykat_parcel_id_<?php echo esc_attr( $field_suffix ); ?>">Parcelle de la ferme</label>
                <select name="parcel_id" id="baykat_parcel_id_<?php echo esc_attr( $field_suffix ); ?>" data-finance-parcel-select required>
                    <option value="">Choisir une parcelle</option>
                    <?php foreach ( $editable_parcels as $parcel ) : ?>
                        <option value="<?php echo esc_attr( $parcel->id ); ?>">
                            <?php echo esc_html( $parcel->farm_name . ' — ' . $parcel->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="baykat-finance-field">
                <label for="baykat_activity_id_<?php echo esc_attr( $field_suffix ); ?>">Activité associée <span>(facultatif)</span></label>
                <select name="activity_id" id="baykat_activity_id_<?php echo esc_attr( $field_suffix ); ?>" data-finance-activity-select>
                    <option value="">Aucune activité — lier seulement à la parcelle</option>
                    <?php foreach ( $editable_activities as $activity ) : ?>
                        <option value="<?php echo esc_attr( $activity->id ); ?>" data-parcel-id="<?php echo esc_attr( $activity->parcel_id ); ?>">
                            <?php echo esc_html( $activity->title . ' — ' . $activity->farm_name . ' / ' . $activity->parcel_name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="baykat-finance-field-help">Reliez cet achat ou cette vente à une activité pour calculer son résultat.</span>
            </div>

            <?php if ( ! $is_sale ) : ?>
            <div class="baykat-finance-field">
                <label for="baykat_crop_id_<?php echo esc_attr( $field_suffix ); ?>">Culture concernée <span>(facultatif)</span></label>
                <select name="crop_id" id="baykat_crop_id_<?php echo esc_attr( $field_suffix ); ?>" data-finance-crop-select>
                    <option value="">Aucune culture</option>
                    <?php foreach ( $editable_crops as $crop ) : ?>
                        <option value="<?php echo esc_attr( $crop->id ); ?>" data-parcel-id="<?php echo esc_attr( $crop->parcel_id ); ?>">
                            <?php echo esc_html( $crop->farm_name . ' — ' . $crop->parcel_name . ' — ' . $crop->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="baykat-finance-field-help">Associez l’opération à une culture pour calculer sa marge.</span>
            </div>
            <?php endif; ?>

            <div class="baykat-finance-field">
                <label for="baykat_amount_<?php echo esc_attr( $field_suffix ); ?>"><?php echo esc_html( $amount_label ); ?> (FCFA)</label>
                <input
                    type="number"
                    name="amount"
                    id="baykat_amount_<?php echo esc_attr( $field_suffix ); ?>"
                    data-finance-amount
                    min="0.01"
                    step="0.01"
                    inputmode="decimal"
                    placeholder="Ex. : 5000"
                    required
                >
            </div>

            <div class="baykat-finance-field baykat-finance-field-description">
                <label for="baykat_description_<?php echo esc_attr( $field_suffix ); ?>">Note (facultatif)</label>
                <textarea
                    name="description"
                    id="baykat_description_<?php echo esc_attr( $field_suffix ); ?>"
                    rows="3"
                    placeholder="Ajoutez un détail utile à cet enregistrement"
                ></textarea>
            </div>

            <button
                type="submit"
                name="baykat_add_finance_item"
                value="1"
                class="baykat-finance-button"
            >
                <?php echo $is_sale ? 'Enregistrer la vente' : 'Enregistrer l’intrant'; ?>
            </button>
            </form>
        </details>
        <?php endif; ?>
    </section>

    <section class="baykat-finance-transactions" aria-labelledby="baykat-finance-item-list-title">
        <div class="baykat-finance-section-heading">
            <span class="baykat-finance-eyebrow">Historique</span>
            <h2 id="baykat-finance-item-list-title"><?php echo esc_html( $is_sale ? 'Ventes enregistrées' : 'Intrants enregistrés' ); ?></h2>
        </div>

        <?php if ( ! empty( $finance_data['transactions'] ) ) : ?>
            <div class="baykat-finance-tools" role="search" aria-label="Rechercher dans les enregistrements">
                <div class="baykat-finance-tool baykat-finance-search">
                    <label for="baykat-finance-search-<?php echo esc_attr( $field_suffix ); ?>">Rechercher</label>
                    <input
                        type="search"
                        id="baykat-finance-search-<?php echo esc_attr( $field_suffix ); ?>"
                        data-finance-search
                        placeholder="Nom, quantité, montant ou date"
                        autocomplete="off"
                    >
                </div>
            </div>
        <?php endif; ?>

        <div class="baykat-finance-table-wrap">
            <table class="baykat-finance-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html( $item_label ); ?></th>
                        <th>Quantité</th>
                        <th>Parcelle</th>
                        <th><?php echo esc_html( $is_sale ? 'Montant encaissé' : 'Coût' ); ?></th>
                        <th>Note</th>
                        <th>Date</th>
                        <?php if ( $is_sale ) : ?>
                            <th>Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $finance_data['transactions'] ) ) : ?>
                        <tr class="baykat-finance-empty-row">
                            <td colspan="<?php echo $is_sale ? '7' : '6'; ?>">Aucun enregistrement pour le moment.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $finance_data['transactions'] as $transaction ) : ?>
                            <?php
                            $quantity_label = rtrim( rtrim( number_format( (float) $transaction->quantity, 3, ',', ' ' ), '0' ), ',' );
                            $search_text = implode(
                                ' ',
                                array(
                                    $transaction->item_name,
                                    $quantity_label,
                                    $transaction->unit,
                                    $transaction->amount,
                                    $transaction->farm_name,
                                    $transaction->parcel_name,
                                    isset( $transaction->activity_title ) ? $transaction->activity_title : '',
                                    isset( $transaction->crop_name ) ? $transaction->crop_name : '',
                                    $transaction->description,
                                    $transaction->created_at,
                                )
                            );
                            ?>
                            <tr
                                class="baykat-finance-transaction-row"
                                data-farm-id="<?php echo esc_attr( $transaction->farm_id ); ?>"
                                data-parcel-id="<?php echo esc_attr( $transaction->parcel_id ); ?>"
                                data-transaction-id="<?php echo esc_attr( $transaction->id ); ?>"
                                data-amount="<?php echo esc_attr( $transaction->amount ); ?>"
                                data-transaction-type="<?php echo esc_attr( $transaction->type ); ?>"
                                data-activity-id="<?php echo esc_attr( $transaction->activity_id ); ?>"
                                data-search-text="<?php echo esc_attr( $search_text ); ?>"
                            >
                                <td data-label="<?php echo esc_attr( $item_label ); ?>">
                                    <strong><?php echo esc_html( $transaction->item_name ); ?></strong>
                                    <?php if ( $is_sale && ! empty( $transaction->cancelled_at ) ) : ?>
                                        <?php $cancelled_by_user = get_userdata( (int) $transaction->cancelled_by ); ?>
                                        <small class="baykat-finance-transaction-meta baykat-finance-cancelled-label">
                                            Vente annulée le <?php echo esc_html( $transaction->cancelled_at ); ?>
                                            <?php if ( $cancelled_by_user ) : ?>
                                                par <?php echo esc_html( $cancelled_by_user->display_name ); ?>
                                            <?php endif; ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Quantité">
                                    <?php echo esc_html( $quantity_label . ' ' . $transaction->unit ); ?>
                                </td>
                                <td data-label="Parcelle">
                                    <?php echo esc_html( $transaction->parcel_name ? $transaction->farm_name . ' — ' . $transaction->parcel_name : 'Sans parcelle' ); ?>
                                    <?php if ( ! empty( $transaction->activity_title ) ) : ?>
                                        <small class="baykat-finance-transaction-meta">Activité : <?php echo esc_html( $transaction->activity_title ); ?></small>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $transaction->crop_name ) ) : ?>
                                        <small class="baykat-finance-transaction-meta">Culture : <?php echo esc_html( $transaction->crop_name ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="<?php echo esc_attr( $is_sale ? 'Montant encaissé' : 'Coût' ); ?>" class="baykat-finance-amount-cell">
                                    <?php echo esc_html( number_format( (float) $transaction->amount, 0, ',', ' ' ) ); ?> FCFA
                                    <?php if ( $is_sale && ! empty( $transaction->cancelled_at ) ) : ?>
                                        <small class="baykat-finance-transaction-meta baykat-finance-cancelled-label">Non comptabilisée</small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Note" class="baykat-finance-description-cell">
                                    <?php echo esc_html( $transaction->description ); ?>
                                </td>
                                <td data-label="Date"><?php echo esc_html( $transaction->created_at ); ?></td>
                                <?php if ( $is_sale ) : ?>
                                    <td data-label="Action">
                                        <?php if ( empty( $transaction->cancelled_at ) && baykat_farm_user_can_edit_farm( (int) $transaction->farm_id, get_current_user_id() ) ) : ?>
                                            <form class="baykat-finance-cancel-form" method="post" data-confirm-message="Annuler cette vente ? Elle restera dans l’historique, mais son montant et sa quantité ne seront plus comptabilisés.">
                                                <?php wp_nonce_field( 'baykat_cancel_sale', '_wpnonce' ); ?>
                                                <input type="hidden" name="transaction_id" value="<?php echo esc_attr( $transaction->id ); ?>">
                                                <button class="baykat-finance-cancel-button" type="submit" name="baykat_cancel_sale" value="1">Annuler la vente</button>
                                            </form>
                                        <?php elseif ( $is_sale && ! empty( $transaction->cancelled_at ) ) : ?>
                                            <span class="baykat-finance-field-help">Annulée</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="baykat-finance-no-results" hidden>
                            <td colspan="<?php echo $is_sale ? '7' : '6'; ?>">Aucun enregistrement ne correspond à votre recherche.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
