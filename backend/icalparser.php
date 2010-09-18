<?php
/***********************************************
* File      :   bynariparser.php
* Project   :   Z-Push
* Descr     :   Parser for bynari object
*
* Created   :   30.08.2010
*
* Copyright  2010 Matthieu Patou mat@matws.net
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License, version 3,
* as published by the Free Software Foundation with the following additional
* term according to sec. 7:
*
* According to sec. 7 of the GNU Affero General Public License, version 3,
* the terms of the AGPL are supplemented with the following terms:
*
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


class BaseCalendarObject {
    var $buf;

    function feed($text) {
        $this->buf[] = $text;
    }

    function parse() {
    }
};

class CalendarEvent extends BaseCalendarObject{
    var $uid;
    var $summary;
    var $body;
    var $start;
    var $end;
    var $organizer;
    var $organizerEmail;
    var $location;
    var $_timezones;
    var $busy;
    var $attendees;
    var $allday;
    var $sensivity;


    function CalendarEvent($tzlist=NULL) {
        $this->_timezones = $tzlist;
        $this->buf = array();
        $this->attendees = array();
        $this->allday = 0;
    }

    function setBusy($val)
    {
        $this->busy = $val;
    }

    function setUid($val)
    {
        $this->uid = $val;
    }

    function setSummary($val)
    {
        $this->summary = $val;
    }

    function setBody($val)
    {
        $this->body = $val;
    }

    function setstart($val)
    {
        $this->start = $val;
    }

    function setEnd($val)
    {
        $this->end = $val;
    }

    function setOrganizer($val)
    {
        $this->organizer = $val;
    }

    function setOrganizerEmail($val)
    {
        $this->organizerEmail = $val;
    }

    function setLocation($val)
    {
        $this->location = $val;
    }

    function setAllDay($val)
    {
        $this->allday = $val;
    }

    function setAttendees($val)
    {
        $this->attendees = $val;
    }

    function setSensivity($val)
    {
        $this->sensivity = $val;
    }

    function toICALString()
    {

        $out = "BEGIN:VEVENT\r\n";
        $cn = "";
        if (isset($this->organizer))
        {
            $cn = ";CN=".$this->organizer;
        }
        $out .= "ORANIZER".$cn.":MAILTO:".$this->organizerEmail."\r\n";
        foreach($this->attendees as $att)
        {
            $cn = "";
            if (isset($att['name']))
            {
                $cn = ";CN=".$att['name'];
            }
            $out .= "ATTENDEE".$cn.":MAILTO:".$att['email']."\r\n";
        }
        $out .= "UID:".$this->uid."\r\n";
        if (isset($this->sensitivity))
        {
            $out .= "CLASS:".$this->sensitivity."\r\n";
        }
        $out .= "SUMMARY:".$this->summary."\r\n";
        if (isset($this->location))
        {
            $out .= "LOCATION:".$this->location."\r\n";
        }
        if (isset($this->description))
        {
            $out .= "DESCRIPTION:".$this->description."\r\n";
        }
        if ($this->allday)
        {
            $out .= "DTSTART;VALUE=DATE:".$this->start->format("Ymd")."\r\n";
            if (($this->end - $this->start) > 86400)
            {
                $out .= "DTEND;VALUE=DATE:".$this->end->format("Ymd")."\r\n";
            }
        }
        else
        {
            $out .= "DTSTART:".$this->start->format("Ymd\THis\Z")."\r\n";
            $out .= "DTEND:".$this->end->format("Ymd\THis\Z")."\r\n";
        }
        $date = new DateTime();
        $date->setTimestamp(time());
        $out .= "DTSTAMP:".$date->format("Ymd\THis\Z")."\r\n";
        $out .= "LAST-MODIFIED:".$date->format("Ymd\THis\Z")."\r\n";
        $out .= "CREATED:".$date->format("Ymd\THis\Z")."\r\n";

        if (isset($this->busy))
        {
            $out .= "X-OUTLOOK-BUSY-STATUS;VALUE=INTEGER:".$this->busy."\r\n";
        }
        $out .= "END:VEVENT\r\n";
        return $out;
    }

    function parse() {
        $prev = null;
        $inSubObj=0;
        foreach($this->buf as $line)
        {
            if (preg_match('/^ (.+)/', $line, $matches))
            {
                $prev .= $matches[1];
            }
            else
            {
                if (preg_match("/^END:(.*)/", $line, $matches) and $inSubObj)
                {
                    $inSubObj--;
                }
                else if (preg_match("/^BEGIN:(.*)/", $line, $matches))
                {
                    $inSubObj++;
                }
                else if (preg_match('/^UID:(.+)/', $prev, $matches))
                {
                    $this->uid = $matches[1];
                }
                else if (preg_match('/^SUMMARY:(.+)/', $prev, $matches))
                {
                    $this->summary = $matches[1];
                }
                else if (preg_match('/^ORGANIZER(;[^:]+)?:(?:(?:MAILTO)|(?:mailto))?:?([^:]+)/', $prev, $matches))
                {
                    $this->organizerEmail = $matches[2];
                    if (preg_match('/CN=((?:[^;]|((?<=\\\\);))+)/', $matches[1], $matches2))
                    {
                        $this->organizer = $matches2[1];
                    }
                }
                else if (preg_match('/^ATTENDEE(;[^:]+)?:(?:(?:MAILTO)|(?:mailto))?:?([^:]+)/', $prev, $matches))
                {
                    $tab = array();
                    $tab['email'] = $matches[2];
                    if (preg_match('/CN=((?:[^;]|((?<=\\\\);))+)/', $matches[1], $matches2))
                    {
                        $tab['name'] = $matches2[1];
                    }
                    $this->attendees[] = $tab;
                }
                else if (preg_match('/^LOCATION:(.+)/', $prev, $matches))
                {
                    $this->location = $matches[1];
                }
                else if (preg_match('/^CLASS:(.+)/', $prev, $matches))
                {
                    $this->sensivity = $matches[1];
                }
                else if (preg_match('/^X-OUTLOOK-BUSY-STATUS(?:;[^:]+)?:(.+)/', $prev, $matches))
                {
                    $this->busy = $matches[1];
                }
                else if (preg_match('/^DTSTART(?:(?:;TZID=)([^;]+))?(?:[^:]+)?:(.+)/', $prev, $matches))
                {
                    $start_tz = $matches[1];
                    $t = strptime($matches[2], "%Y%m%dT%H%M%S");
                    if ($t == "")
                    {
                        $this->allday = 1;
                        $t = strptime($matches[2], "%Y%m%d");
                    }
                    if (isset($start_tz) && $start_tz != "")
                    {
                        $tz = $this->_timezones[$start_tz];
                        $this->start = $tz->getlocaldate($t);
                    }
                    else
                    {
                        $date = sprintf("%04d%02d%02d%02d%02d%02d",
                            $t['tm_year'] + 1900, $t['tm_mon'] + 1,
                            $t['tm_mday'], $t['tm_hour'],
                            $t['tm_min'], $t['tm_sec']);
                        $this->start = date_create($date, new DateTimeZone("UTC"));
                    }
                }
                else if (preg_match('/^DTEND(?:(?:;TZID=)([^;]+))?(?:[^:]+)?:(.+)/', $prev, $matches))
                {
                    $end_tz = $matches[1];
                    $t = strptime($matches[2], "%Y%m%dT%H%M%S");
                    if ($t == "")
                    {
                        $t = strptime($matches[2], "%Y%m%d");
                    }
                    if (isset($end_tz) && $end_tz != "")
                    {
                        $tz = $this->_timezones[$end_tz];
                        $this->end = $tz->getlocaldate($t);
                    }
                    else
                    {
                        $date = sprintf("%04d%02d%02d%02d%02d%02d",
                            $t['tm_year'] + 1900, $t['tm_mon'] + 1,
                            $t['tm_mday'], $t['tm_hour'],
                            $t['tm_min'], $t['tm_sec']);
                        $this->end = date_create($date, new DateTimeZone("UTC"));
                    }
                }
                else if (preg_match('/^DESCRIPTION:(.+)/', $prev, $matches))
                {
                    $this->description = $matches[1];
                }
                $prev = $line;
            }
        }
    }
};

class CalendarTzPeriod extends BaseCalendarObject{
    var $day;
    var $month;
    var $notbefore;
    var $deltaHour;
    var $deltaMinutes;
    var $type;
    var $_dateModifier;


    var $_abrToDay = array("SU" => "Sunday", "MO" => "Monday", "TU" => "Tuesday", "WE" => "Wednesday", "TH" => "Thursday", "FR" => "Friday", "SA" => "SATURDAY");
    function CalendarTzPeriod($type) {
        $this->type = strtolower($type);
        $this->buf = array();
    }

    function parse() {
        foreach($this->buf as $line)
        {
            if (preg_match('/^TZOFFSETTO:(.)(\d\d)(\d\d)/', $line, $matches))
            {
                if ($matches[1] == '-')
                {
                    $this->sign = $matches[1];
                }
                else
                {
                    $this->sign = "+";
                }
                $this->deltaHour = $matches[2];
                $this->deltaMinutes = $matches[3];
            }
            else if (preg_match('/^DTSTART:(.+)/', $line, $matches))
            {
                $this->notbefore = strptime($matches[1], "%Y%m%dT%H%M%S");
            }
            else if (preg_match('/^RRULE:FREQ=([^;]+);(.+)/', $line, $matches))
            {
                $tab = explode(";", $matches[2]);
                if ($matches[1] == "YEARLY")
                {
                    $hash = array();
                    foreach($tab as $l)
                    {
                        $t = explode("=", $l);
                        $hash[$t[0]] = $t[1];
                    }
                    $this->day = 1;
                    $this->month = $hash["BYMONTH"];
                    if (preg_match('/([-+])?(\d)(..)/', $hash["BYDAY"], $matches))
                    {
                        if ($matches[1] == "-")
                            $this->month++;

                        if ($this->month >12)
                            $this->month = 1;

                        $this->_dateModifier = "$matches[1]$matches[2] ".$this->_abrToDay[$matches[3]];
                    }
                }
            }
        }
    }

    function getdateinlocaletime($dateutc)
    {
        $sign = "-";
        if ($this->sign == "-")
        {
            $sign = "+";
        }
        return date_modify($dateutc, $sign.$this->deltaHour." hour ".$sign.$this->deltaMinutes." minute");
    }

    function getdelta()
    {
        return $this->sign.$this->deltaHour.$this->deltaMinutes;
    }

    function getCutDate($year)
    {
        $date = new DateTime("$year-".$this->month."-".$this->day);
        $date->modify($this->_dateModifier);
        return $date;
    }
};

class CalendarTz extends BaseCalendarObject{
    var $_period;
    var $standard;
    var $daylight;
    var $_current;
    var $_current_name;
    var $name;

    function toICALString()
    {
        /* Not implemented */
        return "";
    }

    function CalendarTz() {
    }

    function getlocaldate($date)
    {
        $datedl = date_format($this->daylight->getCutDate(1900 + $date['tm_year']), "Ymdhis");
        $datest = date_format($this->standard->getCutDate(1900 + $date['tm_year']), "Ymdhis");;
        # By default for a given year we concider that the day for the daylight saving is smaller
        # (==before) the day of standard

        $lowest = $datedl;
        $highest = $datest;
        $daylightBeforeStandard = 1;

        if ($datedl > $datest)
        {
            $daylightBeforeStandard = 0;
            $lowest = $datest;
            $highest = $datedl;
        }

        $t = sprintf("%04d%02d%02d%02d%02d%02d",
                        $date['tm_year'] + 1900, $date['tm_mon'] + 1,
                        $date['tm_mday'], $date['tm_hour'],
                        $date['tm_min'], $date['tm_sec']);

        $dateobj = date_create($t, new DateTimeZone("UTC"));

        if ($t <= $lowest || $t >= $highest)
        {
            # Before the lowest so still in the previous period (standard or daylight according to daylightBeforeStandard)
            # After the highest so in the last period (standard or daylight according to daylightBeforeStandard)
            if ($daylightBeforeStandard)
            {
                return $this->standard->getdateinlocaletime($dateobj);
            }
            else
            {
                return $this->daylight->getdateinlocaletime($dateobj);
            }
        }
        else
        {
            if (!$daylightBeforeStandard)
            {
                return $this->standard->getdateinlocaletime($dateobj);
            }
            else
            {
                return $this->daylight->getdateinlocaletime($dateobj);
            }
        }
    }

    function parse() {
        $in_obj = 0;
        foreach($this->buf as $line)
        {
            if (preg_match('/^END:(.+)/i', $line, $matches))
            {
                if ($this->_current_name == $matches[1])
                {
                    $in_obj = 0;
                    $this->_current->feed($line);
                    $this->_current->parse();
                }
            }
            else if (preg_match("/^BEGIN:(DAYLIGHT)/", $line, $matches))
            {
                $in_obj = 1;
                $this->daylight = new CalendarTzPeriod("daylight");
                $this->_current = $this->daylight;
                $this->_current_name = $matches[1];
            }
            else if (preg_match("/^TZID:(.*)/", $line, $matches))
            {
                $this->name = $matches[1];
            }
            else if (preg_match("/^BEGIN:(STANDARD)/", $line, $matches))
            {
                $in_obj = 1;
                $this->standard = new CalendarTzPeriod("standard");
                $this->_current = $this->standard;
                $this->_current_name = $matches[1];
            }
            else if ($in_obj)
            {
                $this->_current->feed($line);
            }
        }
    }

};

