<?php
function hubs_shortcode($atts, $content = null) {
    // Default title
    $title = isset($atts['title']) ? $atts['title'] : 'Weiterführende Links · IPMA® / pma / GPM / spm-VZPM';

    // Default groups (keep your old array here)
    $default_groups = [
        [
            'links' => [
                ['url' => '/weiterbildung-uebersicht-reserve/ipma-zertifizierungen-ueberblick/', 'label' => 'IPMA Zertifizierungen – Überblick & direkte Links'],
                ['url' => '/ipma-projektmanagement-zertifizierung-im-dach-raum-suedtirol-uebersicht/', 'label' => 'Überblick Länder & Städte'],
                ['url' => '/ipma-projektmanagement-zertifizierung-im-dach-raum-suedtirol-kurse/', 'label' => 'Alle Kurse & Zertifizierungen'],
                ['url' => '/projektmanagement-zertifizierung-im-dach-raum-suedtirol-lehrgaenge/', 'label' => 'Lehrgänge – Deutschland (IPMA®/GPM)'],
            ]
        ],
        [
            'links' => [
                ['url' => '/weiterbildung-uebersicht-reserve/ipma-spm-vzpm-zertifizierung-schweiz/', 'label' => 'Schweiz: pma vs. spm-VZPM'],
                ['url' => '/weiterbildung-uebersicht-reserve/gpm-vs-pma-welche-ipma-zertifizierung-passt-zu-ihrer-karriere/', 'label' => 'Vergleich: pma vs. GPM'],
                ['url' => '/projektmanagement-ipma-pma-zertifizierungsvorbereitungen/', 'label' => 'PM DACH & Südtirol – Vorbereitungen (Hub)'],
            ]
        ],
        [
            'title' => 'Zertifizierungscoaching:',
            'links' => [
                ['url' => '/weiterbildung-uebersicht-reserve/zertifizierungscoaching-ipma-pma-level-b-c-d-zertifizierung/', 'label' => 'pma (AT)'],
                ['url' => '/weiterbildung-uebersicht-reserve/zertifizierungscoaching-ipma-gpm-level-b-c-d-zertifizierung/', 'label' => 'GPM (DE)'],
                ['url' => '/weiterbildung-uebersicht-reserve/ipma-spm-vzpm-zertifizierungscoaching-schweiz/', 'label' => 'spm-VZPM (CH)'],
            ]
        ]
    ];

    // If a 'groups' parameter is passed, use it; otherwise default
    $groups = isset($atts['groups']) && is_array($atts['groups']) ? $atts['groups'] : $default_groups;

    ob_start(); ?>

    <div class="hubs">
        <details class="hubs-details">
            <summary class="hubs-summary"><?php echo esc_html($title); ?></summary>

            <?php foreach ($groups as $group): ?>
                <p>
                    <?php if (!empty($group['title'])): ?>
                        <?php echo esc_html($group['title']) . ' '; ?>
                    <?php endif; ?>

                    <?php
                        $links = [];
                        foreach ($group['links'] as $link) {
                            $links[] = '<a href="' . esc_url(home_url($link['url'])) . '">' . esc_html($link['label']) . '</a>';
                        }
                        echo implode(' · ', $links);
                    ?>
                </p>
            <?php endforeach; ?>

        </details>
    </div>

    <?php
    return ob_get_clean();
}
add_shortcode('hubs', 'hubs_shortcode');
