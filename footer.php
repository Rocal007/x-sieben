<?php
/**
 * The template for displaying the footer
 */
?>
</div><?php footer_view(); ?>

<!-- <script>
//     window.$zoho = window.$zoho || {};
//     $zoho.salesiq = $zoho.salesiq || {
//         ready: function() {}
//     }
</script>
<script id="zsiqscript" src="https://salesiq.zohopublic.eu/widget?wc=siq5b18fad99f17e2456e1c0c106ef4e3e7791f3c50717063f87fd29a0e26e80d1a" defer></script>
 -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-JNT5DLQ798"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('js', new Date());
    gtag('config', 'G-JNT5DLQ798');
    gtag('config', 'AW-10976390685');
</script>

<script src="https://track.salesflare.com/flare.js" id="salesflare-script" defer></script> 
<script>
    document.getElementById('salesflare-script').addEventListener('load', function() {
        if (typeof Flare !== 'undefined') {
            var flare = new Flare();
            flare.track("gOeqKlf-ZMbpDsDQQ_QYb_Bb1bEI240bhLrJzxKkTb9Rv");
            console.log("🚀 Salesflare erfolgreich initialisiert");
        } else {
            console.error("❌ Flare-Objekt konnte nicht gefunden werden.");
        }
    });
</script>

<script src="https://instant.page/5.2.0" type="module"></script>



<?php wp_footer(); ?>
</body>
</html>