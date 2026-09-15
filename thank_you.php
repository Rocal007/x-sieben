<?php
	/**
 * Template Name: thank you
 *
 */
	get_header();
$email= $_GET["amp;email"];
$kurs = $_GET["amp;kurs"];
$anrede = $_GET["anrede"];
$first_name = $_GET["amp;id:angebot-anfordern-firstname"];
$last_name = $_GET["amp;lastname"];

?>


	<div class="container">
		<div id="thank_you-wrapper" class="col-md-12">
			
			<div class="thank_you-heading">
				<br>
				<br>
				<h4>		
					Sehr geehrte(r) <?php echo $anrede;?> <?php echo $first_name;?> <?php echo $last_name;?> (E-Mail: <span id="customer-email"><?php echo $email;?>)</span>!<br>
				</h4>
				<p>
					Vielen Dank für Ihre Anfrage zum Kurs <?php echo $kurs;?>, wir werden sie schnellst möglich bearbeiten.
				</p>
				<br>
				<br>
				<?php //var_dump($_GET);?> 
				
			</div>
			
			
		
		</div>	
    
		
		
	</div>


<?php get_footer();
