<?php
function fly_out()
{ ?>
   <div class="hidden">
	   
 <input type="checkbox" id="toggle">

    <label for="toggle" class="toggle-btn">
        Menu
    </label>

    <div class="flyout-container" id="flyout-menu">
        <?php wp_nav_menu(
            array(
                'menu' => 'burger_menue',
                'menu_class' => 'flyout', // UL Class
                'container' => 'div',
                'depth' => 2,
                'walker' => new bootstrap_5_wp_nav_menu_walker()
            )
        ); ?>
    </div>
</div>
<?php }