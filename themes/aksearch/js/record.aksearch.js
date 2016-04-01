
//OVERWRITE FOR AKSEARCH
//Problem with substring if same string exists twice in Record-URL.
//Example Record-URL: http://aksearch.arbeiterkammer.at/aksearch/Record/000097874#holdings
//The substring and split command (see "var chunks = ..." below) cuts the URL into chunks.
//With the original script, the chunks[0] and chunks[1] would be used. This would be
//"arbeiterkammer.at" for chunks[0] and "aksearch" for chunks[1]. But we need to have
//"Record" and "000097874" to be able to build the right URL to the Ajax-Tab
//which would http://aksearch.arbeiterkammer.at/aksearch/Record/000097874/AjaxTab
//That's why in our case we need to use chunks[2] and chunks[3].


function ajaxLoadTab(tabid) {

//	if we're flagged to skip AJAX for this tab, just return true and let the
//	browser handle it.
	if(document.getElementById(tabid).parentNode.className.indexOf('noajax') > -1) {
		return true;
	}

//	Parse out the base URL for the current record:
	var urlParts = document.URL.split('#');
	var urlWithoutFragment = urlParts[0];
	var pathInUrl = urlWithoutFragment.indexOf(path);
	var chunks = urlWithoutFragment.substring(pathInUrl + path.length + 1).split('/');

	var urlroot = null;
	if (chunks[2] == undefined && chunks[3] == undefined) {
		urlroot = '/' + chunks[0] + '/' + chunks[1];
	} else {
		urlroot = '/' + chunks[2] + '/' + chunks[3];
	}

//	Request the tab via AJAX:
	$.ajax({
		url: path + urlroot + '/AjaxTab',
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
