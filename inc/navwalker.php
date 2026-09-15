<?php
/**
 * Bootstrap 3 NavWalker for WordPress with ARIA + clickable parents + hover support
 * Fixed: Updated top-level roles from 'button' to 'menuitem' to satisfy menubar requirements.
 */

if (! class_exists('WP_BS3_Navwalker')) :

class WP_BS3_Navwalker extends Walker_Nav_Menu {

    public function start_lvl(&$output, $depth = 0, $args = array()) {
        $indent = str_repeat("\t", $depth);
        $output .= "\n$indent<ul class=\"dropdown-menu\" role=\"menu\" aria-label=\"Submenu\">\n";
    }

    public function end_lvl(&$output, $depth = 0, $args = array()) {
        $indent = str_repeat("\t", $depth);
        $output .= "$indent</ul>\n";
    }

    public function start_el(&$output, $item, $depth = 0, $args = array(), $id = 0) {
        $indent = ($depth) ? str_repeat("\t", $depth) : '';

        $classes = empty($item->classes) ? array() : (array) $item->classes;
        $classes[] = 'menu-item-' . $item->ID;
        $has_children = in_array('menu-item-has-children', $classes);

        $li_classes = array();
        if ($has_children && $depth === 0) $li_classes[] = 'dropdown';
        if (in_array('current-menu-item', $classes) || in_array('current-menu-ancestor', $classes)) $li_classes[] = 'active';

        $li_class_names = $li_classes ? ' class="' . esc_attr(implode(' ', $li_classes)) . '"' : '';

        // role="none" sorgt dafür, dass das li die menubar -> menuitem Kette nicht unterbricht
        $output .= $indent . '<li' . $li_class_names . ' role="none">';

        $atts = array();
        $atts['title']  = ! empty($item->attr_title) ? $item->attr_title : $item->title;
        $atts['target'] = ! empty($item->target) ? $item->target : '';
        $atts['rel']    = ! empty($item->xfn) ? $item->xfn : '';
        $atts['href']   = ! empty($item->url) ? $item->url : '';

        // FIX: Auch Top-Level Dropdowns müssen role="menuitem" haben, wenn die UL eine menubar ist
        $atts['role'] = 'menuitem';
        $atts['aria-label'] = $item->title;

        if ($has_children && $depth === 0) {
            $atts['class'] = 'dropdown-toggle';
            $atts['data-toggle'] = 'dropdown';
            $atts['aria-haspopup'] = 'true';
            $atts['aria-expanded'] = 'false';
            // aria-label für Submenü-Kontext beibehalten
            $atts['aria-label'] = $item->title . ' submenu';
        }

        $attributes = '';
        foreach ($atts as $attr => $value) {
            if (! empty($value)) {
                $value = ('href' === $attr) ? esc_url($value) : esc_attr($value);
                $attributes .= ' ' . $attr . '="' . $value . '"';
            }
        }

        $title = apply_filters('the_title', $item->title, $item->ID);

        $item_output  = $args->before;
        $item_output .= '<a' . $attributes . '>' . $args->link_before . $title;
        if ($has_children && $depth === 0) {
            $item_output .= ' <span class="caret" aria-hidden="true"></span>';
        }
        $item_output .= $args->link_after . '</a>';
        $item_output .= $args->after;

        $output .= apply_filters('walker_nav_menu_start_el', $item_output, $item, $depth, $args);
    }

    public function end_el(&$output, $item, $depth = 0, $args = array()) {
        $output .= "</li>\n";
    }

    public static function fallback($args) {
        if (current_user_can('manage_options')) {
            echo '<ul class="nav navbar-nav" role="menubar"><li role="none"><a role="menuitem" href="' .
                 esc_url(admin_url('nav-menus.php')) . '">Add a menu</a></li></ul>';
        }
    }
}

endif;

if ( ! class_exists( 'WP_BS3_Mega_Navwalker' ) ) :

class WP_BS3_Mega_Navwalker extends Walker_Nav_Menu {

    protected $current_item;

    public function start_lvl( &$output, $depth = 0, $args = array() ) {
        $indent = str_repeat("\t", $depth);
        $is_mega_menu = ( isset( $this->current_item->has_mega_menu ) && $this->current_item->has_mega_menu );

        if ( $is_mega_menu && $depth === 0 ) {
            $output .= "\n" . $indent . '<ul class="dropdown-menu mega-menu-dropdown" role="menu">' . "\n";
            $output .= '<div class="container-fluid"><div class="row">' . "\n";
        } else {
            $output .= "\n" . $indent . '<ul class="dropdown-menu" role="menu">' . "\n";
        }
    }

