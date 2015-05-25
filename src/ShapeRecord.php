<?php
namespace muka\ShapeReader;

use muka\ShapeReader\Exception\ShapeFileException;

class ShapeRecord extends ShapeReader {
    private $record_number;
    private $content_length;
    private $record_shape_type;
    private $point_count = 0;
    private $fp;
    private $fpos;
    private $options;
    private $filename;
    
    /**
     * 0 Null Shape
     * 1 Point
     * 3 PolyLine
     * 5 Polygon
     * 8 MultiPoint
     * 11 PointZ
     * 13 PolyLineZ
     * 15 PolygonZ
     * 18 MultiPointZ
     * 21 PointM
     * 23 PolyLineM
     * 25 PolygonM
     * 28 MultiPointM
     * 31 MultiPatch
     */
    private $record_class = [
        0 => "RecordNull",
        1 => "RecordPoint",
        3 => "RecordPolyLine",
        5 => "RecordPolygon",
        8 => "RecordMultiPoint",
        11 => "RecordPointZ",
        13 => "RecordPolyLineZ",
        15 => "RecordPolygonZ",
        18 => "MultiPointZ",
        21 => "PointM",
        23 => "PolyLineM",
        25 => "PolygonM",
        28 => "MultiPointM",
        31 => "MultiPatch"
    ];
    // TODO: 31=>MultiPatch
    
    /**
     *
     * @var DbfFile
     */
    private $dbf;

    public function __construct(&$fp, $filename, $options, $dbf = null) {

        $this->fp = $fp;
        $this->fpos = ftell($fp);
        $this->options = $options;
        $this->filename = $filename;
        $this->dbf = $dbf;
        $this->readHeader();
    }

    public function getNextRecordPosition() {

        $nextRecordPosition = $this->fpos + ((4 + $this->content_length) * 2);
        return $nextRecordPosition;
    }

    public function getDbfData() {

        if ($this->dbf) {
            return $this->dbf->getData($this->record_number);
        }
        return [];
    }

    public function getShpData() {

        return $this->getData();
    }

    public function getData() {

        if (!$this->data) {
            $recordType = $this->getRecordClass();
            $function_name = "read{$recordType}";
            
            $this->data = $this->{$function_name}($this->fp, $this->options);
            $this->data['type'] = $this->getTypeLabel();
            $this->data['typeCode'] = $this->getTypeCode();
        }
        
        return $this->data;
    }

    private function readHeader() {

        $this->record_number = $this->readAndUnpack("N", fread($this->fp, 4));
        $this->content_length = $this->readAndUnpack("N", fread($this->fp, 4));
        $this->record_shape_type = $this->readAndUnpack("i", fread($this->fp, 4));
    }

    public function getTypeCode() {

        return $this->record_shape_type;
    }

    public function getTypeLabel() {

        return str_replace("Record", "", $this->getRecordClass());
    }

    private function getRecordClass() {

        if (!isset($this->record_class[$this->record_shape_type])) {
            throw new ShapeFileException(sprintf("Unsupported record type encountered."));
        }
        
        if (!method_exists($this, "read" . $this->record_class[$this->record_shape_type])) {
            throw new ShapeFileException(sprintf("Record type %s not implemented.", $this->record_shape_type));
        }
        
        return $this->record_class[$this->record_shape_type];
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

        $data = [];
        
        $data["x"] = $this->readAndUnpack("d", fread($fp, 8));
        $data["y"] = $this->readAndUnpack("d", fread($fp, 8));
        
        $this->point_count ++;
        
        return $data;
    }

    private function readRecordPointM(&$fp, $create_object = false, $options = null) {

        $data = $this->readRecordPoint($fp);
        $nodata = -pow(10, 38); // any m smaller than this is considered "no data"
        $data["m"] = $this->readAndUnpack("d", fread($fp, 8));
        if ($data['m'] < $nodata) {
            unset($data['m']);
        }
        return $data;
    }

