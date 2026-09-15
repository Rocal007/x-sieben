<?php
function sieben_topbar_view()
{
?>
  <div class="topbar container-fluid">
    <div class="container">
      <div class="row">

        <div class="topbar-content-left col-md-6">
          <a href="tel:+43800700170">
            <i class="fa fa-mobile"></i>
            +43 800 700 170
          </a>

          <a href="mailto:office@x-sieben.at">
            <i class="fa fa-envelope"></i>
            office@x-sieben.at
          </a>

          <a href="https://lp.chatwerk.de/?organizationId=pqodsTzCbi&channelId=zKJsRqNMGm&messenger=WhatsApp">
            <i class="fa fa-whatsapp"></i>
            WhatsApp
          </a>
        </div>
        <div class="topbar-content-right col-md-6">
          <?php
          if (function_exists('search_elemant')) {
            search_element();
          } ?>
        </div>
      </div>
    </div>
  </div>
<?php
} // end function sieben_topbar_view  