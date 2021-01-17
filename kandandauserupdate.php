<?php

/**
 * @package    Kandandauserupdate
 * @subpackage Base
 * @author     Heinl Christian
 * @author     Created on 13-Nov-2013
 * @license    GNU/GPL
 */
//-- No direct access
defined('_JEXEC') || die('=;)');


jimport('joomla.plugin.plugin');
jimport('joomla.application.component.helper');

/**
 * Joomla 3 introduced a very annoying change, $article parameter in
 * onContentAfterSave event is now passed by value, while in Joomla 1.6.-2.5
 * it was passed by reference and having the same method definition for both
 * will cause an error in one of them.
 * So dependent on the current joomla version a decision is made how the
 * onContentAfterSave method is called. This is done by using different
 * derived classes each of them is calling the same method of its base class.
 */
if (version_compare(JVERSION, '3.0', '<')) {

    class plgContentKandandauserupdate extends plgContentKandandaUserUpdate_Intermed {

        public function onContentAfterSave($context, &$article, $isNew) {
            $this->onContentAfterSaveIntermed($context, $article, $isNew);
        }

    }

} else {

    class plgContentKandandauserupdate extends plgContentKandandaUserUpdate_Intermed {

        public function onContentAfterSave($context, $article, $isNew) {
            $this->onContentAfterSaveIntermed($context, $article, $isNew);
        }

    }

}

/**
 * Content Plugin.
 *
 * @package    Kandandauserupdate
 * @subpackage Plugin
 * @abstract   This plugin notifies about changed or newly added Kandanda field 
 *             values. Therefore a email address must be configured via plugin 
 *             parameter 'notification_email' where the notification email 
 *             should be sent to.
 *             Additionally this plugin updates the email address of the 
 *             assigned Joomla user when the Kandanda email address will be 
 *             changed accordingly.
 *             Therefore the plugin has to know in which Kandanda field the
 *             email addresses are stored. If this field is created as a
 *             copy-field, only the content of the base field is used as the new
 *             email address for the associated Joomla user.
 *             If no Joomla user is assigned to the Kandanda member no update
 *             is performed.
 */

/**
 * Intermediate class for making plugin compatible between joomla 2.5 and 3.0
 */
class plgContentKandandaUserUpdate_Intermed extends JPlugin {

    //array which holds all sections a Kandanda member is assigned to
    private $assigned_sections;

    /**
     * Constructor
     *
     * For php4 compatability we must not use the __constructor as a constructor for plugins
     * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
     * This causes problems with cross-referencing necessary for the observer design pattern.
     *
     * @param object $subject The object to observe
     * @param object $params  The object that holds the plugin parameters
     * @since 1.5
     */
    function plgContentKandandaUserUpdate_Intermed(&$subject, $params) {
        parent::__construct($subject, $params);

        /* load language file for plugin frontend */
        $this->loadLanguage();
    }

    /**
     * This is an event that is called right before the content is saved into
     * the database. You can abort the save by returning false.
     *
     * @param string $context
     * @param JTableContent $content
     * @param boolean $isNew
     */
    public function onContentAfterSaveIntermed($context, $content, $isNew) {
        // Don't run this plugin when a save is done not from Kandanda
        if ($context != 'com_kandanda.kandanda' && $context != 'com_kandanda.member') {
            return;
        }

        //**************************************************************************
        // * Notification about changed Kandanda member data
        //**************************************************************************
        //check if valid email is set in plugin parameter for sending Kandanda change notifications        
        if (JMailHelper::isEmailAddress($this->params->get('notification_email', ''))) {
            $notify=$this->params->get('send_notification','');
            $app =& JFactory::getApplication();
            
            //0=don't send emails
            //1=send emails while data change has been done in frontend or backend
            //2=send emails while data change has been done in frontend
            //3=send emails while data change has been done in backend
            if (($notify==1 && ($app->isSite() || $app->isAdmin())) ||
                ($notify==2 && $app->isSite()) || 
                ($notify==3 && $app->isAdmin()))
            {
              $this->sendUpdateNotifiction($content, $isNew);              
            }
        }


        //**************************************************************************
        // * Adaption of joomla user email, if assigned Kandanda user mail has changed
        //**************************************************************************
        //Only update joomla user data if kandanda member is assigned to joomla user
        //and if user is not newly entered
        if ($content->user_id != 0 && !$isNew && $this->params->get('update_joomla_user_email', '') == 1) {
            $this->updateJoomlaUserData($content);
        }
    }

