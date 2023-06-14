# LGV_TZ_Lookup
Server for Matching Long/Lat to Timezone

## Overview
This project is a fairly simple PHP project, designed to accept the GeoJSON output of [the streaming JSON parser](https://github.com/salsify/jsonstreamingparser), and provide a simple API, for matching longitude/latitude locations with timezones.

Send in a long/lat, and get back a string, with [the standard TZ time zone designator](https://en.wikipedia.org/wiki/List_of_tz_database_time_zones) of the timezone that covers that point.

## Dependencies
This project uses [the streaming JSON parser](https://github.com/salsify/jsonstreamingparser), in order to parse [this file](https://github.com/evansiroky/timezone-boundary-builder/releases/download/2023b/timezones-with-oceans.geojson.zip) (a current release, at the time of this writing), which is [a GeoJSON file](https://datatracker.ietf.org/doc/html/rfc7946), containing the calculated timezones, and is created by [this project](https://github.com/evansiroky/timezone-boundary-builder).

## License
This is an [MIT-Licensed](https://opensource.org/license/mit/) project.