    private function readRecordPointZ(&$fp, $create_object = false, $options = null) {

        $data = $this->readRecordPoint($fp);
        $data["z"] = $this->readAndUnpack("d", fread($fp, 8));
        $nodata = -pow(10, 38); // any m smaller than this is considered "no data"
        $data["m"] = $this->readAndUnpack("d", fread($fp, 8));
        if ($data['m'] < $nodata) {
            unset($data['m']);
        }
        return $data;
    }

    private function readRecordMultiPoint(&$fp, $options = null) {

        $data = $this->readBoundingBox($fp);
        $data["numpoints"] = $this->readAndUnpack("i", fread($fp, 4));
        
        for ($i = 0; $i <= $data["numpoints"]; $i ++) {
            $data["points"][] = $this->readRecordPoint($fp);
        }
        
        return $data;
    }

    private function readRecordMultiPointM(&$fp, $options = null) {
        
        // [bounds:32],
        // [numpoints:4],
        // [point(1):16],
        // ...
        // [point(numpoints):16],
        // [mmin:8],
        // [mmax:8]
        // [m(1):16],
        // ...
        // [m(numpoints):16]
        $data = $this->readBoundingBox($fp);
        $data["numpoints"] = $this->readAndUnpack("i", fread($fp, 4));
        
        for ($i = 0; $i <= $data["numpoints"]; $i ++) {
            $data["points"][] = $this->readRecordPoint($fp);
        }
        
        // read mmin, mmax
        $nodata = -pow(10, 38); // any m smaller than this is considered "no data"
        $data['mmin'] = $this->readAndUnpack("d", fread($fp, 8));
        if ($data['mmin'] < $nodata) {
            unset($data['mmin']);
        }
        $data['mmax'] = $this->readAndUnpack("d", fread($fp, 8));
        if ($data['mmax'] < $nodata) {
            unset($data['mmax']);
        }
        
        for ($i = 0; $i <= $data["numpoints"]; $i ++) {
            $data["points"][$i]['m'] = $this->readAndUnpack("d", fread($fp, 8));
            if ($data["parts"][$i]['m'] < $nodata) {
                unset($data["parts"][$i]['m']);
            }
        }
        
        return $data;
    }

    private function readRecordMultiPointZ(&$fp, $options = null) {
        
        // [bounds:32],
        // [numpoints:4],
        // [point(1):16],
        // ...
        // [point(numpoints):16],
        // [zmin:8],
        // [zmax:8]
        // [z(1):16],
        // ...
        // [z(numpoints):16]
        // [mmin:8],
        // [mmax:8]
        // [m(1):16],
        // ...
        // [m(numpoints):16]
        $data = $this->readBoundingBox($fp);
        $data["numpoints"] = $this->readAndUnpack("i", fread($fp, 4));
        
        for ($i = 0; $i <= $data["numpoints"]; $i ++) {
            $data["points"][] = $this->readRecordPoint($fp);
        }
        
        // read zmin, zmax
        $data['zmin'] = $this->readAndUnpack("d", fread($fp, 8));
        $data['zmax'] = $this->readAndUnpack("d", fread($fp, 8));
        
        for ($i = 0; $i <= $data["numpoints"]; $i ++) {
            $data["points"][$i]['z'] = $this->readAndUnpack("d", fread($fp, 8));
        }
        
        // read mmin, mmax
        $nodata = -pow(10, 38); // any m smaller than this is considered "no data"
        $data['mmin'] = $this->readAndUnpack("d", fread($fp, 8));
        if ($data['mmin'] < $nodata) {
            unset($data['mmin']);
        }
        $data['mmax'] = $this->readAndUnpack("d", fread($fp, 8));
        if ($data['mmax'] < $nodata) {
            unset($data['mmax']);
        }
        
        for ($i = 0; $i <= $data["numpoints"]; $i ++) {
            $data["points"][$i]['m'] = $this->readAndUnpack("d", fread($fp, 8));
            if ($data["parts"][$i]['m'] < $nodata) {
                unset($data["parts"][$i]['m']);
            }
        }
        
        return $data;
    }

