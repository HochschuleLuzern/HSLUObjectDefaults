<?php
require_once('./Services/EventHandling/classes/class.ilEventHookPlugin.php');

/**
 * Class ilHSLUObjectDefaultsPlugin
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

		//create course
		if ($a_component == 'Modules/Course' && ($a_event == 'create') /*&& $a_parameter['obj_type']=='crs'*/) {
		
			$crs = $a_parameter['object'];
		
			//activation unlimited, not offline
			$crs->setActivationType(IL_CRS_ACTIVATION_UNLIMITED);
			$crs->setOfflineStatus(false);

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

}

?>
