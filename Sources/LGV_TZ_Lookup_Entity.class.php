<?php
/***************************************************************************************************************************/
/**
    © Copyright 2023, [Little Green Viper Software Development LLC](https://littlegreenviper.com)
    
    LICENSE:
    
    MIT License
    
    Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation
    files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy,
    modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the
    Software is furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
    OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
    IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
    CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

    [Little Green Viper Software Development LLC](https://littlegreenviper.com)
*/
/***************************************************************************************************************************/
/**
    \brief This is a modified GeoJSON listener for [the streaming JSON parser](https://github.com/salsify/jsonstreamingparser).
 */
 
declare(strict_types = 1);

/***************************************************************************************************************************/
/**
    This class is basically a struct, containing one entity.

    The entity is usually one multipolygon segment, as defined in the main data file. It may represent the entirety of a timezone,
    but is more likely to be just one part.
    
    It has a timezone ID, a detailed list of polygon points, and a "domain rect," which is a simple rect that encompasses all of the points.
    We use the domain rect to quickly triage comparisons, and find out where they apply, before applying a more expensive algorithm to do a more
    exact polygon match.
 */
class LGV_TZ_Lookup_Entity {
    /***********************************************************************************************************************/
    /**
    This is [the standard TZ time zone designator](https://en.wikipedia.org/wiki/List_of_tz_database_time_zones) that applies to this entity.
     */
    var $tzID;
    
    /***********************************************************************************************************************/
    /**
    This is the domain rectangle that contains the East, West, North, and South boundaries of the polygon.
     */
    var $domainRect;
    
    /***********************************************************************************************************************/
    /**
    This is the exact polygon for the time zone.
    It is a simple 2-element floating point array, with the longitude being [0], and the latitude being [1].
     */
    var $polygon;

    /***********************************************************************************************************************/
    /**
    The default constructor. We simply pass in the values to be held by this instance.
     */
    function __construct (
                            $inTZID,            ///< This is [the standard TZ time zone designator](https://en.wikipedia.org/wiki/List_of_tz_database_time_zones) that applies to this entity.
                            $inDomainRect,      ///< This is the domain rectangle that contains the East, West, North, and South boundaries of the polygon.
                            $inCoordinateArray  ///< This is the exact polygon for the time zone.
                        ) {
        $this->tzID = $inTZID;
        $this->domainRect = $inDomainRect;
        $this->polygon = $inCoordinateArray;
    }
}
