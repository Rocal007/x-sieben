<?php
function faq_view()
{
    // Get the current page template name
    $current_template = get_page_template_slug();
    //echo $current_template;
    //echo esc_html( get_page_template_slug( $post->ID ) ); 
?>
    <div class="container-fluid accordion-wrapper">
        <div id="accordion" class="panel-group container mrtb50 <?php echo $current_template; ?>">
            <div class="h3 pdtb30 text-center"><i class="fa fa-question-circle"></i> Häufig gestellte Fragen</div>
            <?php
            $args = array(
                'category_name' => 'faq', // Replace 'your-category-slug' with the slug of your specific category
                'posts_per_page' => -1 // Use -1 to fetch all posts in the category
            );
            $query = new WP_Query($args);

            // The Loop
            if ($query->have_posts()) {
                $count = 0;
                while ($query->have_posts()) {
                    $query->the_post();
                    $count++;
            ?>
                    <div class="panel panel-default mrtb50">
                        <div class="panel-heading">
                            <div class="panel-title h5">
                                <a data-toggle="collapse" data-parent="#accordion" href="#collapse<?php echo $count; ?>">
                                   <i class="fa fa-caret-right"></i> <?php the_title(); ?>
                                </a>
                            </div>
                        </div>
                        <div id="collapse<?php echo $count; ?>" class="panel-collapse collapse <?php echo $count; ?>">
                            <div class="panel-body">
                                <?php the_content(); ?>
                            </div>
                        </div>
                    </div>
            <?php
                }
            } else {
                // no posts found
                echo 'No posts found in this category.';
            }


            // Restore original Post Data
            wp_reset_postdata(); ?>
            <a href="/info-ueberblick/">
                <div class="btn btn-primary mrt20">
                    Weitere Informationen entdecken
                </div>
            </a>
        </div>
    </div>
<?php
}

function render_courses_faq()
{
    // Get the current page template name
    $current_template = get_page_template_slug();
    //echo $current_template;
    //echo esc_html( get_page_template_slug( $post->ID ) ); 
?>
    <div class="container-fluid accordion-wrapper">
        <div id="accordion" class="panel-group container mrtb50 <?php echo $current_template; ?>">
            <div class="h3 pdtb30 text-center"><i class="fa fa-question-circle"></i> Häufig gestellte Fragen</div>
            <?php
            $args = array(
                'category_name' => 'faq', // Replace 'your-category-slug' with the slug of your specific category
                'posts_per_page' => -1 // Use -1 to fetch all posts in the category
            );
            $query = new WP_Query($args);

            // The Loop
            if ($query->have_posts()) {
                $count = 0;
                while ($query->have_posts()) {
                    $query->the_post();
                    $count++;
            ?>
                    <div class="panel panel-default mrtb50">
                        <div class="panel-heading">
                            <div class="panel-title h5">
                                <a data-toggle="collapse" data-parent="#accordion" href="#collapse<?php echo $count; ?>">
                                   <i class="fa fa-caret-right"></i> <?php the_title(); ?>
                                </a>
                            </div>
                        </div>
                        <div id="collapse<?php echo $count; ?>" class="panel-collapse collapse <?php echo $count; ?>">
                            <div class="panel-body">
                                <?php the_content(); ?>
                            </div>
                        </div>
                    </div>
            <?php
                }
            } else {
                // no posts found
                echo 'No posts found in this category.';
            }


            // Restore original Post Data
            wp_reset_postdata(); ?>
            <a href="/info-ueberblick/">
                <div class="btn btn-primary mrt20">
                    Weitere Informationen entdecken
                </div>
            </a>
        </div>
    </div>
<?php
}
