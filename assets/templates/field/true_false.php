<?php

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) { die(); }

?>

<div>
	<?php if ( $args['in_group'] ) : ?>
	<label class="grouped-label" for="<?php echo esc_attr( $args['name'] ); ?>">
		<?php echo $args['label']; ?>
	</label>
	<?php endif; ?>
	<input
		id="<?php echo $args['name']; ?>"
		name="<?php echo $args['name']; ?>"
		type="checkbox"
		value="1"
	<?php if ( isset( $args['classes'] ) && $args['classes'] ): ?>
		class="<?php echo implode( ' ', $args['classes'] ); ?>"
	<?php endif; ?>
		<?php checked( $args['value'] ); ?>
	/>
	<?php if ( isset( $args['label'] ) ): ?>
	<label for="<?php echo $args['name']; ?>">
		<?php echo wp_kses( $args['label'], $allowed_tags ); ?>
	</label>
	<?php endif; ?>

	<?php if ( isset( $args['description'] ) ): ?>
		<p class="description"><?php echo wp_kses( $args['description'], $allowed_tags ); ?></p>
	<?php endif; ?>
</div>
