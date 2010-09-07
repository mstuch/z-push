<?php
/***********************************************
* File      :   imap.php
* Project   :   Z-Push
* Descr     :   This backend is based on
*               'BackendDiff' and implements an
*               IMAP interface
*
* Created   :   10.10.2007
*
* Copyright 2007 - 2010 Zarafa Deutschland GmbH
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License, version 3,
* as published by the Free Software Foundation with the following additional
* term according to sec. 7:
*
* According to sec. 7 of the GNU Affero General Public License, version 3,
* the terms of the AGPL are supplemented with the following terms:
*
* "Zarafa" is a registered trademark of Zarafa B.V.
* "Z-Push" is a registered trademark of Zarafa Deutschland GmbH
* The licensing of the Program under the AGPL does not imply a trademark license.
* Therefore any rights, title and interest in our trademarks remain entirely with us.
*
* However, if you propagate an unmodified version of the Program you are
* allowed to use the term "Z-Push" to indicate that you distribute the Program.
* Furthermore you may use our trademarks where it is necessary to indicate
* the intended purpose of a product or service provided you use it in accordance
* with honest practices in industrial or commercial matters.
* If you want to propagate modified versions of the Program under the name "Z-Push",
* you may only do so if you have a written permission by Zarafa Deutschland GmbH
* (to acquire a permission please contact Zarafa at trademark@zarafa.com).
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* Consult LICENSE file for details
************************************************/

include_once('imap.php');
include_once('icalparser.php');

// The is an improved version of mimeDecode from PEAR that correctly
// handles charsets and charset conversion
include_once('mimeDecode.php');
include_once('z_usercache.php');
require_once('z_RFC822.php');

class BackendBynariIMAP extends BackendIMAP {
    private $_cache;

    function Setup($user, $devid, $protocolversion) {
        parent::Setup($user, $devid, $protocolversion);
        $this->_cache = new userCache();
        return true;
    }

    /* Return the type of the folder given its id (name) */
    function _getFolderType($id)
    {
        /* TODO be clever ! */
        /* We could setup a hash of the main folder types so that we
           can get the type in 1 lookup
        */
        $lid = strtolower($id);

        if($lid == "inbox") {
            return SYNC_FOLDER_TYPE_INBOX;
        }
        // Zarafa IMAP-Gateway outputs
        else if($lid == "drafts") {
            return SYNC_FOLDER_TYPE_DRAFTS;
        }
        else if($lid == "trash") {
            return SYNC_FOLDER_TYPE_WASTEBASKET;
        }
        else if($lid == "calendar") {
            return SYNC_FOLDER_TYPE_APPOINTMENT;
        }
        else if($lid == "contacts") {
            return SYNC_FOLDER_TYPE_CONTACT;
        }
        else if($lid == "sent" || $lid == "sent items" || $lid == IMAP_SENTFOLDER) {
            return SYNC_FOLDER_TYPE_SENTMAIL;
        }
        // courier-imap outputs
        else if($lid == "inbox.drafts") {
            return SYNC_FOLDER_TYPE_DRAFTS;
        }
        else if($lid == "inbox.calendar") {
            return SYNC_FOLDER_TYPE_APPOINTMENT;
        }
        else if($lid == "inbox.contacts") {
            return SYNC_FOLDER_TYPE_CONTACT;
        }
        else if($lid == "inbox.trash") {
            return SYNC_FOLDER_TYPE_WASTEBASKET;
        }
        else if($lid == "inbox.trash") {
            return SYNC_FOLDER_TYPE_WASTEBASKET;
        }
        else if($lid == "inbox.sent") {
            return SYNC_FOLDER_TYPE_SENTMAIL;
        }
        // define the rest as other-folders
        else {
            return SYNC_FOLDER_TYPE_OTHER;
        }
    }



    /* GetFolder should return an actual SyncFolder object with all the properties set. Folders
     * are pretty simple really, having only a type, a name, a parent and a server ID.
     */

