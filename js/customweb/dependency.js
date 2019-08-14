function updateDependencySelect(sel, elementId, greyOutOnly, loopError, checkError){
	if(sel.options[sel.selectedIndex].className == "dependency-loop"){
		alert(loopError);
		sel.selectedIndex = 0;
	}
	else if(sel.options[sel.selectedIndex].className == "dependency-check-failed")
	{
		alert(checkError);
		sel.selectedIndex = 0;
	}
	greyOut(sel, elementId, greyOutOnly);
}

function greyOut(sel, elementId, greyOutOnly){
	var value = sel.options[sel.selectedIndex].value;
	var hiddenFields = $$('input[type="hidden"]');
	var toExclude = hiddenFields.concat(sel);
	if(value != 0 && value != 'default')
	{
		originalToggleValueElements($(elementId + "_default"),sel.parentNode.parentNode.parentNode.parentNode,toExclude,true);
		originalToggleValueElements($(elementId + "_inherit"),sel.parentNode.parentNode.parentNode.parentNode,toExclude,true);
		
	}
	else if(!greyOutOnly)
	{
		originalToggleValueElements($(elementId + "_default"),sel.parentNode.parentNode.parentNode.parentNode,toExclude,false);
		originalToggleValueElements($(elementId + "_inherit"),sel.parentNode.parentNode.parentNode.parentNode,toExclude,false);
	}
}

function greyOutAttributes(){
	if(typeof originalToggleValueElements == 'undefined')
	{
		originalToggleValueElements = toggleValueElements;
		toggleValueElements = toggleValueElements.wrap(function(callOriginal, checkbox, container, excludedElements, checked){
			
			// Exclude all hidden fields
			var hiddenFields = $$('input[type="hidden"]');
			if(typeof excludedElements != 'undefined')
			{
				hiddenFields = hiddenFields.concat(excludedElements);
			}
			var name = checkbox.readAttribute("name");
			var m = name.match(/use_default/i);
			if(m == null){
				hiddenFields = hiddenFields.concat($$('select[class="dependency-selector"]'));
			}
			
			// WORKAROUND
			// Magento standard behavior: Some fields (up to now only checkboxes) 
			// do not get disabled when checking 'use_default'. This behavior is unfortunately
			// necessary for certain required fields. The following list therefore excludes those
			// elements from beeing disabled.
			var doNotDisable = ["use_config_group_5available_sort_by",
			                    "use_config_group_5default_sort_by"];	
			
			for(var i=0; i < doNotDisable.length; i++){
				var elem = $(doNotDisable[i]);
				if(elem != null){
					hiddenFields = hiddenFields.concat([elem])
				}
			}			
			
			callOriginal(checkbox, container, hiddenFields, checked);
				
			updatedBasedOnSelects('select[class="dependency-selector"]');
			updatedBasedOnSelects('select[class="config-dependency-selector"]');
			
			
		});
	}
	
	$$('input[type="checkbox"]').each(function(element) {
		var name = element.readAttribute('name');
		if(name != null && (name.match(/use_default\[\]/i) || name.match(/\[inherit\]/i)))
		{
			toggleValueElements(element, element.parentNode.parentNode,$$('input[type="checkbox"][name="use_default[]"]'));
		}
     });
     
     // This is a hack for the Chromium browser
     var tab = $('category_info_tabs_group_4');
     if(tab != null){
		 setTimeout(function(){eventFire(tab,'click');},200);
		 
	 }
}

Event.observe(window, 'load', greyOutAttributes);


function updatedBasedOnSelects(itemSelector){
	$$(itemSelector).each(function(item){
		var name = item.readAttribute("name");
		var dependencyFor = item.readAttribute("dependencyfor");
		if(name != null)
		{
			var m = name.match(/\[(.*)source\]/i);
			if(m != null)
			{					
				greyOut(item,dependencyFor,true);
			}		
		}
	});
}

// Taken from stackoverflow
function eventFire(el, etype){
  if (el.fireEvent) {
    (el.fireEvent('on' + etype));
  } else {
    var evObj = document.createEvent('Events');
    evObj.initEvent(etype, true, false);
    el.dispatchEvent(evObj);
  }
}
