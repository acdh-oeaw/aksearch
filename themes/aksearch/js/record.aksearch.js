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
 * That's why in case that we use the "aksearch" subfolder in the url, we need to use
 * other chunks (probably chunks[2] and chunks[3]).
 * The goal is to build a varialbe named 'urlroot' in this format '/Record/recordId'
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
	var urlWithoutHttp = urlWithoutFragment.replace(/^https?\:\/\//i, "");
	var chunks = urlWithoutHttp.split('/');
	var indexOfRecord = chunks.indexOf('Record'); // Get the index of the word 'Record'

	// Get the chunk with 'Record' (see indexOfRecord above) and the next one (which should be the chunk with the record id)
	// 'urlroot' must be in this format: '/Record/recordId'
	var urlroot = '/' + chunks[indexOfRecord] + '/' + chunks[indexOfRecord+1];

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


/**
 * Removing the little flag from font-awesome on the "place hold" button
 */
/*
function checkRequestIsValid(element, requestURL, requestType, blockedClass) {
	
	var recordId = requestURL.match(/\/Record\/([^\/]+)\//)[1];
	var vars = {}, hash;
	var hashes = requestURL.slice(requestURL.indexOf('?') + 1).split('&');

	for(var i = 0; i < hashes.length; i++)
	{
		hash = hashes[i].split('=');
		var x = hash[0];
		var y = hash[1];
		vars[x] = y;
	}
	vars['id'] = recordId;

	var url = path + '/AJAX/JSON?' + $.param({method:'checkRequestIsValid', id: recordId, requestType: requestType, data: vars});
	
	// Wait some time before checking the if the request is valid to be sure that the user is seen as logged in.
	setTimeout(function(){
		$.ajax({
			dataType: 'json',
			cache: false,
			url: url,
			success: function(response) {
				//console.log(response);
				if (response.status == 'OK') {
					if (response.data.status) {
						$(element).removeClass('disabled')
						.attr('title', response.data.msg)
						.html(response.data.msg);
						//.html('<i class="fa fa-flag"></i>&nbsp;'+response.data.msg);
					} else {
						$(element).remove();
					}
				} else if (response.status == 'NEED_AUTH') {
					$(element).replaceWith('<span class="' + blockedClass + '">' + response.data.msg + '</span>');
				}
			},
			error: function(response) {
				//console.log(response);
			}
		});
	},
	200);
}
*/


function checkRequestIsValid(element, requestURL, requestType, blockedClass) {
	// Do nothing as this can cause errors when checking a lot of items (e. g. from newspapers)
}

/*
function getItemAvailability() {
	var url = path + '/AJAX/JSON';
	var itemId = 'ID_MIKE';
	var data = {
		method: 'getItemAvailability',
		itemId: itemId
	};
	
	$.ajax({
		dataType: 'json',
		url: url,
		data: data,
		success: function(response) {
			console.log('Success:');
			console.log(response);
			
//			if (response.status == 'OK') {
//				if (response.data.status) {
//					$(element).removeClass('disabled')
//					.attr('title', response.data.msg)
//					.html(response.data.msg);
//					//.html('<i class="fa fa-flag"></i>&nbsp;'+response.data.msg);
//				} else {
//					$(element).remove();
//				}
//			} else if (response.status == 'NEED_AUTH') {
//				$(element).replaceWith('<span class="' + blockedClass + '">' + response.data.msg + '</span>');
//			}
			
		},
		error: function(response) {
			console.log('Error:');
			console.log(response);
		}
	});	
}
*/


$(document).ready(function() {

	// Login link in holdings tab
	$('#holdings-tab').on('click', '.akOpenLogin', function(e) {

		// Get title for lightbox
		var title = $(this).attr('title');
		if(typeof title === "undefined") {
			title = $(this).html();
		}
		$('#modal .modal-title').html(title);
		Lightbox.titleSet = true;

		return Lightbox.get('MyResearch', 'UserLogin');
	});
	
	
//	getItemAvailability();
	
});