    function GetFolder($id) {
        $folder = new SyncFolder();
        $folder->serverid = $id;

        // explode hierarchy
          $fhir = explode(".", $id);

        // compare on lowercase strings
        $lid = strtolower($id);

        if($lid == "inbox") {
            $folder->parentid = "0"; // Root
            $folder->displayname = "Inbox";
            $folder->type = SYNC_FOLDER_TYPE_INBOX;
        }
        // Zarafa IMAP-Gateway outputs
        else if($lid == "drafts") {
            $folder->parentid = "0";
            $folder->displayname = "Drafts";
            $folder->type = SYNC_FOLDER_TYPE_DRAFTS;
        }
        else if($lid == "trash") {
            $folder->parentid = "0";
            $folder->displayname = "Trash";
            $folder->type = SYNC_FOLDER_TYPE_WASTEBASKET;
            $this->_wasteID = $id;
        }
        else if($lid == "calendar") {
            debugLog("Calendar found");
            $folder->parentid = $fhir[0];
            $folder->displayname = "Calendar";
            $folder->type = SYNC_FOLDER_TYPE_APPOINTMENT;
        }
        else if($lid == "contacts") {
            debugLog("Contacts found");
            $folder->parentid = $fhir[0];
            $folder->displayname = "Contacts";
            $folder->type = SYNC_FOLDER_TYPE_CONTACT;
        }
        else if($lid == "sent" || $lid == "sent items" || $lid == IMAP_SENTFOLDER) {
            $folder->parentid = "0";
            $folder->displayname = "Sent";
            $folder->type = SYNC_FOLDER_TYPE_SENTMAIL;
            $this->_sentID = $id;
        }
        // courier-imap outputs
        else if($lid == "inbox.drafts") {
            $folder->parentid = $fhir[0];
            $folder->displayname = "Drafts";
            $folder->type = SYNC_FOLDER_TYPE_DRAFTS;
        }
        else if($lid == "inbox.calendar") {
            debugLog("Calendar found");
            $folder->parentid = $fhir[0];
            $folder->displayname = "Calendar";
            $folder->type = SYNC_FOLDER_TYPE_APPOINTMENT;
        }
        else if($lid == "inbox.contacts") {
            $folder->parentid = $fhir[0];
            $folder->displayname = "Contacts";
            $folder->type = SYNC_FOLDER_TYPE_CONTACT;
        }
        else if($lid == "inbox.trash") {
            $folder->parentid = $fhir[0];
            $folder->displayname = "Trash";
            $folder->type = SYNC_FOLDER_TYPE_WASTEBASKET;
            $this->_wasteID = $id;
        }
        else if($lid == "inbox.trash") {
            $folder->parentid = $fhir[0];
            $folder->displayname = "Trash";
            $folder->type = SYNC_FOLDER_TYPE_WASTEBASKET;
            $this->_wasteID = $id;
        }
        else if($lid == "inbox.sent") {
            $folder->parentid = $fhir[0];
            $folder->displayname = "Sent";
            $folder->type = SYNC_FOLDER_TYPE_SENTMAIL;
            $this->_sentID = $id;
        }

        // define the rest as other-folders
        else {
               if (count($fhir) > 1) {
                   $folder->displayname = windows1252_to_utf8(imap_utf7_decode(array_pop($fhir)));
                   $folder->parentid = implode(".", $fhir);
               }
               else {
                $folder->displayname = windows1252_to_utf8(imap_utf7_decode($id));
                $folder->parentid = "0";
               }
            $folder->type = SYNC_FOLDER_TYPE_OTHER;
        }

           //advanced debugging

        return $folder;
    }

    function _quotedPrintableDecode($input)
    {
        // Remove soft line breaks
        $input = preg_replace("/=\r?\n/", '', $input);

        // Replace encoded characters
        $input = preg_replace('/=([a-f0-9]{2})/iA', "sprintf(\"%c\",hexdec('\\1'))", $input);

        return $input;
    }

