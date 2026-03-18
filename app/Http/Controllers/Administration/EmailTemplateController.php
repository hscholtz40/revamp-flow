<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function index(): Response
    {
        $company = auth()->user()->getCurrentCompany();
        $templates = EmailTemplate::where('company_id', $company->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return Inertia::render('administration/email-templates/Index', [
            'templates' => $templates,
            'variableGroups' => EmailTemplate::variableGroups(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('administration/email-templates/Create', [
            'variableGroups' => EmailTemplate::variableGroups(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'html_template' => ['required', 'string'],
            'css_styles' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ]);

        if (($validated['is_default'] ?? false) === true) {
            EmailTemplate::where('company_id', $company->id)->update(['is_default' => false]);
        }

        EmailTemplate::create([
            ...$validated,
            'company_id' => $company->id,
        ]);

        return redirect('/administration/email-templates')->with('success', 'Email template created successfully.');
    }

    public function edit(EmailTemplate $emailTemplate): Response
    {
        $company = auth()->user()->getCurrentCompany();
        if ($emailTemplate->company_id !== $company->id) {
            abort(403, 'You do not have access to this template.');
        }

        return Inertia::render('administration/email-templates/Edit', [
            'template' => $emailTemplate,
            'variableGroups' => EmailTemplate::variableGroups(),
        ]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $company = auth()->user()->getCurrentCompany();
        if ($emailTemplate->company_id !== $company->id) {
            abort(403, 'You do not have access to this template.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'html_template' => ['required', 'string'],
            'css_styles' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ]);

        if (($validated['is_default'] ?? false) === true) {
            EmailTemplate::where('company_id', $company->id)
                ->where('id', '!=', $emailTemplate->id)
                ->update(['is_default' => false]);
        }

        $emailTemplate->update($validated);

        return redirect('/administration/email-templates')->with('success', 'Email template updated successfully.');
    }

    public function destroy(EmailTemplate $emailTemplate): RedirectResponse
    {
        $company = auth()->user()->getCurrentCompany();
        if ($emailTemplate->company_id !== $company->id) {
            abort(403, 'You do not have access to this template.');
        }

        $emailTemplate->delete();

        return redirect('/administration/email-templates')->with('success', 'Email template deleted successfully.');
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:5120'],
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('email-template-images', 'public');
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

