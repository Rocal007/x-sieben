<?php
function slider($pic_amount = 3, $col_divider = 4, $selected_category = '90-trend-themen-2018')
{
    include_once(get_template_directory() . '/inc/core/components/slider/slider-model.php');

    // Get the slider chunks and optional reverenzen post
    $slider_chuncks = get_slider_chunks($selected_category, $pic_amount);
    $reverenzen = get_reverenzen_post(); // if needed later
    // Temporäre Ausgabe zur Fehlersuche


?>
    <div class="container-fluid slider-container pdtb-default">
        <div class="container">
            <div class="text-center x-slider-title pdtb30">
                <div class="h2">Schulung wählen und schon bald fit für die neue Arbeitswelt sein</div>
            </div>
            <?php 
            // echo '<pre>';
            // print_r($slider_chuncks);
            // echo '</pre>'; 
            ?>
            <div id="x-carousel" class="carousel slide" data-ride="carousel" data-interval="13000">
                <div class="carousel-inner">
                    <?php $i = 1; ?>
                    <?php foreach ($slider_chuncks as $slider_chunck): ?>
                        <div class="item <?php echo ($i === 1) ? 'active' : ''; ?>">
                            <?php foreach ($slider_chunck as $slider_chunck_single): ?>
                                <div class="col-md-<?php echo esc_attr($col_divider); ?> text-center">
                                    <?php if (!empty($slider_chunck_single->ID)): ?>
                                        <a href="<?php echo esc_url(get_permalink($slider_chunck_single->ID)); ?>">
                                            <img src="<?php echo esc_url(get_the_post_thumbnail_url($slider_chunck_single->ID, '3col-thumbnail-brain')); ?>" alt="<?php echo esc_attr(get_the_title($slider_chunck_single->ID)); ?>">
                                            <div class="x-carousel-caption">
                                                <div class="x-carousel-caption-title h4">
                                                    <span class="slider-button"><?php echo esc_html(get_field('title_im_slider', $slider_chunck_single->ID)); ?></span>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php $i++; ?>
                    <?php endforeach; ?>
                </div>

                <a class="x-carousel-control" href="#x-carousel" data-slide="prev">
                    <span class="pull-left">
                        <i class="fa fa-chevron-left"></i>
                    </span>
                    <span class="sr-only">Previous</span>
                </a>
                <a class="x-carousel-control" href="#x-carousel" data-slide="next">
                    <span class="pull-right">
                        <i class="fa fa-chevron-right"></i>
                    </span>
                    <span class="sr-only">Next</span>
                </a>
            </div>
        </div>
    </div>
<?php
}