    private function readRecordPolyLine(&$fp, $options = null) {

        $data = $this->readBoundingBox($fp);
        
        $data["numparts"] = $this->readAndUnpack("i", fread($fp, 4));
        $data["numpoints"] = $this->readAndUnpack("i", fread($fp, 4));
        
        if (isset($options['noparts']) && $options['noparts'] == true) {
            // Skip the parts
            $points_initial_index = ftell($fp) + 4 * $data["numparts"];
            $points_read = $data["numpoints"];
            fseek($fp, $points_initial_index + ($points_read * $this->XY_POINT_RECORD_LENGTH));
        } else {
            for ($i = 0; $i < $data["numparts"]; $i ++) {
                $data["parts"][$i] = $this->readAndUnpack("i", fread($fp, 4));
            }
            
            $points_initial_index = ftell($fp);
            
            $points_read = 0;
            foreach ($data["parts"] as $part_index => $point_index) {
                if (!isset($data["parts"][$part_index]["points"]) || !is_array($data["parts"][$part_index]["points"])) {
                    $data["parts"][$part_index] = [];
                    $data["parts"][$part_index]["points"] = [];
                }
                while (!in_array($points_read, $data["parts"]) && $points_read < $data["numpoints"] && !feof($fp)) {
                    $data["parts"][$part_index]["points"][] = $this->readRecordPoint($fp, true);
                    $points_read ++;
                }
            }
        }
        
        return $data;
    }

    private function readRecordPolyLineM(&$fp, $options = null) {
        
        // [bounds:32],
        // [numparts:4],
        // [numpoints:4],
        // [parts(1):4],
        // ...
        // [parts(numparts):4]
        // [point(1):16],
        // ...
        // [point(numpoints):16],
        // [mmin:8],
        // [mmax:8]
        // [m(1):16],
        // ...
        // [m(numpoints):16]
        $data = $this->readBoundingBox($fp);
        
        $data["numparts"] = $this->readAndUnpack("i", fread($fp, 4));
        $data["numpoints"] = $this->readAndUnpack("i", fread($fp, 4));
        
        $points_initial_index = ftell($fp) + 4 * $data["numparts"];
        
        if (isset($options['noparts']) && $options['noparts'] == true) {
            // Skip the parts
            $points_read = $data["numpoints"];
            fseek($fp, 
                $points_initial_index + ($points_read * $this->XYM_POINT_RECORD_LENGTH) +
                     ($points_read * $this->XYM_POINT_RECORD_LENGTH) + $this->RANGE_LENGTH);
        } else {
            
            // array of indexes to the start of each part,
            // moved this out of $data["parts"] for reuse. (normally)
            // this is cleared and set to an array (points) on first xy point
            $parts = [];
            $data['parts'] = [];
            for ($i = 0; $i < $data["numparts"]; $i ++) {
                $parts[$i] = $this->readAndUnpack("i", fread($fp, 4));
                $data["parts"][$i] = [
                    "points" => []
                ];
            }
            
            $points_read = 0;
            foreach ($parts as $part_index => $point_index) {
                while (!in_array($points_read, $data["parts"]) && $points_read < $data["numpoints"] && !feof($fp)) {
                    
                    $data["parts"][$part_index]["points"][] = $this->readRecordPoint($fp, true);
                    
                    $points_read ++;
                }
            }
            
            // read mmin, mmax
            $nodata = -pow(10, 38); // any m smaller than this is considered "no data"
            $data['mmin'] = $this->readAndUnpack("d", fread($fp, 8));
            if ($data['mmin'] < $nodata) {
                unset($data['mmin']);
            }
            $data['mmax'] = $this->readAndUnpack("d", fread($fp, 8));
            if ($data['mmax'] < $nodata) {
                unset($data['mmax']);
            }
            
            $points_read = 0;
            
            foreach ($parts as $part_index => $point_index) {
                $point = 0;
                while (!in_array($points_read, $data["parts"]) && $points_read < $data["numpoints"] && !feof($fp)) {
                    $data["parts"][$part_index]["points"][$point]['m'] = $this->readAndUnpack("d", fread($fp, 8));
                    if ($data["parts"][$part_index]["points"][$point]['m'] < $nodata) {
                        unset($data["parts"][$part_index]["points"][$point]['m']);
                    }
                    $points_read ++;
                    $point ++;
                }
            }
        }
        
        return $data;
    }

