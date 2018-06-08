<?php
/**
 * HSLUObjectDefaults configuration user interface class
 *
 * @author  Stephan Winiker <stephan.winiker@hslu.ch>
 * @version $Id$
 *
 */
class ilHSLUObjectDefaultsConfigGUI extends ilPluginConfigGUI {
	/**
	 * @var ilPropertyFormGUI
	 */
	private $instance;
	private $form;
	private $tpl;
	private $ctrl;
	private $db;
	private $lng;
	private $pl;
	
	/**
	 * Handles all commands, default is "configure"
	 */
	function performCommand($cmd) {
		global $DIC;
		$this->tpl = $DIC->ui()->mainTemplate();
		$this->ctrl = $DIC->ctrl();
		$this->db = $DIC->database();
		$this->lng = $DIC->language();
		$this->pl = new ilHSLUObjectDefaultsPlugin();
		
		switch ($cmd) {
			case 'configure':
			case 'save':
				$this->$cmd();
				break;
		}
	}


	/**
	 * Configure screen
	 */
	function configure() {
		$this->initConfigurationForm();
		$this->initConfigurationFormValues();
		$this->tpl->setContent($this->form->getHTML());
	}

	/**
	 * Init configuration form.
	 *
	 * @return object form object
	 */
	private function initConfigurationForm() {
		/** @var ilCtrl $ilCtrl */
		$this->form = new ilPropertyFormGUI();
		$input = new ilTextAreaInputGUI($this->pl->txt('message_label'), 'message');
		$input->setInfo($this->pl->txt('message_info'));
		$input->setUseRte(1);
		$input->setRequired(false);
		$this->form->addItem($input);
		
		$input = new ilCheckboxInputGUI($this->pl->txt('active_label'), 'active');
		$input->setInfo($this->pl->txt('active_info'));
		$input->setRequired(false);
		$this->form->addItem($input);
		
		$this->form->addCommandButton('save', $this->lng->txt('save'));
		$this->form->setTitle($this->getPluginObject()->txt('configuration'));
		$this->form->setFormAction($this->ctrl->getFormAction($this));
	}
	
	private function initConfigurationFormValues() {
		$values['message'] = self::getValue('message');
		$values['active'] = self::getValue('active');
		$this->form->setValuesByArray($values);
	}


	/**
	 * Save form input
	 */
	private function save() {
		$this->initConfigurationForm();
		if ($this->form->checkInput()) {
			$active = $this->form->getInput('active') ? '1' : '0';
			$this->db->update('evhk_hsluobjdef_s', array('config_key' => array('text', 'message'), 'config_value' => array('text', $this->form->getInput('message'))), array('config_key' => array('text', 'message')));
			$this->db->update('evhk_hsluobjdef_s', array('config_key' => array('text', 'active'), 'config_value' => array('text', $active)), array('config_key' => array('text', 'active')));
			ilUtil::sendSuccess($this->lng->txt('saved_successfully'), true);
			$this->ctrl->redirect($this, 'configure');
		} else {
			$this->form->setValuesByPost();
			$this->tpl->setContent($this->form->getHtml());
		}
	}


	/**
	 * @param string $key
	 *
	 * @return bool|string
	 */
	public static function getValue($key) {
			global $DIC;
			$db = $DIC->database();
			
			$result = $db->query('SELECT config_value FROM evhk_hsluobjdef_s WHERE config_key = '
				. $db->quote($key, 'text'));
			if ($result->numRows() == 0) {
				return false;
			}
			$record = $db->fetchAssoc($result);
			return $record['config_value'];
	}
}