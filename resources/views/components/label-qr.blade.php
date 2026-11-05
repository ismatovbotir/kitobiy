@php
    // A stand-in QR pattern (21×21 modules) for previews without a real book; printed labels use real codes.
    $size = 21;
    $isFinder = fn (int $x, int $y): bool => ($x < 7 && $y < 7) || ($x >= $size - 7 && $y < 7) || ($x < 7 && $y >= $size - 7);
    $modules = [];
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            if ($isFinder($x, $y)) {
                $fx = $x >= $size - 7 ? $x - ($size - 7) : $x;
                $fy = $y >= $size - 7 ? $y - ($size - 7) : $y;
                $ring = max(abs($fx - 3), abs($fy - 3));
                if ($ring !== 2) {
                    $modules[] = [$x, $y];
                }
            } elseif (! ($x === 7 || $y === 7 || $x === $size - 8 || $y === $size - 8) && (($x * 7 + $y * 13 + $x * $y) % 5) < 2) {
                $modules[] = [$x, $y];
            }
        }
    }
    $path = implode('', array_map(fn (array $module): string => "M{$module[0]} {$module[1]}h1v1h-1z", $modules));
@endphp
<svg {{ $attributes->merge(['class' => 'block size-full']) }} viewBox="0 0 {{ $size }} {{ $size }}" shape-rendering="crispEdges" aria-hidden="true">
    <path fill="currentColor" d="{{ $path }}" />
</svg>
