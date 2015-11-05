<?php

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) { die(); }

?>

<?php if ( $args['label'] ) : ?>
	<span><?php echo esc_html( $args['label'] ); ?> Settings</span>
<?php endif; ?>

<?php
