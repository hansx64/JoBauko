<?php

namespace Database\Seeders;

use App\Models\Municipality;
use App\Models\Province;
use Illuminate\Database\Seeder;

/**
 * Seeds the service area: the 22 barangays of Bauko, Mountain Province.
 *
 * Province and municipality PSGC codes are from the PSA. Barangay codes are
 * filled only where verified against the PSA listing; the rest stay null
 * until confirmed, rather than guessing.
 */
class LocationSeeder extends Seeder
{
    /**
     * Barangay name => PSGC code (null when not yet verified).
     *
     * @var array<string, string|null>
     */
    private const BAUKO_BARANGAYS = [
        'Abatan' => '1404402001',
        'Bagnen Oriente' => null,
        'Bagnen Proper' => null,
        'Balintaugan' => null,
        'Banao' => null,
        'Bila (Bua)' => null,
        'Guinzadan Central' => null,
        'Guinzadan Norte' => null,
        'Guinzadan Sur' => null,
        'Lagawa' => null,
        'Leseb' => null,
        'Mabaay' => null,
        'Mayag' => null,
        'Monamon Norte' => null,
        'Monamon Sur' => null,
        'Mount Data' => null,
        'Otucan Norte' => null,
        'Otucan Sur' => null,
        'Poblacion' => null,
        'Sadsadan' => null,
        'Sinto' => null,
        'Tapapan' => '1404402023',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $province = Province::updateOrCreate(
            ['name' => 'Mountain Province'],
            ['psgc_code' => '1404400000'],
        );

        $municipality = Municipality::updateOrCreate(
            ['province_id' => $province->id, 'name' => 'Bauko'],
            ['psgc_code' => '1404402000'],
        );

        foreach (self::BAUKO_BARANGAYS as $name => $code) {
            $municipality->barangays()->updateOrCreate(
                ['name' => $name],
                ['psgc_code' => $code],
            );
        }
    }
}
