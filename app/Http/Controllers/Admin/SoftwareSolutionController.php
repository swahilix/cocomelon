<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SoftwareSolution;
use App\Services\ImageOptimizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SoftwareSolutionController extends Controller
{
    public function __construct(private readonly ImageOptimizationService $images)
    {
    }

    public function index(Request $request): Response
    {
        $trash = $request->input('view') === 'trash';
        $query = $trash ? SoftwareSolution::onlyTrashed() : SoftwareSolution::query();

        $solutions = $query
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->when(! $trash && $request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy($trash ? 'deleted_at' : 'sort_order', $trash ? 'desc' : 'asc')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Software/Index', [
            'solutions' => $solutions,
            'filters' => $request->only('search', 'status', 'view'),
            'counts' => [
                'active' => SoftwareSolution::count(),
                'published' => SoftwareSolution::active()->count(),
                'drafts' => SoftwareSolution::where('status', 'draft')->count(),
                'trash' => SoftwareSolution::onlyTrashed()->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Software/Form');
    }

    public function edit(SoftwareSolution $software): Response
    {
        return Inertia::render('Admin/Software/Form', ['software' => $software]);
    }

    public function store(Request $request): RedirectResponse
    {
        $solution = SoftwareSolution::create($this->data($request));

        return redirect()->route('admin.software.edit', $solution)->with('success', 'Software solution created successfully.');
    }

    public function update(Request $request, SoftwareSolution $software): RedirectResponse
    {
        $software->update($this->data($request, $software));

        return back()->with('success', 'Software solution updated successfully.');
    }

    public function destroy(SoftwareSolution $software): RedirectResponse
    {
        $software->delete();

        return redirect()->route('admin.software.index')->with('success', 'Software solution moved to Trash.');
    }

    public function restore(int $software): RedirectResponse
    {
        $item = SoftwareSolution::onlyTrashed()->findOrFail($software);
        $item->restore();

        return back()->with('success', 'Software solution restored successfully.');
    }

    private function data(Request $request, ?SoftwareSolution $software = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('software_solutions')->ignore($software)],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:12000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'demo_url' => ['nullable', 'url', 'max:2048'],
            'status' => ['required', 'in:draft,active,archived'],
            'featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:180'],
        ]);

        // Preserve the current image unless the request explicitly supplies a
        // replacement file or replacement URL.
        unset($data['image'], $data['image_url']);

        if ($request->hasFile('image')) {
            $oldImage = $software?->image;

            try {
                $data['image'] = $this->images->store(
                    $request->file('image'),
                    'software',
                    $data['name'],
                    [
                        'title' => $data['name'].' | Prinx Software',
                        'description' => trim((string) ($data['meta_description'] ?? $data['short_description'] ?? $data['description'] ?? '')),
                        'keywords' => array_values(array_filter([
                            $data['name'] ?? null,
                            'custom software',
                            'business software',
                            'Prinx Software',
                            'Tanzania',
                        ])),
                    ]
                );
            } catch (Throwable $exception) {
                throw ValidationException::withMessages(['image' => $exception->getMessage()]);
            }

            $this->images->deleteLocal($oldImage);
        } elseif ($request->filled('image_url')) {
            $data['image'] = $request->string('image_url')->toString();
        }

        return $data;
    }
}
