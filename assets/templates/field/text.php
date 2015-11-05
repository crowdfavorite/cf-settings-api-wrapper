<?php

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) { die(); }

?>

<div>
	<?php if ( $args['in_group'] ) : ?>
	<label class="grouped-label" for="<?php echo esc_attr( $args['name'] ); ?>">
		<?php echo $args['label']; ?>
	</label>
	<?php endif; ?>
	<?php if ( isset( $args['long_field'] ) && $args['long_field'] ) : ?>
		<span class="click-to-edit"><?php echo esc_html( $args['value'] ); ?></span>
	<?php endif; ?>
	<input
		<?php if ( isset( $args['long_field'] ) && $args['long_field'] ) : ?>
			class="hidden"
		<?php endif; ?>
		id="<?php echo esc_attr( $args['name'] ); ?>"
		type="text"
		name="<?php echo esc_attr( $args['name'] ); ?>"
		value="<?php echo esc_attr( $args['value'] ); ?>"
		size="50"
	/>
<?php if ( isset( $args['description'] ) ): ?>
	<p class="description"><?php echo wp_kses( $args['description'], $allowed_tags ); ?></p>
<?php endif; ?>
</div>
