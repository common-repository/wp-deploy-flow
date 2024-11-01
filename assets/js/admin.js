jQuery(document).ready(function($) {
	$('#add_environment').submit(function (event) {
		event.preventDefault();
		var data = {
			action: 'deploy_add_environment'
		};
		$(event.target).find('input').each(function (index, element) {
			data[element.name] = element.value;
		});
		$.post(ajaxurl, data, function (response) {
			if(response.changed) {
				window.location.reload();
			}
			console.log('response', response);
		})
	});
	$('.remove_environment').click(function (event) {
		var environment = $(event.target).parent('li').data('environment');
		$.post(ajaxurl, {action: 'deploy_remove_environment', environment_name: environment}, function (response) {
			if(response.changed) {
				$(event.target).parent('li').remove();
			}
			console.log('removing', response);
		});
	});
	$('#environments form').submit(function (event) {
		event.preventDefault();
	});
	$('#environments [name=push], #environments [name=pull]').click(function (event) {
		var environment = $(event.target).parent('li').data('environment');
		var eventType = event.target.name;
		var files_only = $(event.target).parent('li').find('[name=files_only]').is(':checked');
		var dry_run = $(event.target).parent('li').find('[name=dry_run]').is(':checked');
		console.log('dry run', dry_run);
		var message = '';
		if(eventType === 'pull') {
			message = 'You are about to pull from the ' + environment + ' environment to this one.';
		} else {
			message = 'You are about to push from this environment to the ' + environment + ' environment.';
		}
		if(files_only) {
			message += ' This will only include the files. The database will not be transferred.';
		} else {
			message += ' This will transfer the database as well as the files.';
		}
		message += ' Are you sure you wish to proceed?';
		var ssh_passed = $('[data-constant=SSH_PASSWORD]').text();
		if(remote && !ssh_passed) {
			ssh_passed = prompt('Please enter the SSH password');
		} else {
			ssh_passed = true;
		}
		if(dry_run || (confirm(message) && ssh_passed)) {
			$('#output').html('');
			var options = {
				action: 'deploy_' + eventType,
				files_only: files_only,
				dry_run: dry_run,
				environment: environment
			};
			if(remote) options.ssh_password = ssh_passed;
			$('#loader').addClass('loading');
			$.post(
				ajaxurl,
				options,
				function (response) {
					$('#output').html(response.output);
					$.post(ajaxurl, {
						action: 'deploy_set_environments',
						environments: response.environments
					}, function (response) {
						console.log('deployment response', response);
					}).always(function(response) {console.log(response)});
					alert('Deployment complete');
				}
			).always(function(response) {
				console.log(response);
				$('#loader').removeClass('loading');
			});
		} else {
			$('#output').html('Deployment aborted');
		}
	});
});
