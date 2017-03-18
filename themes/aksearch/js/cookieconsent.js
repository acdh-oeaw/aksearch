/*
 * MIT License for cookieconsent code
 * 
 * Copyright (c) 2015 Silktide Ltd
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files
 * (the “Software”), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge,
 * publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR
 * ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH
 * THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
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
		}
	})
});