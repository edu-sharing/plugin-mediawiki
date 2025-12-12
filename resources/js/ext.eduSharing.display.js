!function(){function a(a,b){var c=void 0!==window.pageYOffset?window.pageYOffset:(document.documentElement||document.body.parentNode||document.body).scrollTop,d=document.documentElement.clientHeight,e=c+d;b=b||0;var f=a.getBoundingClientRect();if(0===f.height)return!1;var g=f.top+c-b,h=f.bottom+c+b;return h>c&&e>g}jQuery.expr[":"]["near-viewport"]=function(b,c,d){var e=parseInt(d[3])||0;return a(b,e)}}();

function eduSharingScripts() {

	$.ajaxSetup({ cache: false });
	
	function renderEsObject(esObject, wrapper) {
		var url = esObject.attr("data-url");
        var videoFormat = 'webm';
        var v = document.createElement('video');
        if(v.canPlayType && v.canPlayType('video/mp4').replace(/no/, '')) {
            videoFormat = 'mp4';
        }
        url += '&videoFormat='+videoFormat;

		if(typeof wrapper == 'undefined')
            var wrapper = esObject.parent();

		$.get(url, function(data) {
			wrapper.html('').append(data);
			if (data.toLowerCase().indexOf('data-view="lock"') >= 0)
				setTimeout(function(){ renderEsObject(esObject, wrapper);}, 1111);
		});
		
		// $('.edu_wrapper').css({width: 'auto', height: 'auto'});
        $('.edu_wrapper').css({'min-width': '300px', 'height': 'auto'});
		esObject.removeAttr("data-type");
	}
	
	$("[data-type='esObject']:near-viewport(400)").each(function() {
		renderEsObject($(this));
	});

	$(window).scroll(function() {
		$("[data-type='esObject']:near-viewport(400)").each(function() {
			renderEsObject($(this));
		});
	});

    $("body").unbind('click').click(function(e) {
        if ($(e.target).closest(".edusharing_metadata").length) {
            // clicked inside ".edusharing_metadata" - do nothing
        } else {
            $(".edusharing_metadata_toggle_button").text($(".edusharing_metadata_toggle_button").data('textopen'));
            $(".edusharing_metadata").hide();
            if ($(e.target).closest(".edusharing_metadata_toggle_button").length) {
                $(".edusharing_metadata_toggle_button").text($(this).data('textopen'));
                $(".edusharing_metadata").hide();
                toggle_button = $(e.target);
                metadata = toggle_button.parent().find(".edusharing_metadata");
                if (metadata.hasClass('open')) {
                    metadata.removeClass('open');
                    metadata.hide();
                    toggle_button.text(toggle_button.data('textopen'));
                } else {
                    $(".edusharing_metadata").removeClass('open');
                    metadata.addClass('open');
                    metadata.show();
                    toggle_button.text(toggle_button.data('textclose'));
                }
            } else {
                $(".edusharing_metadata").removeClass('open');
            }
        }
    });
};

$(document).ready(function() {
    eduSharingScripts();
});
  
// Reload script when closing VisualEditor
mw.hook( "ve.deactivationComplete" ).add( eduSharingScripts );