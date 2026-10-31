<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logs_can_be_written()
    {
        DB::table('audit_logs')->insert(['action' => 'test.created']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'test.created']);
    }

    public function test_audit_logs_cannot_be_updated()
    {
        DB::table('audit_logs')->insert(['action' => 'test.created']);

        $this->expectException(QueryException::class);

        DB::table('audit_logs')->update(['action' => 'test.tampered']);
    }

    public function test_audit_logs_cannot_be_deleted()
    {
        DB::table('audit_logs')->insert(['action' => 'test.created']);

        $this->expectException(QueryException::class);

        DB::table('audit_logs')->delete();
    }
}
