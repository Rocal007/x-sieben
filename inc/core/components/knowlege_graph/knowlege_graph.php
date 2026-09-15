<?php
function display_knowledge_graph_box()
{
  $custom_logo_id = get_theme_mod('custom_logo');
  $logo_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : '';

  ob_start();
?>
  <div class="container-fluid pdtb-default">
    <div class="conteiner">
      <div class="knowledge-graph-box text-center">
        <div class="pdtb30"><?php if ($logo_url): ?>
            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php bloginfo('name'); ?> Logo" class="knowledge-graph-logo">
          <?php endif; ?>
        </div>
        <strong class="knowledge-graph-title">Wikidata & Knowledge Graph</strong>
        <p class="knowledge-graph-text">
          Offizieller strukturierter Eintrag für den Google Knowledge Graph:<br>
          <a href="https://www.wikidata.org/wiki/Q134607545" target="_blank" rel="noopener" class="knowledge-graph-link">
            X SIEBEN Wirtschaftstraining GmbH
          </a> &nbsp;|&nbsp;
          <a href="https://www.wikidata.org/wiki/Q135442984" target="_blank" rel="noopener" class="knowledge-graph-link">
            Mag. Dr. Johannes Gasberger (CEO)
          </a>
        </p>
      </div>
    </div>
  </div>
<?php
  return ob_get_clean();
}
add_shortcode('knowledge_graph_box', 'display_knowledge_graph_box');
