<?php

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) { die(); }

if ( ! $args['options'] ) {
	// Let the invalid_args template know what we're missing
	$args['missing_fields'] = array(
		'options'
	);
	include dirname(__FILE__) . '/invalid_args.php';
}
else {
?>

<div>
	<?php if ( $args['in_group'] ) : ?>
	<label class="grouped-label" for="<?php echo esc_attr( $args['name'] ); ?>">
		<?php echo $args['label']; ?>
	</label>
	<?php endif; ?>
	<select
		id="<?php echo esc_attr( $args['name'] ); ?>"
		name="<?php echo esc_attr( $args['name'] ); ?>"
		value="<?php echo esc_attr( $args['value'] ); ?>"
		<?php if ( isset( $args['actions'] ) && $args['actions'] ) : ?>
			has-action
			actions="<?php foreach ( $args['actions'] as $action ) { echo $action; } ?>"
		<?php endif; ?>
	>
	<?php foreach ( $args['options'] as $option => $option_value ) :
			?>
		<option value="<?php echo $option;?>" <?php selected( $option, $args['value'] );?>><?php echo $option_value; ?></option>
			<?php
		endforeach;
	?>
	</select>
<?php if ( isset( $args['description'] ) ): ?>
	<p class="description"><?php echo wp_kses( $args['description'], $allowed_tags ); ?></p>
<?php endif; ?>
</div>
<?php
}
