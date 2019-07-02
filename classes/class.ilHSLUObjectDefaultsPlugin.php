<?php
include_once 'class.ilHSLUObjectDefaultsConfigGUI.php';
/**
 * Class ilHSLUObjectDefaultsPlugin
 * 
 * Important notice: To make the user reset for courses work, you need to add local access rights
 * for the global user on an Object in the path of the corresponding object! It at least needs read
 * access to the course object
 *
 * @author  Stephan Winiker <stephan.winiker@hslu.ch>
 * @author  Raphael Heer <raphael.heer@hslu.ch>
 * @version $id
 */
class ilHSLUObjectDefaultsPlugin extends ilEventHookPlugin {
	/**
	 * @return string
	 */
	public function getPluginName() {
		return self::PLUGIN_NAME;
	}

	/**
	 * @var
	 */
	protected static $instance;


	/**
	 * @return ilHSLUObjectDefaultsPlugin
	 */
	public static function getInstance() {
		if (!isset(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}


	const PLUGIN_NAME = 'HSLUObjectDefaults';


	/**
	 * Handle the event
	 *
	 * @param    string        component, e.g. "Services/User"
	 * @param    event         event, e.g. "afterUpdate"
	 * @param    array         array of event specific parameters
	 */
	public function handleEvent($a_component, $a_event, $a_parameter) {
		global $DIC;

	    // Show informations for users after login
		if ($a_component == 'Services/Authentication' && 
			$a_event == 'afterLogin' && 
			ilHSLUObjectDefaultsConfigGUI::getValue('active') &&
			ilContext::getType() == ilContext::CONTEXT_WEB)
	    {
	        ilUtil::sendInfo(ilHSLUObjectDefaultsConfigGUI::getValue('message'), true);
	    }
	    else if($a_component == 'Services/Object' && $a_event == 'update')
	    {
	    	/*
	    	 * Changes object title of file objects on upload in a postbox
	    	 * Only needed for folders with didactic template "postbox"
	    	 */
	    	$this->changeTitlePostbox($a_parameter, $DIC->user());
	    }
	    else if ($a_component == 'Modules/Course' && ($a_event == 'create'))
	    {
			/*
			 * We set courses to online and active for an unlimited period of time by default
			 */
			$crs = $a_parameter['object'];
		
			//activation unlimited, not offline
			$crs->setOfflineStatus(false);

			global $affected_crs;
			$affected_crs = $a_parameter['obj_id'];
		}
		else if ($a_component == 'Modules/Course' && $a_event == 'update') {
			// Adds access rights for standard participants to courses
			// Only needed for Soziale Arbeit
			// update course part of code
			$this->openCourseAccess($a_parameter, $DIC->repositoryTree(), $DIC->rbac()->review(), $DIC->rbac()->admin(), $DIC->database(), 84/*4781*/);	
		}
		else if ($a_component == 'Services/MediaObjects' && $a_event == 'update' && isset($a_parameter) && count($a_parameter)>0 && isset($a_parameter['object'])){
			// FFMPEG Conversion of media files
			$this->addToConversionQueue($a_parameter, $DIC->user());
		}
	}
	
	private function changeTitlePostbox($a_parameter, $user) {
		$container_ref_id = $_GET['ref_id'];
		$container_type = ilObject::_lookupType($container_ref_id, true);
		
		// Check if it is a fileupload in a folder
		if($a_parameter[obj_type] == 'file' && $container_type == 'fold')
		{
			global $ilUser;

			$postbox_tpl_id = ilDidacticTemplateObjSettings::lookupTemplateIdByName('Briefkasten');
			$tpl_id = ilDidacticTemplateObjSettings::lookupTemplateId($container_ref_id);
			
			// Check if folder uses the postbox-template and if the postbox-template exists
			if($tpl_id == $postbox_tpl_id && $postbox_tpl_id != 0)
			{
				// Change title of file object
				$obj_file = new ilObjFile($a_parameter['obj_id'], false);
				$filename = $obj_file->getTitle();
				
				if (strpos( $filename, ($prepos = utf8_encode(substr(utf8_decode($user->lastname),0,6).'.'.substr(utf8_decode($user->firstname),0,1)).'.'.date('ymd').'.')) !== 0 )
				{
					$obj_file->setTitle($prepos.$filename);
					$obj_file->update();
				}
			}
		}
	}
	
	private function openCourseAccess($a_parameter, $tree, $rbacreview, $rbacadmin, $db, $start_node_id) {
		global $affected_crs;
		
		$crs = $a_parameter['object'];
		$ref_id=$crs->getRefId();
		
		//check if new Course is in "Soziale Arbeit -> Bachelor"
		if ($affected_crs == $a_parameter['obj_id'] && in_array($start_node_id, $obj_path=$tree->getPathId($ref_id))) {
			//Get the current access rights on the course
			$user_role = $this->getGlobalUserRoleId($rbacreview);
			$local_ops = $rbacreview->getRoleOperationsOnObject($user_role, $ref_id);
			$local_ops = array_map('strval', $local_ops);
			
			//Get the access rights of the standard course user and remove rights that the standard user doesn't have
			$non_member_template_id = $this->getCrsNonMemberTemplateId($db);
			$template_ops = $rbacreview->getOperationsOfRole($non_member_template_id, 'crs', ROLE_FOLDER_ID);
			
			$role_folders=array_intersect($obj_path, $rbacreview->getFoldersAssignedToRole($user_role));
			end($role_folders);
			$parent_role_folder = prev($role_folders);
			if ($parent_role_folder == false) {
				return;
			}
			
			$parent_ops = $this->getTemplatePolicies($db, $user_role, $parent_role_folder, 'crs');
			$template_ops = $this->intersectPolicies($template_ops, $parent_ops);
			
			//Get the local and the next higher policies
			$local_policies = $this->getTemplatePolicies($db, $user_role, $ref_id);
			
			$parent_policies = $this->getTemplatePolicies($db, $user_role, $parent_role_folder);
			$non_member_policies = $this->getTemplatePolicies($db, $non_member_template_id, '');
			$non_member_policies = array_values($this->intersectPolicies($non_member_policies, $parent_policies));
			
			if ($local_ops == $template_ops && $local_policies == $non_member_policies) {
				$rbacadmin->copyRoleTemplatePermissions($user_role, $parent_role_folder, $ref_id, $user_role);
				$local_policies = $this->getTemplatePolicies($db, $user_role, $ref_id, 'crs');
				$rbacadmin->grantPermission($user_role,$local_policies,$ref_id);
			}
		}
		
		unset($affected_crs);
	}
	
	private function addToConversionQueue($a_parameter, $user) {
		$allMediaItems=$a_parameter['object']->getMediaItems();
		$ffmpegQueue='data/ffmpegQueue.txt';
		
		$pl = $this->pl = new ilHSLUObjectDefaultsPlugin();
		
		foreach($allMediaItems as $media_item){
			$filename=$media_item->location;

			$settings = ilMediaCastSettings::_getInstance();
			$purposeSuffixes = $settings->getPurposeSuffixes();
			
			if(in_array(mb_strtolower(substr($filename, strrpos($filename,'.')+1)), $purposeSuffixes['VideoPortable'])){
				$folder=ilObjMediaObject::_getDirectory($a_parameter['object']->getId());
				
				$numofLines=ilHSLUObjectDefaultsPlugin::lineCount($ffmpegQueue);
				ilUtil::sendInfo(sprintf($pl->txt('file_uploaded'), $numofLines), true);
				
				file_put_contents($ffmpegQueue, $folder.'/'.$filename.'|'.$folder.'/'.substr($filename,0, strrpos($filename,'.')).'.mp4|'.$user->getEmail()."\n", FILE_APPEND);
				
				//geht nicht wegen client cache
				//@copy('Customizing/mob_vpreview.png', $folder.'/mob_vpreview.png');
				
				@chmod($folder,0775);
				
				//set new media format
				$media_item->setFormat('video/mp4');
				$media_item->setLocation(substr($filename,0, strrpos($filename,'.')).'.mp4');
				$media_item->update();
				
			} else if (in_array(mb_strtolower(substr($filename, strrpos($filename, '.')+1)), $purposeSuffixes['AudioPortable']) && substr($filename,-3)!='mp3'){
				$folder=ilObjMediaObject::_getDirectory($a_parameter['object']->getId());
				
				$numofLines=ilHSLUObjectDefaultsPlugin::lineCount($ffmpegQueue);
				ilUtil::sendInfo(sprintf($pl->txt('file_uploaded'), $numofLines), true);
				
				file_put_contents($ffmpegQueue, $folder.'/'.$filename.'|'.$folder.'/'.substr($filename,0, strrpos($filename,'.')).'.mp3|'.$user->getEmail()."\n", FILE_APPEND);
				
				@chmod($folder,0775);
				
				//set new media format
				$media_item->setFormat('audio/mpeg');
				$media_item->setLocation(substr($filename,0, strrpos($filename,'.')).'.mp3');
				$media_item->update();
			}
		}
	}

	private function getGlobalUserRoleId($rbacreview) {
		
		$globalRoleIds = $rbacreview->getGlobalRoles();
		$userRoleId = null;
		foreach ($globalRoleIds as $roleId) {
			$roleObj = ilObjectFactory::getInstanceByObjId($roleId);
			if ($roleObj->getTitle()=='User' || $roleObj->getTitle()=='BenutzerIn') {
				$userRoleId= $roleId;
			}
		}
		return $userRoleId;
	}
	
	/**
	 * get course non-member template
	 * @access	private
	 * @param	return obj_id of roletemplate containing permissionsettings for
	 *           non-member roles of a course.
	 */
	private function getCrsNonMemberTemplateId($db) {
	
		$q = "SELECT obj_id FROM object_data WHERE type='rolt' AND title='il_crs_non_member'";
		$res = $db->query($q);
		$row = $res->fetchRow(ilDBConstants::FETCHMODE_ASSOC);
	
		return $row["obj_id"];
	}
	
	private function getTemplatePolicies ($db, $template_id, $folder_ref = '', $type = '') {
		if ($folder_ref == '') {
			$query = "SELECT o_r.ref_id FROM object_reference o_r ". 
					"JOIN object_data o_d ON o_r.obj_id = o_d.obj_id ".
					"WHERE o_d.type = 'rolf' ".
					"AND (o_d.title = 'Rollen' OR o_d.title = 'Roles')";
			$res = $db->query($query);
			$folder_ref = $db->fetchObject($res)->ref_id;
		}
		
		if ($type != '') {
			$type = ' AND type='.$db->quote($type, 'text');
		}
		
		$query = 'SELECT * FROM rbac_templates '.
				'WHERE rol_id = '.$db->quote($template_id,'integer').' '.
				'AND parent = '.$db->quote($folder_ref,'integer').$type;
		$res = $db->query($query);
		$operations = array();

		while ($row = $db->fetchObject($res))
		{
			array_push($operations, $row->ops_id);
		}
		
		return $operations;
	}
	
	private function intersectPolicies ($current_ops, $needle_ops) {
		foreach ($current_ops as $key=>$op) {
			if (!in_array($op, $needle_ops)) {
				unset($current_ops[$key]);
			}
		}
		
		return $current_ops;
	}
	
	private static function lineCount($file) {
		$linecount = 0;
		$handle = fopen($file, "r");
		while(!feof($handle)){
			if (fgets($handle) !== false) {
					$linecount++;
			}
		}
		fclose($handle);
		return  $linecount;     
	}

}

?>
