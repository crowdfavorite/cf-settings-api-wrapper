<?php

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) { die(); }

$at_least_one = false;
?>
<table class="field-group">
<?php foreach ( $args as $field_args ) : ?>
	<?php
		if ( ! is_array( $field_args ) ) {
			continue;
		}
		$field_args['in_group'] = true;
		$at_least_one = true;
	?>
	<tr class="js-top">
		<td class="field-group-entry">
	<?php
		if ( ! $field_args['type'] ) {
			$field_args['type'] = 'default';
		}
		$do_template = D23_Membership_Config::field_type_callback( $field_args['type'] );

		$do_template( $field_args );
	?>
		</td>
	</tr>
<?php endforeach; ?>
</table>

<?php if ( ! $at_least_one ) : ?>
	<span>Missing Field Template for Setting:</span>
	<pre><?php print_r( $args ); ?></pre>
<?php endif; ?>