class CalendarCoreObject {

    var $_objs;
    var $_nb_objs;
    var $_timezones;
    var $_events;

    function CalendarCoreObject()
    {
        $this->_nb_objs = 0;
        $this->_events = array();
        $this->_timezones = array();
    }

    function getEvent($nb)
    {
        if( isset($this->_events[$nb]))
        {
            return $this->_events[$nb];
        }
        return null;
    }

    function setEvent($nb, $event)
    {
        $this->_events[$nb] = $event;
    }
    /* GetFolder should return an actual SyncFolder object with all the properties set. Folders
     * are pretty simple really, having only a type, a name, a parent and a server ID.
     */
    function toICALString()
    {

        $out = "BEGIN:VCALENDAR\r\n";
        foreach($this->_timezones as $tz)
        {
            $out .= $tz->toICALString();
        }
        foreach($this->_events as $event)
        {
            $out .= $event->toICALString();
        }
        $out .= "END:VCALENDAR\r\n";

        return $out;
    }

    function parse($text) {
        $tab = explode("\n", $text);

        if (!preg_match("/^BEGIN:VCALENDAR/", rtrim($tab[0], "\r")))
        {
            return false;
        }
        next($tab);
        $in_obj = 0;
        $type = "";
        $this->_nb_objs = 0;
        $this->_events = array();

        foreach($tab as $line)
        {
            $line = rtrim($line, "\r");
            if (preg_match('/^END:(.+)(\r)?/i', $line, $matches))
            {
                $this->_objs[$this->_nb_objs - 1]->feed($line);
                if ($type == $matches[1])
                {
                    $this->_objs[$this->_nb_objs - 1]->parse();
                    $obj = $this->_objs[$this->_nb_objs - 1];
                    if (is_a($obj, "CalendarEvent"))
                    {
                        $this->_events[] = $obj;
                    }
                    if (is_a($obj, "CalendarTz"))
                    {
                        $this->_timezones[$obj->name] = $obj;
                    }
                    $in_obj = 0;
                }
            }
            else if (preg_match('/^BEGIN:(.+)(\r)?/i', $line, $matches) and $in_obj == 0)
            {
                $in_obj = 1;
                $this->_nb_objs++;
                $type = $matches[1];
                if ($matches[1] == "VCALENDAR")
                {
                    $this->_nb_objs--;
                    $in_obj = 0;
                }
                else if ($matches[1] == "VTIMEZONE")
                {
                    $this->_objs[] = new CalendarTz();
                }
                else if ($matches[1] == "VEVENT")
                {
                    $this->_objs[] = new CalendarEvent($this->_timezones);
                }
                else
                {
                    /* Don't know this object */
                    $this->_objs[] = new BaseCalendarObject();
                }
            }
            else if ($in_obj)
            {
                $this->_objs[$this->_nb_objs - 1]->feed($line);
            }
        }
    }
};

?>
