$(document).ready(function() {

	var isAkEntityFactsTooltipOpen = false;
	// Override original autocomplete method for searchbox: removing "<" and ">" (non-sorting characters in RAK-WB)
	// INFO: Autocomplete works with typeahead.js: https://github.com/twitter/typeahead.js

	// First destroy the original typeahead
	$('.autocomplete').typeahead('destroy');

	// Now re-add the typeahead with the modifications
	$('.autocomplete').typeahead({
		highlight: true,
		minLength: 3
	}, {
		displayKey:'val',
		source: function(query, cb) {
			var searcher = extractClassParams('.autocomplete');
			$.ajax({
				url: path + '/AJAX/JSON',
				data: {
					q:query,
					method:'getACSuggestions',
					searcher:searcher['searcher'],
					type:$('#searchForm_type').val()
				},
				dataType:'json',
				success: function(json) {
					if (json.status == 'OK' && json.data.length > 0) {
						var datums = [];
						for (var i=0;i<json.data.length;i++) {
							searchString = json.data[i];
							searchString = searchString.replace(/[<>]/g, ''); // This line actually removes the "<" and ">" characters
							datums.push({val:searchString});
						}
						cb(datums);
					} else {
						cb([]);
					}
				}
			});
		}
	});
	
	// Override method that updates autocomplete values if search handler is changed in DropDown:
	$('.searchForm_type').change(function() {
		// Original uses searchForm as class (.searchForm), we need to use it as id (#searchForm)
		var $lookfor = $(this).closest('#searchForm').find('#searchForm_lookfor[name]');
		var query = $lookfor.val();
		$lookfor.focus().typeahead('val', '').typeahead('val', query);
	});

	
	

	
	// Getting entity facts: Ajax call via PHP
	$('.akEntityFactsOpener').click (function() {
				
		if (!isAkEntityFactsTooltipOpen) {
			
			isAkEntityFactsTooltipOpen = true;
			var gndid = $(this).data('gndid');
			var tooltipLink = $(this);
			var url = path + '/AJAX/JSON?' + $.param({method:'getEntityFact', gndid:gndid});

			$(tooltipLink).after('<div class="akEntityFactsTooltip"><i class="fa fa-cog fa-spin fa-3x fa-fw"></i></div>');
			
			$.ajax({
				url: url,
				dataType: 'json',
				statusCode: {
					// No info was found:
					404: function() {
						var resultHtml =
							'<div class="akEntityFactsTooltip">' +
								'<strong>Keine weiteren Infos verfügbar</strong><div class="akEntityFactsTooltipClose"><i class="fa fa-times-circle" aria-hidden="true"></i></div>' +
							'</div>';
						
						// Remove "loading" spinner:
						$('.fa-cog').remove();
					
						// Add result html to tooltip
						$(resultHtml).appendTo('.akEntityFactsTooltip');
				    }
				},
				success: function(resultString) {
					var status = resultString.status;
					
					// Check for error
					if (status == 'ERROR') {
						var errorMsg = resultString.data
						var resultHtml =
							'<div class="akEntityFactsTooltip">' +
								'<strong>'+errorMsg+'</strong><div class="akEntityFactsTooltipClose"><i class="fa fa-times-circle" aria-hidden="true"></i></div>' +
							'</div>';
						
						// Remove "loading" spinner:
						$('.fa-cog').remove();
					
						// Add result html to tooltip
						$(resultHtml).appendTo('.akEntityFactsTooltip');
						return false;
					}
					
					// Parse result string as JSON object
					var result = JSON.parse(resultString.data);
					
					// General info
					var preferredName = (result.preferredName != undefined) ? '<tr><th>Name:</th><td>' + result.preferredName + '</td></tr>' : '';
					
					// Person info
					var placeOfBirth = (result.placeOfBirth != undefined) ? ' (' + result.placeOfBirth[0].preferredName + ')' : '';
					var dateOfBirth = (result.dateOfBirth != undefined) ? '<tr><th>Geboren:</th><td>' + result.dateOfBirth + placeOfBirth + '</td></tr>' : '';
					var placeOfDeath = (result.placeOfDeath != undefined) ? ' (' + result.placeOfDeath[0].preferredName + ')' : '';
					var dateOfDeath = (result.dateOfDeath != undefined) ? '<tr><th>Gestorben:</th><td>' + result.dateOfDeath + placeOfDeath + '</td></tr>' : '';
					var genderResult = (result.gender != undefined) ? result.gender.label : null;
					var genderText = null;
					if (genderResult != null) {
						if (genderResult == 'Mann' || genderResult == 'mann') {
							genderText = 'männlich';
						} else if (genderResult == 'Frau' || genderResult == 'frau') {
							genderText = 'weiblich';
						}
					}
					var gender = (genderText != null) ? '<tr><th>Geschlecht:</th><td>' + genderText + '</td></tr>' : '';
					var arrPlaceOfActivity = (result.placeOfActivity != undefined) ? result.placeOfActivity : null;
					var strPlaceOfActivity = '';
					var htmlPlaceOfActivity = '';
					if (arrPlaceOfActivity != null) {
						for (i in arrPlaceOfActivity) {
							strPlaceOfActivity += arrPlaceOfActivity[i].preferredName + '<br>';
						}
						htmlPlaceOfActivity = '<tr><th>Wirkungsort:</th><td>' + strPlaceOfActivity + '</td></tr>';
					}
					var arrOccupation = (result.professionOrOccupation != undefined) ? result.professionOrOccupation : null;
					var strOccupation = '';
					var htmlOccupation = '';
					if (arrOccupation != null) {
						for (i in arrOccupation) {
							strOccupation += arrOccupation[i].preferredName + '<br>';
						}
						htmlOccupation = '<tr><th>Beruf:</th><td>' + strOccupation + '</td></tr>';
					}
					var biographicalOrHistoricalInformation = (result.biographicalOrHistoricalInformation != undefined) ? '<tr><th>Sonstiges:</th><td>' + result.biographicalOrHistoricalInformation + '</td></tr>' : '';
					
					// Corporation info
					var arrDateOfEstablishment = (result.dateOfEstablishment != undefined) ? result.dateOfEstablishment : null;
					var strDateOfEstablishment = '';
					var htmlDateOfEstablishment = '';
					if (arrDateOfEstablishment != null) {
						for (i in arrDateOfEstablishment) {
							strDateOfEstablishment += arrDateOfEstablishment[i] + '<br>';
						}
						htmlDateOfEstablishment = '<tr><th>Gründung:</th><td>' + strDateOfEstablishment + '</td></tr>';
					}
					var placeOfBusiness = (result.placeOfBusiness != undefined) ? '<tr><th>Sitz:</th><td>' + result.placeOfBusiness[0].preferredName  + '</td></tr>': '';
					var topic = (result.topic != undefined) ? '<tr><th>Thema:</th><td>' + result.topic[0].preferredName  + '</td></tr>': '';
					var precedingOrganisation = (result.precedingOrganisation != undefined) ? '<tr><th>Vorgänger:</th><td>' + result.precedingOrganisation[0].preferredName  + '</td></tr>': '';
						
					
					// Construction of tooltip html:
					var resultHtml =
						'<strong>Weitere Infos</strong><div class="akEntityFactsTooltipClose"><i class="fa fa-times-circle" aria-hidden="true"></i></div>' +
						'<div class="akClearer"></div>' + 
						'<table class="akEntitiyFactsTable">' +
							preferredName + 
							dateOfBirth +
							dateOfDeath +
							gender +
							htmlPlaceOfActivity +
							htmlOccupation +
							htmlDateOfEstablishment +
							placeOfBusiness +
							topic +
							precedingOrganisation +
							biographicalOrHistoricalInformation +
						'</table>';
					
					// Remove "loading" spinner:
					$('.fa-cog').remove();
					
					// Add result html to tooltip
					$(resultHtml).appendTo('.akEntityFactsTooltip');
				}
			});
		} else {
			// Remove entity facts tooltip on second click on "I":
			$('.akEntityFactsTooltip').remove();
			isAkEntityFactsTooltipOpen = false;
		}
		
	});

	
	// Remove entity facts tooltip on click on "X":
	$("body").on("click", ".akEntityFactsTooltipClose", function(event) {
		if (isAkEntityFactsTooltipOpen) {
			$(this).closest('.akEntityFactsTooltip').remove();
			isAkEntityFactsTooltipOpen = false;
		}
	});
	
	
	// Remove entity facts tooltip on click outside tooltip:
	$(document).click(function(e) {
		var container = $('.akEntityFactsTooltip');		
		if (!container.is(e.target) && container.has(e.target).length === 0 && $(e.target).hasClass('akEntityFactsInfo') == false) {
			$(container).remove();
			isAkEntityFactsTooltipOpen = false;
		}
	});


});


