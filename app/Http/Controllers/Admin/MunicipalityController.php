<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReferralDestination;
use App\Enums\ViolenceType;
use App\Http\Controllers\Controller;
use App\Models\FlowStep;
use App\Models\Municipality;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MunicipalityController extends Controller
{
    public function index()
    {
        $this->authorize('admin');

        return view('admin.municipios.index', ['municipalities' => Municipality::withCount(['users', 'cases', 'flowSteps'])->orderBy('name')->get()]);
    }

    public function create()
    {
        $this->authorize('admin');

        return view('admin.municipios.form', ['municipality' => new Municipality(['active' => true, 'accepts_public_reports' => true])]);
    }

    public function store(Request $request)
    {
        $this->authorize('admin');
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name'].'-'.$data['uf']);
        $m = Municipality::create($data);

        return redirect()->route('admin.municipios.fluxo', $m)->with('ok', 'Município criado. Configure o fluxo de atendimento.');
    }

    public function edit(Municipality $municipality)
    {
        $this->authorize('admin');

        return view('admin.municipios.form', ['municipality' => $municipality]);
    }

    public function update(Request $request, Municipality $municipality)
    {
        $this->authorize('admin');
        $municipality->update($this->validated($request, $municipality));

        return redirect()->route('admin.municipios.index')->with('ok', 'Município atualizado.');
    }

    /** Fluxo municipal: editável pelo admin e pela coordenação do próprio município. */
    public function flow(Municipality $municipality)
    {
        $this->authorize('manageFlow', $municipality);

        return view('admin.municipios.fluxo', [
            'municipality' => $municipality->load('flowSteps'),
            'destinations' => ReferralDestination::cases(),
            'violenceTypes' => ViolenceType::cases(),
        ]);
    }

    public function storeStep(Request $request, Municipality $municipality)
    {
        $this->authorize('manageFlow', $municipality);
        $municipality->flowSteps()->create($this->stepData($request) + ['position' => ($municipality->flowSteps()->max('position') ?? 0) + 1]);

        return back()->with('ok', 'Etapa adicionada.');
    }

    public function updateStep(Request $request, Municipality $municipality, FlowStep $step)
    {
        $this->authorize('manageFlow', $municipality);
        abort_unless($step->municipality_id === $municipality->id, 404);
        $step->update($this->stepData($request));

        return back()->with('ok', 'Etapa atualizada.');
    }

    public function destroyStep(Municipality $municipality, FlowStep $step)
    {
        $this->authorize('manageFlow', $municipality);
        abort_unless($step->municipality_id === $municipality->id, 404);
        // Encaminhamentos já gerados permanecem (histórico); só a etapa deixa de valer.
        $step->delete();

        return back()->with('ok', 'Etapa removida.');
    }

    private function stepData(Request $request): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'destination' => ['required', Rule::enum(ReferralDestination::class)],
            'destination_detail' => ['nullable', 'string', 'max:255'],
            'due_days' => ['required', 'integer', 'between:0,365'],
            'position' => ['nullable', 'integer', 'between:1,999'],
            'violence_types' => ['nullable', 'array'],
            'violence_types.*' => [Rule::enum(ViolenceType::class)],
            'only_urgent' => ['boolean'],
            'active' => ['boolean'],
        ]);
        $d['only_urgent'] = (bool) ($d['only_urgent'] ?? false);
        $d['active'] = (bool) ($d['active'] ?? true);
        $d['violence_types'] = $d['violence_types'] ?? null ?: null;
        if (! isset($d['position'])) {
            unset($d['position']);
        }

        return $d;
    }

    private function validated(Request $request, ?Municipality $m = null): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'uf' => ['required', 'string', 'size:2'],
            'ibge_code' => ['nullable', 'digits:7', Rule::unique('municipalities', 'ibge_code')->ignore($m?->id)],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'active' => ['boolean'],
            'accepts_public_reports' => ['boolean'],
        ]);
        $d['uf'] = strtoupper($d['uf']);
        $d['active'] = (bool) ($d['active'] ?? false);
        $d['accepts_public_reports'] = (bool) ($d['accepts_public_reports'] ?? false);

        return $d;
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $orig = Str::slug($base);
        for ($i = 2; Municipality::where('slug', $slug)->exists(); $i++) {
            $slug = "{$orig}-{$i}";
        }

        return $slug;
    }
}
