<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Receipts\Parsing\ParsedReceipt;
use App\Receipts\StoreIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreIdentifierTest extends TestCase
{
    use RefreshDatabase;

    private function identify(string $text, ?string $abn = null): ?Store
    {
        return (new StoreIdentifier)->identify(new ParsedReceipt(abn: $abn), $text);
    }

    public function testIdentifiesTheStoreByAbnFirst()
    {
        $coles = Store::factory()->create(['name' => 'Coles', 'abn' => '45004189708', 'aliases' => ['COLES']]);
        Store::factory()->create(['name' => 'Woolworths', 'aliases' => ['WOOLWORTHS']]);

        $this->assertTrue($coles->is($this->identify('WOOLWORTHS', '45004189708')));
    }

    public function testTheLongestAliasInTheHeaderWins()
    {
        Store::factory()->create(['name' => 'Coles', 'aliases' => ['COLES']]);
        $express = Store::factory()->create(['name' => 'Coles Express', 'aliases' => ['COLES EXPRESS']]);

        $this->assertTrue($express->is($this->identify("coles express\nNorth Adelaide")));
    }

    public function testAliasesMustBeWholeWords()
    {
        Store::factory()->create(['name' => 'IGA', 'aliases' => ['IGA']]);

        $this->assertNull($this->identify("BRIGADOON BAKERY\nTOTAL 3.00"));
        $this->assertNotNull($this->identify("SUPA IGA\nTOTAL 3.00"));
    }
}
