<?php

//include './src/muka/ShapeReader/ShapeReader.php';
//include './src/muka/ShapeReader/ShapeRecord.php';
//include './src/muka/ShapeReader/ShapeFile.php';
//include './src/muka/ShapeReader/DbfFile.php';
//include './src/muka/ShapeReader/Exception/DbfFileException.php';
//include './src/muka/ShapeReader/Exception/ShapeFileException.php';

include './vendor/autoload.php';

use muka\ShapeReader\ShapeReader;

$shpReader = new ShapeReader("./test/5961.shp");

$i=0;
while ($record = $shpReader->getNext() and $i < 2) {

    $shp_data = $record->getData();
//    var_dump($shp_data);
    //Dump the information
    $dbf_data = $record->getDbfData();
    var_dump($dbf_data);



    $i++;
}