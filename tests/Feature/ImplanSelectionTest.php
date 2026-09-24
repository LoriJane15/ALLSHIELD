<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\GovAgency;
use App\Models\Municipality;
use App\Models\RcspBarangay;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImplanSelectionTest extends TestCase
{
    use RefreshDatabase;

    private User $lgu;

    /** @var array<int, RcspBarangay> */
    private array $targetAreas;

    /** @var array<int, GovAgency> */
    private array $agencies;

    protected function setUp(): void
    {
        parent::setUp();

        $municipality = Municipality::create(['name' => 'Selection Test Municipality']);
        $this->lgu = User::factory()->lgu($municipality->id)->create();

        foreach (['First Target Area', 'Second Target Area'] as $name) {
            $barangay = Barangay::create([
                'municipality_id' => $municipality->id,
                'name' => $name,
            ]);
            $this->targetAreas[] = RcspBarangay::create([
                'municipality_id' => $municipality->id,
                'barangay_id' => $barangay->id,
            ]);
        }

        $this->agencies = [
            GovAgency::create(['name' => 'First Responsible Agency', 'acronym' => 'FRA']),
            GovAgency::create(['name' => 'Second Responsible Agency', 'acronym' => 'SRA']),
        ];

        $this->assertSame($this->targetAreas[0]->id, $this->agencies[0]->id);
        $this->assertSame($this->targetAreas[1]->id, $this->agencies[1]->id);
    }

    public function test_selection_groups_render_as_independent_skydash_checkboxes(): void
    {
        $xpath = $this->renderForm();
        $targetInputs = $xpath->query('//input[@name="target_areas[]"]');
        $agencyInputs = $xpath->query('//input[@name="agencies[]"]');

        $this->assertCount(2, $targetInputs);
        $this->assertCount(2, $agencyInputs);

        $ids = [];
        foreach ($targetInputs as $index => $input) {
            $this->assertInstanceOf(DOMElement::class, $input);
            $this->assertSame('checkbox', $input->getAttribute('type'));
            $this->assertSame('target_areas[]', $input->getAttribute('name'));
            $this->assertSame(
                'implan-target-area-'.$this->targetAreas[$index]->id,
                $input->getAttribute('id')
            );
            $this->assertSkyDashCheckboxStructure($input);
            $ids[] = $input->getAttribute('id');
        }

        foreach ($agencyInputs as $index => $input) {
            $this->assertInstanceOf(DOMElement::class, $input);
            $this->assertSame('checkbox', $input->getAttribute('type'));
            $this->assertSame('agencies[]', $input->getAttribute('name'));
            $this->assertSame(
                'implan-responsible-agency-'.$this->agencies[$index]->id,
                $input->getAttribute('id')
            );
            $this->assertSkyDashCheckboxStructure($input);
            $ids[] = $input->getAttribute('id');
        }

        $this->assertCount(4, array_unique($ids));
        $this->assertNotSame($targetInputs->item(0)->getAttribute('id'), $agencyInputs->item(0)->getAttribute('id'));
    }

    public function test_old_input_restores_multiple_values_in_both_groups_simultaneously(): void
    {
        $xpath = $this->renderForm([
            'issues' => 'Preserved issue text',
            'target_areas' => array_map(fn (RcspBarangay $area) => (string) $area->id, $this->targetAreas),
            'agencies' => array_map(fn (GovAgency $agency) => (string) $agency->id, $this->agencies),
        ]);

        $this->assertSame('Preserved issue text', trim($xpath->evaluate('string(//textarea[@name="issues"])')));
        $this->assertCheckedValues($xpath, 'target_areas[]', array_column($this->targetAreas, 'id'));
        $this->assertCheckedValues($xpath, 'agencies[]', array_column($this->agencies, 'id'));
    }

    public function test_restoring_one_group_does_not_change_the_other_group(): void
    {
        $targetOnly = $this->renderForm([
            'target_areas' => [(string) $this->targetAreas[0]->id],
        ]);
        $this->assertCheckedValues($targetOnly, 'target_areas[]', [$this->targetAreas[0]->id]);
        $this->assertCheckedValues($targetOnly, 'agencies[]', []);

        $agencyOnly = $this->renderForm([
            'agencies' => [(string) $this->agencies[1]->id],
        ]);
        $this->assertCheckedValues($agencyOnly, 'target_areas[]', []);
        $this->assertCheckedValues($agencyOnly, 'agencies[]', [$this->agencies[1]->id]);
    }

    private function renderForm(array $oldInput = []): DOMXPath
    {
        $response = $this->actingAs($this->lgu)
            ->withSession(['_old_input' => $oldInput])
            ->get(route('lgu.implan.index'))
            ->assertOk();

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function assertSkyDashCheckboxStructure(DOMElement $input): void
    {
        $label = $input->parentNode;

        $this->assertInstanceOf(DOMElement::class, $label);
        $this->assertSame('label', $label->tagName);
        $this->assertStringContainsString('form-check-label', $label->getAttribute('class'));
        $this->assertSame($input->getAttribute('id'), $label->getAttribute('for'));

        $firstElement = null;
        foreach ($label->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $firstElement = $child;
                break;
            }
        }

        $this->assertSame($input, $firstElement);
    }

    private function assertCheckedValues(DOMXPath $xpath, string $name, array $expected): void
    {
        $inputs = $xpath->query(sprintf('//input[@name="%s"]', $name));
        $checked = [];

        foreach ($inputs as $input) {
            if ($input instanceof DOMElement && $input->hasAttribute('checked')) {
                $checked[] = (int) $input->getAttribute('value');
            }
        }

        $this->assertSame($expected, $checked);
    }
}
