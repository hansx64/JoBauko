<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Municipality;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_all_barangays_of_bauko(): void
    {
        $this->seed(LocationSeeder::class);

        $bauko = Municipality::where('name', 'Bauko')->firstOrFail();

        $this->assertSame('Mountain Province', $bauko->province->name);
        $this->assertSame(22, $bauko->barangays()->count());
        $this->assertSame(22, Barangay::count());
    }

    public function test_it_can_run_more_than_once_without_duplicates(): void
    {
        $this->seed(LocationSeeder::class);
        $this->seed(LocationSeeder::class);

        $this->assertSame(22, Barangay::count());
    }
}
