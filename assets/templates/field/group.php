<?php

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) { die(); }

?>
<table class="field-group">
<?php foreach ( $args['multi'] as $sub_field ) : ?>
	<tr class="sub-field">
		<td>
	<?php
		$sub_field['renderer']( $sub_field );
	?>
		</td>
	</tr>
<?php endforeach; ?>
</table>
<?php
