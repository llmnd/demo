<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * URL de la page qui héberge l'espace agricole.
 */
function baykat_farm_get_application_url() {
    static $application_url = null;

    if ( null !== $application_url ) {
        return $application_url;
    }

    $application_url = '';
    $pages = get_posts(
        array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        )
    );

    foreach ( $pages as $page_id ) {
        $page = get_post( $page_id );

        if ( $page instanceof WP_Post && has_shortcode( $page->post_content, 'baykat_farm' ) ) {
            $application_url = get_permalink( $page_id );
            break;
        }
    }

    return $application_url;
}

/**
 * Charge les styles des pages publiques et de connexion Baykat.
 */
function baykat_farm_enqueue_public_page_styles() {
    $is_login_page = isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'];
    $is_public_homepage = ! $is_login_page && is_front_page() && ! is_user_logged_in();

    if ( $is_public_homepage || $is_login_page ) {
        wp_enqueue_style(
            'baykat-farm-public-pages',
            BAYKAT_FINANCE_URL . 'public/css/public-pages.css',
            array(),
            filemtime( BAYKAT_FINANCE_DIR . 'public/css/public-pages.css' )
        );
        wp_add_inline_style(
            'baykat-farm-public-pages',
            ':root{--baykat-public-logo:url("' . esc_url( BAYKAT_FINANCE_URL . 'image.png' ) . '");}'
        );
    }
}
add_action( 'wp_enqueue_scripts', 'baykat_farm_enqueue_public_page_styles' );
add_action( 'login_enqueue_scripts', 'baykat_farm_enqueue_public_page_styles' );

/**
 * Habille la page de connexion WordPress avec l'identité Baykat.
 */
function baykat_farm_login_header_url() {
    return home_url( '/' );
}
add_filter( 'login_headerurl', 'baykat_farm_login_header_url' );

function baykat_farm_login_site_html_link() {
    return sprintf(
        '<a href="%1$s">← Retour à %2$s</a>',
        esc_url( home_url( '/' ) ),
        esc_html( get_bloginfo( 'name' ) )
    );
}
add_filter( 'login_site_html_link', 'baykat_farm_login_site_html_link' );

function baykat_farm_render_login_visual_header() {
    ?>
    <header class="baykat-login-site-header">
        <a class="baykat-login-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Baykat, accueil">
            <img src="<?php echo esc_url( BAYKAT_FINANCE_URL . 'image.png' ); ?>" alt="Baykat">
        </a>
        <nav class="baykat-login-site-nav" aria-label="Navigation principale">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Accueil <span aria-hidden="true">⌄</span></a>
            <span>Infos du marché</span>
            <span>A propos de BAYKAT</span>
            <span>Nous contacter</span>
        </nav>
        <div class="baykat-login-account">
            <span class="baykat-login-account-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.25"></circle><path d="M5.75 19a6.25 6.25 0 0 1 12.5 0"></path></svg>
            </span>
            <span class="is-current">Connexion</span>
            <span class="baykat-login-header-signup">S'inscrire</span>
        </div>
        <span class="baykat-login-sell-action" aria-hidden="true">
            <span>Vendre sur BAYKAT !</span><span class="baykat-login-sell-plus">+</span>
        </span>
    </header>
    <?php
}
add_action( 'login_header', 'baykat_farm_render_login_visual_header' );

function baykat_farm_login_header_text() {
    return 'Baykat — Gestion agricole';
}
add_filter( 'login_headertext', 'baykat_farm_login_header_text' );

function baykat_farm_login_title() {
    return 'Connexion — Baykat';
}
add_filter( 'login_title', 'baykat_farm_login_title' );

function baykat_farm_render_login_visual_tabs() {
    $requested_action = isset( $_REQUEST['action'] )
        ? sanitize_key( wp_unslash( $_REQUEST['action'] ) )
        : 'login';

    if ( 'login' !== $requested_action ) {
        return;
    }
    ?>
    <div class="baykat-login-tabs" aria-label="Connexion ou inscription">
        <span class="is-active" aria-current="page">Connexion</span>
        <span>S'inscrire</span>
    </div>
    <?php
}
add_action( 'login_form', 'baykat_farm_render_login_visual_tabs' );

function baykat_farm_login_form_layout_script() {
    ?>
    <script>
        (function () {
            document.addEventListener('DOMContentLoaded', function () {
                var username = document.getElementById('user_login');
                var password = document.getElementById('user_pass');
                var remember = document.querySelector('#loginform .forgetmenot');
                var forgot = document.querySelector('#nav a');

                if (username) {
                    username.setAttribute('placeholder', "E-mail ou nom d'utilisateur");
                }
                if (password) {
                    password.setAttribute('placeholder', 'Mot de passe');
                }
                if (remember && forgot) {
                    remember.appendChild(forgot);
                    var navigation = document.getElementById('nav');
                    if (navigation) {
                        navigation.hidden = true;
                    }
                }
            });
        }());
    </script>
    <?php
}
add_action( 'login_footer', 'baykat_farm_login_form_layout_script' );

function baykat_farm_login_translate_strings( $translation, $text, $domain ) {
    if ( ! isset( $GLOBALS['pagenow'] ) || 'wp-login.php' !== $GLOBALS['pagenow'] ) {
        return $translation;
    }

    $translations = array(
        'Log In'                    => 'Connexion',
        'Username or Email Address' => 'Adresse e-mail ou identifiant',
        'Password'                  => 'Mot de passe',
        'Remember Me'               => 'Se souvenir de moi',
        'Lost your password?'       => 'Mot de passe oublié ?',
        'Show password'             => 'Afficher le mot de passe',
        'Hide password'             => 'Masquer le mot de passe',
        'Go to %s'                  => 'Retour à %s',
        'Help'                      => 'Aide',
    );

    return isset( $translations[ $text ] ) ? $translations[ $text ] : $translation;
}
add_filter( 'gettext', 'baykat_farm_login_translate_strings', 10, 3 );
add_filter( 'gettext_with_context', 'baykat_farm_login_translate_strings', 10, 4 );

function baykat_farm_login_message( $message ) {
    if ( '' !== $message ) {
        return $message;
    }

    return '<p class="baykat-login-intro">Connectez-vous à votre espace agricole.</p>';
}
add_filter( 'login_message', 'baykat_farm_login_message' );

/**
 * Remplace la page d'accueil publique par la présentation Baykat.
 */
function baykat_farm_public_landing_template( $template ) {
    if ( ! is_front_page() || is_user_logged_in() ) {
        return $template;
    }

    $landing_template = BAYKAT_FINANCE_DIR . 'public/landing-page.php';

    return file_exists( $landing_template ) ? $landing_template : $template;
}
add_filter( 'template_include', 'baykat_farm_public_landing_template', 99 );

function baykat_farm_redirect_logged_in_homepage() {
    if ( ! is_front_page() || ! is_user_logged_in() || is_admin() ) {
        return;
    }

    $application_url = baykat_farm_get_application_url();

    if ( $application_url ) {
        wp_safe_redirect( $application_url );
        exit;
    }
}
add_action( 'template_redirect', 'baykat_farm_redirect_logged_in_homepage' );
