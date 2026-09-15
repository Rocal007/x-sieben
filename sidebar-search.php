<?php

/**
 * The sidebar containing the main widget area
 *
 *
 * @package sieben
 */
?>

<div id="courses-sidebar" class="right pdt20">
    <div class="courses-sidebar-inner">


        <div class="courses-sidebar-element clearfix">
            <a href="tel:0800700170">
                <div class="courses-sidebar-icon phone"></div>
                <div class="courses-sidebar-element-inner" style="margin-top: 5px;
               color: black!important;">
                    <strong>RUFEN SIE AN:</strong> 0800 700 170
                </div>
            </a>
        </div>
        <div class="courses-sidebar-element clearfix">

            <div class="courses-sidebar-icon star"></div>
            <div class="courses-sidebar-element-inner">
                <strong style=" margin-top: 5px; color: black!important; display: inline-block;">REZENSIONEN:</strong>
                <div
                    class="sidebar-link">
                    <a href="https://www.provenexpert.com/x-sieben-wirtschaftstraining/?utm_source=Widget&utm_medium=Widget&utm_campaign=Widget">Externe Bewertungen anzeigen </a>
                </div>
                <?php //echo do_shortcode('[wbcr_html_snippet id="28092"]'); 
                ?>
            </div>

        </div>

        <div class="courses-sidebar-element clearfix">
            <div class="courses-sidebar-icon finger"></div>
            <div class="courses-sidebar-element-inner vierfachgarantie" style="margin-top: 5px;">
                <strong>X SIEBEN 4-FACH GARANTIE:</strong>
                <ul>
                    <li>Zufriedenheitsgarantie</li>
                    <li>Rücktrittsgarantie</li>
                    <li>Kleingruppen-Garantie</li>
                    <li>Durchführungsgarantie</li>
                </ul>
            </div>
        </div>
        <div class="courses-sidebar-element clearfix">
            <?php search_element(); ?>
        </div>
    </div>
</div>