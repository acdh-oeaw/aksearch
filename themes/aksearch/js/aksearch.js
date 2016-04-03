$(document).ready(function() {

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

});