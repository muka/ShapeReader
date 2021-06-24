<?php

if (!function_exists('dbase_open')) {
    function dbase_open($file)
    {

        //echo $file . "\n";
        $dbfname = $file;
        $fdbf = fopen($dbfname, 'r');
        $fields = array();
        $buf = fread($fdbf, 32);
        $header = unpack("VRecordCount/vFirstRecord/vRecordLength", substr($buf, 4, 8));
        //echo 'Header: ' . json_encode($header) . '<br/>';
        $goon = true;
        $unpackString = '';
        while ($goon && !feof($fdbf)) {
            // read fields:
            $buf = fread($fdbf, 32);
            if (substr($buf, 0, 1) == chr(13)) {$goon = false;} // end of field list
            else {
                $field = unpack("a11fieldname/A1fieldtype/Voffset/Cfieldlen/Cfielddec", substr($buf, 0, 18));
                //echo 'Field: ' . json_encode($field) . '<br/>';
                $unpackString .= "A$field[fieldlen]$field[fieldname]/";
                array_push($fields, $field);}}
        fseek($fdbf, $header['FirstRecord'] + 1); // move back to the start of the first record (after the field definitions)

        $records = array();
        for ($i = 1; $i <= $header['RecordCount']; $i++) {
            $buf = fread($fdbf, $header['RecordLength']);
            $record = unpack($unpackString, $buf);
            $records[] = unpack($unpackString, $buf);
            //echo 'record: '+$i+':' . json_encode($record) . '<br/>';
            //echo $i . $buf . '<br/>';
        } //raw record
        return array($fdbf, $fields, $records);
    }

    function dbase_get_record_with_names($dbf, $record)
    {

        //print_r(array_slice(debug_backtrace(),0,3));
        //echo 'GET: '.$record.'=>'. print_r($dbf[2][$record-1],true)."<br/>";
        return $dbf[2][$record - 1];

    }
    function dbase_close($dbf)
    {
        fclose($dbf[0]);
    }
}