    /* Get the raw message if possible and return it otherwise return null */
    private function _getAppointmentMessage($message, $folderid, $id, $truncsize)
    {
        $body = "";
        $this->getBodyRecursive($message, "calendar", $body);
        if (strlen($body) == 0)
        {
            /* Ok was not able to find a calendar stuff in the body of the message,
               let's see in the attachement
            */
            foreach($message->parts as $part)
            {
                if(isset($part->disposition) && ($part->disposition == "attachment" || $part->disposition == "inline"))
                {
                    $type = $part->headers;
                    //kolab contact attachment ? 
                    $ctype=explode(";",$type["content-type"]);
                    if ($ctype[0] == "text/calendar")
                    {
                        $encoding = $type["content-transfer-encoding"];
                        $input = $part->body;
                        // Now we need to decode it 
                        global $decodeok;
                        $decodeok = 1;
                        $tmperrhandler = function($errno, $errstr, $errfile, $errline, $errcontext) 
                                                 {
                                                     global $decodeok;
                                                     $decodeok = 0;
                                                     debugLog('BYNARIIMAP-_getAppointmentMessage: Wrong encoding '.$decodeok);
                                                 };
                        set_error_handler($tmperrhandler);
                        switch (strtolower($encoding)) {
                            case 'quoted-printable':
                                $cal = $this->_quotedPrintableDecode($input);
                                break;
                            case 'base64':
                                $cal = base64_decode($input);
                                break;
                            default:
                                $cal = $input;
                                break;
                        }
                        $charsets = array();
                        $charsets[] = isset($type['charset']) ? $type['charset'] : 'utf-8';
                        $charsets[] = "ISO-8859-1";
                        foreach($charsets as $fcharset)
                        {
                            debugLog("Charset = $fcharset");
                            $body = @iconv($fcharset, "UTF-8//TRANSLIT", $cal);
                            if ($decodeok == 1)
                            {
                                /*
                                debugLog("Value of body");
                                debugLog($body);
                                */
                                break;
                            }
                        }
                        restore_error_handler();
                        // if a calendar was found exit the loop.
                        break;
                    }
                }
            }
        }
        $calparser = new CalendarCoreObject();
        $calparser->parse($body);
        $ievent = $calparser->getEvent(0);

        // Get flags, etc
        $event=new SyncAppointment();
        /*if (preg_match('/^1345302537E00074C5B7101A82E008/', $ievent->uid)
        {
           $event-> base64_encode($ievent->uid); 
        }
        */
        $event->uid = $ievent->uid;
        $event->dtstamp = time();
        $event->subject = $ievent->summary;
        $event->starttime = $ievent->start->getTimestamp();
        if(is_object($ievent->start))
        {
            debugLog($ievent->start->format('Y-m-d H:i:s'));
        }
        $event->endtime = $ievent->end->getTimestamp();
        /* Fixed sensivity so far */
        $event->sensitivity="0";
        /* no reminder */
        $event->reminder=NULL;
        
        $event->location = $ievent->location;
        $event->busystatus = "2";
        $event->body = $ievent->body;
        //sensitivity  
        $event->meetingstatus="0";
        $event->alldayevent="0";
        //timezone must be fixed
        $event->timezone="xP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAoAAAAFAAMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAFAAIAAAAAAAAAxP///w==" ;
        $event->bodytruncated = 0;  
        $event->organizername = $ievent->organizer;
        $event->organizeremail = $ievent->organizerEmail;

        //reccurence process
        return $event;
    }

    /* GetMessage should return the actual SyncXXX object type. You may or may not use the '$folderid' parent folder
     * identifier here.
     * Note that mixing item types is illegal and will be blocked by the engine; ie returning an Email object in a
     * Tasks folder will not do anything. The SyncXXX objects should be filled with as much information as possible,
     * but at least the subject, body, to, from, etc.
     */
    function GetMessage($folderid, $id, $truncsize, $mimesupport = 0) {
        debugLog("IMAP-GetMessage: (fid: '$folderid'  id: '$id'  truncsize: $truncsize)");

        $tab = $this->_GetRawMessage($folderid, $id, $truncsize, $mimesupport);
        if ($tab[0])
        {
            debugLog("ici");
            switch($this->_getFolderType($folderid))
            {
                case SYNC_FOLDER_TYPE_APPOINTMENT:
                    debugLog('coin');
                    return $this->_getAppointmentMessage($tab[0], $folderid, $id, $truncsize);
                    break;
                default:
                    return $this->_GetEmailMessage($tab[0], $folderid, $id, $truncsize, $tab[1]);
                    break;
            }
        }
        return false;
    }

