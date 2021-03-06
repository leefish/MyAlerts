jQuery.noConflict();

jQuery(document).ready(function($)
{
	$('body').on({
		click: function(event)
		{
			event.preventDefault();
			var popup_id = $(this).attr('id') + '_popup';

			$('#' + popup_id).attr('top', $(this).height() + 'px').slideToggle('slow');
			return false;
		}
	}, '.myalerts_popup_hook');

	$('#getUnreadAlerts').on('click', function(event) {
		event.preventDefault();
		$.get('xmlhttp.php?action=getNewAlerts', function(data) {
  			$('#latestAlertsListing').prepend(data);
		});
	});

	if (typeof myalerts_autorefresh !== 'undefined' && myalerts_autorefresh > 0)
	{

	}

	if (typeof unreadAlerts !== 'undefined' && unreadAlerts > 0)
	{
    	document.title = document.title + ' (' + unreadAlerts + ')';
	}

});
