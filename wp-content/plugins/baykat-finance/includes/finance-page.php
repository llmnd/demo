<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Charge les fichiers CSS de la page financière.
 */
function baykat_finance_enqueue_frontend_assets() {

    if ( ! is_singular() ) {
        return;
    }

    $post = get_post();

    if (
        ! $post
        || (
            ! has_shortcode( $post->post_content, 'baykat_farm' )
            && ! has_shortcode( $post->post_content, 'gerer_ma_ferme' )
            && ! has_shortcode( $post->post_content, 'baykat_finance' )
        )
    ) {
        return;
    }

    wp_enqueue_style(
        'baykat-finance',
        BAYKAT_FINANCE_URL . 'public/css/finance.css',
        array(),
        filemtime( BAYKAT_FINANCE_DIR . 'public/css/finance.css' )
    );

    wp_enqueue_script(
        'baykat-finance',
        BAYKAT_FINANCE_URL . 'public/js/finance.js',
        array(),
        filemtime( BAYKAT_FINANCE_DIR . 'public/js/finance.js' ),
        true
    );

    if (
        has_shortcode( $post->post_content, 'baykat_farm' )
        || has_shortcode( $post->post_content, 'gerer_ma_ferme' )
    ) {
        wp_enqueue_style(
            'baykat-farm',
            BAYKAT_FINANCE_URL . 'public/css/farm.css',
            array( 'baykat-finance' ),
            filemtime( BAYKAT_FINANCE_DIR . 'public/css/farm.css' )
        );

        wp_enqueue_script(
            'baykat-farm',
            BAYKAT_FINANCE_URL . 'public/js/farm.js',
            array(),
            filemtime( BAYKAT_FINANCE_DIR . 'public/js/farm.js' ),
            true
        );
    }
}

add_action(
    'wp_enqueue_scripts',
    'baykat_finance_enqueue_frontend_assets'
);
/**
 * Affiche la page de gestion financière côté utilisateur.
 */
