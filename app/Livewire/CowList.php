<?php

namespace App\Livewire;

use App\Models\Farm;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CowList extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    // #[Locked]: ব্রাউজার থেকে এই সংখ্যা বদলে অন্য খামারের তথ্য চাওয়া যায় না
    #[Locked]
    public int $farmId;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: 'active')]
    public string $status = 'active';

    public function mount(int $farmId): void
    {
        $this->farmId = $farmId;
        $this->farm(); // সদস্য না হলে এখানেই 403
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function setCategory(string $category): void
    {
        $this->category = $category;
        $this->resetPage();
    }

    /** Livewire-এর প্রতিটি request-এ খামার ও সদস্যপদ আবার যাচাই হয় */
    protected function farm(): Farm
    {
        $farm = Farm::findOrFail($this->farmId);
        Gate::authorize('view', $farm);
        app()->instance('currentFarm', $farm);

        return $farm;
    }

    public function render()
    {
        $farm = $this->farm();
        $user = auth()->user();
        $role = $farm->roleOf($user);

        $status = ($this->status === 'all' || array_key_exists($this->status, config('farm.statuses')))
            ? $this->status
            : 'active';

        $base = $farm->cows()->when($status !== 'all', fn($q) => $q->where('status', $status));

        // "গর্ভবতী" কোনো গরুর ধরন নয়, প্রজননের রেকর্ড থেকে আসে
        $pregnantIds = $farm->breedingEvents()
            ->where('status', 'pregnant')
            ->pluck('cow_id')
            ->unique()
            ->values()
            ->all();

        $counts = (clone $base)->selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category');
        $pregnantCount = (clone $base)->whereIn('id', $pregnantIds)->count();

        $search = trim($this->search);

        $cows = (clone $base)
            ->when($this->category === 'pregnant', fn($q) => $q->whereIn('id', $pregnantIds))
            ->when(array_key_exists($this->category, config('farm.categories')), fn($q) => $q->where('category', $this->category))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where(fn($w) => $w->where('tag_number', 'like', $like)->orWhere('name', 'like', $like));
            })
            ->orderBy('tag_number')
            ->paginate(12);

        return view('livewire.cow-list', [
            'farm' => $farm,
            'cows' => $cows,
            'counts' => $counts,
            'total' => (int) $counts->sum(),
            'pregnantIds' => $pregnantIds,
            'pregnantCount' => $pregnantCount,
            'categories' => config('farm.categories'),
            'statuses' => config('farm.statuses'),
            'canCreate' => $role !== null || $user->is_super_admin,
            'canEdit' => in_array($role, [Farm::ROLE_OWNER, Farm::ROLE_MANAGER], true) || $user->is_super_admin,
        ]);
    }
}
