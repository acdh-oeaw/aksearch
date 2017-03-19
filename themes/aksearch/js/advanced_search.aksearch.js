/**
 * OVERRIDE AND/OR EXTEND ADVANCED_SEARCH.JS FOR AKSEARCH
 */

$(document).ready(function() {
	// Fix the width of the facet select boxes in the advanced search site:
	fixFacetSelectboxWidth();
	
});

function fixFacetSelectboxWidth() {
	// Get width of browser viewport
	var windowWidth = $(window).width();
	
	// Get the row in which the select boxes are displayed
	var row = $('.col-sm-12').children('.row').first();
	console.log(row);
	
	//Get the width of the row 
	var rowWidth = $(row).width();

	// Get the select boxes:
	var selectBoxes = $(row).children('div');
	
	// Get the no of select boxes in the row
	var noOfSelectBoxes = $(selectBoxes).length;
	
	// Check if responsive design mode for mobile devices will be used from bootstrap CSS
	// It will be used for screens smaller than 768px. We won't adjust the widths of the
	// selectboxes there.
	if (windowWidth >= 768) {
		// Get the target width of one select box in pixel 
		var widthOfSelectBoxPx = (rowWidth/noOfSelectBoxes);
		
		// Get the target width of one select box in percent
		var widthOfSelectBoxPercent = ((100 / rowWidth) * widthOfSelectBoxPx); 
		
		// Set the width of the select boxes
		$(selectBoxes).css('width', widthOfSelectBoxPercent + '%');
	}
}