<?php

/**
 * Content Plugin.
 *
 * @package    Kandandauserupdate
 * @subpackage Plugin
 * @author     Heinl Christian <heinchrs@gmail.com>
 * @copyright  (C) 2013-2020 Heinl Christian
 * @license    GNU General Public License version 2 or later
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

// -- No direct access
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
if (version_compare(JVERSION, '3.0', '<'))
{
	/**
	 * The main class which implements the plugin behavior
	 *
	 * @author  Heinl Christian <heinchrs@gmail.com>
	 * @since   1.0
	 */
	class plgContentKandandauserupdate extends plgContentKandandaUserUpdate_Intermed
	{
		/**
		 * Method to be executed if content has been saved
		 *
		 * @param   string   $context    The context of the content being passed to the plugin - this is the component name and view
		 *                               or name of module (e.g. com_content.article).
		 *                               Use this to check whether you are in the desired context for the plugin.
		 * @param   object   $article    A reference to the JTableContent object that is being saved which holds the article data.
		 * @param   bool     $isNew      A boolean which is set to true if the content is about to be created.
		 * @return  void
		 */
		public function onContentAfterSave($context, &$article, $isNew)
		{
			$this->onContentAfterSaveIntermed($context, $article, $isNew);
		}
	}
}
else
{
	/**
	 * The main class which implements the plugin behavior
	 *
	 * @author  Heinl Christian <heinchrs@gmail.com>
	 * @since   1.0
	 */
	class plgContentKandandauserupdate extends plgContentKandandaUserUpdate_Intermed
	{
		/**
		 * Method to be executed if content has been saved
		 *
		 * @param   string   $context    The context of the content being passed to the plugin - this is the component name and view
		 *                               or name of module (e.g. com_content.article).
		 *                               Use this to check whether you are in the desired context for the plugin.
		 * @param   object   $article    A reference to the JTableContent object that is being saved which holds the article data.
		 * @param   bool     $isNew      A boolean which is set to true if the content is about to be created.
		 * @return  void
		 */
		public function onContentAfterSave($context, $article, $isNew)
		{
			$this->onContentAfterSaveIntermed($context, $article, $isNew);
		}
	}

}



/**
 * Intermediate class for making plugin compatible between joomla 2.5 and 3.0
 *
 * @author  Heinl Christian <heinchrs@gmail.com>
 * @since 1.0
 */
class plgContentKandandaUserUpdate_Intermed extends JPlugin
{
	/**
	 * Array which holds all sections a Kandanda member is assigned to
	 * @var array
	 */
	private $assignedSections;

	/**
	 * Constructor
	 *
	 * For php4 compatability we must not use the __constructor as a constructor for plugins
	 * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
	 * This causes problems with cross-referencing necessary for the observer design pattern.
	 *
	 * @param   object $subject The object to observe
	 * @param   object $params  The object that holds the plugin parameters
	 * @return  void
	 */
	public function plgContentKandandaUserUpdate_Intermed(&$subject, $params)
	{
		parent::__construct($subject, $params);

		// Load language file for plugin frontend
		$this->loadLanguage();
	}

	/**
	 * This is an event that is called right before the content is saved into
	 * the database. You can abort the save by returning false.
	 *
	 * @param   string   $context    The context of the content being passed to the plugin - this is the component name and view
	 *                               or name of module (e.g. com_content.article).
	 *                               Use this to check whether you are in the desired context for the plugin.
	 * @param   object   $content    A reference to the JTableContent object that is being saved which holds the article data.
	 * @param   bool     $isNew      A boolean which is set to true if the content is about to be created.
	 * @return  void
	 */
	public function onContentAfterSaveIntermed($context, $content, $isNew) {
		// Don't run this plugin when a save is done not from Kandanda
		if ($context != 'com_kandanda.kandanda' && $context != 'com_kandanda.member') {
			return;
		}

		/**
		 *************************************************************************
		 * Notification about changed Kandanda member data
		 *************************************************************************
		 */
		// Check if valid email is set in plugin parameter for sending Kandanda change notifications
		if (JMailHelper::isEmailAddress($this->params->get('notification_email', '')))
		{
			$notify = $this->params->get('send_notification', '');
			$app =& JFactory::getApplication();

			/**
			 * 0=don't send emails
			 * 1=send emails while data change has been done in frontend or backend
			 * 2=send emails while data change has been done in frontend
			 * 3=send emails while data change has been done in backend
			 */
			if (($notify == 1 && ($app->isSite() || $app->isAdmin()))
				|| ($notify == 2 && $app->isSite())
				|| ($notify == 3 && $app->isAdmin()))
			{
				$this->sendUpdateNotifiction($content, $isNew);
			}
		}

		/**
		 ***************************************************************************
		 * Adaption of joomla user email, if assigned Kandanda user mail has changed
		 ***************************************************************************
		 */
		// Only update joomla user data if kandanda member is assigned to joomla user and if user is not newly entered
		if ($content->user_id != 0 && !$isNew && $this->params->get('update_joomla_user_email', '') == 1)
		{
			$this->updateJoomlaUserData($content);
		}
	}

