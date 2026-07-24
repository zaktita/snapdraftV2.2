{{-- Visual compare: generated banner, or competitor mark vs SnapDraft mark --}}
@php
    $compareImage = $alternative['compare_image'] ?? null;
    $compareAlt = $alternative['compare_image_alt']
        ?? (($alternative['name'] ?? 'Tool').' vs SnapDraft comparison');
    $altImage = $alternative['image'] ?? null;
    $altName = $alternative['name'] ?? 'Tool';
    $altAlt = $alternative['image_alt'] ?? ($altName.' comparison mark');
    $snapImage = '/images/marketing/alternatives/snapdraft.svg';
@endphp
@if ($compareImage)
    <div class="sd-compare-vs sd-compare-vs-banner reveal" aria-label="{{ $compareAlt }}">
        <img
            src="{{ $compareImage }}"
            alt="{{ $compareAlt }}"
            width="1600"
            height="900"
            loading="lazy"
            decoding="async"
        >
    </div>
@elseif ($altImage)
    <div class="sd-compare-vs reveal" aria-label="{{ $altName }} versus SnapDraft">
        <div class="sd-compare-vs-side">
            <img
                src="{{ $altImage }}"
                alt="{{ $altAlt }}"
                width="480"
                height="270"
                loading="lazy"
                decoding="async"
            >
            <span>{{ $altName }}</span>
        </div>
        <div class="sd-compare-vs-badge" aria-hidden="true">vs</div>
        <div class="sd-compare-vs-side">
            <img
                src="{{ $snapImage }}"
                alt="SnapDraft comparison mark"
                width="480"
                height="270"
                loading="lazy"
                decoding="async"
            >
            <span>SnapDraft</span>
        </div>
    </div>
@endif
