<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Affiche l'application agricole à partir de son shortcode principal.
 */
function baykat_farm_shortcode() {
    if ( ! is_user_logged_in() ) {
        $login_url = wp_login_url( get_permalink() );

        return sprintf(
            '<div class="baykat-farm-app baykat-farm-login"><h1>Gérer ma ferme</h1><p>Vous devez être connecté pour accéder à votre espace.</p><a class="baykat-finance-button" href="%1$s">Se connecter</a></div>',
            esc_url( $login_url )
        );
    }

    if ( ! baykat_finance_user_can_access() ) {
        return '<div class="baykat-farm-app"><p>Vous n’avez pas l’autorisation d’accéder à cette application.</p></div>';
    }

    $modules = array(
        'vue-ensemble' => array(
            'label'    => 'Vue d’ensemble',
            'callback' => 'baykat_farm_render_dashboard',
        ),
        'parcelles' => array(
            'label'    => 'Parcelles',
            'callback' => 'baykat_farm_render_parcelles',
        ),
        'cultures' => array(
            'label'    => 'Cultures',
            'callback' => 'baykat_farm_render_cultures',
        ),
        'activites' => array(
            'label'    => 'Activités',
            'callback' => 'baykat_farm_render_activites',
        ),
        'finances' => array(
            'label'    => 'Finances',
            'callback' => 'baykat_farm_render_finances',
        ),
        'stocks' => array(
            'label'    => 'Stocks',
            'callback' => 'baykat_farm_render_stocks',
        ),
    );

    $requested_module = isset( $_GET['module'] ) && is_string( $_GET['module'] )
        ? sanitize_key( wp_unslash( $_GET['module'] ) )
        : 'vue-ensemble';

    $active_module = isset( $modules[ $requested_module ] )
        ? $requested_module
        : 'vue-ensemble';

    $base_url = get_permalink();

    ob_start();
    ?>
    <main class="baykat-farm-app">
        <header class="baykat-farm-header">
            <span class="baykat-farm-eyebrow">Baykat</span>
            <h1>Gérer ma ferme</h1>
            <p>Retrouvez vos outils agricoles dans un seul espace.</p>
            <p class="baykat-farm-header-actions">
                <a
                    class="baykat-finance-button"
                    href="<?php echo esc_url( add_query_arg( 'module', 'vue-ensemble', $base_url ) . '#baykat-farm-add-title' ); ?>"
                >
                    Ajouter une ferme
                </a>
            </p>
        </header>

        <?php baykat_farm_render_navigation( $modules, $active_module, $base_url ); ?>

        <div class="baykat-farm-module" id="baykat-farm-module-content">
            <?php
            $renderer = $modules[ $active_module ]['callback'];

            if ( 'baykat_farm_render_finances' === $renderer ) {
                echo $renderer(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } else {
                $renderer();
            }
            ?>
        </div>
    </main>
    <?php

    return ob_get_clean();
}

add_shortcode( 'baykat_farm', 'baykat_farm_shortcode' );