	/**
	 * This method checks for changed or new Kandanda member data and informs
	 * via email about the new or changed data.
	 *
	 * @param   JTableContent $content  A reference to the JTableContent object that is being saved which holds the article data.
	 * @param   boolean       $isNew    A boolean which is set to true if the content is about to be created.
	 * @return  void
	 */
	private function sendUpdateNotifiction($content, $isNew)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);

		$user = JFactory::getUser();
		$mailer = JFactory::getMailer();
		$config = JFactory::getConfig();

		// Fetch the field values which were submitted by Kandanda edit form
		$jinput = JFactory::getApplication()->input->get('jform', array(), 'array');

		/**
		 **************************************************************************
		 * Email notification about changed member data
		 **************************************************************************
		 */
		// If Kandanda member data was changed
		if (!$isNew)
		{
			/*
			 * Setup query to get old member values
			 * When onContentAfterSaveIntermed is called the new values are not yet saved in database
			 * First process kandanda field values (assigned groups are processed later)
			 */
			$query->clear();
			$query->select('f.value, f.instance, a.title, a.alias');
			$query->from('#__kandanda_fields_values AS f');
			$query->leftjoin('#__kandanda_fields AS a ON a.id = f.field_id');
			$query->where('f.member_id = ' . $content->id);
			$query->order('a.id');
			$db->setQuery($query);

			$kandandaMemberData = $db->loadAssoclist();

			/**
			 * print("<pre>");
			 * print_r($query->dump());
			 * print_r($kandandaMemberData);
			 *
			 * print_r($jinput);
			 * print("</pre>"); die();
			 */

			/*
			 * Generate array which holds the old data in a associative array which has
			 * the following key syntax: alias_0_0=>value
			 * The last number is incremented for each entry of the same alias
			 */
			$oldData = array();

			foreach ($kandandaMemberData as $data)
			{
				$iInstance = $data[instance];
				$iIndex = 0;

				while (array_key_exists($data[alias] . '_' . $iInstance . '_' . $iIndex, $oldData))
				{
					$iIndex++;
				}

				$oldData[$data[alias] . '_' . $iInstance . '_' . $iIndex] = $data[value];
			}

			/**
			 * print("<pre>");
			 * print_r($oldData);
			 * print("</pre>"); die();
			 */

			$iChangeCounter = 0;
			$buffer = "";
			$regex = "#(.*?)_\d+?_\d+?#s";
			$matches = array();

			foreach ($jinput as $key => $value)
			{
				// If value itself is an array (e.g. if field is a select type) then the values are stored separated by "\n" in database
				if (is_array($value))
				{
					// Convert array to string separated by "\n"
					$value = implode("\n", $value);
				}

				// If values has changed and field is user defined(end up with '_number_number', e.g. _0_0)
				if ($oldData[$key] != $value && preg_match($regex, $key, $matches))
				{
					$iChangeCounter++;

					/**
					 * Variable matches[1] contains the field alias without '_number_number' postfix
					 * in case of array data converted to strings divided by "\n" the "\n" are replaced by ","
					 */
					$buffer = $buffer . $matches[1] . ": " .
								 str_replace("\n", ", ", $oldData[$key]) . " => " . str_replace("\n", ", ", $value) . "\n";
				}
			}

			$this->checkSectionAssignment($content->id, $iChangeCounter, $buffer);
			$this->checkSectionFeeAssignment($content->id, $iChangeCounter, $buffer);
		}
		else
		{
			$iChangeCounter = 1;
			$buffer = $jinput[lastname . '_0_0'] . " " . $jinput[firstname . '_0_0'];
		}

		if ($iChangeCounter > 0)
		{
			// Setup email stuff
			$sender = array($config->get('mailfrom'), $config->get('fromname'));

			if (is_array($sender) && version_compare(JVERSION,'3.5', '>=') == 1)
			{
				$mailer->addReplyTo($sender[0], $sender[1]);
				$mailer->setSender($sender[0], $sender[1]);
			}
			else
			{
				$mailer->addReplyTo($sender);
				$mailer->setSender($sender);
			}

			$recipient = $this->params->get('notification_email', '');

			if (is_array($recipient) && version_compare(JVERSION, '3.5', '>=') == 1)
			{
				$mailer->addRecipient($recipient[0], $recipient[1]);
			}
			else
			{
				$mailer->addRecipient($recipient);
			}

			setlocale(LC_CTYPE, "de_DE.UTF-8");

			/**
			 * $string = "Deutsche Umlaute Ä Ö Ü ä ö ü ß";
			 * $string = iconv('UTF-8', 'ASCII//TRANSLIT', $buffer);
			 * echo $string;  die();
			 * print $buffer;die();
			 */

			$body = JText::sprintf($isNew ? PLG_KANDANDA_USERUPDATE_MEMBER_DATA_ADDED : PLG_KANDANDA_USERUPDATE_MEMBER_DATA_CHANGED, $user->name, $user->username, $user->email, iconv("UTF-8", "ASCII//TRANSLIT", $jinput[lastname . '_0_0'] . " " . $jinput[firstname . '_0_0']), iconv("UTF-8", "ASCII//TRANSLIT", $buffer));

			$subject = JText::sprintf($isNew ? PLG_KANDANDA_USERUPDATE_SUBJECT_MEMBER_DATA_ADDED : PLG_KANDANDA_USERUPDATE_SUBJECT_MEMBER_DATA_CHANGED, $user->name, $user->username, $user->email, iconv("UTF-8", "ASCII//TRANSLIT", $jinput[lastname . '_0_0'] . " " . $jinput[firstname . '_0_0']), iconv("UTF-8", "ASCII//TRANSLIT", $buffer));

			/**
			 * print "<pre>";
			 * print_r($body);die();
			 * print "</pre>";
			 */
			$mailer->setSubject($subject);

			// Set email body while converting all characters that can't be represented in the target charset,
			// are approximated through one or several similarly looking characters.
			$mailer->setBody($body);

			$mailer->Send();
		}

		/**
		 * print("<pre>");
		 * print_r($body);
		 * print("</pre>");
		 * die();
		 */
	}

	/**
	 * This method updates the email-address of assigned Joomla user to the changed
	 * Kandanda email-address. The Kandanda field which holds the email-adress is configured via
	 * a plugin parameter which is named 'email_field'.
	 *
	 * @param   JTableContent $content A reference to the JTableContent object that is being saved which holds the article data.
	 * @return  void
	 */
	private function updateJoomlaUserData($content)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);

		// Fetch the field values which were submitted by Kandanda edit form
		$jinput = JFactory::getApplication()->input->get('jform', array(), 'array');

		// Get plugin parameter for Kandanda field which is used for storing member emails
		$mailField = $this->params->get('email_field', '');

		$query->clear();

		// Get current email address of assigned joomla user
		$query->select('email');
		$query->from('#__users');
		$query->where('id = ' .$content->user_id);

		// Limit data to one record
		$db->setQuery($query, 0, 1);
		$result = $db->loadAssoc();

		// Get value of the currently stored email field (base field is used)
		$newKandandaEmail = $jinput[$mailField . '_0_0'];

		// Get the value of the currently assigned joomla member email address
		$joomlaEmail = $result['email'];

		// Get the user id of assigned Joomla user of current Kandanda member
		$joomlaUserId = $content->user_id;

		// Check if new Kandanda email address is a valid email address
		if (!JMailHelper::isEmailAddress($newKandandaEmail))
		{
			JError::raiseNotice(100, JText::_('PLG_KANDANDA_USERUPDATE_INVALID_EMAIL'));
			return;
		}

		// Only if joomla email address and new Kandanda email address differs
		if ($newKandandaEmail != $joomlaEmail)
		{
			$query->clear();

			// Fields to update.
			$fields = array($db->quoteName('email') . '=\'' . $newKandandaEmail . '\'');

			// Conditions for which records should be updated.
			$conditions = array($db->quoteName('id') . '=' . $joomlaUserId);

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
	 * @param   integer $memberId
	 * @param   integer $iChangeCounter
	 * @param   string  $buffer
	 *
	 * @return  void
	 */
	private function checkSectionAssignment($memberId, &$iChangeCounter, &$buffer)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->clear();

		// Get configuration parameter of Kandanda component
		$kandandaParams = JComponentHelper::getParams('com_kandanda');

		// Fetch the field values which were submitted by Kandanda edit form
		$jinput = JFactory::getApplication()->input->get('jform', array(), 'array');

		// First get all sections to which the member has belonged to
		$query->select('c.id, c.title, c.alias, m.fee');
		$query->from('#__kandanda_member_category_map AS m');
		$query->leftjoin('#__categories AS c ON c.id = m.catid');
		$query->where('m.member_id = ' . $memberId);
		$query->order('c.id');
		$db->setQuery($query);
		$kandandaOldSectionData = $db->loadAssoclist();

		/**
		 * print("<pre>");
		 * print_r($kandandaOldSectionData);
		 * print("</pre>"); die();
		 */

		// If 'sections' key exists in array (only if user is allowed to change section assignments)
		if (array_key_exists('sections', $jinput))
		{
			// Get all section names, the member belongs now to
			$query->clear();
			$query->select('id,title, alias');
			$query->from('#__categories');
			$query->where('id in (' . implode(',', $jinput['sections']) . ')');
			$query->order('id');
			$db->setQuery($query);
			$kandandaNewSectionData = $db->loadAssoclist();
		}

		elseif ($kandandaParams->get('edit_club_section') == 0)
		{
			// New section assignment is equal to old section assignment
			$kandandaNewSectionData = $kandandaOldSectionData;
		}

		/** print("<pre>");
		 * print_r($kandandaNewSectionData);
		 * print("</pre>"); die();
		 */

		// Array to hold new added member sections
		$added = array();

		// Array to hold deleted member sections
		$deleted = array();

		// Array to hold changed fee assignments
		$fee = array();

		if (count($kandandaOldSectionData) > 0)
		{
			// Loop over old section data and compare it with new section data in order to detect deleted section assignments
			foreach ($kandandaOldSectionData as $old)
			{
				$found = false;

				// Loop over new section data
				foreach ($kandandaNewSectionData as $new)
				{
					// If section was already assigned in old member data
					if ($old['id'] == $new['id'])
					{
						$found = true;
						break;
					}
				}

				// If old section id was not found in new section data --> section was deleted
				if (!$found)
				{
					$deleted[] = $old['title'];
				}
			}
		}

		// Loop over new section data and compare it with old section data in order to detect added section assignments
		foreach ($kandandaNewSectionData as $new)
		{
			$found = false;

			// Store currently assigned sections in member array needed for checking section fee assignment
			$this->assignedSections[] = $new['id'];

			if (count($kandandaOldSectionData) > 0)
			{
				// Loop over old section data
				foreach ($kandandaOldSectionData as $old)
				{
					// If section was already assigned in old member data
					if ($new['id'] == $old['id'])
					{
						$found = true;
						break;
					}
				}
			}

			// If new section id was not found in old section data --> section was added
			if (!$found)
			{
				$added[] = $new['title'];
			}
		}

		$iChangeCounter += count($deleted) + count($added) + count($fee);

		foreach ($deleted as $deletedSection)
		{
			$buffer = $buffer . JText::_('PLG_KANDANDA_USERUPDATE_SECTION') . ": \"" . $deletedSection . "\" " . JText::_('PLG_KANDANDA_USERUPDATE_NOT_ASSIGNED') . "\n";
		}

		foreach ($added as $addedSection)
		{
			$buffer = $buffer . JText::_('PLG_KANDANDA_USERUPDATE_SECTION') . ": \"" . $addedSection . "\" " . JText::_('PLG_KANDANDA_USERUPDATE_ASSIGNED') . "\n";
		}
	}

	/**
	 * This method checks which section_fees assignment of a Kandanda
	 * user was changed. All changed values are returned as a string via the
	 * reference parameter $buffer.
	 *
	 * @param   integer $memberId
	 * @param   integer $iChangeCounter
	 * @param   string  $buffer
	 * @return  void
	 */
	private function checkSectionFeeAssignment($memberId, &$iChangeCounter, &$buffer)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->clear();

		// Get configuration parameter of Kandanda component
		$kandandaParams = JComponentHelper::getParams('com_kandanda');

		// Fetch the field values which were submitted by Kandanda edit form
		$jinput = JFactory::getApplication()->input->get('jform', array(), 'array');

		// First get all section fees to which the member has belonged to
		$query->select('c.id, c.title, c.alias, m.fee');
		$query->from('#__kandanda_member_category_map AS m');
		$query->leftjoin('#__categories AS c ON c.id = m.catid');
		$query->where('m.fee=1');
		$query->where('m.member_id = ' . $memberId);
		$query->order('c.id');
		$db->setQuery($query);
		$kandandaOldSectionfeeData = $db->loadAssoclist();

		/**
		 * print("<pre>");
		 * print_r($kandandaOldSectionfeeData);
		 * print("</pre>"); die();
		 */

		// If 'sections_fees' key exists in array (only if user is allowed to change section assignments)
		if (array_key_exists('section_fees', $jinput))
		{
			// Get all section fee names, the member belongs now to
			$query->clear();
			$query->select('id,title, alias');
			$query->from('#__categories');
			$query->where('id in (' . implode(',', $jinput['section_fees']) . ')');
			$query->order('id');
			$db->setQuery($query);
			$kandandaNewSectionfeeData = $db->loadAssoclist();
		}

		// If editing of section fees is not allowed -> new section fee is old section fee
		else if ($kandandaParams->get('edit_club_section_fee') == 0)
		{
			// New section assignment is equal to old section assignment
			$kandandaNewSectionfeeData = $kandandaOldSectionfeeData;
		}

		/**
		 * print("<pre>");
		 * print_r($kandandaNewSectionfeeData);
		 * print("</pre>"); die();
		 */

		// Array to hold new added member section fees
		$added = array();

		// Array to hold deleted member section fees
		$deleted = array();

		// Loop over old section fee data and compare it with new section fee data in order to detect deleted section fee assignments
		foreach ($kandandaOldSectionfeeData as $old)
		{
			$found = false;

			if (count($kandandaNewSectionfeeData) > 0)
			{
				// Loop over new section fee data
				foreach ($kandandaNewSectionfeeData as $new)
				{
					// If section fee was already assigned in old member data
					if ($old['id'] == $new['id'])
					{
						$found = true;
						break;
					}
				}
			}

			// If old section fee id was not found in new section data --> section fee was deleted
			if (!$found)
			{
				$deleted[] = $old['title'];
			}
		}

		if (count($kandandaNewSectionfeeData) > 0)
		{
			// Loop over new section fee data and compare it with old section fee data in order to detect added section fee assignments
			foreach ($kandandaNewSectionfeeData as $new)
			{
				$found = false;

				if (!in_array($new['id'], $this->assignedSections))
				{
					continue;
				}

				// Loop over old section fee data
				foreach ($kandandaOldSectionfeeData as $old)
				{
					// If section fee was already assigned in old member data
					if ($new['id'] == $old['id'])
					{
						$found = true;
						break;
					}
				}

				// If new section fee id was not found in old section data --> section fee was added
				if (!$found)
				{
					$added[] = $new['title'];
				}
			}
		}

		$iChangeCounter += count($deleted) + count($added);

		foreach ($deleted as $deletedSectionFee)
		{
			$buffer = $buffer . JText::_('PLG_KANDANDA_USERUPDATE_FEE') . ": \"" . $deletedSectionFee . "\" " . JText::_('PLG_KANDANDA_USERUPDATE_NOT_ASSIGNED') . "\n";
		}

		foreach ($added as $addedSectionFee)
		{
			$buffer = $buffer . JText::_('PLG_KANDANDA_USERUPDATE_FEE') . ": \"" . $addedSectionFee . "\" " . JText::_('PLG_KANDANDA_USERUPDATE_ASSIGNED') . "\n";
		}
	}
}