    /**
     * This method checks for changed or new Kandanda member data and informs
     * via email about the new or changed data.
     * 
     * @param JTableContent $content
     * @param boolean $isNew
     */
    private function sendUpdateNotifiction($content, $isNew) {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);

        $user = JFactory::getUser();
        $mailer = JFactory::getMailer();
        $config = JFactory::getConfig();

        //fetch the field values which were submitted by Kandanda edit form
        $jinput = JFactory::getApplication()->input->get('jform', array(), 'array');  
        
        
        //**************************************************************************
        // * Email notification about changed member data
        //**************************************************************************
        //if Kandanda member data was changed
        if (!$isNew) {
            //setup query to get old member values
            //When onContentAfterSaveIntermed is called the new values are not yet saved in database
            //First process kandanda field values (assigned groups are processed later)
            $query->clear();
            $query->select('f.value, f.instance, a.title, a.alias');
            $query->from('#__kandanda_fields_values AS f');
            $query->leftjoin('#__kandanda_fields AS a ON a.id = f.field_id');
            $query->where('f.member_id = ' . $content->id);
            $query->order('a.id');
            $db->setQuery($query);
            
            $kandanda_member_data = $db->loadAssoclist();

//            print("<pre>");
//            print_r($query->dump());
//            print_r($kandanda_member_data);
//            
//            print_r($jinput);
//            print("</pre>"); die();
//            
//            
            //generate array which holds the old data in a associative array which has
            //the following key syntax: alias_0_0=>value
            //The last number is incremented for each entry of the same alias
            $oldData = array();
            foreach ($kandanda_member_data as $data) {
                $iInstance=$data[instance];
                $iIndex = 0;
                while (array_key_exists($data[alias] . '_'.$iInstance.'_' . $iIndex, $oldData)) {
                    $iIndex++;
                }
                $oldData[$data[alias] . '_'.$iInstance.'_' . $iIndex] = $data[value];
            }
            
//            print("<pre>");
//            print_r($oldData);            
//            print("</pre>"); die();

            $iChangeCounter = 0;
            $buffer = "";
            $regex = "#(.*?)_\d+?_\d+?#s";
            $matches = array();
            foreach ($jinput as $key => $value) {
                //if value itself is an array (e.g. if field is a select type)
                //then the values are stored separated by "\n" in database
                if (is_array($value)) {
                    $value = implode("\n", $value); //convert array to string separated by "\n"
                }

                //if values has changed and field is user defined(end up with '_number_number', e.g. _0_0)
                if ($oldData[$key] != $value && preg_match($regex, $key, $matches)) {
                    $iChangeCounter++;
                    //matches[1] contains the field alias without '_number_number' postfix
                    //in case of array data converted to strings divided by "\n" the "\n" are replaced by ","
                    $buffer = $buffer . $matches[1] . ": " .
                            str_replace("\n", ", ", $oldData[$key]) . " => " . str_replace("\n", ", ", $value) . "\n";
                }
            }

            $this->checkSectionAssignment($content->id, $iChangeCounter, $buffer);
            $this->checkSectionFeeAssignment($content->id, $iChangeCounter, $buffer);
        } else {
            $iChangeCounter = 1;
            $buffer = $jinput[lastname . '_0_0'] . " " . $jinput[firstname . '_0_0'];
        }

