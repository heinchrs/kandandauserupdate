<?php
/**
 * CHANGELOG
 *
 * This is the changelog for Kandandauserupdate.<br>
 * <b>Please</b> be patient =;)
 *
 * @package    Kandandauserupdate
 * @subpackage Documentation
 * @author     Heinl Christian
 * @author     Created on 13-Nov-2013
 */

//--No direct access to this changelog...
defined('_JEXEC') || die('=;)');

//--For phpDocumentor documentation we need to construct a function ;)
/**
 * CHANGELOG
 * {@source}
 */
function CHANGELOG()
{
/*
_______________________________________________
_______________________________________________

This is the changelog for Kandandauserupdate
_______________________________________________
_______________________________________________

Legend:

 * -> Security Fix
 # -> Bug Fix
 + -> Addition
 ^ -> Change
 - -> Removed
 ! -> Note
______________________________________________

Version 1.7
27-Apr-2016 Heinl Christian
 ^ Readiness for most recent JMail/PHPMailer version in Joomla 3.5.1

Version 1.6
14-Oct-2015 Heinl Christian
 # Duplicatable groups were not correctly checked for changes. So this elements
   were always marked as changed in notification mails.

Version 1.5
01-Jun-2015 Heinl Christian
 + Added new plugin parameter in order to disable notification mails when editing member data.
 
Version 1.4
01-Jun-2015 Heinl Christian
 # Userid for updating joomla user email address is now taken from variable content submitted in method onContentAfterSave

Version 1.3
31-May-2015 Heinl Christian
 # Update of joomla user email address is only done if corresponding userid has a valid format

Version 1.2
23-Jan-2015 Heinl Christian
 # Fixed wrong SQL statement when user is changing his own member data
   while he is not allowed to change section and section fee assignment

Version 1.1
13-Aug-2014 Heinl Christian
 + Sending of email notifications about new or changed mebmer data

Version 1.0
13-Nov-2013 Heinl Christian
 ! Startup

*/
}//--This is the END
