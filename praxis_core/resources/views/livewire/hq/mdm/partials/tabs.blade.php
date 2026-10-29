{{-- Master data tabs, shared by the master data screens. Names match the HQ rail. --}}
<nav class="tabs" aria-label="Master data">
    <a href="{{ route('hq.mdm.dictionary') }}" wire:navigate class="tab {{ request()->routeIs('hq.mdm.dictionary') ? 'is-active' : '' }}" @if(request()->routeIs('hq.mdm.dictionary')) aria-current="page" @endif>Financial dictionary</a>
    <a href="{{ route('hq.mdm.copq') }}" wire:navigate class="tab {{ request()->routeIs('hq.mdm.copq') ? 'is-active' : '' }}" @if(request()->routeIs('hq.mdm.copq')) aria-current="page" @endif>Cost of poor quality tables</a>
    <a href="{{ route('hq.mdm.tax') }}" wire:navigate class="tab {{ request()->routeIs('hq.mdm.tax') ? 'is-active' : '' }}" @if(request()->routeIs('hq.mdm.tax')) aria-current="page" @endif>Tax compliance</a>
    <a href="{{ route('hq.mdm.remittance') }}" wire:navigate class="tab {{ request()->routeIs('hq.mdm.remittance') ? 'is-active' : '' }}" @if(request()->routeIs('hq.mdm.remittance')) aria-current="page" @endif>Remittance accounts</a>
</nav>
