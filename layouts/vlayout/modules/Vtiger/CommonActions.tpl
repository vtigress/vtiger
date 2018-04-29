{*<!--
/*********************************************************************************
** The contents of this file are subject to the vtiger CRM Public License Version 1.0
* ("License"); You may not use this file except in compliance with the License
* The Original Code is:  vtiger CRM Open Source
* The Initial Developer of the Original Code is vtiger.
* Portions created by vtiger are Copyright (C) vtiger.
* All Rights Reserved.
*
********************************************************************************/
-->*}

{strip}
    {assign var="announcement" value=$ANNOUNCEMENT->get('announcement')}
    {assign var='count' value=0}
    {assign var="dateFormat" value=$USER_MODEL->get('date_format')}
    <div class="navbar commonActionsContainer noprint">
        <div class="actionsContainer row-fluid">
            <div class="span2">
                <span class="companyLogo"><img src="{$COMPANY_LOGO->get('imagepath')}" title="{$COMPANY_LOGO->get('title')}" alt="{$COMPANY_LOGO->get('alt')}"/>&nbsp;</span>
            </div>
            <div class="span10">
                <div class="row-fluid">
                    <div class="searchElement span8">
                        <div class="select-search">
                            <select class="chzn-select" id="basicSearchModulesList" style="width:150px;">
                                <option value="" class="globalSearch_module_All">{vtranslate('LBL_ALL_RECORDS', $MODULE_NAME)}</option>
                                {foreach key=MODULE_NAME item=fieldObject from=$SEARCHABLE_MODULES}
                                    {if isset($SEARCHED_MODULE) && $SEARCHED_MODULE eq $MODULE_NAME && $SEARCHED_MODULE !== 'All'}
                                        <option value="{$MODULE_NAME}" class="globalSearch_module_{$MODULE_NAME}" selected>{vtranslate($MODULE_NAME,$MODULE_NAME)}</option>
                                    {else}
                                        <option value="{$MODULE_NAME}" class="globalSearch_module_{$MODULE_NAME}">{vtranslate($MODULE_NAME,$MODULE_NAME)}</option>
                                    {/if}
                                {/foreach}
                            </select>
                        </div>
                        <div class="input-append searchBar">
                            <input type="text" class="" id="globalSearchValue" placeholder="{vtranslate('LBL_GLOBAL_SEARCH')}" results="10" />
                            <span id="searchIcon" class="add-on search-icon"><i class="icon-white icon-search "></i></span>
                            <span class="adv-search  pull-left">
                                <a class="alignMiddle" id="globalSearch">{vtranslate('LBL_ADVANCE_SEARCH')}</a>
                            </span>
                        </div>

                    </div>
                    <div class="notificationMessageHolder span2">
                 	<form name="timerform"><font color='red'><strong>Auto logout in <span id="timer"></span></strong></font>
                 	<input type="hidden" id="ck_open_close" name="ck_open_close" value="close" />
                 	<input type="hidden" id="autologout_time_1" name="autologout_time" value="{$USER_MODEL->get('autologout_time')}" /></form>
                     </div>
                    <div class="nav quickActions btn-toolbar span2 pull-right marginLeftZero">
                        <div class="pull-right commonActionsButtonContainer">
                            {if !empty($announcement)}
                                <div class="btn-group cursorPointer">
                                    <img class='alignMiddle' src="{vimage_path('btnAnnounceOff.png')}" alt="{vtranslate('LBL_ANNOUNCEMENT',$MODULE)}" title="{vtranslate('LBL_ANNOUNCEMENT',$MODULE)}" id="announcementBtn" />
                                </div>&nbsp;
                            {/if}

                            <div class="btn-group cursorPointer" id="guiderHandler">
                                {if !$MAIN_PRODUCT_WHITELABEL}
                                {/if}
                            </div>&nbsp;

                            <div class="btn-group cursorPointer">
                                <img id="menubar_quickCreate" src="{vimage_path('btnAdd.png')}" class="alignMiddle" alt="{vtranslate('LBL_QUICK_CREATE',$MODULE)}" title="{vtranslate('LBL_QUICK_CREATE',$MODULE)}" data-toggle="dropdown" />
                                <ul class="dropdown-menu dropdownStyles commonActionsButtonDropDown">
                                    <li class="title"><strong>{vtranslate('Quick Create',$MODULE)}</strong></li><hr/>
                                    <li id="quickCreateModules">
                                        <div class="row-fluid">
                                            <div class="span12">
                                                {foreach key=moduleName item=moduleModel from=$MENUS}
                                                    {if $moduleModel->isPermitted('EditView')}
                                                        {assign var='quickCreateModule' value=$moduleModel->isQuickCreateSupported()}
                                                        {assign var='singularLabel' value=$moduleModel->getSingularLabelKey()}
														{if $singularLabel == 'SINGLE_Calendar'}
															{assign var='singularLabel' value='LBL_EVENT_OR_TASK'}
														{/if}	
                                                        {if $quickCreateModule == '1'}
                                                            {if $count % 3 == 0}
                                                                <div class="row-fluid">
                                                                {/if}
                                                                <div class="span4">
                                                                    <a id="menubar_quickCreate_{$moduleModel->getName()}" class="quickCreateModule" data-name="{$moduleModel->getName()}"
                                                                       data-url="{$moduleModel->getQuickCreateUrl()}" href="javascript:void(0)">{vtranslate($singularLabel,$moduleName)}</a>
                                                                </div>
                                                                {if $count % 3 == 2}
                                                                </div>
                                                            {/if}
                                                            {assign var='count' value=$count+1}
                                                        {/if}
                                                    {/if}
                                                {/foreach}
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>&nbsp;
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<!--Auto logout universal, Dev: Anjaneya, Date: 25th Oct'2107 -->
<script src="libraries/timeout/jquery.storageapi.min.js" type="text/javascript"></script>
<link href="libraries/timeout/jquery-idleTimeout-plus.css" rel="stylesheet" type="text/css" />
<script src="libraries/timeout/jquery-idleTimeout-plus.js" type="text/javascript"></script>
<!--End-->

