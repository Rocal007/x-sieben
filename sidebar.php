<?php
   /**
    * The sidebar containing the main widget area
    *
    *
    * @package sieben
    */
   
   global $post;
   
   if (is_single() && $post->post_type == "courses") {
       wp_reset_postdata();
   
       while (have_posts()): the_post(); ?>
<div id="courses-sidebar" class="right">
   <div class="courses-sidebar-inner">
      <div class="courses-sidebar-element clearfix">
         <h4 class="sidebar-heading">
            Wichtige <b>Kursdetails ...</b>
         </h4>
         <?php xsieben_course_base_meta_html(); ?>
      </div>
     
      <div class="courses-sidebar-element clearfix button-divider">
         <a href="#" class="xsieben-page-printer">
            <div class="" style="padding-top: 10px;">
               <div class="sidebar-button" id="investition">
                  <div style="color:#ffffff!important; font-weight:500;" class="" href="#invest" title="" data-original-title="Ihre Investition I Kosten anzeigen">💡 IHRE INVESTITION I KLARNA RATENKAUF</div>
               </div>
               <div class="sidebar-button" id="starttermin" data-original-title="" title="">
                  <a href="#" style="color:#ffffff!important; font-weight:500; text-decoration: none;" class="" title="" data-original-title="Termine">💡 STARTTERMINE ANZEIGEN</a></div>
         </div>
         </a>
      </div>
      <div class="courses-sidebar-element clearfix">
         <a href="tel:0800700170">
            <div class="courses-sidebar-icon phone"></div>
            <div class="courses-sidebar-element-inner" style="margin-top: 5px;
               color: black!important;">
               <strong>RUFEN SIE AN:</strong> 0800 700 170
            </div>
         </a>
      </div>
      <div class="courses-sidebar-element clearfix">
        
            <div class="courses-sidebar-icon star"></div>
            <div class="courses-sidebar-element-inner">
               <strong style=" margin-top: 5px; color: black!important; display: inline-block;">REZENSIONEN:</strong>  
               <div
                  class= "sidebar-link" >
                   <a href="https://www.provenexpert.com/x-sieben-wirtschaftstraining/?utm_source=Widget&utm_medium=Widget&utm_campaign=Widget">Externe Bewertungen anzeigen </a>
               </div>
               <?php echo do_shortcode('[wbcr_html_snippet id="28092"]'); ?>
            </div>
        
      </div>
      <div class="courses-sidebar-element clearfix button-divider">
         <div class="" style="padding-top: 10px;">
			<a href="#"> 
            <div class="sidebar-button" id="sidebar-angebot">
               <div style="color:#ffffff!important; font-weight:500; text-decoration: none;">💡 FÖRDERBARES ANGEBOT IN 8 STUNDEN</div>
            </div>
			</a>
         </div>
      </div>
      <div class="courses-sidebar-element clearfix">
         <div class="courses-sidebar-icon finger"></div>
         <div class="courses-sidebar-element-inner vierfachgarantie" style="margin-top: 5px;
            color: black!important;">
            <strong>X SIEBEN 4-FACH GARANTIE:</strong> 
            <ul>
               <li>Zufriedenheitsgarantie</li>
               <li>Rücktrittsgarantie</li>
               <li>Kleingruppen-Garantie</li>
               <li>Durchführungsgarantie</li>
            </ul>
         </div>
      </div>
      <div class="courses-sidebar-element clearfix">
         <div class="courses-sidebar-icon share"></div>
         <div class="courses-sidebar-element-inner" style="margin-top: 5px;
            color: black!important;">
            <strong>DIESE VERANSTALTUNG TEILEN:</strong> 
            <?php echo do_shortcode('[wbcr_snippet id="28096"]'); ?>
         </div>
      </div>
   </div>
</div>
<?php
endwhile;
}
if (!is_active_sidebar('main-sidebar')) {
return;
}