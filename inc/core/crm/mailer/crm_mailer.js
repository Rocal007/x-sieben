	jQuery(document).ready(function ($) {
		$('input[type=checkbox][name=ams]').change(function () {
			if ($("#ams").is(':checked')) {
				$("#svr").addClass('show-it');
				$("#svr").removeClass('hide-it');
			} else {
				$("#svr").addClass('hide-it');
				$("#svr").removeClass('show-it');
			};
		});
		$("#submit-form").click(function (e) {
			e.preventDefault(); // if the clicked element is a link
			var vorname = $("#angebot-vorname").val();
			var nachname = $("#angebot-nachname").val();
			var email = $("#angebot-email").val();
			var firma = $("#angebot-firma").val();
			var strasse = $("#angebot-strasse").val();
			var nummer = $("#angebot-nummer").val();
			var ort = $("#angebot-ort").val();
			var plz = $("#angebot-plz").val();
			var betreff = $('#kurse-title').text();
			var startdatum = $('#startdatum').text();
			var enddatum = $('#enddatum').text();
			var message = $('#mail-text').val();
			var kurstyp = $('#kurstyp').val();
			var title = $('#title').val();
			var anrede = $('#anrede option:selected').val();
			var trainer = $('span#vortragende').html();
			var expire = $('#expire').val();
			var abschluss = $('#abschluss').val();
			var kurszeiten = $('#kurszeiten').val();
			var kursart_t = $('#kursart_t').val();
			var kursart_a = $('#kursart_a').val();
			var kursart_we = $('#kursart_we').val();
			var montag = $('#montag').val();
			var dienstag = $('#dienstag').val();
			var mittwoch = $('#mittwoch').val();
			var donnerstag = $('#donnerstag').val();
			var freitag = $('#freitag').val();
			var samstag = $('#samstag').val();
			var sonntag = $('#sonntag').val();
			var montag_s = $('#montag_s').val();
			var dienstag_s = $('#dienstag_s').val();
			var mittwoch_s = $('#mittwoch_s').val();
			var donnerstag_s = $('#donnerstag_s').val();
			var freitag_s = $('#freitag_s').val();
			var samstag_s = $('#samstag_s').val();
			var sonntag_s = $('#sonntag_s').val();
			var module = $('#module').html();
			var module_count = $('#module_count').val();
			var module_text = $('#module_text').html();
			var module_solo = $('#module_solo').html();
			var preise = $('#preise').html();
			var current = $('#current').val();
			var waff = $('#waff').val();
			var svr = $('#svrnum').val();
			var zielgruppe = $('#zielgruppe').val();
			var voraussetzungen = $('#voraussetzungen').html();
			var requirements = $('#requirements').html();
			var inhalte = $('#inhalte').html();
			var title_preis = $('#title_preis').val();
			var preis = $('#preis').val();
			var angebot_beschreibung = $('#angebot_beschreibung').val();
			var anzahl_le = $('#anzahl_le').val();
			var zielgruppe_filter = $('#zielgruppe_filter').html();
			var zert_single = $('#zert_single').html();
			var permalink = $('#permalink').val();
			var termine_pdf = $('#termine_pdf').val();
			var zert_images = []
			$('#zert_images img').each(function () {
				var image = $(this).attr('src');
				zert_images.push(image);
			});
			console.log(permalink);
			var ams = false;
			if ($("#ams").is(':checked')) {
				ams = true;
			} else {
				ams = false;
			};
			var diplom = false;
			if ($("#diplom").is(':checked')) {
				diplom = true;
			} else {
				diplom = false;
			};
			var zerts = [];
			$("input[name='zertifizierungen']").each(function (index, element) {
				if (element.checked) {
					var title = $(this).val();
					var price = $(this).attr("price");
					var ust = $(this).attr("ust");
					zerts.push({
						title: title,
						price: price,
						ust: ust
					});
				}
			});
			var docs = [];
			$("input[name='docs']").each(function (index, element) {
				if (element.checked) {
					var doc = $(this).val();
					docs.push({
						document: doc,
					});
				}
			});
			$.post($("#ajax_url").val(), {
				action: 'siteWideMessage',
				betreff: betreff,
				startdatum: startdatum,
				enddatum: enddatum,
				anrede: anrede,
				vorname: vorname,
				nachname: nachname,
				strasse: strasse,
				nummer: nummer,
				ort: ort,
				plz: plz,
				kurstyp: kurstyp,
				title: title,
				message: message,
				email: email,
				firma: firma,
				expire: expire,
				trainer: trainer,
				abschluss: abschluss,
				kurszeiten: kurszeiten,
				kursart_t: kursart_t,
				kursart_a: kursart_a,
				kursart_we: kursart_we,
				montag: montag,
				dienstag: dienstag,
				mittwoch: mittwoch,
				donnerstag: donnerstag,
				freitag: freitag,
				samstag: samstag,
				sonntag: sonntag,
				montag_s: montag_s,
				dienstag_s: dienstag_s,
				mittwoch_s: mittwoch_s,
				donnerstag_s: donnerstag_s,
				freitag_s: freitag_s,
				samstag_s: samstag_s,
				sonntag_s: sonntag_s,
				diplom: diplom,
				ams: ams,
				waff: waff,
				svr: svr,
				module: module,
				module_count: module_count,
				module_text: module_text,
				module_solo: module_solo,
				preise: preise,
				current: current,
				zielgruppe: zielgruppe,
				voraussetzungen: voraussetzungen,
				inhalte: inhalte,
				zerts: zerts,
				docs: docs,
				title_preis: title_preis,
				preis: preis,
				angebot_beschreibung: angebot_beschreibung,
				anzahl_le: anzahl_le,
				zielgruppe_filter: zielgruppe_filter,
				requirements: requirements,
				zert_single: zert_single,
				zert_images: zert_images,
				permalink: permalink,
				termine_pdf: termine_pdf
			}, function (response) {
				// handle a successful response
				console.log(termine_pdf);
				$('.text-muted').append(response);
			});
		});
	});
	jQuery(document).ready(function ($) {
		function toggleAndScroll($elements, targetSection) {
			$elements.toggle();
			$('html, body').animate({
				scrollTop: $(targetSection).offset().top - 140
			}, 100);
		}

		$("#investition, #courses-back, #invest-inline, #termine-inline, #starttermin, #sidebar-angebot, #sidebar-smd-angebot").click(function () {
			toggleAndScroll($("#investition, #starttermin, #sidebar-angebot, .button-divider, #related, #coures-content, #courses-booking, .proven-experts-sidebar, #sidebar-smd-angebot"), "#termine-anchor");
		});

		$("#angebot-inline").click(function () {
			toggleAndScroll($("#investition, #starttermin, #sidebar-angebot, .button-divider, #related, #coures-content, #courses-booking, .proven-experts-sidebar, #sidebar-smd-angebot"), "#angebot-anchor");
		});

		$("#online-info-event").click(function () {
			$('html, body').animate({
				scrollTop: $("#online-info-event-anchor").offset().top
			}, 100);
		});
	});