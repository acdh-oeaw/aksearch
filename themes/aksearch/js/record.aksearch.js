/**
 * OVERRIDE RECORD.JS FOR AKSEARCH
 */

// Avoid 'console' errors in browsers that lack a console.
// Thanks for the code to HTML5-BoilerPlate: https://github.com/h5bp/html5-boilerplate/blob/5.3.0/src/js/plugins.js
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


/**
 * Override ajaxLoadTab() function
 * 
 * Problem with substring if same string exists twice in Record-URL.
 * Example Record-URL: http://aksearch.arbeiterkammer.at/aksearch/Record/000097874#holdings
 * The substring and split command (see "var chunks = ..." below) cuts the URL into chunks.
 * With the original script, the chunks[0] and chunks[1] would be used. This would be
 * "arbeiterkammer.at" for chunks[0] and "aksearch" for chunks[1]. But we need to have
 * "Record" and "000097874" to be able to build the right URL to the Ajax-Tab
 * which would http://aksearch.arbeiterkammer.at/aksearch/Record/000097874/AjaxTab
 * That's why in our case we need to use chunks[2] and chunks[3] in this case.
 */
function ajaxLoadTab(tabid) {

	// If we're flagged to skip AJAX for this tab, just return true and let the
	// browser handle it.
	if(document.getElementById(tabid).parentNode.className.indexOf('noajax') > -1) {
		return true;
	}

	// Parse out the base URL for the current record:
	var urlParts = document.URL.split('#');
	var urlWithoutFragment = urlParts[0];
	var pathInUrl = urlWithoutFragment.indexOf(path);
	var chunks = urlWithoutFragment.substring(pathInUrl + path.length + 1).split('/');

	// If chunks 2 and 3 are undefined, use chunks 0 and 1 (same as in original code)
	var urlroot = null;
	if (chunks[2] == undefined && chunks[3] == undefined) {
		urlroot = '/' + chunks[0] + '/' + chunks[1];
	} else {
		urlroot = '/' + chunks[2] + '/' + chunks[3];
	}

	// Request the tab via AJAX:
	$.ajax({
		url: path + urlroot + '/AjaxTab',
		cache: false,
		type: 'POST',
		data: {tab: tabid},
		success: function(data) {
			$('#record-tabs .tab-pane.active').removeClass('active');
			$('#'+tabid+'-tab').html(data).addClass('active');
			$('#'+tabid).tab('show');
			registerTabEvents();
			if(typeof syn_get_widget === "function") {
				syn_get_widget();
			}
			window.location.hash = tabid;
		}
	});
	return false;
}