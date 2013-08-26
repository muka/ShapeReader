<?php

namespace muka\ShapeReader\Tests;

require __DIR__ . '/../../../vendor/autoload.php';

use muka\ShapeReader\ShapeReader;

class ShapeReaderTest extends \PHPUnit_Framework_TestCase {

    private $worldcities_shape = "./tests/support/worldcities/worldcities.shp";

    private function loadShp($file = null) {
        if(is_null($file)) {
            $file = $this->worldcities_shape;
        }
        return new ShapeReader($file);
    }

    public function testLoadShapefile() {

        $shpReader = $this->loadShp();

        $rows = 0;
        while ($record = $shpReader->getNext()) {
            $rows++;
        }

        $this->assertEquals(12686, $rows);

    }

    public function testGetDbfData() {

        $shpReader = $this->loadShp();

        $rows = 0;
        $rowID = 11358;
        while ($record = $shpReader->getNext()) {
            $dbf = $record->getDbfData();
            if($dbf['ID'] == $rowID) {
                break;
            }
            $rows++;
        }

        $this->assertEquals('Trento', $dbf['NAME']);

    }

}

