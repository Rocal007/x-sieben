<?php

/**
 * CourseRenderer
 *
 * Handles HTML rendering for COURSE_Model objects with enhanced SEO, accessibility, and JSON-LD.
 *
 * @package sieben
 */
class CourseRenderer
{
    /**
     * Renders courses based on the specified layout type.
     */
    public static function render($courses, string $layout_type): void
    {
        if (!is_array($courses)) {
            $courses = [$courses];
        }

        foreach ($courses as $course) {
            switch ($layout_type) {
                case 'card':
                    self::renderCard($course);
                    break;
                case 'grid-card':
                    self::renderGridCard($course);
                    break;
                case 'list':
                    self::renderListItem($course);
                    break;
                case 'featured':
                    self::renderFeatured($course);
                    break;
                case 'x-sieben-card-rich':
                    self::renderCardRich($course);
                    break;
                default:
                    self::renderCard($course);
                    break;
            }
        }

        echo self::getJsonLdSchemaMultiple($courses);
    }

    /**
     * Renders a single course as a card.
     */
    public static function renderCard(COURSE_Model $course): void
    {
        $img_url = $course->getFeaturedImage();
        $title = esc_html($course->getTitle());
        $subtitle = esc_html($course->untertitel);
?>
        <div class="col-md-6 courses-2-col">
            <div class="courses-badge-container">
                <span>
                    <a href="<?= esc_url($course->permalink); ?>" title="Kurs: <?= $title; ?>" aria-label="Zum Kurs <?= $title; ?>"><?= $title; ?></a>
                </span>
            </div>
            <div>
                <a href="<?= esc_url($course->permalink); ?>" title="Mehr Informationen zum Kurs: <?= $title; ?>" aria-label="Zur Kursseite von <?= $title; ?>">
                    <img src="<?= esc_url($img_url); ?>" alt="Bild für den Kurs: <?= $title; ?> - <?= $subtitle; ?>">
                </a>
            </div>
        </div>
    <?php
    }

    /**
     * Renders a single course as a modern grid card.
     */
    public static function renderGridCard(COURSE_Model $course): void
    {
        $img_url = $course->mobile_image_url;
        $title = esc_html($course->getTitle());
    ?>
        <div class="x7-course-card">
            <a href="<?= esc_url($course->permalink); ?>" aria-label="<?= $title; ?>">
                <img src="<?= esc_url($img_url); ?>" alt="<?= $title; ?>">
            </a>
            <p><?= $title; ?></p>
        </div>
    <?php
    }

    /**
     * Renders the header row for list layout.
     */
    public static function renderListHeader(): void
    {
    ?>
        <div class="row list-header font-weight-bold pdtb10 border-bottom" role="row">
            <div class="col-md-4" role="columnheader">Kurs</div>
            <div class="col-md-3" role="columnheader">
                <button class="sort-btn" data-sort="date" aria-label="Sortieren nach Datum">Datum <i class="fa fa-sort" aria-hidden="true"></i></button>
            </div>
            <div class="col-md-1" role="columnheader">
                <button class="sort-btn" data-sort="units" aria-label="Sortieren nach LE">LE <i class="fa fa-sort" aria-hidden="true"></i></button>
            </div>
            <div class="col-md-2" role="columnheader">
                <button class="sort-btn" data-sort="price" aria-label="Sortieren nach Preis">Preis <i class="fa fa-sort" aria-hidden="true"></i></button>
            </div>
            <div class="col-md-2" role="columnheader">Aktionen</div>
        </div>
    <?php
    }

