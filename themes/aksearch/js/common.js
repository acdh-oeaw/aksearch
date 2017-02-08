/*global isPhoneNumberValid */
/*exported VuFind, htmlEncode, deparam, moreFacets, lessFacets, phoneNumberFormHandler, bulkFormHandler */

// IE 9< console polyfill
window.console = window.console || {log: function polyfillLog() {}};

var VuFind = (function VuFind() {
  var defaultSearchBackend = null;
  var path = null;
  var _initialized = false;
  var _submodules = [];
  var _translations = {};

  var register = function register(name, module) {
    if (_submodules.indexOf(name) === -1) {
      _submodules.push(name);
      this[name] = typeof module == 'function' ? module() : module;
    }
    // If the object has already initialized, we should auto-init on register:
    if (_initialized && this[name].init) {
      this[name].init();
    }
  };
  var init = function init() {
    for (var i = 0; i < _submodules.length; i++) {
      if (this[_submodules[i]].init) {
        this[_submodules[i]].init();
      }
    }
    _initialized = true;
  };

  var addTranslations = function addTranslations(s) {
    for (var i in s) {
      if (s.hasOwnProperty(i)) {
        _translations[i] = s[i];
      }
    }
  };
  var translate = function translate(op) {
    return _translations[op] || op;
  };

  //Reveal
  return {
    defaultSearchBackend: defaultSearchBackend,
    path: path,

    addTranslations: addTranslations,
    init: init,
    register: register,
    translate: translate
  };
})();

/* --- GLOBAL FUNCTIONS --- */
function htmlEncode(value) {
  if (value) {
    return $('<div />').text(value).html();
  } else {
    return '';
  }
}
function extractClassParams(selector) {
  var str = $(selector).attr('class');
  if (typeof str === "undefined") {
    return [];
  }
  var params = {};
  var classes = str.split(/\s+/);
  for (var i = 0; i < classes.length; i++) {
    if (classes[i].indexOf(':') > 0) {
      var pair = classes[i].split(':');
      params[pair[0]] = pair[1];
    }
  }
  return params;
}
// Turn GET string into array
function deparam(url) {
  if (!url.match(/\?|&/)) {
    return [];
  }
  var request = {};
  var pairs = url.substring(url.indexOf('?') + 1).split('&');
  for (var i = 0; i < pairs.length; i++) {
    var pair = pairs[i].split('=');
    var name = decodeURIComponent(pair[0].replace(/\+/g, ' '));
    if (name.length === 0) {
      continue;
    }
    if (name.substring(name.length - 2) === '[]') {
      name = name.substring(0, name.length - 2);
      if (!request[name]) {
        request[name] = [];
      }
      request[name].push(decodeURIComponent(pair[1].replace(/\+/g, ' ')));
    } else {
      request[name] = decodeURIComponent(pair[1].replace(/\+/g, ' '));
    }
  }
  return request;
}

// Sidebar
function moreFacets(id) {
  $('.' + id).removeClass('hidden');
  $('#more-' + id).addClass('hidden');
  //return false; // AK Bibliothek Wien: Causes problems when clicking on "more ..." in side facets.
}
function lessFacets(id) {
  $('.' + id).addClass('hidden');
  $('#more-' + id).removeClass('hidden');
  //return false; // AK Bibliothek Wien: Causes problems when clicking on "more ..." in side facets.
}

// Phone number validation
function phoneNumberFormHandler(numID, regionCode) {
  var phoneInput = document.getElementById(numID);
  var number = phoneInput.value;
  var valid = isPhoneNumberValid(number, regionCode);
  if (valid !== true) {
    if (typeof valid === 'string') {
      valid = VuFind.translate(valid);
    } else {
      valid = VuFind.translate('libphonenumber_invalid');
    }
    $(phoneInput).siblings('.help-block.with-errors').html(valid);
    $(phoneInput).closest('.form-group').addClass('sms-error');
    return false;
  } else {
    $(phoneInput).closest('.form-group').removeClass('sms-error');
    $(phoneInput).siblings('.help-block.with-errors').html('');
  }
}

