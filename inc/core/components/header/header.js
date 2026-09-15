jQuery(document).ready(function($){
  $('#hamburger-toggle').on('click', function() {
    $(this).toggleClass('active');
  });

  $('#toggle-search').on('click', function(e){
    e.preventDefault();
    $('#search-form').slideToggle(200);
  });
});