<?php

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) { die(); }

$rel = str_replace( trailingslashit( site_url() ), '', $args['value'] );
?>

<div class="field-rel_link js-rel_link">
	<?php if ( $args['in_group'] ) : ?>
	<label class="grouped-label" for="<?php echo esc_attr( $args['name'] ); ?>">
		<?php echo $args['label']; ?>
	</label>
	<?php endif; ?>
	<div class="rel_link-input-wrapper">
		<code class="js-click-to-edit-pre"><?php echo trailingslashit( site_url() ); ?></code>
		<input
			id="<?php echo esc_attr( $args['name'] ); ?>"
			class="js-click-to-edit-target"
			type="text"
			name="<?php echo esc_attr( $args['name'] ); ?>"
			value="<?php echo esc_attr( $args['value'] ); ?>"
			size="50"
			style="display:none !important;"
		/>
		<div class="js-click-to-edit click-to-edit">
			<span class="<?php if ( ! $rel ) echo 'empty'; ?>">
				<?php echo $rel; ?>
			</span>
		</div>
	</div>
<?php if ( isset( $args['description'] ) ): ?>
	<p class="description"><?php echo wp_kses( $args['description'], $allowed_tags ); ?></p>
<?php endif; ?>
</div>

<?php
