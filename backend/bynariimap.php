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

class ASTimezone {
    private $bias;
    private $name;
    private $stddate;
    private $stdbias;
    private $dstname;
    private $dstdate;
    private $dstbias;

    function ASTimezone()
    {
    }
    function setBias($v)
    {
        $this->bias = $v;
    }

    function setStdName($v)
    {
        $this->name = $v;
    }

    function setDstBias($v)
    {
        $this->dstbias = $v;
    }

    function setStdBias($v)
    {
        $this->stdbias = $v;
    }

    function setDstName($v)
    {
        $this->dstname = $v;
    }

    function setStdDate($d, $f)
    {
       $tab = strptime($d, $f);
       $tab['tm_year']+=1900;
       $tab['tm_mon']+=1;

       $this->stddate = $tab;
    }

    function setDstDate($d, $f)
    {
       $tab = strptime($d, $f);
       $tab['tm_year']+=1900;
       $tab['tm_mon']+=1;

       $this->dstdate = $tab;
    }
    function toString() 
    {
        $ret = pack('V', $this->bias);
        /* Convert the string to UTF-16 and take only the first 32 letters */
        $str = substr(@iconv('utf-8','utf-16', $this->name), 2, 64);
        $len = strlen($str);
        $ret .= $str;
         
        if ($len < 64)
        {
            $str2 = pack("x".(64-$len));
            $ret .= $str2;
        }
        $t = $this->stddate; 
        //$ret .= pack('v8', $t["tm_year"], $t["tm_mon"], $t["tm_wday"], $t["tm_mday"], $t["tm_hour"], $t["tm_min"], $t["tm_sec"], 0);
        $ret .= pack('v8', 0, $t["tm_mon"], 0, $t["tm_mday"], $t["tm_hour"], $t["tm_min"], $t["tm_sec"], 0);
                
        $ret .= pack('V', $this->stdbias);
        /* Convert the string to UTF-16 and take only the first 32 letters */
        $str = substr(@iconv('utf-8','utf-16', $this->dstname), 2, 64);
        $len = strlen($str);
        $ret .= $str;
         
        if ($len < 64)
        {
            $str2 = pack("x".(64-$len));
            $ret .= $str2;
        }
        $t = $this->dstdate; 
        //$ret .= pack('v8', $t["tm_year"], $t["tm_mon"], $t["tm_wday"], $t["tm_mday"], $t["tm_hour"], $t["tm_min"], $t["tm_sec"], 0);
        $ret .= pack('v8', 0, $t["tm_mon"], 0, $t["tm_mday"], $t["tm_hour"], $t["tm_min"], $t["tm_sec"], 0);
                
        $ret .= pack('V', $this->dstbias);

        return $ret;
    }

