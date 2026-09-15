<? function dates_costs()
{ ?>

    <div class="courses-divider-heading info">
        <h4>Starttermin dieser Veranstaltung und Ihre Investition</h4>
    </div>


    <div class="termine col-md-5 left-margin">
        <p>
            <b>Starttermin: </b>
            <span class="orange" id="startdatum">
                <?php $start_datum = date('d.m.Y', strtotime(get_post_meta(get_the_ID(), 'start_datum', true)));
                echo $start_datum; ?>
            </span>
            <br>
            Ende:
            <span id="enddatum">
                <?php $end_datum = date('d.m.Y', strtotime(get_post_meta(get_the_ID(), 'end_datum', true)));
                echo $end_datum; ?>
            </span>
        </p>

    </div>
    <div class="termine col-md-7">
        <p>Individuelle Starttermine und Quereinstieg nach Rücksprache möglich. Wenn Sie einzelne Tage versäumen, können
            diese in Folge-Veranstaltungen nachgeholt werden.
        </p>
    </div>

    <div class="kosten col-md-5 left-margin">
        <p>
            <b>Investition: </b>
            <?php
            $price = get_post_meta(get_the_ID(), 'kosten', true);
            $price_text = get_post_meta(get_the_ID(), 'kosten_text', true);
            $formatted_price = isset($price) && $price !== '' ? 'EUR ' . number_format($price, 2, ',', '.') : $price_text;

            ?>
            <span class="orange">
                <?php echo $formatted_price; ?>
            </span>
    </div>
    <div class="kosten col-md-7">
        <p>Preis zuzüglich USt. Förderbare Veranstaltung. <strong> Ratenzahlung </strong> möglich. Jetzt anmelden und die
            Investition auf bis zu <strong> 24 monatliche Raten </strong> aufteilen. Optional sind auch 6 bzw. 12 Raten
            möglich, mit <a href="https://www.x-sieben.at/jetzt-weiterbilden-bezahlen-in-bis-zu-24-raten-mit-klarna/"><img
                    class="klarna-image" src="https://www.x-sieben.at/wp-content/uploads/2023/01/Klarna-Logo-1.png"></a>
        </p>
    </div>
<? }