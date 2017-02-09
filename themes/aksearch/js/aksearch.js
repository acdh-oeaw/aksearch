// Avoid `console` errors in browsers that lack a console.
(function() {
    var method;
    var noop = function () {};
    var methods = [
        'assert', 'clear', 'count', 'debug', 'dir', 'dirxml', 'error',
        'exception', 'group', 'groupCollapsed', 'groupEnd', 'info', 'log',
        'markTimeline', 'profile', 'profileEnd', 'table', 'time', 'timeEnd',
        'timeline', 'timelineEnd', 'timeStamp', 'trace', 'warn'
    ];
    var length = methods.length;
    var console = (window.console = window.console || {});

    while (length--) {
        method = methods[length];

        // Only stub undefined methods.
        if (!console[method]) {
            console[method] = noop;
        }
    }
}());

//Setup search autocomplete
function setupAutocomplete() {
	
	// Search autocomplete
	$('.autocomplete').each(function(i, op) {
		$(op).autocomplete({
			maxResults: 10,
			loadingString: VuFind.translate('loading')+'...',
			searchtypeHandler: '.searchForm_type',
			handler: function(input, cb) {
				var query = input.val();
				var searcher = extractClassParams(input);
				var hiddenFilters = [];
				$(input).closest('#searchForm').find('input[name="hiddenFilters[]"]').each(function() {
					hiddenFilters.push($(this).val());
				});
				
				$.fn.autocomplete.ajax({
					url:		path + '/AJAX/JSON',
					cache:		false,
					data: {
						q:				query,
						method:			'getACSuggestions',
						searcher:		searcher['searcher'],
						type:			searcher['type'] ? searcher['type'] : $(input).closest('#searchForm').find('#searchForm_type').val(),
						hiddenFilters:	hiddenFilters
					},
					dataType:	'json',
					success:	function(json) {
									if (json.data.length > 0) {
										var datums = [];
										for (var i=0;i<json.data.length;i++) {
											searchString = json.data[i];
											searchString = searchString.replace(/[<>]/g, ''); // This line actually removes the "<" and ">" characters
											searchString = searchString.replace(/^"/g, '').replace(/"$/g, ''); // Remove quotation marks from the beginning and ending of the string
											searchString = searchString.replace(/\?$/g, ''); // Remove question marks at the end of the string as this could lead to wrong Solr queries
											datums.push(searchString);
										}
										cb(datums);
									} else {
										cb([]);
									}
								}
				});
			}
		});
	});
    
	// If search type is changed, clear cache and set focus to search field
	$('.searchForm_type').change(function() {
		// Original uses searchForm as class (.searchForm), we need to use it as id (#searchForm)
		var $lookfor = $(this).closest('#searchForm').find('#searchForm_lookfor[name]');
		$lookfor.autocomplete('clear cache');
		$lookfor.focus().val($lookfor.val()); // Set focus to search field and re-add it's value (for putting cursor to the end of the text)
	});
	
}

// Setup autocomplete
// Info: do not put this in $(document).ready, otherwise the keydown event fires twice which will mess up the navigation
// with the arrow keys in the autocomplete drop-down.
setupAutocomplete();