    function GetMessageList($folderid, $cutoffdate) {
        debugLog("BYNARIIMAP-GetMessageList: (fid: '$folderid'  cutdate: '$cutoffdate' )");

        $messages = array();
        $this->imap_reopenFolder($folderid, true);

        switch($this->_getFolderType($folderid))
        {
            case SYNC_FOLDER_TYPE_APPOINTMENT:
                return $this->_getMessageListAppointment($folderid, $cutoffdate);
                break;

            default: 
                return $this->_getMessageListOther($cutoffdate);
                break;
        }
    }
 
    private function CacheReadEndDate($folder,$uid)
    {
        $this->_cache->open(BYNARIIMAP_CACHE."/".$this->_username."_".$this->_devid);
        $deffolder=$this->_cache->find("ENDDATE:".$folder."/".$uid);
        $this->_cache->close();
        if ($deffolder == False)
        {
            $deffolder = "-1";
        }
        return $deffolder;
    }

    private function CacheWriteEndDate($folder,$event)
    {
        $uid=strtoupper(bin2hex($event->uid));
        $this->_cache->open(KOLAB_INDEX."/".$this->_username."_".$this->_devid);
        $edate=$event->endtime;
        $this->_cache->write("ENDDATE:" . $folder."/".$uid,$edate);
        $this->_cache->close();
    }

    function _getMessageListAppointment($folderid, $cutoffdate)
    {
        $messages = array();
        $sequence = "1:*";
        $overviews = @imap_fetch_overview($this->_mbox, $sequence);
        if (!$overviews) {
            debugLog("BYNARIIMAP-GetMessageListAppointment: Failed to retrieve overview");
        } else {
            foreach($overviews as $overview) {
                $date = "";
                $vars = get_object_vars($overview);
                 // cut of deleted messages
                if (array_key_exists( "deleted", $vars) && $overview->deleted)
                    continue;
                $date = $overview->date;
    
                debugLog(" Msg: ".$overview->uid." date $date");
                $endate = $this->CacheReadEndDate($folderid, $overview->uid);

                if ($endate != -1 && $cutoffdate > $enddate)
                {
                    debugLog("Message: ".$overview->uid." not included endate < cutoff");
                    continue;
                }
                if (array_key_exists( "uid", $vars)) {
                    $message = array();
                    $message["mod"] = $date;
                    $message["id"] = $overview->uid;
                    // Flag we don't care
                    $message["flags"] = 0;

                    array_push($messages, $message);
                }
            }
       }
       return $messages;
   }

    function _getMessageListOther($cutoffdate)
    {
        $sequence = "1:*";
        $messages = array();
        if ($cutoffdate > 0) {
            $search = @imap_search($this->_mbox, "SINCE ". date("d-M-Y", $cutoffdate));
            if ($search !== false)
                $sequence = implode(",", $search);
        }
        $overviews = @imap_fetch_overview($this->_mbox, $sequence);

        if (!$overviews) {
            debugLog("IMAP-GetMessageList: Failed to retrieve overview");
        } else {
            foreach($overviews as $overview) {
                $date = "";
                $vars = get_object_vars($overview);
                if (array_key_exists( "date", $vars)) {
                    // message is out of range for cutoffdate, ignore it
                    if(strtotime($overview->date) < $cutoffdate) continue;
                    $date = $overview->date;
                }

                // cut of deleted messages
                if (array_key_exists( "deleted", $vars) && $overview->deleted)
                    continue;

                if (array_key_exists( "uid", $vars)) {
                    $message = array();
                    $message["mod"] = $date;
                    $message["id"] = $overview->uid;
                    // 'seen' aka 'read' is the only flag we want to know about
                    $message["flags"] = 0;

                    if(array_key_exists( "seen", $vars) && $overview->seen)
                        $message["flags"] = 1;

                    array_push($messages, $message);
                }
            }
        }
        return $messages;
    }
};

?>
