<?php

namespace muka\ShapeReader;

class ShapeFile {

    protected $filename;
    protected $fp;
    protected $fpos = 100;
    protected $data;
    protected $shp_type = 0;
    protected $options;
    protected $bbox = array();
    protected $point_count = 0;
    protected $record_number;
    protected $content_length;
    protected $record_shape_type;
    protected $record_class = array(
      0 => "RecordNull",
      1 => "RecordPoint",
      8 => "RecordMultiPoint",
      3 => "RecordPolyLine",
      5 => "RecordPolygon"
    );
    protected $XY_POINT_RECORD_LENGTH = 16;

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

    private function getNextRecordPosition() {
        $nextRecordPosition = $this->fpos + ((4 + $this->content_length ) * 2);
        return $nextRecordPosition;
    }

    private function readBoundingBox(&$fp) {

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

    private function getRecordClass() {

        if (!isset($this->record_class[$this->record_shape_type])) {
            throw new Exception\ShapeFileException(sprintf("Record type %s not supported.", $this->record_shape_type));
        }

        if (!method_exists($this, "read" . $this->record_class[$this->record_shape_type])) {
            throw new Exception\ShapeFileException(sprintf("Record type %s not implemented.", $this->record_shape_type));
        }

        return $this->record_class[$this->record_shape_type];
    }

    public function getData() {

        if (!$this->data) {
            $function_name = "read" . $this->getRecordClass();
            $this->data = $this->{$function_name}($this->fp, $this->options);
        }

        return $this->data;
    }

    /**
     * Reading functions
     */
    protected function readRecordNull(&$fp, $read_shape_type = false, $options = null) {
        $data = array();
        if ($read_shape_type)
            $data += $this->readShapeType($fp);

        return $data;
    }

    protected function readRecordPoint(&$fp, $create_object = false, $options = null) {

        $data = array();

        $data["x"] = $this->readAndUnpack("d", fread($fp, 8));
        $data["y"] = $this->readAndUnpack("d", fread($fp, 8));

        $this->point_count++;
        return $data;
    }

    protected function readRecordMultiPoint(&$fp, $options = null) {
        $data = $this->readBoundingBox($fp);
        $data["numpoints"] = $this->readAndUnpack("i", fread($fp, 4));

        for ($i = 0; $i <= $data["numpoints"]; $i++) {
            $data["points"][] = $this->readRecordPoint($fp);
        }

        return $data;
    }

    protected function readRecordPolyLine(&$fp, $options = null) {
        $data = $this->readBoundingBox($fp);
        $data["numparts"] = $this->readAndUnpack("i", fread($fp, 4));
        $data["numpoints"] = $this->readAndUnpack("i", fread($fp, 4));

        if (isset($options['noparts']) && $options['noparts'] == true) {
            //Skip the parts
            $points_initial_index = ftell($fp) + 4 * $data["numparts"];
            $points_read = $data["numpoints"];
        } else {
            for ($i = 0; $i < $data["numparts"]; $i++) {
                $data["parts"][$i] = $this->readAndUnpack("i", fread($fp, 4));
            }

            $points_initial_index = ftell($fp);

            $points_read = 0;
            foreach ($data["parts"] as $part_index => $point_index) {
                if (!isset($data["parts"][$part_index]["points"]) || !is_array($data["parts"][$part_index]["points"])) {
                    $data["parts"][$part_index] = array();
                    $data["parts"][$part_index]["points"] = array();
                }
                while (!in_array($points_read, $data["parts"]) && $points_read < $data["numpoints"] && !feof($fp)) {
                    $data["parts"][$part_index]["points"][] = $this->readRecordPoint($fp, true);
                    $points_read++;
                }
            }
        }

        fseek($fp, $points_initial_index + ($points_read * $this->XY_POINT_RECORD_LENGTH));

        return $data;
    }

    protected function readRecordPolygon(&$fp, $options = null) {
        return $this->readRecordPolyLine($fp, $options);
    }

}