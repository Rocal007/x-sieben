<?php 
function get_courses($term, $coursetype) {
$args = array(
    'post_type' => 'courses',
    'offset' => 0, 
    'orderby'   => 'date',
    'order' => 'ASC',
    "posts_per_page" => -1,
    
    'tax_query' => array(
        array(
            'taxonomy' => 'coursecategory',
            'field'    => 'slug',
            'terms'    => $term,
        ),
    ),
);



// The Query
$the_query = new WP_Query( $args );

$total = $the_query->found_posts;
$today = date('d-m-Y');
//echo '<div style="color: black; float: right">' . $total . ' </div>';  
// The Loop


if ( $the_query->have_posts() ) {
 

//echo '<div class="container"><div class="vc_row wpb_row vc_inner vc_row-fluid">';
   
	switch ($coursetype) {
	case $coursetype === 'lehrgang':
	$courseTypeHeading = '<strong>Lehrgang</strong>  9 Tage';
    break;
	case $coursetype === 'seminar':
	$courseTypeHeading = '<strong>Seminar</strong>  2 Tage';
	break;
	case $coursetype === 'crashkurs':
	$courseTypeHeading = '<strong>Intensivkurs</strong>';
    break;	
	case $coursetype === 'blended-learning':
	$courseTypeHeading = '<strong>Blended Learning</strong> – Präsenzunterricht + Online Kurse / eLearning';
    break;	
	
			
    default:
    $courseTypeHeading = "";
};
//echo $courseTypeHeading;
		
	
while ( $the_query->have_posts() ) {
            
            
    
            $cat_slug_custom  = '';
				if (has_term( 'Lehrgang', 'coursecategory' )) {
 				$cat_slug_custom = 'lehrgang';
				} 
    			elseif (has_term( 'Seminar', 'coursecategory' )){
                $cat_slug_custom = 'seminar';
                }
                elseif (has_term( 'Crashkurs', 'coursecategory' )){
                $cat_slug_custom = 'crashkurs';
                }
    			elseif (has_term( 'Bundle', 'coursecategory' )){
                $cat_slug_custom = 'bundle';
                }
                elseif (has_term( 'eLearning', 'coursecategory' )){
                $cat_slug_custom = 'eLearning';
                }
                elseif (has_term( 'Blended Learning', 'coursecategory' )){
                $cat_slug_custom = 'blended-learning';
                }
    			else {
				$cat_slug_custom = '';
				};	
	
	        $cat_fields_of_education = ' Nicht förderbar';
				if( has_term( '', 'fieldofeducation' ) ) {
  				$cat_fields_of_education = '  förderbarer Kurs';
				};
    		
	        $ams =  get_post_meta( get_the_ID(), 'api_ams_publish', true );
			if ($ams) {$ams = ' Förderungen möglich';}
	           
			else {$ams = 'keine Förderungen möglich';}; 
	      
			$skills = '';
	
			if (has_term( 'Einsteiger', 'coursecategory' ) && !has_term( 'Fortgeschrittene', 'coursecategory' )) {
 				$skills = 'einsteiger';
				} 
			elseif (has_term( 'Fortgeschrittene', 'coursecategory' ) && !has_term( 'Einsteiger', 'coursecategory' )) {
 				$skills = 'fortgeschrittener';
				} 
			elseif (has_term( 'Fortgeschrittene', 'coursecategory' ) && has_term( 'Einsteiger', 'coursecategory' )) {
 				$skills = 'einsteiger-fortgeschrittene';
				} 
	
                          
	        //$ziele = get_post_meta( get_the_ID(), 'courses_accordion', true);        
	        //$ziele = $ziele[1][content];
	
            $title_link = wp_make_link_relative(get_permalink());
            $title_link = basename($title_link);
            $alt = get_the_title();
            
			$title_short = get_field('title_im_slider');
			$title = get_the_title();
			if ($title_short === "") {
				
					$title = get_the_title();
					$title = substr($title, 0, 60);
				
			} else 
				{
					$title = $title_short;
				}
			
	        $featured_img_url = get_the_post_thumbnail_url(get_the_ID(),'2col-thumbnail-brain'); 
            $fallback_img = '/wp-content/uploads/2020/04/seo-scaled.jpg';
            if ($featured_img_url == "") {
                   $featured_img_url = $fallback_img;
             };
            
	  		 $untertitel = get_post_meta(get_the_ID(), 'untertitel', true);
	  		 $untertitel =  preg_replace('/\s+?(\S+)?$/', '', substr($untertitel, 0, 500));
	
	
            $link = get_post_permalink();
			if (get_post_meta(get_the_ID(),"start_datum",true)) {
				$date_check = get_post_meta(get_the_ID(),"start_datum",true); 
			}
            
            $start_datum = date('d. m. Y', strtotime(get_post_meta(get_the_ID(), 'start_datum', true)));
            $end_datum = date('d. m. Y', strtotime(get_post_meta(get_the_ID(), 'end_datum', true)));
                    
			
	       
            $template = ' 
				<div class="col-md-6 courses-2-col ' . $title_link . ' ' . $skills . ' ' . $cat_slug_custom . '">
							<div class="courses-badge-container">	
								<span class="notify-badge-title">' .  $title . '</span>
							</div>			

							<div class="courses-top">
								<a href="' . $link . '">	
								    	<img class="courses-tab-image-2-col" src="' . $featured_img_url . '" alt="'. $untertitel .'">
								</a>
							</div>
				</div>';           
	
  
                if ($coursetype === 'lehrgang' && $cat_slug_custom === 'lehrgang') { 
					echo $template;
				};

			   if ($coursetype === 'seminar' && $cat_slug_custom === 'seminar') { 
					echo $template;
				};

			   if ($coursetype === 'crashkurs' && $cat_slug_custom === 'crashkurs') { 
					echo $template;
				};
            //if ($today < $start_datum || $start_datum = "") {echo $template;}
           //echo $template;
     
	
};
	
} else {
    // no posts found
}
/* Restore original Post Data */
wp_reset_postdata();
}