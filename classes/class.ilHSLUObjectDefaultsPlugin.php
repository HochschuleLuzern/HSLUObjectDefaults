<?php
require_once('./Services/EventHandling/classes/class.ilEventHookPlugin.php');

/**
 * Class ilHSLUObjectDefaultsPlugin
 * 
 * Important notice: To make the user reset for courses work, you need to add local access rights
 * for the global user on an Object in the path of the corresponding object! It at least needs read
 * access to the course object
 *
 * @author  Simon Moor <simon.moor@hslu.ch>
 * @version 1.0.0
 */
class ilHSLUObjectDefaultsPlugin extends ilEventHookPlugin {

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

		// Adds access rights for standard participants to courses
		// Only needed for Soziale Arbeit
		// create course part of code
		if ($a_component == 'Modules/Course' && ($a_event == 'create') /*&& $a_parameter['obj_type']=='crs'*/) {
		
			$crs = $a_parameter['object'];
		
			//activation unlimited, not offline
			$crs->setActivationType(IL_CRS_ACTIVATION_UNLIMITED);
			$crs->setOfflineStatus(false);

			global $affected_crs;
			$affected_crs = $a_parameter['obj_id'];
		}

		// Adds access rights for standard participants to courses
		// Only needed for Soziale Arbeit
		// update course part of code
		if ($a_component == 'Modules/Course' && $a_event == 'update') {
			global $tree, $rbacreview, $rbacadmin, $affected_crs;

			$crs = $a_parameter['object'];
			$ref_id=$crs->getRefId();

			//check if new Course is in "Soziale Arbeit -> Bachelor"
			if ($affected_crs == $a_parameter['obj_id'] && in_array(4781, $obj_path=$tree->getPathId($ref_id))) {
				//Get the current access rights on the course
				$user_role = $this->_getGlobalUserRoleId();
				$local_ops = $rbacreview->getRoleOperationsOnObject($user_role, $ref_id);
				$local_ops = array_map('strval', $local_ops);

				//Get the access rights of the standard course user and remove rights that the standard user doesn't have
				$non_member_template_id = $this->__getCrsNonMemberTemplateId();
				$template_ops = $rbacreview->getOperationsOfRole($non_member_template_id, 'crs', ROLE_FOLDER_ID);

				$role_folders=array_intersect($obj_path, $rbacreview->getFoldersAssignedToRole($user_role));
				end($role_folders);
				$parent_role_folder = prev($role_folders);
				$parent_ops = $this->__getTemplatePolicies($user_role, $parent_role_folder, 'crs');
				$template_ops = $this->__intersectPolicies($template_ops, $parent_ops);

				//Get the local and the next higher policies
				$local_policies = $this->__getTemplatePolicies($user_role, $ref_id);

				$parent_policies = $this->__getTemplatePolicies($user_role, $parent_role_folder);
				$non_member_policies = $this->__getTemplatePolicies($non_member_template_id, '');
				$non_member_policies = array_values($this->__intersectPolicies($non_member_policies, $parent_policies));

				if ($local_ops == $template_ops && $local_policies == $non_member_policies) {
					$rbacadmin->copyRoleTemplatePermissions($user_role, $parent_role_folder, $ref_id, $user_role);
					$local_policies = $this->__getTemplatePolicies($user_role, $ref_id, 'crs');
					$rbacadmin->grantPermission($user_role,$local_policies,$ref_id);
				}
			}	
		}


