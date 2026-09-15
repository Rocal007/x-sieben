<?php
   global $post;
?>
<div class="container-fluid">
   <div class="content-floor-wrapper">
      <div class="grid-row">
         <div class="content col">
            <div id="kurse-title"><?php the_title( '<h1>', '</h1>' ); ?></div>
         </div>
         <div class="filter-col">
            <div id="myCarousel" class="carousel slide" data-ride="carousel">
               <div id="myCarousel" class="carousel slide" data-ride="carousel">
                  <div class="inner-thumb-courses">
                     <div class="item active">
                        
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>
</div>
<div class="breadcrumbs-black">
   <div class="container">
      <?php
         if ( function_exists('yoast_breadcrumb') ) {
           yoast_breadcrumb( '<p id="breadcrumbs-courses">','</p>' );
         }
         ?>
   </div>
</div>
<div class="container">
<div id="courses_content_wrap" class="row">
   <div class="col-md-8">
      <?php the_content(); ?>
      <div id="courses_accordion">
         <div id="courses_accordion_inner">
            <?php
               $tabs = get_post_meta($post->ID,"courses_accordion",true);
               
               if($tabs) {
                   foreach($tabs as $tab) {
               
                                    
                       ?>
            <button class="caccordion <?php echo ($tab["opentab"]) ? "active" : ""; ?> <?= $opener_class; ?>">
               <h4>
                  <?php echo htmlentities($tab["title"]); ?>
               </h4>
            </button>
            <div class="cpanel <?= $cpanel_class; ?>">
               <div class="cpanel_inner">
                  <?php $tab_content = $tab["content"]; 
						$tab_content= apply_filters( 'the_content', $tab_content );?>
                  <div class="cai_content"><?php echo $tab_content; ?></div>
               </div>
            </div>
            <?php
               }
               }
               ?>
         </div>
      </div>
   </div>
   <div class="col-md-4">
      <p class="wpb_content_element">
      </p>
   </div>
</div>
<?php
   $related = [];
   for($i=1;$i<11; $i++) {
       $rel = trim(get_post_meta($post->ID,"related".$i,true));
       if($rel) {
           array_push($related,$rel);
       }
   }
   
   $related = array_unique($related);
   
   if($related != "") {
       ?>
<div class="container">
   <div class="vc_row wpb_row vc_inner vc_row-fluid">
      <p>
         Besucher interessieren sich auch für …
      </p>
      <?php foreach($related as $rel) { ?>
      <div class="wpb_column vc_column_container vc_col-sm-4 ">
         <div class="vc_column-inner">
            <div class="wpb_wrapper" style="padding-top:30px">
               <a href="<?php echo get_the_permalink($rel); ?>">
                  <div> <?php echo get_the_title($rel); ?></div>
                  <div class="related-courses-img"><?php echo get_the_post_thumbnail( $rel, 'full' ); ?></div>
               </a>
            </div>
         </div>
      </div>
      <?php } ?>
      <?php
         }
         ?>
   </div>
</div>