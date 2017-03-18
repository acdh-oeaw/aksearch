$(document).ready(function() {
	
	// Show tooltip for facets only if search results in more than 100 hits
	if (VuFind.recordTotal > 100) {
		
		// Check if the user already saw this tooltip in this browser session. If not, show it.
		if (!sessionStorage.akFacetTooltipSeen) {			
			var akFacetTipTooltip =	'<div class="akTipTooltip" id="akFacetTipTooltip">' +
										'<div class="akTipTooltipHeader">' +
											'<div class="akTipTooltipHeaderText">' + vufindString['akTip'] + '</div>' +
											'<div class="akTipTooltipHeaderClose"><i class="fa fa-times" aria-hidden="true"></i></div>' +
											'<div class="akClearer"></div>' +
										'</div>' +
										'<div class="akTipTooltipText">' +
											vufindString['facetTiptooltipText'] +
										'</div>' +
									'</div>';
			
			// Prepend the tooltip to the facet column:
			$('.col-sm-3').prepend(akFacetTipTooltip);
			
			// Slowly show tooltip
			$('#akFacetTipTooltip').fadeIn(700);
			
			$(document).on('click', '#akFacetTipTooltip', function() {
				// Set sessionStorage.akFacetTooltipSeen to true so that the tooltip does not load again in this browser session (until user closes browser tab)
				sessionStorage.akFacetTooltipSeen = true;
				
				// Remove the tooltip
				$(this).fadeOut(300, function(){
					$(this).remove();
				});
			});
			
		}
	}
});