    private function readRecordPolyLineZ(&$fp, $options = null) {
        
        // [bounds:32],
        // [numparts:4],
        // [numpoints:4],
        // [parts(1):4],
        // ...
        // [parts(numparts):4]
        // [point(1):16],
        // ...
        // [point(numpoints):16],
        // [zmin:8],
        // [zmax:8]
        // [z(1):16],
        // ...
        // [z(numpoints):16]
        // [mmin:8],
        // [mmax:8]
        // [m(1):16],
        // ...
        // [m(numpoints):16]
        $data = $this->readBoundingBox($fp);
        
        $data["numparts"] = $this->readAndUnpack("i", fread($fp, 4));
        $data["numpoints"] = $this->readAndUnpack("i", fread($fp, 4));
        
        $points_initial_index = ftell($fp) + 4 * $data["numparts"];
        
        if (isset($options['noparts']) && $options['noparts'] == true) {
            // Skip the parts
            $points_read = $data["numpoints"];
            fseek($fp, 
                $points_initial_index + ($points_read * $this->XYZ_POINT_RECORD_LENGTH) + (2 * $this->RANGE_LENGTH));
        } else {
            
            // array of indexes to the start of each part,
            // moved this out of $data["parts"] for reuse. (normally)
            // this is cleared and set to an array (points) on first xy point
            $parts = [];
            $data['parts'] = [];
            for ($i = 0; $i < $data["numparts"]; $i ++) {
                $parts[$i] = $this->readAndUnpack("i", fread($fp, 4));
                $data["parts"][$i] = [
                    "points" => []
                ];
            }
            
            $points_read = 0;
            foreach ($parts as $part_index => $point_index) {
                while (!in_array($points_read, $data["parts"]) && $points_read < $data["numpoints"] && !feof($fp)) {
                    
                    $data["parts"][$part_index]["points"][] = $this->readRecordPoint($fp, true);
                    
                    $points_read ++;
                }
            }
            // read zmin, zmax
            $data['zmin'] = $this->readAndUnpack("d", fread($fp, 8));
            $data['zmax'] = $this->readAndUnpack("d", fread($fp, 8));
            
            $points_read = 0;
            foreach ($parts as $part_index => $point_index) {
                $point = 0;
                while (!in_array($points_read, $data["parts"]) && $points_read < $data["numpoints"] && !feof($fp)) {
                    $data["parts"][$part_index]["points"][$point]['z'] = $this->readAndUnpack("d", fread($fp, 8));
                    $points_read ++;
                    $point ++;
                }
            }
            
            // read mmin, mmax
            $nodata = -pow(10, 38); // any m smaller than this is considered "no data"
            $data['mmin'] = $this->readAndUnpack("d", fread($fp, 8));
            if ($data['mmin'] < $nodata) {
                unset($data['mmin']);
            }
            $data['mmax'] = $this->readAndUnpack("d", fread($fp, 8));
            if ($data['mmax'] < $nodata) {
                unset($data['mmax']);
            }
            
            $points_read = 0;
            
            foreach ($parts as $part_index => $point_index) {
                $point = 0;
                while (!in_array($points_read, $data["parts"]) && $points_read < $data["numpoints"] && !feof($fp)) {
                    $data["parts"][$part_index]["points"][$point]['m'] = $this->readAndUnpack("d", fread($fp, 8));
                    if ($data["parts"][$part_index]["points"][$point]['m'] < $nodata) {
                        unset($data["parts"][$part_index]["points"][$point]['m']);
                    }
                    $points_read ++;
                    $point ++;
                }
            }
        }
        
        return $data;
    }

    private function readRecordPolygon(&$fp, $options = null) {

        return $this->readRecordPolyLine($fp, $options);
    }

    private function readRecordPolygonM(&$fp, $options = null) {

        return $this->readRecordPolyLineM($fp, $options);
    }

