<?php

use muka\ShapeReader\ShapeReader;

class ShapeReaderTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var String
     */
    private $worldcities_shape;

    /**
     * @var ShapeReader
     */
    private $shpReader;

    public function setUp() {
        parent::setUp();

        $this->worldcities_shape = __DIR__."/../../support/worldcities/worldcities.shp";

        $this->shpReader = new ShapeReader($this->worldcities_shape);
    }

    public function testLoadShapefile() {

        $rows = 0;
        while ($record = $this->shpReader->getNext()) {
            $dbf = $record->getDbfData();
            $rows++;
        }

        $this->assertEquals(12686, $rows);

    }

    public function testGetDbfData() {

        $rows = 0;
        $rowIndex = 11358;
        while ($record = $this->shpReader->getNext()) {
            $dbf = $record->getDbfData();
            if($dbf['ID'] == $rowIndex) {
                break;
            }
            $rows++;
        }

        $this->assertEquals('Trento', $dbf['NAME']);

    }

}

