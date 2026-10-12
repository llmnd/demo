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
        return '<div class="baykat-farm-app"><p>Vous n\'avez pas l\'autorisation d\'accéder à cette application.</p></div>';
    }

    $modules = array(
        'vue-ensemble' => array(
            'label'    => 'Vue d\'ensemble',
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

    $base_url     = get_permalink();
    $current_user = wp_get_current_user();

    ob_start();
    ?>
    <main class="baykat-farm-app">
        <header class="baykat-farm-site-header">
            <button
                class="baykat-farm-mobile-menu-toggle"
                type="button"
                aria-label="Ouvrir le menu de navigation"
                aria-expanded="false"
                aria-controls="baykat-farm-site-navigation baykat-farm-account-navigation baykat-farm-module-navigation"
                data-mobile-menu-toggle
            >
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M4 7h16"></path>
                    <path d="M4 12h16"></path>
                    <path d="M4 17h16"></path>
                </svg>
            </button>
            <a class="baykat-farm-site-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Baykat — Accueil">
                <img src="<?php echo esc_url( BAYKAT_FINANCE_URL . 'image.png' ); ?>" alt="Baykat">
            </a>
            <nav class="baykat-farm-site-navigation" id="baykat-farm-site-navigation" aria-label="Navigation principale">
                <a class="baykat-farm-home-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">Accueil <span aria-hidden="true">⌄</span></a>
                <span aria-disabled="true">Infos du marché</span>
                <span aria-disabled="true">A propos de BAYKAT</span>
                <span aria-disabled="true">Nous contacter</span>
            </nav>
            <div class="baykat-farm-user">
                <span class="baykat-farm-user-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="8" r="3.25"></circle>
                        <path d="M5.75 19a6.25 6.25 0 0 1 12.5 0"></path>
                    </svg>
                </span>
                <span><?php echo esc_html( $current_user->display_name ); ?></span>
            </div>
            <div class="baykat-farm-sell-action" aria-label="Vendre sur Baykat">
                <span>Vendre sur BAYKAT !</span>
                <span class="baykat-farm-sell-plus" aria-hidden="true">+</span>
            </div>
        </header>

        <nav class="baykat-farm-account-navigation" id="baykat-farm-account-navigation" aria-label="Navigation du compte">
            <span aria-disabled="true">Ajouter nouveau</span>
            <span aria-disabled="true">Mes annonces</span>
            <span aria-disabled="true">Favoris</span>
            <span aria-disabled="true">Messages</span>
            <span aria-disabled="true">Réglages</span>
            <a href="<?php echo esc_url( $base_url ); ?>" aria-current="page">Gestion de ferme</a>
        </nav>

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

function baykat_farm_add_body_class( $classes ) {
    $post = get_queried_object();

    if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'baykat_farm' ) ) {
        $classes[] = 'baykat-farm-page';
    }

    return $classes;
}

add_filter( 'body_class', 'baykat_farm_add_body_class' );