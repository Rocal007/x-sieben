<div class="container" id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <div class="row main-row">
        <div class="col-sm-12">
            <div class="container">
                <h1><?php single_cat_title('', true); ?></h1>

                <?php
                // Get current category ID
                $current_category = get_queried_object();
                if ($current_category && isset($current_category->term_id)) :
                    $args = array(
                        'cat' => $current_category->term_id,
                        'posts_per_page' => 5,
                    );
                    $myposts = get_posts($args);
                    foreach ($myposts as $post) :
                        setup_postdata($post);
                ?>
                        <div class="row">
                            <div class="col-md-4">
                                <a href="<?php echo esc_url(get_the_permalink()); ?>">
                                    <?php if (has_post_thumbnail()) {
                                        the_post_thumbnail('sieben-thumbnail-image');
                                    } else { ?>
                                        <img src="<?php echo esc_url(get_template_directory_uri() . '/images/default-260x165.png'); ?>" alt="" />
                                    <?php } ?>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <!-- Optional content here -->
                            </div>
                        </div>
                <?php
                    endforeach;
                    wp_reset_postdata();
                endif;
                ?>

                <div class="news-posts">
                    <div class="other-news">
                        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
                                <div class="othernews-post">
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="row">
                                                <div class="col-sm-4">
                                                    <div class="label-img">
                                                        <div class="othernews-post-image">
                                                            <a href="<?php echo esc_url(get_the_permalink()); ?>">
                                                                <?php if (has_post_thumbnail()) {
                                                                    the_post_thumbnail('sieben-thumbnail-image');
                                                                } else { ?>
                                                                    <img src="<?php echo esc_url(get_template_directory_uri() . '/images/default-260x165.png'); ?>" alt="" />
                                                                <?php } ?>
                                                            </a>
                                                        </div>
                                                        <?php $sieben_categories = get_the_category(); ?>
                                                        <div class="label">
                                                            <div class="row label-row">
                                                                <div class="col-sm-9 col-xs-9 label-column no-padding">
                                                                    <span><?php echo !empty($sieben_categories) ? esc_html($sieben_categories[0]->name) : ''; ?></span>
                                                                </div>
                                                                <?php $sieben_num_comments = get_comments_number();
                                                                if ($sieben_num_comments != 0) { ?>
                                                                    <div class="col-sm-3 col-xs-3 no-padding">
                                                                        <div class="comments">
                                                                            <i class="fa fa-comments comments-icon"></i>
                                                                            <span class="comments-no"><?php echo esc_html($sieben_num_comments); ?></span>
                                                                        </div>
                                                                    </div>
                                                                <?php } ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-sm-8">
                                                    <div class="othernews-post-details">
                                                        <h4 class="othernews-post-title">
                                                            <a href="<?php echo esc_url(get_the_permalink()); ?>"><?php the_title(); ?></a>
                                                        </h4>
                                                        <div class="othernews-post-news">
                                                            <?php the_excerpt(); ?>
                                                        </div>
                                                        <a href="<?php echo esc_url(get_the_permalink()); ?>">
                                                            <?php echo esc_html__('mehr erfahren', 'sieben'); ?>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        <?php endwhile;
                        endif; ?>

                        <div class="more-info">
                            <div class="row">
                                <div class="col-sm-12">
                                    <?php
                                    the_posts_pagination(array(
                                        'mid_size' => 2,
                                        'prev_text' => esc_html__('Back', 'sieben'),
                                        'next_text' => esc_html__('Onward', 'sieben'),
                                    ));
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="relatednews-post">
                        <?php if ((int)get_theme_mod('hide_related_post') !== 0) : ?>
                            <div class="row">
                                <?php
                                $sieben_catID = get_theme_mod('category_section_2');
                                $num_post = get_theme_mod('number_posts_sec2');
                                $args = array('cat' => $sieben_catID, 'numberposts' => $num_post);
                                $sieben_related = get_posts($args);
                                if ($sieben_related) :
                                    foreach ($sieben_related as $post) :
                                        setup_postdata($post);
                                ?>
                                        <div class="col-sm-4">
                                            <div class="relatednews-post-panel">
                                                <div class="label-img">
                                                    <div class="relatednews-post-image">
                                                        <a href="<?php echo esc_url(get_the_permalink()); ?>">
                                                            <?php if (has_post_thumbnail()) {
                                                                the_post_thumbnail('sieben-related-thumbnail');
                                                            } else { ?>
                                                                <img src="<?php echo esc_url(get_template_directory_uri() . '/images/default-260x165.png'); ?>" alt="" />
                                                            <?php } ?>
                                                        </a>
                                                    </div>
                                                    <?php $sieben_categories = get_the_category(); ?>
                                                    <div class="label">
                                                        <div class="row label-row">
                                                            <div class="col-sm-9 col-xs-9 label-column no-padding">
                                                                <span><?php echo !empty($sieben_categories) ? esc_html($sieben_categories[0]->name) : ''; ?></span>
                                                            </div>
                                                            <?php $sieben_num_comments = get_comments_number();
                                                            if ($sieben_num_comments != 0) { ?>
                                                                <div class="col-sm-3 col-xs-3 no-padding">
                                                                    <div class="comments">
                                                                        <i class="fa fa-comments comments-icon"></i>
                                                                        <span class="comments-no"><?php echo esc_html($sieben_num_comments); ?></span>
                                                                    </div>
                                                                </div>
                                                            <?php } ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="relatednews-post-details">
                                                    <div class="relatednews-post-title">
                                                        <h5>
                                                            <a href="<?php echo esc_url(get_the_permalink()); ?>"><?php the_title(); ?></a>
                                                        </h5>
                                                    </div>
                                                    <div class="relatednews-post-news">
                                                        <?php the_excerpt(); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                <?php endforeach;
                                    wp_reset_postdata();
                                endif;
                                ?>
                            </div>
                        <?php endif; ?>

                        <?php if ((int)get_theme_mod('hide_similar_post') !== 0) : ?>
                            <div class="similar-post">
                                <div class="row">
                                    <?php
                                    $sieben_catID = get_theme_mod('category_section_3');
                                    $num_post = get_theme_mod('number_posts_sec3');
                                    $args = array('cat' => $sieben_catID, 'numberposts' => $num_post);
                                    $sieben_similar = get_posts($args);
                                    if ($sieben_similar) :
                                        foreach ($sieben_similar as $post) :
                                            setup_postdata($post);
                                    ?>
                                            <div class="col-sm-6">
                                                <div class="similar-post-panel">
                                                    <div class="label-img">
                                                        <div class="similar-post-image">
                                                            <a href="<?php echo esc_url(get_the_permalink()); ?>">
                                                                <?php if (has_post_thumbnail()) {
                                                                    the_post_thumbnail('sieben-similar-thumbnail');
                                                                } else { ?>
                                                                    <img src="<?php echo esc_url(get_template_directory_uri() . '/images/default-400x300.png'); ?>" alt="" />
                                                                <?php } ?>
                                                            </a>
                                                        </div>
                                                        <?php $sieben_similar_cat = get_the_category(); ?>
                                                        <div class="label">
                                                            <div class="row label-row">
                                                                <div class="col-sm-9 col-xs-9 label-column no-padding">
                                                                    <span><?php echo !empty($sieben_similar_cat) ? esc_html($sieben_similar_cat[0]->name) : ''; ?></span>
                                                                </div>
                                                                <?php $sieben_num_comments = get_comments_number();
                                                                if ($sieben_num_comments != 0) { ?>
                                                                    <div class="col-sm-3 col-xs-3 no-padding">
                                                                        <div class="comments">
                                                                            <i class="fa fa-comments comments-icon"></i>
                                                                            <span class="comments-no"><?php echo esc_html($sieben_num_comments); ?></span>
                                                                        </div>
                                                                    </div>
                                                                <?php } ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="similar-post-details">
                                                        <div class="similar-post-title">
                                                            <h4>
                                                                <a href="<?php echo esc_url(get_the_permalink()); ?>"><?php the_title(); ?></a>
                                                            </h4>
                                                        </div>
                                                        <div class="similar-post-news">
                                                            <?php the_excerpt(); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                    <?php endforeach;
                                        wp_reset_postdata();
                                    endif;
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php //get_sidebar(); ?>
        </div>
    </div>
</div>