function bulkFormHandler(event, data) {
  if ($('.checkbox-select-item:checked,checkbox-select-all:checked').length === 0) {
    VuFind.lightbox.alert(VuFind.translate('bulk_noitems_advice'), 'danger');
    return false;
  }
  for (var i in data) {
    if ('print' === data[i].name) {
      return true;
    }
  }
}

// Ready functions
function setupOffcanvas() {
  if ($('.sidebar').length > 0) {
    $('[data-toggle="offcanvas"]').click(function offcanvasClick() {
      $('body.offcanvas').toggleClass('active');
      var active = $('body.offcanvas').hasClass('active');
      var right = $('body.offcanvas').hasClass('offcanvas-right');
      if ((active && !right) || (!active && right)) {
        $('.offcanvas-toggle .fa').removeClass('fa-chevron-right').addClass('fa-chevron-left');
      } else {
        $('.offcanvas-toggle .fa').removeClass('fa-chevron-left').addClass('fa-chevron-right');
      }
      $('.offcanvas-toggle .fa').attr('title', VuFind.translate(active ? 'sidebar_close' : 'sidebar_expand'));
    });
    $('[data-toggle="offcanvas"]').click().click();
  } else {
    $('[data-toggle="offcanvas"]').addClass('hidden');
  }
}

