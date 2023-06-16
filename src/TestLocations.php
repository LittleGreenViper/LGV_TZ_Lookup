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
    [   'title' =>  'Água Boa, Brazil',
        'result' => 'America/Cuiaba',
        'params' => [
                        'lng' => -51.950479,
                        'lat' => -14.134819
                    ]
    ],
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
    [   'title' =>  'American Samoa',
        'result' => 'Pacific/Pago_Pago',
        'params' => [
                        'lng' => -170.671159,
                        'lat' => -14.273195
                    ]
    ],
    [   'title' =>  'Angola (Southeast Corner)',
        'result' => 'Africa/Luanda',
        'params' => [
                        'lng' => 23.342037,
                        'lat' => -17.60014
                    ]
    ],
    [   'title' =>  'Annobón',
        'result' => 'Africa/Malabo',
        'params' => [
                        'lng' => 5.640997,
                        'lat' => -1.419615 
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
    [   'title' =>  'Ascension Island',
        'result' => 'Atlantic/St_Helena',
        'params' => [
                        'lng' => -14.363119,
                        'lat' => -7.941107
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
    [   'title' =>  'Azores',
        'result' => 'Atlantic/Azores',
        'params' => [
                        'lng' => -25.473137,
                        'lat' => 37.808556
                    ]
    ],
    [   'title' =>  'Badajoz, Spain',
        'result' => 'Europe/Madrid',
        'params' => [
                        'lng' => -6.973893,
                        'lat' => 38.878292
                    ]
    ],
    [   'title' =>  'Bela Vista',
        'result' => 'Africa/Sao_Tome',
        'params' => [
                        'lng' => 7.398135,
                        'lat' => 1.589948
                    ]
    ],
    [   'title' =>  'Belfast, Ireland',
        'result' => 'Europe/London',
        'params' => [
                        'lng' => -5.921657,
                        'lat' => 54.598146
                    ]
    ],
    [   'title' =>  'Bellingham, Washington',
        'result' => 'America/Los_Angeles',
        'params' => [
                        'lng' => -122.441657,
                        'lat' => 48.763431
                    ]
    ],
    [   'title' =>  'Benton Harbor, Michigan',
        'result' => 'America/Detroit',
        'params' => [
                        'lng' => -86.452084,
                        'lat' => 42.119871
                    ]
    ],
    [   'title' =>  'Bermuda',
        'result' => 'Atlantic/Bermuda',
        'params' => [
                        'lng' => -64.756165,
                        'lat' => 32.30382
                    ]
    ],
    [   'title' =>  'Blountstown, Florida',
        'result' => 'America/Chicago',
        'params' => [
                        'lng' => -85.055622,
                        'lat' => 30.443066
                    ]
    ],
    [   'title' =>  'Brasilia, Brazil',
        'result' => 'America/Sao_Paulo',
        'params' => [
                        'lng' => -47.867956,
                        'lat' => -15.864759
                    ]
    ],
    [   'title' =>  'British Columbia (Western)',
        'result' => 'America/Vancouver',
        'params' => [
                        'lng' => -132.771299,
                        'lat' => 53.981935
                    ]
    ],
    [   'title' =>  'British Indian Ocean Territory',
        'result' => 'Indian/Chagos',
        'params' => [
                        'lng' => 72.363589,
                        'lat' => -7.270218
                    ]
    ],
    [   'title' =>  'British Virgin Islands',
        'result' => 'America/Tortola',
        'params' => [
                        'lng' => -64.620627,
                        'lat' => 18.443443
                    ]
    ],
    [   'title' =>  'Broken Hill, Australia',
        'result' => 'Australia/Broken_Hill',
        'params' => [
                        'lng' => 141.458629,
                        'lat' => -31.960523
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
    [   'title' =>  'Bumadeya, Kenya',
        'result' => 'Africa/Nairobi',
        'params' => [
                        'lng' => 33.97874,
                        'lat' => 0.096752
                    ]
    ],
    [   'title' =>  'Cabo Delgado, Mozambique',
        'result' => 'Africa/Maputo',
        'params' => [
                        'lng' => 40.623642,
                        'lat' => -10.686976
                    ]
    ],
    [   'title' =>  'Calle el Salto, Costa Rica',
        'result' => 'America/Costa_Rica',
        'params' => [
                        'lng' => -82.898204,
                        'lat' => 8.052373
                    ]
    ],
    [   'title' =>  'Cape Bathurst',
        'result' => 'America/Inuvik',
        'params' => [
                        'lng' => -128.200098,
                        'lat' => 70.601294
                    ]
    ],
    [   'title' =>  'Cape Horn',
        'result' => 'America/Punta_Arenas',
        'params' => [
                        'lng' => -67.274663,
                        'lat' => -55.956996
                    ]
    ],
    [   'title' =>  'Cape Perry',
        'result' => 'America/Inuvik',
        'params' => [
                        'lng' => -124.583874,
                        'lat' => 70.184311
                    ]
    ],
    [   'title' =>  'Cape Ulvak',
        'result' => 'America/Goose_Bay',
        'params' => [
                        'lng' => -62.633902,
                        'lat' => 58.477601
                    ]
    ],
    [   'title' =>  'Capurita, Bolivia',
        'result' => 'America/La_Paz',
        'params' => [
                        'lng' => -68.923307,
                        'lat' => -16.205201
                    ]
    ],
    [   'title' =>  'Channel-Port aux Basques, Newfoundland',
        'result' => 'America/St_Johns',
        'params' => [
                        'lng' => -59.148697,
                        'lat' => 47.583752
                    ]
    ],
    [   'title' =>  'Chatham Islands',
        'result' => 'Pacific/Chatham',
        'params' => [
                        'lng' => -176.221343,
                        'lat' => -44.288154
                    ]
    ],
    [   'title' =>  'Chicago',
        'result' => 'America/Chicago',
        'params' => [
                        'lng' => -87.501113,
                        'lat' => 41.607228
                    ]
    ],
    [   'title' =>  'Christmas Island',
        'result' => 'Indian/Christmas',
        'params' => [
                        'lng' => 105.617137,
                        'lat' => -10.491315
                    ]
    ],
    [   'title' =>  'Clipperton Island <em>(NOTE: Returns an &quot;Etc&quot; time zone)</em>',
        'result' => 'Etc/GMT+7',
        'params' => [
                        'lng' => -109.216321,
                        'lat' => 10.303647
                    ]
    ],
    [   'title' =>  'Cocos Islands',
        'result' => 'Indian/Cocos',
        'params' => [
                        'lng' => 96.840938,
                        'lat' => -12.072831
                    ]
    ],
    [   'title' =>  'Cockburn, Australia',
        'result' => 'Australia/Adelaide',
        'params' => [
                        'lng' => 140.920299,
                        'lat' => -32.095577
                    ]
    ],
    [   'title' =>  'Cumbati, Rwanda',
        'result' => 'Africa/Kigali',
        'params' => [
                        'lng' => 30.545317,
                        'lat' => -2.406189
                    ]
    ],
    [   'title' =>  'Cyprus',
        'result' => 'Asia/Nicosia',
        'params' => [
                        'lng' => 33.145129,
                        'lat' => 34.982302
                    ]
    ],
    [   'title' =>  'Dallas',
        'result' => 'America/Chicago',
        'params' => [
                        'lng' => -96.796856,
                        'lat' => 32.776272
                    ]
    ],
    [   'title' =>  'Delhi, India',
        'result' => 'Asia/Kolkata',
        'params' => [
                        'lng' => 77.102493,
                        'lat' => 28.704060
                    ]
    ],
    [   'title' =>  'Demirköy, Türkiye',
        'result' => 'Europe/Istanbul',
        'params' => [
                        'lng' => 28.020586,
                        'lat' => 41.958755
                    ]
    ],
    [   'title' =>  'Dubai',
        'result' => 'Asia/Dubai',
        'params' => [
                        'lng' => 55.188539,
                        'lat' => 25.074282
                    ]
    ],
    [   'title' =>  'Durban, South Africa',
        'result' => 'Africa/Johannesburg',
        'params' => [
                        'lng' => 31.012545,
                        'lat' => -29.867861 
                    ]
    ],
    [   'title' =>  'Easter Island',
        'result' => 'Pacific/Easter',
        'params' => [
                        'lng' => -109.349633,
                        'lat' => -27.125945
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
    [   'title' =>  'Elvas, Portugal',
        'result' => 'Europe/Lisbon',
        'params' => [
                        'lng' => -7.16812,
                        'lat' => 38.878292
                    ]
    ],
    [   'title' =>  'Entebbe, Uganda (Actually, In the Lake, South of Entebbe)',
        'result' => 'Africa/Kampala',
        'params' => [
                        'lng' => 32.600395,
                        'lat' => 0
                    ]
    ],
    [   'title' =>  'Falkland Islands',
        'result' => 'Atlantic/Stanley',
        'params' => [
                        'lng' => -58.719566,
                        'lat' => -51.591525
                    ]
    ],
    [   'title' =>  'Fort Liberté, Haiti',
        'result' => 'America/Port-au-Prince',
        'params' => [
                        'lng' => -71.835976,
                        'lat' => 19.663983
                    ]
    ],
    [   'title' =>  'French Polynesia',
        'result' => 'Pacific/Tahiti',
        'params' => [
                        'lng' => -145.583614,
                        'lat' => -17.365464
                    ]
    ],
    [   'title' =>  'Galway, Ireland',
        'result' => 'Europe/Dublin',
        'params' => [
                        'lng' => -9.04906,
                        'lat' => 53.274412
                    ]
    ],
    [   'title' =>  'Gary, Indiana',
        'result' => 'America/Chicago',
        'params' => [
                        'lng' => -87.33164,
                        'lat' => 41.601978
                    ]
    ],
    [   'title' =>  'Golema Crcorija, Macedonia',
        'result' => 'Europe/Skopje',
        'params' => [
                        'lng' => 22.354002,
                        'lat' => 42.307866
                    ]
    ],
    [   'title' =>  'Greenland (All the Way to the Tippy-Top)',
        'result' => 'America/Nuuk',
        'params' => [
                        'lng' => -31.304008,
                        'lat' => 83.520162
                    ]
    ],
    [   'title' =>  'Greenland (East Coast)',
        'result' => 'America/Nuuk',
        'params' => [
                        'lng' => -25.051294,
                        'lat' => 73.554519
                    ]
    ],
    [   'title' =>  'Greenwich, United Kingdom',
        'result' => 'Europe/London',
        'params' => [
                        'lng' => 0,
                        'lat' => 51.482578
                    ]
    ],
    [   'title' =>  'Guam',
        'result' => 'Pacific/Guam',
        'params' => [
                        'lng' => 144.757551,
                        'lat' => 13.450126
                    ]
    ],
    [   'title' =>  'Havana, Cuba',
        'result' => 'America/Havana',
        'params' => [
                        'lng' => -82.358963,
                        'lat' => 23.135305
                    ]
    ],
    [   'title' =>  'Helsinki',
        'result' => 'Europe/Helsinki',
        'params' => [
                        'lng' => 24.931838,
                        'lat' => 60.216934
                    ]
    ],
    [   'title' =>  'Herschel Island, Canada',
        'result' => 'America/Dawson',
        'params' => [
                        'lng' => -139.066651,
                        'lat' => 69.615974
                    ]
    ],
    [   'title' =>  'Hobart, Australia',
        'result' => 'Australia/Hobart',
        'params' => [
                        'lng' => 147.387644,
                        'lat' => -42.892064
                    ]
    ],
    [   'title' =>  'Honolulu',
        'result' => 'Pacific/Honolulu',
        'params' => [
                        'lng' => -157.855676,
                        'lat' => 21.304547
                    ]
    ],
    [   'title' =>  'Hulapai Reservation, Arizona',
        'result' => 'America/Phoenix',
        'params' => [
                        'lng' => -113.064321,
                        'lat' => 36.021309
                    ]
    ],
    [   'title' =>  'Hwange, Zimbabwe',
        'result' => 'Africa/Harare',
        'params' => [
                        'lng' => 25.290954,
                        'lat' => -17.864113
                    ]
    ],
    [   'title' =>  'Îles Gambier',
        'result' => 'Pacific/Gambier',
        'params' => [
                        'lng' => -134.954092,
                        'lat' => -23.157164
                    ]
    ],
    [   'title' =>  'Îles Marquesas',
        'result' => 'Pacific/Marquesas',
        'params' => [
                        'lng' => -138.986576,
                        'lat' => -9.774991
                    ]
    ],
    [   'title' =>  'Irkutsk, Russia',
        'result' => 'Asia/Irkutsk',
        'params' => [
                        'lng' => 104.719221,
                        'lat' => 56.637012
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
    [   'title' =>  'Izhevsk, Russia',
        'result' => 'Europe/Samara',
        'params' => [
                        'lng' => 53.209417,
                        'lat' => 56.866557
                    ]
    ],
    [   'title' =>  'Jan Mayen',
        'result' => 'Europe/Oslo',
        'params' => [
                        'lng' => -8.352066,
                        'lat' => 70.993147
                    ]
    ],
    [   'title' =>  'Jarvis Island <em>(NOTE: Returns an &quot;Etc&quot; time zone)</em>',
        'result' => 'Etc/GMT+11',
        'params' => [
                        'lng' => -159.997361,
                        'lat' => -0.372281
                    ]
    ],
    [   'title' =>  'Jayapura, New Guinea',
        'result' => 'Asia/Jayapura',
        'params' => [
                        'lng' => 140.674007,
                        'lat' => -2.609334
                    ]
    ],
    [   'title' =>  'Jersey, Channel Islands',
        'result' => 'Europe/Jersey',
        'params' => [
                        'lng' => -2.13753,
                        'lat' => 49.2167
                    ]
    ],
    [   'title' =>  'Jimaní, Dominican Republic',
        'result' => 'America/Santo_Domingo',
        'params' => [
                        'lng' => -71.853829,
                        'lat' => 18.493341
                    ]
    ],
    [   'title' =>  'Jordan (Close to Israel)',
        'result' => 'Asia/Hebron',
        'params' => [
                        'lng' => 35.207436,
                        'lat' => 32.314991
                    ]
    ],
    [   'title' =>  'Kabanga, Tanzania',
        'result' => 'Africa/Dar_es_Salaam',
        'params' => [
                        'lng' => 30.468192,
                        'lat' => -2.638472
                    ]
    ],
    [   'title' =>  'Kaktovik, Alaska',
        'result' => 'America/Anchorage',
        'params' => [
                        'lng' => -143.621573,
                        'lat' => 70.129688
                    ]
    ],
    [   'title' =>  'Kampala, Uganda',
        'result' => 'Africa/Kampala',
        'params' => [
                        'lng' => 32.600395,
                        'lat' => 0.267742
                    ]
    ],
    [   'title' =>  'Kasane, Botswana',
        'result' => 'Africa/Gaborone',
        'params' => [
                        'lng' => 25.156854,
                        'lat' => -17.787516
                    ]
    ],
    [   'title' =>  'Katima Mulilo, Namibia',
        'result' => 'Africa/Windhoek',
        'params' => [
                        'lng' => 24.276266,
                        'lat' => -17.523431
                    ]
    ],
    [   'title' =>  'Key West, Florida',
        'result' => 'America/New_York',
        'params' => [
                        'lng' => -81.786597,
                        'lat' => 24.553227
                    ]
    ],
    [   'title' =>  'Kingston, Jamaica',
        'result' => 'America/Jamaica',
        'params' => [
                        'lng' => -76.792813,
                        'lat' => 17.971215
                    ]
    ],
    [   'title' =>  'Kiribati',
        'result' => 'Pacific/Kiritimati',
        'params' => [
                        'lng' => -157.435158,
                        'lat' => 1.87096
                    ]
    ],
    [   'title' =>  'Kisu, Uganda',
        'result' => 'Africa/Kampala',
        'params' => [
                        'lng' => 32.78,
                        'lat' => 0
                    ]
    ],
    [   'title' =>  'Kisangani, Congo',
        'result' => 'Africa/Lubumbashi',
        'params' => [
                        'lng' => 25.205729,
                        'lat' => 0.518402
                    ]
    ],
    [   'title' =>  'Kosovo',
        'result' => 'Europe/Belgrade',
        'params' => [
                        'lng' => 20.933892,
                        'lat' => 42.650122
                    ]
    ],
    [   'title' =>  'København (Copenhagen)',
        'result' => 'Europe/Copenhagen',
        'params' => [
                        'lng' => 12.570072,
                        'lat' => 55.686724
                    ]
    ],
    [   'title' =>  'Kosovo',
        'result' => 'Europe/Belgrade',
        'params' => [
                        'lng' => 20.933892,
                        'lat' => 42.650122
                    ]
    ],
    [   'title' =>  'Kuril Islands',
        'result' => 'Asia/Srednekolymsk',
        'params' => [
                        'lng' => 155.832214,
                        'lat' => 50.384038
                    ]
    ],
    [   'title' =>  'Lakshadweep',
        'result' => 'Asia/Kolkata',
        'params' => [
                        'lng' => 72.63231,
                        'lat' => 10.557337
                    ]
    ],
    [   'title' =>  'La Paz, Bolivia',
        'result' => 'America/La_Paz',
        'params' => [
                        'lng' => -68.148878,
                        'lat' => -16.522396
                    ]
    ],
    [   'title' =>  'Lesotho',
        'result' => 'Africa/Johannesburg',
        'params' => [
                        'lng' => 28.335019,
                        'lat' => -29.603927
                    ]
    ],
    [   'title' =>  'Livinstone, Zambia',
        'result' => 'Africa/Lusaka',
        'params' => [
                        'lng' => 25.858643,
                        'lat' => -17.847094
                    ]
    ],
    [   'title' =>  'Los Angeles',
        'result' => 'America/Los_Angeles',
        'params' => [
                        'lng' => -118.242766,
                        'lat' => 34.053691
                    ]
    ],
    [   'title' =>  'Madagascar',
        'result' => 'Asia/Riyadh',
        'params' => [
                        'lng' => 46.441642,
                        'lat' => 18.92496
                    ]
    ],
    [   'title' =>  'Madara, Egypt',
        'result' => 'Africa/Cairo',
        'params' => [
                        'lng' => 27.239941,
                        'lat' => 31.35427
                    ]
    ],
    [   'title' =>  'Magadan, Russia',
        'result' => 'Asia/Magadan',
        'params' => [
                        'lng' => 150.780632,
                        'lat' => 59.58117
                    ]
    ],
    [   'title' =>  'Malawi',
        'result' => 'Africa/Blantyre',
        'params' => [
                        'lng' => 33.930196,
                        'lat' => -13.26872
                    ]
    ],
    [   'title' =>  'Maldives',
        'result' => 'Indian/Maldives',
        'params' => [
                        'lng' => 73.525455,
                        'lat' => 4.160202
                    ]
    ],
    [   'title' =>  'Malta',
        'result' => 'Europe/Malta',
        'params' => [
                        'lng' => 14.447691,
                        'lat' => 35.888599
                    ]
    ],
    [   'title' =>  'Mauritius',
        'result' => 'Indian/Mauritius',
        'params' => [
                        'lng' => 57.570357,
                        'lat' => -20.275945
                    ]
    ],
    [   'title' =>  'Meat Cove, Nova Scotia',
        'result' => 'America/Glace_Bay',
        'params' => [
                        'lng' => -60.565287,
                        'lat' => 47.027044
                    ]
    ],
    [   'title' =>  'Melchor de Mencos, Guatemala',
        'result' => 'America/Guatemala',
        'params' => [
                        'lng' => -89.154505,
                        'lat' => 17.064157
                    ]
    ],
    [   'title' =>  'Merauke, New Guinea',
        'result' => 'Asia/Jayapura',
        'params' => [
                        'lng' => 140.976131,
                        'lat' => -9.075947
                    ]
    ],
    [   'title' =>  'Michigan City, Indiana',
        'result' => 'America/Chicago',
        'params' => [
                        'lng' => -86.904771,
                        'lat' => 41.722852
                    ]
    ],
    [   'title' =>  'Minami-Tori-shima',
        'result' => 'Asia/Tokyo',
        'params' => [
                        'lng' => 153.978512,
                        'lat' => 24.284017
                    ]
    ],
    [   'title' =>  'Miras, Albania',
        'result' => 'Europe/Tirane',
        'params' => [
                        'lng' => 20.928681,
                        'lat' => 40.508075
                    ]
    ],
    [   'title' =>  'Moscow',
        'result' => 'Europe/Moscow',
        'params' => [
                        'lng' => 37.617494,
                        'lat' => 55.750446
                    ]
    ],
    [   'title' =>  'Mtwara, Tanzania',
        'result' => 'Africa/Dar_es_Salaam',
        'params' => [
                        'lng' => 40.314692,
                        'lat' => -10.407115
                    ]
    ],
    [   'title' =>  'Mukoni, Burundi',
        'result' => 'Africa/Bujumbura',
        'params' => [
                        'lng' => 30.420256,
                        'lat' => -2.63822
                    ]
    ],
    [   'title' =>  'Musu, Papua New Guinea',
        'result' => 'Pacific/Port_Moresby',
        'params' => [
                        'lng' => 141.149166,
                        'lat' => -2.669695
                    ]
    ],
    [   'title' =>  'Nairobi, Kenya',
        'result' => 'Africa/Nairobi',
        'params' => [
                        'lng' => 36.824456,
                        'lat' => -1.301696
                    ]
    ],
    [   'title' =>  'Naujaat, Canada',
        'result' => 'America/Rankin_Inlet',
        'params' => [
                        'lng' => -86.232047,
                        'lat' => 66.52669
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
    [   'title' =>  'Niceville, Florida',
        'result' => 'America/Chicago',
        'params' => [
                        'lng' => -86.47737,
                        'lat' => 30.523166
                    ]
    ],
    [   'title' =>  'Niles, Michigan',
        'result' => 'America/Detroit',
        'params' => [
                        'lng' => -86.255862,
                        'lat' => 41.825545
                    ]
    ],
    [   'title' =>  'Novosibirsk, Russia',
        'result' => 'Asia/Novosibirsk',
        'params' => [
                        'lng' => 82.922689,
                        'lat' => 55.028831
                    ]
    ],
    [   'title' =>  'Null Island <em>(NOTE: Returns an &quot;Etc&quot; time zone)</em>',
        'result' => 'Etc/GMT',
        'params' => [
                        'lng' => 0,
                        'lat' => 0
                    ]
    ],
    [   'title' =>  'Nuuk, Greenland',
        'result' => 'America/Nuuk',
        'params' => [
                        'lng' => -51.785176,
                        'lat' => 64.199023
                    ]
    ],
    [   'title' =>  'Ollaraya, Peru',
        'result' => 'America/Lima',
        'params' => [
                        'lng' => -69.015137,
                        'lat' => -16.246348
                    ]
    ],
    [   'title' =>  'Olympic Coast Marine Sanctuary',
        'result' => 'America/Los_Angeles',
        'params' => [
                        'lng' => -124.607409,
                        'lat' => 48.308774
                    ]
    ],
    [   'title' =>  'Oman',
        'result' => 'Asia/Muscat',
        'params' => [
                        'lng' => 57.00369,
                        'lat' => 21.000029
                    ]
    ],
    [   'title' =>  'Omsk, Russia',
        'result' => 'Asia/Omsk',
        'params' => [
                        'lng' => 73.371529,
                        'lat' => 54.991375
                    ]
    ],
    [   'title' =>  'Omeath, Ireland',
        'result' => 'Europe/Dublin',
        'params' => [
                        'lng' => -6.261241,
                        'lat' => 54.088695
                    ]
    ],
    [   'title' =>  'Panama City, Florida',
        'result' => 'America/Chicago',
        'params' => [
                        'lng' => -85.663221,
                        'lat' => 30.157734
                    ]
    ],
    [   'title' =>  'Papua New Guinea (Southwest Corner)',
        'result' => 'Pacific/Port_Moresby',
        'params' => [
                        'lng' => 141.047542,
                        'lat' => -9.124763
                    ]
    ],
    [   'title' =>  'Pensacola, Florida',
        'result' => 'America/Chicago',
        'params' => [
                        'lng' => -87.222669,
                        'lat' => 30.410414
                    ]
    ],
    [   'title' =>  'Pepillo Salcedo, Dominican Republic',
        'result' => 'America/Santo_Domingo',
        'params' => [
                        'lng' => -71.746712,
                        'lat' => 19.697603
                    ]
    ],
    [   'title' =>  'Pitcairn Islands',
        'result' => 'Pacific/Pitcairn',
        'params' => [
                        'lng' => -130.101782,
                        'lat' => -25.065772
                    ]
    ],
    [   'title' =>  'Port Au Prince, Haiti',
        'result' => 'America/Port-au-Prince',
        'params' => [
                        'lng' => -72.350736,
                        'lat' => 18.533356
                    ]
    ],
    [   'title' =>  'Porto Velho, Brazil',
        'result' => 'America/Porto_Velho',
        'params' => [
                        'lng' => -63.960999,
                        'lat' => -8.715448
                    ]
    ],
    [   'title' =>  'Prince Edward Island, Canada',
        'result' => 'America/Halifax',
        'params' => [
                        'lng' => -63.595411,
                        'lat' => 46.503681
                    ]
    ],
    [   'title' =>  'Prince Edward Islands, South Africa',
        'result' => 'Africa/Johannesburg',
        'params' => [
                        'lng' => 37.83243,
                        'lat' => -46.7455
                    ]
    ],
    [   'title' =>  'Prrenjas, Albania',
        'result' => 'Europe/Tirane',
        'params' => [
                        'lng' => 20.530188,
                        'lat' => 41.07005
                    ]
    ],
    [   'title' =>  'Prudhoe Bay, Alaska',
        'result' => 'America/Anchorage',
        'params' => [
                        'lng' => -148.397837,
                        'lat' => 70.245011
                    ]
    ],
    [   'title' =>  'Qikiqtaaluk, Canada',
        'result' => 'America/Iqaluit',
        'params' => [
                        'lng' => -78.390141,
                        'lat' => 77.492724
                    ]
    ],
    [   'title' =>  'Resbalosa, Panama',
        'result' => 'America/Panama',
        'params' => [
                        'lng' => -82.893398,
                        'lat' => 8.039456
                    ]
    ],
    [   'title' =>  'Resolution Island',
        'result' => 'America/Iqaluit',
        'params' => [
                        'lng' => -65.00855,
                        'lat' => 61.530029
                    ]
    ],
    [   'title' =>  'Revillagigedo Islands',
        'result' => 'America/Mazatlan',
        'params' => [
                        'lng' => -110.982031,
                        'lat' => 18.792742
                    ]
    ],
    [   'title' =>  'Revoso, Bulgaria',
        'result' => 'Europe/Sofia',
        'params' => [
                        'lng' => 28.017705,
                        'lat' => 41.990881
                    ]
    ],
    [   'title' =>  'Rural Montana',
        'result' => 'America/Denver',
        'params' => [
                        'lng' => -109.353259,
                        'lat' => 47.219568
                    ]
    ],
    [   'title' =>  'Russian Islands (In Arctic Ocean)',
        'result' => 'Europe/Moscow',
        'params' => [
                        'lng' => 49.904964,
                        'lat' => 80.816891
                    ]
    ],
    [   'title' =>  'Reykjavik, Iceland',
        'result' => 'Atlantic/Reykjavik',
        'params' => [
                        'lng' => -21.873763,
                        'lat' => 64.132082
                    ]
    ],
    [   'title' =>  'Riyadh, Saudi Arabia',
        'result' => 'Asia/Riyadh',
        'params' => [
                        'lng' => 46.71601,
                        'lat' => 24.638916
                    ]
    ],
    [   'title' =>  'Samnak Kham, Thailand',
        'result' => 'Asia/Bangkok',
        'params' => [
                        'lng' => 100.384853,
                        'lat' => 6.585328
                    ]
    ],
    [   'title' =>  'Samoa <em>(NOTE: Returns an &quot;Etc&quot; time zone)</em>',
        'result' => 'Etc/GMT+11',
        'params' => [
                        'lng' => -172.43581,
                        'lat' => 13.598793
                    ]
    ],
    [   'title' =>  'San Diego',
        'result' => 'America/Los_Angeles',
        'params' => [
                        'lng' => -117.163121,
                        'lat' => 32.718362
                    ]
    ],
    [   'title' =>  'San Ignacio, Belize',
        'result' => 'America/Belize',
        'params' => [
                        'lng' => -89.070632,
                        'lat' => 17.153497
                    ]
    ],
    [   'title' =>  'San Juan, Puerto Rico',
        'result' => 'America/Puerto_Rico',
        'params' => [
                        'lng' => -66.062673,
                        'lat' => 18.389789
                    ]
    ],
    [   'title' =>  'São Tomé and Príncipe',
        'result' => 'Africa/Sao_Tome',
        'params' => [
                        'lng' => 6.62532,
                        'lat' => 0.239759
                    ]
    ],
    [   'title' =>  'Seattle',
        'result' => 'America/Los_Angeles',
        'params' => [
                        'lng' => -122.342714,
                        'lat' => 47.580231
                    ]
    ],
    [   'title' =>  'Seoul',
        'result' => 'Asia/Seoul',
        'params' => [
                        'lng' => 126.978291,
                        'lat' => 37.566679
                    ]
    ],
    [   'title' =>  'Serbia (Soutwest Corner)',
        'result' => 'Europe/Belgrade',
        'params' => [
                        'lng' => 22.355495,
                        'lat' => 42.319638
                    ]
    ],
    [   'title' =>  'Sermersooq, Greenland',
        'result' => 'America/Scoresbysund',
        'params' => [
                        'lng' => -21.784272,
                        'lat' => 70.506184
                    ]
    ],
    [   'title' =>  'Seychelles',
        'result' => 'Indian/Mahe',
        'params' => [
                        'lng' => 55.454015,
                        'lat' => -4.657498
                    ]
    ],
    [   'title' =>  'Sinai Peninsula (Southern Tip)',
        'result' => 'Africa/Cairo',
        'params' => [
                        'lng' => 34.209576,
                        'lat' => 27.911913
                    ]
    ],
    [   'title' =>  'Singapore',
        'result' => 'Asia/Singapore',
        'params' => [
                        'lng' => 103.819499,
                        'lat' => 1.357107
                    ]
    ],
    [   'title' =>  'Sintok, Malaysia',
        'result' => 'Asia/Kuala_Lumpur',
        'params' => [
                        'lng' => 100.498239,
                        'lat' => 6.457729
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
    [   'title' =>  'South Bend, Indiana',
        'result' => 'America/Indiana/Indianapolis',
        'params' => [
                        'lng' => -86.257583,
                        'lat' => 41.689441
                    ]
    ],
    [   'title' =>  'Staten Island (But Not the One You Think)',
        'result' => 'America/Argentina/Ushuaia',
        'params' => [
                        'lng' => -63.867503,
                        'lat' => -54.756304
                    ]
    ],
    [   'title' =>  'Struga, Macedonia',
        'result' => 'Europe/Skopje',
        'params' => [
                        'lng' => 20.674959,
                        'lat' => 41.180227
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
    [   'title' =>  'Tallahassee Florida',
        'result' => 'America/New_York',
        'params' => [
                        'lng' => -84.274177,
                        'lat' => 30.43713
                    ]
    ],
    [   'title' =>  'Tempe, Arizona',
        'result' => 'America/Phoenix',
        'params' => [
                        'lng' => -111.940016,
                        'lat' => 33.425512
                    ]
    ],
    [   'title' =>  'Thule, Greenland',
        'result' => 'America/Thule',
        'params' => [
                        'lng' => -69.222275,
                        'lat' => 77.468636
                    ]
    ],
    [   'title' =>  'Tijuana, Mexico',
        'result' => 'America/Tijuana',
        'params' => [
                        'lng' => -116.956683,
                        'lat' => 32.530506
                    ]
    ],
    [   'title' =>  'Timmins, Ontario',
        'result' => 'America/Toronto',
        'params' => [
                        'lng' => -81.330414,
                        'lat' => 48.477473
                    ]
    ],
    [   'title' =>  'Tobruk, Libya',
        'result' => 'Africa/Tripoli',
        'params' => [
                        'lng' => 23.948159,
                        'lat' => 32.096059
                    ]
    ],
    [   'title' =>  'Tokyo',
        'result' => 'Asia/Tokyo',
        'params' => [
                        'lng' => 139.757653,
                        'lat' => 35.681267
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
    [   'title' =>  'Tureia Island',
        'result' => 'Pacific/Tahiti',
        'params' => [
                        'lng' => -138.791699,
                        'lat' => -21.844161
                    ]
    ],
    [   'title' =>  'US Virgin Islands',
        'result' => 'America/St_Thomas',
        'params' => [
                        'lng' => -64.934509,
                        'lat' => 18.338845
                    ]
    ],
    [   'title' =>  'Valparaiso, Indiana',
        'result' => 'America/Chicago',
        'params' => [
                        'lng' => -87.061404,
                        'lat' => 41.46798
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
    ],
    [   'title' =>  'Vladivostok, Russia',
        'result' => 'Asia/Vladivostok',
        'params' => [
                        'lng' => 131.885577,
                        'lat' => 43.115068
                    ]
    ],
    [   'title' =>  'Warrenpoint, Ireland',
        'result' => 'Europe/London',
        'params' => [
                        'lng' => -6.254489,
                        'lat' => 54.100993
                    ]
    ],
    [   'title' =>  'Washington DC',
        'result' => 'America/New_York',
        'params' => [
                        'lng' => -77.036543,
                        'lat' => 38.895037
                    ]
    ],
    [   'title' =>  'Wilcannia, Australia',
        'result' => 'Australia/Sydney',
        'params' => [
                        'lng' => 143.37849,
                        'lat' => -31.558849
                    ]
    ],
    [   'title' =>  'Winnipeg',
        'result' => 'America/Winnipeg',
        'params' => [
                        'lng' => -97.138458,
                        'lat' => 49.895537
                    ]
    ],
    [   'title' =>  'Yakutsk, Russia',
        'result' => 'Asia/Yakutsk',
        'params' => [
                        'lng' => 129.731979,
                        'lat' => 62.027408
                    ]
    ],
    [   'title' =>  'Yekaterinburg, Russia',
        'result' => 'Asia/Yekaterinburg',
        'params' => [
                        'lng' => 60.60825,
                        'lat' => 56.839104
                    ]
    ],
    [   'title' =>  'Yuzhno-Sakhalinsk, Russia',
        'result' => 'Asia/Sakhalin',
        'params' => [
                        'lng' => 142.75186,
                        'lat' => 46.954849
                    ]
    ],
    [   'title' =>  'Zanzibar, Tanzania',
        'result' => 'Africa/Dar_es_Salaam',
        'params' => [
                        'lng' => 39.192454,
                        'lat' => -6.167184
                    ]
    ],
    [   'title' =>  'Διποταμιά - Καλή Βρύση, Greece',
        'result' => 'Europe/Athens',
        'params' => [
                        'lng' => 20.976938,
                        'lat' => 40.481592
                    ]
    ]
];
