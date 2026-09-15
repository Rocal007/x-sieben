<?php

/**
 * The template for displaying the footer
 *
 */
?>
</div><!-- outer-content-wrap div -->


<?php
if (get_theme_mod('hide_footer_widget_bar', 1) != 0): ?>
    <footer class="widget-footer">
        <div class="container">
            <div class="widget-footer-row">
                <div class="row">
                    <?php if (is_active_sidebar('footer-1')): ?>
                        <div class="col-md-3 col-xs-12">
                            <!-- <div class="footer-kontakt invert-text-color">
                <img style="margin-bottom: 25px; margin-right: -25px;" src="https://www.x-sieben.at/wp-content/uploads/2025/01/cropped-logo_x_sieben_2025_.png" alt="X-Sieben - Kurse - Seminare - Lehrgänge - Coaching - Logo" />
<p><strong>X SIEBEN Wirtschaftstraining GmbH</strong></p>
    New Work @ Agile Transformation @ KI Training 4Coaches </br>@ Berater @ Mittelstand
                    <p style="margin-top: 15px; line-height: 2em;">Wien・Lichtenwörth・Graz・DACH</p>
                    <p style="margin: 25px 0px;">Telefon: <a style="text-decoration: underline;" href="tel:+43 800 700 170">+43 800 700 170</a>
                    E-Mail: <a style="text-decoration: underline;" href="mailto:office@x-sieben.at">office@x-sieben.at</a>
                    Messenger: Chat via <a href="https://lp.chatwerk.de/?organizationId=pqodsTzCbi&channelId=zKJsRqNMGm&messenger=WhatsApp" target="_blank" rel="noopener">WhatsApp</a></p>
                    <p data-wp-editing="1"> 
                        <a href="https://www.x-sieben.at/jetzt-weiterbilden-bezahlen-in-bis-zu-24-raten-mit-klarna/">
                       
                     <img class="wp-image-49804 alignnone" src="https://www.x-sieben.at/wp-content/uploads/2023/01/Klarna_Visa_Mastercard_X-SIEBEN-300x123.png" alt="" width="136" height="56" />
                    </a>
                    </p>
                    <a style="text-decoration: underline;" href="https://firmen.wko.at/x-sieben-wirtschaftstraining-gmbh/niederösterreich/?firmaid=b0485f24-019a-429f-b768-03c8d791ae74&suchbegriff=x%20sieben%20wirtschaftstraining%20gmbh">Impressum</a> | <a style="text-decoration: underline;" href="https://www.x-sieben.at/wp-content/uploads/2023/03/AGB_X_SIEBEN_2023.pdf">AGB</a>
                </div> -->
                            <?php dynamic_sidebar('footer-1'); ?>
                        </div>
                    <?php endif ?>
                    <?php if (is_active_sidebar('footer-2')): ?>
                        <div class="col-md-3 col-xs-12">
                            <?php dynamic_sidebar('footer-2'); ?>
                        </div>
                    <?php endif ?>
                    <?php if (is_active_sidebar('footer-3')): ?>
                        <div class="col-md-3 col-xs-12">
                            <?php dynamic_sidebar('footer-3'); ?>
                        </div>
                    <?php endif ?>
                    <?php if (is_active_sidebar('footer-4')): ?>
                        <div class="col-md-3 col-xs-12">
                            <div class="widget-footer-heading">
                                <div class="wp-container-1 wp-block-group">
                                    <div class="wp-block-group__inner-container">
                                        <div class="widget widget_newsletterwidgetminimal">
                                            <h2 class="widgettitle">Newsletter abonnieren</h2>
                                            <div class="tnp tnp-widget-minimal">
                                                <form class="tnp-form" action="https://www.x-sieben.at/?na=s" method="post"><input type="hidden" name="nr" value="widget-minimal">
                                                    <input class="tnp-email" type="email" required="" name="ne" value="" placeholder="Email"><input class="tnp-submit" type="submit" value="Jetzt abonnieren">
                                                </form>
                                            </div>
                                        </div>
                                        <div class="flex">
                                            <figure class="wp-block-image is-resized mrr20"><a href="https://www.wko.at"><img src="https://www.x-sieben.at/wp-content/uploads/2018/08/WKO-Österreich-300x96.png" alt="Weiterbildungspartner der WK Österreich" width="97" height="31" title="Weiterbildungspartner der WK Österreich"></a></figure>

                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php //dynamic_sidebar('footer-4'); 
                            ?>
                        </div>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </footer>
<?php endif; ?>

<footer class="menu-footer">

    <div class="menu-footer-row">
        <div class="row">
            <div class="col-md-12">
                <div class="footer-copyrights">
                    <div class="footer-copyright-text">

                        <?php echo '© ' . date("Y"); ?>

                    </div>

                </div>
            </div>

        </div>

    </div>
</footer>
<?php wp_footer(); ?>
<script>
    window.$zoho = window.$zoho || {};
    $zoho.salesiq = $zoho.salesiq || {
        ready: function() {}
    }
</script>
<script id="zsiqscript" src="https://salesiq.zohopublic.eu/widget?wc=siq5b18fad99f17e2456e1c0c106ef4e3e7791f3c50717063f87fd29a0e26e80d1a" defer></script>
</body>

</html>