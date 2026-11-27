<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConcentrationRequest;
use App\Models\Concentration;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConcentrationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Concentration::class);

        $concentrations = Concentration::where('school_id', $request->user()->school_id)
            ->withCount('classes')
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Master/Concentrations/Index', ['concentrations' => $concentrations]);
    }

    public function create(): Response
    {
        $this->authorize('create', Concentration::class);

        return Inertia::render('Master/Concentrations/Form', ['concentration' => null]);
    }

    public function store(ConcentrationRequest $request): RedirectResponse
    {
        $concentration = Concentration::create($request->safe()->all() + ['school_id' => $request->user()->school_id]);

        AuditLogger::log('concentration.created', Concentration::class, $concentration, after: $concentration->only(['name']));

        return redirect()->route('master.concentrations.index')->with('success', 'Konsentrasi dibuat.');
    }

    public function edit(Concentration $concentration): Response
    {
        $this->authorize('update', $concentration);

        return Inertia::render('Master/Concentrations/Form', ['concentration' => $concentration]);
    }

    public function update(ConcentrationRequest $request, Concentration $concentration): RedirectResponse
    {
        $before = $concentration->only(['name']);
        $concentration->update($request->validated());

        AuditLogger::log('concentration.updated', Concentration::class, $concentration, before: $before, after: $concentration->only(['name']));

        return redirect()->route('master.concentrations.index')->with('success', 'Konsentrasi diperbarui.');
    }

    public function destroy(Concentration $concentration): RedirectResponse
    {
        $this->authorize('delete', $concentration);

        if ($concentration->classes()->exists()) {
            return back()->with('error', 'Konsentrasi masih dipakai kelas. Lepaskan dari kelas terlebih dahulu.');
        }

        $concentration->delete();

        AuditLogger::log('concentration.deleted', Concentration::class, $concentration->id);

        return redirect()->route('master.concentrations.index')->with('success', 'Konsentrasi dihapus.');
    }
}
