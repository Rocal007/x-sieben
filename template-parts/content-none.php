<?php
/**
 * Template part for displaying a message when no posts are found
 *
 * @package sieben
 */
?>
<div class="container content-container pdtb-default">
    <div class="row main-row">
        <div class="col-sm-9">
            <div class="news-posts">
                <section class="page-content no-results">
                    <h1><?php esc_html_e( 'Leider nichts gefunden', 'sieben' ); ?></h1>

                    <?php if ( is_home() && current_user_can( 'publish_posts' ) ) : ?>

                        <p>
                            <?php
                            printf(
                                wp_kses(
                                    __( 'Bereit, Ihren ersten Beitrag zu veröffentlichen? <a href="%1$s">Jetzt starten</a>.', 'sieben' ),
                                    array( 'a' => array( 'href' => array() ) )
                                ),
                                esc_url( admin_url( 'post-new.php' ) )
                            );
                            ?>
                        </p>

                    <?php elseif ( is_search() ) : ?>

                        <p>
                            <?php esc_html_e( 'Leider konnten wir für diese Suchanfrage keine Ergebnisse finden. Vielleicht haben Sie Glück mit einem ähnlichen Begriff?', 'sieben' ); ?>
                        </p>
                        <?php get_search_form(); ?>

                    <?php else : ?>

                        <p>
                            <?php esc_html_e( 'Es scheint, wir konnten nicht finden, wonach Sie gesucht haben. Vielleicht hilft die Suche.', 'sieben' ); ?>
                        </p>
                        <?php get_search_form(); ?>

                    <?php endif; ?>
                </section>
            </div>
        </div>

        <?php get_sidebar(); ?>
    </div>
</div>
