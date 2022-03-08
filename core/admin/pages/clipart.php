<?php
	global $customdesign;

	$section = 'clipart';
	$fields = $customdesign_admin->process_data(array(
		array(
			'type' => 'input',
			'name' => 'name',
			'label' => $customdesign->lang('Name'),
			'required' => true,
			'default' => 'Untitled'
		),
		array(
			'type' => 'categories',
			'cate_type' => 'cliparts',
			'name' => 'categories',
			'label' => $customdesign->lang('Categories'),
			'id' => isset($_GET['id'])? $_GET['id'] : 0,
			'db' => false
		),
		array(
			'type' => 'tags',
			'tag_type' => 'cliparts',
			'name' => 'tags',
			'label' => $customdesign->lang('Tags'),
			'id' => isset($_GET['id'])? $_GET['id'] : 0,
			'desc' => $customdesign->lang('Example: tag1, tag2, tag3 ...'),
		),
		array(
			'type' => 'upload',
			'name' => 'upload',
			'path' => 'cliparts'.DS.date('Y').DS.date('m').DS,
			'thumbn' => 'thumbnail_url',
			'label' => $customdesign->lang('Upload design file'),
			'desc' => $customdesign->lang('Supported files svg, png, jpg, jpeg. Max size 5MB')
		),
		array(
			'type' => 'input',
			'name' => 'price',
			'label' => $customdesign->lang('Price'),
			'default' => 0
		),
		array(
			'type' => 'toggle',
			'name' => 'featured',
			'label' => $customdesign->lang('Featured'),
			'default' => 'no',
			'value' => null
		),
		array(
			'type' => 'toggle',
			'name' => 'active',
			'label' => $customdesign->lang('Active'),
			'default' => 'yes',
			'value' => null
		),
		array(
			'type' => 'input',
			'name' => 'order',
			'type_input' => 'number',
			'label' => $customdesign->lang('Order'),
			'default' => 0,
			'desc' => $customdesign->lang('Ordering of item with other.')
		),
	), 'cliparts');

?>

<div class="customdesign_wrapper" id="customdesign-<?php echo $section; ?>-page">
	<div class="customdesign_content">
		<?php
			$customdesign->views->detail_header(array(
				'add' => $customdesign->lang('Add new clipart'),
				'edit' => $fields[0]['value'],
				'page' => $section
			));
		?>
		<form action="<?php echo $customdesign->cfg->admin_url; ?>customdesign-page=<?php
			echo $section.(isset($_GET['callback']) ? '&callback='.$_GET['callback'] : '');
		?>" id="customdesign-clipart-form" method="post" class="customdesign_form" enctype="multipart/form-data">

			<?php $customdesign->views->tabs_render($fields); ?>

			<div class="customdesign_form_group customdesign_form_submit">
				<input type="submit" class="customdesign-button customdesign-button-primary" value="<?php echo $customdesign->lang('Save Clipart'); ?>"/>
				<input type="hidden" name="do" value="action" />
				<a class="customdesign_cancel" href="<?php echo $customdesign->cfg->admin_url;?>customdesign-page=cliparts">
					<?php echo $customdesign->lang('Cancel'); ?>
				</a>
				<input type="hidden" name="customdesign-section" value="<?php echo $section; ?>">
			</div>
		</form>
	</div>
</div>
