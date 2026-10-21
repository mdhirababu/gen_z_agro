@isset($subscriptionStatus)
    @php $days = $subscriptionDaysLeft ?? null; @endphp

    @if ($subscriptionStatus === 'trial')
        <div class="alert alert-info">
            🎁 ফ্রি ট্রায়াল চলছে@if ($days !== null)
                : আর {{ $days }} দিন বাকি
            @endif।
            @can('manageBilling', $currentFarm)
                <a href="{{ route('farms.billing.index', $currentFarm) }}">প্ল্যান নিন</a>
            @endcan
        </div>
    @elseif ($subscriptionStatus === 'active' && $days !== null && $days <= 7)
        <div class="alert alert-warning">
            সাবস্ক্রিপশনের মেয়াদ আর {{ $days }} দিন বাকি।
            @can('manageBilling', $currentFarm)
                <a href="{{ route('farms.billing.index', $currentFarm) }}">নবায়ন করুন</a>
            @endcan
        </div>
    @elseif ($subscriptionStatus === 'past_due')
        <div class="alert alert-warning">
            মেয়াদ শেষ হয়েছে। অল্প কয়েক দিনের grace period চলছে, এরপর খামার read-only হয়ে যাবে।
            @can('manageBilling', $currentFarm)
                <a href="{{ route('farms.billing.index', $currentFarm) }}">এখনই নবায়ন করুন</a>
            @endcan
        </div>
    @elseif (in_array($subscriptionStatus, ['expired', 'cancelled'], true))
        <div class="alert alert-danger">
            সাবস্ক্রিপশনের মেয়াদ শেষ। খামারটি এখন শুধু দেখা যাবে, নতুন তথ্য যোগ করা যাবে না। আপনার ডেটা নিরাপদ আছে।
            @can('manageBilling', $currentFarm)
                <a href="{{ route('farms.billing.index', $currentFarm) }}">নবায়ন করুন</a>
            @endcan
        </div>
    @endif
@endisset
