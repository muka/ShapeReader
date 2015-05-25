<?php
namespace muka\ShapeReader;

use muka\ShapeReader\Exception\ShapeFileException;

class ShapeRecord extends ShapeReader {
    private $record_number;
    private $content_length;
    private $record_shape_type;
    private $point_count = 0;
    protected $fp;
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

    public function __destruct() {
        // overriden to prevent closing shared file pointer.
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
            
            $this->data = $this->{$function_name}($this->options);
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
    private function readRecordNull($read_shape_type = false, $options = null) {

        $data = array();
        if ($read_shape_type)
            $data += $this->readShapeType($this->fp);
        
        return $data;
    }

    private function readRecordPoint($create_object = false, $options = null) {

        $data = [];
        
        $data["x"] = $this->readAndUnpack("d", fread($this->fp, 8));
        $data["y"] = $this->readAndUnpack("d", fread($this->fp, 8));
        
        $this->point_count ++;
        
        return $data;
    }

    private function readRecordPointM($create_object = false, $options = null) {

        $data = $this->readRecordPoint($this->fp);
        $nodata = -pow(10, 38); // any m smaller than this is considered "no data"
        $data["m"] = $this->readAndUnpack("d", fread($this->fp, 8));
        if ($data['m'] < $nodata) {
            unset($data['m']);
        }
        return $data;
    }

    private function readRecordPointZ($create_object = false, $options = null) {

        $data = $this->readRecordPoint($this->fp);
        $data["z"] = $this->readAndUnpack("d", fread($this->fp, 8));
        $nodata = -pow(10, 38); // any m smaller than this is considered "no data"
        $data["m"] = $this->readAndUnpack("d", fread($this->fp, 8));
        if ($data['m'] < $nodata) {
            unset($data['m']);
        }
        return $data;
    }

    private function _readNumPoints(&$data) {

        $count = $this->readAndUnpack("i", fread($this->fp, 4));
        $data["numpoints"] = $count;
        return $count;
    }

    private function _readNumParts(&$data) {

        $count = $this->readAndUnpack("i", fread($this->fp, 4));
        $data["numparts"] = $count;
        return $count;
    }

    private function _readPoints(&$data) {

        for ($i = 0; $i <= $data["numpoints"]; $i ++) {
            $data["points"][] = $this->readRecordPoint($this->fp);
        }
    }

    private function _readMPoints(&$data) {
        
        // read mmin, mmax
        $nodata = -pow(10, 38); // any m smaller than this is considered "no data"
        $data['mmin'] = $this->readAndUnpack("d", fread($this->fp, 8));
        if ($data['mmin'] < $nodata) {
            unset($data['mmin']);
        }
        $data['mmax'] = $this->readAndUnpack("d", fread($this->fp, 8));
        if ($data['mmax'] < $nodata) {
            unset($data['mmax']);
        }
        
        for ($i = 0; $i <= $data["numpoints"]; $i ++) {
            $data["points"][$i]['m'] = $this->readAndUnpack("d", fread($this->fp, 8));
            if ($data["parts"][$i]['m'] < $nodata) {
                unset($data["parts"][$i]['m']);
            }
        }
    }

    private function _readZPoints(&$data) {
        
        // read zmin, zmax
        $data['zmin'] = $this->readAndUnpack("d", fread($this->fp, 8));
        $data['zmax'] = $this->readAndUnpack("d", fread($this->fp, 8));
        
        for ($i = 0; $i <= $data["numpoints"]; $i ++) {
            $data["points"][$i]['z'] = $this->readAndUnpack("d", fread($this->fp, 8));
        }
    }

    private function readRecordMultiPoint($options = null) {

        $data = $this->readBoundingBox();
        $data["numpoints"] = $this->readAndUnpack("i", fread($this->fp, 4));
        
        $this->_readPoints($data);
        
        return $data;
    }

    private function readRecordMultiPointM($options = null) {
        
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
        $data = $this->readBoundingBox();
        $this->_readNumPoints($data);
        $this->_readPoints($data);
        $this->_readMPoints($data);
        
        return $data;
    }

    private function readRecordMultiPointZ($options = null) {
        
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
        $data = $this->readBoundingBox();
        
        $this->_readNumPoints($data);
        $this->_readPoints($data);
        $this->_readZPoints($data);
        $this->_readMPoints($data);
        
        return $data;
    }

    private function _readPartIndexes(&$data) {

        $parts = [];
        $data['parts'] = [];
        for ($i = 0; $i < $data['numparts']; $i ++) {
            $parts[$i] = $this->readAndUnpack("i", fread($this->fp, 4));
            $data["parts"][$i] = [
                "points" => []
            ];
        }
        return $parts;
    }

    private function _readPartPoints(&$data, $parts) {

        $points_read = 0;
        foreach ($parts as $part_index => $point_index) {
            while (!in_array($points_read, $data["parts"]) && $points_read < $data["numpoints"] && !feof($this->fp)) {
                
                $data["parts"][$part_index]["points"][] = $this->readRecordPoint($this->fp, true);
                
                $points_read ++;
            }
        }
    }

    private function _readPartMPoints(&$data, $parts) {
        // read mmin, mmax
        $nodata = -pow(10, 38); // any m smaller than this is considered "no data"
        $data['mmin'] = $this->readAndUnpack("d", fread($this->fp, 8));
        if ($data['mmin'] < $nodata) {
            unset($data['mmin']);
        }
        $data['mmax'] = $this->readAndUnpack("d", fread($this->fp, 8));
        if ($data['mmax'] < $nodata) {
            unset($data['mmax']);
        }
        
        $points_read = 0;
        
        foreach ($parts as $part_index => $point_index) {
            $point = 0;
            while (!in_array($points_read, $data["parts"]) && $points_read < $data["numpoints"] && !feof($this->fp)) {
                $data["parts"][$part_index]["points"][$point]['m'] = $this->readAndUnpack("d", fread($this->fp, 8));
                if ($data["parts"][$part_index]["points"][$point]['m'] < $nodata) {
                    unset($data["parts"][$part_index]["points"][$point]['m']);
                }
                $points_read ++;
                $point ++;
            }
        }
    }

    private function _readPartZPoints(&$data, $parts) {
        
        // read zmin, zmax
        $data['zmin'] = $this->readAndUnpack("d", fread($this->fp, 8));
        $data['zmax'] = $this->readAndUnpack("d", fread($this->fp, 8));
        
        $points_read = 0;
        foreach ($parts as $part_index => $point_index) {
            $point = 0;
            while (!in_array($points_read, $data["parts"]) && $points_read < $data["numpoints"] && !feof($this->fp)) {
                $data["parts"][$part_index]["points"][$point]['z'] = $this->readAndUnpack("d", fread($this->fp, 8));
                $points_read ++;
                $point ++;
            }
        }
    }

    private function readRecordPolyLine($options = null) {

        $data = $this->readBoundingBox();
        
        $countparts = $this->_readNumParts($data);
        $countpoints = $this->_readNumPoints($data);
        
        if (isset($options['noparts']) && $options['noparts'] == true) {
            fseek($this->fp, ftell($this->fp) + (4 * $countparts) + ($countpoints * $this->XY_POINT_RECORD_LENGTH));
        } else {
            $parts = $this->_readPartIndexes($data);
            $this->_readPartPoints($data, $parts);
        }
        
        return $data;
    }

    private function readRecordPolyLineM($options = null) {
        
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
        $data = $this->readBoundingBox();
        
        $countparts = $this->_readNumParts($data);
        $countpoints = $this->_readNumPoints($data);
        
        if (isset($options['noparts']) && $options['noparts'] == true) {
            // Skip the parts
            fseek($this->fp, 
                ftell($this->fp) + (4 * $countparts) + ($countpoints * $this->XYM_POINT_RECORD_LENGTH) +
                     $this->RANGE_LENGTH);
        } else {
            
            $parts = $this->_readPartIndexes($data);
            $this->_readPartPoints($data, $parts);
            $this->_readPartMPoints($data, $parts);
        }
        
        return $data;
    }

    private function readRecordPolyLineZ($options = null) {
        
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
        $data = $this->readBoundingBox();
        
        $countparts = $this->_readNumParts($data);
        $countpoints = $this->_readNumPoints($data);
        
        if (isset($options['noparts']) && $options['noparts'] == true) {
            // Skip the parts
            fseek($this->fp, 
                ftell($this->fp) + (4 * $countparts) + ($countpoints * $this->XYZ_POINT_RECORD_LENGTH) +
                     (2 * $this->RANGE_LENGTH));
        } else {
            
            $parts = $this->_readPartIndexes($data);
            $this->_readPartPoints($data, $parts);
            $this->_readPartZPoints($data, $parts);
            $this->_readPartMPoints($data, $parts);
        }
        
        return $data;
    }

    private function readRecordPolygon($options = null) {

        return $this->readRecordPolyLine($options);
    }

    private function readRecordPolygonM($options = null) {

        return $this->readRecordPolyLineM($options);
    }

    private function readRecordPolygonZ($options = null) {

        return $this->readRecordPolyLineZ($options);
    }

    private function _readPartTypes(&$data, $parts) {

        for ($i = 0; $i < $data["numparts"]; $i ++) {
            $data["parts"][$i]['type'] = $this->readAndUnpack("i", fread($this->fp, 4));
        }
    }

    private function readRecordMultipatch($options = null) {
        
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
        $data = $this->readBoundingBox();
        
        $countparts = $this->_readNumParts($data);
        $countpoints = $this->_readNumPoints($data);
        
        if (isset($options['noparts']) && $options['noparts'] == true) {
            // Skip the parts
            fseek($this->fp, 
                ftell($this->fp) + (8 * $countparts) + ($countpoints * $this->XYZ_POINT_RECORD_LENGTH) +
                     (2 * $this->RANGE_LENGTH));
        } else {
            
            $parts = $this->_readPartIndexes($data);
            $this->_readPartTypes($data, $parts);
            $this->_readPartPoints($data, $parts);
            $this->_readPartZPoints($data, $parts);
            $this->_readPartMPoints($data, $parts);
        }
        
        return $data;
    }
}
