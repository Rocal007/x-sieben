<?php 
$cat_id = get_field('cat_selected');
	function courses($education, $cat_id) {
        if (isset($cat_id)) {
                $args = array(
                'post_type' => 'courses',
                'post_status' => 'publish',
                'offset' => 0, 
                'orderby'   => 'date',
                'order' => 'ASC',
                "posts_per_page" => 200,

                'tax_query' => array(
                    'relation' => 'AND',
                    array(
                        'taxonomy' => 'coursecategory',
                        'field'    => 'slug',
                        'terms'    => $cat_id[0] 
                    ),
                        array(
                        'taxonomy' => 'coursecategory',
                        'field'    => 'slug',
                        'terms'    => $education,
                    ),
                ),
            );
        }
        return $args;
    }

    $cat_array = ['crashkurs','Lehrgang','Seminar','blended-learning','e-learning','bundle','coaching'];

	function cat_view($myposts){
						
        $featured_img_url = get_the_post_thumbnail_url(get_the_ID(),'2col-thumbnail-brain'); 
        $fallback_img = '/wp-content/uploads/2020/04/seo-scaled.jpg';
        $untertitel = get_post_meta(get_the_ID(), 'untertitel', true);
        $untertitel =  preg_replace('/\s+?(\S+)?$/', '', substr($untertitel, 0, 500));
        $title = get_field ("title_im_slider");
        $link = get_post_permalink();	
        if ($featured_img_url == "") {
                $featured_img_url = $fallback_img;
            };?>
    
        <div class="col-md-6 courses-2-col">
            <div class="courses-badge-container">	
                    <span class="category-notify-badge-title"><a href="<?php the_permalink(); ?>" title="<?php echo $untertitel;?>"><?php echo $title;?></a></span>
            </div>			
            <div class="courses-top">
                <a href="<?php echo $link;?>" title="<?php echo $untertitel;?>">	
                    <img class="courses-tab-image-2-col" src="<?php echo $featured_img_url;?>" alt="<?php echo $untertitel; ?>">
                </a>
            </div>
        </div>
    <?php wp_reset_postdata();
}
    function cat_list($myposts){
        $start_datum = get_post_meta(get_the_ID(), 'start_datum', true);
        $end_datum = get_post_meta(get_the_ID(), 'end_datum', true);
        $untertitel = get_post_meta(get_the_ID(), 'untertitel', true);
        $untertitel =  preg_replace('/\s+?(\S+)?$/', '', substr($untertitel, 0, 500));
        $title = get_field ("title_im_slider");
        $featured = get_field ("topseeller");	
        $custom = get_post_custom(get_the_ID());
        $link = get_the_permalink();
        $content = get_the_content();
            //var_dump($custom["dauer"]);
            if (!$featured) { ?>		
                                        
                <li><a href="<?php echo $link;?>" title="<?php echo $untertitel;?>"><?php echo $title;?></a><span class="category-course-date"><?php echo $custom["dauer"][0];?></span>
                    <?php //echo $content;?>		
                </li>
            <?php };?>				
    <?php }
				
     
    function cat_list_featured($myposts){
            $featured_img_url = get_the_post_thumbnail_url(get_the_ID(),'2col-thumbnail-brain'); 
            $fallback_img = '/wp-content/uploads/2020/04/seo-scaled.jpg';
            $start_datum = get_post_meta(get_the_ID(), 'start_datum', true);
            $end_datum = date('d. m. Y', strtotime(get_post_meta(get_the_ID(), 'end_datum', true)));
            $untertitel = get_post_meta(get_the_ID(), 'untertitel', true);
            $untertitel =  preg_replace('/\s+?(\S+)?$/', '', substr($untertitel, 0, 500));
            $title = get_field ("title_im_slider");
            $featured = get_field ("topseeller");	
            $link = get_the_permalink();
            
            if ($featured){?>
            <div class="row">	
                <div class="mdl-20 cat-courses-title"> <h2><a href="<?php echo $link;?>" title="<?php echo $start_datum; ?>"><?php echo $title;?></a><span class="pull-right pdr10 mrt5 three-point">
    <i class="fa fa-ellipsis-v" aria-hidden="true"></i></span></h2></div>
                    <div class="mdl-20 col-md-5 pdr10">
                        
                        <a href="<?php echo $link;?>" title="<?php echo $untertitel;?>">	
                                <img class="" src="<?php echo $featured_img_url;?>" alt="<?php echo $start_datum; ?>">
                        </a>
                    </div>
                    <div class="col-md-7 pdl20">
                        <p><?php echo $untertitel;?></p>
                    </div>
            </div>
            <?php };?>

    <?php }?>