(function($){
	var broken = [];
	$('img').error(function(){
		var src = $(this).attr('src');

		if ( src.indexOf('imageshack.us') > 0 )
			broken.push(src);
	});

	$(window).load(function(){
		if ( broken.length == 0 )
			return;

		var data = {action: 'imageshack-offloader'};

		for ( var i in broken )
			data['urls[' + i + ']'] = broken[i];

		$.post(iShackL10n.ajax, data);
	});
})(jQuery);