$(document).ready(function() {

	// Set focus to search field and re-add it's value (for putting cursor to the end of the text)
	var tempSearchValue = $('#searchForm_lookfor[name]').focus().val();
	$('#searchForm_lookfor[name]').val('').val(tempSearchValue);
	
	
	// Set info tooltip variable
	var isAkInfoTooltipOpen = false;
	
	// Open AK info tooltip
	$('.akInfoTooltipOpener').click (function(event) {
		if (!isAkInfoTooltipOpen) {

			isAkInfoTooltipOpen = true;
			var akTooltipTitle = $(this).data('aktooltiptitle');
			var akTooltipText = $(this).data('aktooltiptext');
			$("body").append('<div class="akInfoTooltip"></div>');
			
			// Position the tooltip near the mouse pointer
			var mousePosX = event.pageX;
	        var mousePosY = event.pageY;
		    $(".akInfoTooltip").css("left", (mousePosX-310));
		    $(".akInfoTooltip").css("top", (mousePosY+10));
			
		    var resultHtml =
				'<strong>' + akTooltipTitle + '</strong><div class="akInfoTooltipClose"><i class="fa fa-times-circle" aria-hidden="true"></i></div>' +
				'<div class="akClearer"></div>' + 
				'<div>' + akTooltipText + '</div>';
		    
			// Add html to AK info tooltip
			$(resultHtml).appendTo('.akInfoTooltip');
			
		} else {
			// Remove AK info tooltip on second click on "I":
			$('.akInfoTooltip').remove();
			isAkInfoTooltipOpen = false;
		}
	});
	
	// Remove AK info tooltip on click on "X":
	$("body").on("click", ".akInfoTooltipClose", function(event) {
		if (isAkInfoTooltipOpen) {
			$(this).closest('.akInfoTooltip').remove();
			isAkInfoTooltipOpen = false;
		}
	});
	
	
	// Remove AK info tooltip on click outside tooltip:
	$(document).click(function(e) {
		var container = $('.akInfoTooltip');		
		if (!container.is(e.target) && container.has(e.target).length === 0 && $(e.target).hasClass('akInfoTooltipInfo') == false) {
			$(container).remove();
			isAkInfoTooltipOpen = false;
		}
	});
	

	
	// TODO: Merge code for AK info tooltip and entity facts tooltip because right now we are doing quite the same thing with two separate methods.
	
	
	// Set entity facts tooltip variable
	var isAkEntityFactsTooltipOpen = false;
	
	// Getting entity facts: Ajax call via PHP
	$('.akEntityFactsOpener').click (function() {
				
		if (!isAkEntityFactsTooltipOpen) {
			
			isAkEntityFactsTooltipOpen = true;
			var gndid = $(this).data('gndid');
			var tooltipLink = $(this);
			var url = path + '/AJAX/JSON?' + $.param({method:'getEntityFact', gndid:gndid});

			$(tooltipLink).after('<div class="akEntityFactsTooltip"><div class="entityFactsLoader" style="text-align: center;"><i class="fa fa-cog fa-spin fa-3x fa-fw"></i></div></div>');
			
			$.ajax({
				url: url,
				cache: false,
				dataType: 'json',
				statusCode: {
					// No info was found:
					404: function() {
						var resultHtml =
							'<div class="akEntityFactsTooltip">' +
								'<strong>Keine weiteren Infos verfügbar</strong><div class="akEntityFactsTooltipClose"><i class="fa fa-times-circle" aria-hidden="true"></i></div>' +
							'</div>';
						
						// Remove "loading" spinner:
						$('.entityFactsLoader').remove();
					
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
						$('.entityFactsLoader').remove();
					
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
					$('.entityFactsLoader').remove();
					
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
	
	
	// bootstrap-datepicker for "new items" search (Neuerwerbungen):
	// Code available at https://bootstrap-datepicker.readthedocs.io
	$('#newitemDatepicker').datepicker({
		format: {
			toDisplay: function (date, format, language) {
	            var firstDay = '01';
				var firstRealMonth = (date.getMonth()+1);
				var firstMonth = (firstRealMonth > 9) ? firstRealMonth : '0' + firstRealMonth;
				var firstYear = date.getFullYear();
				var firstDate = firstYear + '' + firstMonth + '' +  firstDay;
				
				// ALWAYS prefix the date from the datepicker with the string "datePicker_"!!!
				// We make checks on that prefix in ILS Driver \AkSearch\ILS\Driver\Aleph.php
	            return 'datePicker_' + firstDate;
	        },
	        toValue: function (date, format, language) {
	            return new Date(date);
	        }
		},
		startView: 1,
		minViewMode: 1, // months
		maxViewMode: 2, // years
		clearBtn: true,
		language: 'de',
		autoclose: true
	})
	
	$('#newitemDatepicker').on('changeDate', function(e) {
		var dateDisplay = e.format(0, 'MM yyyy');
		if (dateDisplay) {
			$('#newitemDatepickerText').text(dateDisplay);
		} else {
			$('#newitemDatepickerText').text('Auswählen');
			$('#newitemDatepickerValue').val(0);
		}
    });


});



jQuery.fn.putCursorAtEnd = function() {

	  return this.each(function() {
	    
	    // Cache references
	    var $el = $(this),
	        el = this;

	    // Only focus if input isn't already
	    if (!$el.is(":focus")) {
	     $el.focus();
	    }

	    // If this function exists... (IE 9+)
	    if (el.setSelectionRange) {

	      // Double the length because Opera is inconsistent about whether a carriage return is one character or two.
	      var len = $el.val().length * 2;
	      
	      // Timeout seems to be required for Blink
	      setTimeout(function() {
	        el.setSelectionRange(len, len);
	      }, 1);
	    
	    } else {
	      
	      // As a fallback, replace the contents with itself
	      // Doesn't work in Chrome, but Chrome supports setSelectionRange
	      $el.val($el.val());
	      
	    }

	    // Scroll to the bottom, in case we're in a tall textarea
	    // (Necessary for Firefox and Chrome)
	    this.scrollTop = 999999;

	  });

	};