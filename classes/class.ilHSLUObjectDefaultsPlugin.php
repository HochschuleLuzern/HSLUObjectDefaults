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
class ilHSLUObjectDefaultsPlugin extends ilEventHookPlugin
{
    const ID = 'hsluobjdef';
    public function __construct()
    {
        global $DIC;
        $this->db = $DIC->database();
        parent::__construct($this->db, $DIC["component.repository"], self::ID);
    }
    /**
     * @return string
     */
    public function getPluginName(): string
    {
        return self::PLUGIN_NAME;
    }

    protected const PLUGIN_NAME = 'HSLUObjectDefaults';


    /**
     * Handle the event
     *
     * @param    string        component, e.g. "Services/User"
     * @param    event         event, e.g. "afterUpdate"
     * @param    array         array of event specific parameters
     */
    public function handleEvent($a_component, $a_event, $a_parameter): void
    {
        global $DIC;

        if ($a_component == 'Modules/Course' && ($a_event == 'create')) {
            /**
             * We set courses to online and active for an unlimited period of time by default
             * Since ILIAS 6, we also set the news per default to activated
             * @var $crs ilObjCourse
             */
            $crs = $a_parameter['object'];
        
            //activation unlimited, not offline
            $crs->setOfflineStatus(false);

            // Activate news per default
            $crs->setUseNews(true);
            $crs->setNewsBlockActivated(true);
            return;
        }
        
        if ($a_component == 'Modules/Group' && ($a_event == 'create')) {
            /**
             * Since ILIAS 6, we also set the news per default to activated
             * @var $grp ilObjGroup
             */
            $grp = $a_parameter['object'];

            // Activate news per default
            $grp->setUseNews(true);
            $grp->setNewsBlockActivated(true);
            return;
        }
        
        if (($a_component == 'Modules/Course' || $a_component == 'Modules/Group') && ($a_event == 'addParticipant' || $a_event == 'deleteParticipant')) {
            // Is used when a user is added to a course
            // the course is then added to their "Favorites"
            // This is used with ILIAS6
            $this->participantAddedOrRemoved($a_event, (int) $a_parameter['usr_id'], (int) $a_parameter['obj_id']);
            return;
        }
        
        if ($a_component == 'Services/MediaObjects' && $a_event == 'update' && isset($a_parameter) && count($a_parameter) > 0 && isset($a_parameter['object'])) {
            // FFMPEG Conversion of media files
            $this->addToConversionQueue($a_parameter, $DIC->user());
            return;
        }
    }

    private function participantAddedOrRemoved(string $a_event_name, int $user_id, int $obj_id) : string
    {
        $list_of_ref_ids = ilObject::_getAllReferences($obj_id);
        if (count($list_of_ref_ids) < 1) {
            return 'no_objects_found';
        }
        
        $ref_id = array_shift($list_of_ref_ids);
        $fav_manager = new ilFavouritesManager();
        if ($a_event_name == 'addParticipant') {
            $fav_manager->add($user_id, $ref_id);
            return 'added';
        }
        if ($a_event_name == 'deleteParticipant') {
            $fav_manager->remove($user_id, $ref_id);
            return 'removed';
        }
        
        return 'no_change';
    }
    
    private function addToConversionQueue(array $a_parameter, ilObjUser $user) : void
    {
        $allMediaItems = $a_parameter['object']->getMediaItems();
        $ffmpegQueue = 'data/ffmpegQueue.txt';
        
        $pl = $this->pl = new ilHSLUObjectDefaultsPlugin();
        
        foreach ($allMediaItems as $media_item) {
            $filename = $media_item->location;
            $media_item->resetParameters();

            $video_prefixes = explode(',', ilHSLUObjectDefaultsConfigGUI::getValue('video_types'));
            $audio_prefixes = explode(',', ilHSLUObjectDefaultsConfigGUI::getValue('audio_types'));
            
            if (in_array(mb_strtolower(substr($filename, strrpos($filename, '.') + 1)), $video_prefixes)) {
                $folder = ilObjMediaObject::_getDirectory($a_parameter['object']->getId());
                
                $numofLines = ilHSLUObjectDefaultsPlugin::lineCount($ffmpegQueue);
                ilUtil::sendInfo(sprintf($pl->txt('file_uploaded'), $numofLines), true);
                
                file_put_contents($ffmpegQueue, $folder . '/' . $filename . '|' . $folder . '/' . substr($filename, 0, strrpos($filename, '.')) . '.mp4|' . $user->getEmail() . "\n", FILE_APPEND);
                
                //geht nicht wegen client cache
                //@copy('Customizing/mob_vpreview.png', $folder.'/mob_vpreview.png');
                
                @chmod($folder, 0775);
                
                //set new media format
                $media_item->setFormat('video/mp4');
                $media_item->setLocation(substr($filename, 0, strrpos($filename, '.')) . '.mp4');
                $media_item->update();
            } elseif (in_array(mb_strtolower(substr($filename, strrpos($filename, '.') + 1)), $audio_prefixes) && substr($filename, -3) != 'mp3') {
                $folder = ilObjMediaObject::_getDirectory($a_parameter['object']->getId());
                
                $numofLines = ilHSLUObjectDefaultsPlugin::lineCount($ffmpegQueue);
                ilUtil::sendInfo(sprintf($pl->txt('file_uploaded'), $numofLines), true);
                
                file_put_contents($ffmpegQueue, $folder . '/' . $filename . '|' . $folder . '/' . substr($filename, 0, strrpos($filename, '.')) . '.mp3|' . $user->getEmail() . "\n", FILE_APPEND);
                
                @chmod($folder, 0775);
                
                //set new media format
                $media_item->setFormat('audio/mpeg');
                $media_item->setLocation(substr($filename, 0, strrpos($filename, '.')) . '.mp3');
                $media_item->update();
            }
        }
    }
    
    private static function lineCount($file)
    {
        $linecount = 0;
        $handle = fopen($file, "r");
        while (!feof($handle)) {
            if (fgets($handle) !== false) {
                $linecount++;
            }
        }
        fclose($handle);
        return  $linecount;
    }
}
