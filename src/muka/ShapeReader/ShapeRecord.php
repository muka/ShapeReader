<?php

namespace muka\ShapeReader;

class ShapeRecord extends ShapeFile
{

    private $record_number;
    private $content_length;
    private $record_shape_type;

    private $record_class = array(
      0 => "RecordNull",
      1 => "RecordPoint",
      8 => "RecordMultiPoint",
      3 => "RecordPolyLine",
      5 => "RecordPolygon"
    );

    public function __construct(&$fp, $filename, $options){
		$this->fp = $fp;
		$this->fpos = ftell($fp);
		$this->options = $options;

		if (feof($fp)) {
            return;
		}

		$this->readHeader();
		$this->filename = $filename;

	}

    public function getNextRecordPosition() {
        $nextRecordPosition = $this->fpos + ((4 + $this->content_length ) * 2);
        return $nextRecordPosition;
    }

    public function getDbfData() {
        $dbf = new DbfFile($this->filename, $this->record_number);
        return $dbf->getData();
    }

    public function getData() {

        if (!$this->data) {
            $function_name = "read" . $this->getRecordClass();
            $this->data = $this->{$function_name}($this->fp, $this->options);
        }

        return $this->data;
    }

    private function readHeader() {
        $this->record_number = $this->readAndUnpack("N", fread($this->fp, 4));
        $this->content_length = $this->readAndUnpack("N", fread($this->fp, 4));
        $this->record_shape_type = $this->readAndUnpack("i", fread($this->fp, 4));
    }

    /**
     * Reading functions
     */
    private function readRecordNull(&$fp, $read_shape_type = false, $options = null) {
        $data = array();
        if ($read_shape_type)
            $data += $this->readShapeType($fp);

        return $data;
    }

    private function readRecordPoint(&$fp, $create_object = false, $options = null) {

        $data = array();

        $data["x"] = $this->readAndUnpack("d", fread($fp, 8));
        $data["y"] = $this->readAndUnpack("d", fread($fp, 8));

        $this->point_count++;

        return $data;
    }

    private function readRecordMultiPoint(&$fp, $options = null) {
        $data = $this->readBoundingBox($fp);
        $data["numpoints"] = $this->readAndUnpack("i", fread($fp, 4));

        for ($i = 0; $i <= $data["numpoints"]; $i++) {
            $data["points"][] = $this->readRecordPoint($fp);
        }

        return $data;
    }

    private function readRecordPolyLine(&$fp, $options = null) {
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
                if (!isset($data["parts"][$part_index]["points"])
                        || !is_array($data["parts"][$part_index]["points"])) {
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

    private function readRecordPolygon(&$fp, $options = null) {
        return $this->readRecordPolyLine($fp, $options);
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

}
