(function($){
	$(document)

	.on('click', '.js-rel_link label, .js-rel_link code', function() {
		$(this).parents('.js-rel_link').find('.js-click-to-edit')
			.trigger('click');
	})

	.on('click', '.js-click-to-edit', function() {
		var $this = $(this),
		    $tmpInput = $('<input class="js-click-to-edit-input" type="text">');

		$this
			.hide()
			.before($tmpInput);

		$tmpInput
			.val($this.find('span').html().trim())
			.data('display', $this)
			.focus();
	})

	.on('keypress', '.js-click-to-edit-input', function(e) {
		if (e.which == 13) {
			e.preventDefault();
			updateClickToEditTarget(this, false);
		}
	})

	.on('focusout', '.js-click-to-edit-input', function() {
		updateClickToEditTarget(this, true);
	});

	function updateClickToEditTarget(input, removeInput) {
		var $this = $(input),
		    $display = $($this.data('display')),
		    $pres = $this.siblings('.js-click-to-edit-pre'),
		    $posts = $this.siblings('.js-click-to-edit-post'),
		    $target = $this.siblings('.js-click-to-edit-target'),
		    val = '';

		$this
			.hide();

		$pres.each(function() {
			var $pre = $(this);
			if ($pre.is('input')) {
				val += $pre.val();
			}
			else {
				val += $pre.html();
			}
		});

		val += $this.val();

		$posts.each(function() {
			var $post = $(this);
			if ($post.is('input')) {
				val += $post.val();
			}
			else {
				val += $post.html();
			}
		});

		$target.val(val);

		if (val) {
			$display.find('.empty')
				.removeClass('empty');
		}

		$display
			.find('span')
				.html($this.val().trim())
				.end()
			.show();

		if (removeInput) {
			$this.remove();
		}
	}

})(jQuery);
