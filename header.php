<?php
/**
 * The header for our theme
 *
 * @package sieben
 */
$settings = get_option('cis_options_1');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="alexaVerifyID" content="<?php echo $settings["meta_alexaverify"]; ?>">
    <meta name="google-site-verification" content="<?php echo $settings["meta_googlesiteverification"]; ?>">
    <meta name="p:domain_verify" content="7d1459abe5ba6dfa455f357829fc68a4">
    <meta name="facebook-domain-verification" content="q2lyv8vg57eyqsinh6m3dk68gn9q06">
	<script>
window._nQc="89371542";
</script>
<script async src="https://serve.albacross.com/track.js"></script>
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
    <a class="skip-link screen-reader-text" href="#content">Zum Hauptinhalt springen</a>

    <header id="x7-top" role="banner">
        <div id="header_info" class="header-info hidden-xs hidden-sm">
            <div class="header_contact_wrapper">
                <?php echo xsieben_contact_block(); ?>
            </div>
        </div>
        
        <div class="x7-navbar-inner">
            <a class="navbar-brand" href="<?php bloginfo('url'); ?>" aria-label="Zur Startseite von X-Sieben" title="Zur Startseite von X-Sieben">
                <?php the_custom_logo(); ?>
            </a>

            <div class="collapse navbar-collapse x7-nav-primary-wrapper" id="x7-main-nav">
                <?php
                wp_nav_menu(array(
                    'theme_location' => 'primary',
                    'depth'          => 2,
                    'container'      => false,
                    'menu_class'     => 'nav navbar-nav x7-nav-primary',
                    'items_wrap'     => '<ul id="%1$s" class="%2$s" role="menubar">%3$s</ul>',
                    'fallback_cb'    => 'WP_BS3_Navwalker::fallback',
                    'walker'         => new WP_BS3_Navwalker(),
                ));
                ?>
            </div>

            <div class="x7-icons-wrapper">
                <button class="x7-search-btn"
                    type="button"
                    aria-label="Suche öffnen"
                    aria-controls="x7-search-form"
                    aria-expanded="false">
                    <i class="fa fa-search" aria-hidden="true"></i>
                </button>

                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#x7-main-nav" aria-expanded="false" aria-label="Navigation umschalten">
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>

                <button class="x-sieben-hamburger-btn hidden-xs hidden-sm"
                    type="button"
                    aria-label="Zusatzmenü öffnen"
                    aria-controls="x-sieben-desktop-extra-menu"
                    aria-expanded="false">
                    <i class="fa fa-bars" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </header>

    <nav id="x-sieben-desktop-extra-menu" class="x-sieben-extra-menu" aria-label="Zusatznavigation">
        <button class="x7-extra-menu-close" aria-label="Menü schließen">&times;</button>
        <?php
        wp_nav_menu(array(
            'theme_location' => 'burger-menue',
            'depth'          => 2,
            'container'      => false,
            'menu_class'     => 'nav x7-nav-secondary list-unstyled',
            'items_wrap'     => '<ul id="%1$s" class="%2$s" role="menu">%3$s</ul>',
            'fallback_cb'    => 'WP_BS3_Navwalker::fallback',
            'walker'         => new WP_BS3_Navwalker(),
        ));
        ?>
    </nav>

    <?php if (!is_search()) : ?>
        <?php vorteile_view('tooltip'); ?>
    <?php endif; ?>

    <div id="x7-search-form" class="x7-search-form">
        <button class="x7-search-close hidden" aria-label="Suche schließen">&times;</button>
        <?php echo course_search_form_material_simple(); ?>
    </div>