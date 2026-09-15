<?php /**
 * Template part for displaying page content in courses_full.php
 *
 *
 * @package sieben
 */
?>
<style>
/* Einheitliche Icon-Farbe für alle Viewports */
.x7-kurse-einleitung i {
  color: #007c90;
  margin-right: 8px;
}

/* Nur für Desktop */
@media (min-width: 769px) {
  .x7-kurse-einleitung h2 {
    font-size: 32px;
    font-weight: 600;
    margin-bottom: 15px;
    color: #00324a;
  }

  .x7-kurse-einleitung li {
    font-size: 20px;
    margin: 8px 0;
    color: #00324a;
  }
}
</style>

<div class="x7-kurse-einleitung" style="text-align: center; margin-bottom: 30px;">
  <h1>So profitieren Sie bei X SIEBEN</h1>
  <ul style="list-style: none; padding: 0;">
    <li><i class="fa-solid fa-certificate"></i>ISO 17024, TÜV, IPMA®pma, Scrum-Zertifizierungen</li>
    <li><i class="fa-solid fa-users"></i>Max. 6 Teilnehmer:innen pro Gruppe</li>
    <li><i class="fa-solid fa-chalkboard-user"></i>Persönliches Mentoring & Karriereberatung</li>
    <li><i class="fa-solid fa-hand-holding-dollar"></i>Kursförderung möglich</li>
    <li><i class="fa-solid fa-laptop"></i>Live-Online, Präsenz- & Hybridformate</li>
  </ul>
</div>
<style>

  .x7-courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 32px;
    padding: 40px 20px;
    max-width: 1200px;
    margin: 0 auto;
  }

  .x7-course-card {
    background: #ffffff;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    box-shadow: 0 0 0 rgba(0, 0, 0, 0);
  }

  .x7-course-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
  }

  .x7-course-card img {
    max-width: 100%;
    max-height: 200px;
    object-fit: contain;
    margin-bottom: 16px;
    border-radius: 8px;
  }

  .x7-course-card p {
    font-size: 16px;
    font-weight: 600;
    color: #00324a;
    margin: 0;
  }
</style>

<?php
// Alle Seiten abrufen, die das Template 'kurskategorien.php' verwenden
$args = array(
    'post_type' => 'page',
    'meta_key' => '_wp_page_template',
    'meta_value' => 'kurscategorien.php',
    'orderby' => 'title',
    'order' => 'ASC'
);
$kursseiten = get_pages($args);
//var_dump($kursseiten);
?>

<section class="x7-courses-grid">
<?php foreach ($kursseiten as $kurs) : 
    $kurs_link = get_permalink($kurs->ID);
    $kurs_title = get_the_title($kurs->ID);
    $kurs_image = get_the_post_thumbnail_url($kurs->ID, 'medium'); // Thumbnail
?>
    <div class="x7-course-card">
        <a href="<?php echo esc_url($kurs_link); ?>">
            <?php if ($kurs_image) : ?>
                <img src="<?php echo esc_url($kurs_image); ?>" alt="<?php echo esc_attr($kurs_title); ?>">
            <?php endif; ?>
        </a>
        <p><?php echo esc_html($kurs_title); ?></p>
    </div>
<?php endforeach; ?>
</section>


