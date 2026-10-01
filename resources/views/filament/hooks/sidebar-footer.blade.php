<div class="pt-sidebar-footer">
    <div class="pt-sysline">
        <span class="pt-live-dot"></span>
        <span>All systems nominal</span>
    </div>
    <div class="pt-sysmeta">
        <span>WK {{ now()->isoWeek() }}</span>
        <span>{{ now()->format('d.m.Y') }}</span>
        <span>v{{ config('app.version', '1.0') }}</span>
    </div>
</div>
