<?php
    /*
    Kolab Z-Push Backend

    Copyright (C) 2009-2010 Free Software Foundation Europe e.V.

    The main author of the Kolab Z-Push Backend is Alain Abbas, with
    contributions by .......

    This program is Free Software; you can redistribute it and/or
    modify it under the terms of version two of the GNU General Public
    License as published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful, but
    WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
    General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
    02110-1301, USA.

    The licensor of the Kolab Z-Push Backend is the 
    Free Software Foundation Europe (FSFE), Fiduciary Program, 
    Linienstr. 141, 10115 Berlin, Germany, email:ftf@fsfeurope.org.
    */                                                                    
    class userCache {
        private $_filename;
        private $_id;
        public $_lastError;
        function open($filename)
        {
            $this->_id = dba_open ($filename.".cache", "cl");

            if (!$this->_id) {
                $this->_lastError = "failed to open $filename";
                return false;
            }
            $this->_filename=$filename;
            return true;
        }
        function close()
        {
            dba_close($this->_id);
        }
        function write($key,$value)
        {
            $oldvalue=dba_fetch($key, $this->_id);
            if ( $oldvalue == $value)
            {
                //the key already exist and the value is the same we do nothing
                return 1;
            }
            if ($oldvalue) 
            {
                //the key exist but the value change
                dba_delete($key,$this->_id);
            }
            return dba_insert($key,$value, $this->_id);

        }
        function delete($key)
        {
            if (dba_exists ($key, $this->_id)) {
                return dba_delete ($key, $this->_id);
            }
            return 1;
        }
        function purge()
        {

            unlink($this->_filename."cache");

        }
        function find($key)
        {
            return dba_fetch($key,$this->_id);
        }


    };
?>
