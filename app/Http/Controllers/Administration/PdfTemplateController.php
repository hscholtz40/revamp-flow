<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\PdfTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PdfTemplateController extends Controller
{
    /**
     * Display a listing of PDF templates grouped by module.
     */
    public function index(): Response
    {
        $company = auth()->user()->getCurrentCompany();
        $modules = PdfTemplate::getModules();
        $templates = PdfTemplate::where('company_id', $company->id)
            ->orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy('module');

        return Inertia::render('administration/pdf-templates/Index', [
            'modules' => $modules,
            'templates' => $templates,
        ]);
    }

    /**
     * Show the form for creating a new PDF template.
     */
    public function create(Request $request): Response
    {
        $module = $request->get('module', 'invoice');
        $modules = PdfTemplate::getModules();

        return Inertia::render('administration/pdf-templates/Create', [
            'modules' => $modules,
            'selectedModule' => $module,
        ]);
    }

    /**
     * Store a newly created PDF template.
     */
    public function store(Request $request): RedirectResponse
    {
        $company = auth()->user()->getCurrentCompany();
        
        $validated = $request->validate([
            'module' => ['required', 'string', 'in:invoice,quote,jobcard,proforma-invoice,purchase-order'],
            'name' => ['required', 'string', 'max:255'],
            'html_template' => ['required', 'string'],
            'css_styles' => ['nullable', 'string'],
            'default_data' => ['nullable', 'array'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ]);

        // If setting as default, unset other defaults for this module and company
        if ($validated['is_default'] ?? false) {
            PdfTemplate::where('company_id', $company->id)
                ->where('module', $validated['module'])
                ->update(['is_default' => false]);
        }

        PdfTemplate::create([
            ...$validated,
            'company_id' => $company->id,
        ]);

        return redirect()->route('administration.pdf-templates.index')
            ->with('success', 'PDF template created successfully.');
    }

    /**
     * Show the form for editing the specified PDF template.
     */
    public function edit(PdfTemplate $pdfTemplate): Response
    {
        $modules = PdfTemplate::getModules();

        return Inertia::render('administration/pdf-templates/Edit', [
            'template' => $pdfTemplate,
            'modules' => $modules,
        ]);
    }

    /**
     * Update the specified PDF template.
     */
    public function update(Request $request, PdfTemplate $pdfTemplate): RedirectResponse
    {
        $company = auth()->user()->getCurrentCompany();
        
        // Ensure template belongs to current company
        if ($pdfTemplate->company_id !== $company->id) {
            abort(403, 'You do not have access to this template.');
        }
        
        $validated = $request->validate([
            'module' => ['required', 'string', 'in:invoice,quote,jobcard,proforma-invoice,purchase-order'],
            'name' => ['required', 'string', 'max:255'],
            'html_template' => ['required', 'string'],
            'css_styles' => ['nullable', 'string'],
            'default_data' => ['nullable', 'array'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ]);

        // If setting as default, unset other defaults for this module and company
        if ($validated['is_default'] ?? false) {
            PdfTemplate::where('company_id', $company->id)
                ->where('module', $validated['module'])
                ->where('id', '!=', $pdfTemplate->id)
                ->update(['is_default' => false]);
        }

        $pdfTemplate->update($validated);

        return redirect()->route('administration.pdf-templates.index')
            ->with('success', 'PDF template updated successfully.');
    }

    /**
     * Remove the specified PDF template.
     */
    public function destroy(PdfTemplate $pdfTemplate): RedirectResponse
    {
        $company = auth()->user()->getCurrentCompany();
        
        // Ensure template belongs to current company
        if ($pdfTemplate->company_id !== $company->id) {
            abort(403, 'You do not have access to this template.');
        }
        
        $pdfTemplate->delete();

        return redirect()->route('administration.pdf-templates.index')
            ->with('success', 'PDF template deleted successfully.');
    }

    /**
     * Upload an image for PDF templates.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:5120'], // 5MB max
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('pdf-template-images', 'public');
            $url = Storage::url($path);
            
            return response()->json([
                'data' => [
                    [
                        'src' => asset($url),
                        'type' => 'image',
                    ],
                ],
            ]);
        }

        return response()->json(['error' => 'No image provided'], 400);
    }
}