        if ($iChangeCounter > 0) {            
            //Setup email stuff
            $sender = array($config->get('mailfrom'), $config->get('fromname'));
            if(is_array($sender) && version_compare(JVERSION,'3.5','>=') == 1):
              $mailer->addReplyTo($sender[0], $sender[1]);
              $mailer->setSender($sender[0], $sender[1]);          
            else:
              $mailer->addReplyTo($sender);
              $mailer->setSender($sender);
            endif;
        

            $recipient = $this->params->get('notification_email', '');
            if(is_array($recipient) && version_compare(JVERSION,'3.5','>=') == 1):
              $mailer->addRecipient($recipient[0], $recipient[1]);          
            else:
              $mailer->addRecipient($recipient);          
            endif;
        

		    setlocale(LC_CTYPE,"de_DE.UTF-8");
//$string = "Deutsche Umlaute Ä Ö Ü ä ö ü ß";
//$string = iconv('UTF-8', 'ASCII//TRANSLIT', $buffer);
//echo $string;  die();
			//print $buffer;die();

            $body = JText::sprintf($isNew ? PLG_KANDANDA_USERUPDATE_MEMBER_DATA_ADDED : PLG_KANDANDA_USERUPDATE_MEMBER_DATA_CHANGED, $user->name, $user->username, $user->email, iconv("UTF-8", "ASCII//TRANSLIT", $jinput[lastname . '_0_0'] . " " . $jinput[firstname . '_0_0']), iconv("UTF-8", "ASCII//TRANSLIT", $buffer));
            //$subject = JText::sprintf($isNew ? PLG_KANDANDA_USERUPDATE_SUBJECT_MEMBER_DATA_ADDED : PLG_KANDANDA_USERUPDATE_SUBJECT_MEMBER_DATA_CHANGED, $user->name, $user->username, $user->email, iconv("UTF-8", "CP1252//TRANSLIT", $jinput[lastname . '_0_0'] . " " . $jinput[firstname . '_0_0']), iconv("UTF-8", "CP1252//TRANSLIT", $buffer));
			$subject = JText::sprintf($isNew ? PLG_KANDANDA_USERUPDATE_SUBJECT_MEMBER_DATA_ADDED : PLG_KANDANDA_USERUPDATE_SUBJECT_MEMBER_DATA_CHANGED, $user->name, $user->username, $user->email, iconv("UTF-8", "ASCII//TRANSLIT", $jinput[lastname . '_0_0'] . " " . $jinput[firstname . '_0_0']), iconv("UTF-8", "ASCII//TRANSLIT", $buffer));
			
			//print "<pre>";
			//print_r($body);die();
			//print "</pre>";
            //
            $mailer->setSubject($subject);            

			//set email body while converting all characters that can't be represented in the target charset, 
			//are approximated through one or several similarly looking characters.
            $mailer->setBody($body);

            $mailer->Send();
        }

