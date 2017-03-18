window.addEventListener("load", function() {
	
	var url = document.URL;
	var urlWithoutHttp = url.replace(/^https?\:\/\//i, "");
	var chunks = urlWithoutHttp.split('/');
	var baseUrl = '';
	if (chunks[1] == 'aksearch') {
		baseUrl = '/aksearch';
	}
	
	window.cookieconsent.initialise({
		"palette": {
			"popup": {
				"background": "#777777"
				},
				"button": {
					"background": "#be0021"
				}
			},
		"theme": "edgeless",
		"position": "top",
		"content": {
			"message": "Um Ihnen den bestmöglichen Service zu bieten, speichert diese Website Informationen über Ihren Besuch in sogenannten Cookies. Durch die Nutzung dieser Webseite erklären Sie sich mit der Verwendung von Cookies einverstanden. Weitere Informationen finden Sie in der ",
			"dismiss": "OK",
			"link": "Datenschutzerklärung.",
			"href": baseUrl + "/AkSites/DataPrivacyStatement",
		},
		"elements": {
			"message": '<span id="cookieconsent:desc" class="cc-message">{{message}}</span>',
			"messagelink": '<span id="cookieconsent:desc" class="cc-message">{{message}} <a aria-label="learn more about cookies" tabindex="0" class="cc-link" href="{{href}}" target="_self">{{link}}</a></span>',
			"dismiss": '<a aria-label="dismiss cookie message" tabindex="0" class="cc-btn cc-dismiss">{{dismiss}}</a>',
			"link": '<a aria-label="learn more about cookies" tabindex="0" class="cc-link" href="{{href}}" target="_self">{{link}}</a>',
		}
	})
});