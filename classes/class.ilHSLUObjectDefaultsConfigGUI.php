<?php
/**
 * HSLUObjectDefaults configuration user interface class
 *
 * @author  Stephan Winiker <stephan.winiker@hslu.ch>
 * @version $Id$
 * @ilCtrl_isCalledBy    ilHSLUObjectDefaultsConfigGUI: ilObjComponentSettingsGUI
 */
class ilHSLUObjectDefaultsConfigGUI extends ilPluginConfigGUI
{
    private $instance;
    private $ui;
    private $ctrl;
    private $db;
    private $lng;
    private $refinery;
    private $pl;
    
    public function performCommand($cmd): void
    {
        global $DIC;
        $this->ui = $DIC->ui();
        $this->ctrl = $DIC->ctrl();
        $this->db = $DIC->database();
        $this->lng = $DIC->language();
        $this->request = $DIC->http()->request();
        $this->refinery = $DIC->refinery();
        $this->pl = new ilHSLUObjectDefaultsPlugin();
        
        switch ($cmd) {
            case 'configure':
            case 'save':
                $this->$cmd();
                break;
        }
    }
    
    private function configure()
    {
        $form_fields = $this->initFormFields();
        $form_fields_with_values = $this->initFormValues($form_fields);
        $form = $this->initForm($form_fields_with_values);
        $this->ui->mainTemplate()->setContent($this->ui->renderer()->render($form));
    }

    private function initFormFields() : array
    {
        $factory = $this->ui->factory();
        
        $remove_spaces_trafo = $this->refinery->custom()->transformation(function (string $v) {
            return str_replace(' ', '', $v);
        });
        
        $types_to_transform['video_types'] = $factory->input()->field()
           ->text($this->pl->txt('video_types_label'), $this->pl->txt('video_types_info'))
           ->withAdditionalTransformation($remove_spaces_trafo);
        $types_to_transform['audio_types'] = $factory->input()->field()
           ->text($this->pl->txt('audio_types_label'), $this->pl->txt('audio_types_info'))
           ->withAdditionalTransformation($remove_spaces_trafo);
        
        return ['types_to_transform' => $types_to_transform];
    }
    
    private function initFormValues(array $form_fields)
    {
        $form_fields_with_values = [];
        foreach ($form_fields as $section_name => $section_content) {
            $form_fields_with_values[$section_name] = [];
            
            foreach ($section_content as $field_key => $field_value) {
                $form_fields_with_values[$section_name][$field_key] = $field_value->withValue(self::getValue($field_key));
            }
        }
            
        return $form_fields_with_values;
    }
    
    private function initForm(array $form_fields_with_values)
    {
        $factory = $this->ui->factory();
        
        foreach ($form_fields_with_values as $section_name => $section_content) {
            $sections[$section_name] = $factory->input()->field()->section($section_content, $this->pl->txt($section_name . '_section_label'));
        }
        
        $form_actions = $this->ctrl->getFormActionByClass('ilHSLUObjectDefaultsConfigGUI', 'save');
        return $factory->input()->container()->form()->standard($form_actions, $sections);
    }

    private function save()
    {
        if ($this->request->getMethod() == "POST") {
            $fields = $this->initFormFields();
            $form = $this->initForm($fields);
        }
        $form = $form->withRequest($this->request);
        $result = $form->getData();
        $errors = $this->saveResult($result);
        if (count($errors) > 0) {
            $fields_with_errors = $this->setErrorText($fields, $errors);
            $form = $this->initForm($fields_with_errors);
            $form = $form->withRequest($this->request);
        }
        $this->ui->renderer()->render($form);
        $this->ui->mainTemplate()->setContent($this->ui->renderer()->render($form));
    }

    private function saveResult($result)
    {
        $errors = [];
        $changed_values = 0;
        
        foreach ($result as $section_key => $section_content) {
            foreach ($section_content as $key => $value) {
                if ($value != self::getValue($key)) {
                    $rows_changed = $this->db->update(
                        'evhk_hsluobjdef_s',
                        array('config_key' => array('text', $key), 'config_value' => array('text', $value)),
                        array('config_key' => array('text', $key))
                    );
                    
                    if ($rows_changed == 0) {
                        $errors[$section_key] = [$key => $value];
                        continue;
                    }
                    $changed_values++;
                }
            }
        }

        if (count($errors) > 0) {
            ilHSLUObjectDefaultsPlugin::sendFailure($this->lng->txt('msg_form_save_error'));
            return $errors;
        }
        
        if ($changed_values == 0) {
            ilHSLUObjectDefaultsPlugin::sendInfo($this->lng->txt('no_changes'), true);
            return $errors;
        }

        ilHSLUObjectDefaultsPlugin::sendSuccess($this->lng->txt('saved_successfully'), true);
        return $errors;
    }
    
    private function setErrorText($fields, $errors)
    {
        foreach ($errors as $section_key => $section_value) {
            foreach ($section_value as $field_key => $field_value) {
                $fields[$section_key][$field_key] = $fields[$section_key][$field_key]->withError($this->pl->txt('config_not_saved'));
            }
        }
        return $fields;
    }

    /**
     * @param string $key
     *
     * @return bool|string
     */
    public static function getValue($key)
    {
        global $DIC;
        $db = $DIC->database();
            
        $result = $db->query('SELECT config_value FROM evhk_hsluobjdef_s WHERE config_key = '
                . $db->quote($key, 'text'));
        if ($result->numRows() == 0) {
            return '';
        }
        $record = $db->fetchAssoc($result);
                        
        try {
            return $DIC->refinery()->kindlyTo()->bool()->transform($record['config_value']);
        } catch (Exception $e) {
            return $record['config_value'];
        }
    }
}
