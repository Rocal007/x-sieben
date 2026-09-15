<?php
function x_sieben_header_view() {
  ?>
  <nav class="navbar navbar-default navbar-custom navbar-fixed-top">
    <div class="container">
      <div class="navbar-header">

        <!-- Hamburger -->
        <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#main-navbar" id="hamburger-toggle" aria-label="Toggle navigation">
          <span class="icon-bar top-bar"></span>
          <span class="icon-bar middle-bar"></span>
          <span class="icon-bar bottom-bar"></span>
        </button>

        <!-- Centered Logo -->
        <a class="navbar-brand centered-logo" href="<?= esc_url(home_url('/')); ?>">
          <?php if (function_exists('the_custom_logo') && has_custom_logo()): ?>
            <?= the_custom_logo(); ?>
          <?php else: ?>
            <span class="site-title"><?= get_bloginfo('name'); ?></span>
          <?php endif; ?>
        </a>

        <!-- Search icon -->
        <button type="button" class="navbar-search-icon" id="toggle-search" aria-label="Toggle search">
          <i class="fa fa-search" aria-hidden="true"></i>
        </button>
      </div>

      <!-- Navigation Menu -->
      <div class="collapse navbar-collapse" id="main-navbar">
        <?php
        wp_nav_menu([
          'theme_location' => 'primary',
          'menu_class'     => 'nav navbar-nav',
          'container'      => false,
          'fallback_cb'    => '__return_false',
          'depth'          => 2, // optional: limit menu depth
        ]);
        ?>
      </div>

      <!-- Hidden Search Form -->
      <div class="navbar-search-form" id="search-form" style="display: none;">
        <form method="get" action="<?= esc_url(home_url('/')); ?>" class="form-inline" role="search">
          <input type="search" name="s" class="form-control" placeholder="Search..." aria-label="Search">
        </form>
      </div>
    </div>
  </nav>
  <?php
}
