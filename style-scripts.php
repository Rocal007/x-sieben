<?php
/**
 * Enqueue scripts and styles.
 */
function seopress_scripts()
{
    //Load Faq css file
    wp_enqueue_style('faq', get_template_directory_uri() . '/inc/core/components/faq/faq.css', true, 'screen');
    
        //Load bootstrap css
    wp_enqueue_style('bootstrap', get_template_directory_uri() . '/css/bootstrap.css', array(), '3.3.6', 'all');
    
    //Load font-awesome file
    wp_enqueue_style('font-awesome', get_template_directory_uri() . '/css/font-awesome.css', array(), '4.7.0', 'all');
    


    //Load Tipps css file
    wp_enqueue_style('tipps', get_template_directory_uri() . '/inc/core/components/tipps/tipps.css',  true, 'screen');
    
    //Load Video css file
    wp_enqueue_style('video', get_template_directory_uri() . '/inc/core/components/video/video.css',  true, 'screen');
    
    // Load Icons css file
    wp_enqueue_style('icons', get_template_directory_uri() . '/assets/icons.css',  true, 'screen');
    
    // Load Punsh lines css file
    wp_enqueue_style('punsh_lines', get_template_directory_uri() . '/inc/core/components/punsh_lines/punsh_lines.css',  true, 'screen');
    
    // Load Top Bar css file
    wp_enqueue_style('top_bar', get_template_directory_uri() . '/inc/core/components/top_bar/top_bar.css', true, 'screen');
    
    // Load Subtexte css file
    wp_enqueue_style('subtexte', get_template_directory_uri() . '/inc/core/components/subtexte/subtexte.css',  true, 'screen');
    
    // Load Favorite Blogs css file
    wp_enqueue_style('favorite_blogs', get_template_directory_uri() . '/inc/core/components/favorite_blogs/favorite_blogs.css',  true, 'screen');
    
    // Load Timeline css file
    wp_enqueue_style('timeline', get_template_directory_uri() . '/inc/core/components/timeline/timeline.css',  true, 'screen');
    
    // Load Vorteile css file
    wp_enqueue_style('vorteile', get_template_directory_uri() . '/inc/core/components/vorteile/vorteile.css', true, 'screen');
    
    // Load Images css file
    wp_enqueue_style('images', get_template_directory_uri() . '/inc/core/components/images/images.css',  true, 'screen');
    
    // Load Contact css file
    wp_enqueue_style('contact', get_template_directory_uri() . '/inc/core/components/contact/contact.css',  true, 'screen');
    
    // Load Claim css file
    wp_enqueue_style('claim', get_template_directory_uri() . '/inc/core/components/contact/contact.css',  true, 'screen');

    // Load Footer css file
    wp_enqueue_style('footer', get_template_directory_uri() . '/css/footer.css',  true, 'screen');

    // Load Header css file
    wp_enqueue_style('header', get_template_directory_uri() . '/css/header.css',  true, 'screen');
    
    // Load Main Text css file
    wp_enqueue_style('main_text', get_template_directory_uri() . '/inc/core/components/main_text/main_text.css',  true, 'screen');

    // Load Top Picture css file
    wp_enqueue_style('top_picture', get_template_directory_uri() . '/inc/core/components/top_picture/top_picture.css',  true, 'screen');

    // Load Listen css file
    wp_enqueue_style('listen', get_template_directory_uri() . '/inc/core/components/listen/listen.css',  true, 'screen');

    // Load Cta Boxes css file
    wp_enqueue_style('cta_boxes', get_template_directory_uri() . '/inc/core/components/cta_boxes/cta_boxes.css',  true, 'screen');

    // Load Backlinks css file
    wp_enqueue_style('backlinks', get_template_directory_uri() . '/inc/core/components/backlinks/backlinks.css',  true, 'screen');

    // Load Menues css file
    wp_enqueue_style('menues', get_template_directory_uri() . '/inc/core/components/menues/menues.css',  true, 'screen');

    // Load Overview Texte css file
    wp_enqueue_style('overview_texte', get_template_directory_uri() . '/inc/core/components/overview_texte/overview_texte.css',  true, 'screen');
	
	// Load Logo css file
    wp_enqueue_style('logo', get_template_directory_uri() . '/inc/core/components/logo/logo.css',  true, 'screen');

    // Load Buttons css file
    wp_enqueue_style('custom-buttons', get_template_directory_uri() . '/inc/core/components/custom-buttons/custom-buttons.css',  true, 'screen');

    // Load Kosten css file
    wp_enqueue_style('kosten', get_template_directory_uri() . '/inc/core/components/kosten/kosten.css',  true, 'screen');

    //Load css/style.css file
    wp_enqueue_style('seopress-style-core', get_template_directory_uri() . '/css/style.css',  true, 'screen');
    
/* Load scripts
	* @ https://codex.wordpress.org/Function_Reference/wp_enqueue_script
	* Usages wp_enqueue_script( $handle, $src, $deps, $ver, $in_footer );
	*/
    
    // Load bootstrap js
    wp_enqueue_script('bootstrap', get_template_directory_uri() . '/js/bootstrap.js', array('jquery'), '3.3.6', true);
    
    // Load html5shiv
    //wp_enqueue_script('html5shiv', get_template_directory_uri() . '/js/html5shiv.js', array(), '3.7.3', false);
    //wp_script_add_data('html5shiv', 'conditional', 'lt IE 9');
    
    /* Load respond js*/
    //wp_enqueue_script('respond', get_template_directory_uri() . '/js/respond.js', array(), false);
    //wp_script_add_data('respond', 'conditional', 'lt IE 9');
}

add_action('wp_enqueue_scripts', 'seopress_scripts');