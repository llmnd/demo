<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$application_url = baykat_farm_get_application_url();
$login_url       = wp_login_url( $application_url ? $application_url : home_url( '/' ) );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f6f1">
    <title><?php echo esc_html( get_bloginfo( 'name' ) . ' — Votre espace agricole' ); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'baykat-public-page' ); ?>>
<?php wp_body_open(); ?>
<div class="baykat-public-site">
    <header class="baykat-public-header">
        <a class="baykat-public-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Baykat, accueil">
            <img src="<?php echo esc_url( BAYKAT_FINANCE_URL . 'image.png' ); ?>" alt="Baykat">
        </a>
        <nav class="baykat-public-nav" aria-label="Navigation principale">
            <a href="#services">La plateforme</a>
            <a href="#pourquoi">Pourquoi Baykat</a>
        </nav>
        <a class="baykat-public-login-link" href="<?php echo esc_url( $login_url ); ?>">Connexion <span aria-hidden="true">↗</span></a>
    </header>

    <main>
        <section class="baykat-public-hero">
            <div class="baykat-public-hero-copy">
                <span class="baykat-public-eyebrow"><span></span> Votre agriculture, mieux organisée</span>
                <h1>Faites grandir<br>votre <em>exploitation.</em></h1>
                <p>Fermes, parcelles, activités et finances réunies dans un espace simple, pensé pour votre quotidien.</p>
                <div class="baykat-public-hero-actions">
                    <a class="baykat-public-button" href="<?php echo esc_url( $login_url ); ?>">Accéder à mon espace <span aria-hidden="true">→</span></a>
                    <a class="baykat-public-text-link" href="#services">Découvrir la plateforme <span aria-hidden="true">↓</span></a>
                </div>
                <div class="baykat-public-proof">
                    <span class="baykat-public-proof-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M12 21V11m0 4C6.5 15 4 12 4 7c5.5 0 8 3 8 8Zm0-3c0-5.5 3-8 8-8 0 5.5-2.5 8-8 8Z"></path></svg>
                    </span>
                    <span><strong>Tout au même endroit</strong><small>Vos données agricoles, simplement.</small></span>
                </div>
            </div>

            <div class="baykat-public-hero-art" aria-label="Illustration de parcelles agricoles">
                <div class="baykat-public-art-sun"></div>
                <div class="baykat-public-art-tag"><span class="baykat-public-live-dot"></span> Mon exploitation</div>
                <svg class="baykat-public-fields" viewBox="0 0 650 500" role="img" aria-labelledby="baykat-fields-title">
                    <title id="baykat-fields-title">Parcelles cultivées au lever du soleil</title>
                    <defs>
                        <linearGradient id="baykat-field-sky" x1="0" x2="1" y1="0" y2="1">
                            <stop offset="0" stop-color="#f2dfb9"></stop><stop offset="1" stop-color="#e8c98e"></stop>
                        </linearGradient>
                        <linearGradient id="baykat-field-ground" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0" stop-color="#7f9061"></stop><stop offset="1" stop-color="#354f3b"></stop>
                        </linearGradient>
                    </defs>
                    <rect width="650" height="500" rx="28" fill="url(#baykat-field-sky)"></rect>
                    <circle cx="458" cy="142" r="60" fill="#fff4d8" opacity=".9"></circle>
                    <path d="M0 277 102 221l90 40 106-76 102 67 96-49 154 80v217H0Z" fill="#a8a477"></path>
                    <path d="m0 326 142-94 118 68 99-63 113 72 83-37 95 49v179H0Z" fill="url(#baykat-field-ground)"></path>
                    <path d="M-28 500c73-145 146-190 235-201 76-9 143 27 198 77 56-71 138-114 273-100v224Z" fill="#667d52"></path>
                    <path d="M-8 500c76-120 159-155 239-166 77-10 139 16 195 57m-434 109c86-96 163-119 241-125 74-5 131 16 189 50m-415 75c90-70 167-84 243-84 77 0 136 18 194 42" fill="none" stroke="#d1b982" stroke-width="3" opacity=".66"></path>
                    <path d="M-15 387c60-55 123-88 193-99m-157 152c55-47 116-72 177-82m-138 132c49-34 100-49 151-56m301-97c53-31 111-42 184-30m-157 81c47-21 98-26 160-14m-132 64c42-12 86-12 134-3" fill="none" stroke="#e3d0a5" stroke-width="2" opacity=".8"></path>
                    <path d="M316 500c-27-66-34-132-23-205 12-70 43-126 91-171m-63 376c-2-78 16-147 53-211 34-57 79-103 134-136" fill="none" stroke="#476343" stroke-width="7" opacity=".8"></path>
                </svg>
                <div class="baykat-public-art-note">
                    <span class="baykat-public-note-icon" aria-hidden="true">✳</span>
                    <span><strong>Une vue claire</strong><small>Pour mieux décider, chaque jour.</small></span>
                </div>
            </div>
        </section>

        <section class="baykat-public-services" id="services">
            <div class="baykat-public-section-heading">
                <span class="baykat-public-eyebrow">Un seul espace</span>
                <h2>Votre ferme, en toute clarté.</h2>
            </div>
            <div class="baykat-public-service-grid">
                <article class="baykat-public-service-card">
                    <span class="baykat-public-service-icon is-green" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><path d="m3 7 6-3 6 3 6-3v13l-6 3-6-3-6 3zM9 4v13m6-10v13"></path></svg>
                    </span>
                    <h3>Fermes &amp; parcelles</h3>
                    <p>Retrouvez vos exploitations et leurs parcelles en quelques gestes.</p>
                </article>
                <article class="baykat-public-service-card">
                    <span class="baykat-public-service-icon is-gold" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M12 21v-9m0 3c-5.5 0-8-3-8-8 5.5 0 8 3 8 8Zm0-3c0-5.5 3-8 8-8 0 5.5-2.5 8-8 8Z"></path></svg>
                    </span>
                    <h3>Activités &amp; cultures</h3>
                    <p>Suivez les travaux, les cultures et les récoltes à venir.</p>
                </article>
                <article class="baykat-public-service-card">
                    <span class="baykat-public-service-icon is-sage" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16v13H4zM4 7l2-3h12l2 3m-14 5h16m-12 4h4"></path></svg>
                    </span>
                    <h3>Finances &amp; stocks</h3>
                    <p>Gardez un œil sur vos dépenses, revenus et intrants.</p>
                </article>
            </div>
        </section>

        <section class="baykat-public-cta" id="pourquoi">
            <div>
                <span class="baykat-public-eyebrow">Prêt à commencer ?</span>
                <h2>Votre quotidien agricole<br>commence ici.</h2>
            </div>
            <a class="baykat-public-button is-light" href="<?php echo esc_url( $login_url ); ?>">Se connecter <span aria-hidden="true">→</span></a>
        </section>
    </main>

    <footer class="baykat-public-footer">
        <a class="baykat-public-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Baykat, accueil">
            <img src="<?php echo esc_url( BAYKAT_FINANCE_URL . 'image.png' ); ?>" alt="Baykat">
        </a>
        <span>Baykat · La gestion agricole, simplement.</span>
        <a href="<?php echo esc_url( $login_url ); ?>">Connexion</a>
    </footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
