# LGV_TZ_Lookup
Sever for Matching Long/Lat to Timezone

## Dependencies
This project uses [the streaming JSON parser](https://github.com/salsify/jsonstreamingparser), in order to parse [this file](https://github.com/evansiroky/timezone-boundary-builder/releases/download/2023b/timezones-with-oceans.geojson.zip) (a current release, at the time of this writing), which is [a GeoJSON file](https://datatracker.ietf.org/doc/html/rfc7946), containing the calculated timezones, and is created by [this project](https://github.com/evansiroky/timezone-boundary-builder).

## License
This is an [MIT-Licensed](https://opensource.org/license/mit/) project.