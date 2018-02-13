<?php
/*+********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is: vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *********************************************************************************/

if (defined('VTIGER_UPGRADE')) {
	global $current_user;
	$db = PearDatabase::getInstance();

	//START::Workflow task's template path
	$pathsList = array();
	$result = $db->pquery('SELECT classname FROM com_vtiger_workflow_tasktypes', array());
	while($rowData = $db->fetch_row($result)) {
		$className = $rowData['classname'];
		if ($className) {
			$pathsList[$className] = vtemplate_path("Tasks/$className.tpl", 'Settings:Workflows');
		}
	}

	if ($pathsList) {
		$updateQuery = 'UPDATE com_vtiger_workflow_tasktypes SET templatepath = CASE';
		foreach ($pathsList as $className => $templatePath) {
			$updateQuery .= " WHEN classname='$className' THEN '$templatePath'";
		}
		$updateQuery .= ' ELSE templatepath END';
		$db->pquery($updateQuery, array());
	}
	//END::Workflow task's template path

	//START::Duplication Prevention
	$vtigerFieldColumns = $db->getColumnNames('vtiger_field');
	if (!in_array('isunique', $vtigerFieldColumns)) {
		$db->pquery('ALTER TABLE vtiger_field ADD COLUMN isunique BOOLEAN DEFAULT 0');
	}

	$vtigerTabColumns = $db->getColumnNames('vtiger_tab');
	if (!in_array('issyncable', $vtigerTabColumns)) {
		$db->pquery('ALTER TABLE vtiger_tab ADD COLUMN issyncable BOOLEAN DEFAULT 0');
	}
	if (!in_array('allowduplicates', $vtigerTabColumns)) {
		$db->pquery('ALTER TABLE vtiger_tab ADD COLUMN allowduplicates BOOLEAN DEFAULT 1');
	}
	if (!in_array('sync_action_for_duplicates', $vtigerTabColumns)) {
		$db->pquery('ALTER TABLE vtiger_tab ADD COLUMN sync_action_for_duplicates INT(1) DEFAULT 1');
	}

	//START - Enable prevention for Accounts module
	$accounts = 'Accounts';
	$db->pquery('UPDATE vtiger_field SET isunique=? WHERE fieldname=? AND tabid=(SELECT tabid FROM vtiger_tab WHERE name=?)', array(1, 'accountname', $accounts));
	$db->pquery('UPDATE vtiger_tab SET allowduplicates=? WHERE name=?', array(0, $accounts));
	//End - Enable prevention for Accounts module

	$db->pquery('UPDATE vtiger_tab SET issyncable=1', array());
	$em = new VTEventsManager($db);
	$em->registerHandler('vtiger.entity.beforesave', 'modules/Vtiger/handlers/CheckDuplicateHandler.php', 'CheckDuplicateHandler');

	$em = new VTEventsManager($db);
	$em->registerHandler('vtiger.entity.beforerestore', 'modules/Vtiger/handlers/CheckDuplicateHandler.php', 'CheckDuplicateHandler');
	echo '<br>Succecssfully handled duplications<br>';
	//END::Duplication Prevention

	//START::Webform Attachements
	if (!Vtiger_Utils::CheckTable('vtiger_webform_file_fields')) {
		$db->pquery('CREATE TABLE IF NOT EXISTS vtiger_webform_file_fields(id INT(19) NOT NULL AUTO_INCREMENT, webformid INT(19) NOT NULL, fieldname VARCHAR(100) NOT NULL, fieldlabel VARCHAR(100) NOT NULL, required INT(1) NOT NULL DEFAULT 0, PRIMARY KEY (id), KEY fk_vtiger_webforms (webformid), CONSTRAINT fk_vtiger_webforms FOREIGN KEY (webformid) REFERENCES vtiger_webforms (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=UTF8;', array());
	}

	$result = $db->pquery('SELECT 1 FROM vtiger_ws_operation WHERE name=?', array('add_related'));
	if (!$db->num_rows($result)) {
		$operationId = vtws_addWebserviceOperation('add_related', 'include/Webservices/AddRelated.php', 'vtws_add_related', 'POST');
		vtws_addWebserviceOperationParam($operationId, 'sourceRecordId', 'string', 1);
		vtws_addWebserviceOperationParam($operationId, 'relatedRecordId', 'string', 2);
		vtws_addWebserviceOperationParam($operationId, 'relationIdLabel', 'string', 3);
	}
	echo '<br>Succecssfully added Webforms attachements<br>';
	//END::Webform Attachements

	//START::Tag fields are pointed to cf table for the modules Assets, Services etc..
	$fieldName = 'tags';
	$moduleModels = Vtiger_Module_Model::getAll();
	foreach ($moduleModels as $moduleModel) {
		$baseTableId = $moduleModel->basetableid;
		if ($baseTableId) {
			$baseTableName = $moduleModel->basetable;
			$customTableName = $baseTableName.'cf';
			$customTableColumns = $db->getColumnNames($customTableName);
			if (in_array($fieldName, $customTableColumns)) {
				$fieldModel = Vtiger_Field_Model::getInstance($fieldName, $moduleModel);
				$db->pquery("UPDATE vtiger_field SET tablename=? WHERE fieldid=?", array($baseTableName, $fieldModel->id));
				$db->pquery("ALTER TABLE $baseTableName ADD COLUMN $fieldName VARCHAR(1)", array());

				$db->pquery("UPDATE $baseTableName, $customTableName SET $baseTableName.tags=$customTableName.tags WHERE $baseTableName.$baseTableId=$customTableName.$baseTableId", array());
				$db->pquery("ALTER TABLE $customTableName DROP COLUMN $fieldName", array());
			}
		}
	}
	echo '<br>Succecssfully generalized tag fields<br>';
	//END::Tag fields are pointed to cf table for the modules Assets, Services etc..

	//START::Follow & unfollow features
	$em = new VTEventsManager($db);
	$em->registerHandler('vtiger.entity.aftersave', 'modules/Vtiger/handlers/FollowRecordHandler.php', 'FollowRecordHandler');
	//END::Follow & unfollow features

	//START::Reordering Timezones
	$fieldName = 'time_zone';
	$userModuleModel = Vtiger_Module_Model::getInstance('Users');
	$fieldModel = Vtiger_Field_Model::getInstance($fieldName, $userModuleModel);
	if ($fieldModel) {
		$picklistValues = $fieldModel->getPicklistValues();

		$utcTimezones = preg_grep('/\(UTC\)/', $picklistValues);
		asort($utcTimezones);

		$utcPlusTimezones = preg_grep('/\(UTC\+/', $picklistValues);
		asort($utcPlusTimezones);

		$utcMinusTimezones = preg_grep('/\(UTC\-/', $picklistValues);
		arsort($utcMinusTimezones);

		$timeZones = array_merge($utcMinusTimezones, $utcTimezones, $utcPlusTimezones);
		$originalPicklistValues = array_flip(Vtiger_Util_Helper::getPickListValues($fieldName));

		$orderedPicklists = array();
		$i = 0;
		foreach ($timeZones as $timeZone => $value) {
			$orderedPicklists[$originalPicklistValues[$timeZone]] = $i++;
		}
		ksort($orderedPicklists);

		$moduleModel = new Settings_Picklist_Module_Model();
		$moduleModel->updateSequence($fieldName, $orderedPicklists);
		echo '<br>Succecssfully reordered timezones<br>';
	}
	//END::Reordering Timezones

	//START::Differentiate custom modules from Vtiger modules
	$vtigerTabColumns = $db->getColumnNames('vtiger_tab');
	if (!in_array('source', $vtigerTabColumns)) {
		$db->pquery('ALTER TABLE vtiger_tab ADD COLUMN source VARCHAR(255) DEFAULT "custom"', array());
	}
	$db->pquery('UPDATE vtiger_tab SET source=NULL', array());

	$pkgModules = array();
	$pkgFolder = 'pkg/vtiger/modules';
	$pkgHandle = opendir($pkgFolder);

	if ($pkgHandle) {
		while (($pkgModuleName = readdir($pkgHandle)) !== false) {
			$pkgModules[$pkgModuleName] = $pkgModuleName;

			$moduleHandle = opendir("$pkgFolder/$pkgModuleName");
			while (($innerModuleName = readdir($moduleHandle)) !== false) {
				if (is_dir("$pkgFolder/$pkgModuleName/$innerModuleName")) {
					$pkgModules[$innerModuleName] = $innerModuleName;
				}
			}
			closedir($moduleHandle);
		}
		closedir($pkgHandle);
		$pkgModules = array_keys($pkgModules);
	}

	$db->pquery('UPDATE vtiger_tab SET source="custom" WHERE version IS NOT NULL AND name NOT IN ('.generateQuestionMarks($pkgModules).')', $pkgModules);
	echo '<br>Succecssfully added source column vtiger tab table<br>';
	//END::Differentiate custom modules from Vtiger modules

	//START::Google calendar sync settings
	if (!Vtiger_Utils::CheckTable('vtiger_google_event_calendar_mapping')) {
		$db->pquery('CREATE TABLE vtiger_google_event_calendar_mapping (event_id VARCHAR(255) DEFAULT NULL, calendar_id VARCHAR(255) DEFAULT NULL, user_id INT(11) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8', array());
		echo '<br>Succecssfully vtiger_google_event_calendar_mapping table created<br>';
	}
	//END::Google calendar sync settings

	//START::Centralize user field table for easy query with context of user across module
	$generalUserFieldTable = 'vtiger_crmentity_user_field';
	if (!Vtiger_Utils::CheckTable($generalUserFieldTable)) {
		Vtiger_Utils::CreateTable($generalUserFieldTable,
				'(`recordid` INT(19) NOT NULL, 
				`userid` INT(19) NOT NULL,
				`starred` VARCHAR(100) DEFAULT NULL', true);
	}

	if (Vtiger_Utils::CheckTable($generalUserFieldTable)) {
		$indexRes = $db->pquery("SHOW INDEX FROM $generalUserFieldTable WHERE NON_UNIQUE=? AND KEY_NAME=?", array('1', 'record_user_idx'));
		if ($db->num_rows($indexRes) < 2) {
			$db->pquery('ALTER TABLE vtiger_crmentity_user_field ADD CONSTRAINT record_user_idx UNIQUE KEY(recordid, userid)', array());
		}

		$checkUserFieldConstraintExists = $db->pquery('SELECT DISTINCT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE table_name=? AND CONSTRAINT_SCHEMA=?', array($generalUserFieldTable, $db->dbName));
		if ($db->num_rows($checkUserFieldConstraintExists) < 1) {
			$db->pquery('ALTER TABLE vtiger_crmentity_user_field ADD CONSTRAINT `fk_vtiger_crmentity_user_field_recordid` FOREIGN KEY (`recordid`) REFERENCES `vtiger_crmentity`(`crmid`) ON DELETE CASCADE', array());
		}
		
	}

	$migratedTables = array();
	$result = $db->pquery('SELECT vtiger_tab.tabid, vtiger_tab.name, tablename, fieldid FROM vtiger_field INNER JOIN vtiger_tab ON vtiger_tab.tabid=vtiger_field.tabid WHERE fieldname=?', array('starred'));
	while ($row = $db->fetch_array($result)) {
		$fieldId = $row['fieldid'];
		$moduleName = $row['name'];
		$oldTableName = $row['tablename'];

		$db->pquery('UPDATE vtiger_field SET tablename=? WHERE fieldid=? AND tablename=?', array($generalUserFieldTable, $fieldId, $oldTableName));
		echo "Updated starred field for module $moduleName to point generic table => $generalUserFieldTable<br>";

		if (Vtiger_Utils::CheckTable($oldTableName)) {
			if (!in_array($oldTableName, $migratedTables)) {
				if ($oldTableName != $generalUserFieldTable) {
					//Insert entries from module specific table to generic table for follow up records
					$db->pquery("INSERT INTO $generalUserFieldTable (recordid, userid, starred) (SELECT recordid,userid,starred FROM $oldTableName INNER JOIN vtiger_crmentity ON $oldTableName.recordid = vtiger_crmentity.crmid)", array());
					echo "entries moved from $oldTableName to $generalUserFieldTable table<br>";

					//Drop module specific user table
					$db->pquery("DROP TABLE $oldTableName", array());
					echo "module specific user field table $oldTableName has been dropped<br>";
					array_push($migratedTables, $oldTableName);
				}
			}
		}
	}
	echo '<br>Succesfully centralize user field table for easy query with context of user across module<br>';
	//END::Centralize user field table for easy query with context of user across module

	//START::Adding new parent TOOLS in menu
	$appsList = array('TOOLS' => array('Rss', 'Portal', 'Contacts', 'Accounts'));
	foreach ($appsList as $app => $appModules) {
		foreach ($appModules as $moduleName) {
			$moduleModel = Vtiger_Module_Model::getInstance($moduleName);
			if ($moduleModel) {
				Settings_MenuEditor_Module_Model::addModuleToApp($moduleName, $app);
			}
		}
	}
	echo '<br>Succesfully added RSS, Email Templates for new parent TOOLS<br>';
	//END::Adding new parent TOOLS in menu

	//Update existing package modules
	Install_Utils_Model::installModules();

	echo '<br>Succecssfully vtiger version updated to <b>7.1.0</b><br>';
}
