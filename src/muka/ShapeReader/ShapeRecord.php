<?php

namespace muka\ShapeReader;

class ShapeRecord extends ShapeFile
{

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

    private function readHeader() {
        $this->record_number = $this->readAndUnpack("N", fread($this->fp, 4));
        $this->content_length = $this->readAndUnpack("N", fread($this->fp, 4));
        $this->record_shape_type = $this->readAndUnpack("i", fread($this->fp, 4));
    }

}
