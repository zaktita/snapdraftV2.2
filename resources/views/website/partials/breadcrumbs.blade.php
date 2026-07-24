@php
    /** @var list<array{label: string, href?: string|null}> $crumbs */
    $crumbs = $breadcrumbs ?? [];
@endphp
@if (count($crumbs) > 0)
    <nav class="sd-breadcrumbs" aria-label="Breadcrumb">
        <ol>
            @foreach ($crumbs as $i => $crumb)
                <li>
                    @if (! empty($crumb['href']) && ! $loop->last)
                        <a href="{{ $crumb['href'] }}">{{ $crumb['label'] }}</a>
                    @else
                        <span aria-current="{{ $loop->last ? 'page' : 'false' }}">{{ $crumb['label'] }}</span>
                    @endif
                    @unless ($loop->last)
                        <span class="sd-breadcrumbs-sep" aria-hidden="true">/</span>
                    @endunless
                </li>
            @endforeach
        </ol>
    </nav>
@endif
