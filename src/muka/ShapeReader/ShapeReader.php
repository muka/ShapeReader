<?php

namespace muka\ShapeReader;

class ShapeReader {

    public $shp;
    public $dbf;

    public function __construct($filename, $options = array()) {

        $this->options = $options;

        $this->shp = new ShapeFile($filename);
        $this->dbf = new DbfFile($filename);
    }

}
