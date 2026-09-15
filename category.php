<?php
/**
 * Die Vorlagendatei für die Anzeige von Beitragsarchiven nach Kategorie.
 *
 * Kann untergeordneten Themes zur Verfügung gestellt werden.
 */
get_header(); ?>

<div class="main-content">
    <div class="container archive-title pdtb-default">
        <h1>Blog <?php single_cat_title(); ?></h1>
        <div class="archive-meta">
            <h6><?php echo category_description(); ?></h6>
        </div>
        <div class="col-md-8">
            <section id="primary" class="site-content">
                <div id="content" role="main">
                    <?php if (have_posts()) : ?>
                        <?php while (have_posts()) : the_post(); ?>
                            <div class="row pdtb-default">
                                <div class="col-md-4">
                                    <?php if (has_post_thumbnail()) : ?>
                                        <img src="<?php the_post_thumbnail_url('medium'); ?>" alt="<?php the_title_attribute(); ?>">
                                    <?php else : ?>
                                        <img src="<?php echo get_template_directory_uri(); ?>/images/default-thumbnail.jpg" alt="<?php the_title_attribute(); ?>">
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-8">
                                    <div class="entry mrl20">
                                        <span class="share pull-right">
                                            <a href="javascript:toggleShare();" class="share-btn"></a>
                                            <span class="services"><?php echo do_shortcode('[addtoany]'); ?></span>
                                        </span>
                                        <h4><a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a></h4>
                                        <?php the_excerpt(); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                        
                        <?php
                            the_posts_pagination( array(
                                'prev_text'          => __( 'Vorherige Seite', 'text-domain' ),
                                'next_text'          => __( 'Nächste Seite', 'text-domain' ),
                                'before_page_number' => '<span class="meta-nav screen-reader-text">' . __( '', 'text-domain' ) . ' </span>',
                            ) );
                        ?>
                    
                    <?php else : ?>
                        <p>Sorry, keine Beiträge gefunden.</p>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <div class="col-md-4">
            <?php get_sidebar('blog_category'); ?>
        </div>
    </div>
</div>

<?php get_footer(); ?>