    /**
     * Renders a single course as a list item.
     */
    public static function renderListItem(COURSE_Model $course): void
    {
        $permalink = esc_url($course->getPermalink());
        $title = esc_html($course->getTitle());
        $start_date = !empty($course->start_datum) ? date('d.m.Y', strtotime($course->start_datum)) : '';
        $end_date = !empty($course->end_datum) ? date('d.m.Y', strtotime($course->end_datum)) : '';
        $units = $course->anzahl_le;
        $cost = $course->preis_netto_formatted;
        $data_price = !empty($course->preis_netto) ? floatval(str_replace(',', '.', str_replace('.', '', $course->preis_netto))) : 0;

    ?>
        <article id="post-<?= esc_attr($course->post_id); ?>" class="list-item" 
                 data-date="<?= esc_attr(!empty($course->start_datum) ? strtotime($course->start_datum) : 0); ?>" 
                 data-units="<?= esc_attr($units); ?>" 
                 data-price="<?= esc_attr($data_price); ?>">
            <div class="row" role="row">
                <div class="col-md-4">
                    <div class="list-heading mrb5">
                        <a href="<?= $permalink; ?>" rel="bookmark"><?= $title; ?></a>
                    </div>
                </div>
                <div class="col-md-3">
                    <?php if ($start_date): ?>
                        <div class="list-date">
                            <i class="fa fa-calendar" aria-hidden="true"></i> <?= $start_date; ?>
                            <?php if ($end_date): ?>- <?= $end_date; ?><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-1">
                    <?php if ($units): ?>
                        <span class="course-le">LE <?= $units; ?></span>
                    <?php endif; ?>
                </div>
                <div class="col-md-2">
                    <?php if ($cost): ?>
                        <span class="course-price">€ <?= $cost; ?></span>
                    <?php endif; ?>
                </div>
                <div class="col-md-2 pull-right">
                    <div class="btn-group-accessible" role="group" aria-label="Kursaktionen für <?= $title; ?>">
                        <a href="<?= $permalink; ?>" class="btn btn-warning btn-sm mrr10" style="min-width:44px; min-height:44px; display:inline-flex; align-items:center; justify-content:center;" aria-label="Kursunterlagen herunterladen"><i class="fa fa-download" aria-hidden="true"></i></a>
                        <a href="<?= $permalink; ?>" class="btn btn-danger btn-sm" style="min-width:44px; min-height:44px; display:inline-flex; align-items:center; justify-content:center;" aria-label="Details zum Kurs <?= $title; ?>"><i class="fa fa-info" aria-hidden="true"></i></a>
                    </div>
                </div>
            </div>
        </article>
    <?php
    }