    public function end_lvl( &$output, $depth = 0, $args = array() ) {
        $indent = str_repeat("\t", $depth);
        $is_mega_menu = ( isset( $this->current_item->has_mega_menu ) && $this->current_item->has_mega_menu );

        if ( $is_mega_menu && $depth === 0 ) {
            $output .= '</div></div>' . "\n"; 
            $output .= $indent . "</ul>\n";
        } else {
            $output .= $indent . "</ul>\n";
        }
    }

    public function start_el(&$output, $item, $depth = 0, $args = array(), $id = 0)
    {
        $this->current_item = $item;
        $indent = ($depth) ? str_repeat("\t", $depth) : '';

        $classes = empty($item->classes) ? array() : (array) $item->classes;
        $classes[] = 'menu-item-' . $item->ID;

        $is_mega_menu = ($depth === 0 && in_array('mega-menu', $classes));
        if ($is_mega_menu) {
            $item->has_mega_menu = true;
            $args->has_children = true;
        }

        $class_names = join(' ', apply_filters('nav_menu_css_class', array_filter($classes), $item, $args));
        $is_mega_column = ($depth === 1 && isset($item->has_mega_menu) && $item->has_mega_menu);

        if (($args->has_children || $is_mega_menu) && !$is_mega_column) {
            $class_names .= ' dropdown';
        }

        if (in_array('current-menu-item', $classes) || in_array('current-menu-ancestor', $classes)) {
            $class_names .= ' active';
        }

        if ($is_mega_menu) { $class_names .= ' mega-menu'; }
        if ($is_mega_column) { $class_names .= ' col-sm-3'; }

        $class_names = $class_names ? ' class="' . esc_attr($class_names) . '"' : '';
        $output .= $indent . '<li' . $class_names . ' role="none">';

        $atts = array();
        $atts['title']  = ! empty($item->attr_title) ? $item->attr_title : '';
        $atts['target'] = ! empty($item->target)     ? $item->target     : '';
        $atts['rel']    = ! empty($item->xfn)        ? $item->xfn        : '';
        $atts['href']   = ! empty($item->url)        ? $item->url        : '';
        
        // FIX: Immer role="menuitem" für Kinder einer menubar/menu
        $atts['role'] = 'menuitem';

        if (($args->has_children || $is_mega_menu) && $depth === 0) {
            $atts['data-toggle']    = 'dropdown';
            $atts['class']          = 'dropdown-toggle';
            $atts['aria-haspopup']  = 'true';
        }

        $attributes = '';
        foreach ($atts as $attr => $value) {
            if (! empty($value)) {
                $value = ('href' === $attr) ? esc_url($value) : esc_attr($value);
                $attributes .= ' ' . $attr . '="' . $value . '"';
            }
        }

        $item_output = $args->before;
        $is_mega_content_item = ($depth === 1 && isset($item->has_mega_menu) && $item->has_mega_menu);
        $is_post_type = ($item->object === 'page' || $item->object === 'post');

        global $post;
        $original_post = $post;
        if (isset($item->object_id)) { $post = get_post($item->object_id); }

        $item_output .= '<a' . $attributes . '>';
        
        if ($is_mega_content_item && $is_post_type && has_post_thumbnail($item->object_id)) {
             $item_output .= get_the_post_thumbnail($item->object_id, 'thumbnail');
             $item_output .= '<span>' . apply_filters('the_title', $item->title, $item->ID) . '</span>';
        } else {
             $item_output .= $args->link_before . apply_filters('the_title', $item->title, $item->ID) . $args->link_after;
             if ($args->has_children && 0 === $depth) {
                 $item_output .= ' <span class="caret"></span>';
             }
        }
        
        $item_output .= '</a>';
        $post = $original_post;
        $item_output .= $args->after;
        $output .= apply_filters('walker_nav_menu_start_el', $item_output, $item, $depth, $args);
    }

    public function display_element($element, &$children_elements, $max_depth, $depth, $args, &$output)
    {
        if (!$element) return;
        $id_field = $this->db_fields['id'];
        if (!empty($children_elements[$element->$id_field]) && isset($element->has_mega_menu)) {
            foreach ($children_elements[$element->$id_field] as $child) {
                $child->has_mega_menu = $element->has_mega_menu;
            }
        }
        parent::display_element($element, $children_elements, $max_depth, $depth, $args, $output);
    }
}
endif;