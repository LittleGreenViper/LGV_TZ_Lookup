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
    \brief This file has a list of long/lat pairs, along with the expected results, when presented to the server.
 */
$test_locations_param_array = [
    [   'title' =>  'Aleutians',
        'result' => 'America/Nome',
        'params' => [
                        'lng' => -162.29536,
                        'lat' => 55.097230
                    ]
    ],
    [   'title' =>  'Alice Springs, Australia',
        'result' => 'Australia/Darwin',
        'params' => [
                        'lng' => 133.880707,
                        'lat' => -23.700680
                    ]
    ],
    [   'title' =>  'Antarctica',
        'result' => 'Antarctica/DumontDUrville',
        'params' => [
                        'lng' => 140.169917,
                        'lat' => -69.900118
                    ]
    ],
    [   'title' =>  'Antarctica (Near South Pole)',
        'result' => 'Antarctica/McMurdo',
        'params' => [
                        'lng' => -118.24863,
                        'lat' => -85.229801
                    ]
    ],
    [   'title' =>  'Antarctica (Near South America)',
        'result' => 'America/Argentina/Ushuaia',
        'params' => [
                        'lng' => -62.760362,
                        'lat' => -77.078784
                    ]
    ],
    [   'title' =>  'Athens, Greece',
        'result' => 'Europe/Athens',
        'params' => [
                        'lng' => 23.76802,
                        'lat' => 38.013476
                    ]
    ],
    [   'title' =>  'Auckland, New Zealand',
        'result' => 'Pacific/Auckland',
        'params' => [
                        'lng' => 174.763336,
                        'lat' => -36.848461
                    ]
    ],
    [   'title' =>  'Bellingham, Washington',
        'result' => 'America/Los_Angeles',
        'params' => [
                        'lng' => -122.441657,
                        'lat' => 48.763431
                    ]
    ],
    [   'title' =>  'British Columbia (Western)',
        'result' => 'America/Vancouver',
        'params' => [
                        'lng' => -132.771299,
                        'lat' => 53.981935
                    ]
    ],
    [   'title' =>  'Budapest, Hungary',
        'result' => 'Europe/Zurich',
        'params' => [
                        'lng' => 9.086956,
                        'lat' => 47.517201
                    ]
    ],
    [   'title' =>  'Buenos Aires, Argentina',
        'result' => 'America/Argentina/Buenos_Aires',
        'params' => [
                        'lng' => -58.455354,
                        'lat' => -34.597042
                    ]
    ],
    [   'title' =>  'Chicago',
        'result' => 'America/Chicago',
        'params' => [
                        'lng' => -87.501113,
                        'lat' => 41.607228
                    ]
    ],
    [   'title' =>  'Delhi, India',
        'result' => 'Asia/Kolkata',
        'params' => [
                        'lng' => 77.102493,
                        'lat' => 28.704060
                    ]
    ],
    [   'title' =>  'Eastern Siberia',
        'result' => 'Asia/Kamchatka',
        'params' => [
                        'lng' => 166.051721,
                        'lat' => 55.254077
                    ]
    ],
    [   'title' =>  'Edmonton, Canada',
        'result' => 'America/Edmonton',
        'params' => [
                        'lng' => -113.530852,
                        'lat' => 53.566414
                    ]
    ],
    [   'title' =>  'Greenland (All the Way to the Tippy-Top)',
        'result' => 'America/Nuuk',
        'params' => [
                        'lng' => -31.304008,
                        'lat' => 83.520162
                    ]
    ],
    [   'title' =>  'Greenwich, United Kingdom',
        'result' => 'Europe/London',
        'params' => [
                        'lng' => 0,
                        'lat' => 51.482578
                    ]
    ],
    [   'title' =>  'Hobart, Australia',
        'result' => 'Australia/Hobart',
        'params' => [
                        'lng' => 147.387644,
                        'lat' => -42.892064
                    ]
    ],
    [   'title' =>  'Israel',
        'result' => 'Asia/Jerusalem',
        'params' => [
                        'lng' => 35.190945,
                        'lat' => 32.6625
                    ]
    ],
    [   'title' =>  'Istanbul, Türkiye',
        'result' => 'Europe/Istanbul',
        'params' => [
                        'lng' => 28.990942,
                        'lat' => 41.033787
                    ]
    ],
    [   'title' =>  'Jordan (Close to Israel)',
        'result' => 'Asia/Hebron',
        'params' => [
                        'lng' => 35.207436,
                        'lat' => 32.314991
                    ]
    ],
    [   'title' =>  'Kosovo',
        'result' => 'Europe/Belgrade',
        'params' => [
                        'lng' => 20.933892,
                        'lat' => 42.650122
                    ]
    ],
    [   'title' =>  'Rural Montana',
        'result' => 'America/Denver',
        'params' => [
                        'lng' => -109.353259,
                        'lat' => 47.219568
                    ]
    ],
    [   'title' =>  'New York State',
        'result' => 'America/New_York',
        'params' => [
                        'lng' => -73,
                        'lat' => 44
                    ]
    ],
    [   'title' =>  'New Zealand Island (Off West Coast)',
        'result' => 'Pacific/Auckland',
        'params' => [
                        'lng' => 166.583440,
                        'lat' => -48.0083
                    ]
    ],
    [   'title' =>  'Olympic Coast Marine Sanctuary',
        'result' => 'America/Los_Angeles',
        'params' => [
                        'lng' => -124.607409,
                        'lat' => 48.308774
                    ]
    ],
    [   'title' =>  'Russian Islands (In Arctic Ocean)',
        'result' => 'Europe/Moscow',
        'params' => [
                        'lng' => 49.904964,
                        'lat' => 80.816891
                    ]
    ],
    [   'title' =>  'Seattle',
        'result' => 'America/Los_Angeles',
        'params' => [
                        'lng' => -122.342714,
                        'lat' => 47.580231
                    ]
    ],
    [   'title' =>  'Sinai Peninsula (Southern Tip)',
        'result' => 'Africa/Cairo',
        'params' => [
                        'lng' => 34.209576,
                        'lat' => 27.911913
                    ]
    ],
    [   'title' =>  'Sitka, Alaska',
        'result' => 'America/Sitka',
        'params' => [
                        'lng' => -133.518868,
                        'lat' => 56.243350
                    ]
    ],
    [   'title' =>  'South Africa',
        'result' => 'Africa/Johannesburg',
        'params' => [
                        'lng' => 25.632211,
                        'lat' => -33.979809
                    ]
    ],
    [   'title' =>  'Stockholm, Sweden',
        'result' => 'Europe/Stockholm',
        'params' => [
                        'lng' => 13.948279,
                        'lat' => 55.254077
                    ]
    ],
    [   'title' =>  'Sudan (East Coast)',
        'result' => 'Africa/Khartoum',
        'params' => [
                        'lng' => 37.742594,
                        'lat' => 18.364953
                    ]
    ],
    [   'title' =>  'Svarlbard',
        'result' => 'Arctic/Longyearbyen',
        'params' => [
                        'lng' => 13.034925,
                        'lat' => 78.389278
                    ]
    ],
    [   'title' =>  'Tombuktu, Mali',
        'result' => 'Africa/Bamako',
        'params' => [
                        'lng' => -3.002758,
                        'lat' => 16.783506
                    ]
    ],
    [   'title' =>  'Toronto Area (Near New York)',
        'result' => 'America/Toronto',
        'params' => [
                        'lng' => -74,
                        'lat' => 45
                    ]
    ],
    [   'title' =>  'Tromsø, Norway',
        'result' => 'Europe/Oslo',
        'params' => [
                        'lng' => 18.905175,
                        'lat' => 69.679990
                    ]
    ],
    [   'title' =>  'Vancouver, Canada',
        'result' => 'America/Vancouver',
        'params' => [
                        'lng' => -122.930875,
                        'lat' => 49.203243
                    ]
    ],
    [   'title' =>  'Victoria, Canada',
        'result' => 'America/Vancouver',
        'params' => [
                        'lng' => -123.359628,
                        'lat' => 48.436490
                    ]
    ]
];