    public static function renderCardRich(COURSE_Model $course): void
    {
        $permalink = esc_url($course->getPermalink());
        $title = esc_html($course->getTitle());
        $subtitle = !empty($course->untertitel) ? esc_html($course->untertitel) : '';
        $start_date = !empty($course->start_datum) ? $course->start_datum : '';
        $end_date = !empty($course->end_datum) ? $course->end_datum : '';
        $units = $course->anzahl_le;
        $cost = $course->preis_netto_formatted;
        $course_types = $course->getCourseTypes();
        $education_fields = $course->getFieldOfEducations();
    ?>
        <article id="post-<?= esc_attr($course->post_id); ?>" class="pd20 courses-media-body media container">
            <div class="row">
                <?php if ($course->hasFeaturedImage()): ?>
                    <div class="search-media-left col-md-4">
                        <a href="<?= $permalink; ?>" title="Details zum Kurs <?= $title; ?> anzeigen">
                            <img src="<?= esc_url($course->mobile_image_url); ?>" alt="Bild zum Kurs <?= $title; ?>">
                        </a>
                    </div>
                <?php endif; ?>
                <div class="search-media-body col-md-8">
                    <div class="row">
                        <div class="col-md-8">
                            <h5 class="media-heading entry-title cat-courses-title">
                                <strong>
                                    <a href="<?= $permalink; ?>" rel="bookmark"><?= $title; ?></a>
                                </strong>
                            </h5>
                            <?php if ($subtitle): ?>
                                <div class="entry-summary pdb20 mrb20"><?= $subtitle; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 small">
                            <?php if ($start_date): ?>
                                <div class="course-date" aria-label="Kursdaten: Start am <?= $start_date; ?><?php if ($end_date) echo ', Ende am ' . $end_date; ?>">
                                    <i class="fa fa-calendar" aria-hidden="true"></i> <?= $start_date; ?>
                                    <?php if ($end_date): ?> - <?= $end_date; ?><?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($course->abschluss)): ?>
                                <div class="mrt10"><strong>Abschluss:</strong> <?= esc_html($course->abschluss); ?></div>
                            <?php endif; ?>
                            <div class="mrt20 hidden-xs">
                                <?php echo certs_courses($course->post_id, 'horizontal', ''); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-3 cta-courses">
                    <div class="btn-group" role="group" aria-label="Kursaktionen für <?= $title; ?>">
                        <a href="<?= $permalink; ?>#heading-last" class="btn btn-primary btn-sm mrr10" style="min-width:44px; min-height:44px; display:inline-flex; align-items:center; justify-content:center;" aria-label="E-Mail Anfrage senden"><i class="fa fa-envelope" aria-hidden="true"></i></a>
                        <a href="<?= $permalink; ?>" class="btn btn-warning btn-sm mrr10" style="min-width:44px; min-height:44px; display:inline-flex; align-items:center; justify-content:center;" aria-label="Infomaterial herunterladen"><i class="fa fa-download" aria-hidden="true"></i></a>
                        <a href="<?= $permalink; ?>" class="btn btn-danger btn-sm" style="min-width:44px; min-height:44px; display:inline-flex; align-items:center; justify-content:center;" aria-label="Mehr Informationen"><i class="fa fa-info" aria-hidden="true"></i></a>
                    </div>
                </div>
                <div class="col-md-9 hidden-xs">
                    <div class="col-md-8">
                        <?php if (!empty($course_types)): ?>
                            <span class="label label-danger"><?= implode('</span> <span class="label label-danger">', $course_types); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($education_fields)): ?>
                            <span class="label label-warning"><?= implode('</span> <span class="label label-warning">', $education_fields); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-right">
                        <?php if ($units): ?><span class="course-le mrr10">LE <?= $units; ?></span><?php endif; ?>
                        <?php if ($cost): ?><span class="course-price">€ <?= $cost; ?></span><?php endif; ?>
                    </div>
                </div>
            </div>
        </article>
    <?php
    }

	/**
     * Renders the single course accordion (CLEAN VERSION - NO ARIA ERRORS)
     */
	public static function renderSingleAccordion(COURSE_Model $course): void
	{
		$tabs = $course->getAccordionTabs();
		if (empty($tabs)) return;
?>
<section class="courses-accordion" aria-label="Kurs Informationen">
	<div class="panel-group" id="courses-accordion">
		<?php foreach ($tabs as $key => $tab):
		$panel_id = 'collapse-' . $key;
		$heading_id = 'heading-' . $key;
		?>
		<div class="panel panel-default">
			<div class="panel-heading" id="<?= esc_attr($heading_id); ?>">
				<h3 class="panel-title">
					<a data-toggle="collapse" 
					   data-parent="#courses-accordion"
					   href="#<?= esc_attr($panel_id); ?>">
						<i class="fa fa-caret-right" aria-hidden="true"></i>
						<?= esc_html($tab['title']); ?>
					</a>
				</h3>
			</div>
			<div id="<?= esc_attr($panel_id); ?>" class="panel-collapse collapse">
				<div class="panel-body">
					<?= $tab['content']; ?>
				</div>
			</div>
		</div>
		<?php endforeach; ?>

		<div class="panel panel-default">
			<div class="panel-heading" id="heading-last">
				<h3 class="panel-title">
					<a data-toggle="collapse" 
					   data-parent="#courses-accordion"
					   href="#collapse-last">
						<i class="fa fa-envelope" aria-hidden="true"></i> Angebot holen | Buchung
					</a>
				</h3>
			</div>
			<div id="collapse-last" class="panel-collapse collapse in">
				<div class="panel-body">
					<?= shortcode_exists('wpforms') ? do_shortcode('[wpforms id="60468" title="false"]') : ''; ?>
				</div>
			</div>
		</div>
	</div>
</section>
<?php
}

    /**
     * Renders the interest section.
     */
    public static function renderInterestSection(COURSE_Model $course): void
    {
    ?>
   <div class="container-fluid bg-image">
    <section aria-labelledby="interest-section-title" class="xs-course-promise">
        <div class="container">

            <div class="xs-course-promise__intro text-center">
                <div class="xs-course-promise__eyebrow">
                    X SIEBEN @ KOMPETENZENTWICKLUNG
                </div>

                <h3 id="interest-section-title" class="xs-course-promise__title">
                    Kompetenz, die in der Praxis wirkt.
                </h3>

         
            </div>

            <div class="xs-course-promise__items">

                <div class="xs-course-promise__item">
                    <strong>Klarheit schaffen.</strong>
                    <span>Wissen, was wirklich zählt.</span>
                </div>

                <div class="xs-course-promise__item">
                    <strong>Kompetenz entwickeln.</strong>
                   <span>Können, was wirklich gebraucht wird.</span>
                </div>

                <div class="xs-course-promise__item">
                    <strong>Wirksam umsetzen.</strong>
                    <span>Damit aus Wissen Wirkung wird.</span>
                </div>

            </div>

        </div>
    </section>
</div>
    <?php
    }

    public static function renderLayoutSwitcher(string $activeLayout = 'card'): void
    {
        $layouts = [
            'x-sieben-card-rich' => ['icon' => 'fa-th-large', 'label' => 'Info'],
            'grid-card'          => ['icon' => 'fa-th',       'label' => 'Grid-Ansicht'],
            'list'               => ['icon' => 'fa-list',     'label' => 'Listenansicht'],
        ];
    ?>
        <div class="course-layout-switcher pull-right pd20" role="group" aria-label="Layout Auswahl">
            <?php foreach ($layouts as $layout => $data):
                $is_active = $layout === $activeLayout;
            ?>
                <button class="layout-switch btn"
                    data-layout="<?= esc_attr($layout); ?>"
                    aria-pressed="<?= $is_active ? 'true' : 'false'; ?>"
                    title="<?= esc_attr($data['label']); ?>"
                    aria-label="<?= esc_attr($data['label']); ?>">
                    <i class="fa <?= esc_attr($data['icon']); ?>" aria-hidden="true"></i>
                </button>
            <?php endforeach; ?>
        </div>
    <?php
    }

    protected static function getJsonLdSchemaMultiple(array $courses): string
    {
        $schema_array = [];
        foreach ($courses as $course) {
            $schema_array[] = [
                '@context' => 'https://schema.org',
                '@type' => 'Course',
                'name' => $course->getTitle(),
                'description' => $course->untertitel,
                'url' => $course->permalink,
                'image' => $course->getFeaturedImage(),
                'provider' => [
                    '@type' => 'Organization',
                    'name' => 'Sieben GmbH',
                    'sameAs' => get_bloginfo('url'),
                ],
                'offers' => [
                    '@type' => 'Offer',
                    'price' => $course->preis_netto_formatted,
                    'priceCurrency' => 'EUR',
                    'availability' => 'https://schema.org/InStock',
                    'url' => $course->permalink,
                ]
            ];
        }
        return '<script type="application/ld+json">' . json_encode($schema_array, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }

    // Helper fallbacks
    public static function renderRelatedCourses(): void { if (function_exists('related_courses_view')) related_courses_view(); }
    public static function renderResponsiveTopPicture(array $args): void { if (function_exists('render_responsive_top_picture')) render_responsive_top_picture($args); }
    public static function renderCoursesFAQ(): void { if (function_exists('render_courses_faq')) render_courses_faq(); }
    public static function renderSingleCourseContent(COURSE_Model $course): void {
        ?>
        <header class="courses-divider-heading"><h2 id="course-title"><?php echo esc_html($course->excerpt); ?></h2></header>
        <article class="course-content"><?php echo $course->content; ?></article>
        <section class="vortragende-desktop pdtb30" aria-label="ReferentInnen">
            <strong>ReferentInnen:</strong> <span id="vortragende"><?php if (function_exists('trainer_course_single')) trainer_course_single(); ?></span>
        </section>
        <?php
    }
}