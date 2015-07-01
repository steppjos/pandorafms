<?php
global $config;
check_login ();
ui_require_css_file ('firts_task');
?>
<?php ui_print_info_message ( array('no_close'=>true, 'message'=>  __('There are no collections defined yet.') ) ); ?>

<div class="new_task">
	<div class="image_task">
		<?php echo html_print_image('images/icono_grande_reconserver.png', true, array("alt" => __('Recon server')));?>
	</div>
	<div class="text_task">
		<h3> <?php echo __('Create Collections'); ?></h3>
		<p id="description_task"> <?php echo __("A file collection is a group of files (e.g. scripts or executables) which are 
		automatically copied to a specific directory of the agent (under Windows or UNIX). The file collections allow to be propagated
		 along with the policies in order to be used by a group of agents, using a 'package' of scripts and modules which use them.
		First we learn how to use the file collections in the agent's view, how to conduct it manually, agent by agent, without using collections,
		 and how to do the same thing by using policies.Our first task is to arrange a compilation of files. In order to do this, please go to the agent's 
		 administrator. Subsequently, we're going to see a 'sub option' called 'Collections'. Please click on it in order to create a new collection as we can see on 
		 the picture below. "); ?></p>
		<form action="index.php?sec=gagente&sec2=enterprise/godmode/agentes/collections&action=new" method="post">
			<input type="submit" class="button_task" value="<?php echo __('Create Collections'); ?>" />
		</form>
	</div>
</div>