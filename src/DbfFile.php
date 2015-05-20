<?php

namespace muka\ShapeReader;

class DbfFile {

    private $filename;
    private $data;
    private $record_number = 0;
    private $dbf;
    private $options;

    public function __construct($shpFilename, $options = array()) {
        $this->filename = $this->getFilename($shpFilename);
        $this->options = $options;
    }

    public function getFilename($filename) {

        if($this->filename) {
            return $this->filename;
        }

        if (!strstr($filename, ".")) {
            $filename .= ".dbf";
        }

        if (substr($filename, strlen($filename) - 3, 3) != "dbf") {
            $filename = substr($filename, 0, strlen($filename) - 3) . "dbf";
        }

        return $filename;
    }

    public function getData($record_number) {

        $this->record_number = $record_number;
        $this->load();

        return $this->data;
    }

    public function setData(array $row) {

        $this->open(true);
        unset($row["deleted"]);

        if (!dbase_replace_record($this->dbf, array_values($row), $this->record_number)) {
            throw new Exception\DbfFileException("Error writing data to file.");
        } else {
            $this->data = $row;
        }

        $this->close();
    }

    private function open($check_writeable = false) {
        $check_function = $check_writeable ? "is_writable" : "is_readable";
        if ($check_function($this->filename)) {
            $this->dbf = dbase_open($this->filename, ($check_writeable ? 2 : 0));
            if (!$this->dbf) {
                throw new Exception\DbfFileException(sprintf("Error loading %s", $this->filename));
            }
        } else {
            throw new Exception\DbfFileException(sprintf("File doesn't exists (%s)", $this->filename));
        }
    }

    public function __destruct() {
        $this->close();
    }

    private function close() {
        if ($this->dbf) {
            dbase_close($this->dbf);
            $this->dbf = null;
        }
    }

    private function load() {
        $this->open();
        $this->data = dbase_get_record_with_names($this->dbf, $this->record_number);
        if(!isset($this->options['normalize'])
                || (isset($this->options['normalize']) && $this->options['normalize'])) {
            $this->normalize();
        }
        $this->close();
    }

    private function normalize() {
        foreach($this->data as $key => &$value) {
            $value = trim(utf8_encode($value));
        }
    }

}
