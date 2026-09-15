<?php

/**
 * Centralized component loader
 * Clean, maintainable, and modular.
 */

function require_components(array $files): void
{
  foreach ($files as $file) {
    $path = get_template_directory() . $file;
    if (file_exists($path)) {
      require_once $path;
    }
  }
}

// Contact
require_components([
  '/inc/core/components/contact/contact-controler.php',
  '/inc/core/components/contact/contact-view.php',
]);



// Courses & Certs
require_components([
  '/inc/core/components/courses_all/courses_all-view.php',
  '/inc/core/components/certs/certs-view.php',
  '/inc/models/course-model.php',
  '/inc/models/course-registry.php',
  '/inc/models/assets-model.php',
  '/inc/renderer/course-renderer.php',
  '/inc/respositories/course-repository.php',
  '/inc/handler/course-ajax.php'
]);

// UI Components
require_components([
  '/inc/core/components/slider/slider-model.php',
  '/inc/core/components/slider/slider-view.php',
  '/inc/core/components/share_buttons/share_buttons-view.php',
  '/inc/core/components/call_back/call_back-view.php',
  '/inc/core/components/paying_add/paying_add-view.php',
  '/inc/core/components/proven_expert/proven_expert-view.php',
  '/inc/core/components/interest/interest-view.php',
  '/inc/core/components/related_courses/related_courses-view.php',
  '/inc/core/components/vorteile/vorteile-view.php',
  '/inc/core/components/top_picture/top_picture-view.php',
  '/inc/core/components/faq/faq-view.php',
  '/inc/core/components/fly_out_menu/fly_out-view.php',
  '/inc/core/components/trainer/trainer-view.php',
  '/inc/core/components/trainer_courses/trainer_courses-view.php',
  '/inc/core/components/topbar/topbar-view.php',
  '/inc/core/components/header/header-view.php',
  '/inc/core/components/footer/footer-view.php',
  '/inc/core/components/c_logos/c_logos-view.php',
  '/inc/core/components/search/search-view.php',
  '/inc/core/components/search/search-controller.php',
  '/inc/core/components/breadcrumbs/breadcurmbs-view.php',
  '/inc/core/components/kunden_logos/kunden_logos-view.php',
  '/inc/core/components/hubs/hubs-view.php',
  '/inc/core/components/course_categories/course_categories-view.php',
  '/inc/core/components/knowlege_graph/knowlege_graph.php', 
]);

// Navwalker
require_components([
  '/inc/navwalker.php'
]);

// CRM
require_components([
  '/inc/core/crm/crm-admin.php',
  '/inc/core/crm/crm-model.php',
  '/inc/core/crm/crm-form.php',
  '/inc/core/crm/crm-settings.php',
  '/inc/core/crm/crm-view-entry.php',
  '/inc/core/crm/controler/output-controler.php',
  '/inc/core/crm/pdf/teilnamebestaetigung.php',
  '/inc/core/crm/pdf/kurszeitenbestaetigung.php',
  '/inc/core/crm/pdf/angebot_kurszeiten.php',
  '/inc/core/crm/pdf/offer.php',
  '/inc/core/crm/pdf/invoice.php',
  '/inc/core/crm/pdf/diplom.php',
  '/inc/core/crm/helpers/normalize.php',
]);
