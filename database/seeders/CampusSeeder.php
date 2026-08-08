<?php

namespace Database\Seeders;

use App\Models\Campus;
use Illuminate\Database\Seeder;

class CampusSeeder extends Seeder
{
    public function run(): void
    {
        $campuses = [
            ['name' => 'Tandag Campus',     'code' => 'TAN', 'address' => 'Tandag City, Surigao del Sur'],
            ['name' => 'Cantilan Campus',   'code' => 'CAN', 'address' => 'Cantilan, Surigao del Sur'   ],
            ['name' => 'Lianga Campus',     'code' => 'LIA', 'address' => 'Lianga, Surigao del Sur'     ],
            ['name' => 'Cagwait Campus',    'code' => 'CAG', 'address' => 'Cagwait, Surigao del Sur'    ],
            ['name' => 'San Miguel Campus', 'code' => 'SMC', 'address' => 'San Miguel, Surigao del Sur' ],
            ['name' => 'Tagbina Campus',    'code' => 'TAG', 'address' => 'Tagbina, Surigao del Sur'    ],
            ['name' => 'Bislig Campus',     'code' => 'BIS', 'address' => 'Bislig City, Surigao del Sur'],
        ];

        foreach ($campuses as $campus) {
            Campus::firstOrCreate(['code' => $campus['code']], $campus);
        }
    }
}
