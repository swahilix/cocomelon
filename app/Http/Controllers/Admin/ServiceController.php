<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Service;
use App\Services\ImageOptimizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function __construct(private readonly ImageOptimizationService $images)
    {
    }

    public function index(Request $request): Response
    {
        $trash = $request->input('view') === 'trash';
        $query = $trash ? Service::onlyTrashed() : Service::query();

        $services = $query
            ->with('category:id,name')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->when(! $trash && $request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->integer('category')))
            ->orderByDesc($trash ? 'deleted_at' : 'created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Services/Index', [
            'services' => $services,
            'categories' => Category::serviceType()->active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only('search', 'status', 'category', 'view'),
            'counts' => [
                'active' => Service::count(),
                'published' => Service::active()->count(),
                'drafts' => Service::where('status', 'draft')->count(),
                'trash' => Service::onlyTrashed()->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Services/Form', $this->formData());
    }

    public function edit(Service $service): Response
    {
        return Inertia::render('Admin/Services/Form', [
            ...$this->formData(),
            'service' => $service->load('category'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $service = Service::create($this->data($request));

        return redirect()->route('admin.services.edit', $service)->with('success', 'Service created successfully.');
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $service->update($this->data($request, $service));

        return back()->with('success', 'Service updated successfully.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return redirect()->route('admin.services.index')->with('success', 'Service moved to Trash. It can be restored at any time.');
    }

    public function restore(int $service): RedirectResponse
    {
        $item = Service::onlyTrashed()->findOrFail($service);
        $item->restore();

        return back()->with('success', 'Service restored successfully.');
    }

    private function formData(): array
    {
        return [
            'categories' => Category::serviceType()->active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function data(Request $request, ?Service $service = null): array
    {
        $data = $request->validate([
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('type', 'service')->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('services')->ignore($service)],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['required', 'string', 'max:10000'],
            'price_from' => ['nullable', 'numeric', 'min:0'],
            'price_label' => ['nullable', 'string', 'max:100'],
            'duration' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=500,min_height=500'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'icon' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'in:draft,active,archived'],
            'featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:180'],
        ]);

        // Empty nullable file inputs are submitted as null by Inertia. Never let
        // that null overwrite the stored image on an ordinary metadata update.
        unset($data['image'], $data['image_url']);

        if ($request->hasFile('image')) {
            $oldImage = $service?->image;
            $category = Category::find($data['category_id'] ?? null);
            try {
                $data['image'] = $this->images->store(
                    $request->file('image'),
                    'services',
                    $data['name'],
                    [
                        'title' => $data['name'].' service | Prinx Electronics',
                        'description' => trim((string) ($data['meta_description'] ?? $data['short_description'] ?? $data['description'] ?? '')),
                        'keywords' => array_values(array_filter([
                            $data['name'] ?? null,
                            $category?->name,
                            'technical services',
                            'Prinx Electronics',
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