function baykat_finance_render_frontend_page( $initial_type = 'all', $process_submission = true, $open_add_form = false, $transaction_category = null ) {

    if ( ! in_array( $initial_type, array( 'all', 'income', 'expense' ), true ) ) {
        $initial_type = 'all';
    }

    if ( ! is_user_logged_in() ) {
        $login_url = wp_login_url( get_permalink() );

        return '
            <div class="baykat-finance-login" role="region" aria-label="Connexion requise">
                <p>Vous devez être connecté pour accéder à votre gestion financière.</p>
                <a class="baykat-finance-button" href="' . esc_url( $login_url ) . '">
                    Se connecter
                </a>
            </div>
        ';
    }

    $user_id = get_current_user_id();

    // Traiter une éventuelle nouvelle transaction.
    $message = $process_submission
        ? baykat_finance_process_transaction_submission( $user_id )
        : null;

    // Récupérer les données financières.
    $finance_data = baykat_finance_get_user_transactions( $user_id, $transaction_category );
    $parcels = baykat_farm_get_user_parcels( $user_id );
    $editable_parcels = baykat_farm_filter_editable_records( $parcels );
    $farms = baykat_farm_get_user_farms( $user_id );
    $activities = baykat_farm_get_user_activities( $user_id );
    $editable_activities = baykat_farm_filter_editable_records( $activities );
    $crops = baykat_farm_get_user_crops( $user_id );
    $editable_crops = baykat_farm_filter_editable_records( $crops );

    ob_start();
    ?>

    <div class="baykat-finance-page">

        <div class="baykat-finance-header">
            <h1>Gestion financière</h1>
            <p>Suivez vos revenus, dépenses et votre activité financière depuis votre espace Baykat.</p>
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

        <div class="baykat-finance-cards">

            <div class="baykat-finance-card baykat-finance-card-balance">
                <span class="baykat-finance-card-label">Solde</span>

                <strong class="baykat-finance-card-value" data-farm-finance-total="balance">
                    <?php
                    echo esc_html(
                        number_format(
                            $finance_data['balance'],
                            0,
                            ',',
                            ' '
                        )
                    );
                    ?>
                    FCFA
                </strong>
            </div>

            <div class="baykat-finance-card baykat-finance-card-income">
                <span class="baykat-finance-card-label">Revenus</span>

                <strong class="baykat-finance-card-value" data-farm-finance-total="income">
                    <?php
                    echo esc_html(
                        number_format(
                            $finance_data['total_income'],
                            0,
                            ',',
                            ' '
                        )
                    );
                    ?>
                    FCFA
                </strong>
            </div>

            <div class="baykat-finance-card baykat-finance-card-expense">
                <span class="baykat-finance-card-label">Dépenses</span>

                <strong class="baykat-finance-card-value" data-farm-finance-total="expense">
                    <?php
                    echo esc_html(
                        number_format(
                            $finance_data['total_expense'],
                            0,
                            ',',
                            ' '
                        )
                    );
                    ?>
                    FCFA
                </strong>
            </div>

        </div>

        <div class="baykat-finance-form">

            <?php if ( empty( $editable_parcels ) && ! empty( $parcels ) ) : ?>
                <div class="baykat-farm-notice" role="status">
                    Votre rôle actuel autorise uniquement la consultation des finances partagées avec vous.
                </div>
            <?php elseif ( empty( $parcels ) ) : ?>
                <div class="baykat-finance-section-heading">
                    <span class="baykat-finance-eyebrow">Votre activité</span>
                    <h2>Ajouter une transaction</h2>
                    <p>Enregistrez un revenu ou une dépense pour garder vos comptes à jour.</p>
                </div>
                <div class="baykat-farm-notice baykat-farm-notice-error" role="status">
                    <p>
                        <?php if ( $farms ) : ?>
                            Ajoutez une parcelle à votre ferme avant d’enregistrer une transaction.
                        <?php else : ?>
                            Créez d’abord une ferme, puis une parcelle pour lui rattacher vos transactions.
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
            <details class="baykat-farm-add-form baykat-farm-finance-add-form"<?php echo $open_add_form ? ' open' : ''; ?>>
                <summary id="baykat-finance-add-transaction-title"><span class="baykat-farm-eyebrow">Votre activité</span><strong>Ajouter une transaction</strong><span class="baykat-farm-add-form-icon" aria-hidden="true">+</span></summary>
                <p class="baykat-farm-add-form-description">Enregistrez un revenu ou une dépense pour garder vos comptes à jour.</p>
                <form class="baykat-finance-form-content" method="post">

                <?php
                wp_nonce_field(
                    'baykat_add_transaction',
                    '_wpnonce'
                );
                ?>
                <p class="baykat-farm-add-form-description">Choisissez le type et le montant, puis rattachez l’opération à la parcelle concernée. La culture et l’activité sont facultatives.</p>

                <div class="baykat-finance-field">

                    <label for="baykat_type">
                        Type
                    </label>

                    <select
                        name="type"
                        id="baykat_type"
                        required
                    >
                        <option value="income">
                            Revenu
                        </option>

                        <option value="expense">
                            Dépense
                        </option>
                    </select>
                    <span class="baykat-finance-field-help">Un revenu augmente vos entrées; une dépense enregistre une sortie d’argent.</span>

                </div>

                <div class="baykat-finance-field">

                    <label for="baykat_amount">
                        Montant
                    </label>

                    <input
                        type="number"
                        name="amount"
                        id="baykat_amount"
                        data-finance-amount
                        min="0.01"
                        step="0.01"
                        inputmode="decimal"
                        placeholder="Ex. : 500"
                        aria-describedby="baykat-finance-amount-help"
                        required
                    >
                    <span class="baykat-finance-field-help" id="baykat-finance-amount-help">Le montant doit être supérieur à zéro.</span>

                </div>

                <div class="baykat-finance-field" data-expense-category-field>
                    <label for="baykat_expense_category">Nature de la dépense</label>
                    <select name="expense_category" id="baykat_expense_category">
                        <?php foreach ( baykat_finance_expense_categories() as $slug => $label ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="baykat-finance-field">
                    <label for="baykat_parcel_id">Parcelle de la ferme</label>
                    <select name="parcel_id" id="baykat_parcel_id" data-finance-parcel-select required>
                        <option value="">Choisir une parcelle</option>
                        <?php foreach ( $editable_parcels as $parcel ) : ?>
                            <option value="<?php echo esc_attr( $parcel->id ); ?>">
                                <?php echo esc_html( $parcel->farm_name . ' — ' . $parcel->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="baykat-finance-field-help">Les activités et cultures proposées ensuite correspondront à cette parcelle.</span>
                </div>

                <div class="baykat-finance-field">
                    <label for="baykat_activity_id">Activité associée <span>(facultatif)</span></label>
                    <select name="activity_id" id="baykat_activity_id" data-finance-activity-select>
                        <option value="">Aucune activité — lier seulement à la parcelle</option>
                        <?php foreach ( $editable_activities as $activity ) : ?>
                            <option value="<?php echo esc_attr( $activity->id ); ?>" data-parcel-id="<?php echo esc_attr( $activity->parcel_id ); ?>">
                                <?php echo esc_html( $activity->title . ' — ' . $activity->farm_name . ' / ' . $activity->parcel_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="baykat-finance-field-help">Associez une opération pour suivre son coût et sa rentabilité.</span>
                </div>

                <div class="baykat-finance-field">
                    <label for="baykat_crop_id">Culture concernée <span>(facultatif)</span></label>
                    <select name="crop_id" id="baykat_crop_id" data-finance-crop-select>
                        <option value="">Aucune culture</option>
                        <?php foreach ( $editable_crops as $crop ) : ?>
                            <option value="<?php echo esc_attr( $crop->id ); ?>" data-parcel-id="<?php echo esc_attr( $crop->parcel_id ); ?>">
                                <?php echo esc_html( $crop->farm_name . ' — ' . $crop->parcel_name . ' — ' . $crop->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="baykat-finance-field-help">Associez une opération à une culture pour calculer sa marge.</span>
                </div>

                <div class="baykat-finance-field baykat-finance-field-description">

                    <label for="baykat_description">
                        Objet de l’opération
                    </label>

                    <textarea
                        name="description"
                        id="baykat_description"
                        rows="3"
                        placeholder="Ex. : Paiement de la main-d’œuvre"
                    ></textarea>

                </div>

                <button
                    type="submit"
                    name="baykat_add_transaction"
                    value="1"
                    class="baykat-finance-button"
                >
                    Enregistrer la transaction
                </button>

                </form>
            </details>
            <?php endif; ?>

        </div>

        <div class="baykat-finance-transactions">

            <div class="baykat-finance-section-heading baykat-finance-transactions-heading">
                <div>
                    <span class="baykat-finance-eyebrow">Historique</span>
                    <h2>Transactions</h2>
                    <p>Retrouvez et filtrez les mouvements enregistrés.</p>
                </div>
            </div>

            <div class="baykat-finance-tools" role="search" aria-label="Rechercher et filtrer les transactions">
                <div class="baykat-finance-tool baykat-finance-search">
                    <label for="baykat-finance-search">Rechercher</label>
                    <input
                        type="search"
                        id="baykat-finance-search"
                        data-finance-search
                        placeholder="Description, montant ou date"
                        autocomplete="off"
                    >
                </div>
                <div class="baykat-finance-tool baykat-finance-filter">
                    <label for="baykat-finance-filter">Type de transaction</label>
                    <select id="baykat-finance-filter">
                        <option value="all" <?php selected( $initial_type, 'all' ); ?>>Tous les types</option>
                        <option value="income" <?php selected( $initial_type, 'income' ); ?>>Revenus</option>
                        <option value="expense" <?php selected( $initial_type, 'expense' ); ?>>Dépenses</option>
                    </select>
                </div>
            </div>

            <div class="baykat-finance-table-wrap">
                <table class="baykat-finance-table">

                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Description</th>
                            <th>Parcelle</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ( empty( $finance_data['transactions'] ) ) : ?>
                            <tr class="baykat-finance-empty-row">
                                <td colspan="5">Aucune transaction pour le moment.</td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $finance_data['transactions'] as $transaction ) : ?>

                                <?php
                                $transaction_type_label = 'income' === $transaction->type ? 'Revenu' : 'Dépense';
                                $transaction_amount_label = number_format(
                                    (float) $transaction->amount,
                                    0,
                                    ',',
                                    ' '
                                );
                                $transaction_search_text = implode(
                                    ' ',
                                    array(
                                        $transaction_type_label,
                                        $transaction->item_name,
                                        isset( $transaction->expense_category ) ? baykat_finance_expense_category_label( $transaction->expense_category ) : '',
                                        isset( $transaction->activity_title ) ? $transaction->activity_title : '',
                                        isset( $transaction->crop_name ) ? $transaction->crop_name : '',
                                        $transaction->amount,
                                        $transaction_amount_label,
                                        $transaction->description,
                                        $transaction->farm_name,
                                        $transaction->parcel_name,
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
                                    data-expense-category="<?php echo esc_attr( $transaction->expense_category ); ?>"
                                    data-search-text="<?php echo esc_attr( $transaction_search_text ); ?>"
                                >

                                <td data-label="Type">
                                    <span class="baykat-finance-type baykat-finance-type-<?php echo esc_attr( $transaction->type ); ?>">
                                        <?php echo esc_html( $transaction_type_label ); ?>
                                    </span>
                                </td>

                                <td data-label="Montant" class="baykat-finance-amount-cell">
                                    <?php echo esc_html( $transaction_amount_label ); ?>
                                    FCFA
                                </td>

                                <td data-label="Description" class="baykat-finance-description-cell">
                                    <?php
                                    echo esc_html( $transaction->item_name ? $transaction->item_name : $transaction->description );
                                    if ( 'expense' === $transaction->type && 'general' !== $transaction->expense_category ) :
                                    ?>
                                        <small class="baykat-finance-transaction-meta"><?php echo esc_html( baykat_finance_expense_category_label( $transaction->expense_category ) ); ?></small>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $transaction->activity_title ) ) : ?>
                                        <small class="baykat-finance-transaction-meta">Activité : <?php echo esc_html( $transaction->activity_title ); ?></small>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $transaction->crop_name ) ) : ?>
                                        <small class="baykat-finance-transaction-meta">Culture : <?php echo esc_html( $transaction->crop_name ); ?></small>
                                    <?php endif; ?>
                                    <?php if ( $transaction->item_name && $transaction->description ) : ?>
                                        <small class="baykat-finance-transaction-meta"><?php echo esc_html( $transaction->description ); ?></small>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $transaction->cancelled_at ) ) : ?>
                                        <small class="baykat-finance-transaction-meta baykat-finance-cancelled-label">Vente annulée le <?php echo esc_html( $transaction->cancelled_at ); ?></small>
                                    <?php endif; ?>
                                </td>

                                <td data-label="Parcelle">
                                    <?php
                                    echo esc_html(
                                        $transaction->parcel_name
                                            ? $transaction->farm_name . ' — ' . $transaction->parcel_name
                                            : 'Finances générales'
                                    );
                                    ?>
                                </td>

                                <td data-label="Date">
                                    <?php
                                    echo esc_html(
                                        $transaction->created_at
                                    );
                                    ?>
                                </td>

                            </tr>

                            <?php endforeach; ?>
                        <?php endif; ?>

                        <tr class="baykat-finance-no-results" hidden>
                            <td colspan="5">Aucune transaction ne correspond à votre recherche.</td>
                        </tr>
                    </tbody>

                </table>
            </div>

        </div>

    </div>

    <?php

    return ob_get_clean();
}


/**
 * Shortcode :
 *
 * [baykat_finance]
 */
function baykat_finance_shortcode() {

    return baykat_finance_render_frontend_page();
}

add_shortcode(
    'baykat_finance',
    'baykat_finance_shortcode'
);