function setupAutocomplete() {
  // Search autocomplete
  $('.autocomplete').each(function autocompleteSetup(i, op) {
    $(op).autocomplete({
      maxResults: 10,
      loadingString: VuFind.translate('loading') + '...',
      handler: function vufindACHandler(input, cb) {
        var query = input.val();
        var searcher = extractClassParams(input);
        var hiddenFilters = [];
        $(input).closest('.searchForm').find('input[name="hiddenFilters[]"]').each(function hiddenFiltersEach() {
          hiddenFilters.push($(this).val());
        });
        $.fn.autocomplete.ajax({
          url: VuFind.path + '/AJAX/JSON',
          data: {
            q: query,
            method: 'getACSuggestions',
            searcher: searcher.searcher,
            type: searcher.type ? searcher.type : $(input).closest('.searchForm').find('.searchForm_type').val(),
            hiddenFilters: hiddenFilters
          },
          dataType: 'json',
          success: function autocompleteJSON(json) {
            if (json.data.length > 0) {
              var datums = [];
              for (var j = 0; j < json.data.length; j++) {
                datums.push(json.data[j]);
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
  // Update autocomplete on type change
  $('.searchForm_type').change(function searchTypeChange() {
    var $lookfor = $(this).closest('.searchForm').find('.searchForm_lookfor[name]');
    $lookfor.autocomplete('clear cache');
  });
}

/**
 * Handle arrow keys to jump to next record
 * @returns {undefined}
 */
/*
// Causes problems with down- and up-arrow for autocomplete.js
function keyboardShortcuts() {
	console.log('keyboardShortcuts from commons.js')
  var $searchform = $('.searchForm_lookfor');
  if ($('.pager').length > 0) {
    $(window).keydown(function shortcutKeyDown(e) {
      if (!$searchform.is(':focus')) {
        var $target = null;
        switch (e.keyCode) {
        case 37: // left arrow key
          $target = $('.pager').find('a.previous');
          if ($target.length > 0) {
            $target[0].click();
            return;
          }
          break;
        case 38: // up arrow key
          if (e.ctrlKey) {
            $target = $('.pager').find('a.backtosearch');
            if ($target.length > 0) {
              $target[0].click();
              return;
            }
          }
          break;
        case 39: //right arrow key
          $target = $('.pager').find('a.next');
          if ($target.length > 0) {
            $target[0].click();
            return;
          }
          break;
        case 40: // down arrow key
          break;
        }
      }
    });
  }
}
*/

//Lightbox
/*
 * This function adds jQuery events to elements in the lightbox
 *
 * This is a default open action, so it runs every time changeContent
 * is called and the 'shown' lightbox event is triggered
 */
function bulkActionSubmit($form) {
  var button = $form.find('[type="submit"][clicked=true]');
  var submit = button.attr('name');
  var checks = $form.find('input.checkbox-select-item:checked');
  if(checks.length == 0 && submit != 'empty') {
    Lightbox.displayError(vufindString['bulk_noitems_advice']);
    return false;
  }
  if (submit == 'print') {
    //redirect page
    var url = path+'/Records/Home?print=true';
    for(var i=0;i<checks.length;i++) {
      url += '&id[]='+checks[i].value;
    }
    document.location.href = url;
  } else {
    $('#modal .modal-title').html(button.attr('title'));
    Lightbox.titleSet = true;
    Lightbox.submit($form, Lightbox.changeContent);
  }
  return false;
}
function registerLightboxEvents() {
	  var modal = $("#modal");
	  // New list
	  $('#make-list').click(function() {
	    var get = deparam(this.href);
	    get['id'] = 'NEW';
	    return Lightbox.get('MyResearch', 'EditList', get);
	  });
	  // New account link handler
	  $('.createAccountLink').click(function() {
	    var get = deparam(this.href);
	    return Lightbox.get('MyResearch', 'Account', get);
	  });
	  $('.back-to-login').click(function() {
	    Lightbox.getByUrl(Lightbox.openingURL);
	    return false;
	  });
	  // Select all checkboxes
	  $(modal).find('.checkbox-select-all').change(function() {
	    $(this).closest('.modal-body').find('.checkbox-select-item').prop('checked', this.checked);
	  });
	  $(modal).find('.checkbox-select-item').change(function() {
	    $(this).closest('.modal-body').find('.checkbox-select-all').prop('checked', false);
	  });
	  // Highlight which submit button clicked
	  $(modal).find("form [type=submit]").click(function() {
	    // Abort requests triggered by the lightbox
	    $('#modal .fa-spinner').remove();
	    // Remove other clicks
	    $(modal).find('[type="submit"][clicked=true]').attr('clicked', false);
	    // Add useful information
	    $(this).attr("clicked", "true");
	    // Add prettiness
	    if($(modal).find('.has-error,.sms-error').length == 0 && !$(this).hasClass('dropdown-toggle')) {
	      $(this).after(' <i class="fa fa-spinner fa-spin"></i> ');
	    }
	  });
	  /**
	   * Hide the header in the lightbox content
	   * if it matches the title bar of the lightbox
	   */
	  var header = $('#modal .modal-title').html();
	  var contentHeader = $('#modal .modal-body h2');
	  contentHeader.each(function(i,op) {
	    if (op.innerHTML == header) {
	      $(op).hide();
	    }
	  });
	}
function updatePageForLogin() {
	  // Hide "log in" options and show "log out" options:
	  $('#loginOptions').addClass('hidden');
	  $('.logoutOptions').removeClass('hidden');

	  var recordId = $('#record_id').val();

	  // Update user save statuses if the current context calls for it:
	  if (typeof(checkSaveStatuses) == 'function') {
	    checkSaveStatuses();
	  }

	  // refresh the comment list so the "Delete" links will show
	  $('.commentList').each(function(){
	    var recordSource = extractSource($('#record'));
	    refreshCommentList(recordId, recordSource);
	  });

	  var summon = false;
	  $('.hiddenSource').each(function(i, e) {
	    if(e.value == 'Summon') {
	      summon = true;
	      // If summon, queue reload for when we close
	      Lightbox.addCloseAction(function(){document.location.reload(true);});
	    }
	  });

	  // Refresh tab content
	  var recordTabs = $('.recordTabs');
	  if(!summon && recordTabs.length > 0) { // If summon, skip: about to reload anyway
	    var tab = recordTabs.find('.active a').attr('id');
	    ajaxLoadTab(tab);
	  }

	  // Refresh tag list
	  if(typeof refreshTagList === "function") {
	    refreshTagList(true);
	  }
	}
	function newAccountHandler(html) {
	  updatePageForLogin();
	  var params = deparam(Lightbox.openingURL);
	  if (params['subaction'] != 'UserLogin') {
	    Lightbox.getByUrl(Lightbox.openingURL);
	    Lightbox.openingURL = false;
	  } else {
	    Lightbox.close();
	  }
	}

//This is a full handler for the login form
function ajaxLogin(form) {
  Lightbox.ajax({
    url: path + '/AJAX/JSON?method=getSalt',
    dataType: 'json',
    success: function(response) {
      if (response.status == 'OK') {
        var salt = response.data;

        // extract form values
        var params = {};
        for (var i = 0; i < form.length; i++) {
          // special handling for password
          if (form.elements[i].name == 'password') {
            // base-64 encode the password (to allow support for Unicode)
            // and then encrypt the password with the salt
            var password = rc4Encrypt(
                salt, btoa(unescape(encodeURIComponent(form.elements[i].value)))
            );
            // hex encode the encrypted password
            params[form.elements[i].name] = hexEncode(password);
          } else {
            params[form.elements[i].name] = form.elements[i].value;
          }
        }

        // login via ajax
        Lightbox.ajax({
          type: 'POST',
          url: path + '/AJAX/JSON?method=login',
          dataType: 'json',
          data: params,
          success: function(response) {
            if (response.status == 'OK') {
              updatePageForLogin();
              // and we update the modal
              var params = deparam(Lightbox.lastURL);
              if (params['subaction'] == 'UserLogin') {
                Lightbox.close();
              } else {
                Lightbox.getByUrl(
                  Lightbox.lastURL,
                  Lightbox.lastPOST,
                  Lightbox.changeContent
                );
              }
            } else {
              Lightbox.displayError(response.data);
            }
          }
        });
      } else {
        Lightbox.displayError(response.data);
      }
    }
  });
}

$(document).ready(function commonDocReady() {
  // Start up all of our submodules
  VuFind.init();
  // Setup search autocomplete
  setupAutocomplete();
  // Off canvas
  setupOffcanvas();
  // Keyboard shortcuts in detail view
  keyboardShortcuts();

  // support "jump menu" dropdown boxes
  $('select.jumpMenu').change(function jumpMenu(){ $(this).parent('form').submit(); });

  // Checkbox select all
  $('.checkbox-select-all').change(function selectAllCheckboxes() {
    $(this).closest('form').find('.checkbox-select-item').prop('checked', this.checked);
  });
  $('.checkbox-select-item').change(function selectAllDisable() {
    $(this).closest('form').find('.checkbox-select-all').prop('checked', false);
  });

  // handle QR code links
  $('a.qrcodeLink').click(function qrcodeToggle() {
    if ($(this).hasClass("active")) {
      $(this).html(VuFind.translate('qrcode_show')).removeClass("active");
    } else {
      $(this).html(VuFind.translate('qrcode_hide')).addClass("active");
    }

    var holder = $(this).next('.qrcode');
    if (holder.find('img').length === 0) {
      // We need to insert the QRCode image
      var template = holder.find('.qrCodeImgTag').html();
      holder.html(template);
    }
    holder.toggleClass('hidden');
    return false;
  });

  // Print
  var url = window.location.href;
  if (url.indexOf('?' + 'print' + '=') !== -1 || url.indexOf('&' + 'print' + '=') !== -1) {
    $("link[media='print']").attr("media", "all");
    $(document).ajaxStop(function triggerPrint() {
      window.print();
    });
    // Make an ajax call to ensure that ajaxStop is triggered
    $.getJSON(VuFind.path + '/AJAX/JSON', {method: 'keepAlive'});
  }

  // Advanced facets
  $('.facetOR').click(function facetBlocking() {
    $(this).closest('.collapse').html('<div class="list-group-item">' + VuFind.translate('loading') + '...</div>');
    window.location.assign($(this).attr('href'));
  });
  
  $('[name=bulkActionForm]').submit(function() {
    return bulkActionSubmit($(this));
  });
  $('[name=bulkActionForm]').find("[type=submit]").click(function() {
    // Abort requests triggered by the lightbox
    $('#modal .fa-spinner').remove();
    // Remove other clicks
    $(this).closest('form').find('[type="submit"][clicked=true]').attr('clicked', false);
    // Add useful information
    $(this).attr("clicked", "true");
  });
  
  
  /******************************
   * LIGHTBOX DEFAULT BEHAVIOUR *
   ******************************/
  Lightbox.addOpenAction(registerLightboxEvents);

  Lightbox.addFormCallback('newList', Lightbox.changeContent);
  Lightbox.addFormCallback('accountForm', newAccountHandler);
  Lightbox.addFormCallback('bulkDelete', function(html) {
    location.reload();
  });
  Lightbox.addFormCallback('bulkSave', function(html) {
    Lightbox.addCloseAction(updatePageForLogin);
    Lightbox.confirm(vufindString['bulk_save_success']);
  });
  Lightbox.addFormCallback('bulkRecord', function(html) {
    Lightbox.close();
    checkSaveStatuses();
  });
  Lightbox.addFormCallback('emailSearch', function(html) {
    Lightbox.confirm(vufindString['bulk_email_success']);
  });
  Lightbox.addFormCallback('saveRecord', function(html) {
    Lightbox.close();
    checkSaveStatuses();
  });

  Lightbox.addFormHandler('exportForm', function(evt) {
    $.ajax({
      url: path + '/AJAX/JSON?' + $.param({method:'exportFavorites'}),
      type:'POST',
      dataType:'json',
      data:Lightbox.getFormData($(evt.target)),
      success:function(data) {
        if(data.data.export_type == 'download' || data.data.needs_redirect) {
          document.location.href = data.data.result_url;
          Lightbox.close();
          return false;
        } else {
          Lightbox.changeContent(data.data.result_additional);
        }
      },
      error:function(d,e) {
        //console.log(d,e); // Error reporting
      }
    });
    return false;
  });
  Lightbox.addFormHandler('feedback', function(evt) {
    var $form = $(evt.target);
    // Grabs hidden inputs
    var formSuccess     = $form.find("input#formSuccess").val();
    var feedbackFailure = $form.find("input#feedbackFailure").val();
    var feedbackSuccess = $form.find("input#feedbackSuccess").val();
    // validate and process form here
    var name  = $form.find("input#name").val();
    var email = $form.find("input#email").val();
    var comments = $form.find("textarea#comments").val();
    if (name.length == 0 || comments.length == 0) {
      Lightbox.displayError(feedbackFailure);
    } else {
      Lightbox.get('Feedback', 'Email', {}, {'name':name,'email':email,'comments':comments}, function() {
        Lightbox.changeContent('<div class="alert alert-info">'+formSuccess+'</div>');
      });
    }
    return false;
  });
  Lightbox.addFormHandler('loginForm', function(evt) {
    ajaxLogin(evt.target);
    return false;
  });
  
  //Feedback
  $('#feedbackLink').click(function() {
    return Lightbox.get('Feedback', 'Home');
  });
  // Help links
  $('.help-link').click(function() {
    var split = this.href.split('=');
    return Lightbox.get('Help','Home',{topic:split[1]});
  });
  // Hierarchy links
  $('.hierarchyTreeLink a').click(function() {
    var id = $(this).parent().parent().parent().find(".hiddenId")[0].value;
    var hierarchyID = $(this).parent().find(".hiddenHierarchyId")[0].value;
    return Lightbox.get('Record','AjaxTab',{id:id},{hierarchy:hierarchyID,tab:'HierarchyTree'});
  });
  // Login link
  $('#loginOptions a.modal-link').click(function() {
    return Lightbox.get('MyResearch','UserLogin');
  });
  // Email search link
  $('.mailSearch').click(function() {
    return Lightbox.get('Search','Email',{url:document.URL});
  });
  // Save record links
  $('.save-record').click(function() {
    var parts = this.href.split('/');
    return Lightbox.get(parts[parts.length-3],'Save',{id:$(this).attr('id')});
  });
});