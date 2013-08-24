<?php

namespace muka\ShapeReader;

class ShapeFile {

    private $filename;
    private $fp;
    private $fpos = 100;
    private $shp_type = 0;
    private $options;
    private $bbox = array();
    private $point_count = 0;

    public $XY_POINT_RECORD_LENGTH = 16;

    protected $data;

    public function __construct($filename, $options = array()) {

        $this->filename = $filename;

        $this->fopen();
        $this->readConfig();
        $this->options = $options;
    }

    public function __destruct() {
        $this->closeFile();
    }

    private function closeFile() {
        if ($this->fp) {
            fclose($this->fp);
        }
    }

    private function fopen() {
        if (!is_readable($this->filename)) {
            throw new Exception\ShapeFileException(sprintf("%s is not readable.", $this->filename));
        }
        $this->fp = fopen($this->filename, "rb");
    }

    private function readConfig() {
        fseek($this->fp, 32, SEEK_SET);
        $this->shp_type = $this->readAndUnpack("i", fread($this->fp, 4));
        $this->bbox = $this->readBoundingBox($this->fp);
    }

    public function getNext() {

        if (!feof($this->fp)) {

            fseek($this->fp, $this->fpos);
            $record = new ShapeRecord($this->fp, $this->filename, $this->options);
            $this->fpos = $record->getNextRecordPosition();

            return $record;
        }

        return false;
    }

    protected function readBoundingBox(&$fp) {

        $data = array();
        $data["xmin"] = $this->readAndUnpack("d", fread($fp, 8));
        $data["ymin"] = $this->readAndUnpack("d", fread($fp, 8));
        $data["xmax"] = $this->readAndUnpack("d", fread($fp, 8));
        $data["ymax"] = $this->readAndUnpack("d", fread($fp, 8));

        return $data;
    }

    protected function readAndUnpack($type, $data) {

        if (!$data) {
            return $data;
        }

        return current(unpack($type, $data));
    }

}