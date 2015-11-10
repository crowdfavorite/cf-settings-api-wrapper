(function($) {
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
			closure_functions.updateClickToEditTarget(this, false);
		}
	})

	.on('focusout', '.js-click-to-edit-input', function() {
		closure_functions.updateClickToEditTarget(this, true);
	})

	.on('change', 'input[has-action]', function() {
		/**
		*  Run a function from a caller with a proxied relation scope
		*  Expected input:
		*  <function>__<relation proxy>__<target relative to proxy>
		*
		*  That is to say, we're running actions based on interaction with a form
		*  element but changing elements that may be related to, for instance, that
		*  element's parent or grandparent. If chaning elements are related to the
		*  calling element directly, use
		*  <function>__self__<target relative to proxy==self>
		*
		*  Eg, setting toggle on a true_false input checkbox:
		*  'toggle__sub-field__nextAll'
		*  ==>
		*  when checkbox is clicked, toggle all subsequent siblings of the checkbox's closest
		*  sub-field container.
		*
		*  if the page element
		*  		[X] Some Label
		*  is the visual output of the HTML
		*
		*  <tr class="sub-field">
		*  	<td>
		*  		<input type="checkbox" id="asdf" val="1" checked="checked" />
		*  		<label for="asdf">My Toggler</label>
		*  	</td>
		*  </tr>
		*
		*  Then visually, we would get the interaction
		*
		*  My Input Group
		*  				[X] My Toggler
		*  				[X] My next input, which depends on the Toggler
		*  				[X] My final input, which also depends on the Toggler
		*  ==>
		*  My Input Group
		*  				[ ] My Toggler
		*  				<!-- these elements have been toggled to hide -->
		*
		*/
		var $this = $(this),
		    actions = $this.attr('actions').split(' ');

		$(actions).each(function(i, action) {
			var args = action.split('__'),
			    behavior = args[0],
			    $actor_proxy = $(closure_functions.interpret_proxy($this, args[1])),
			    $target = $(closure_functions.interpret_target($actor_proxy, args[2]));

			if (typeof closure_functions[behavior] == 'function') {
				closure_functions[behavior]($this, $actor_proxy, $target);
			}
		});
	});

	var closure_functions = {

		interpret_proxy: function($caller, name) {
			if (! name) {
				return;
			}

			var known_proxies = {
				'self': $caller,
				'field-group': ['closest', '.field-group'],
				'sub-field': ['closest', '.sub-field']
			},
			    known_selectors;

			for (var known_name in known_proxies) {
				if (name == known_name) {
					if ($.isArray(known_proxies[known_name])) {
						known_selectors = known_proxies[known_name];
						return $caller[known_selectors[0]](known_selectors[1]);
					}
					else if (known_proxies[known_name] instanceof $) {
						return known_proxies[known_name];
					}
				}
			}
		},

		interpret_target: function($actor_proxy, name) {
			if (typeof $actor_proxy[name] == 'function' && $actor_proxy[name]().length) {
				return $actor_proxy[name]();
			}
		},

		updateClickToEditTarget: function(input, removeInput) {
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

			if ($this.val()) {
				$display.find('.empty')
					.removeClass('empty');
			}
			else {
				$display.find('span')
					.addClass('empty');
			}

			$display
				.find('span')
					.html($this.val().trim())
					.end()
				.show();

			if (removeInput) {
				$this.remove();
			}
		},

		toggle: function($caller, $proxy, $target) {
			var binary_actors = [
				'checkbox',
				'radio'
			],
			    caller_is_binary = false;
			$(binary_actors).each(function(i, bin_act) {
				if ($caller.is('[type=' + bin_act + ']')) {
					caller_is_binary = true;
					return false;
				}
			});

			if (caller_is_binary) {
				if ($caller.is(':checked')) {
					$target.show();
				}
				else {
					$target.hide();
				}
			}
		}
	};

})(jQuery);
