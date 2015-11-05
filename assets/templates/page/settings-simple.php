<?php

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) { die(); }

?>
<div class="cf-settings-content">
	<h1><?php echo $args['heading']; ?></h1>
	<form action="options.php" method="POST">
		<?php
			settings_fields( $args['settings_group'] );
			do_settings_sections( $args['settings_id'] );
			submit_button();
		?>
	</form>
</div>
