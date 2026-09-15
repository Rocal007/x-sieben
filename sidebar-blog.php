<div id="sidebar-single-blog" class="right pdt20">
   <div class="courses-sidebar-inner">
      <div class="courses-sidebar-element clearfix">
         <h4 class="sidebar-heading">weitere Themen...</h4>
         <ul class="category-menu">
            <?php
            wp_list_categories(array(
               'title_li'    => '', // Removes default title
               'orderby'     => 'name',
               'show_count'  => false, // Don't show post count
               'hide_empty'  => true, // Hide empty categories
               'exclude'     => 11145, // Exclude category ID 11145
            ));
            ?>
         </ul>
      </div>
   </div>
   <div class="courses-sidebar-element clearfix">
      <div id="search">
         <form role="search" method="get" id="searchform" class="searchform" action="https://www.x-sieben.at/">
            <div class="input-group search-box-input">
               <input type="text" value="" name="s" id="s" class="form-control search-box-form" placeholder="Kurse finden ...">
               <span class="input-group-btn search-button-span">
                  <button class="btn btn-info search-button" type="submit"><i class="fa fa-search"></i></button>
               </span>
            </div>
         </form>
         <div class="clear"> </div>
      </div>
   </div>
</div>