        //print("<pre>");
        //print_r($body);
        //print("</pre>");
        //die();
    }

    /**
     * This method updates the email-address of assigned Joomla user to the changed
     * Kandanda email-address. The Kandanda field which holds the email-adress is configured via 
     * a plugin parameter which is named 'email_field'.
     * 
     * @param JTableContent $content
     */
    private function updateJoomlaUserData($content) {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);

        //fetch the field values which were submitted by Kandanda edit form
        $jinput = JFactory::getApplication()->input->get('jform', array(), 'array');

        //get plugin parameter for Kandanda field which is used for storing member emails
        $mail_field = $this->params->get('email_field', '');

        $query->clear();

        //get current email address of assigned joomla user
        $query->select('email');
        $query->from('#__users');       
        $query->where('id = ' .$content->user_id);
        $db->setQuery($query, 0, 1); //limit data to one record
        $result = $db->loadAssoc();


        //get value of the currently stored email field (base field is used)
        $new_kandanda_email = $jinput[$mail_field . '_0_0'];
        //get the value of the currently assigned joomla member email address
        $joomla_email = $result['email'];
        //get the user id of assigned Joomla user of current Kandanda member
        $joomla_user_id = $content->user_id;

        //check if new Kandanda email address is a valid email address
        if (!JMailHelper::isEmailAddress($new_kandanda_email)) {
            JError::raiseNotice(100, JText::_('PLG_KANDANDA_USERUPDATE_INVALID_EMAIL'));
            return;
        }

        //only if joomla email address and new Kandanda email address differs
        if ($new_kandanda_email != $joomla_email) {

            $query->clear();
            // Fields to update.
            $fields = array($db->quoteName('email') . '=\'' . $new_kandanda_email . '\'');
            // Conditions for which records should be updated.
            $conditions = array($db->quoteName('id') . '=' . $joomla_user_id);
            $query->update($db->quoteName('#__users'))->set($fields)->where($conditions);
            $db->setQuery($query);
            $db->query();
        }
    }

    /**
     * This method checks which section and section_fees assignment of a Kandanda
     * user was changed. All changed values are returned as a string via the 
     * reference parameter $buffer.
     * 
     * @param integer $memberId
     * @param integer $iChangeCounter
     * @param string $buffer
     */
    private function checkSectionAssignment($memberId, &$iChangeCounter, &$buffer) {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->clear();

        //get configuration parameter of Kandanda component
        $KandandaParams = JComponentHelper::getParams('com_kandanda');

        //fetch the field values which were submitted by Kandanda edit form
        $jinput = JFactory::getApplication()->input->get('jform', array(), 'array');


        //first get all sections to which the member has belonged to
        $query->select('c.id, c.title, c.alias, m.fee');
        $query->from('#__kandanda_member_category_map AS m');
        $query->leftjoin('#__categories AS c ON c.id = m.catid');
        $query->where('m.member_id = ' . $memberId);
        $query->order('c.id');
        $db->setQuery($query);
        $kandanda_old_section_data = $db->loadAssoclist();
//      print("<pre>");
//      print_r($kandanda_old_section_data);      
//      print("</pre>"); die();
//      
        //if 'sections' key exists in array (only if user is allowed to change section assignments)
        if (array_key_exists('sections', $jinput)) {
            //get all section names, the member belongs now to
            $query->clear();
            $query->select('id,title, alias');
            $query->from('#__categories');
            $query->where('id in (' . implode(',', $jinput['sections']) . ')');
            $query->order('id');
            $db->setQuery($query);
            $kandanda_new_section_data = $db->loadAssoclist();
        } else if ($KandandaParams->get('edit_club_section') == 0) {
            //new section assignment is equal to old section assignment
            $kandanda_new_section_data = $kandanda_old_section_data;
        }

//      print("<pre>");
//      print_r($kandanda_new_section_data);
//      print("</pre>"); die();     

        $added = array(); //array to hold new added member sections
        $deleted = array(); //array to hold deleted member sections
        $fee = array(); //array to hold changed fee assignments

        if (count($kandanda_old_section_data) > 0) {
            //loop over old section data and compare it with new section data
            //in order to detect deleted section assignments
            foreach ($kandanda_old_section_data as $old) {
                $found = false;

                //loop over new section data
                foreach ($kandanda_new_section_data as $new) {
                    //if section was already assigned in old member data
                    if ($old['id'] == $new['id']) {
                        $found = true;
                        break;
                    }
                }
                //if old section id was not found in new section data --> section was deleted
                if (!$found) {
                    $deleted[] = $old['title'];
                }
            }
        }

        //loop over new section data and compare it with old section data
        //in order to detect added section assignments
        foreach ($kandanda_new_section_data as $new) {
            $found = false;

            //store currently assigned sections in member array
            //needed for checking section fee assignment
            $this->assigned_sections[] = $new['id'];

            if (count($kandanda_old_section_data) > 0) {
                //loop over old section data
                foreach ($kandanda_old_section_data as $old) {
                    //if section was already assigned in old member data
                    if ($new['id'] == $old['id']) {
                        $found = true;
                        break;
                    }
                }
            }
            //if new section id was not found in old section data --> section was added
            if (!$found) {
                $added[] = $new['title'];
            }
        }

        $iChangeCounter += count($deleted) + count($added) + count($fee);

        foreach ($deleted as $deleted_section) {
            $buffer = $buffer . JText::_('PLG_KANDANDA_USERUPDATE_SECTION') . ": \"" . $deleted_section . "\" " . JText::_('PLG_KANDANDA_USERUPDATE_NOT_ASSIGNED') . "\n";
        }

        foreach ($added as $added_section) {
            $buffer = $buffer . JText::_('PLG_KANDANDA_USERUPDATE_SECTION') . ": \"" . $added_section . "\" " . JText::_('PLG_KANDANDA_USERUPDATE_ASSIGNED') . "\n";
        }
    }

    /**
     * This method checks which section_fees assignment of a Kandanda
     * user was changed. All changed values are returned as a string via the 
     * reference parameter $buffer.
     * 
     * @param integer $memberId
     * @param integer $iChangeCounter
     * @param string $buffer
     */
    private function checkSectionFeeAssignment($memberId, &$iChangeCounter, &$buffer) {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->clear();

        //get configuration parameter of Kandanda component
        $KandandaParams = JComponentHelper::getParams('com_kandanda');

        //fetch the field values which were submitted by Kandanda edit form
        $jinput = JFactory::getApplication()->input->get('jform', array(), 'array');


        //first get all section fees to which the member has belonged to
        $query->select('c.id, c.title, c.alias, m.fee');
        $query->from('#__kandanda_member_category_map AS m');
        $query->leftjoin('#__categories AS c ON c.id = m.catid');
        $query->where('m.fee=1');
        $query->where('m.member_id = ' . $memberId);
        $query->order('c.id');
        $db->setQuery($query);
        $kandanda_old_sectionfee_data = $db->loadAssoclist();
//      print("<pre>");
//      print_r($kandanda_old_sectionfee_data);      
//      print("</pre>"); die();
//      
        //if 'sections_fees' key exists in array (only if user is allowed to change section assignments)
        if (array_key_exists('section_fees', $jinput)) {
            //get all section fee names, the member belongs now to
            $query->clear();
            $query->select('id,title, alias');
            $query->from('#__categories');
            $query->where('id in (' . implode(',', $jinput['section_fees']) . ')');
            $query->order('id');
            $db->setQuery($query);
            $kandanda_new_sectionfee_data = $db->loadAssoclist();
        }
        //if editing of section fees is not allowed -> new section fee is old section fee
        else if ($KandandaParams->get('edit_club_section_fee') == 0) {
            //new section assignment is equal to old section assignment
            $kandanda_new_sectionfee_data = $kandanda_old_sectionfee_data;
        }

//      print("<pre>");
//      print_r($kandanda_new_sectionfee_data);
//      print("</pre>"); die();     


        $added = array(); //array to hold new added member section fees
        $deleted = array(); //array to hold deleted member section fees
        //loop over old section fee data and compare it with new section fee data
        //in order to detect deleted section fee assignments
        foreach ($kandanda_old_sectionfee_data as $old) {
            $found = false;

            if (count($kandanda_new_sectionfee_data) > 0) {
                //loop over new section fee data
                foreach ($kandanda_new_sectionfee_data as $new) {
                    //if section fee was already assigned in old member data
                    if ($old['id'] == $new['id']) {
                        $found = true;
                        break;
                    }
                }
            }
            //if old section fee id was not found in new section data --> section fee was deleted
            if (!$found) {
                $deleted[] = $old['title'];
            }
        }

        if (count($kandanda_new_sectionfee_data) > 0) {
            //loop over new section fee data and compare it with old section fee data
            //in order to detect added section fee assignments
            foreach ($kandanda_new_sectionfee_data as $new) {
                $found = false;

                if (!in_array($new['id'], $this->assigned_sections)) {
                    continue;
                }

                //loop over old section fee data
                foreach ($kandanda_old_sectionfee_data as $old) {
                    //if section fee was already assigned in old member data
                    if ($new['id'] == $old['id']) {
                        $found = true;
                        break;
                    }
                }
                //if new section fee id was not found in old section data --> section fee was added
                if (!$found) {
                    $added[] = $new['title'];
                }
            }
        }

        $iChangeCounter += count($deleted) + count($added);

        foreach ($deleted as $deleted_section_fee) {
            $buffer = $buffer . JText::_('PLG_KANDANDA_USERUPDATE_FEE') . ": \"" . $deleted_section_fee . "\" " . JText::_('PLG_KANDANDA_USERUPDATE_NOT_ASSIGNED') . "\n";
        }

        foreach ($added as $added_section_fee) {
            $buffer = $buffer . JText::_('PLG_KANDANDA_USERUPDATE_FEE') . ": \"" . $added_section_fee . "\" " . JText::_('PLG_KANDANDA_USERUPDATE_ASSIGNED') . "\n";
        }
    }
}