    private function readRecordPolygonZ(&$fp, $options = null) {

        return $this->readRecordPolyLineZ($fp, $options);
    }

    private function readRecordMultipatch(&$fp, $options = null) {
        
        // [bounds:32],
        // [numparts:4],
        // [numpoints:4],
        // [parts(1):4],
        // ...
        // [parts(numparts):4]
        // [parttypes(1):4],
        // ...
        // [parttypes(numparts):4]
        // [point(1):16],
        // ...
        // [point(numpoints):16],
        // [zmin:8],
        // [zmax:8]
        // [z(1):16],
        // ...
        // [z(numpoints):16]
        // [mmin:8],
        // [mmax:8]
        // [m(1):16],
        // ...
        // [m(numpoints):16]
        $data = $this->readBoundingBox($fp);
        
        $data["numparts"] = $this->readAndUnpack("i", fread($fp, 4));
        $data["numpoints"] = $this->readAndUnpack("i", fread($fp, 4));
        
        $points_initial_index = ftell($fp) + 4 * $data["numparts"];
        
        if (isset($options['noparts']) && $options['noparts'] == true) {
            // Skip the parts
            $points_read = $data["numpoints"];
            fseek($fp, 
                $points_initial_index + ($points_read * $this->XYZ_POINT_RECORD_LENGTH) + (2 * $this->RANGE_LENGTH));
        } else {
            
            // array of indexes to the start of each part,
            // moved this out of $data["parts"] for reuse. (normally)
            // this is cleared and set to an array (points) on first xy point
            $parts = [];
            $data['parts'] = [];
            for ($i = 0; $i < $data["numparts"]; $i ++) {
                $parts[$i] = $this->readAndUnpack("i", fread($fp, 4));
                $data["parts"][$i] = [
                    "points" => []
                ];
            }
            
            for ($i = 0; $i < $data["numparts"]; $i ++) {
                $parts[$i] = $this->readAndUnpack("i", fread($fp, 4));
                $data["parts"][$i]['type'] = $this->readAndUnpack("i", fread($fp, 4));
            }
            
            $points_read = 0;
            foreach ($parts as $part_index => $point_index) {
                while (!in_array($points_read, $data["parts"]) && $points_read < $data["numpoints"] && !feof($fp)) {
                    
                    $data["parts"][$part_index]["points"][] = $this->readRecordPoint($fp, true);
                    
                    $points_read ++;
                }
            }
            // read zmin, zmax
            $data['zmin'] = $this->readAndUnpack("d", fread($fp, 8));
            $data['zmax'] = $this->readAndUnpack("d", fread($fp, 8));
            
            $points_read = 0;
            foreach ($parts as $part_index => $point_index) {
                $point = 0;
                while (!in_array($points_read, $data["parts"]) && $points_read < $data["numpoints"] && !feof($fp)) {
                    $data["parts"][$part_index]["points"][$point]['z'] = $this->readAndUnpack("d", fread($fp, 8));
                    $points_read ++;
                    $point ++;
                }
            }
            
            // read mmin, mmax
            $nodata = -pow(10, 38); // any m smaller than this is considered "no data"
            $data['mmin'] = $this->readAndUnpack("d", fread($fp, 8));
            if ($data['mmin'] < $nodata) {
                unset($data['mmin']);
            }
            $data['mmax'] = $this->readAndUnpack("d", fread($fp, 8));
            if ($data['mmax'] < $nodata) {
                unset($data['mmax']);
            }
            
            $points_read = 0;
            
            foreach ($parts as $part_index => $point_index) {
                $point = 0;
                while (!in_array($points_read, $data["parts"]) && $points_read < $data["numpoints"] && !feof($fp)) {
                    $data["parts"][$part_index]["points"][$point]['m'] = $this->readAndUnpack("d", fread($fp, 8));
                    if ($data["parts"][$part_index]["points"][$point]['m'] < $nodata) {
                        unset($data["parts"][$part_index]["points"][$point]['m']);
                    }
                    $points_read ++;
                    $point ++;
                }
            }
        }
        
        return $data;
    }
}
