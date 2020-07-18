function highlightrow(el) {
	$(el).addClass("highlight");
}
function unhighlightrow(el) {
	$(el).removeClass("highlight");
}
$(document).ready(function() {
	//hide columns that don't apply
	var hideidx = [];
	$.each($("#myTable thead th"), function( index, value ) {
		if($(value).hasClass("notattempted")){
			hideidx.push(index);
		}
	});

	$("#myTable tbody tr").each(function( index, value ) { 
        $(value).find("td").each(function( index, value ) { 
			if(hideidx.includes(index)){
				$(value).addClass("notattempted");
			}
		});
	});
	
	$(".notattempted").hide();
});