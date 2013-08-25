= ShapeReader =
A library to parse Shp/Dbf files.

Based on the great work of Juan Carlos Gonzalez Ulloa and David Granqvist
A copy of the original work is available at http://www.phpclasses.org/package/1741-PHP-Read-vectorial-data-from-geographic-shape-files.html

`This library is meant to read vectorial information from shape files in the SHP format. The SHP file format is an open standard for storing vectorial information that is used to distribute geographical information. Plenty of commercial and open source applications are able to read from it.`

= Requirements =
PHP version should be > 5.3.2
To open the DBF related database you need the dbase extension available as PECL package.

    pecl install dbase
    echo "extension=dbase.so" > /etc/php5/conf.d/dbase.ini

= Usage =
See examples folder for details.

    $shpReader = new ShapeReader("./test/5961.shp");

    $i = 0;
    while ($record = $shpReader->getNext() and $i < 5) {

        //Dump SHP information
        $shp_data = $record->getData();
        var_dump($shp_data);

        //Dump DBF information
        $dbf_data = $record->getDbfData();
        var_dump($dbf_data);

        $i++;
    }

= Changelog =
2013-08-24 - Base refactor, added namespace support, composer and test cases

= License =
GNU General Public License
http://opensource.org/licenses/GPL-3.0