    function fromString($str)
    {
        $t = unpack('V', substr($str, 0, 4));
        $this->bias = $t[1];
        $this->name = @iconv('utf-16','utf-8',substr($str, 4, 64));
        $d = unpack('v8', substr($str, 68, 16));

        $tab["tm_year"] = $d[1];
        $tab["tm_mon"]  = $d[2];
        $tab["tm_wday"] = $d[3];
        $tab["tm_mday"] = $d[4];
        $tab["tm_hour"] = $d[5];
        $tab["tm_min"]  = $d[6];
        $tab["tm_sec"]  = $d[7];
        $this->stddate =  $tab;

        $t2 = unpack('V', substr($str, 84, 4));
        $this->stdbias = $t2[1];
        $this->dstname = @iconv('utf-16','utf-8',substr($str, 88, 64));
        $d2 = unpack('v8', substr($str, 152, 16));

        $tab2["tm_year"] = $d2[1];
        $tab2["tm_mon"]  = $d2[2];
        $tab2["tm_wday"] = $d2[3];
        $tab2["tm_mday"] = $d2[4];
        $tab2["tm_hour"] = $d2[5];
        $tab2["tm_min"]  = $d2[6];
        $tab2["tm_sec"]  = $d2[7];
        $tab2["tm_msec"]  = $d2[8];
        $this->dstdate =  $tab2;

        $t3 = unpack('V', substr($str, 168, 4));
        $this->dstbias = $t3[1];
    }
};

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
            $folder->parentid = $fhir[0];
            $folder->displayname = "Calendar";
            $folder->type = SYNC_FOLDER_TYPE_APPOINTMENT;
        }
        else if($lid == "contacts") {
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
    /* This function is called when a message has been changed on the PDA. You should parse the new
    * message here and save the changes to disk. The return value must be whatever would be returned
    * from StatMessage() after the message has been saved. This means that both the 'flags' and the 'mod'
    * properties of the StatMessage() item may change via ChangeMessage().
    * Note that this function will never be called on E-mail items as you can't change e-mail items, you
    * can only set them as 'read'.
    */
    
    function ChangeMessage($folderid, $id, $message) 
    {
        $modify=false; 
        $uid = null;
        debugLog("PDA Folder : " . $folderid .  "  object uid : " . $id);
        $tabmsg = null;
        if ( $id != FALSE )
        {                                                                              
                $imap_id=$this->CacheReadUid($folderid,$id); 
                if ($imap_id == -1)
                {
                    $imap_id = $id;
                }
                $this->imap_reopenFolder($folderid);
                $tabmsg = $this->_GetRawMessage($folderid, $id, 0, 1);
                $s1 = @imap_delete ($this->_mbox, $imap_id, FT_UID);
                $s11 = @imap_setflag_full($this->_mbox, $imap_id, "\\Deleted", FT_UID);
                $s2 = @imap_expunge($this->_mbox);
                $uid = $id;
                $modify = true;
        }                                                                                
        switch($this->_getFolderType($folderid))
        {
                case SYNC_FOLDER_TYPE_APPOINTMENT:
                    $mail=$this->_setAppointmentMessage($message, $uid, $tabmsg[0]);
                    break;
                default:
                    break;
        }
        
        $this->imap_reopenFolder($folderid);
        $info = imap_status($this->_mbox, $this->_server . $folderid, SA_ALL)  ;    
        $r = @imap_append($this->_mbox,$this->_server . $folderid,$mail[2] ,"\\Seen");
        $oldid = $id;
        $id = $info->uidnext;     
        if ($r == TRUE)   
        {
            debugLog("create message : " . $folderid . " " . $id)  ;    
            $this->CacheWriteUid($folderid,$mail[0],$id);
            if (! $modify == true)
            {
                $this->CacheWriteCreated($folderid,$id);
            }
            if ( $this->_getFolderType($folderid) == SYNC_FOLDER_TYPE_APPOINTMENT)
            {
                $this->CacheWriteEndDate($folderid,$message,$id);   
            }
            $entry["mod"] = $folderid ."/".$id;
            $entry["id"]=$id;
            $entry["flags"]=0;
            return $entry;
        } 
        $this->Log("IMAP can't add mail : " . imap_last_error());
        return false;
    }

    private function _setAppointmentMessage($message, $uid, $oldmessage)
    {  
        
        $cal = new CalendarCoreObject();
        $event = new CalendarEvent();

        if (isset($oldmessage))
        {
            $body = $this->_getCalendar($oldmessage);
            debugLog($body);

            $calparser = new CalendarCoreObject();
            $calparser->parse($body);

            if (isset($calparser->organizername))
            {
                $event->setOrganizer($calparser->organizer);
            }
            if (isset($calparser->organizeremail))
            {
                $event->setOrganizerEmail($calparser->organizerEmail);
            }
        }

        if (isset($message->organizername))
        {
            $event->setOrganizer($message->organizername);
        }
        if (isset($message->organizeremail))
        {
            $event->setOrganizerEmail($message->organizeremail);
        }
        $uid = $message->uid;
        $event->setUid($message->uid);

        $dstart = new DateTime();
        $dstart->setTimezone(new DateTimeZone("UTC"));
        $dstart->setTimestamp($message->starttime);
        $event->setStart($dstart);

        $dend = new DateTime();
        $dend->setTimezone(new DateTimeZone("UTC"));
        $dend->setTimestamp($message->endtime);
        $event->setEnd($dend);
        $event->setSummary($message->subject);
        $event->setBusy(2);

        if (isset($message->body))
        {
            $event->setBody($message->body);
        }
        if ($message->alldayevent == 1)
        {
            $event->setAllDay(1);
        }
        if ($message->busystatus != "")
        {
            $event->setBusy($message->busystatus);
        }
        if (isset($message->sensivity))
        {
            switch($message->sensivity)
            {
                case 1:
                    $event->setSensivity("PUBLIC");
                    break;
                case 2:
                    $event->setSensivity("PRIVATE");
                    break;
                default:
                    $event->setSensivity("PUBLIC");
                    break;
            }
        }
        if (isset($message->attendees))
        {
            $tab = array();
            foreach($message->attendees as $att)
            {
                $iatt = array();
                $iatt['email'] = $att->email;
                if (isset($att->name))
                {
                    $iatt['name'] = $att->name;
                }
                $tab[] = $iatt;
            }
            $event->setAttendees($tab);
        }
        
        $cal->setEvent(0,$event);
        /*
        //recurence
        if(isset($message->recurrence)) 
        {
            $object["recurrence"]=$this->kolabWriteReccurence($message->reccurence);
        }
        */
        $ical = $cal->toICALString();
        // set the mail 
        // attach the XML file 
        $mail=$this->mail_attach2(NULL,0,$ical,"","text/plain", "7bit","text/calendar","utf-8","quoted-printable"); 
        //add header
        $h["from"]=$event->organizerEmail;
        $h["to"]=$event->organizerEmail;
        $h["X-Mailer"]="z-push-Bynari Backend";
        $h["subject"]= $event->summary;
        $h["message-id"]= "<" . strtoupper(md5(uniqid(time()))) . ">";
        $h["date"]=date(DATE_RFC2822);
        $header = "";
        foreach(array_keys($h) as $i)
        {
            $header= $header . $i . ": " . $h[$i] ."\r\n";
        }
        //return the mail formatted
        return array($uid,$h['date'],$header  .$mail[0]."\r\n" .$mail[1]);

    }
     // build a multipart email, embedding body and one file (for attachments)
    function mail_attach2($filenm,$filesize,$file_cont,$body, $body_ct, $body_cte,$file_ct,$file_charset=null,$encoding=null) {

        $boundary = strtoupper(md5(uniqid(time())));
        if ( $file_ct == "")
        {
            $file_ct="text/plain"  ;
        }    
        $mail_header = "Content-Type: multipart/mixed; boundary=$boundary\r\n";

        // build main body with the sumitted type & encoding from the pda
        $mail_body  = "This is a multi-part message in MIME format\r\n\r\n";
        $mail_body .= "--$boundary\r\n";
        $mail_body .= "Content-Type:$body_ct\r\n";
        if ($body_cte != "")
        {
            $mail_body .= "Content-Transfer-Encoding:$body_cte\r\n\r\n";
        }
        $mail_body .= "$body\r\n";

        $mail_body .= "--$boundary\r\n";
        $mail_body .= "Content-Type: ".$file_ct;
        if ($file_charset != null)
        {
            $mail_body .="; charset=".$file_charset;
        }
        $cd = "";
        if ($filenm != null)
        {
            $mail_body .="; name=\"$filenm\"\r\n";
            $mail_body .= "Content-Description: $filenm\r\n\r\n";
            $cd = "; filename=\"$filenm\"";
        }
        else
        {
            $mail_body .="\r\n";
        }

        $mail_body .= "Content-Transfer-Encoding: $encoding\r\n";
        $mail_body .= "Content-Disposition: attachment".$cd."\r\n\r\n";
        if ($encoding == "base64")
        {
            $mail_body .= base64_encode($file_cont) . "\r\n";
        }
        else if($encoding == "quoted-printable")
        {
            $mail_body .= quoted_printable_encode($file_cont) . "\r\n";
        }

        $mail_body .= "--$boundary--\r\n\r\n";  
        return array($mail_header, $mail_body);
    }

    function _getCalendar($message)
    {
        $this->getBodyRecursive($message, "calendar", $body);
        if (strlen($body) == 0 and isset($message->parts))
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
                            $body = @iconv($fcharset, "UTF-8//TRANSLIT", $cal);
                            if ($decodeok == 1)
                            {
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
        return $body;
    }
   /* Get the raw message if possible and return it otherwise return null */
    private function _getAppointmentMessage($message, $folderid, $id, $truncsize)
    {

        $tz = new ASTimezone;
        $tz->setBias(0);
        $tz->setStdName("UTC (GMT+0)");
        $tz->setStdDate("27/03/00 02:00:00", "%d/%m/%y %T");
        $tz->setStdBias(0);

        $tz->setDstName("UTC (GMT+0)");
        $tz->setDstDate("30/10/00 03:00:00", "%d/%m/%y %T");
        $tz->setDstBias(0);

        $tzbase64 = base64_encode($tz->toString());

        $body = "";
        $body = $this->_getCalendar($message);

        if (strlen($body) == 0)
        {
            $event=new SyncAppointment();
            $event->endtime = 1;
            $this->CacheWriteEndDate($folderid,$event,$id);   
            unset($event);
            return false;
        }
        $calparser = new CalendarCoreObject();
        $calparser->parse($body);
        $ievent = $calparser->getEvent(0);
        if (! isset($ievent))
        {
            debugLog("Strange body for message $id");
            return false;
        }

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
        $event->alldayevent= $ievent->allday;
        if (is_object($ievent->end))
        {
            $event->endtime = $ievent->end->getTimestamp();
        }
        else
        {
            $event->endtime = $ievent->start->getTimestamp()+86400;
        }
        $event->sensitivity = "0";
        if (isset($ievent->class))
        {
            if ($ievent->class == "CONFIDENTIAL")
            {
                $event->sensitivity = "3";
            }
            if ($ievent->class == "PRIVATE")
            {
                $event->sensitivity = "2";
            }
        }
        if (count($ievent->attendees))
        {
            $tab = array();
            foreach($ievent->attendees as $att)
            {
                $satt = new SyncAttendee();
                $satt->email = $att['email'];
                if (isset($att['name']))
                {
                    $satt->name = $att['name'];
                }
                $tab[] = $satt;
            }
            $event->attendees = $tab;
        }
        /* no reminder and no recurrence for the moment */
        $event->reminder=NULL;
        $event->reccurence=NULL;
        
        $event->location = $ievent->location;
    
        if (isset($ievent->busy))
        {
            $event->busystatus = $ievent->busy;
        }
        else
        {
            $event->busystatus = "2";
        }

        /*$event->bodytruncated = 0;  */
        if (isset($ievent->description))
        {
            $event->body = $ievent->description;
        }
        /* meeting status related to status but for the moment we don't really know how ...*/
        $event->meetingstatus="0";
        $event->timezone= $tzbase64;
        $event->organizername = $ievent->organizer;
        $event->organizeremail = $ievent->organizerEmail;

        //reccurence process
        //Populate cache
        $this->CacheWriteEndDate($folderid,$event,$id);   
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
            $ts = $this->CacheReadCreated($folderid, $id);
            if (time() - $ts <120)
            {
                debugLog("Skipping msg ".$id);
                /* It's a message that we created less than 2 minutes ago */
                return false;
            }
            switch($this->_getFolderType($folderid))
            {
                case SYNC_FOLDER_TYPE_APPOINTMENT:
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

    private function CacheReadCreated($folder,$id)
    {
        $this->_cache->open(BYNARIIMAP_CACHE."/".$this->_username."_".$this->_devid);
        $deffolder=$this->_cache->find("CREATED:".$folder."/".$id);
        $this->_cache->close();
        if ($deffolder == False)
        {
            $deffolder = "0";
        }
        return $deffolder;
    }

    private function CacheReadUid($folder,$uid)
    {
        $this->_cache->open(BYNARIIMAP_CACHE."/".$this->_username."_".$this->_devid);
        $deffolder=$this->_cache->find("UID:".$folder."/".$uid);
        $this->_cache->close();
        if ($deffolder == False)
        {
            $deffolder = "-1";
        }
        return $deffolder;
    }

    private function CacheReadEndDate($folder,$uid)
    {
        debugLog("CacheReadEndDate for $folder / $uid");
        $this->_cache->open(BYNARIIMAP_CACHE."/".$this->_username."_".$this->_devid);
        $deffolder=$this->_cache->find("ENDDATE:".$folder."/".$uid);
        $this->_cache->close();
        if ($deffolder == False)
        {
            $deffolder = "-1";
        }
        return $deffolder;
    }

    private function CacheWriteEndDate($folder,$event,$id)
    {
        $this->_cache->open(BYNARIIMAP_CACHE."/".$this->_username."_".$this->_devid);
        $edate=$event->endtime;
        debugLog("CacheWriteEndDate for $folder / $id = $edate");
        $this->_cache->write("ENDDATE:" . $folder."/".$id,$edate);
        $this->_cache->close();
    }

    private function CacheWriteCreated($folder,$id)
    {
        $this->_cache->open(BYNARIIMAP_CACHE."/".$this->_username."_".$this->_devid);
        $this->_cache->write("CREATED:" . $folder."/".$id,time());
        $this->_cache->close();
    }

    private function CacheWriteUid($folder,$uid,$id)
    {
        $this->_cache->open(BYNARIIMAP_CACHE."/".$this->_username."_".$this->_devid);
        $this->_cache->write("ID" . $folder."/".$uid,$id);
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
                $enddate = $this->CacheReadEndDate($folderid, $overview->uid);
                if ($enddate != -1 && $cutoffdate > $enddate)
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

                if (array_key_exists( "subject", $vars) && $vars["subject"] == "Hidden synchronization message")
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