<script type='text/javascript'>
{literal}
//Added By sri For auto logout in 15 mins end

    var mu_val = jQuery('#autologout_time_1').val();

if(mu_val == '15 mins') { 
	mu_val = 900;
} else if(mu_val == '30 mins') {
	mu_val = 1800;
} else if (mu_val == '45 mins') {
	mu_val = 2700;
} else if (mu_val == '60 mins') {
	mu_val = 3600;
}
   // mu_val = 36;
var vtrcount=mu_val;

var vtrcounter=setInterval(timer, 1000);
function timer()
{
  vtrcount=vtrcount-1;
  if (vtrcount <= 0)
  {
     clearInterval(vtrcounter);
     <!-- Auto logout universal, Dev: Anjaneya, Date: 25th Oct'2107 -->
		/* var cURL = window.location.href;
		var url = cURL.split("?");
		var redURL = url[0]+"?module=Users&action=Logout";		 
		window.location.href = redURL */
	/* END */
		
  }

 document.getElementById("timer").innerHTML=vtrcount + " secs"; // watch for spelling
}
function updateMe( data ) {
   // alert( data );
    window.clearInterval(vtrcounter);
   
 vtrcount=mu_val;

  vtrcounter=setInterval(timer, 1000);
}
document.onkeypress = function (e) {
    e = e || window.event;
     window.clearInterval(vtrcounter);
   
 vtrcount=mu_val;

  vtrcounter=setInterval(timer, 1000);
	
};
window.ununload = function (e) {
    e = e || window.event;
     window.clearInterval(vtrcounter);
   
 vtrcount=mu_val;

  vtrcounter=setInterval(timer, 1000);
	
};

document.onmousedown = function (e) {
    e = e || window.event;
    
    //console.log(e.currentTarget);
	//console.log(e.target.attr('id'));
	//console.log(jQuery(e.currentTarget).closest('[id]'));
vtrcount=mu_val;
	var targetid = e.target.id;
	if(targetid == 'Leads_editView_fieldName_cf_876_select'){
		//console.log(e.target.id);
		return false;
	}else{
		window.clearInterval(vtrcounter);
		vtrcounter=setInterval(timer, 1000);
	}
	  	
};
<!-- Auto logout universal, Dev: Anjaneya, Date: 25th Oct'2107 -->
jQuery(document).scroll(function(){ 	 
	window.clearInterval(vtrcounter);
	vtrcount=mu_val;
	vtrcounter=setInterval(timer, 1000);
	
});
/* END */
jQuery(document).ready(function () {  
	if(typeof(CKEDITOR)!=='undefined'){
		CKEDITOR.on('instanceCreated', function(e) {

		e.editor.on('contentDom', function() {
		e.editor.document.on('keydown', function(event) {
		window.clearInterval(vtrcounter);
		vtrcount=mu_val;

		vtrcounter=setInterval(timer, 1000);
		});
		e.editor.document.on('mousedown', function(event) {
		window.clearInterval(vtrcounter);
		vtrcount=mu_val;

		vtrcounter=setInterval(timer, 1000);
					});
			});
		});
	}
	<!-- Auto logout universal, Dev: Anjaneya, Date: 25th Oct'2107 -->
	IdleTimeoutPlus.start({
		idleTimeLimit: mu_val,
		bootstrap : false,
		warnTimeLimit : 60,
		warnMessage : 'Session is about to expire!',
		redirectUrl : 'index.php?module=Users&action=Logout',
		logoutUrl : 'index.php?module=Users&action=Logout',
		logoutautourl : 'index.php?module=Users&action=Logout',
		warnLogoutButton : null,
		multiWindowSupport : true
	}); 
	/* END */
});
//Added By sri For auto logout in 15 mins end 
{/literal}
</script>
{/strip}
