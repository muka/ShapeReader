<?php

/**
 * This class is under GPL Licencense Agreement
 * @author Juan Carlos Gonzalez Ulloa <jgonzalez@innox.com.mx>
 * Innovacion Inteligente S de RL de CV (Innox)
 * Lopez Mateos Sur 2077 - Z16
 * Col. Jardines de Plaza del Sol
 * Guadalajara, Jalisco
 * CP 44510
 * Mexico
 *
 * Edited by David Granqvist March 2008 for better performance on large files
 * Refactored by Luca Capra
 */
namespace muka\ShapeReader;

use muka\ShapeReader\Exception\ShapeFileException;

class ShapeReader
{

    private $filename;

    private $fp;

    private $dbf;

    private $fpos = 100;

    private $fsize = 0;

    private $options;

    private $bbox = [];

    private $point_count = 0;

    public $XY_POINT_RECORD_LENGTH = 16;

    public $XYM_POINT_RECORD_LENGTH = 24;

    public $XYZ_POINT_RECORD_LENGTH = 32;

    protected $data;

    private $shp_type = 0;

    public function __construct($filename, $options = [])
    {
        $this->filename = $filename;
        
        $this->fopen();
        $this->readConfig();
        $this->dbf = new DbfFile($this->filename, $this->options);
        
        $this->options = $options;
    }

    public function __destruct()
    {
        $this->closeFile();
    }

    private function closeFile()
    {
        if ($this->fp) {
            fclose($this->fp);
        }
    }

    private function fopen()
    {
        if (! is_readable($this->filename)) {
            throw new ShapeFileException(sprintf("%s is not readable.", $this->filename));
        }
        $this->fp = fopen($this->filename, "rb");
        $fstat = fstat($this->fp);
        $this->fsize = $fstat['size'];
    }

    private function readConfig()
    {
        fseek($this->fp, 32, SEEK_SET);
        $this->shp_type = $this->readAndUnpack("i", fread($this->fp, 4));
        $this->bbox = $this->readBoundingBox($this->fp);
    }

    public function getNext()
    {
        if (! feof($this->fp) && $this->fpos < $this->fsize) {
            
            fseek($this->fp, $this->fpos);
            $record = new ShapeRecord($this->fp, $this->filename, $this->options, $this->dbf);
            $this->fpos = $record->getNextRecordPosition();
            
            return $record;
        }
        
        return false;
    }

    protected function readBoundingBox(&$fp)
    {
        $data = [];
        $data["xmin"] = $this->readAndUnpack("d", fread($fp, 8));
        $data["ymin"] = $this->readAndUnpack("d", fread($fp, 8));
        $data["xmax"] = $this->readAndUnpack("d", fread($fp, 8));
        $data["ymax"] = $this->readAndUnpack("d", fread($fp, 8));
        
        return $data;
    }

    protected function readAndUnpack($type, $data)
    {
        if (! $data) {
            return $data;
        }
        
        return current(unpack($type, $data));
    }
}
