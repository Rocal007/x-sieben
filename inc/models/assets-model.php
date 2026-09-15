<?php 
class COURSE_assets
{
    private $assets_url;

    public function __construct()
    {
        $this->assets_url = get_template_directory_uri() . '/inc/core/crm/assets/';
    }

    public function get_all(): array
    {
        return [
            'web' => $this->logo('kontakt.png', 25, 'Webseite'),
            'mail' => $this->logo('email.png', 25, 'E-Mail'),
            'fax' => $this->logo('fax.png', 25, 'Fax'),
            'phone' => $this->logo('tel.png', 25, 'Telefon'),
            'calender' => $this->logo('kalender.png', 25, 'Kalender'),
            'ort' => $this->logo('ort.png', 25, 'Ort'),
            'abschluss' => $this->logo('abschluss.png', 25, 'Abschluss'),
            'diplom' => $this->logo('diplom.png', 25, 'Diplom'),
            'danger' => $this->logo('danger.png', 25, 'Hinweis'),
            'sitting' => $this->logo('sitting.png', 25, 'Garantie'),
            'proven' => $this->logo('proven.png', 25, 'Proven Expert Bewertungen'),
            'proven_wide' => $this->logo('proven_wide.png', null, 'Proven Expert'),
            'share' => $this->logo('share.png', 25, 'Teilen'),
            'wba' => $this->logo('wba-1.png', 100, 'WBA Logo'),
            'cert_noe' => $this->logo('cert-1.png', 100, 'Cert NÖ Logo'),
            'tuef' => $this->logo('tuef.png', null, 'TÜV Logo'),
            'sys_zert' => $this->logo('system-1.png', null, 'System Zert Logo'),
            'pma' => $this->logo('PMA-1.png', null, 'PMA Logo'),
            'ipma' => $this->logo('impa.png', null, 'IPMA Logo'),
            'email_logos' => $this->logo('email_zerts.png', 100, 'Email Zertifikate', 'padding-left: 54px;'),
            'ams' => $this->logo('ams.png', 160, 'AMS Logo'),
            'signatur' => $this->logo('Signatur_Blau.png', 180, 'Signatur'),
            'xsieben' => $this->logo('xsieben_logo.png', 200, 'X-Sieben Logo'),
        ];
    }
	
public function render(string $key): string
    {
        $logos = $this->get_all();
        if (!isset($logos[$key])) {
            return '';
        }
        $logo = $logos[$key];

        $width = !empty($logo['width']) ? ' width="' . intval($logo['width']) . '"' : '';
        $style = !empty($logo['style']) ? ' style="' . esc_attr($logo['style']) . '"' : '';

        return sprintf(
            '<img src="%s" alt="%s"%s%s>',
            esc_url($logo['src']),
            esc_attr($logo['alt']),
            $width,
            $style
        );
    }

    private function logo(string $file, ?int $width, string $alt, string $style = null): array
    {
        return [
            'src'   => $this->assets_url . $file,
            'width' => $width,
            'alt'   => $alt,
            'style' => $style,
        ];
    }
}