		// FFMPEG Conversion of media files
		if ($a_component == 'Services/MediaObjects' && $a_event == 'update' && isset($a_parameter) && count($a_parameter)>0 && isset($a_parameter['object'])){
			
			//check if new object is not in Marcos special course
			//special for Marco Sommer, until end of HS15
			if(!in_array($_GET['ref_id'],array(2899661,2899719,2903368,2903382,2903598,2903603,2903606,2903613,2903621,2903625,2903639,2903641,2903643,2903645,2903649,2903652,2903658))){
			
				$allMediaItems=$a_parameter['object']->getMediaItems();
				$ffmpegQueue='ffmpegQueue.txt';
				
				foreach($allMediaItems as $media_item){
					$filename=$media_item->location;
					
					//print substr($filename, strrpos($filename,'.')+1);exit;
					
					//if(substr($filename,-3)!='mp4'){
					if(in_array(substr($filename, strrpos($filename,'.')+1), array('mp4','m4v','mov','flv','wmv','avi','mts','m2ts','mov','avi','wmv','aac','rm','mpg','mpeg','divx','flv','swf','ts','vob','mkv','ogv','mjpeg','m4v','3gpp'))){
						
						global $ilUser;
						$folder=ilObjMediaObject::_getDirectory($a_parameter['object']->getId());
						
						if(substr($filename,-3)!='mp4'){
							$numofLines=ilHSLUObjectDefaultsPlugin::lineCount($ffmpegQueue);
							ilUtil::sendSuccess('Ihre Datei wurde hochgeladen und wird nun in ein Streaming-kompatibles Format konvertiert. Sie werden via Mail informiert, sobald die Konvertierung abgeschlossen ist. Vor der aktuell hochgeladenen Datei hat es '.$numofLines.' andere in der Warteschlange. Besten Dank für Ihre Geduld.', true);
						}
						
						file_put_contents($ffmpegQueue, $folder.'/'.$filename.'|'.$folder.'/'.substr($filename,0, strrpos($filename,'.')).'.mp4|'.$ilUser->getEmail()."\n", FILE_APPEND);
						
						//geht nicht wegen client cache
						//@copy('Customizing/mob_vpreview.png', $folder.'/mob_vpreview.png');
						
						@chmod($folder,0775);
						
						//set new media format
						$media_item->setFormat('video/mp4');
						$media_item->setLocation(substr($filename,0, strrpos($filename,'.')).'.mp4');
						$media_item->update();
						
					}
				}
			
			}
		}
	}

	
	
	
	
	
	
	
	
	
	/**
	 * @return string
	 */
	public function getPluginName() {
		return self::PLUGIN_NAME;
	}


	/**
	 * @return bool
	 */
	public static function is55() {
		return ((int)str_ireplace('.', '', ILIAS_VERSION_NUMERIC)) >= 500;
	}

	private function _getGlobalUserRoleId() {
		global $rbacreview;
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
	private function __getCrsNonMemberTemplateId() {
		global $ilDB;
	
		$q = "SELECT obj_id FROM object_data WHERE type='rolt' AND title='il_crs_non_member'";
		$res = $ilDB->query($q);
		$row = $res->fetchRow(DB_FETCHMODE_ASSOC);
	
		return $row["obj_id"];
	}
	
	private function __getTemplatePolicies ($template_id, $folder_ref = '', $type = '') {
		global $ilDB;
		
		if ($folder_ref == '') {
			$query = "SELECT o_r.ref_id FROM object_reference o_r ". 
					"JOIN object_data o_d ON o_r.obj_id = o_d.obj_id ".
					"WHERE o_d.type = 'rolf' ".
					"AND (o_d.title = 'Rollen' OR o_d.title = 'Roles')";
			$res = $ilDB->query($query);
			$folder_ref = $ilDB->fetchObject($res)->ref_id;
		}
		
		if ($type != '') {
			$type = ' AND type='.$ilDB->quote($type, 'text');
		}
		
		$query = 'SELECT * FROM rbac_templates '.
				'WHERE rol_id = '.$ilDB->quote($template_id,'integer').' '.
				'AND parent = '.$ilDB->quote($folder_ref,'integer').$type;
		$res = $ilDB->query($query);
		$operations = array();

		while ($row = $ilDB->fetchObject($res))
		{
			array_push($operations, $row->ops_id);
		}
		
		return $operations;
	}
	
	private function __intersectPolicies ($current_ops, $needle_ops) {
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
