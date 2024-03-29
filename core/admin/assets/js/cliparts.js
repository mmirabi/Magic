jQuery(document).ready(function($){

	var wrp = $('#magic-popup'),
	
		show = function(){
			wrp.css({opacity:0}).show().animate({opacity: 1}, 250).find('header input').focus();
			wrp.find('.magic-popup-content').css({top: '-50px', opacity: 0}).animate({top: 0, opacity: 1}, 250);
		},
		
		hide = function(){
			wrp.animate({opacity: 0}, 250, function(){wrp.hide();});
			wrp.find('.magic-popup-content').animate({top: '-50px', opacity: 0}, 250);
		},
		
		doing = function() {
			if (done < total) {
				$('#magic-cliparts-bundle-stt').html('<i class="fa fa-spinner fa-spin fa-fw"></i> Processing '+done+'/'+total);
			} else {
				$('#magic-cliparts-bundle-stt').html('Processed '+done+'/'+total);
			}
		},
		
		svguni = function(data) {
			
			if (data.indexOf('image/svg+xml') === -1)
				return data;
			
			data = data.split(',');
			data[1] = $('<div>'+atob(data[1].replace('viewbox=', 'viewBox='))+'</div>');
			
			data[1].find('[id]').each(function(){
				this.id = this.id.replace(/[\u{0080}-\u{FFFF}]/gu,"");
			});
			
			var svg = data[1].find('svg').get(0);
			
			if (!svg.getAttribute('width'))
				svg.setAttribute('width', '1000px');
			
			if (!svg.getAttribute('height')) {
				var vb = svg.getAttribute('viewBox').trim().split(' ');
				svg.setAttribute('height', (1000*(parseFloat(vb[3])/parseFloat(vb[2])))+'px');
			};
			
			data[1] = btoa(data[1].html());
			
			return data[0]+','+data[1];
			
		},
		
		process_files = function(files) {

			for (var i=0; i<files.length; i++) {

				if (magic_validate_file(files[i])) {

					total ++;
					doing();

					reader[total] = new FileReader();
					reader[total].f = total;
					reader[total].file = files[i];
					reader[total].addEventListener("load", function () {
						
						var result = svguni(this.result);
						
						var f = reader[this.f].file,
							name = f.name.replace(/[^0-9a-zA-Z\.\-\_]/g, "").trim().replace(/\ /g, '+').split('.'),
							cates = [];

						name.pop();

						$('#magic-list-categories input[type="checkbox"]:checked').map(function(){
							cates.push(this.value);
						});

						var data = {
							upload: {
								data: result,
								type: f.type ? f.type : f.name.split('.').pop(),
								size: f.size,
								name: 'magic-base-'+f.name.replace(/[^0-9a-zA-Z\.\-\_]/g, "").trim().replace(/\ /g, '+'),
							},
							name: name.join('.'),
							cates: cates,
							tags: $('#magic-cliparts-tags').val().split(','),
							price: $('#magic-cliparts-price').val(),
							featured: $('#magic-cliparts-featured').get(0).checked
						};

						magic_create_thumbn({
							source: result,
							width: 320,
							callback: function(res) {

								data.upload.thumbn = res;

						    	$.ajax({
									url: MagicDesign.ajax,
									method: 'POST',
									data: MagicDesign.filter_ajax({
										action: 'add_clipart',
										nonce: 'MAGIC_ADMIN_cliparts:'+nonce,
										data: JSON.stringify(data)
									}),
									statusCode: {
										403: function(){
											location.reload();
										}
									},
									success: function(data){
										if(typeof data == "object")
										{
											if(data.success)
											{
												done++;
												doing();
											}
											else if(data.error)
											{
												$('#magic-cliparts-bundle-stt').html(data.error);
											}
											else 
											{
												$('#magic-cliparts-bundle-stt').html('Can\t upload at this time');
											}
										}
										else 
										{
											$('#magic-cliparts-bundle-stt').html('Can\t upload at this time');
										}
									}
								});
							}
						});

				    	delete reader[this.f];

					}, false);

					reader[total].readAsDataURL(files[i]);

				}
			}
		},

		reload_categories = function(data){

			$('#magic-list-categories').html('<br><center><i class="fa fa-spinner fa-2x fa-spin fa-fw"></i></center><br>');

			$.ajax({
				url: MagicDesign.ajax,
				method: 'POST',
				data: MagicDesign.filter_ajax({
					action: 'categories',
					nonce: 'MAGIC_ADMIN_cliparts:'+nonce,
					data: data !== undefined ? data : ''
				}),
				statusCode: {
					403: function(){
						location.reload();
					}
				},
				success: function(res){

					var checkbox = [],
						select = ['<option value="0">None</option>'];

					if (res.length > 0) {
						res.map(function(cate){
							checkbox.push('<li style="padding-left:'+(20*cate.lv)+'px">\
									<div class="magic_checkbox sty2">\
									<input type="checkbox" name="category[]" class="action_check" value="'+cate['id']+'" class="action" id="'+cate['id']+'" />\
								<label for="'+cate['id']+'">'+cate['name']+'<em class="check"></em></label>\
								</div></li>');
							select.push('<option value="'+cate['id']+'">'+('&mdash;'.repeat(cate.lv))+' '+cate['name']+'</option>');
						});
					} else {
						checkbox.push('<p class="no-data">' + magic.i(89) +'</p>');
					}

					$('#magic-list-categories').html(checkbox.join(''));
					$('#magic-parent-categories').html(select.join(''));

				}
			});

			$('#create-category-form').hide().find('input,select,textarea,img').val("").attr({'src': ''});

		};

	$('#magic-add-bundle-cliparts').on('click', function(e){
		show();
		e.preventDefault();
		return false;
	});

	wrp.on('click', function(e){

		if (e.target.id == 'magic-popup'){
			hide();
			e.preventDefault();
		}

	}).find('header [data-close]').on('click', function(e){
		hide();e.preventDefault();
	});

	$('#magic-upload-form').on('click', function() {
		$(this).find('input[type="file"]').get(0).click();
	});

	$('#magic-upload-form input[type="file"]').on('change', function(e){
		process_files(this.files);
	});

	$('[data-click="toggle-form"]').on('click', function(e){
		$('#create-category-form').slideToggle();
		e.preventDefault();
		return false;
	});

	$('#create-category-form button.magic-btn-primary').on('click', function(e){

		var name = $('#create-category-form input[name="category[name]"]');

		if (name.val() === '')
			return name.shake();

		if (
			$('#magic-list-categories label').filter(function(){
				return this.innerText.toLowerCase() == name.val().toLowerCase();
			}).length > 0
		)
			return name.shake();

		var parent = $('#create-category-form select[name="category[parent]"]').val();
		var upload = $('#create-category-form input[name="category[upload]"]').val();

		reload_categories({
			name: name.val(),
			parent: parent,
			upload: upload,
			type: 'cliparts'
		});

		e.preventDefault();
		return false;
	});

	reload_categories();

});
